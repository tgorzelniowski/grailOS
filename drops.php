<?php
require_once 'config/db.php';

$uid = (int)($_SESSION['user_id'] ?? 0);

$drops = $pdo->prepare("
    SELECT drops.*,
           COUNT(DISTINCT dh.id) AS hype_count,
           MAX(CASE WHEN dh.user_id = ? THEN 1 ELSE 0 END) AS user_hyped
    FROM drops
    LEFT JOIN drop_hypes dh ON drops.id = dh.drop_id
    GROUP BY drops.id
    ORDER BY drops.drop_date ASC
");
$drops->execute([$uid]);
$drops = $drops->fetchAll();

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Incoming</div>
  <h1 class="page-title">DROP<br>TRACKER</h1>
  <p class="page-sub">Nigdy więcej nie przegap dropu.</p>
</section>

<div class="section-label">/// Nadchodzące dropy</div>
<div class="drops-list">
<?php if ($drops): foreach ($drops as $drop):
  $days = (int)ceil((strtotime($drop['drop_date']) - time()) / 86400);
?>
  <div class="drop-row-full">
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
        <?php else: ?>
          Za <?= $days ?> dni
        <?php endif; ?>
      </div>
      <!-- HYPE COUNTER -->
      <div class="drop-hype-wrap">
        <?php if ($uid): ?>
          <button class="hype-btn <?= $drop['user_hyped'] ? 'hyped' : '' ?>"
                  onclick="hypeToggle(<?= $drop['id'] ?>, this)">
            <span class="hype-count"><?= $drop['hype_count'] ?></span>
            <span class="hype-label"><?= $drop['user_hyped'] ? 'Hype!' : 'Hype' ?></span>
          </button>
        <?php else: ?>
          <span class="hype-static"><?= $drop['hype_count'] ?> osób chce ten drop</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="drop-meta">
      <div class="drop-date">
        <?= date('d M Y', strtotime($drop['drop_date'])) ?><br>
        <?= date('H:i', strtotime($drop['drop_date'])) ?>
      </div>
      <?php if ($drop['link']): ?>
        <a href="<?= htmlspecialchars($drop['link']) ?>"
           target="_blank" class="btn" style="margin-top:12px;">Kup →</a>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach;
else: ?>
  <div style="padding:80px 40px; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:13px;">
    Brak nadchodzących dropów.
  </div>
<?php endif; ?>
</div>

<script>
async function hypeToggle(dropId, btn) {
  const res  = await fetch('/grailos/hype_drop.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `drop_id=${dropId}`
  });
  const data = await res.json();
  if (data.success) {
    btn.classList.toggle('hyped', data.hyped);
    btn.querySelector('.hype-count').textContent = data.count;
    btn.querySelector('.hype-label').textContent  = data.hyped ? 'Hype!' : 'Hype';
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>