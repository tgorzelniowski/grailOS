<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /profile.php?username=' . urlencode($_SESSION['username'])); exit;
}

csrf_verify();

if (empty($_FILES['avatar']['name'])) {
    header('Location: /profile.php?username=' . urlencode($_SESSION['username']) . '&error=no_file'); exit;
}

$result = upload_image($_FILES['avatar'], 'avatars');
if (isset($result['error'])) {
    header('Location: /profile.php?username=' . urlencode($_SESSION['username']) . '&error=' . urlencode($result['error'])); exit;
}

// Usuń stary avatar
$old = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
$old->execute([$_SESSION['user_id']]);
$old = $old->fetchColumn();
if ($old && str_starts_with($old, '/assets/uploads/')) {
    $path = __DIR__ . str_replace('/grailos', '', $old);
    if (file_exists($path)) unlink($path);
}

$pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?")
    ->execute([$result['path'], $_SESSION['user_id']]);

$_SESSION['avatar_url'] = $result['path'];

header('Location: /profile.php?username=' . urlencode($_SESSION['username']));
exit;
?>