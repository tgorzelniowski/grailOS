<?php
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$fit_id  = (int) ($_POST['fit_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$fit_id) {
    echo json_encode(['success' => false]);
    exit;
}

// Sprawdź czy już głosował
$stmt = $pdo->prepare("SELECT id FROM fit_votes WHERE fit_id = ? AND user_id = ?");
$stmt->execute([$fit_id, $user_id]);
$existing = $stmt->fetch();

if ($existing) {
    // Cofnij głos
    $pdo->prepare("DELETE FROM fit_votes WHERE fit_id = ? AND user_id = ?")->execute([$fit_id, $user_id]);
    $voted = false;
} else {
    // Dodaj głos
    $pdo->prepare("INSERT INTO fit_votes (fit_id, user_id) VALUES (?, ?)")->execute([$fit_id, $user_id]);
    $voted = true;
}

$votes = $pdo->prepare("SELECT COUNT(*) FROM fit_votes WHERE fit_id = ?");
$votes->execute([$fit_id]);
$count = (int) $votes->fetchColumn();

echo json_encode(['success' => true, 'voted' => $voted, 'votes' => $count]);
?>