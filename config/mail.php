<?php
// ============================================================
//  GrailOS — konfiguracja poczty
//  Uzupełnij swoje dane SMTP poniżej
// ============================================================

// Nadawca
// WAŻNE: przy SMTP MAIL_FROM musi być adresem uwierzytelnianej skrzynki
// (MAIL_USER) lub jej dozwolonym aliasem — inaczej serwer odrzuci wysyłkę.
define('MAIL_FROM',      'noreply@grailos.com');
define('MAIL_FROM_NAME', 'GrailOS');

// URL serwisu (bez końcowego /) — używany w linkach w mailach.
// Na produkcji ustaw realny adres, np. 'https://twojadomena.pl/grailos'
define('MAIL_SITE_URL',  'http://localhost/grailos');
define('MAIL_SITE_NAME', 'GrailOS');

// ---- Konfiguracja SMTP ----
// 1. Ustaw MAIL_SMTP na true.
// 2. Uzupełnij dane SMTP z panelu hostingu.
//    PORT 587 = STARTTLS,  PORT 465 = SSL (oba obsługiwane).
//    Gmail: HOST=smtp.gmail.com, PORT=587, MAIL_PASS = "hasło aplikacji".
// 3. Ustaw MAIL_FROM na adres tej samej skrzynki co MAIL_USER (patrz wyżej).
define('MAIL_SMTP',      false);
define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USER',      'twoj@email.com');
define('MAIL_PASS',      'twoje_haslo_aplikacji');