<?php
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Musisz być zalogowany.']); exit;
}

// CSRF bez rotacji (żeby nie psuć innych formularzy na stronie)
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['ok' => false, 'error' => 'Błąd bezpieczeństwa. Odśwież stronę.']); exit;
}

$valid_types = ['profile','post','post_reply','fit','fit_comment','fit_reply','story'];
$type    = $_POST['content_type'] ?? '';
$cid     = (int)($_POST['content_id'] ?? 0);
$reason  = trim($_POST['reason'] ?? '');
$details = trim($_POST['details'] ?? '');
$me      = (int)$_SESSION['user_id'];

if (!in_array($type, $valid_types, true) || !$cid || $reason === '') {
    echo json_encode(['ok' => false, 'error' => 'Niekompletne zgłoszenie.']); exit;
}
if (mb_strlen($details) > 1000) $details = mb_substr($details, 0, 1000);

// Zapobiegaj duplikatom: to samo zgłoszenie tej samej treści przez tego usera (otwarte)
$dup = $pdo->prepare("SELECT id FROM reports WHERE reporter_id = ? AND content_type = ? AND content_id = ? AND status = 'open'");
$dup->execute([$me, $type, $cid]);
if ($dup->fetch()) {
    echo json_encode(['ok' => true, 'already' => true]); exit;
}

try {
    $pdo->prepare("INSERT INTO reports (reporter_id, content_type, content_id, reason, details) VALUES (?, ?, ?, ?, ?)")
        ->execute([$me, $type, $cid, $reason, $details ?: null]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Uruchom migrację reports_migration.sql.']); exit;
}

echo json_encode(['ok' => true]);
