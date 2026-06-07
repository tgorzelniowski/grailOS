<?php
require_once __DIR__ . '/auth.php';

$clothing_id = (int)($_GET['id'] ?? $_POST['clothing_id'] ?? 0);
if (!$clothing_id) { header('Location: /admin/manage_clothing.php'); exit; }

$item = $pdo->prepare("SELECT * FROM clothing WHERE id = ?");
$item->execute([$clothing_id]);
$item = $item->fetch();
if (!$item) { header('Location: /admin/manage_clothing.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (isset($_POST['delete_link_id'])) {
        $pdo->prepare("DELETE FROM clothing_links WHERE id = ? AND clothing_id = ?")
            ->execute([$_POST['delete_link_id'], $clothing_id]);
        $success = 'Link usunięty.';

    } elseif (isset($_POST['add_link'])) {
        $store_name = trim($_POST['store_name'] ?? '');
        $store_url  = trim($_POST['store_url'] ?? '');
        $price      = trim($_POST['price'] ?? '');

        if (!$store_name || !$store_url) {
            $error = 'Nazwa sklepu i URL są wymagane.';
        } elseif (!filter_var($store_url, FILTER_VALIDATE_URL)) {
            $error = 'Nieprawidłowy URL.';
        } else {
            $pdo->prepare("INSERT INTO clothing_links (clothing_id, store_name, store_url, price) VALUES (?, ?, ?, ?)")
                ->execute([$clothing_id, $store_name, $store_url, $price ?: null]);
            $success = 'Link dodany!';
        }
    }
}

$links = $pdo->prepare("SELECT * FROM clothing_links WHERE clothing_id = ? ORDER BY price ASC");
$links->execute([$clothing_id]);
$links = $links->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Linki</div>
  <h1 class="page-title"><?= htmlspecialchars($item['brand']) ?><br><?= htmlspecialchars($item['name']) ?></h1>
</section>

<div class="admin-form-wrap" style="max-width:900px;">
  <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="form-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- LINKI -->
  <div class="section-label" style="padding:0 0 16px; border:none;">
    /// Linki do sklepów (<?= count($links) ?>)
  </div>

  <?php if ($links): ?>
    <table class="manage-table" style="margin-bottom:32px;">
      <thead>
        <tr>
          <th>Sklep</th>
          <th>Cena</th>
          <th>URL</th>
          <th>Akcje</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($links as $i => $link): ?>
          <tr>
            <td>
              <?= htmlspecialchars($link['store_name']) ?>
              <?php if ($i === 0 && $link['price']): ?>
                <span class="price-badge" style="margin-left:8px;">Najtaniej</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--red);">
              <?= $link['price'] ? number_format($link['price'],2).' zł' : '—' ?>
            </td>
            <td style="color:var(--gray); font-size:12px; max-width:200px; overflow:hidden; text-overflow:ellipsis;">
              <a href="<?= htmlspecialchars($link['store_url']) ?>" target="_blank"
                 style="color:var(--gray);"><?= htmlspecialchars($link['store_url']) ?></a>
            </td>
            <td>
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="clothing_id" value="<?= $clothing_id ?>">
                <input type="hidden" name="delete_link_id" value="<?= $link['id'] ?>">
                <button type="button" class="btn-delete"
                        onclick="grailConfirm('Usunąć link?', () => this.closest('form').submit())">Usuń</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p style="color:#444; letter-spacing:2px; text-transform:uppercase; font-size:12px; margin-bottom:32px;">
      Brak linków. Dodaj pierwszy sklep.
    </p>
  <?php endif; ?>

  <!-- DODAJ LINK -->
  <div style="padding-top:24px; border-top:1px solid var(--border);">
    <div class="section-label" style="padding:0 0 16px; border:none;">/// Dodaj link do sklepu</div>
    <form method="POST" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="clothing_id" value="<?= $clothing_id ?>">
      <input type="hidden" name="add_link" value="1">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nazwa sklepu *</label>
          <input type="text" name="store_name" class="form-input"
                 placeholder="np. Nike.com, Zalando, Grailed"
                 maxlength="100">
        </div>
        <div class="form-group">
          <label class="form-label">Cena (zł)</label>
          <input type="number" name="price" class="form-input"
                 step="0.01" min="0" placeholder="np. 499.99">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">URL do produktu *</label>
        <input type="url" name="store_url" class="form-input"
               placeholder="https://...">
      </div>
      <button type="submit" class="btn">Dodaj link →</button>
    </form>
  </div>

  <div style="margin-top:24px; display:flex; gap:12px;">
    <a href="/admin/manage_clothing.php" class="btn btn-outline">← Wróć</a>
    <a href="/admin/item_images.php?id=<?= $clothing_id ?>"
       class="btn btn-outline">Zarządzaj zdjęciami</a>
    <a href="/item.php?id=<?= $clothing_id ?>"
       class="btn" target="_blank">Podgląd →</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>