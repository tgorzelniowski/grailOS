<?php
require_once __DIR__ . '/auth.php';

$clothing_id = (int)($_GET['id'] ?? $_POST['clothing_id'] ?? 0);
if (!$clothing_id) { header('Location: /admin/manage_clothing.php'); exit; }

$item = $pdo->prepare("SELECT * FROM clothing WHERE id = ?");
$item->execute([$clothing_id]);
$item = $item->fetch();
if (!$item) { header('Location: /admin/manage_clothing.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['delete_img_id'])) {
        $img = $pdo->prepare("SELECT image_url FROM clothing_images WHERE id = ? AND clothing_id = ?");
        $img->execute([$_POST['delete_img_id'], $clothing_id]);
        $img = $img->fetch();
        if ($img) {
            $path = __DIR__ . str_replace('/grailos', '/../..', $img['image_url']);
            if (file_exists($path)) unlink($path);
            $pdo->prepare("DELETE FROM clothing_images WHERE id = ?")->execute([$_POST['delete_img_id']]);
            $success = 'Zdjęcie usunięte.';
        }
    } elseif (!empty($_FILES['image']['name'])) {
        $result = upload_image($_FILES['image'], 'clothing');
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $max_order = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM clothing_images WHERE clothing_id = ?");
            $max_order->execute([$clothing_id]);
            $pdo->prepare("INSERT INTO clothing_images (clothing_id, image_url, sort_order) VALUES (?, ?, ?)")
                ->execute([$clothing_id, $result['path'], $max_order->fetchColumn()]);
            $success = 'Zdjęcie dodane!';
        }
    }
}

$images = $pdo->prepare("SELECT * FROM clothing_images WHERE clothing_id = ? ORDER BY sort_order ASC");
$images->execute([$clothing_id]);
$images = $images->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Zdjęcia</div>
  <h1 class="page-title"><?= htmlspecialchars($item['brand']) ?><br><?= htmlspecialchars($item['name']) ?></h1>
</section>

<div class="admin-form-wrap">
  <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="form-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- AKTUALNE ZDJĘCIA -->
  <div class="section-label" style="padding:0 0 16px; border:none;">
    /// Aktualne zdjęcia (<?= count($images) ?>)
  </div>

  <?php if ($images): ?>
    <div class="img-manage-grid">
      <?php foreach ($images as $img): ?>
        <div class="img-manage-item">
          <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="">
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="clothing_id" value="<?= $clothing_id ?>">
            <input type="hidden" name="delete_img_id" value="<?= $img['id'] ?>">
            <button type="button" class="btn-delete" style="width:100%; margin-top:8px;"
                    onclick="grailConfirm('Usunąć zdjęcie?', () => this.closest('form').submit())">Usuń</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- DODAJ NOWE -->
  <div style="margin-top:32px; padding-top:24px; border-top:1px solid var(--border);">
    <div class="section-label" style="padding:0 0 16px; border:none;">/// Dodaj zdjęcie</div>
    <form method="POST" enctype="multipart/form-data" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="clothing_id" value="<?= $clothing_id ?>">
      <div class="form-group">
        <label class="form-label">Plik (JPG, PNG, WebP — max 5MB)</label>
        <input type="file" name="image" class="form-input form-file"
               accept="image/jpeg,image/png,image/webp">
      </div>
      <button type="submit" class="btn">Dodaj zdjęcie →</button>
    </form>
  </div>

  <div style="margin-top:24px;">
    <a href="/admin/manage_clothing.php" class="btn btn-outline">← Wróć</a>
    <a href="/admin/item_links.php?id=<?= $clothing_id ?>"
       class="btn" style="margin-left:12px;">Zarządzaj linkami →</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>