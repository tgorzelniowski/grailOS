<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalog.php'); exit;
}

csrf_verify();

$clothing_id = (int) ($_POST['clothing_id'] ?? 0);
$user_id     = $_SESSION['user_id'];

if (!$clothing_id) {
    header('Location: /catalog.php'); exit;
}

$check = $pdo->prepare("SELECT id FROM wardrobe_items WHERE user_id = ? AND clothing_id = ?");
$check->execute([$user_id, $clothing_id]);

if ($check->fetch()) {
    $pdo->prepare("DELETE FROM wardrobe_items WHERE user_id = ? AND clothing_id = ?")
        ->execute([$user_id, $clothing_id]);
} else {
    $pdo->prepare("INSERT INTO wardrobe_items (user_id, clothing_id) VALUES (?, ?)")
        ->execute([$user_id, $clothing_id]);
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/catalog.php'));
exit;
?>