<?php
require_once 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

csrf_verify();

$action   = $_POST['action'] ?? 'necessary';
$lifetime = 60 * 60 * 24 * 365; // 1 rok
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

$base_opts = [
    'expires'  => time() + $lifetime,
    'path'     => '/grailos/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => $is_https,
];

if ($action === 'all') {
    // Cookie zgody
    setcookie('grailos_consent', 'all', $base_opts);

    // Sanityzuj dane urządzenia przesłane przez JS
    $screen = preg_replace('/[^0-9x×]/', '', substr($_POST['screen'] ?? '', 0, 20));
    $tz     = preg_replace('/[^A-Za-z0-9_\/\-+]/', '', substr($_POST['tz'] ?? '', 0, 60));
    $lang   = preg_replace('/[^A-Za-z0-9\-,;=. ]/', '', substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 80));

    // Wykryj rodzaj urządzenia z User-Agent
    $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device_type = 'desktop';
    if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
        $device_type = preg_match('/iPad/i', $ua) ? 'tablet' : 'mobile';
    }

    $device_data = json_encode([
        'ua'          => substr($ua, 0, 250),
        'lang'        => $lang,
        'screen'      => $screen,
        'tz'          => $tz,
        'device_type' => $device_type,
        'first_visit' => date('Y-m-d H:i:s'),
    ]);

    $device_opts             = $base_opts;
    $device_opts['httponly'] = false; // dostępne przez JS do analityki
    setcookie('grailos_device', base64_encode($device_data), $device_opts);

} elseif ($action === 'necessary') {
    setcookie('grailos_consent', 'necessary', $base_opts);

} elseif ($action === 'revoke') {
    // Cofnięcie zgody
    $remove_opts = [
        'expires'  => time() - 3600,
        'path'     => '/grailos/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => $is_https,
    ];
    setcookie('grailos_consent', '', $remove_opts);
    setcookie('grailos_device',  '', $remove_opts);
}

echo json_encode(['ok' => true]);