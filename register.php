
<?php
require_once 'config/db.php';
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$username || !$email || !$password || !$confirm) {
        $error = 'Wypełnij wszystkie pola.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = 'Username: 3-20 znaków, tylko litery, cyfry i _.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Nieprawidłowy adres email.';
    } elseif (strlen($password) < 8) {
        $error = 'Hasło musi mieć minimum 8 znaków.';
    } elseif ($password !== $confirm) {
        $error = 'Hasła nie są identyczne.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = 'Ten email lub username jest już zajęty.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hash]);
            $success = 'Konto utworzone! Możesz się teraz zalogować.';
        }
    }
}
require_once 'includes/header.php';
?>

<section class="auth-page">
  <div class="auth-box">
    <div class="auth-tag">/// Join</div>
    <h1 class="auth-title">REGISTER</h1>

    <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="form-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" class="auth-form">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Username (3-20 znaków)</label>
        <input type="text" name="username" class="form-input" maxlength="20"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input" maxlength="100"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Hasło (min. 8 znaków)</label>
        <input type="password" name="password" class="form-input" minlength="8">
      </div>
      <div class="form-group">
        <label class="form-label">Potwierdź hasło</label>
        <input type="password" name="confirm" class="form-input">
      </div>
      <button type="submit" class="btn" style="width:100%">Utwórz konto →</button>
      <p class="auth-switch">Masz już konto? <a href="/grailos/login.php">Zaloguj się</a></p>
    </form>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>