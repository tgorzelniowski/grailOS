<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM fits WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$fit = $stmt->fetch();
if (!$fit) { header('Location: /grailos/fits.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $desc = trim($_POST['description'] ?? '');

    // Nowe zdjęcie główne (opcjonalne)
    if (!empty($_FILES['image']['name'])) {
        $result = upload_image($_FILES['image'], 'fits');
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $pdo->prepare("UPDATE fits SET image_url = ? WHERE id = ? AND user_id = ?")
                ->execute([$result['path'], $id, $_SESSION['user_id']]);
        }
    }

    // Dodatkowe zdjęcia
    if (!$error && !empty($_FILES['extra_images']['name'][0])) {
        foreach ($_FILES['extra_images']['name'] as $i => $fname) {
            if ($_FILES['extra_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $file = [
                'name'     => $fname,
                'type'     => $_FILES['extra_images']['type'][$i],
                'tmp_name' => $_FILES['extra_images']['tmp_name'][$i],
                'error'    => $_FILES['extra_images']['error'][$i],
                'size'     => $_FILES['extra_images']['size'][$i],
            ];
            $result = upload_image($file, 'fits');
            if (!isset($result['error'])) {
                $order = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM fit_images WHERE fit_id=$id")->fetchColumn();
                $pdo->prepare("INSERT INTO fit_images (fit_id, image_url, sort_order) VALUES (?, ?, ?)")
                    ->execute([$id, $result['path'], $order]);
            }
        }
    }

    // Usuń dodatkowe zdjęcie
    if (!$error && isset($_POST['delete_img_id'])) {
        $pdo->prepare("DELETE FROM fit_images WHERE id = ? AND fit_id = ?")
            ->execute([(int)$_POST['delete_img_id'], $id]);
        header("Location: /grailos/edit_fit.php?id=$id");
        exit;
    }

    if (!$error) {
        $pdo->prepare("UPDATE fits SET description = ? WHERE id = ? AND user_id = ?")
            ->execute([$desc ?: null, $id, $_SESSION['user_id']]);
        header("Location: /grailos/fit.php?id=$id");
        exit;
    }
}

// Pobierz dodatkowe zdjęcia
$extra_imgs = $pdo->prepare("SELECT * FROM fit_images WHERE fit_id = ? ORDER BY sort_order ASC");
$extra_imgs->execute([$id]);
$extra_imgs = $extra_imgs->fetchAll();

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Community</div>
  <h1 class="page-title">EDYTUJ<br>FIT</h1>
</section>

<div class="admin-form-wrap" style="max-width:700px;">
  <?php if ($error): ?>
    <div class="form-error" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>

    <!-- PODGLĄD GŁÓWNEGO ZDJĘCIA -->
    <div class="form-section-title">/// Główne zdjęcie</div>
    <div style="display:flex; align-items:flex-start; gap:20px; margin-bottom:16px;">
      <img src="<?= htmlspecialchars($fit['image_url']) ?>"
           style="width:120px; height:120px; object-fit:cover; border:1px solid var(--border);">
      <div class="form-group" style="flex:1;">
        <label class="form-label">Zmień główne zdjęcie (opcjonalnie)</label>
        <input type="file" name="image" class="form-input form-file"
               accept="image/jpeg,image/png,image/webp">
      </div>
    </div>

    <!-- DODATKOWE ZDJĘCIA -->
    <div class="form-section-title">/// Dodatkowe zdjęcia</div>
    <?php if ($extra_imgs): ?>
      <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
        <?php foreach ($extra_imgs as $img): ?>
          <div style="position:relative;">
            <img src="<?= htmlspecialchars($img['image_url']) ?>"
                 style="width:80px;height:80px;object-fit:cover;border:1px solid var(--border);">
            <form method="POST" style="position:absolute;top:2px;right:2px;">
              <?= csrf_field() ?>
              <input type="hidden" name="delete_img_id" value="<?= $img['id'] ?>">
              <button type="button" class="inline-delete-btn"
                      style="opacity:1;width:22px;height:22px;font-size:11px;"
                      onclick="grailConfirm('Usunąć zdjęcie?', () => this.closest('form').submit())">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div id="extra-inputs">
      <div class="img-input-row" style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <input type="file" name="extra_images[]" class="form-input form-file"
               accept="image/jpeg,image/png,image/webp"
               onchange="previewExtra(this, 0)">
        <div id="xprev0"></div>
      </div>
    </div>
    <button type="button" class="btn btn-outline" style="margin-bottom:24px;"
            onclick="addExtraInput()">+ Dodaj kolejne zdjęcie</button>

    <!-- OPIS -->
    <div class="form-section-title">/// Opis</div>
    <div class="form-group">
      <textarea name="description" class="form-input form-textarea"
                rows="4" maxlength="500"
                placeholder="Opisz swój fit..."><?= htmlspecialchars($fit['description'] ?? '') ?></textarea>
    </div>

    <div style="display:flex; gap:12px; margin-top:16px;">
      <button type="submit" class="btn">Zapisz →</button>
      <a href="/grailos/fit.php?id=<?= $fit['id'] ?>" class="btn btn-outline">← Wróć</a>
    </div>
  </form>
</div>

<script>
let xCount = 1;
function addExtraInput() {
  const wrap = document.getElementById('extra-inputs');
  const i = xCount++;
  const row = document.createElement('div');
  row.style.cssText = 'display:flex; align-items:center; gap:12px; margin-bottom:8px;';
  row.innerHTML = `
    <input type="file" name="extra_images[]" class="form-input form-file"
           accept="image/jpeg,image/png,image/webp" onchange="previewExtra(this, ${i})">
    <div id="xprev${i}"></div>
    <button type="button" class="btn-delete" onclick="this.parentElement.remove()">✕</button>
  `;
  wrap.appendChild(row);
}
function previewExtra(input, idx) {
  if (!input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    const el = document.getElementById('xprev' + idx);
    if (el) el.innerHTML = `<img src="${e.target.result}" style="height:64px;width:64px;object-fit:contain;border:1px solid #222;background:#111;">`;
  };
  r.readAsDataURL(input.files[0]);
}
</script>

<?php require_once 'includes/footer.php'; ?>
