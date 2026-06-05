<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $stmt = $pdo->prepare("SELECT image_url FROM fits WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    $fit = $stmt->fetch();
    if ($fit && str_starts_with($fit['image_url'], '/grailos/assets/uploads/')) {
        $path = __DIR__ . '/../' . str_replace('/grailos/', '', $fit['image_url']);
        if (file_exists($path)) unlink($path);
    }
    $pdo->prepare("DELETE FROM fits WHERE id = ?")->execute([$_POST['delete_id']]);
}

$search_user = trim($_GET['user'] ?? '');
$search_kw   = trim($_GET['kw']   ?? '');
$where_parts = [];
$params      = [];

if ($search_user !== '') {
    $where_parts[] = "users.username LIKE ?";
    $params[]      = '%' . $search_user . '%';
}
if ($search_kw !== '') {
    $where_parts[] = "fits.description LIKE ?";
    $params[]      = '%' . $search_kw . '%';
}
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$stmt = $pdo->prepare("
    SELECT fits.*, users.username,
           COUNT(DISTINCT fit_votes.id) AS votes,
           (SELECT COUNT(*) FROM fit_comments WHERE fit_comments.fit_id = fits.id)
           + (SELECT COUNT(*) FROM fit_comment_replies fcr
              INNER JOIN fit_comments fc ON fcr.comment_id = fc.id
              WHERE fc.fit_id = fits.id) AS comments
    FROM fits
    LEFT JOIN users     ON fits.user_id = users.id
    LEFT JOIN fit_votes ON fits.id = fit_votes.fit_id
    $where
    GROUP BY fits.id
    ORDER BY fits.created_at DESC
");
$stmt->execute($params);
$fits = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin</div>
  <h1 class="page-title">ZARZĄDZAJ<br>FITAMI</h1>
</section>

<!-- WYSZUKIWARKA -->
<div style="padding:20px 40px; border-bottom:1px solid var(--border);">
  <form method="GET" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <input type="text" name="user" placeholder="@użytkownik"
           value="<?= htmlspecialchars($search_user) ?>"
           class="form-input" style="max-width:200px; padding:8px 0;">
    <input type="text" name="kw" placeholder="Słowo kluczowe w opisie..."
           value="<?= htmlspecialchars($search_kw) ?>"
           class="form-input" style="max-width:280px; padding:8px 0;">
    <button type="submit" class="btn" style="font-size:11px; padding:8px 18px;">Szukaj</button>
    <?php if ($search_user !== '' || $search_kw !== ''): ?>
      <a href="/grailos/admin/manage_fits.php" class="btn btn-outline"
         style="font-size:11px; padding:8px 18px;">Resetuj ×</a>
      <span style="font-size:11px; letter-spacing:2px; color:var(--gray); text-transform:uppercase;">
        <?= count($fits) ?> wyników
      </span>
    <?php endif; ?>
  </form>
</div>

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
              <a href="/grailos/fit.php?id=<?= $fit['id'] ?>" target="_blank">
                <img src="<?= htmlspecialchars($fit['image_url']) ?>"
                     style="width:56px;height:56px;object-fit:cover;border:1px solid var(--border);">
              </a>
            </td>
            <td><a href="/grailos/profile.php?username=<?= urlencode($fit['username']) ?>"
                   style="color:var(--red); text-decoration:none;">@<?= htmlspecialchars($fit['username']) ?></a></td>
            <td style="max-width:200px; color:var(--gray); font-size:12px;">
              <?= htmlspecialchars(mb_substr($fit['description'] ?? '—', 0, 60)) ?>
            </td>
            <td>♥ <?= $fit['votes'] ?></td>
            <td>💬 <?= $fit['comments'] ?></td>
            <td style="color:var(--gray); font-size:12px;"><?= date('d M Y', strtotime($fit['created_at'])) ?></td>
            <td style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
              <a href="/grailos/fit.php?id=<?= $fit['id'] ?>" target="_blank"
                 class="btn" style="font-size:11px; padding:6px 12px;">Podgląd</a>
              <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= $fit['id'] ?>">
                <button type="button" class="btn-delete"
                        onclick="grailConfirm('Na pewno usunąć ten fit?', () => this.closest('form').submit())">Usuń</button>
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
