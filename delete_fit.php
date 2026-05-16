<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /grailos/fits.php'); exit;
}

csrf_verify();

$fit_id = (int) ($_POST['fit_id'] ?? 0);
if (!$fit_id) { header('Location: /grailos/fits.php'); exit; }

// Pobierz fit
$stmt = $pdo->prepare("SELECT * FROM fits WHERE id = ?");
$stmt->execute([$fit_id]);
$fit = $stmt->fetch();

if (!$fit) { header('Location: /grailos/fits.php'); exit; }

// Tylko właściciel lub admin może usunąć
if ($fit['user_id'] !== $_SESSION['user_id'] && !$_SESSION['is_admin']) {
    header('Location: /grailos/fits.php'); exit;
}

// Usuń plik zdjęcia
if (str_starts_with($fit['image_url'], '/grailos/assets/uploads/')) {
    $path = __DIR__ . str_replace('/grailos', '', $fit['image_url']);
    if (file_exists($path)) unlink($path);
}

$pdo->prepare("DELETE FROM fits WHERE id = ?")->execute([$fit_id]);
header('Location: /grailos/fits.php');
exit;
?>