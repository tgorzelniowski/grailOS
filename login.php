<?php require_once 'includes/header.php'; ?>
<?php
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!check_rate_limit('login', 5, 900)) {
        $error = 'Za dużo prób logowania. Poczekaj 15 minut.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error = 'Wypełnij wszystkie pola.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Nieprawidłowy email.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true); // Ochrona przed session fixation
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = (bool) $user['is_admin'];
                unset($_SESSION['rate']);
                header('Location: /grailos/');
                exit;
            } else {
                $error = 'Nieprawidłowy email lub hasło.';
            }
        }
    }
}
?>

<section class="auth-page">
  <div class="auth-box">
    <div class="auth-tag">/// Welcome back</div>
    <h1 class="auth-title">LOGIN</h1>

    <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" class="auth-form">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-input"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Hasło</label>
        <input type="password" name="password" class="form-input">
      </div>
      <button type="submit" class="btn" style="width:100%">Zaloguj się →</button>
      <p class="auth-switch">Nie masz konta? <a href="/grailos/register.php">Zarejestruj się</a></p>
    </form>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>