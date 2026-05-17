<?php
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}

$error   = '';
$success = '';

// Pobierz szafę usera
$wardrobe = $pdo->prepare("
    SELECT wardrobe_items.id, clothing.name, clothing.brand,
           clothing.image_url, clothing.category
    FROM wardrobe_items
    LEFT JOIN clothing ON wardrobe_items.clothing_id = clothing.id
    WHERE wardrobe_items.user_id = ?
    ORDER BY clothing.category, clothing.brand
");
$wardrobe->execute([$_SESSION['user_id']]);
$wardrobe = $wardrobe->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $items       = $_POST['items'] ?? [];

    if (!$name) {
        $error = 'Nazwa outfitu jest wymagana.';
    } elseif (empty($items)) {
        $error = 'Wybierz przynajmniej jeden item z szafy.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO outfits (user_id, name, description) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $name, $description ?: null]);
        $outfit_id = $pdo->lastInsertId();

        $item_stmt = $pdo->prepare("INSERT INTO outfit_items (outfit_id, wardrobe_item_id) VALUES (?, ?)");
        foreach ($items as $item_id) {
            $item_stmt->execute([$outfit_id, (int)$item_id]);
        }

        header('Location: /grailos/profile.php?username=' . urlencode($_SESSION['username']));
        exit;
    }
}
?>

<section class="page-header">
  <div class="page-header-tag">/// Wardrobe</div>
  <h1 class="page-title">STWÓRZ<br>OUTFIT</h1>
  <p class="page-sub">Wybierz itemy z szafy i zapisz jako outfit.</p>
</section>

<div class="admin-form-wrap" style="max-width:900px;">
  <?php if ($error): ?>
    <div class="form-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (empty($wardrobe)): ?>
    <div class="form-error">
      Twoja szafa jest pusta.
      <a href="/grailos/catalog.php" style="color:var(--red)">Dodaj itemy z katalogu →</a>
    </div>
  <?php else: ?>
    <form method="POST" class="admin-form">
      <?= csrf_field() ?>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nazwa outfitu *</label>
          <input type="text" name="name" class="form-input" maxlength="100"
                 placeholder="np. Summer Fit, Palace Day..."
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Opis (opcjonalnie)</label>
          <input type="text" name="description" class="form-input" maxlength="200"
                 value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Wybierz itemy z szafy *</label>
        <div class="outfit-picker">
          <?php foreach ($wardrobe as $item): ?>
            <label class="outfit-item <?= in_array($item['id'], $_POST['items'] ?? []) ? 'selected' : '' ?>">
              <input type="checkbox" name="items[]" value="<?= $item['id'] ?>"
                     <?= in_array($item['id'], $_POST['items'] ?? []) ? 'checked' : '' ?>
                     onchange="this.closest('.outfit-item').classList.toggle('selected', this.checked)">
              <div class="outfit-item-img">
                <?php if ($item['image_url']): ?>
                  <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="">
                <?php else: ?>
                  <span><?= strtoupper(substr($item['category'], 0, 2)) ?></span>
                <?php endif; ?>
              </div>
              <div class="outfit-item-info">
                <div class="outfit-item-brand"><?= htmlspecialchars($item['brand']) ?></div>
                <div class="outfit-item-name"><?= htmlspecialchars($item['name']) ?></div>
              </div>
              <div class="outfit-check">✓</div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="display:flex; gap:12px;">
        <button type="submit" class="btn">Zapisz outfit →</button>
        <a href="/grailos/profile.php?username=<?= urlencode($_SESSION['username']) ?>"
           class="btn btn-outline">← Wróć</a>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>