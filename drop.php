<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /grailos/drops.php'); exit; }

$uid  = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT drops.*,
           COUNT(DISTINCT dh.id) AS hype_count,
           MAX(CASE WHEN dh.user_id = ? THEN 1 ELSE 0 END) AS user_hyped
    FROM drops
    LEFT JOIN drop_hypes dh ON drops.id = dh.drop_id
    WHERE drops.id = ?
    GROUP BY drops.id
");
$stmt->execute([$uid, $id]);
$drop = $stmt->fetch();
if (!$drop) { header('Location: /grailos/drops.php'); exit; }

$imgs_stmt = $pdo->prepare("SELECT * FROM drop_images WHERE drop_id = ? ORDER BY sort_order ASC");
$imgs_stmt->execute([$id]);
$extra_imgs = $imgs_stmt->fetchAll();

$all_imgs = [];
if ($drop['image_url']) $all_imgs[] = $drop['image_url'];
foreach ($extra_imgs as $img) $all_imgs[] = $img['image_url'];

$days = (int)ceil((strtotime($drop['drop_date']) - time()) / 86400);

require_once 'includes/header.php';
?>

<div class="item-page">

  <!-- SLIDER -->
  <div class="item-slider-wrap">
    <div class="item-slider" id="dropSlider">
      <?php if ($all_imgs): ?>
        <?php foreach ($all_imgs as $i => $img): ?>
          <div class="slide <?= $i === 0 ? 'active' : '' ?>">
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($drop['name']) ?>">
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="slide active">
          <div class="item-no-img">NO IMAGE</div>
        </div>
      <?php endif; ?>
    </div>
    <?php if (count($all_imgs) > 1): ?>
      <button class="slide-btn slide-prev" onclick="slide(-1)">‹</button>
      <button class="slide-btn slide-next" onclick="slide(1)">›</button>
      <div class="item-thumbs">
        <?php foreach ($all_imgs as $i => $img): ?>
          <button class="thumb <?= $i === 0 ? 'active' : '' ?>"
                  onclick="goSlide(<?= $i ?>)">
            <img src="<?= htmlspecialchars($img) ?>" alt="">
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- INFO -->
  <div class="item-info-wrap">
    <div class="item-brand"><?= htmlspecialchars($drop['brand']) ?></div>
    <h1 class="item-name"><?= htmlspecialchars($drop['name']) ?></h1>

    <!-- DATA DROPU -->
    <div class="drop-detail-date">
      <div class="drop-detail-label">/// Data dropu</div>
      <div class="drop-detail-val">
        <?= date('d M Y, H:i', strtotime($drop['drop_date'])) ?>
      </div>
      <div class="drop-countdown-big">
        <?php if ($days < 0): ?>
          <span style="color:var(--gray)">Drop już był</span>
        <?php elseif ($days === 0): ?>
          <span style="color:var(--red)">DZIŚ 🔥</span>
        <?php elseif ($days === 1): ?>
          <span style="color:var(--red)">JUTRO</span>
        <?php else: ?>
          Za <?= $days ?> dni
        <?php endif; ?>
      </div>
    </div>

    <!-- OPIS -->
    <?php if ($drop['description']): ?>
      <div class="drop-detail-label" style="margin-top:24px;">/// Opis</div>
      <div class="item-desc-wrap">
        <p class="item-desc" id="itemDesc">
          <?= nl2br(htmlspecialchars($drop['description'])) ?>
        </p>
        <?php if (strlen($drop['description']) > 200): ?>
          <button class="desc-toggle" id="descToggle" onclick="toggleDesc()">
            Pokaż więcej ▼
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- HYPE -->
    <div style="margin-top:28px; padding-top:24px; border-top:1px solid var(--border);">
      <div class="drop-detail-label">/// Hype</div>
      <?php if ($uid): ?>
        <button class="hype-btn <?= $drop['user_hyped'] ? 'hyped' : '' ?>"
                id="hypeBtn"
                onclick="hypeToggle(<?= $drop['id'] ?>)">
          <span class="hype-fire">🔥</span>
          <span class="hype-count"><?= $drop['hype_count'] ?></span>
          <span class="hype-label"><?= $drop['user_hyped'] ? 'Hype!' : 'Hype' ?></span>
        </button>
      <?php else: ?>
        <span class="hype-static">🔥 <?= $drop['hype_count'] ?> osób chce ten drop</span>
      <?php endif; ?>
    </div>

    <!-- KUP -->
    <?php if ($drop['link']): ?>
      <div style="margin-top:24px;">
        <a href="<?= htmlspecialchars($drop['link']) ?>"
           target="_blank" rel="noopener"
           class="btn" style="width:100%; text-align:center; display:block;">
          Kup teraz →
        </a>
      </div>
    <?php endif; ?>

    <div style="margin-top:16px;">
      <a href="/grailos/drops.php" class="btn btn-outline"
         style="font-size:11px; padding:6px 16px;">← Wszystkie dropy</a>
    </div>
  </div>
</div>

<script>
let current = 0;
const slides = document.querySelectorAll('.slide');
const thumbs = document.querySelectorAll('.thumb');

function goSlide(n) {
  slides[current].classList.remove('active');
  thumbs[current]?.classList.remove('active');
  current = (n + slides.length) % slides.length;
  slides[current].classList.add('active');
  thumbs[current]?.classList.add('active');
}

function slide(dir) { goSlide(current + dir); }

function toggleDesc() {
  const desc = document.getElementById('itemDesc');
  const btn  = document.getElementById('descToggle');
  const icon = btn ? btn.querySelector('.desc-toggle-icon') : null;
  if (!desc) return;

  if (desc.classList.contains('expanded')) {
    desc.style.maxHeight = '72px';
    desc.classList.remove('expanded');
    if (btn)  btn.innerHTML  = '<span class="desc-toggle-icon">▼</span> Pokaż więcej';
  } else {
    desc.style.maxHeight = desc.scrollHeight + 'px';
    desc.classList.add('expanded');
    if (btn)  btn.innerHTML  = '<span class="desc-toggle-icon">▲</span> Pokaż mniej';
  }
}

async function hypeToggle(dropId) {
  const btn  = document.getElementById('hypeBtn');
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