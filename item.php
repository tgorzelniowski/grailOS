<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /grailos/catalog.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM clothing WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { header('Location: /grailos/catalog.php'); exit; }

// Pobierz wszystkie zdjęcia
$imgs_stmt = $pdo->prepare("SELECT * FROM clothing_images WHERE clothing_id = ? ORDER BY sort_order ASC");
$imgs_stmt->execute([$id]);
$extra_imgs = $imgs_stmt->fetchAll();

// Buduj pełną listę zdjęć (główne + dodatkowe)
$all_imgs = [];
if ($item['image_url']) $all_imgs[] = $item['image_url'];
foreach ($extra_imgs as $img) $all_imgs[] = $img['image_url'];

// Pobierz linki do sklepów (posortowane po cenie)
$links_stmt = $pdo->prepare("SELECT * FROM clothing_links WHERE clothing_id = ? ORDER BY price ASC");
$links_stmt->execute([$id]);
$links = $links_stmt->fetchAll();

// Czy w szafie
$in_wardrobe = false;
$wardrobe_item_id = null;
if (isset($_SESSION['user_id'])) {
    $w = $pdo->prepare("SELECT id FROM wardrobe_items WHERE user_id = ? AND clothing_id = ?");
    $w->execute([$_SESSION['user_id'], $id]);
    $wrow = $w->fetch();
    if ($wrow) { $in_wardrobe = true; $wardrobe_item_id = $wrow['id']; }
}

require_once 'includes/header.php';
?>

<div class="item-page">

  <!-- LEWA: SLIDER -->
  <div class="item-slider-wrap">
    <div class="item-slider" id="itemSlider">
      <?php if ($all_imgs): ?>
        <?php foreach ($all_imgs as $i => $img): ?>
          <div class="slide <?= $i === 0 ? 'active' : '' ?>">
            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
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

  <!-- PRAWA: INFO -->
  <div class="item-info-wrap">
    <div class="item-brand"><?= htmlspecialchars($item['brand']) ?></div>
    <h1 class="item-name"><?= htmlspecialchars($item['name']) ?></h1>

    <div class="item-meta">
      <span class="item-category"><?= htmlspecialchars($item['category']) ?></span>
      <?php if ($item['price']): ?>
        <span class="item-price-base">od <?= number_format($item['price'], 2) ?> zł</span>
      <?php endif; ?>
    </div>

    <?php if ($item['description']): ?>
      <div class="item-desc-wrap">
        <p class="item-desc" id="itemDesc">
          <?= nl2br(htmlspecialchars($item['description'])) ?>
        </p>
        <?php if (strlen($item['description']) > 200): ?>
          <button class="desc-toggle" id="descToggle" onclick="toggleDesc()">
            Pokaż więcej ▼
          </button>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- WARDROBE BTN -->
    <?php if (isset($_SESSION['user_id'])): ?>
      <?php if ($in_wardrobe): ?>
        <form method="POST" action="/grailos/wardrobe_remove.php">
          <?= csrf_field() ?>
          <input type="hidden" name="wardrobe_item_id" value="<?= $wardrobe_item_id ?>">
          <button type="submit" class="btn btn-outline" style="margin-top:16px;">
            ✓ W szafie — usuń
          </button>
        </form>
      <?php else: ?>
        <form method="POST" action="/grailos/wardrobe_toggle.php">
          <?= csrf_field() ?>
          <input type="hidden" name="clothing_id" value="<?= $item['id'] ?>">
          <button type="submit" class="btn" style="margin-top:16px;">
            + Dodaj do szafy
          </button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <!-- PORÓWNYWARKA CEN -->
    <?php if ($links): ?>
      <div class="price-compare">
        <div class="price-compare-title">/// Gdzie kupić</div>
        <?php
          $min_price = min(array_filter(array_column($links, 'price')));
        ?>
        <?php foreach ($links as $i => $link): ?>
          <div class="price-row <?= $i === 0 ? 'price-best' : '' ?>">
            <div class="price-store">
              <?php if ($i === 0 && $link['price']): ?>
                <span class="price-badge">Najlepsza cena</span>
              <?php endif; ?>
              <span class="price-store-name"><?= htmlspecialchars($link['store_name']) ?></span>
            </div>
            <div class="price-amount">
              <?= $link['price'] ? number_format($link['price'], 2) . ' zł' : 'Sprawdź cenę' ?>
            </div>
            <a href="<?= htmlspecialchars($link['store_url']) ?>"
               target="_blank" rel="noopener"
               class="btn price-btn">
              Kup →
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="price-compare">
        <div class="price-compare-title">/// Gdzie kupić</div>
        <p style="color:var(--gray); font-size:13px; letter-spacing:1px; padding:16px 0;">
          Brak linków do sklepów.
        </p>
      </div>
    <?php endif; ?>

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
  desc.classList.toggle('expanded');
  btn.textContent = desc.classList.contains('expanded') ? 'Pokaż mniej ▲' : 'Pokaż więcej ▼';
}
</script>

<?php require_once 'includes/footer.php'; ?>