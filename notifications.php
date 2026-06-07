<?php
require_once 'config/db.php';
require_once __DIR__ . '/includes/notifications.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
$me = (int)$_SESSION['user_id'];

$list = [];
try {
    $stmt = $pdo->prepare("
        SELECT n.*, u.username AS actor_name, u.avatar_url AS actor_avatar
        FROM notifications n
        LEFT JOIN users u ON n.actor_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$me]);
    $list = $stmt->fetchAll();

    // Oznacz wszystkie jako przeczytane (po wejściu na stronę)
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$me]);
} catch (Exception $e) {
    $list = [];
}

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Aktywność</div>
  <h1 class="page-title">POWIADOMIENIA</h1>
</section>

<div class="notif-list">
  <?php if ($list): ?>
    <?php foreach ($list as $n):
      [$text, $url] = notif_render($n);
    ?>
      <a href="<?= $url ?>" class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
        <?php if (!empty($n['actor_avatar'])): ?>
          <img src="<?= htmlspecialchars($n['actor_avatar']) ?>" class="notif-av" alt="">
        <?php else: ?>
          <span class="notif-av notif-av-init"><?= strtoupper(htmlspecialchars(($n['actor_name'] ?? '?')[0])) ?></span>
        <?php endif; ?>
        <div class="notif-body">
          <div class="notif-text"><?= $text ?></div>
          <div class="notif-time"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
        </div>
        <?php if (!$n['is_read']): ?><span class="notif-dot"></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="padding:60px 20px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:13px;">
      Brak powiadomień
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
