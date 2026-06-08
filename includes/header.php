<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/notifications.php';
$navUnread = isset($_SESSION['user_id']) ? notif_unread_count($pdo, (int)$_SESSION['user_id']) : 0;

// ---------- SEO / meta (strony mogą ustawić $meta_* przed include) ----------
$SITE_NAME = 'GrailOS';
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'grailos.pl';
$base      = $scheme . '://' . $host;

$_t = trim($meta_title ?? '');
$seo_title = $_t !== '' ? ($_t . ' — ' . $SITE_NAME)
           : 'GrailOS — polska społeczność sneakerów i streetwearu';
$seo_desc  = trim($meta_desc ?? '');
if ($seo_desc === '') {
    $seo_desc = 'GrailOS — katalog ubrań i butów z porównaniem sklepów, dropy, fity i forum dla fanów streetwearu i sneakerów w Polsce.';
}
$seo_desc  = mb_substr(preg_replace('/\s+/', ' ', $seo_desc), 0, 160);

// canonical: bieżący URL bez fragmentu (#...)
$seo_canonical = $base . strtok($_SERVER['REQUEST_URI'] ?? '/', '#');
if (!empty($meta_canonical)) {
    $seo_canonical = (strpos($meta_canonical, 'http') === 0) ? $meta_canonical : $base . $meta_canonical;
}

// obrazek OG (absolutny)
$seo_image = $meta_image ?? '/favicon.svg';
if (strpos($seo_image, 'http') !== 0) $seo_image = $base . $seo_image;

$seo_type   = $meta_type   ?? 'website';
$seo_robots = $meta_robots ?? 'index, follow';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <!-- Google tag (gtag.js) + Consent Mode v2 -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-88GNK9L9GG"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}

    // Consent Mode v2 (advanced) — domyślnie BRAK zgody, dopóki użytkownik nie zdecyduje w bannerze.
    // Obejmuje użytkowników z EOG (RODO). wait_for_update wstrzymuje tagi na czas decyzji.
    gtag('consent', 'default', {
      'ad_storage': 'denied',
      'ad_user_data': 'denied',
      'ad_personalization': 'denied',
      'analytics_storage': 'denied',
      'functionality_storage': 'denied',
      'personalization_storage': 'denied',
      'security_storage': 'granted',
      'wait_for_update': 500
    });
    // Prywatność przy braku zgody: redakcja danych reklamowych + przekazywanie kliknięć w URL
    gtag('set', 'ads_data_redaction', true);
    gtag('set', 'url_passthrough', true);

    <?php if (($_COOKIE['grailos_consent'] ?? '') === 'all'): ?>
    // Użytkownik wcześniej zaakceptował wszystkie cookie — przywróć zgodę od razu
    gtag('consent', 'update', {
      'ad_storage': 'granted',
      'ad_user_data': 'granted',
      'ad_personalization': 'granted',
      'analytics_storage': 'granted',
      'functionality_storage': 'granted',
      'personalization_storage': 'granted'
    });
    <?php endif; ?>

    gtag('js', new Date());
    gtag('config', 'G-88GNK9L9GG');
  </script>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($seo_title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($seo_desc) ?>">
  <meta name="robots" content="<?= htmlspecialchars($seo_robots) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($seo_canonical) ?>">

  <!-- Open Graph -->
  <meta property="og:type" content="<?= htmlspecialchars($seo_type) ?>">
  <meta property="og:site_name" content="<?= $SITE_NAME ?>">
  <meta property="og:locale" content="pl_PL">
  <meta property="og:title" content="<?= htmlspecialchars($seo_title) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seo_desc) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($seo_canonical) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($seo_image) ?>">
  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($seo_title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($seo_desc) ?>">
  <meta name="twitter:image" content="<?= htmlspecialchars($seo_image) ?>">

  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="apple-touch-icon" href="/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
  <?= $seo_jsonld ?? '' ?>
  <meta name='impact-site-verification' value='ac50d77f-a1bf-4b6c-944d-c230d63aff0c'>
</head>
<body>

<nav class="navbar">

  <!-- LOGO -->
  <a href="/" class="logo">GRAIL<span>OS</span></a>

  <!-- NAV LINKS (desktop center, mobile fullscreen) -->
  <div class="nav-menu" id="navMenu">

    <div class="nav-menu-links">
      <a href="/catalog.php">Catalog</a>
      <a href="/drops.php">Drops</a>
      <a href="/fits.php">Fits</a>
      <a href="/forum.php">Forum</a>
      <a href="/rankings.php">Rankings</a>
    </div>

    <!-- TYLKO MOBILE: auth linki wewnątrz menu -->
    <div class="nav-mob-auth">
      <div class="nav-mob-divider"></div>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="/profile.php?username=<?= urlencode($_SESSION['username']) ?>">
          @<?= htmlspecialchars($_SESSION['username']) ?>
        </a>
        <a href="/notifications.php">Powiadomienia<?= $navUnread > 0 ? ' ('.$navUnread.')' : '' ?></a>
        <a href="/settings.php">Ustawienia</a>
        <?php if ($_SESSION['is_admin']): ?>
          <a href="/admin/">Panel admina</a>
        <?php endif; ?>
        <a href="/logout.php" style="color:var(--red)">Logout</a>
      <?php else: ?>
        <a href="/login.php">Login</a>
        <a href="/register.php">Register</a>
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
               onkeydown="if(event.key==='Enter'){window.location='/search.php?q='+encodeURIComponent(this.value)}">
        <div class="nav-suggest" id="navSuggest"></div>
      </div>
    </div>

    <!-- DESKTOP AUTH -->
    <div class="nav-desk-auth">
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($_SESSION['is_admin']): ?>
          <a href="/admin/" class="nav-sm">Admin</a>
        <?php endif; ?>
        <a href="/notifications.php" class="nav-bell" title="Powiadomienia" aria-label="Powiadomienia">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
          </svg>
          <?php if ($navUnread > 0): ?><span class="nav-bell-badge"><?= $navUnread > 9 ? '9+' : $navUnread ?></span><?php endif; ?>
        </a>
        <a href="/profile.php?username=<?= urlencode($_SESSION['username']) ?>"
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
        <a href="/logout.php" class="nav-sm">Logout</a>
      <?php else: ?>
        <a href="/register.php" class="nav-sm">Register</a>
        <a href="/login.php" class="btn nav-login-btn">Login</a>
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
    const res  = await fetch('/search_suggest.php?q=' + encodeURIComponent(q));
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