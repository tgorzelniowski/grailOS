<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (empty($_FILES['image']['name'])) {
        $error = 'Zdjęcie jest wymagane.';
    } else {
        $result = upload_image($_FILES['image'], 'stories');
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $pdo->prepare("INSERT INTO stories (user_id, image_url) VALUES (?, ?)")
                ->execute([$_SESSION['user_id'], $result['path']]);
            header('Location: /grailos/');
            exit;
        }
    }
}

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Stories</div>
  <h1 class="page-title">DODAJ<br>STORKĘ</h1>
  <p class="page-sub">Znika po 24h. Widzą ją tylko Twoi obserwujący.</p>
</section>

<div class="admin-form-wrap" style="max-width:520px;">
  <?php if ($error): ?>
    <div class="form-error" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Zdjęcie * (JPG, PNG, WebP — max 5MB)</label>
      <input type="file" name="image" class="form-input form-file"
             accept="image/jpeg,image/png,image/webp" required
             onchange="previewStory(this)">
    </div>
    <div id="storyPreview" style="margin:8px 0 16px;"></div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn">Opublikuj storkę →</button>
      <a href="/grailos/" class="btn btn-outline">← Wróć</a>
    </div>
  </form>
</div>

<script>
function previewStory(input) {
  if (!input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    document.getElementById('storyPreview').innerHTML =
      `<img src="${e.target.result}" style="max-height:280px;max-width:100%;object-fit:contain;border:1px solid #222;background:#111;">`;
  };
  r.readAsDataURL(input.files[0]);
}
</script>

<?php require_once 'includes/footer.php'; ?>
