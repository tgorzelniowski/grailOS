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
  <a href="/grailos/" class="logo">GRAIL<span>OS</span></a>

  <div class="nav-links" id="navLinks">
    <a href="/grailos/catalog.php">Catalog</a>
    <a href="/grailos/drops.php">Drops</a>
    <a href="/grailos/fits.php">Fits</a>
    <a href="/grailos/forum.php">Forum</a>
    <a href="/grailos/rankings.php">Rankings</a>
  </div>

  <div class="nav-right">
    <?php if (isset($_SESSION['user_id'])): ?>
      <?php if ($_SESSION['is_admin']): ?>
        <a href="/grailos/admin/" class="nav-login">Admin</a>
      <?php endif; ?>
      <span class="nav-username"><?= htmlspecialchars($_SESSION['username']) ?></span>
      <a href="/grailos/logout.php" class="nav-login">Logout</a>
    <?php else: ?>
      <a href="/grailos/register.php" class="nav-login">Register</a>
      <a href="/grailos/login.php" class="nav-login">Login</a>
    <?php endif; ?>
    <button class="hamburger" id="hamburger" onclick="toggleNav()">☰</button>
  </div>
</nav>

<script>
function toggleNav() {
  const nav = document.getElementById('navLinks');
  nav.classList.toggle('open');
}
</script>