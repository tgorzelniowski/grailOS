<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $pdo->prepare("DELETE FROM clothing WHERE id = ?")->execute([$_POST['delete_id']]);
}

$items = $pdo->query("SELECT * FROM clothing ORDER BY created_at DESC")->fetchAll();
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin</div>
  <h1 class="page-title">ZARZĄDZAJ<br>KATALOGIEM</h1>
</section>

<div style="padding:24px 40px; border-bottom:1px solid var(--border);">
  <a href="/grailos/admin/add_clothing.php" class="btn">+ Dodaj produkt</a>
</div>

<div class="manage-table-wrap">
  <table class="manage-table">
    <thead>
      <tr>
        <th>Zdjęcie</th>
        <th>Nazwa</th>
        <th>Marka</th>
        <th>Kategoria</th>
        <th>Cena</th>
        <th>Akcje</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($items): ?>
        <?php foreach ($items as $item): ?>
          <tr>
            <td>
              <?php if ($item['image_url']): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>"
                     style="width:56px;height:56px;object-fit:cover;border:1px solid var(--border);">
              <?php else: ?>
                <div style="width:56px;height:56px;background:var(--card);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:9px;color:#333;letter-spacing:1px;">NO IMG</div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= htmlspecialchars($item['brand']) ?></td>
            <td><?= htmlspecialchars($item['category']) ?></td>
            <td><?= $item['price'] ? number_format($item['price'], 2) . ' zł' : '—' ?></td>
            <td>
              <form method="POST"
                    onsubmit="return confirm('Na pewno usunąć <?= htmlspecialchars(addslashes($item['name'])) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                <button type="submit" class="btn-delete">Usuń</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" style="text-align:center;padding:60px;color:#444;letter-spacing:3px;text-transform:uppercase;font-size:12px;">
            Brak produktów
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>