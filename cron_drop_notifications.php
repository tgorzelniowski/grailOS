<?php
/**
 * GrailOS — wysyłka powiadomień o nadchodzących dropach
 *
 * Uruchom jako zadanie zaplanowane (Windows Task Scheduler / Linux cron):
 *   php c:/xampp/htdocs/grailos/cron_drop_notifications.php
 *
 * Lub dodaj w admin panelu przycisk "Wyślij powiadomienia".
 * Skrypt wysyła emaile na 24 godziny przed dropem (raz).
 */

define('CRON_KEY', 'zmien_ten_klucz_na_losowy_ciag'); // zabezpieczenie przed wywołaniem przez HTTP

if (PHP_SAPI !== 'cli') {
    // Dostęp przez HTTP tylko z kluczem
    $key = $_GET['key'] ?? '';
    if (!hash_equals(CRON_KEY, $key)) {
        http_response_code(403);
        exit('Brak dostępu.');
    }
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mailer.php';

$sent  = 0;
$error = 0;

// Dropy w ciągu najbliższych 24 godzin, jeszcze nie powiadomialiśmy
$drops = $pdo->query("
    SELECT DISTINCT d.id, d.name, d.brand, d.drop_date, d.link
    FROM drops d
    JOIN drop_notifications dn ON d.id = dn.drop_id
    WHERE d.drop_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
      AND dn.notified = 0
")->fetchAll();

foreach ($drops as $drop) {
    $drop_url  = MAIL_SITE_URL . '/drop.php?id=' . $drop['id'];
    $drop_date = date('d.m.Y H:i', strtotime($drop['drop_date']));
    $brand     = htmlspecialchars($drop['brand']);
    $name      = htmlspecialchars($drop['name']);

    // Pobierz subskrybentów tego dropu
    $subs = $pdo->prepare("
        SELECT dn.id, dn.user_id, dn.email, dn.unsubscribe_token, u.email AS user_email, u.username
        FROM drop_notifications dn
        LEFT JOIN users u ON dn.user_id = u.id
        WHERE dn.drop_id = ? AND dn.notified = 0
    ");
    $subs->execute([$drop['id']]);

    foreach ($subs->fetchAll() as $sub) {
        $to_email = $sub['user_id'] ? $sub['user_email'] : $sub['email'];
        $to_name  = $sub['username'] ?? 'Grailhead';

        if (!$to_email) continue;

        $unsub_url = MAIL_SITE_URL . '/drop_unsubscribe.php?token=' . $sub['unsubscribe_token'];

        $body = "<p style='color:#444;font-size:15px;line-height:1.7;'>
            Cześć <strong>$to_name</strong>!<br><br>
            Drop, na który czekasz, zbliża się wielkimi krokami.
        </p>
        <table style='width:100%;border-top:3px solid #FF2400;margin:24px 0;padding-top:16px;'>
          <tr>
            <td style='font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#FF2400;padding-bottom:6px;'>Marka</td>
            <td style='font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#FF2400;padding-bottom:6px;'>Data dropu</td>
          </tr>
          <tr>
            <td style='font-size:22px;font-weight:bold;color:#0a0a0a;letter-spacing:1px;'>$brand</td>
            <td style='font-size:22px;font-weight:bold;color:#FF2400;letter-spacing:1px;'>$drop_date</td>
          </tr>
          <tr><td colspan='2' style='font-size:18px;color:#333;padding-top:4px;'>$name</td></tr>
        </table>
        <p style='font-size:12px;color:#aaa;margin-top:32px;'>
          Nie chcesz więcej otrzymywać tych powiadomień?
          <a href='$unsub_url' style='color:#FF2400;'>Wypisz się</a>
        </p>";

        $html = mail_template(
            'Drop za mniej niż 24h!',
            $body,
            'Zobacz drop →',
            $drop['link'] ?: $drop_url
        );

        if (send_mail($to_email, $to_name, "Drop alert: $brand $name — GrailOS", $html)) {
            $pdo->prepare("UPDATE drop_notifications SET notified = 1 WHERE id = ?")
                ->execute([$sub['id']]);
            $sent++;
        } else {
            $error++;
        }
    }
}

$msg = "Wysłano: $sent, Błędy: $error";
echo PHP_SAPI === 'cli' ? $msg . PHP_EOL : "<p>$msg</p>";