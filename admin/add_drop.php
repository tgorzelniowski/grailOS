<?php
require_once __DIR__ . '/auth.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name      = trim($_POST['name'] ?? '');
    $brand     = trim($_POST['brand'] ?? '');
    $drop_date = str_replace('T', ' ', trim($_POST['drop_date'] ?? ''));
    $link      = trim($_POST['link'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$name || !$brand || !$drop_date) {
        $error = 'Nazwa, marka i data są wymagane.';
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
                $result = upload_image($file, 'drops');
                if (isset($result['error'])) { $error = $result['error']; break; }
                $uploaded[] = $result['path'];
            }
        }

        if (!$error) {
            $main_img = $uploaded[0] ?? null;

            $pdo->prepare("INSERT INTO drops (name, brand, drop_date, image_url, link, description) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$name, $brand, $drop_date, $main_img, $link ?: null, $description ?: null]);
            $did = $pdo->lastInsertId();

            for ($i = 1; $i < count($uploaded); $i++) {
                $pdo->prepare("INSERT INTO drop_images (drop_id, image_url, sort_order) VALUES (?, ?, ?)")
                    ->execute([$did, $uploaded[$i], $i]);
            }

            $success = 'Drop "' . htmlspecialchars($name) . '" dodany! <a href="/grailos/drop.php?id=' . $did . '" style="color:var(--red)">Zobacz →</a>';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Drops</div>
  <h1 class="page-title">DODAJ DROP</h1>
</section>

<div class="admin-form-wrap" style="max-width:900px;">
  <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="form-success"><?= $success ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>

    <div class="form-section-title">/// Podstawowe info</div>
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
      <label class="form-label">Opis dropu</label>
      <textarea name="description" class="form-input form-textarea" rows="4"
                placeholder="Opisz drop, kolekcję, szczegóły..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <!-- ZDJĘCIA -->
    <div class="form-section-title" style="margin-top:32px;">/// Zdjęcia (pierwsze = główne)</div>
    <div id="img-inputs">
      <div class="img-input-row" style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <input type="file" name="images[]" class="form-input form-file"
               accept="image/jpeg,image/png,image/webp"
               onchange="previewSingleImg(this, 0)">
        <div id="prev0"></div>
      </div>
    </div>
    <button type="button" class="btn btn-outline" style="margin-top:4px;"
            onclick="addImgInput()">+ Dodaj kolejne zdjęcie</button>

    <div style="display:flex; gap:12px; margin-top:32px; padding-top:24px; border-top:1px solid var(--border);">
      <button type="submit" class="btn">Dodaj drop →</button>
      <a href="/grailos/admin/" class="btn btn-outline">← Wróć</a>
    </div>
  </form>
</div>

<script>
let imgCount = 1;

function addImgInput() {
  const wrap = document.getElementById('img-inputs');
  const i = imgCount++;
  const row = document.createElement('div');
  row.style.cssText = 'display:flex; align-items:center; gap:12px; margin-bottom:8px;';
  row.innerHTML = `
    <input type="file" name="images[]" class="form-input form-file"
           accept="image/jpeg,image/png,image/webp"
           onchange="previewSingleImg(this, ${i})">
    <div id="prev${i}"></div>
    <button type="button" class="btn-delete" onclick="this.parentElement.remove()">✕</button>
  `;
  wrap.appendChild(row);
}

function previewSingleImg(input, idx) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const el = document.getElementById('prev' + idx);
    if (el) el.innerHTML =
      `<img src="${e.target.result}" style="height:72px;width:72px;object-fit:contain;border:1px solid #222;background:#111;">`;
  };
  reader.readAsDataURL(input.files[0]);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
