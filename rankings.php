<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Best of</div>
  <h1 class="page-title">RANKINGS</h1>
  <p class="page-sub">Najlepsze fity tygodnia i miesiąca.</p>
</section>

<?php
$week = $pdo->query("
    SELECT fits.*, users.username, users.avatar_url,
           COUNT(fit_votes.id) AS votes
    FROM fits
    LEFT JOIN users     ON fits.user_id = users.id
    LEFT JOIN fit_votes ON fits.id = fit_votes.fit_id
    WHERE fits.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY fits.id
    ORDER BY votes DESC
    LIMIT 3
")->fetchAll();

$month = $pdo->query("
    SELECT fits.*, users.username, users.avatar_url,
           COUNT(fit_votes.id) AS votes
    FROM fits
    LEFT JOIN users     ON fits.user_id = users.id
    LEFT JOIN fit_votes ON fits.id = fit_votes.fit_id
    WHERE fits.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY fits.id
    ORDER BY votes DESC
    LIMIT 3
")->fetchAll();

function renderRankingCard($fit, $position) { ?>
  <div class="ranking-card">
    <div class="ranking-pos"><?= $position ?></div>
    <a href="/grailos/fit.php?id=<?= $fit['id'] ?>" class="fit-img-wrap ranking-img">
      <img src="<?= htmlspecialchars($fit['image_url']) ?>" alt="Fit">
    </a>
    <div class="fit-info">
      <div class="fit-user-row">
        <a href="/grailos/profile.php?username=<?= urlencode($fit['username']) ?>"
           class="post-av-link">
          <?php if ($fit['avatar_url']): ?>
            <img src="<?= htmlspecialchars($fit['avatar_url']) ?>"
                 class="fit-mini-avatar" alt="">
          <?php else: ?>
            <span class="fit-mini-avatar fit-mini-initials">
              <?= strtoupper($fit['username'][0]) ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="/grailos/profile.php?username=<?= urlencode($fit['username']) ?>"
           class="rank-user-link">@<?= htmlspecialchars($fit['username']) ?></a>
      </div>
      <div class="fit-footer">
        <span class="fit-date"><?= date('d M Y', strtotime($fit['created_at'])) ?></span>
        <span class="vote-count-static">♥ <?= $fit['votes'] ?></span>
      </div>
    </div>
  </div>
<?php }
?>

<div class="section-label">/// Top tygodnia</div>
<div class="ranking-grid">
  <?php if ($week):
    foreach ($week as $i => $fit) renderRankingCard($fit, $i + 1);
  else: ?>
    <div style="grid-column:span 3; padding:60px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Za mało fitów w tym tygodniu.
    </div>
  <?php endif; ?>
</div>

<div class="section-label">/// Top miesiąca</div>
<div class="ranking-grid">
  <?php if ($month):
    foreach ($month as $i => $fit) renderRankingCard($fit, $i + 1);
  else: ?>
    <div style="grid-column:span 3; padding:60px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Za mało fitów w tym miesiącu.
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>