<?php
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /'); exit; }

$stmt = $pdo->prepare("
    SELECT outfits.*, users.username, users.avatar_url, users.show_outfits
    FROM outfits
    LEFT JOIN users ON outfits.user_id = users.id
    WHERE outfits.id = ?
");
$stmt->execute([$id]);
$outfit = $stmt->fetch();
if (!$outfit) { header('Location: /'); exit; }

// Prywatność: jeśli właściciel ukrył outfity, widzą tylko on i admin
$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $outfit['user_id'];
$is_admin = !empty($_SESSION['is_admin']);
if ((int)($outfit['show_outfits'] ?? 1) === 0 && !$is_owner && !$is_admin) {
    header('Location: /profile.php?username=' . urlencode($outfit['username'])); exit;
}

// Itemy outfitu (z katalogu lub custom z szafy)
$items = $pdo->prepare("
    SELECT wardrobe_items.clothing_id,
           COALESCE(clothing.name, wardrobe_items.custom_name)       AS name,
           COALESCE(clothing.brand, wardrobe_items.custom_brand)     AS brand,
           COALESCE(clothing.image_url, wardrobe_items.custom_image_url) AS image_url,
           clothing.price
    FROM outfit_items
    LEFT JOIN wardrobe_items ON outfit_items.wardrobe_item_id = wardrobe_items.id
    LEFT JOIN clothing       ON wardrobe_items.clothing_id = clothing.id
    WHERE outfit_items.outfit_id = ?
");
$items->execute([$id]);
$items = $items->fetchAll();
?>

<section class="page-header">
  <div class="page-header-tag">/// Outfit</div>
  <h1 class="page-title"><?= htmlspecialchars($outfit['name']) ?></h1>
  <p class="page-sub">
    <a href="/profile.php?username=<?= urlencode($outfit['username']) ?>"
       style="color:var(--red); text-decoration:none;">@<?= htmlspecialchars($outfit['username']) ?></a>
    · <?= count($items) ?> itemów
  </p>
</section>

<?php if ($outfit['description']): ?>
  <div style="max-width:900px; margin:0 auto; padding:24px 40px 0; color:var(--gray); letter-spacing:0.5px; line-height:1.7;">
    <?= nl2br(htmlspecialchars($outfit['description'])) ?>
  </div>
<?php endif; ?>

<div class="clothing-grid clothing-grid--profile" style="padding-top:24px;">
  <?php if ($items): ?>
    <?php foreach ($items as $it):
      $clickable = !empty($it['clothing_id']);
    ?>
      <div class="clothing-card <?= $clickable ? 'profile-clickable' : '' ?>"
           <?= $clickable ? 'data-href="/item.php?id=' . (int)$it['clothing_id'] . '" style="cursor:pointer;"' : '' ?>>
        <div class="clothing-card-img">
          <?php if ($it['image_url']): ?>
            <img src="<?= htmlspecialchars($it['image_url']) ?>" alt="">
          <?php else: ?>NO IMG<?php endif; ?>
        </div>
        <div class="clothing-card-brand"><?= htmlspecialchars($it['brand'] ?? '—') ?></div>
        <div class="clothing-card-name"><?= htmlspecialchars($it['name'] ?? '—') ?></div>
        <div class="clothing-card-price"><?= $it['price'] ? number_format($it['price'],2).' zł' : '—' ?></div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:1/-1; padding:60px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Ten outfit nie ma itemów.
    </div>
  <?php endif; ?>
</div>

<div style="text-align:center; padding:32px 0 80px;">
  <a href="/profile.php?username=<?= urlencode($outfit['username']) ?>"
     class="btn btn-outline" style="font-size:11px; padding:8px 18px;">← Wróć do profilu</a>
</div>

<script>
document.querySelectorAll('.profile-clickable').forEach(card => {
  card.addEventListener('click', e => {
    if (!e.target.closest('a, button, form')) window.location.href = card.dataset.href;
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>
