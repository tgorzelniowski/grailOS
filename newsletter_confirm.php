<?php
require_once 'config/db.php';

$token = trim($_GET['token'] ?? '');
$ok    = false;

if ($token) {
    $stmt = $pdo->prepare("
        UPDATE newsletter_subscribers SET confirmed = 1
        WHERE confirm_token = ? AND confirmed = 0
    ");
    $stmt->execute([$token]);
    $ok = $stmt->rowCount() > 0;
}

require_once 'includes/header.php';
?>

<section class="auth-page">
  <div class="auth-box" style="text-align:center;">
    <?php if ($ok): ?>
      <div class="auth-tag">/// Newsletter</div>
      <h1 class="auth-title">GOTOWE!</h1>
      <div class="form-success" style="margin-bottom:24px;">
        Twój email został potwierdzony. Jesteś teraz na liście GrailOS!
      </div>
      <a href="/grailos/" class="btn" style="width:100%;text-align:center;display:block;">
        Przejdź do strony →
      </a>
    <?php else: ?>
      <div class="auth-tag">/// Newsletter</div>
      <h1 class="auth-title">BŁĄD</h1>
      <div class="form-error" style="margin-bottom:24px;">
        Link jest nieważny lub subskrypcja już została potwierdzona.
      </div>
      <a href="/grailos/" class="btn btn-outline"
         style="width:100%;text-align:center;display:block;">
        Wróć do strony
      </a>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>