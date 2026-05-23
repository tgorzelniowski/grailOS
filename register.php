<?php
require_once 'config/db.php';

$error   = '';
$success = '';

function validatePassword(string $p): ?string {
    if (strlen($p) < 8)                          return 'Minimum 8 znaków.';
    if (!preg_match('/[A-Z]/', $p))              return 'Wymagana co najmniej jedna wielka litera.';
    if (!preg_match('/[a-z]/', $p))              return 'Wymagana co najmniej jedna mała litera.';
    if (!preg_match('/[^A-Za-z0-9]/', $p))      return 'Wymagany co najmniej jeden znak specjalny (!@#$%...).';
    return null;
}

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
    } elseif ($err = validatePassword($password)) {
        $error = $err;
    } elseif ($password !== $confirm) {
        $error = 'Hasła nie są identyczne.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $error = 'Ten email lub username jest już zajęty.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)")
                ->execute([$username, $email, $hash]);
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

    <?php if ($error): ?>
      <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="form-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form" id="regForm">
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
        <label class="form-label">Hasło</label>
        <input type="password" name="password" id="regPassword" class="form-input"
               oninput="checkPassword(this.value)">
        <!-- WSKAŹNIK SIŁY HASŁA -->
        <div class="password-strength" id="pwStrength">
          <div class="pw-bar" id="pwBar"></div>
        </div>
        <div class="pw-rules" id="pwRules">
          <span class="pw-rule" id="rule-len">✗ Min. 8 znaków</span>
          <span class="pw-rule" id="rule-upper">✗ Wielka litera</span>
          <span class="pw-rule" id="rule-lower">✗ Mała litera</span>
          <span class="pw-rule" id="rule-special">✗ Znak specjalny</span>
        </div>
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

<script>
function checkPassword(val) {
  const rules = {
    'rule-len':     val.length >= 8,
    'rule-upper':   /[A-Z]/.test(val),
    'rule-lower':   /[a-z]/.test(val),
    'rule-special': /[^A-Za-z0-9]/.test(val),
  };
  let passed = 0;
  for (const [id, ok] of Object.entries(rules)) {
    const el = document.getElementById(id);
    el.textContent = (ok ? '✓ ' : '✗ ') + el.textContent.slice(2);
    el.className   = 'pw-rule' + (ok ? ' pw-ok' : '');
    if (ok) passed++;
  }
  const bar = document.getElementById('pwBar');
  const pct = (passed / 4) * 100;
  bar.style.width = pct + '%';
  bar.style.background = pct <= 25 ? '#e74c3c'
                       : pct <= 50 ? '#e67e22'
                       : pct <= 75 ? '#f1c40f'
                       : '#2ecc71';
}
</script>

<?php require_once 'includes/footer.php'; ?>