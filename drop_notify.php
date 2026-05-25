<?php
require_once 'config/db.php';
require_once 'includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

csrf_verify();

$drop_id = (int)($_POST['drop_id'] ?? 0);
if (!$drop_id) {
    echo json_encode(['ok' => false, 'error' => 'Brak ID dropu.']);
    exit;
}

// Sprawdź czy drop istnieje i nie minął
$drop_stmt = $pdo->prepare("SELECT id, name, brand, drop_date FROM drops WHERE id = ? AND drop_date > NOW()");
$drop_stmt->execute([$drop_id]);
$drop = $drop_stmt->fetch();

if (!$drop) {
    echo json_encode(['ok' => false, 'error' => 'Drop nie istnieje lub już się odbył.']);
    exit;
}

if (isset($_SESSION['user_id'])) {
    // Zalogowany użytkownik
    $uid = (int)$_SESSION['user_id'];

    // Sprawdź czy już zapisany
    $check = $pdo->prepare("SELECT id FROM drop_notifications WHERE drop_id = ? AND user_id = ?");
    $check->execute([$drop_id, $uid]);
    if ($check->fetch()) {
        // Toggle — wypisz
        $pdo->prepare("DELETE FROM drop_notifications WHERE drop_id = ? AND user_id = ?")
            ->execute([$drop_id, $uid]);
        echo json_encode(['ok' => true, 'subscribed' => false, 'msg' => 'Wypisano z powiadomień.']);
        exit;
    }

    $token = bin2hex(random_bytes(24));
    $pdo->prepare("INSERT INTO drop_notifications (drop_id, user_id, unsubscribe_token) VALUES (?, ?, ?)")
        ->execute([$drop_id, $uid, $token]);

    echo json_encode(['ok' => true, 'subscribed' => true, 'msg' => 'Powiadomimy Cię przed dropem!']);

} else {
    // Gość — wymaga emaila
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Podaj prawidłowy adres email.']);
        exit;
    }

    if (!check_rate_limit('drop_notify', 5, 3600)) {
        echo json_encode(['ok' => false, 'error' => 'Za dużo prób. Spróbuj za godzinę.']);
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM drop_notifications WHERE drop_id = ? AND email = ?");
    $check->execute([$drop_id, $email]);
    if ($check->fetch()) {
        echo json_encode(['ok' => true, 'subscribed' => true, 'msg' => 'Jesteś już zapisany na to powiadomienie.']);
        exit;
    }

    $token = bin2hex(random_bytes(24));
    $pdo->prepare("INSERT INTO drop_notifications (drop_id, email, unsubscribe_token) VALUES (?, ?, ?)")
        ->execute([$drop_id, $email, $token]);

    echo json_encode(['ok' => true, 'subscribed' => true, 'msg' => 'Powiadomimy Cię przed dropem!']);
}