<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}
csrf_verify();

$outfit_id = (int)($_POST['outfit_id'] ?? 0);
if (!$outfit_id) {
    header('Location: /grailos/profile.php?username=' . urlencode($_SESSION['username'])); exit;
}

$stmt = $pdo->prepare("SELECT id FROM outfits WHERE id = ? AND user_id = ?");
$stmt->execute([$outfit_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    header('Location: /grailos/profile.php?username=' . urlencode($_SESSION['username'])); exit;
}

$pdo->prepare("DELETE FROM outfits WHERE id = ?")->execute([$outfit_id]);
header('Location: /grailos/profile.php?username=' . urlencode($_SESSION['username']));
exit;
