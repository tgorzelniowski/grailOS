<?php

// ============================================
// BEZPIECZNA KONFIGURACJA SESJI
// ============================================
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

ini_set('session.cookie_httponly',  1);
ini_set('session.use_strict_mode',  1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite',  'Strict');
ini_set('session.cookie_secure',    $is_https ? '1' : '0');
ini_set('session.gc_maxlifetime',   1800);   // 30 minut GC
ini_set('session.cookie_lifetime',  0);      // sesja do zamknięcia przeglądarki
ini_set('session.use_trans_sid',    0);      // nigdy nie używaj SID w URL

session_start();

require_once __DIR__ . '/security.php';

// Wyślij nagłówki bezpieczeństwa
send_security_headers();

// Waliduj sesję zalogowanego użytkownika (timeout + fingerprint)
validate_session();

// ============================================
// POŁĄCZENIE Z BAZĄ DANYCH
// ============================================
$host     = 'localhost';
$dbname   = 'grailos';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,  // prawdziwe prepared statements
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection error: ' . $e->getMessage());
    http_response_code(500);
    die('Błąd serwera. Spróbuj ponownie później.');
}