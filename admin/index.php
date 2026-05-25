<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

$totalClothing = $pdo->query("SELECT COUNT(*) FROM clothing")->fetchColumn();
$totalDrops    = $pdo->query("SELECT COUNT(*) FROM drops")->fetchColumn();
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin</div>
  <h1 class="page-title">PANEL</h1>
</section>

<div class="admin-stats">
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalClothing ?></div>
    <div class="admin-stat-label">Produktów w katalogu</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalDrops ?></div>
    <div class="admin-stat-label">Dropów w bazie</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalUsers ?></div>
    <div class="admin-stat-label">Użytkowników</div>
  </div>
</div>

<div class="section-label">/// Akcje</div>
<div class="admin-actions">
  <a href="/grailos/admin/add_clothing.php" class="admin-card">
    <div class="admin-card-icon">+</div>
    <div class="admin-card-title">Dodaj produkt</div>
    <div class="admin-card-sub">Dodaj nowe ubranie do katalogu</div>
  </a>
  <a href="/grailos/admin/add_drop.php" class="admin-card">
    <div class="admin-card-icon">+</div>
    <div class="admin-card-title">Dodaj drop</div>
    <div class="admin-card-sub">Dodaj nadchodzący drop</div>
  </a>
  <a href="/grailos/admin/manage_clothing.php" class="admin-card">
    <div class="admin-card-icon">≡</div>
    <div class="admin-card-title">Katalog</div>
    <div class="admin-card-sub">Edytuj i usuwaj produkty</div>
  </a>
  <a href="/grailos/admin/manage_drops.php" class="admin-card">
    <div class="admin-card-icon">≡</div>
    <div class="admin-card-title">Dropy</div>
    <div class="admin-card-sub">Edytuj i usuwaj dropy</div>
  </a>
  <a href="/grailos/admin/manage_fits.php" class="admin-card">
    <div class="admin-card-icon">✕</div>
    <div class="admin-card-title">Fity</div>
    <div class="admin-card-sub">Moderuj posty fitów</div>
  </a>
  <a href="/grailos/admin/manage_posts.php" class="admin-card">
    <div class="admin-card-icon">✕</div>
    <div class="admin-card-title">Forum</div>
    <div class="admin-card-sub">Moderuj posty forum</div>
  </a>
  <a href="/grailos/admin/manage_users.php" class="admin-card">
    <div class="admin-card-icon">⊘</div>
    <div class="admin-card-title">Użytkownicy</div>
    <div class="admin-card-sub">Banuj i zarządzaj userami</div>
  </a>
  <a href="/grailos/admin/send_newsletter.php" class="admin-card">
    <div class="admin-card-icon">✉</div>
    <div class="admin-card-title">Newsletter</div>
    <div class="admin-card-sub">Wyślij wiadomość do subskrybentów</div>
  </a>
  <a href="/grailos/cron_drop_notifications.php?key=zmien_ten_klucz_na_losowy_ciag"
     class="admin-card" target="_blank">
    <div class="admin-card-icon">🔔</div>
    <div class="admin-card-title">Drop alerts</div>
    <div class="admin-card-sub">Wyślij powiadomienia o zbliżających się dropach</div>
  </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>