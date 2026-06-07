<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /catalog.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM clothing WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { header('Location: /catalog.php'); exit; }

$imgs_stmt = $pdo->prepare("SELECT * FROM clothing_images WHERE clothing_id = ? ORDER BY sort_order ASC");
$imgs_stmt->execute([$id]);
$extra_imgs = $imgs_stmt->fetchAll();

$all_imgs = [];
if ($item['image_url']) $all_imgs[] = $item['image_url'];
foreach ($extra_imgs as $img) $all_imgs[] = $img['image_url'];

$links_stmt = $pdo->prepare("SELECT * FROM clothing_links WHERE clothing_id = ? ORDER BY price ASC");
$links_stmt->execute([$id]);
$links = $links_stmt->fetchAll();

$in_wardrobe      = false;
$wardrobe_item_id = null;
if (isset($_SESSION['user_id'])) {
    $w = $pdo->prepare("SELECT id FROM wardrobe_items WHERE user_id = ? AND clothing_id = ?");
    $w->execute([$_SESSION['user_id'], $id]);
    $wrow = $w->fetch();
    if ($wrow) { $in_wardrobe = true; $wardrobe_item_id = $wrow['id']; }
}

// Śledzenie ostatnio przeglądanych
if (!isset($_SESSION['recently_viewed'])) $_SESSION['recently_viewed'] = [];
$_SESSION['recently_viewed'] = array_values(array_filter($_SESSION['recently_viewed'], fn($i) => $i !== $id));
array_unshift($_SESSION['recently_viewed'], $id);
$_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 5);

// Pobierz ostatnio przeglądane (bez bieżącego)
$rv_ids   = array_slice(array_filter($_SESSION['recently_viewed'], fn($i) => $i !== $id), 0, 4);
$rv_items = [];
if ($rv_ids) {
    $ph = implode(',', array_fill(0, count($rv_ids), '?'));
    $rv_stmt = $pdo->prepare("SELECT * FROM clothing WHERE id IN ($ph)");
    $rv_stmt->execute($rv_ids);
    $fetched = $rv_stmt->fetchAll();
    usort($fetched, fn($a, $b) => array_search($a['id'], $rv_ids) - array_search($b['id'], $rv_ids));
    $rv_items = $fetched;
}

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
  </div>

  <!-- GŁÓWNE ZDJĘCIE -->
  <div class="item-main-img">
    <?php if ($all_imgs): ?>
      <?php foreach ($all_imgs as $i => $img): ?>
        <div class="item-slide <?= $i === 0 ? 'active' : '' ?>">
          <img src="<?= htmlspecialchars($img) ?>"
               alt="<?= htmlspecialchars($item['name']) ?>">
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

    <div class="item-brand-v"><?= htmlspecialchars($item['brand']) ?></div>
    <h1 class="item-name-v"><?= htmlspecialchars($item['name']) ?></h1>

    <?php if ($item['price']): ?>
      <div class="item-price-v">od <?= number_format($item['price'], 2) ?> zł</div>
    <?php endif; ?>

    <!-- DODAJ DO SZAFY -->
    <?php if (isset($_SESSION['user_id'])): ?>
      <?php if ($in_wardrobe): ?>
        <form method="POST" action="/wardrobe_remove.php">
          <?= csrf_field() ?>
          <input type="hidden" name="wardrobe_item_id" value="<?= $wardrobe_item_id ?>">
          <button type="submit" class="btn btn-outline item-wardrobe-btn">
            ✓ W szafie — usuń
          </button>
        </form>
      <?php else: ?>
        <form method="POST" action="/wardrobe_toggle.php">
          <?= csrf_field() ?>
          <input type="hidden" name="clothing_id" value="<?= $item['id'] ?>">
          <button type="submit" class="btn item-wardrobe-btn">
            + Dodaj do szafy
          </button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <!-- LINKI DO SKLEPÓW -->
    <div class="item-buy-section">
      <div class="item-section-label">/// Gdzie kupić</div>
      <?php if ($links): ?>
        <?php foreach ($links as $i => $link): ?>
          <div class="item-price-row <?= $i === 0 ? 'item-price-best' : '' ?>">
            <div>
              <?php if ($i === 0 && $link['price']): ?>
                <span class="price-badge">Najlepsza</span>
              <?php endif; ?>
              <div class="item-store-name"><?= htmlspecialchars($link['store_name']) ?></div>
            </div>
            <div class="item-store-price">
              <?= $link['price'] ? number_format($link['price'], 2) . ' zł' : 'Sprawdź' ?>
            </div>
            <a href="<?= htmlspecialchars($link['store_url']) ?>"
               target="_blank" rel="noopener"
               class="btn" style="font-size:11px; padding:6px 16px; white-space:nowrap;">
              Kup →
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:#444; font-size:12px; letter-spacing:1px; padding:12px 0;">
          Brak linków do sklepów.
        </p>
      <?php endif; ?>
    </div>

    <!-- OPIS (rozwijany) -->
    <?php if ($item['description']): ?>
      <div class="item-desc-v2-section">
        <button class="item-desc-v2-toggle" onclick="toggleDesc()">
          <span>Opis produktu</span>
          <span id="descArrow">▼</span>
        </button>
        <div class="item-desc-v2" id="itemDesc">
          <?= nl2br(htmlspecialchars($item['description'])) ?>
        </div>
      </div>
    <?php endif; ?>

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
</script>

<?php if ($rv_items): ?>
<div class="section-label">/// Ostatnio przeglądane</div>
<div class="clothing-grid clothing-grid--home" style="padding:0;">
  <?php foreach ($rv_items as $rv): ?>
    <a href="/item.php?id=<?= $rv['id'] ?>"
       class="clothing-card" style="text-decoration:none; color:inherit; display:block; border:1px solid var(--border);">
      <div class="clothing-card-img" style="aspect-ratio:1/1;">
        <?php if ($rv['image_url']): ?>
          <img src="<?= htmlspecialchars($rv['image_url']) ?>"
               alt="<?= htmlspecialchars($rv['name']) ?>"
               style="object-fit:contain;">
        <?php else: ?>
          <span class="no-img-text">NO IMG</span>
        <?php endif; ?>
      </div>
      <div class="clothing-card-brand"><?= htmlspecialchars($rv['brand']) ?></div>
      <div class="clothing-card-name" style="font-size:11px;"><?= htmlspecialchars($rv['name']) ?></div>
      <div class="clothing-card-price"><?= $rv['price'] ? number_format($rv['price'], 2) . ' zł' : '—' ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
