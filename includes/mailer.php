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

function _smtp_send(string $to, string $subject, string $html): bool {
    try {
        $socket = @stream_socket_client(
            'tcp://' . MAIL_HOST . ':' . MAIL_PORT,
            $errno, $errstr, 15
        );
        if (!$socket) {
            error_log("SMTP connect failed: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($socket, 15);

        $read = fn() => fgets($socket, 512);
        $write = function(string $cmd) use ($socket): void { fwrite($socket, $cmd . "\r\n"); };

        $read(); // greeting
        $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        while (($l = $read()) && $l[3] !== ' ');

        $write('STARTTLS');
        $read();
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        $write('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        while (($l = $read()) && $l[3] !== ' ');

        $write('AUTH LOGIN');
        $read();
        $write(base64_encode(MAIL_USER));
        $read();
        $write(base64_encode(MAIL_PASS));
        $r = $read();
        if (substr($r, 0, 3) !== '235') {
            error_log('SMTP auth failed: ' . trim($r));
            fclose($socket);
            return false;
        }

        $write('MAIL FROM: <' . MAIL_FROM . '>');
        $read();
        $write("RCPT TO: <$to>");
        $read();
        $write('DATA');
        $read();

        $msg  = "To: $to\r\n";
        $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= 'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . ">\r\n";
        $msg .= "\r\n" . $html . "\r\n.";
        $write($msg);
        $read();

        $write('QUIT');
        fclose($socket);
        return true;

    } catch (\Throwable $e) {
        error_log('SMTP error: ' . $e->getMessage());
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