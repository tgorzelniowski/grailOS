<?php
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $description = trim($_POST['description'] ?? '');

    if (empty($_FILES['image']['name'])) {
        $error = 'Zdjęcie jest wymagane.';
    } else {
        $result = upload_image($_FILES['image'], 'fits');
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO fits (user_id, image_url, description) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $result['path'], $description ?: null]);
            header('Location: /grailos/fits.php');
            exit;
        }
    }
}
?>

<section class="page-header">
  <div class="page-header-tag">/// Community</div>
  <h1 class="page-title">DODAJ<br>FIT</h1>
</section>

<div class="admin-form-wrap">
  <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Zdjęcie * (JPG, PNG, WebP — max 5MB)</label>
      <input type="file" name="image" class="form-input form-file"
             accept="image/jpeg,image/png,image/webp" required>
    </div>
    <div class="form-group">
      <label class="form-label">Opis (opcjonalnie)</label>
      <textarea name="description" class="form-input form-textarea"
                rows="3" maxlength="500"
                placeholder="Opisz swój fit, wymień marki..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>
    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn">Opublikuj fit →</button>
      <a href="/grailos/fits.php" class="btn btn-outline">← Wróć</a>
    </div>
  </form>
</div>

<?php require_once 'includes/footer.php'; ?>