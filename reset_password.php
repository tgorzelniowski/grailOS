<?php
require_once 'config/db.php';

$token = trim($_GET['token'] ?? '');
$error   = '';
$success = '';

// Weryfikuj token
$reset = null;
if ($token) {
    $stmt = $pdo->prepare("
        SELECT password_resets.*, users.username, users.email
        FROM password_resets
        JOIN users ON password_resets.user_id = users.id
        WHERE password_resets.token = ?
          AND password_resets.used = 0
          AND password_resets.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
}

if (!$reset) {
    $error = 'Link do resetowania hasła jest nieważny lub wygasł.';
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 8) {
        $error = 'Hasło musi mieć co najmniej 8 znaków.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Hasło musi zawierać co najmniej jedną wielką literę.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Hasło musi zawierać co najmniej jedną małą literę.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Hasło musi zawierać co najmniej jeden znak specjalny.';
    } elseif ($password !== $confirm) {
        $error = 'Hasła nie są identyczne.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([$hash, $reset['user_id']]);

        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
            ->execute([$token]);

        // Unieważnij wszystkie sesje użytkownika (nie ma prostego mechanizmu w PHP bez DB sesji)
        $success = 'Hasło zostało zmienione. Możesz się teraz zalogować.';
    }
}

require_once 'includes/header.php';
?>

<section class="auth-page">
  <div class="auth-box">
    <div class="auth-tag">/// Bezpieczeństwo</div>
    <h1 class="auth-title">NOWE<br>HASŁO</h1>

    <?php if ($error && !$reset): ?>
      <div class="form-error"><?= htmlspecialchars($error) ?></div>
      <p style="margin-top:20px;">
        <a href="/forgot_password.php" class="btn">Wyślij nowy link →</a>
      </p>

    <?php elseif ($success): ?>
      <div class="form-success"><?= htmlspecialchars($success) ?></div>
      <div style="margin-top:24px;">
        <a href="/login.php" class="btn" style="width:100%;text-align:center;display:block;">
          Zaloguj się →
        </a>
      </div>

    <?php else: ?>
      <p style="font-size:14px;color:var(--gray);letter-spacing:1px;margin-bottom:28px;">
        Ustawiasz nowe hasło dla konta
        <strong style="color:var(--white);">@<?= htmlspecialchars($reset['username']) ?></strong>
      </p>

      <?php if ($error): ?>
        <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="auth-form" id="resetForm">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Nowe hasło</label>
          <input type="password" name="password" id="resetPassword" class="form-input"
                 oninput="checkPassword(this.value)" required>
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
          <input type="password" name="confirm" class="form-input" required>
        </div>
        <button type="submit" class="btn" style="width:100%">Zapisz nowe hasło →</button>
      </form>
    <?php endif; ?>
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
    if (!el) continue;
    el.textContent = (ok ? '✓ ' : '✗ ') + el.textContent.slice(2);
    el.className   = 'pw-rule' + (ok ? ' pw-ok' : '');
    if (ok) passed++;
  }
  const bar = document.getElementById('pwBar');
  if (!bar) return;
  const pct = (passed / 4) * 100;
  bar.style.width      = pct + '%';
  bar.style.background = pct <= 25 ? '#e74c3c'
                       : pct <= 50 ? '#e67e22'
                       : pct <= 75 ? '#f1c40f'
                       : '#2ecc71';
}
</script>

<?php require_once 'includes/footer.php'; ?>