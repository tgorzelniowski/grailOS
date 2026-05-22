<?php
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

$drop_id = (int)($_POST['drop_id'] ?? 0);
$user_id = $_SESSION['user_id'];
if (!$drop_id) { echo json_encode(['success' => false]); exit; }

$check = $pdo->prepare("SELECT id FROM drop_hypes WHERE drop_id = ? AND user_id = ?");
$check->execute([$drop_id, $user_id]);

if ($check->fetch()) {
    $pdo->prepare("DELETE FROM drop_hypes WHERE drop_id = ? AND user_id = ?")
        ->execute([$drop_id, $user_id]);
    $hyped = false;
} else {
    $pdo->prepare("INSERT INTO drop_hypes (drop_id, user_id) VALUES (?, ?)")
        ->execute([$drop_id, $user_id]);
    $hyped = true;
}

$count = $pdo->prepare("SELECT COUNT(*) FROM drop_hypes WHERE drop_id = ?");
$count->execute([$drop_id]);
echo json_encode(['success' => true, 'hyped' => $hyped, 'count' => (int)$count->fetchColumn()]);
?>