<?php
// Dynamiczna mapa strony (XML) dla wyszukiwarek. Dostępna pod /sitemap.php oraz /sitemap.xml (rewrite).
require_once 'config/db.php';
header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
$base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'grailos.pl');

$out = [];
$add = function (string $path, string $freq = 'weekly', string $prio = '0.6', ?string $lastmod = null) use (&$out, $base) {
    $loc = htmlspecialchars($base . $path, ENT_XML1);
    $lm  = $lastmod ? '<lastmod>' . date('Y-m-d', strtotime($lastmod)) . '</lastmod>' : '';
    $out[] = "<url><loc>$loc</loc>$lm<changefreq>$freq</changefreq><priority>$prio</priority></url>";
};

// Strony statyczne
$add('/', 'daily', '1.0');
$add('/catalog.php', 'daily', '0.9');
$add('/drops.php', 'daily', '0.9');
$add('/fits.php', 'daily', '0.8');
$add('/forum.php', 'daily', '0.8');
$add('/rankings.php', 'weekly', '0.5');
$add('/about.php', 'monthly', '0.3');
$add('/contact.php', 'monthly', '0.3');

// Dynamiczne — każda pozycja osobno (z lastmod jeśli kolumna istnieje)
$sources = [
    ['clothing', '/item.php?id=', '0.7'],
    ['drops',    '/drop.php?id=', '0.7'],
    ['fits',     '/fit.php?id=',  '0.6'],
    ['posts',    '/post.php?id=', '0.6'],
];
foreach ($sources as [$table, $path, $prio]) {
    try {
        // spróbuj z created_at; jak brak kolumny — fallback bez niej
        try   { $rows = $pdo->query("SELECT id, created_at FROM `$table` ORDER BY id DESC LIMIT 5000"); }
        catch (Exception $e) { $rows = $pdo->query("SELECT id FROM `$table` ORDER BY id DESC LIMIT 5000"); }
        foreach ($rows as $r) {
            $add($path . (int)$r['id'], 'weekly', $prio, $r['created_at'] ?? null);
        }
    } catch (Exception $e) { /* tabela nie istnieje — pomiń */ }
}

// Profile publiczne
try {
    $u = $pdo->query("SELECT username FROM users WHERE is_banned = 0 ORDER BY id DESC LIMIT 5000");
    foreach ($u as $r) {
        $add('/profile.php?username=' . urlencode($r['username']), 'weekly', '0.5');
    }
} catch (Exception $e) {}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo implode("\n", $out) . "\n";
echo '</urlset>';
