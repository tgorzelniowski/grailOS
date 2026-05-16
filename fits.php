<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Community</div>
  <h1 class="page-title">FITS</h1>
  <p class="page-sub">Pokaż co masz. Zainspiruj innych.</p>
</section>

<div style="padding:24px 40px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
  <div class="section-label" style="padding:0; border:none;">
    /// <?= $pdo->query("SELECT COUNT(*) FROM fits")->fetchColumn() ?> fitów w bazie
  </div>
  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="/grailos/submit_fit.php" class="btn">+ Dodaj fit</a>
  <?php else: ?>
    <a href="/grailos/login.php" class="btn btn-outline">Zaloguj się by dodać fit</a>
  <?php endif; ?>
</div>

<?php
$fits = $pdo->query("
    SELECT fits.*, users.username,
           COUNT(DISTINCT fit_votes.id)    AS votes,
           COUNT(DISTINCT fit_comments.id) AS comments
    FROM fits
    LEFT JOIN users        ON fits.user_id    = users.id
    LEFT JOIN fit_votes    ON fits.id          = fit_votes.fit_id
    LEFT JOIN fit_comments ON fits.id          = fit_comments.fit_id
    GROUP BY fits.id
    ORDER BY fits.created_at DESC
")->fetchAll();
?>

<div class="fits-grid">
  <?php if ($fits): ?>
    <?php foreach ($fits as $fit): ?>
      <a href="/grailos/fit.php?id=<?= $fit['id'] ?>" class="fit-card">
        <div class="fit-img-wrap">
          <img src="<?= htmlspecialchars($fit['image_url']) ?>"
               alt="Fit by @<?= htmlspecialchars($fit['username']) ?>">
        </div>
        <div class="fit-info">
          <div class="fit-user">@<?= htmlspecialchars($fit['username']) ?></div>
          <?php if ($fit['description']): ?>
            <div class="fit-desc"><?= htmlspecialchars(mb_substr($fit['description'], 0, 80)) ?>...</div>
          <?php endif; ?>
          <div class="fit-footer">
            <span class="fit-date"><?= date('d M Y', strtotime($fit['created_at'])) ?></span>
            <span class="vote-count-static">♥ <?= $fit['votes'] ?> &nbsp; 💬 <?= $fit['comments'] ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3; padding:80px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:13px;">
      Brak fitów. Bądź pierwszy.
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>