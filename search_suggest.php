<?php
require_once 'config/db.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$like    = '%' . $q . '%';
$results = [];

$stmt = $pdo->prepare("SELECT id, name, brand, image_url FROM clothing WHERE name LIKE ? OR brand LIKE ? LIMIT 4");
$stmt->execute([$like, $like]);
foreach ($stmt->fetchAll() as $item) {
    $results[] = [
        'text'  => $item['brand'] . ' — ' . $item['name'],
        'type'  => 'item',
        'url'   => '/item.php?id=' . $item['id'],
        'thumb' => $item['image_url'],
        'init'  => strtoupper($item['brand'][0]),
    ];
}

$stmt = $pdo->prepare("SELECT username, avatar_url FROM users WHERE username LIKE ? AND is_banned = 0 LIMIT 3");
$stmt->execute([$like]);
foreach ($stmt->fetchAll() as $user) {
    $results[] = [
        'text'  => '@' . $user['username'],
        'type'  => 'user',
        'url'   => '/profile.php?username=' . urlencode($user['username']),
        'thumb' => $user['avatar_url'],
        'init'  => strtoupper($user['username'][0]),
    ];
}

$stmt = $pdo->prepare("SELECT id, name, brand, image_url FROM drops WHERE name LIKE ? OR brand LIKE ? LIMIT 2");
$stmt->execute([$like, $like]);
foreach ($stmt->fetchAll() as $drop) {
    $results[] = [
        'text'  => $drop['brand'] . ' — ' . $drop['name'],
        'type'  => 'drop',
        'url'   => '/drops.php',
        'thumb' => $drop['image_url'],
        'init'  => strtoupper($drop['brand'][0]),
    ];
}

echo json_encode($results);
?>