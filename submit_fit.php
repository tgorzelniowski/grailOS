<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $description = trim($_POST['description'] ?? '');

    if (empty($_FILES['images']['name'][0])) {
        $error = 'Przynajmniej jedno zdjęcie jest wymagane.';
    } else {
        $uploaded = [];
        foreach ($_FILES['images']['name'] as $i => $fname) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $file = [
                'name'     => $fname,
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i],
            ];
            $result = upload_image($file, 'fits');
            if (isset($result['error'])) { $error = $result['error']; break; }
            $uploaded[] = $result['path'];
        }

        if (!$error && $uploaded) {
            $pdo->prepare("INSERT INTO fits (user_id, image_url, description) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], $uploaded[0], $description ?: null]);
            $fit_id = $pdo->lastInsertId();

            for ($i = 1; $i < count($uploaded); $i++) {
                $pdo->prepare("INSERT INTO fit_images (fit_id, image_url, sort_order) VALUES (?, ?, ?)")
                    ->execute([$fit_id, $uploaded[$i], $i]);
            }

            header('Location: /grailos/fit.php?id=' . $fit_id);
            exit;
        }
    }
}

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Community</div>
  <h1 class="page-title">DODAJ<br>FIT</h1>
</section>

<div class="admin-form-wrap" style="max-width:600px;">
  <?php if ($error): ?>
    <div class="form-error" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>

    <div class="form-section-title">/// Zdjęcia (pierwsze = główne)</div>
    <div id="img-inputs">
      <div class="img-input-row" style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <input type="file" name="images[]" class="form-input form-file"
               accept="image/jpeg,image/png,image/webp" required
               onchange="previewSingle(this, 0)">
        <div id="fprev0"></div>
      </div>
    </div>
    <button type="button" class="btn btn-outline" style="margin-bottom:24px;"
            onclick="addImg()">+ Dodaj kolejne zdjęcie</button>

    <div class="form-group">
      <label class="form-label">Opis (opcjonalnie)</label>
      <textarea name="description" class="form-input form-textarea"
                rows="3" maxlength="500"
                placeholder="Opisz swój fit..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div style="display:flex; gap:12px; margin-top:8px;">
      <button type="submit" class="btn">Opublikuj fit →</button>
      <a href="/grailos/fits.php" class="btn btn-outline">← Wróć</a>
    </div>
  </form>
</div>

<script>
let imgN = 1;
function addImg() {
  const wrap = document.getElementById('img-inputs');
  const i = imgN++;
  const row = document.createElement('div');
  row.style.cssText = 'display:flex; align-items:center; gap:12px; margin-bottom:8px;';
  row.innerHTML = `
    <input type="file" name="images[]" class="form-input form-file"
           accept="image/jpeg,image/png,image/webp" onchange="previewSingle(this, ${i})">
    <div id="fprev${i}"></div>
    <button type="button" class="btn-delete" onclick="this.parentElement.remove()">✕</button>
  `;
  wrap.appendChild(row);
}
function previewSingle(input, idx) {
  if (!input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    const el = document.getElementById('fprev' + idx);
    if (el) el.innerHTML = `<img src="${e.target.result}" style="height:72px;width:72px;object-fit:contain;border:1px solid #222;background:#111;">`;
  };
  r.readAsDataURL(input.files[0]);
}
</script>

<?php require_once 'includes/footer.php'; ?>
