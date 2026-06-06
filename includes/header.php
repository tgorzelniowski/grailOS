<?php require_once __DIR__ . '/../config/db.php'; ?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GrailOS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar">

  <!-- LOGO -->
  <a href="/grailos/" class="logo">GRAIL<span>OS</span></a>

  <!-- NAV LINKS (desktop center, mobile fullscreen) -->
  <div class="nav-menu" id="navMenu">

    <div class="nav-menu-links">
      <a href="/grailos/catalog.php">Catalog</a>
      <a href="/grailos/drops.php">Drops</a>
      <a href="/grailos/fits.php">Fits</a>
      <a href="/grailos/forum.php">Forum</a>
      <a href="/grailos/rankings.php">Rankings</a>
    </div>

    <!-- TYLKO MOBILE: auth linki wewnątrz menu -->
    <div class="nav-mob-auth">
      <div class="nav-mob-divider"></div>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/grailos/profile.php?username=<?= urlencode($_SESSION['username']) ?>">
          @<?= htmlspecialchars($_SESSION['username']) ?>
        </a>
        <a href="/grailos/settings.php">Ustawienia</a>
        <?php if ($_SESSION['is_admin']): ?>
          <a href="/grailos/admin/">Panel admina</a>
        <?php endif; ?>
        <a href="/grailos/logout.php" style="color:var(--red)">Logout</a>
      <?php else: ?>
        <a href="/grailos/login.php">Login</a>
        <a href="/grailos/register.php">Register</a>
      <?php endif; ?>
    </div>

  </div>

  <!-- PRAWA STRONA -->
  <div class="nav-end">

    <!-- SEARCH -->
    <div class="nav-search-wrap" id="navSearchWrap">
      <button class="nav-search-icon" onclick="toggleSearch(event)" aria-label="Szukaj">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
      </button>
      <div class="nav-search-panel" id="navSearchPanel">
        <input type="text" name="q" id="navQ"
               placeholder="Szukaj produktów, userów..."
               value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
               autocomplete="off"
               onkeydown="if(event.key==='Enter'){window.location='/grailos/search.php?q='+encodeURIComponent(this.value)}">
        <div class="nav-suggest" id="navSuggest"></div>
      </div>
    </div>

    <!-- DESKTOP AUTH -->
    <div class="nav-desk-auth">
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($_SESSION['is_admin']): ?>
          <a href="/grailos/admin/" class="nav-sm">Admin</a>
        <?php endif; ?>
        <a href="/grailos/profile.php?username=<?= urlencode($_SESSION['username']) ?>"
           class="nav-avatar-link">
          <?php if (!empty($_SESSION['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['avatar_url']) ?>"
                 class="nav-av-img" alt="">
          <?php else: ?>
            <span class="nav-av-init">
              <?= strtoupper(htmlspecialchars($_SESSION['username'][0])) ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="/grailos/logout.php" class="nav-sm">Logout</a>
      <?php else: ?>
        <a href="/grailos/register.php" class="nav-sm">Register</a>
        <a href="/grailos/login.php" class="btn nav-login-btn">Login</a>
      <?php endif; ?>
    </div>

    <!-- HAMBURGER (tylko mobile) -->
    <button class="hamburger" id="hamburger" onclick="toggleMenu()" aria-label="Menu">☰</button>

  </div>
</nav>

<script>
function toggleMenu() {
  const menu   = document.getElementById('navMenu');
  const burger = document.getElementById('hamburger');
  menu.classList.toggle('open');
  burger.textContent = menu.classList.contains('open') ? '✕' : '☰';
}

function toggleSearch(e) {
  e.stopPropagation();
  const panel = document.getElementById('navSearchPanel');
  panel.classList.toggle('open');
  if (panel.classList.contains('open')) {
    setTimeout(() => document.getElementById('navQ').focus(), 50);
  }
}

let searchTimer;
document.getElementById('navQ').addEventListener('input', function() {
  clearTimeout(searchTimer);
  const q    = this.value.trim();
  const sugg = document.getElementById('navSuggest');
  if (q.length < 2) { sugg.classList.remove('open'); sugg.innerHTML = ''; return; }
  searchTimer = setTimeout(async () => {
    const res  = await fetch('/grailos/search_suggest.php?q=' + encodeURIComponent(q));
    const data = await res.json();
    if (!data.length) { sugg.classList.remove('open'); return; }
    sugg.innerHTML = data.map(s => {
      const thumb = s.thumb
        ? `<img src="${s.thumb}" class="sugg-thumb-img" alt="">`
        : `<span class="sugg-thumb-init">${s.init || '?'}</span>`;
      return `<a href="${s.url}" class="sugg-item">
        <span class="sugg-thumb">${thumb}</span>
        <span class="sugg-badge sugg-${s.type}">${s.type}</span>
        <span class="sugg-text">${s.text}</span>
      </a>`;
    }).join('');
    sugg.classList.add('open');
  }, 250);
});

document.addEventListener('click', e => {
  if (!document.getElementById('navSearchWrap').contains(e.target)) {
    document.getElementById('navSearchPanel').classList.remove('open');
    document.getElementById('navSuggest').classList.remove('open');
  }
  if (!document.getElementById('navMenu').contains(e.target) &&
      !document.getElementById('hamburger').contains(e.target)) {
    document.getElementById('navMenu').classList.remove('open');
    document.getElementById('hamburger').textContent = '☰';
  }
});
</script>

<?php require_once __DIR__ . '/cookie_consent.php'; ?>