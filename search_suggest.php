<?php
require_once 'config/db.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$like    = '%' . $q . '%';
$results = [];

$stmt = $pdo->prepare("SELECT name, brand FROM clothing WHERE name LIKE ? OR brand LIKE ? LIMIT 4");
$stmt->execute([$like, $like]);
foreach ($stmt->fetchAll() as $item) {
    $results[] = [
        'text' => $item['brand'] . ' — ' . $item['name'],
        'type' => 'item',
        'url'  => '/grailos/search.php?q=' . urlencode($q)
    ];
}

$stmt = $pdo->prepare("SELECT username FROM users WHERE username LIKE ? AND is_banned = 0 LIMIT 3");
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $user) {
    $results[] = [
        'text' => '@' . $user['username'],
        'type' => 'user',
        'url'  => '/grailos/profile.php?username=' . urlencode($user['username'])
    ];
}

$stmt = $pdo->prepare("SELECT name FROM drops WHERE name LIKE ? OR brand LIKE ? LIMIT 2");
$stmt->execute([$like, $like]);
foreach ($stmt->fetchAll() as $drop) {
    $results[] = [
        'text' => $drop['name'],
        'type' => 'drop',
        'url'  => '/grailos/drops.php'
    ];
}

echo json_encode($results);
?>