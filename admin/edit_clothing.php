<?php
require_once __DIR__ . '/auth.php';

$id = (int)($_GET['id'] ?? $_POST['clothing_id'] ?? 0);
if (!$id) { header('Location: /grailos/admin/manage_clothing.php'); exit; }

$item = $pdo->prepare("SELECT * FROM clothing WHERE id = ?");
$item->execute([$id]);
$item = $item->fetch();
if (!$item) { header('Location: /grailos/admin/manage_clothing.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // === AKTUALIZACJA PODSTAWOWYCH DANYCH ===
    if ($action === 'update_info') {
        $name     = trim($_POST['name'] ?? '');
        $brand    = trim($_POST['brand'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price    = trim($_POST['price'] ?? '');
        $desc     = trim($_POST['description'] ?? '');

        if (!$name || !$brand || !$category) {
            $error = 'Nazwa, marka i kategoria są wymagane.';
        } else {
            $pdo->prepare("
                UPDATE clothing SET name=?, brand=?, category=?, price=?, description=? WHERE id=?
            ")->execute([$name, $brand, $category, $price ?: null, $desc ?: null, $id]);
            $item = array_merge($item, compact('name','brand','category','price','desc'));
            $success = 'Dane zaktualizowane!';
        }
    }

    // === ZMIANA GŁÓWNEGO ZDJĘCIA ===
    if ($action === 'update_main_img' && !empty($_FILES['main_img']['name'])) {
        $result = upload_image($_FILES['main_img'], 'clothing');
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            // Usuń stare główne zdjęcie
            if ($item['image_url'] && str_starts_with($item['image_url'], '/grailos/assets/uploads/')) {
                $path = __DIR__ . str_replace('/grailos', '/../..', $item['image_url']);
                if (file_exists($path)) unlink($path);
            }
            $pdo->prepare("UPDATE clothing SET image_url=? WHERE id=?")->execute([$result['path'], $id]);
            $item['image_url'] = $result['path'];
            $success = 'Główne zdjęcie zmienione!';
        }
    }

    // === DODAJ DODATKOWE ZDJĘCIA ===
    if ($action === 'add_images' && !empty($_FILES['images']['name'][0])) {
        $max_order = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM clothing_images WHERE clothing_id=?");
        $max_order->execute([$id]);
        $order = (int)$max_order->fetchColumn();

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
            if (!isset($result['error'])) {
                $pdo->prepare("INSERT INTO clothing_images (clothing_id, image_url, sort_order) VALUES (?,?,?)")
                    ->execute([$id, $result['path'], $order++]);
            }
        }
        $success = 'Zdjęcia dodane!';
    }

    // === USUŃ ZDJĘCIE ===
    if ($action === 'delete_img' && isset($_POST['img_id'])) {
        $img = $pdo->prepare("SELECT image_url FROM clothing_images WHERE id=? AND clothing_id=?");
        $img->execute([$_POST['img_id'], $id]);
        $img = $img->fetch();
        if ($img) {
            $path = __DIR__ . str_replace('/grailos', '/../..', $img['image_url']);
            if (file_exists($path)) unlink($path);
            $pdo->prepare("DELETE FROM clothing_images WHERE id=?")->execute([$_POST['img_id']]);
        }
        $success = 'Zdjęcie usunięte.';
    }

    // === DODAJ LINK ===
    if ($action === 'add_link') {
        $store = trim($_POST['store_name'] ?? '');
        $url   = trim($_POST['store_url'] ?? '');
        $price = trim($_POST['store_price'] ?? '');
        if ($store && $url) {
            $pdo->prepare("INSERT INTO clothing_links (clothing_id, store_name, store_url, price) VALUES (?,?,?,?)")
                ->execute([$id, $store, $url, $price ?: null]);
            $success = 'Link dodany!';
        } else {
            $error = 'Nazwa sklepu i URL są wymagane.';
        }
    }

    // === USUŃ LINK ===
    if ($action === 'delete_link' && isset($_POST['link_id'])) {
        $pdo->prepare("DELETE FROM clothing_links WHERE id=? AND clothing_id=?")
            ->execute([$_POST['link_id'], $id]);
        $success = 'Link usunięty.';
    }

    // Odśwież dane
    $item = $pdo->prepare("SELECT * FROM clothing WHERE id=?");
    $item->execute([$id]);
    $item = $item->fetch();
}

$images = $pdo->prepare("SELECT * FROM clothing_images WHERE clothing_id=? ORDER BY sort_order ASC");
$images->execute([$id]);
$images = $images->fetchAll();

$links = $pdo->prepare("SELECT * FROM clothing_links WHERE clothing_id=? ORDER BY price ASC");
$links->execute([$id]);
$links = $links->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Edit</div>
  <h1 class="page-title">EDYTUJ<br><?= htmlspecialchars($item['brand']) ?></h1>
</section>

<div class="admin-form-wrap" style="max-width:900px;">

  <?php if ($error): ?><div class="form-error" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="form-success" style="margin-bottom:20px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- === SEKCJA 1: PODSTAWOWE INFO === -->
  <div class="edit-section">
    <div class="form-section-title">/// Podstawowe info</div>
    <form method="POST" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="clothing_id" value="<?= $id ?>">
      <input type="hidden" name="action" value="update_info">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nazwa *</label>
          <input type="text" name="name" class="form-input" maxlength="100"
                 value="<?= htmlspecialchars($item['name']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Marka *</label>
          <input type="text" name="brand" class="form-input" maxlength="100"
                 value="<?= htmlspecialchars($item['brand']) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Kategoria *</label>
          <select name="category" class="form-input">
            <?php foreach (['hoodie','tshirt','pants','jacket','sneakers','cap','other'] as $cat): ?>
              <option value="<?= $cat ?>" <?= $item['category'] === $cat ? 'selected' : '' ?>>
                <?= ucfirst($cat) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Cena bazowa (zł)</label>
          <input type="number" name="price" class="form-input" step="0.01" min="0"
                 value="<?= htmlspecialchars($item['price'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Opis</label>
        <textarea name="description" class="form-input form-textarea"
                  rows="4"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn">Zapisz zmiany →</button>
    </form>
  </div>

  <!-- === SEKCJA 2: GŁÓWNE ZDJĘCIE === -->
  <div class="edit-section">
    <div class="form-section-title">/// Główne zdjęcie</div>
    <?php if ($item['image_url']): ?>
      <div class="edit-main-img">
        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="">
      </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="admin-form" style="margin-top:16px;">
      <?= csrf_field() ?>
      <input type="hidden" name="clothing_id" value="<?= $id ?>">
      <input type="hidden" name="action" value="update_main_img">
      <div class="form-group">
        <label class="form-label">Nowe główne zdjęcie</label>
        <input type="file" name="main_img" class="form-input form-file"
               accept="image/jpeg,image/png,image/webp">
      </div>
      <button type="submit" class="btn">Zmień główne zdjęcie →</button>
    </form>
  </div>

  <!-- === SEKCJA 3: DODATKOWE ZDJĘCIA === -->
  <div class="edit-section">
    <div class="form-section-title">
      /// Dodatkowe zdjęcia (<?= count($images) ?>)
    </div>
    <?php if ($images): ?>
      <div class="img-manage-grid" style="margin-bottom:20px;">
        <?php foreach ($images as $img): ?>
          <div class="img-manage-item">
            <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="">
            <form method="POST" style="margin-top:6px;">
              <?= csrf_field() ?>
              <input type="hidden" name="clothing_id" value="<?= $id ?>">
              <input type="hidden" name="action" value="delete_img">
              <input type="hidden" name="img_id" value="<?= $img['id'] ?>">
              <button type="button" class="btn-delete" style="width:100%;"
                      onclick="grailConfirm('Usunąć?', () => this.closest('form').submit())">Usuń</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="clothing_id" value="<?= $id ?>">
      <input type="hidden" name="action" value="add_images">
      <div class="form-group">
        <label class="form-label">Dodaj zdjęcia (możesz wybrać wiele)</label>
        <input type="file" name="images[]" class="form-input form-file"
               accept="image/jpeg,image/png,image/webp" multiple>
      </div>
      <button type="submit" class="btn">Dodaj zdjęcia →</button>
    </form>
  </div>

  <!-- === SEKCJA 4: LINKI === -->
  <div class="edit-section">
    <div class="form-section-title">/// Linki do sklepów</div>
    <?php if ($links): ?>
      <table class="manage-table" style="margin-bottom:20px;">
        <thead>
          <tr><th>Sklep</th><th>Cena</th><th>URL</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($links as $i => $link): ?>
            <tr>
              <td>
                <?= htmlspecialchars($link['store_name']) ?>
                <?php if ($i === 0): ?><span class="price-badge" style="margin-left:8px;">Najtaniej</span><?php endif; ?>
              </td>
              <td style="color:var(--red);">
                <?= $link['price'] ? number_format($link['price'],2).' zł' : '—' ?>
              </td>
              <td style="color:var(--gray); font-size:12px; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <?= htmlspecialchars($link['store_url']) ?>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="clothing_id" value="<?= $id ?>">
                  <input type="hidden" name="action" value="delete_link">
                  <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                  <button type="button" class="btn-delete"
                          onclick="grailConfirm('Usunąć?', () => this.closest('form').submit())">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <form method="POST" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="clothing_id" value="<?= $id ?>">
      <input type="hidden" name="action" value="add_link">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nazwa sklepu *</label>
          <input type="text" name="store_name" class="form-input"
                 placeholder="np. Nike.com, Zalando">
        </div>
        <div class="form-group">
          <label class="form-label">Cena (zł)</label>
          <input type="number" name="store_price" class="form-input"
                 step="0.01" min="0" placeholder="499.99">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">URL *</label>
        <input type="url" name="store_url" class="form-input" placeholder="https://...">
      </div>
      <button type="submit" class="btn">Dodaj link →</button>
    </form>
  </div>

  <div style="display:flex; gap:12px; padding-top:24px; border-top:1px solid var(--border);">
    <a href="/grailos/admin/manage_clothing.php" class="btn btn-outline">← Katalog</a>
    <a href="/grailos/item.php?id=<?= $id ?>" class="btn" target="_blank">Podgląd →</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>