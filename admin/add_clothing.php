<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name      = trim($_POST['name'] ?? '');
    $brand     = trim($_POST['brand'] ?? '');
    $category  = trim($_POST['category'] ?? '');
    $price     = trim($_POST['price'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $image_url = null;

    if (!$name || !$brand || !$category) {
        $error = 'Nazwa, marka i kategoria są wymagane.';
    } else {
        if (!empty($_FILES['image']['name'])) {
            $result = upload_image($_FILES['image'], 'clothing');
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $image_url = $result['path'];
            }
        }

        if (!$error) {
            $pdo->prepare("
                INSERT INTO clothing (name, brand, category, price, image_url, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$name, $brand, $category, $price ?: null, $image_url, $desc ?: null]);
            $success = 'Produkt "' . $name . '" dodany!';
        }
    }
}
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Catalog</div>
  <h1 class="page-title">DODAJ PRODUKT</h1>
</section>

<div class="admin-form-wrap">
  <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="form-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Nazwa *</label>
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
        <label class="form-label">Kategoria *</label>
        <select name="category" class="form-input">
          <?php foreach (['hoodie','tshirt','pants','jacket','sneakers','cap','other'] as $cat): ?>
            <option value="<?= $cat ?>" <?= ($_POST['category'] ?? '') === $cat ? 'selected' : '' ?>>
              <?= ucfirst($cat) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Cena (zł)</label>
        <input type="number" name="price" class="form-input" step="0.01" min="0"
               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Zdjęcie (JPG, PNG, WebP — max 5MB)</label>
      <input type="file" name="image" class="form-input form-file"
             accept="image/jpeg,image/png,image/webp">
    </div>
    <div class="form-group">
      <label class="form-label">Opis</label>
      <textarea name="description" class="form-input form-textarea"
                rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn">Dodaj produkt →</button>
      <a href="/grailos/admin/" class="btn btn-outline">← Wróć</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>