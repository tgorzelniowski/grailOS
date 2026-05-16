<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$_POST['delete_id']]);
}

$posts = $pdo->query("
    SELECT posts.*, users.username,
           COUNT(DISTINCT post_likes.id)   AS likes,
           COUNT(DISTINCT post_replies.id) AS replies
    FROM posts
    LEFT JOIN users        ON posts.user_id = users.id
    LEFT JOIN post_likes   ON posts.id = post_likes.post_id
    LEFT JOIN post_replies ON posts.id = post_replies.post_id
    GROUP BY posts.id
    ORDER BY posts.created_at DESC
")->fetchAll();
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin</div>
  <h1 class="page-title">ZARZĄDZAJ<br>POSTAMI</h1>
</section>

<div class="manage-table-wrap">
  <table class="manage-table">
    <thead>
      <tr>
        <th>Użytkownik</th>
        <th>Treść</th>
        <th>Lajki</th>
        <th>Odpowiedzi</th>
        <th>Data</th>
        <th>Akcje</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($posts): ?>
        <?php foreach ($posts as $post): ?>
          <tr>
            <td>@<?= htmlspecialchars($post['username']) ?></td>
            <td style="max-width:280px; color:var(--gray); font-size:12px; line-height:1.4;">
              <?= htmlspecialchars(mb_substr($post['content'], 0, 100)) ?>...
            </td>
            <td>♥ <?= $post['likes'] ?></td>
            <td>💬 <?= $post['replies'] ?></td>
            <td style="color:var(--gray); font-size:12px;">
              <?= date('d M Y', strtotime($post['created_at'])) ?>
            </td>
            <td>
              <form method="POST"
                    onsubmit="return confirm('Usunac ten post?')">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= $post['id'] ?>">
                <button type="submit" class="btn-delete">Usuń</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" style="padding:60px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
            Brak postów
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>