<?php
require_once 'config/db.php';

$token = trim($_GET['token'] ?? '');
$ok    = false;
$email = '';

if ($token) {
    $stmt = $pdo->prepare("SELECT email FROM newsletter_subscribers WHERE unsubscribe_token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if ($row) {
        $email = $row['email'];
        $pdo->prepare("DELETE FROM newsletter_subscribers WHERE unsubscribe_token = ?")
            ->execute([$token]);
        $ok = true;
    }
}

require_once 'includes/header.php';
?>

<section class="auth-page">
  <div class="auth-box" style="text-align:center;">
    <div class="auth-tag">/// Newsletter</div>
    <?php if ($ok): ?>
      <h1 class="auth-title">WYPISANO</h1>
      <div class="form-success" style="margin-bottom:24px;">
        Adres <strong><?= htmlspecialchars($email) ?></strong>
        został usunięty z listy mailingowej GrailOS.
      </div>
    <?php else: ?>
      <h1 class="auth-title">BŁĄD</h1>
      <div class="form-error" style="margin-bottom:24px;">
        Link jest nieprawidłowy lub subskrypcja już została anulowana.
      </div>
    <?php endif; ?>
    <a href="/grailos/" class="btn btn-outline"
       style="width:100%;text-align:center;display:block;">
      Wróć do strony
    </a>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>