<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

$totalClothing  = (int)$pdo->query("SELECT COUNT(*) FROM clothing")->fetchColumn();
$totalDrops     = (int)$pdo->query("SELECT COUNT(*) FROM drops")->fetchColumn();
$totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPosts     = (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$totalFits      = (int)$pdo->query("SELECT COUNT(*) FROM fits")->fetchColumn();
$totalNewsletter= (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE confirmed=1")->fetchColumn();
?>

<section class="page-header">
  <div class="page-header-tag">/// GrailOS</div>
  <h1 class="page-title">ADMIN<br>PANEL</h1>
</section>

<!-- STATS -->
<div class="admin-stats">
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalClothing ?></div>
    <div class="admin-stat-label">Produkty</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalDrops ?></div>
    <div class="admin-stat-label">Dropy</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalUsers ?></div>
    <div class="admin-stat-label">Użytkownicy</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalFits ?></div>
    <div class="admin-stat-label">Fity</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalPosts ?></div>
    <div class="admin-stat-label">Posty</div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat-number"><?= $totalNewsletter ?></div>
    <div class="admin-stat-label">Newsletter</div>
  </div>
</div>

<!-- WYSZUKIWARKA AKCJI -->
<div class="admin-search-wrap">
  <input
    type="text"
    id="adminSearch"
    class="admin-search-input"
    placeholder="SZUKAJ AKCJI…"
    autocomplete="off"
    oninput="filterAdminCards(this.value)"
  >
  <span class="admin-search-icon">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
  </span>
  <div class="admin-search-count" id="adminSearchCount"></div>
</div>

<!-- GRUPY AKCJI -->
<div id="adminCards">

  <div class="admin-group-label">Katalog</div>
  <div class="admin-actions" data-group="katalog">
    <a href="/grailos/admin/add_clothing.php" class="admin-card"
       data-tags="dodaj produkt ubranie katalog clothing add">
      <div class="admin-card-icon">+</div>
      <div class="admin-card-title">Dodaj produkt</div>
      <div class="admin-card-sub">Dodaj nowe ubranie do katalogu</div>
    </a>
    <a href="/grailos/admin/manage_clothing.php" class="admin-card"
       data-tags="katalog produkty zarządzaj ubrania edytuj usuń clothing manage">
      <div class="admin-card-icon">≡</div>
      <div class="admin-card-title">Zarządzaj katalogiem</div>
      <div class="admin-card-sub">Edytuj i usuwaj produkty</div>
    </a>
  </div>

  <div class="admin-group-label">Dropy</div>
  <div class="admin-actions" data-group="dropy">
    <a href="/grailos/admin/add_drop.php" class="admin-card"
       data-tags="dodaj drop drops add nowy">
      <div class="admin-card-icon">+</div>
      <div class="admin-card-title">Dodaj drop</div>
      <div class="admin-card-sub">Dodaj nadchodzący drop</div>
    </a>
    <a href="/grailos/admin/manage_drops.php" class="admin-card"
       data-tags="dropy zarządzaj drops edytuj usuń manage">
      <div class="admin-card-icon">≡</div>
      <div class="admin-card-title">Zarządzaj dropami</div>
      <div class="admin-card-sub">Edytuj i usuwaj dropy</div>
    </a>
    <a href="/grailos/cron_drop_notifications.php?key=a7f3c9e1b54d28760af1e6c3d9b840e2"
       class="admin-card" target="_blank"
       data-tags="drop powiadomienia notify alert email wyślij">
      <div class="admin-card-icon">🔔</div>
      <div class="admin-card-title">Drop alerts</div>
      <div class="admin-card-sub">Wyślij powiadomienia o zbliżających się dropach</div>
    </a>
  </div>

  <div class="admin-group-label">Społeczność</div>
  <div class="admin-actions" data-group="spolecznosc">
    <a href="/grailos/admin/manage_fits.php" class="admin-card"
       data-tags="fity moderuj posty fits moderate usuń">
      <div class="admin-card-icon">◻</div>
      <div class="admin-card-title">Fity</div>
      <div class="admin-card-sub">Moderuj posty fitów</div>
    </a>
    <a href="/grailos/admin/manage_posts.php" class="admin-card"
       data-tags="forum posty moderuj posts moderate usuń">
      <div class="admin-card-icon">◻</div>
      <div class="admin-card-title">Forum</div>
      <div class="admin-card-sub">Moderuj posty forum</div>
    </a>
    <a href="/grailos/admin/manage_users.php" class="admin-card"
       data-tags="użytkownicy users ban zarządzaj manage blokuj">
      <div class="admin-card-icon">⊘</div>
      <div class="admin-card-title">Użytkownicy</div>
      <div class="admin-card-sub">Banuj i zarządzaj userami</div>
    </a>
  </div>

  <div class="admin-group-label">Marketing</div>
  <div class="admin-actions" data-group="marketing">
    <a href="/grailos/admin/send_newsletter.php" class="admin-card"
       data-tags="newsletter email wyślij subskrybenci send mailing">
      <div class="admin-card-icon">✉</div>
      <div class="admin-card-title">Newsletter</div>
      <div class="admin-card-sub">Wyślij wiadomość do subskrybentów</div>
    </a>
  </div>

  <div class="admin-no-results" id="adminNoResults" style="display:none;">
    <span>Brak wyników dla "<span id="adminSearchTerm"></span>"</span>
  </div>

</div>

<script>
function filterAdminCards(q) {
  const term    = q.trim().toLowerCase();
  const cards   = document.querySelectorAll('.admin-card');
  const groups  = document.querySelectorAll('.admin-actions, .admin-group-label');
  const noRes   = document.getElementById('adminNoResults');
  const count   = document.getElementById('adminSearchCount');
  const termEl  = document.getElementById('adminSearchTerm');

  if (!term) {
    cards.forEach(c => c.style.display = '');
    groups.forEach(g => g.style.display = '');
    noRes.style.display = 'none';
    count.textContent   = '';
    return;
  }

  let visible = 0;
  cards.forEach(card => {
    const text = (card.textContent + ' ' + (card.dataset.tags || '')).toLowerCase();
    const show = text.includes(term);
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  // Ukryj puste grupy
  document.querySelectorAll('[data-group]').forEach(grid => {
    const anyVisible = [...grid.querySelectorAll('.admin-card')]
      .some(c => c.style.display !== 'none');
    grid.style.display = anyVisible ? '' : 'none';
    const label = grid.previousElementSibling;
    if (label?.classList.contains('admin-group-label')) {
      label.style.display = anyVisible ? '' : 'none';
    }
  });

  count.textContent   = visible ? `${visible} wynik${visible === 1 ? '' : 'ów'}` : '';
  termEl.textContent  = q.trim();
  noRes.style.display = visible === 0 ? 'flex' : 'none';
}

// Skrót klawiaturowy: / otwiera wyszukiwarkę
document.addEventListener('keydown', e => {
  if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
    e.preventDefault();
    document.getElementById('adminSearch').focus();
  }
  if (e.key === 'Escape') {
    const inp = document.getElementById('adminSearch');
    inp.value = '';
    filterAdminCards('');
    inp.blur();
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
