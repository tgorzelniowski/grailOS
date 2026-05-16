<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Incoming</div>
  <h1 class="page-title">DROP<br>TRACKER</h1>
  <p class="page-sub">Nigdy więcej nie przegap dropu.</p>
</section>

<div class="section-label">/// Nadchodzące dropy</div>
<div class="drops-list">
<?php
  $stmt = $pdo->query("SELECT * FROM drops WHERE drop_date >= NOW() ORDER BY drop_date ASC");
  $drops = $stmt->fetchAll();

  if ($drops):
    foreach ($drops as $drop):
      $days_left = (int) ceil((strtotime($drop['drop_date']) - time()) / 86400);
?>
  <div class="drop-row-full">
    <div class="drop-img-wrap">
      <?php if ($drop['image_url']): ?>
        <img src="<?= htmlspecialchars($drop['image_url']) ?>" alt="<?= htmlspecialchars($drop['name']) ?>">
      <?php else: ?>
        <div class="drop-no-img">NO IMG</div>
      <?php endif; ?>
    </div>
    <div class="drop-info">
      <div class="drop-brand"><?= htmlspecialchars($drop['brand']) ?></div>
      <div class="drop-name"><?= htmlspecialchars($drop['name']) ?></div>
      <div class="drop-countdown">
        <?php if ($days_left === 0): ?>
          <span style="color: var(--red)">DZIŚ</span>
        <?php elseif ($days_left === 1): ?>
          <span style="color: var(--red)">JUTRO</span>
        <?php else: ?>
          Za <?= $days_left ?> dni
        <?php endif; ?>
      </div>
    </div>
    <div class="drop-meta">
      <div class="drop-date">
        <?= date('d M Y', strtotime($drop['drop_date'])) ?><br>
        <?= date('H:i', strtotime($drop['drop_date'])) ?>
      </div>
      <?php if ($drop['link']): ?>
        <a href="<?= htmlspecialchars($drop['link']) ?>" target="_blank" class="btn" style="margin-top:12px">
          Kup →
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php
    endforeach;
  else:
?>
  <div style="padding: 80px 40px; color: #444; letter-spacing: 3px; text-transform: uppercase; font-size: 13px;">
    Brak nadchodzących dropów. Wróć wkrótce.
  </div>
<?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>