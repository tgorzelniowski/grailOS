<?php require_once 'includes/header.php'; ?>

<!-- TICKER -->
<div class="ticker">
  <span>Witaj w GrailOS</span>
  <span>///</span>
  <span>Twoje centrum streetwearu</span>
  <span>///</span>
  <span>Catalog. Drops. Fits. Rankings.</span>
</div>

<!-- HERO -->
<section class="hero">
  <div class="hero-tag">The operating system for your wardrobe</div>
  <h1 class="hero-title">FIND<br>YOUR<br><span>GRAIL</span></h1>
  <p class="hero-sub">Catalog. Drops. Fits. Rankings. Wszystko o streetwearze w jednym miejscu.</p>
  <a href="/grailos/catalog.php" class="btn">Explore catalog →</a>
</section>

<!-- SEKCJA KATALOGU -->
<div class="section-label">/// New in catalog</div>
<div class="clothing-grid">
  <?php
    $stmt = $pdo->query("SELECT * FROM clothing ORDER BY created_at DESC LIMIT 3");
    $items = $stmt->fetchAll();

    if ($items):
      foreach ($items as $item): ?>
        <div class="clothing-card">
          <div class="clothing-card-img">
            <?php if ($item['image_url']): ?>
              <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
            <?php else: ?>
              NO IMG
            <?php endif; ?>
          </div>
          <div class="clothing-card-brand"><?= htmlspecialchars($item['brand']) ?></div>
          <div class="clothing-card-name"><?= htmlspecialchars($item['name']) ?></div>
          <div class="clothing-card-price"><?= $item['price'] ? number_format($item['price'], 2) . ' zł' : '—' ?></div>
        </div>
    <?php endforeach;
    else: ?>
      <div class="clothing-card" style="grid-column: span 3; text-align:center; padding: 60px; color: #444; letter-spacing: 3px; text-transform: uppercase; font-size: 13px;">
        Brak produktów w katalogu
      </div>
    <?php endif; ?>
</div>

<!-- SEKCJA DROPÓW -->
<div class="section-label">/// Upcoming drops</div>
<div class="drops-list" style="padding:0 40px;">
<?php
  $stmt = $pdo->query("SELECT * FROM drops ORDER BY drop_date ASC LIMIT 3");
  $drops = $stmt->fetchAll();

  if ($drops):
    foreach ($drops as $drop): ?>
      <div class="drop-row">
        <div class="drop-row-thumb">
          <?php if ($drop['image_url']): ?>
            <img src="<?= htmlspecialchars($drop['image_url']) ?>" alt="">
          <?php endif; ?>
        </div>
        <div>
          <div class="drop-name"><?= htmlspecialchars($drop['name']) ?></div>
          <div class="drop-brand"><?= htmlspecialchars($drop['brand']) ?></div>
        </div>
        <div class="drop-date">
          <?= date('d M', strtotime($drop['drop_date'])) ?><br>
          <?= date('H:i', strtotime($drop['drop_date'])) ?>
        </div>
      </div>
  <?php endforeach;
  else: ?>
    <div style="padding:32px 0; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Brak dropów.
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>