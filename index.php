<?php require_once 'includes/header.php'; ?>

<!-- TICKER -->
<div class="ticker">
  <span>Witaj w GrailOS</span>
  <span>///</span>
  <span>Twoje centrum streetwearu</span>
  <span>///</span>
  <span>Catalog. Drops. Fits. Forum. Rankings.</span>
</div>

<!-- STORIES -->
<?php require_once 'includes/stories_bar.php'; ?>

<!-- HERO -->
<section class="hero">
  <div class="hero-tag">The operating system for your wardrobe</div>
  <h1 class="hero-title">FIND<br>YOUR<br><span>GRAIL</span></h1>
  <p class="hero-sub">Catalog. Drops. Fits. Forum. Rankings. Wszystko o streetwearze w jednym miejscu.</p>
  <a href="/catalog.php" class="btn">Explore catalog →</a>
</section>

<!-- SEKCJA KATALOGU -->
<div class="section-label">/// New in catalog</div>
<div class="clothing-grid clothing-grid--home">
  <?php
    $stmt = $pdo->query("SELECT * FROM clothing ORDER BY created_at DESC LIMIT 4");
    $items = $stmt->fetchAll();

    if ($items):
      foreach ($items as $item): ?>
        <a href="/item.php?id=<?= $item['id'] ?>"
           class="clothing-card" style="text-decoration:none; color:inherit; display:block;">
          <div class="clothing-card-img">
            <?php if ($item['image_url']): ?>
              <img src="<?= htmlspecialchars($item['image_url']) ?>"
                   alt="<?= htmlspecialchars($item['name']) ?>">
            <?php else: ?>
              NO IMG
            <?php endif; ?>
          </div>
          <div class="clothing-card-brand"><?= htmlspecialchars($item['brand']) ?></div>
          <div class="clothing-card-name"><?= htmlspecialchars($item['name']) ?></div>
          <div class="clothing-card-price">
            <?= $item['price'] ? number_format($item['price'], 2) . ' zł' : '—' ?>
          </div>
        </a>
      <?php endforeach;
    else: ?>
      <div style="grid-column:span 4; padding:60px; text-align:center; color:#444;
                  letter-spacing:3px; text-transform:uppercase; font-size:13px;">
        Brak produktów w katalogu
      </div>
    <?php endif; ?>
</div>

<!-- SEKCJA DROPÓW -->
<div class="section-label">/// Upcoming drops</div>
<div class="drops-list">
<?php
  $stmt = $pdo->query("SELECT * FROM drops ORDER BY drop_date ASC LIMIT 3");
  $drops = $stmt->fetchAll();

  if ($drops):
    foreach ($drops as $drop):
      $days = (int)ceil((strtotime($drop['drop_date']) - time()) / 86400);
?>
  <a href="/drop.php?id=<?= $drop['id'] ?>" class="drop-row-full-link">
    <div class="drop-img-wrap">
      <?php if ($drop['image_url']): ?>
        <img src="<?= htmlspecialchars($drop['image_url']) ?>" alt="">
      <?php else: ?>
        <div class="drop-no-img">NO IMG</div>
      <?php endif; ?>
    </div>
    <div class="drop-info">
      <div class="drop-brand"><?= htmlspecialchars($drop['brand']) ?></div>
      <div class="drop-name"><?= htmlspecialchars($drop['name']) ?></div>
      <div class="drop-countdown">
        <?php if ($days <= 0): ?>
          <span style="color:var(--red)">DZIŚ</span>
        <?php elseif ($days === 1): ?>
          <span style="color:var(--red)">JUTRO</span>
        <?php else: ?>Za <?= $days ?> dni<?php endif; ?>
      </div>
    </div>
    <div class="drop-meta">
      <div class="drop-date">
        <?= date('d M Y', strtotime($drop['drop_date'])) ?><br>
        <?= date('H:i', strtotime($drop['drop_date'])) ?>
      </div>
    </div>
  </a>
<?php endforeach;
  else: ?>
  <div style="padding:32px 40px; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
    Brak dropów.
  </div>
<?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>