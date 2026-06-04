<?php require_once 'includes/header.php'; ?>

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
    <a href="/grailos/submit_fit.php" class="btn">+ Dodaj fit</a>
  <?php else: ?>
    <a href="/grailos/login.php" class="btn btn-outline">Zaloguj się by dodać</a>
  <?php endif; ?>
</div>

<!-- FILTRY -->
<div class="fits-filter-bar">
  <form method="GET" action="/grailos/fits.php" class="fits-filter-form">
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
      <a href="/grailos/fits.php" class="btn btn-outline">Resetuj ×</a>
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
           COUNT(DISTINCT fit_votes.id)    AS votes,
           COUNT(DISTINCT fit_comments.id) AS comments,
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
?>

<div class="fits-grid">
  <?php if ($fits): ?>
    <?php foreach ($fits as $fit):
      $can_delete = $uid && ($fit['user_id'] == $uid || $_SESSION['is_admin']);
    ?>
      <div class="fit-card-wrap">
        <a href="/grailos/fit.php?id=<?= $fit['id'] ?>" class="fit-card">
          <div class="fit-img-wrap">
            <img src="<?= htmlspecialchars($fit['image_url']) ?>" alt="Fit">
            <?php if ($fit['is_followed']): ?>
              <div class="fit-following-badge">Obserwujesz</div>
            <?php endif; ?>
          </div>
          <div class="fit-info">
            <div class="fit-user-row">
              <?php if ($fit['avatar_url']): ?>
                <img src="<?= htmlspecialchars($fit['avatar_url']) ?>"
                     class="fit-mini-avatar" alt="">
              <?php else: ?>
                <span class="fit-mini-avatar fit-mini-initials">
                  <?= strtoupper($fit['username'][0]) ?>
                </span>
              <?php endif; ?>
              <span class="fit-user">@<?= htmlspecialchars($fit['username']) ?></span>
            </div>
            <?php if ($fit['description']): ?>
              <div class="fit-desc"><?= htmlspecialchars(mb_substr($fit['description'],0,80)) ?>...</div>
            <?php endif; ?>
            <div class="fit-footer">
              <span class="fit-date"><?= date('d M Y', strtotime($fit['created_at'])) ?></span>
              <span class="vote-count-static">♥ <?= $fit['votes'] ?> &nbsp;💬 <?= $fit['comments'] ?></span>
            </div>
          </div>
        </a>
        <?php if ($can_delete): ?>
          <form method="POST" action="/grailos/delete_fit.php"
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

<?php require_once 'includes/footer.php'; ?>