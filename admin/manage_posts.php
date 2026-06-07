<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$_POST['delete_id']]);
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
    $where_parts[] = "posts.content LIKE ?";
    $params[]      = '%' . $search_kw . '%';
}
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$stmt = $pdo->prepare("
    SELECT posts.*, users.username,
           (SELECT COUNT(*) FROM post_likes   WHERE post_likes.post_id   = posts.id) AS likes,
           (SELECT COUNT(*) FROM post_replies WHERE post_replies.post_id = posts.id) AS replies
    FROM posts
    LEFT JOIN users ON posts.user_id = users.id
    $where
    GROUP BY posts.id
    ORDER BY posts.created_at DESC
");
$stmt->execute($params);
$posts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin</div>
  <h1 class="page-title">ZARZĄDZAJ<br>POSTAMI</h1>
</section>

<!-- WYSZUKIWARKA -->
<div style="padding:20px 40px; border-bottom:1px solid var(--border);">
  <form method="GET" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <input type="text" name="user" placeholder="@użytkownik"
           value="<?= htmlspecialchars($search_user) ?>"
           class="form-input" style="max-width:200px; padding:8px 0;">
    <input type="text" name="kw" placeholder="Słowo kluczowe..."
           value="<?= htmlspecialchars($search_kw) ?>"
           class="form-input" style="max-width:280px; padding:8px 0;">
    <button type="submit" class="btn" style="font-size:11px; padding:8px 18px;">Szukaj</button>
    <?php if ($search_user !== '' || $search_kw !== ''): ?>
      <a href="/admin/manage_posts.php" class="btn btn-outline"
         style="font-size:11px; padding:8px 18px;">Resetuj ×</a>
      <span style="font-size:11px; letter-spacing:2px; color:var(--gray); text-transform:uppercase;">
        <?= count($posts) ?> wyników
      </span>
    <?php endif; ?>
  </form>
</div>

<div class="manage-table-wrap">
  <table class="manage-table">
    <thead>
      <tr>
        <th>Użytkownik</th>
        <th>Treść / Zdjęcie</th>
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
            <td><a href="/profile.php?username=<?= urlencode($post['username']) ?>"
                   style="color:var(--red); text-decoration:none;">@<?= htmlspecialchars($post['username']) ?></a></td>
            <td style="max-width:260px;">
              <?php if ($post['content']): ?>
                <div style="color:var(--gray); font-size:12px; line-height:1.4; margin-bottom:6px;">
                  <?= htmlspecialchars(mb_substr($post['content'], 0, 100)) ?><?= mb_strlen($post['content']) > 100 ? '…' : '' ?>
                </div>
              <?php endif; ?>
              <?php if ($post['image_url']): ?>
                <img src="<?= htmlspecialchars($post['image_url']) ?>"
                     style="width:56px; height:56px; object-fit:cover; border:1px solid var(--border);">
              <?php endif; ?>
            </td>
            <td>♥ <?= $post['likes'] ?></td>
            <td>💬 <?= $post['replies'] ?></td>
            <td style="color:var(--gray); font-size:12px;">
              <?= date('d M Y', strtotime($post['created_at'])) ?>
            </td>
            <td style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
              <a href="/post.php?id=<?= $post['id'] ?>" target="_blank"
                 class="btn" style="font-size:11px; padding:6px 12px;">Podgląd</a>
              <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="delete_id" value="<?= $post['id'] ?>">
                <button type="button" class="btn-delete"
                        onclick="grailConfirm('Usunąć ten post?', () => this.closest('form').submit())">Usuń</button>
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
