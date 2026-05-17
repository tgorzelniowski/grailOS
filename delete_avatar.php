<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /grailos/profile.php?username=' . urlencode($_SESSION['username'])); exit;
}

csrf_verify();

$stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$avatar = $stmt->fetchColumn();

if ($avatar && str_starts_with($avatar, '/grailos/assets/uploads/')) {
    $path = __DIR__ . str_replace('/grailos', '', $avatar);
    if (file_exists($path)) unlink($path);
}

$pdo->prepare("UPDATE users SET avatar_url = NULL WHERE id = ?")
    ->execute([$_SESSION['user_id']]);

$_SESSION['avatar_url'] = '';
header('Location: /grailos/profile.php?username=' . urlencode($_SESSION['username']));
exit;
?>