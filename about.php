<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Who we are</div>
  <h1 class="page-title">ABOUT<br>GRAILOS</h1>
</section>

<div class="about-wrap">
  <div class="about-block">
    <div class="about-tag">/// The idea</div>
    <h2 class="about-heading">Zbudowane przez kogoś, kto żyje tym tak samo jak ty.</h2>
    <p class="about-text">
      GrailOS powstał z jednego powodu — nie było jednego miejsca które łączyłoby katalog streetwearu,
      tracker dropów i community fitów w jednym. Więc zbudowaliśmy to sami. Od zera. Solo.
    </p>
  </div>

  <div class="about-block">
    <div class="about-tag">/// The mission</div>
    <h2 class="about-heading">Wszystko o streetwearze. W jednym miejscu.</h2>
    <p class="about-text">
      Catalog. Drops. Fits. Forum. Rankings. GrailOS to system operacyjny twojej garderoby.
      Znajdziesz tu inspirację, nie przegapisz żadnego dropu i pokażesz swój fit światu.
    </p>
  </div>

  <?php
    $clothing_count = $pdo->query("SELECT COUNT(*) FROM clothing")->fetchColumn();
    $drops_count    = $pdo->query("SELECT COUNT(*) FROM drops")->fetchColumn();
    $users_count    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $fits_count     = $pdo->query("SELECT COUNT(*) FROM fits")->fetchColumn();
  ?>
  <div class="about-stats">
    <div class="about-stat">
      <div class="about-stat-num"><?= $clothing_count ?></div>
      <div class="about-stat-label">Produktów w katalogu</div>
    </div>
    <div class="about-stat">
      <div class="about-stat-num"><?= $drops_count ?></div>
      <div class="about-stat-label">Dropów w bazie</div>
    </div>
    <div class="about-stat">
      <div class="about-stat-num"><?= $fits_count ?></div>
      <div class="about-stat-label">Fitów w community</div>
    </div>
    <div class="about-stat">
      <div class="about-stat-num"><?= $users_count ?></div>
      <div class="about-stat-label">Użytkowników</div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>