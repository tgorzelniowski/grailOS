<?php
$meta_title = 'Fity — outfity społeczności';
$meta_desc  = 'Zobacz fity i outfity społeczności GrailOS, głosuj na najlepsze stylizacje streetwear i pokaż swój fit.';
require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Community</div>
  <h1 class="page-title">FITS</h1>
  <p class="page-sub">Pokaż co masz. Zainspiruj innych.</p>
</section>

<div style="padding:24px 40px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
  <div class="section-label" style="padding:0; border:none;">
    /// <?= $pdo->query("SELECT COUNT(*) FROM fits")->fetchColumn() ?> fitów
  </div>
  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="/submit_fit.php" class="btn">+ Dodaj fit</a>
  <?php else: ?>
    <a href="/login.php" class="btn btn-outline">Zaloguj się by dodać</a>
  <?php endif; ?>
</div>

<!-- FILTRY -->
<div class="fits-filter-bar">
  <form method="GET" action="/fits.php" class="fits-filter-form">
    <input type="text" name="user"
           placeholder="@użytkownik"
           value="<?= htmlspecialchars($_GET['user'] ?? '') ?>"
           class="fits-filter-input">
    <input type="text" name="kw"
           placeholder="Słowo kluczowe w opisie..."
           value="<?= htmlspecialchars($_GET['kw'] ?? '') ?>"
           class="fits-filter-input">
    <button type="submit" class="btn">Szukaj</button>
    <?php if (!empty($_GET['user']) || !empty($_GET['kw'])): ?>
      <a href="/fits.php" class="btn btn-outline">Resetuj ×</a>
    <?php endif; ?>
  </form>
</div>

<?php
$uid        = (int)($_SESSION['user_id'] ?? 0);
$search_user = trim($_GET['user'] ?? '');
$search_kw   = trim($_GET['kw']   ?? '');

$where_parts  = [];
$filter_params = [];

if ($search_user !== '') {
    $where_parts[]  = "users.username LIKE ?";
    $filter_params[] = '%' . $search_user . '%';
}
if ($search_kw !== '') {
    $where_parts[]  = "fits.description LIKE ?";
    $filter_params[] = '%' . $search_kw . '%';
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$stmt = $pdo->prepare("
    SELECT fits.*, users.username, users.avatar_url,
           COUNT(DISTINCT fit_votes.id) AS votes,
           (SELECT COUNT(*) FROM fit_comments WHERE fit_comments.fit_id = fits.id)
           + (SELECT COUNT(*) FROM fit_comment_replies fcr
              INNER JOIN fit_comments fc ON fcr.comment_id = fc.id
              WHERE fc.fit_id = fits.id) AS comments,
           MAX(CASE WHEN f.follower_id = ? THEN 1 ELSE 0 END) AS is_followed
    FROM fits
    LEFT JOIN users        ON fits.user_id    = users.id
    LEFT JOIN fit_votes    ON fits.id          = fit_votes.fit_id
    LEFT JOIN fit_comments ON fits.id          = fit_comments.fit_id
    LEFT JOIN follows f    ON f.following_id   = fits.user_id AND f.follower_id = ?
    $where_sql
    GROUP BY fits.id
    ORDER BY is_followed DESC, fits.created_at DESC
");
$stmt->execute(array_merge([$uid, $uid], $filter_params));
$fits = $stmt->fetchAll();

require_once __DIR__ . '/includes/stories_lib.php';
$story_map = active_stories_map($pdo, array_column($fits, 'user_id'), $uid);
?>

<div class="fits-grid">
  <?php if ($fits): ?>
    <?php foreach ($fits as $fit):
      $can_delete = $uid && ($fit['user_id'] == $uid || $_SESSION['is_admin']);
    ?>
      <div class="fit-card-wrap">
        <div class="fit-card fit-card-clickable" data-href="/fit.php?id=<?= $fit['id'] ?>">
          <div class="fit-img-wrap">
            <img src="<?= htmlspecialchars($fit['image_url']) ?>" alt="Fit">
            <?php if ($fit['is_followed']): ?>
              <div class="fit-following-badge">Obserwujesz</div>
            <?php endif; ?>
          </div>
          <div class="fit-info">
            <?php $ring = story_ring_class($story_map, (int)$fit['user_id']);
                  $row_href = $ring ? '/story_view.php?user=' . (int)$fit['user_id']
                                    : '/profile.php?username=' . urlencode($fit['username']); ?>
            <a href="<?= $row_href ?>"
               class="fit-user-row" onclick="event.stopPropagation()" style="text-decoration:none;">
              <span class="<?= $ring ?>" style="display:inline-flex;border-radius:50%;">
              <?php if ($fit['avatar_url']): ?>
                <img src="<?= htmlspecialchars($fit['avatar_url']) ?>"
                     class="fit-mini-avatar" alt="">
              <?php else: ?>
                <span class="fit-mini-avatar fit-mini-initials">
                  <?= strtoupper($fit['username'][0]) ?>
                </span>
              <?php endif; ?>
              </span>
              <span class="fit-user">@<?= htmlspecialchars($fit['username']) ?></span>
            </a>
            <?php if ($fit['description']): ?>
              <div class="fit-desc"><?= htmlspecialchars(mb_substr($fit['description'],0,80)) ?>...</div>
            <?php endif; ?>
            <div class="fit-footer">
              <span class="fit-date"><?= date('d M Y', strtotime($fit['created_at'])) ?></span>
              <span class="vote-count-static">♥ <?= $fit['votes'] ?> &nbsp;💬 <?= $fit['comments'] ?></span>
            </div>
          </div>
        </div>
        <?php if ($can_delete): ?>
          <form method="POST" action="/delete_fit.php"
                class="inline-delete-form">
            <?= csrf_field() ?>
            <input type="hidden" name="fit_id" value="<?= $fit['id'] ?>">
            <button type="button" class="inline-delete-btn"
                    onclick="grailConfirm('Usunąć ten fit?', () => this.closest('form').submit())">✕</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3;padding:80px;text-align:center;color:#444;letter-spacing:3px;text-transform:uppercase;font-size:13px;">
      Brak fitów.
    </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.fit-card-clickable').forEach(card => {
  card.addEventListener('click', e => {
    if (!e.target.closest('a, button, form')) {
      window.location.href = card.dataset.href;
    }
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>