<?php

// ============================================
// NAGŁÓWKI BEZPIECZEŃSTWA HTTP
// ============================================
function send_security_headers(): void {
    if (headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // Content-Security-Policy — dopasowane do zasobów projektu
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline'; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: blob: https:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'self';"
    );
}

// ============================================
// WALIDACJA SESJI (fingerprint + timeout)
// ============================================
function validate_session(): void {
    if (!isset($_SESSION['user_id'])) return;

    $timeout = 30 * 60; // 30 minut bezczynności

    // Sprawdź timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        _destroy_session();
        header('Location: /grailos/login.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();

    // Sprawdź fingerprint przeglądarki
    if (isset($_SESSION['ua_fingerprint'])) {
        $current = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if (!hash_equals($_SESSION['ua_fingerprint'], $current)) {
            _destroy_session();
            header('Location: /grailos/login.php?security=1');
            exit;
        }
    }
}

// Ustaw fingerprint przy logowaniu (wywołaj z login.php)
function set_session_fingerprint(): void {
    $_SESSION['ua_fingerprint']  = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['last_activity']   = time();
    $_SESSION['session_created'] = time();
}

// Zniszcz sesję w całości
function _destroy_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 3600,
            $p['path'], $p['domain'],
            $p['secure'], $p['httponly']
        );
    }
    session_destroy();
}

// ============================================
// CSRF PROTECTION
// ============================================
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void {
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.');
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================
// RATE LIMITING (session-based)
// ============================================
function check_rate_limit(string $key, int $max, int $window): bool {
    $now = time();
    if (!isset($_SESSION['rate'][$key])) {
        $_SESSION['rate'][$key] = [];
    }
    $_SESSION['rate'][$key] = array_values(array_filter(
        $_SESSION['rate'][$key],
        fn($t) => $t > $now - $window
    ));
    if (count($_SESSION['rate'][$key]) >= $max) {
        return false;
    }
    $_SESSION['rate'][$key][] = $now;
    return true;
}

// ============================================
// FILE UPLOAD
// ============================================
function upload_image(array $file, string $folder): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Błąd podczas przesyłania pliku.'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'Plik za duży. Maksymalnie 5MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mime, $allowed_mime, true)) {
        return ['error' => 'Dozwolone tylko pliki JPG, PNG lub WebP.'];
    }

    $ext_map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext      = $ext_map[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;

    $upload_dir = __DIR__ . "/../assets/uploads/$folder/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        return ['error' => 'Nie udało się zapisać pliku na serwerze.'];
    }

    return ['path' => "/grailos/assets/uploads/$folder/$filename"];
}