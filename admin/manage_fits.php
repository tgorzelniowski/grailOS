<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    // Usuń plik ze zdjęciem jeśli jest lokalny
    $stmt = $pdo->prepare("SELECT image_url FROM fits WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    $fit = $stmt->fetch();
    if ($fit && str_starts_with($fit['image_url'], '/grailos/assets/uploads/')) {
        $path = __DIR__ . '/../' . str_replace('/grailos/', '', $fit['image_url']);
        if (file_exists($path)) unlink($path);
    }
    $pdo->prepare("DELETE FROM fits WHERE id = ?")->execute([$_POST['delete_id']]);
}

$fits = $pdo->query("
    SELECT fits.*, users.username,
           COUNT(DISTINCT fit_votes.id)    AS votes,
           COUNT(DISTINCT fit_comments.id) AS comments
    FROM fits
    LEFT JOIN users        ON fits.user_id = users.id
    LEFT JOIN fit_votes    ON fits.id = fit_votes.fit_id
    LEFT JOIN fit_comments ON fits.id = fit_comments.fit_id
    GROUP BY fits.id
    ORDER BY fits.created_at DESC
")->fetchAll();
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin</div>
  <h1 class="page-title">ZARZĄDZAJ<br>FITAMI</h1>
</section>

<div class="manage-table-wrap">
  <table class="manage-table">
    <thead>
      <tr>
        <th>Zdjęcie</th>
        <th>Użytkownik</th>
        <th>Opis</th>
        <th>Lajki</th>
        <th>Komentarze</th>
        <th>Data</th>
        <th>Akcje</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($fits): ?>
        <?php foreach ($fits as $fit): ?>
          <tr>
            <td>
              <img src="<?= htmlspecialchars($fit['image_url']) ?>"
                   style="width:56px;height:56px;object-fit:cover;border:1px solid var(--border);">
            </td>
            <td>@<?= htmlspecialchars($fit['username']) ?></td>
            <td style="max-width:200px; color:var(--gray); font-size:12px;">
              <?= htmlspecialchars(mb_substr($fit['description'] ?? '—', 0, 60)) ?>
            </td>
            <td>♥ <?= $fit['votes'] ?></td>
            <td>💬 <?= $fit['comments'] ?></td>
            <td style="color:var(--gray); font-size:12px;"><?= date('d M Y', strtotime($fit['created_at'])) ?></td>
            <td>
              <form method="POST"
                    onsubmit="return confirm('Na pewno usunac ten fit?')">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= $fit['id'] ?>">
                <button type="submit" class="btn-delete">Usuń</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" style="padding:60px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
            Brak fitów
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>