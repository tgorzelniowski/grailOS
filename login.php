<?php
require_once 'config/db.php';

$error   = '';
$success = '';

if (!empty($_GET['registered'])) {
    $success = 'Konto utworzone! Możesz się teraz zalogować.';
}

// Komunikaty z walidacji sesji
if (!empty($_GET['expired'])) {
    $error = 'Sesja wygasła z powodu braku aktywności. Zaloguj się ponownie.';
} elseif (!empty($_GET['security'])) {
    $error = 'Sesja została zakończona ze względów bezpieczeństwa. Zaloguj się ponownie.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!check_rate_limit('login', 5, 900)) {
        $error = 'Za dużo prób. Poczekaj 15 minut.';
    } else {
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$login || !$password) {
            $error = 'Wypełnij wszystkie pola.';
        } else {
            // Email lub username
            if (strpos($login, '@') !== false) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            }
            $stmt->execute([$login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['is_banned']) {
                    $error = 'Twoje konto zostało zbanowane.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['username']   = $user['username'];
                    $_SESSION['is_admin']   = (bool) $user['is_admin'];
                    $_SESSION['avatar_url'] = $user['avatar_url'] ?? '';
                    unset($_SESSION['rate']);
                    set_session_fingerprint();
                    header('Location: /');
                    exit;
                }
            } else {
                $error = 'Nieprawidłowy login lub hasło.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<section class="auth-page">
  <div class="auth-box">
    <div class="auth-tag">/// Welcome back</div>
    <h1 class="auth-title">LOGIN</h1>

    <?php if ($success): ?>
      <div class="form-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Email lub username</label>
        <input type="text" name="login" class="form-input"
               value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
               placeholder="np. grailuser lub grail@email.com">
      </div>
      <div class="form-group">
        <label class="form-label">Hasło</label>
        <input type="password" name="password" class="form-input">
      </div>
      <button type="submit" class="btn" style="width:100%">Zaloguj się →</button>
      <p class="auth-switch" style="margin-top:8px;">
        <a href="/forgot_password.php"
           style="font-size:11px;letter-spacing:2px;color:var(--gray);">
          Zapomniałem hasła
        </a>
      </p>
      <p class="auth-switch">Nie masz konta? <a href="/register.php">Zarejestruj się</a></p>
    </form>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>