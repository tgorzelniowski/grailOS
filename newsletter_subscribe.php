<?php
require_once 'config/db.php';
require_once 'includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

csrf_verify();

if (!check_rate_limit('newsletter', 3, 3600)) {
    echo json_encode(['ok' => false, 'error' => 'Za dużo prób. Spróbuj za godzinę.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$name  = substr(trim($_POST['name'] ?? ''), 0, 100);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Podaj prawidłowy adres email.']);
    exit;
}

// Sprawdź czy już zapisany
$stmt = $pdo->prepare("SELECT id, confirmed FROM newsletter_subscribers WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing && $existing['confirmed']) {
    echo json_encode(['ok' => true, 'msg' => 'Ten email jest już zapisany do newslettera.']);
    exit;
}

$confirm_token     = bin2hex(random_bytes(32));
$unsubscribe_token = bin2hex(random_bytes(32));

if ($existing) {
    // Zaktualizuj token (nie potwierdzony wcześniej)
    $pdo->prepare("UPDATE newsletter_subscribers SET confirm_token = ?, name = ? WHERE email = ?")
        ->execute([$confirm_token, $name ?: null, $email]);
} else {
    $pdo->prepare("
        INSERT INTO newsletter_subscribers (email, name, confirm_token, unsubscribe_token)
        VALUES (?, ?, ?, ?)
    ")->execute([$email, $name ?: null, $confirm_token, $unsubscribe_token]);
}

$confirm_url = MAIL_SITE_URL . '/newsletter_confirm.php?token=' . $confirm_token;
$display     = $name ?: 'Hej';

$body = "<p style='color:#444;font-size:15px;line-height:1.7;'>
    Cześć <strong>$display</strong>!<br><br>
    Dziękujemy za zapisanie się do newslettera GrailOS.<br>
    Kliknij przycisk poniżej, aby potwierdzić swój adres email i aktywować subskrypcję.
</p>
<p style='color:#888;font-size:13px;margin-top:24px;'>
    Jeśli to nie Ty — po prostu zignoruj tę wiadomość.
</p>";

$html = mail_template('Potwierdź subskrypcję', $body, 'Potwierdź email →', $confirm_url);
send_mail($email, $name ?: 'Subskrybent', 'Potwierdź newsletter — GrailOS', $html);

echo json_encode(['ok' => true, 'msg' => 'Sprawdź skrzynkę i potwierdź adres email!']);