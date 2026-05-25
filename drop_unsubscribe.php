<?php
require_once 'config/db.php';

$token = trim($_GET['token'] ?? '');
$ok    = false;

if ($token) {
    $stmt = $pdo->prepare("DELETE FROM drop_notifications WHERE unsubscribe_token = ?");
    $stmt->execute([$token]);
    $ok = $stmt->rowCount() > 0;
}

require_once 'includes/header.php';
?>

<section class="auth-page">
  <div class="auth-box" style="text-align:center;">
    <div class="auth-tag">/// Powiadomienia</div>
    <?php if ($ok): ?>
      <h1 class="auth-title">WYPISANO</h1>
      <div class="form-success">Nie będziemy Cię już powiadamiać o tym dropie.</div>
    <?php else: ?>
      <h1 class="auth-title">BŁĄD</h1>
      <div class="form-error">Link jest nieprawidłowy lub już wypisano wcześniej.</div>
    <?php endif; ?>
    <div style="margin-top:24px;">
      <a href="/grailos/drops.php" class="btn btn-outline"
         style="width:100%;text-align:center;display:block;">
        Wróć do dropów
      </a>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>