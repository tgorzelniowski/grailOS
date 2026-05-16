<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Best of</div>
  <h1 class="page-title">RANKINGS</h1>
  <p class="page-sub">Najlepsze fity tygodnia i miesiąca.</p>
</section>

<?php
// Top tygodnia
$week = $pdo->query("
  SELECT fits.*, users.username, COUNT(fit_votes.id) as votes
  FROM fits
  LEFT JOIN users ON fits.user_id = users.id
  LEFT JOIN fit_votes ON fits.id = fit_votes.fit_id
  WHERE fits.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
  GROUP BY fits.id
  ORDER BY votes DESC
  LIMIT 3
")->fetchAll();

// Top miesiąca
$month = $pdo->query("
  SELECT fits.*, users.username, COUNT(fit_votes.id) as votes
  FROM fits
  LEFT JOIN users ON fits.user_id = users.id
  LEFT JOIN fit_votes ON fits.id = fit_votes.fit_id
  WHERE fits.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY fits.id
  ORDER BY votes DESC
  LIMIT 3
")->fetchAll();
?>

<div class="section-label">/// Top tygodnia</div>
<div class="ranking-grid">
  <?php if ($week): ?>
    <?php foreach ($week as $i => $fit): ?>
      <div class="ranking-card">
        <div class="ranking-pos"><?= $i + 1 ?></div>
        <div class="fit-img-wrap">
          <img src="<?= htmlspecialchars($fit['image_url']) ?>"
               alt="Fit by <?= htmlspecialchars($fit['username']) ?>">
        </div>
        <div class="fit-info">
          <div class="fit-user">@<?= htmlspecialchars($fit['username']) ?></div>
          <?php if ($fit['description']): ?>
            <div class="fit-desc"><?= htmlspecialchars($fit['description']) ?></div>
          <?php endif; ?>
          <div class="fit-footer">
            <span class="vote-count-static">♥ <?= $fit['votes'] ?> głosów</span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3; padding:60px 40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Za mało fitów w tym tygodniu.
    </div>
  <?php endif; ?>
</div>

<div class="section-label">/// Top miesiąca</div>
<div class="ranking-grid">
  <?php if ($month): ?>
    <?php foreach ($month as $i => $fit): ?>
      <div class="ranking-card">
        <div class="ranking-pos"><?= $i + 1 ?></div>
        <div class="fit-img-wrap">
          <img src="<?= htmlspecialchars($fit['image_url']) ?>"
               alt="Fit by <?= htmlspecialchars($fit['username']) ?>">
        </div>
        <div class="fit-info">
          <div class="fit-user">@<?= htmlspecialchars($fit['username']) ?></div>
          <?php if ($fit['description']): ?>
            <div class="fit-desc"><?= htmlspecialchars($fit['description']) ?></div>
          <?php endif; ?>
          <div class="fit-footer">
            <span class="vote-count-static">♥ <?= $fit['votes'] ?> głosów</span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3; padding:60px 40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Za mało fitów w tym miesiącu.
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>