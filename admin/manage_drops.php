<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $stmt = $pdo->prepare("SELECT image_url FROM drops WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    $drop = $stmt->fetch();
    if ($drop && $drop['image_url'] && str_starts_with($drop['image_url'], '/grailos/assets/uploads/')) {
        $path = __DIR__ . str_replace('/grailos', '', $drop['image_url']);
        if (file_exists($path)) unlink($path);
    }
    $pdo->prepare("DELETE FROM drops WHERE id = ?")->execute([$_POST['delete_id']]);
}

$drops = $pdo->query("SELECT * FROM drops ORDER BY drop_date DESC")->fetchAll();
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Drops</div>
  <h1 class="page-title">ZARZĄDZAJ<br>DROPAMI</h1>
</section>

<div style="padding:24px 40px; border-bottom:1px solid var(--border);">
  <a href="/grailos/admin/add_drop.php" class="btn">+ Dodaj drop</a>
</div>

<div class="manage-table-wrap">
  <table class="manage-table">
    <thead>
      <tr>
        <th>Zdjęcie</th>
        <th>Nazwa</th>
        <th>Marka</th>
        <th>Data dropu</th>
        <th>Link</th>
        <th>Akcje</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($drops): ?>
        <?php foreach ($drops as $drop): ?>
          <tr>
            <td>
              <?php if ($drop['image_url']): ?>
                <img src="<?= htmlspecialchars($drop['image_url']) ?>"
                     style="width:56px;height:56px;object-fit:cover;border:1px solid var(--border);">
              <?php else: ?>
                <div style="width:56px;height:56px;background:var(--card);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:9px;color:#333;">NO IMG</div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($drop['name']) ?></td>
            <td><?= htmlspecialchars($drop['brand']) ?></td>
            <td style="color:var(--red); font-size:12px; letter-spacing:1px;">
              <?= date('d M Y, H:i', strtotime($drop['drop_date'])) ?>
            </td>
            <td>
              <?php if ($drop['link']): ?>
                <a href="<?= htmlspecialchars($drop['link']) ?>" target="_blank"
                   style="color:var(--gray); font-size:11px; letter-spacing:2px; text-transform:uppercase;">
                  Link →
                </a>
              <?php else: ?>
                <span style="color:#444;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <form method="POST"
                    onsubmit="return confirm('Usunac drop <?= htmlspecialchars(addslashes($drop['name'])) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= $drop['id'] ?>">
                <button type="submit" class="btn-delete">Usuń</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" style="padding:60px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
            Brak dropów
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>