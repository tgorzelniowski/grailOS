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
  <link rel="stylesheet" href="/grailos/assets/css/style.css">
</head>
<body>

<nav class="navbar">

  <!-- LOGO -->
  <a href="/grailos/" class="logo">GRAIL<span>OS</span></a>

  <!-- NAV LINKS + MOBILE AUTH -->
  <div class="nav-links" id="navLinks">
    <a href="/grailos/catalog.php">Catalog</a>
    <a href="/grailos/drops.php">Drops</a>
    <a href="/grailos/fits.php">Fits</a>
    <a href="/grailos/forum.php">Forum</a>
    <a href="/grailos/rankings.php">Rankings</a>

    <div class="nav-mobile-auth">
      <div class="nav-mobile-divider"></div>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/grailos/profile.php?username=<?= urlencode($_SESSION['username']) ?>">
          @<?= htmlspecialchars($_SESSION['username']) ?>
        </a>
        <?php if ($_SESSION['is_admin']): ?>
          <a href="/grailos/admin/">Admin</a>
        <?php endif; ?>
        <a href="/grailos/logout.php" style="color:var(--red)">Logout</a>
      <?php else: ?>
        <a href="/grailos/login.php">Login</a>
        <a href="/grailos/register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- RIGHT SIDE -->
  <div class="nav-right">

    <!-- SEARCH -->
    <div class="nav-search-wrap" id="navSearchWrap">
      <button class="nav-icon-btn" onclick="toggleSearch()" aria-label="Szukaj">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
      </button>
      <div class="nav-search-box" id="navSearchBox">
        <form method="GET" action="/grailos/search.php" autocomplete="off">
          <input type="text" name="q" id="navSearchInput"
                 placeholder="Szukaj..."
                 value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        </form>
        <div class="nav-suggestions" id="navSuggestions"></div>
      </div>
    </div>

    <!-- DESKTOP USER -->
    <div class="nav-user-desktop">
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($_SESSION['is_admin']): ?>
          <a href="/grailos/admin/" class="nav-link-sm">Admin</a>
        <?php endif; ?>
        <a href="/grailos/profile.php?username=<?= urlencode($_SESSION['username']) ?>"
           class="nav-avatar-btn" title="@<?= htmlspecialchars($_SESSION['username']) ?>">
          <?php if (!empty($_SESSION['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['avatar_url']) ?>"
                 class="nav-avatar-img" alt="">
          <?php else: ?>
            <span class="nav-avatar-initials">
              <?= strtoupper(htmlspecialchars($_SESSION['username'][0])) ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="/grailos/logout.php" class="nav-link-sm">Logout</a>
      <?php else: ?>
        <a href="/grailos/register.php" class="nav-link-sm">Register</a>
        <a href="/grailos/login.php" class="btn nav-login-btn">Login</a>
      <?php endif; ?>
    </div>

    <!-- HAMBURGER -->
    <button class="hamburger" id="hamburger" onclick="toggleNav()" aria-label="Menu">☰</button>

  </div>
</nav>

<script>
function toggleNav() {
  const nav  = document.getElementById('navLinks');
  const btn  = document.getElementById('hamburger');
  nav.classList.toggle('open');
  btn.textContent = nav.classList.contains('open') ? '✕' : '☰';
}

function toggleSearch() {
  const box = document.getElementById('navSearchBox');
  box.classList.toggle('open');
  if (box.classList.contains('open')) {
    setTimeout(() => document.getElementById('navSearchInput').focus(), 50);
  } else {
    document.getElementById('navSuggestions').classList.remove('open');
  }
}

let suggestTimeout;
document.getElementById('navSearchInput').addEventListener('input', function() {
  clearTimeout(suggestTimeout);
  const q    = this.value.trim();
  const sugg = document.getElementById('navSuggestions');
  if (q.length < 2) { sugg.classList.remove('open'); sugg.innerHTML = ''; return; }
  suggestTimeout = setTimeout(() => {
    fetch('/grailos/search_suggest.php?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        if (!data.length) { sugg.classList.remove('open'); return; }
        sugg.innerHTML = data.map(s =>
          `<a href="${s.url}" class="sugg-item">
            <span class="sugg-type sugg-${s.type}">${s.type}</span>
            <span class="sugg-text">${s.text}</span>
           </a>`
        ).join('');
        sugg.classList.add('open');
      });
  }, 250);
});

document.addEventListener('click', e => {
  if (!document.getElementById('navSearchWrap').contains(e.target)) {
    document.getElementById('navSuggestions').classList.remove('open');
    document.getElementById('navSearchBox').classList.remove('open');
  }
  if (!document.getElementById('navLinks').contains(e.target) &&
      !document.getElementById('hamburger').contains(e.target)) {
    document.getElementById('navLinks').classList.remove('open');
    document.getElementById('hamburger').textContent = '☰';
  }
});
</script>