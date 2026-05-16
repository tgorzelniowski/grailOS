<?php

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
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.');
    }
    // Regeneruj token po użyciu
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
    // Usuń stare wpisy
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

    // Max 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['error' => 'Plik za duży. Maksymalnie 5MB.'];
    }

    // Weryfikuj MIME type przez finfo (bezpieczniejsze niż $_FILES['type'])
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($mime, $allowed_mime, true)) {
        return ['error' => 'Dozwolone tylko pliki JPG, PNG lub WebP.'];
    }

    // Weryfikuj rozszerzenie
    $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $ext_map[$mime];

    // Losowa nazwa — nie można odgadnąć ścieżki
    $filename   = bin2hex(random_bytes(16)) . '.' . $ext;
    $upload_dir = __DIR__ . "/../assets/uploads/$folder/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        return ['error' => 'Nie udało się zapisać pliku na serwerze.'];
    }

    return ['path' => "/grailos/assets/uploads/$folder/$filename"];
}