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
  <a href="/grailos/drop.php?id=<?= $drop['id'] ?>" class="drop-row-full drop-row-link"></a>
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