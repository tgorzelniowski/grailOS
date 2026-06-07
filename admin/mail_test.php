<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

$sent = null; $err = ''; $to = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $to = trim($_POST['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $err = 'Podaj poprawny adres e-mail.';
    } else {
        $html = mail_template('Test wysyłki', '<p style="color:#444;font-size:15px;">To jest testowy e-mail z GrailOS. Jeśli go widzisz — SMTP działa. ✅</p>');
        $sent = send_mail($to, 'Test', 'GrailOS — test SMTP', $html);
        if (!$sent) $err = mail_last_error();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Diagnostyka</div>
  <h1 class="page-title">TEST MAILA</h1>
  <p class="page-sub">Sprawdź wysyłkę SMTP i zobacz błąd serwera</p>
</section>

<div class="admin-form-wrap" style="max-width:560px;">

  <?php if ($sent === true): ?>
    <div class="form-success" style="margin-bottom:16px;">
      ✅ Wysłano na <strong><?= htmlspecialchars($to) ?></strong>. Sprawdź skrzynkę (i SPAM).
    </div>
  <?php elseif ($sent === false): ?>
    <div class="form-error" style="margin-bottom:16px;">
      ❌ Nie wysłano. Odpowiedź serwera:<br>
      <code style="color:#fff; word-break:break-all;"><?= htmlspecialchars($err ?: 'brak szczegółów') ?></code>
    </div>
  <?php elseif ($err): ?>
    <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <!-- Aktualna konfiguracja (bez hasła) -->
  <div class="edit-section" style="margin-bottom:24px;">
    <div class="form-section-title">/// Konfiguracja</div>
    <ul style="color:var(--gray); font-size:13px; line-height:1.8; padding-left:18px;">
      <li>SMTP: <strong><?= MAIL_SMTP ? 'ON' : 'OFF (native mail)' ?></strong></li>
      <li>Host: <strong><?= htmlspecialchars(MAIL_HOST) ?></strong> : <strong><?= (int)MAIL_PORT ?></strong> <?= (int)MAIL_PORT === 465 ? '(SSL)' : '(STARTTLS)' ?></li>
      <li>Login: <strong><?= htmlspecialchars(MAIL_USER) ?></strong></li>
      <li>From: <strong><?= htmlspecialchars(MAIL_FROM) ?></strong></li>
    </ul>
  </div>

  <form method="POST" class="admin-form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Wyślij testowy mail na adres</label>
      <input type="email" name="to" class="form-input" required
             value="<?= htmlspecialchars($to) ?>" placeholder="twoj@email.com">
    </div>
    <button type="submit" class="btn">Wyślij test →</button>
  </form>

  <div style="margin-top:24px;">
    <a href="/admin/" class="btn btn-outline">← Admin</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
