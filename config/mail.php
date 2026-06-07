<?php
// ============================================================
//  GrailOS — konfiguracja poczty (LH.pl / grailos.pl)
// ============================================================

// Nadawca — MAIL_FROM musi być adresem uwierzytelnianej skrzynki (MAIL_USER)
define('MAIL_FROM',      'no-reply@grailos.pl');
define('MAIL_FROM_NAME', 'GrailOS');

// URL serwisu (bez końcowego /)
define('MAIL_SITE_URL',  'https://grailos.pl');
define('MAIL_SITE_NAME', 'GrailOS');

// ---- SMTP (LH.pl) ----
// Port 465 = SSL. Host i port z panelu poczty.
define('MAIL_SMTP',      true);
define('MAIL_HOST',      'mail-serwer452079.lh.pl');
define('MAIL_PORT',      465);
define('MAIL_USER',      'no-reply@grailos.pl');
define('MAIL_PASS',      'nmNbMJ6&Xg%2<#nC'); 
