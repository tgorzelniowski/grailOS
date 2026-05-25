<?php
// ============================================================
//  GrailOS — konfiguracja poczty
//  Uzupełnij swoje dane SMTP poniżej
// ============================================================

// Nadawca
define('MAIL_FROM',      'noreply@grailos.com');
define('MAIL_FROM_NAME', 'GrailOS');

// URL serwisu (bez końcowego /)
define('MAIL_SITE_URL',  'http://localhost/grailos');
define('MAIL_SITE_NAME', 'GrailOS');

// ---- Konfiguracja SMTP ----
// Ustaw MAIL_SMTP na true i uzupełnij dane swojego serwera SMTP
// Przykład dla Gmail: HOST=smtp.gmail.com, PORT=587, użyj hasła aplikacji
define('MAIL_SMTP',      false);
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USER',      'twoj@email.com');
define('MAIL_PASS',      'twoje_haslo_aplikacji');