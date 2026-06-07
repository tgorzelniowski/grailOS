<?php
require_once __DIR__ . '/../config/mail.php';

// ============================================================
//  Główna funkcja wysyłki emaila
// ============================================================
function send_mail(string $to_email, string $to_name, string $subject, string $html): bool {
    if (MAIL_SMTP) {
        return _smtp_send($to_email, $subject, $html);
    }
    return _native_send($to_email, $to_name, $subject, $html);
}

function _native_send(string $to, string $to_name, string $subject, string $html): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . ">\r\n";
    $headers .= 'Reply-To: ' . MAIL_FROM . "\r\n";
    $headers .= "X-Mailer: GrailOS/1.0\r\n";

    $enc_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail("\"$to_name\" <$to>", $enc_subject, $html, $headers);
}

// Ostatni błąd SMTP (do diagnostyki / strony testowej)
function mail_last_error(?string $set = null): string {
    static $err = '';
    if ($set !== null) $err = $set;
    return $err;
}

function _smtp_send(string $to, string $subject, string $html): bool {
    mail_last_error('');
    $socket = null;
    try {
        $use_ssl   = (int)MAIL_PORT === 465;
        $transport = $use_ssl ? 'ssl://' : 'tcp://';

        $ctx = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
            'SNI_enabled'      => true,
        ]]);

        $socket = @stream_socket_client(
            $transport . MAIL_HOST . ':' . MAIL_PORT,
            $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$socket) {
            mail_last_error("Połączenie nieudane: $errstr ($errno) — host/port/SSL?");
            error_log('SMTP: ' . mail_last_error());
            return false;
        }
        stream_set_timeout($socket, 20);

        // Czyta pełną (wielolinijkową) odpowiedź serwera
        $readResp = function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                // ostatnia linia odpowiedzi ma spację na 4. pozycji ("250 OK"),
                // linie pośrednie myślnik ("250-...")
                if (strlen($line) < 4 || $line[3] === ' ') break;
            }
            return $data;
        };
        $cmd = fn(string $c) => fwrite($socket, $c . "\r\n");

        // Sprawdza kod odpowiedzi; przy błędzie zapisuje treść i rzuca wyjątek
        $expect = function ($want) use ($readResp): void {
            $r = $readResp();
            $code = (int)substr($r, 0, 3);
            if (!in_array($code, (array)$want, true)) {
                mail_last_error(trim($r) ?: 'brak odpowiedzi serwera');
                throw new \RuntimeException(mail_last_error());
            }
        };

        // nazwa HELO z domeny nadawcy
        $helo = substr(strrchr(MAIL_FROM, '@') ?: '@localhost', 1);

        $expect(220);                         // powitanie
        $cmd('EHLO ' . $helo); $expect(250);

        if (!$use_ssl) {                      // STARTTLS dla 587
            $cmd('STARTTLS'); $expect(220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                mail_last_error('STARTTLS — nie udało się nawiązać szyfrowania');
                throw new \RuntimeException(mail_last_error());
            }
            $cmd('EHLO ' . $helo); $expect(250);
        }

        $cmd('AUTH LOGIN');               $expect(334);
        $cmd(base64_encode(MAIL_USER));   $expect(334);
        $cmd(base64_encode(MAIL_PASS));   $expect(235);   // 235 = autoryzacja OK

        $cmd('MAIL FROM:<' . MAIL_FROM . '>'); $expect(250);
        $cmd('RCPT TO:<' . $to . '>');         $expect([250, 251]);
        $cmd('DATA');                          $expect(354);

        $msg  = 'Date: ' . date('r') . "\r\n";
        $msg .= 'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . ">\r\n";
        $msg .= "To: <$to>\r\n";
        $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "\r\n" . $html;

        // KLUCZOWE dla qmail (LH): wszystkie końce linii muszą być CRLF,
        // inaczej serwer odrzuca z błędem 451 (bare line feed / smtplf).
        $msg = preg_replace('/\r\n|\r|\n/', "\r\n", $msg);
        // dot-stuffing: linie zaczynające się od kropki podwajamy
        $msg = str_replace("\r\n.", "\r\n..", $msg);

        fwrite($socket, $msg . "\r\n.\r\n");
        $expect(250);                          // serwer przyjął wiadomość

        $cmd('QUIT');
        fclose($socket);
        return true;

    } catch (\Throwable $e) {
        if (mail_last_error() === '') mail_last_error($e->getMessage());
        error_log('SMTP: ' . mail_last_error());
        if (is_resource($socket)) @fclose($socket);
        return false;
    }
}

// ============================================================
//  Szablon HTML emaila
// ============================================================
function mail_template(string $title, string $body_html, string $btn_label = '', string $btn_url = ''): string {
    $site  = MAIL_SITE_NAME;
    $url   = MAIL_SITE_URL;
    $year  = date('Y');
    $btn   = $btn_label
        ? "<div style='margin:32px 0;text-align:center;'>
             <a href='$btn_url'
                style='display:inline-block;background:#FF2400;color:#fff;
                       font-family:Arial,sans-serif;font-size:13px;
                       letter-spacing:2px;text-transform:uppercase;
                       padding:14px 32px;text-decoration:none;font-weight:bold;'>
               $btn_label
             </a>
           </div>"
        : '';

    return "<!DOCTYPE html>
<html lang='pl'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>$title — $site</title></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f4;padding:40px 0;'>
<tr><td align='center'>
<table width='560' cellpadding='0' cellspacing='0' style='max-width:560px;width:100%;'>

  <!-- HEADER -->
  <tr><td style='background:#0a0a0a;padding:28px 40px;border-bottom:3px solid #FF2400;'>
    <a href='$url' style='font-family:Impact,Arial,sans-serif;font-size:28px;
                          letter-spacing:3px;color:#fff;text-decoration:none;'>
      GRAIL<span style='color:#FF2400;'>OS</span>
    </a>
  </td></tr>

  <!-- BODY -->
  <tr><td style='background:#fff;padding:40px;'>
    <h2 style='margin:0 0 20px;font-size:22px;color:#0a0a0a;
               letter-spacing:1px;text-transform:uppercase;'>$title</h2>
    $body_html
    $btn
  </td></tr>

  <!-- FOOTER -->
  <tr><td style='background:#0a0a0a;padding:20px 40px;text-align:center;'>
    <p style='margin:0;font-size:11px;color:#555;letter-spacing:1px;'>
      © $year $site &nbsp;·&nbsp;
      <a href='$url/privacy.php' style='color:#FF2400;text-decoration:none;'>Polityka prywatności</a>
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";
}