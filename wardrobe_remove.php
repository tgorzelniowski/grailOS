<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalog.php'); exit;
}

csrf_verify();

$item_id = (int)($_POST['wardrobe_item_id'] ?? 0);

$pdo->prepare("DELETE FROM wardrobe_items WHERE id = ? AND user_id = ?")
    ->execute([$item_id, $_SESSION['user_id']]);

$ref = $_SERVER['HTTP_REFERER'] ?? '/profile.php?username=' . urlencode($_SESSION['username']);
header('Location: ' . $ref);
exit;
?>