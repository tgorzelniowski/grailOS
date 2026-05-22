<?php
require_once __DIR__ . '/auth.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = trim($_POST['name'] ?? '');
    $brand    = trim($_POST['brand'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price    = trim($_POST['price'] ?? '');
    $desc     = trim($_POST['description'] ?? '');

    if (!$name || !$brand || !$category) {
        $error = 'Nazwa, marka i kategoria są wymagane.';
    } else {
        // Upload zdjęć
        $uploaded = [];
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['name'] as $i => $fname) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $file = [
                    'name'     => $fname,
                    'type'     => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error'    => $_FILES['images']['error'][$i],
                    'size'     => $_FILES['images']['size'][$i],
                ];
                $result = upload_image($file, 'clothing');
                if (isset($result['error'])) { $error = $result['error']; break; }
                $uploaded[] = $result['path'];
            }
        }

        if (!$error) {
            $main_img = $uploaded[0] ?? null;

            $pdo->prepare("
                INSERT INTO clothing (name, brand, category, price, image_url, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$name, $brand, $category, $price ?: null, $main_img, $desc ?: null]);
            $cid = $pdo->lastInsertId();

            // Dodatkowe zdjęcia
            for ($i = 1; $i < count($uploaded); $i++) {
                $pdo->prepare("INSERT INTO clothing_images (clothing_id, image_url, sort_order) VALUES (?, ?, ?)")
                    ->execute([$cid, $uploaded[$i], $i]);
            }

            // Linki do sklepów
            $stores  = $_POST['stores']       ?? [];
            $urls    = $_POST['store_urls']   ?? [];
            $prices  = $_POST['store_prices'] ?? [];
            foreach ($stores as $i => $store) {
                if (empty($store) || empty($urls[$i])) continue;
                $pdo->prepare("INSERT INTO clothing_links (clothing_id, store_name, store_url, price) VALUES (?, ?, ?, ?)")
                    ->execute([$cid, $store, $urls[$i], $prices[$i] ?: null]);
            }

            $success = 'Produkt dodany! <a href="/grailos/item.php?id=' . $cid . '" style="color:var(--red)">Zobacz →</a>';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Catalog</div>
  <h1 class="page-title">DODAJ PRODUKT</h1>
</section>

<div class="admin-form-wrap" style="max-width:900px;">
  <?php if ($error): ?><div class="form-error"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="form-success"><?= $success ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>

    <!-- PODSTAWOWE INFO -->
    <div class="form-section-title">/// Podstawowe info</div>
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
        <label class="form-label">Cena bazowa (zł)</label>
        <input type="number" name="price" class="form-input" step="0.01" min="0"
               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Opis</label>
      <textarea name="description" class="form-input form-textarea"
                rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <!-- ZDJĘCIA -->
    <div class="form-section-title" style="margin-top:32px;">/// Zdjęcia</div>
    <div class="form-group">
      <label class="form-label">Wybierz zdjęcia (pierwsze = główne)</label>
      <input type="file" name="images[]" class="form-input form-file"
             accept="image/jpeg,image/png,image/webp" multiple
             onchange="previewFiles(this)">
    </div>
    <div class="img-preview-wrap" id="imgPreview"></div>

    <!-- LINKI DO SKLEPÓW -->
    <div class="form-section-title" style="margin-top:32px;">/// Linki do sklepów</div>
    <div id="links-wrap">
      <div class="link-row">
        <input type="text" name="stores[]" class="form-input"
               placeholder="Nazwa sklepu (np. Nike.com)">
        <input type="url" name="store_urls[]" class="form-input"
               placeholder="https://...">
        <input type="number" name="store_prices[]" class="form-input"
               step="0.01" min="0" placeholder="Cena zł">
        <button type="button" class="btn-delete" onclick="this.closest('.link-row').remove()">✕</button>
      </div>
    </div>
    <button type="button" class="btn btn-outline" style="margin-top:12px;"
            onclick="addLink()">+ Dodaj sklep</button>

    <div style="display:flex; gap:12px; margin-top:32px; padding-top:24px; border-top:1px solid var(--border);">
      <button type="submit" class="btn">Dodaj produkt →</button>
      <a href="/grailos/admin/manage_clothing.php" class="btn btn-outline">← Wróć</a>
    </div>
  </form>
</div>

<script>
function addLink() {
  const wrap = document.getElementById('links-wrap');
  const row  = document.createElement('div');
  row.className = 'link-row';
  row.innerHTML = `
    <input type="text"   name="stores[]"       class="form-input" placeholder="Nazwa sklepu">
    <input type="url"    name="store_urls[]"   class="form-input" placeholder="https://...">
    <input type="number" name="store_prices[]" class="form-input" step="0.01" placeholder="Cena zł">
    <button type="button" class="btn-delete" onclick="this.closest('.link-row').remove()">✕</button>
  `;
  wrap.appendChild(row);
}

function previewFiles(input) {
  const wrap = document.getElementById('imgPreview');
  wrap.innerHTML = '';
  Array.from(input.files).forEach((file, i) => {
    const reader = new FileReader();
    reader.onload = e => {
      const div = document.createElement('div');
      div.className = 'img-preview-item';
      div.innerHTML = `
        <img src="${e.target.result}" alt="">
        <span>${i === 0 ? '★ Główne' : `#${i+1}`}</span>
      `;
      wrap.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>