<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name      = trim($_POST['name'] ?? '');
    $brand     = trim($_POST['brand'] ?? '');
    $drop_date = str_replace('T', ' ', trim($_POST['drop_date'] ?? ''));
    $link      = trim($_POST['link'] ?? '');
    $image_url = null;

    if (!$name || !$brand || !$drop_date) {
        $error = 'Nazwa, marka i data są wymagane.';
    } else {
        if (!empty($_FILES['image']['name'])) {
            $result = upload_image($_FILES['image'], 'drops');
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $image_url = $result['path'];
            }
        }

        if (!$error) {
            $pdo->prepare("
                INSERT INTO drops (name, brand, drop_date, image_url, link)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$name, $brand, $drop_date, $image_url, $link ?: null]);
            $success = 'Drop "' . $name . '" dodany!';
        }
    }
}
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Drops</div>
  <h1 class="page-title">DODAJ DROP</h1>
</section>

<div class="admin-form-wrap">
  <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="form-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Nazwa dropu *</label>
        <input type="text" name="name" class="form-input" maxlength="100"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Marka *</label>
        <input type="text" name="brand" class="form-input" maxlength="100"
               value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Data i godzina *</label>
        <input type="datetime-local" name="drop_date" class="form-input"
               value="<?= htmlspecialchars($_POST['drop_date'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Link do zakupu</label>
        <input type="url" name="link" class="form-input" placeholder="https://..."
               value="<?= htmlspecialchars($_POST['link'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Zdjęcie (JPG, PNG, WebP — max 5MB)</label>
      <input type="file" name="image" class="form-input form-file"
             accept="image/jpeg,image/png,image/webp">
    </div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn">Dodaj drop →</button>
      <a href="/grailos/admin/" class="btn btn-outline">← Wróć</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>