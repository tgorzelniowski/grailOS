<?php
require_once 'includes/header.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$message) {
        $error = 'Wypełnij wszystkie pola.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Nieprawidłowy adres email.';
    } elseif (strlen($message) < 10) {
        $error = 'Wiadomość zbyt krótka.';
    } elseif (strlen($message) > 2000) {
        $error = 'Wiadomość zbyt długa (max 2000 znaków).';
    } else {
        $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)")
            ->execute([$name, $email, $message]);
        $success = 'Wiadomość wysłana! Odezwiemy się wkrótce.';
    }
}
?>

<section class="page-header">
  <div class="page-header-tag">/// Get in touch</div>
  <h1 class="page-title">CONTACT</h1>
  <p class="page-sub">Masz pytanie? Napisz do nas.</p>
</section>

<div class="admin-form-wrap">
  <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="form-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" class="admin-form">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Imię / Nick</label>
        <input type="text" name="name" class="form-input" maxlength="100"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" maxlength="100"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Wiadomość</label>
      <textarea name="message" class="form-input form-textarea"
                rows="6" maxlength="2000"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
    </div>
    <button type="submit" class="btn">Wyślij →</button>
  </form>
</div>

<?php require_once 'includes/footer.php'; ?>