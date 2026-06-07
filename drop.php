<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /drops.php'); exit; }

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
if (!$drop) { header('Location: /drops.php'); exit; }

$imgs_stmt = $pdo->prepare("SELECT * FROM drop_images WHERE drop_id = ? ORDER BY sort_order ASC");
$imgs_stmt->execute([$id]);
$extra_imgs = $imgs_stmt->fetchAll();

$all_imgs = [];
if ($drop['image_url']) $all_imgs[] = $drop['image_url'];
foreach ($extra_imgs as $img) $all_imgs[] = $img['image_url'];

$days = (int)ceil((strtotime($drop['drop_date']) - time()) / 86400);

$is_notified = false;
if ($uid) {
    $nstmt = $pdo->prepare("SELECT id FROM drop_notifications WHERE drop_id = ? AND user_id = ?");
    $nstmt->execute([$drop['id'], $uid]);
    $is_notified = (bool)$nstmt->fetch();
}

// ---- SEO ----
$meta_title = trim(($drop['brand'] ? $drop['brand'] . ' ' : '') . $drop['name']) . ' — drop';
$meta_desc  = $drop['description']
    ? $drop['description']
    : ('Data dropu: ' . date('d.m.Y H:i', strtotime($drop['drop_date'])) . '. Premiera ' .
       trim(($drop['brand'] ? $drop['brand'] . ' ' : '') . $drop['name']) . ' na GrailOS — ustaw przypomnienie.');
$meta_image = $drop['image_url'] ?: '';
$meta_type  = 'product';

require_once 'includes/header.php';
?>

<div class="item-page-v2">

  <!-- KAFELKI PO LEWEJ -->
  <div class="item-thumbs-v">
    <?php foreach ($all_imgs as $i => $img): ?>
      <button class="item-thumb-v <?= $i === 0 ? 'active' : '' ?>"
              onclick="goSlide(<?= $i ?>)">
        <img src="<?= htmlspecialchars($img) ?>" alt="">
      </button>
    <?php endforeach; ?>
    <?php if (!$all_imgs): ?>
      <div style="width:86px; height:86px; background:var(--card); border:1px solid var(--border);"></div>
    <?php endif; ?>
  </div>

  <!-- GŁÓWNE ZDJĘCIE -->
  <div class="item-main-img">
    <?php if ($all_imgs): ?>
      <?php foreach ($all_imgs as $i => $img): ?>
        <div class="item-slide <?= $i === 0 ? 'active' : '' ?>">
          <img src="<?= htmlspecialchars($img) ?>"
               alt="<?= htmlspecialchars($drop['name']) ?>">
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="item-slide active">
        <div class="item-no-img">NO IMAGE</div>
      </div>
    <?php endif; ?>
    <?php if (count($all_imgs) > 1): ?>
      <button class="item-nav-btn item-nav-prev" onclick="slide(-1)">‹</button>
      <button class="item-nav-btn item-nav-next" onclick="slide(1)">›</button>
    <?php endif; ?>
  </div>

  <!-- INFO PO PRAWEJ -->
  <div class="item-info-v">

    <div class="item-brand-v"><?= htmlspecialchars($drop['brand']) ?></div>
    <h1 class="item-name-v"><?= htmlspecialchars($drop['name']) ?></h1>

    <!-- DATA I ODLICZANIE -->
    <div class="item-price-v" style="font-size:20px; letter-spacing:2px;">
      <?= date('d M Y · H:i', strtotime($drop['drop_date'])) ?>
      <div style="font-size:15px; color:
        <?= $days < 0 ? 'var(--gray)' : 'var(--red)' ?>; margin-top:4px;">
        <?php if ($days < 0): ?>
          Drop już był
        <?php elseif ($days === 0): ?>
          DZIŚ
        <?php elseif ($days === 1): ?>
          JUTRO
        <?php else: ?>
          Za <?= $days ?> dni
        <?php endif; ?>
      </div>
    </div>

    <!-- KUP TERAZ -->
    <?php if ($drop['link']): ?>
      <a href="<?= htmlspecialchars($drop['link']) ?>"
         target="_blank" rel="noopener"
         class="btn item-wardrobe-btn">
        Kup teraz →
      </a>
    <?php endif; ?>

    <!-- HYPE -->
    <div class="item-buy-section">
      <div class="item-section-label">/// Hype</div>
      <?php if ($uid): ?>
        <button class="hype-btn <?= $drop['user_hyped'] ? 'hyped' : '' ?>"
                id="hypeBtn" onclick="hypeToggle(<?= $drop['id'] ?>)">
          <span class="hype-fire">🔥</span>
          <span class="hype-count"><?= $drop['hype_count'] ?></span>
          <span class="hype-label"><?= $drop['user_hyped'] ? 'Hype!' : 'Hype' ?></span>
        </button>
      <?php else: ?>
        <span class="hype-static"><?= $drop['hype_count'] ?> osób zainteresowanych</span>
      <?php endif; ?>
    </div>

    <!-- POWIADOM -->
    <?php if ($days >= 0): ?>
    <div class="item-buy-section">
      <div class="item-section-label">/// Powiadomienie</div>
      <?php if ($uid): ?>
        <button class="notify-btn <?= $is_notified ? 'notified' : '' ?>"
                id="notifyBtn" onclick="toggleNotify(<?= $drop['id'] ?>)">
          <span class="notify-icon"><?= $is_notified ? '🔔' : '🔕' ?></span>
          <span class="notify-label"><?= $is_notified ? 'Powiadomimy Cię!' : 'Powiadom mnie' ?></span>
        </button>
      <?php else: ?>
        <form id="notifyGuestForm" onsubmit="notifyGuest(event)">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="drop_id" value="<?= $drop['id'] ?>">
          <div style="display:flex; gap:8px; margin-top:8px;">
            <input type="email" name="email" id="notifyEmail"
                   class="form-input" placeholder="twoj@email.com"
                   style="flex:1;" required>
            <button type="submit" class="btn"
                    style="font-size:11px; padding:8px 14px; white-space:nowrap;">Zapisz →</button>
          </div>
          <div id="notifyMsg" style="font-size:12px; letter-spacing:1px; margin-top:8px;"></div>
        </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- OPIS -->
    <?php if ($drop['description']): ?>
      <div class="item-desc-v2-section">
        <button class="item-desc-v2-toggle" onclick="toggleDesc()">
          <span>Opis dropu</span>
          <span id="descArrow">▼</span>
        </button>
        <div class="item-desc-v2" id="itemDesc">
          <?= nl2br(htmlspecialchars($drop['description'])) ?>
        </div>
      </div>
    <?php endif; ?>

    <div style="margin-top:20px;">
      <a href="/drops.php" class="btn btn-outline"
         style="font-size:11px; padding:6px 16px;">← Wszystkie dropy</a>
    </div>

  </div>
</div>

<script>
let current = 0;
const slides  = document.querySelectorAll('.item-slide');
const thumbsV = document.querySelectorAll('.item-thumb-v');

function goSlide(n) {
  slides[current].classList.remove('active');
  if (thumbsV[current]) thumbsV[current].classList.remove('active');
  current = (n + slides.length) % slides.length;
  slides[current].classList.add('active');
  if (thumbsV[current]) thumbsV[current].classList.add('active');
}

function slide(dir) { goSlide(current + dir); }

function toggleDesc() {
  const desc  = document.getElementById('itemDesc');
  const arrow = document.getElementById('descArrow');
  const open  = desc.classList.toggle('expanded');
  if (arrow) arrow.textContent = open ? '▲' : '▼';
}

async function hypeToggle(dropId) {
  const btn  = document.getElementById('hypeBtn');
  const res  = await fetch('/hype_drop.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `drop_id=${dropId}`
  });
  const data = await res.json();
  if (data.success) {
    btn.classList.toggle('hyped', data.hyped);
    btn.querySelector('.hype-count').textContent = data.count;
    btn.querySelector('.hype-label').textContent = data.hyped ? 'Hype!' : 'Hype';
  }
}

async function toggleNotify(dropId) {
  const btn   = document.getElementById('notifyBtn');
  const body  = new URLSearchParams({ csrf_token: '<?= csrf_token() ?>', drop_id: dropId });
  const res   = await fetch('/drop_notify.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: body.toString(),
  });
  const data = await res.json();
  if (data.ok) {
    btn.classList.toggle('notified', data.subscribed);
    btn.querySelector('.notify-label').textContent = data.subscribed ? 'Powiadomimy Cię!' : 'Powiadom mnie';
    btn.querySelector('.notify-icon').textContent  = data.subscribed ? '🔔' : '🔕';
  }
}

async function notifyGuest(e) {
  e.preventDefault();
  const form = e.target;
  const msg  = document.getElementById('notifyMsg');
  const res  = await fetch('/drop_notify.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams(new FormData(form)).toString(),
  });
  const data = await res.json();
  msg.textContent = data.msg || data.error || '';
  msg.style.color = data.ok ? '#2ecc71' : 'var(--red)';
  if (data.ok) form.reset();
}
</script>

<?php require_once 'includes/footer.php'; ?>
