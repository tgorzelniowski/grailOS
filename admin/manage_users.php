<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (isset($_POST['ban_id'])) {
        $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ? AND is_admin = 0")
            ->execute([$_POST['ban_id']]);
    }
    if (isset($_POST['unban_id'])) {
        $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?")
            ->execute([$_POST['unban_id']]);
    }
}

$users = $pdo->query("
    SELECT users.*,
           COUNT(DISTINCT fits.id)  AS fit_count,
           COUNT(DISTINCT posts.id) AS post_count
    FROM users
    LEFT JOIN fits  ON users.id = fits.user_id
    LEFT JOIN posts ON users.id = posts.user_id
    GROUP BY users.id
    ORDER BY users.created_at DESC
")->fetchAll();
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin</div>
  <h1 class="page-title">ZARZĄDZAJ<br>USERAMI</h1>
</section>

<div class="manage-table-wrap">
  <table class="manage-table">
    <thead>
      <tr>
        <th>Username</th>
        <th>Email</th>
        <th>Fity</th>
        <th>Posty</th>
        <th>Dołączył</th>
        <th>Status</th>
        <th>Akcje</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <tr style="<?= $user['is_banned'] ? 'opacity:0.5;' : '' ?>">
          <td>
            @<?= htmlspecialchars($user['username']) ?>
            <?php if ($user['is_admin']): ?>
              <span style="color:var(--red); font-size:10px; letter-spacing:2px; margin-left:8px;">ADMIN</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--gray); font-size:12px;"><?= htmlspecialchars($user['email']) ?></td>
          <td><?= $user['fit_count'] ?></td>
          <td><?= $user['post_count'] ?></td>
          <td style="color:var(--gray); font-size:12px;"><?= date('d M Y', strtotime($user['created_at'])) ?></td>
          <td>
            <?php if ($user['is_banned']): ?>
              <span style="color:var(--red); font-size:11px; letter-spacing:2px; text-transform:uppercase;">BANNED</span>
            <?php else: ?>
              <span style="color:#2ecc71; font-size:11px; letter-spacing:2px; text-transform:uppercase;">ACTIVE</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$user['is_admin']): ?>
              <form method="POST">
                <?= csrf_field() ?>
                <?php if ($user['is_banned']): ?>
                  <input type="hidden" name="unban_id" value="<?= $user['id'] ?>">
                  <button type="submit" class="btn" style="font-size:11px; padding:6px 14px;">Unban</button>
                <?php else: ?>
                  <input type="hidden" name="ban_id" value="<?= $user['id'] ?>">
                  <button type="submit" class="btn-delete"
                          onclick="return confirm('Zbanowac @<?= htmlspecialchars($user['username']) ?>?')">
                    Ban
                  </button>
                <?php endif; ?>
              </form>
            <?php else: ?>
              <span style="color:#444; font-size:11px; letter-spacing:2px;">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>