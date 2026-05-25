<?php
require_once 'config/db.php';
require_once 'includes/mailer.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!check_rate_limit('forgot_pw', 3, 900)) {
        $error = 'Za dużo prób. Poczekaj 15 minut.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Podaj prawidłowy adres email.';
        } else {
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Zawsze pokazuj ten sam komunikat (ochrona przed enumeracją emaili)
            $success = 'Jeśli konto z tym emailem istnieje, wysłaliśmy link do resetowania hasła.';

            if ($user) {
                // Unieważnij stare tokeny
                $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?")
                    ->execute([$user['id']]);

                $token     = bin2hex(random_bytes(32));
                $expires   = date('Y-m-d H:i:s', time() + 3600); // 1 godzina

                $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $token, $expires]);

                $reset_url = MAIL_SITE_URL . '/reset_password.php?token=' . $token;
                $username  = htmlspecialchars($user['username']);

                $body = "<p style='color:#444;font-size:15px;line-height:1.7;'>
                    Cześć <strong>@$username</strong>,<br><br>
                    Otrzymaliśmy prośbę o reset hasła do Twojego konta GrailOS.
                    Kliknij przycisk poniżej, aby ustawić nowe hasło.
                    Link jest ważny przez <strong>1 godzinę</strong>.
                </p>
                <p style='color:#888;font-size:13px;margin-top:24px;'>
                    Jeśli to nie Ty wysłałeś tę prośbę — zignoruj tę wiadomość.
                    Twoje hasło pozostaje bez zmian.
                </p>";

                $html = mail_template(
                    'Reset hasła',
                    $body,
                    'Ustaw nowe hasło →',
                    $reset_url
                );

                send_mail($email, $user['username'], 'Reset hasła — GrailOS', $html);
            }
        }
    }
}

require_once 'includes/header.php';
?>

<section class="auth-page">
  <div class="auth-box">
    <div class="auth-tag">/// Bezpieczeństwo</div>
    <h1 class="auth-title">RESET<br>HASŁA</h1>

    <?php if ($error): ?>
      <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="form-success"><?= htmlspecialchars($success) ?></div>
      <p style="margin-top:20px;font-size:13px;color:var(--gray);letter-spacing:1px;">
        Sprawdź skrzynkę odbiorczą oraz folder Spam.
      </p>
    <?php else: ?>
      <p style="font-size:14px;color:var(--gray);letter-spacing:1px;margin-bottom:28px;line-height:1.6;">
        Podaj adres email przypisany do konta. Wyślemy Ci link do ustawienia nowego hasła.
      </p>
      <form method="POST" class="auth-form">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Adres email</label>
          <input type="email" name="email" class="form-input"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="twoj@email.com" required>
        </div>
        <button type="submit" class="btn" style="width:100%">Wyślij link →</button>
        <p class="auth-switch">
          <a href="/grailos/login.php">← Wróć do logowania</a>
        </p>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>