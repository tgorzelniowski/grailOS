<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}

$success = '';
$error   = '';
$section = $_POST['section'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // === ZMIANA NAZWY ===
    if ($section === 'username') {
        $new_username = trim($_POST['username'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $new_username)) {
            $error = 'Username: 3-20 znaków, tylko litery, cyfry i _.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$new_username, $_SESSION['user_id']]);
            if ($check->fetch()) {
                $error = 'Ta nazwa jest już zajęta.';
            } else {
                $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")
                    ->execute([$new_username, $_SESSION['user_id']]);
                $_SESSION['username'] = $new_username;
                $success = 'Nazwa użytkownika zmieniona!';
            }
        }
    }

    // === ZMIANA EMAILA ===
    if ($section === 'email') {
        $new_email = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';

        $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $user->execute([$_SESSION['user_id']]);
        $user = $user->fetch();

        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Nieprawidłowy adres email.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Podaj aktualne hasło żeby zmienić email.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$new_email, $_SESSION['user_id']]);
            if ($check->fetch()) {
                $error = 'Ten email jest już zajęty.';
            } else {
                $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")
                    ->execute([$new_email, $_SESSION['user_id']]);
                $success = 'Email zmieniony!';
            }
        }
    }

    // === ZMIANA HASŁA ===
    if ($section === 'password') {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $user->execute([$_SESSION['user_id']]);
        $user = $user->fetch();

        if (!password_verify($current, $user['password'])) {
            $error = 'Aktualne hasło jest nieprawidłowe.';
        } elseif (strlen($new_pass) < 8) {
            $error = 'Nowe hasło musi mieć minimum 8 znaków.';
        } elseif ($new_pass !== $confirm) {
            $error = 'Hasła nie są identyczne.';
        } else {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([$hash, $_SESSION['user_id']]);
            $success = 'Hasło zmienione!';
        }
    }

    // === PRYWATNOŚĆ ===
    if ($section === 'privacy') {
        $sw = isset($_POST['show_wardrobe']) ? 1 : 0;
        $so = isset($_POST['show_outfits'])  ? 1 : 0;
        $sp = isset($_POST['show_posts'])    ? 1 : 0;
        $sf = isset($_POST['show_fits'])     ? 1 : 0;
        try {
            $pdo->prepare("UPDATE users SET show_wardrobe = ?, show_outfits = ?, show_posts = ?, show_fits = ? WHERE id = ?")
                ->execute([$sw, $so, $sp, $sf, $_SESSION['user_id']]);
            $success = 'Ustawienia prywatności zapisane!';
        } catch (Exception $e) {
            $error = 'Uruchom migrację privacy_settings_migration.sql w phpMyAdmin.';
        }
        // Widoczność obserwacji (osobna migracja)
        $sfo = isset($_POST['show_follows']) ? 1 : 0;
        try {
            $pdo->prepare("UPDATE users SET show_follows = ? WHERE id = ?")
                ->execute([$sfo, $_SESSION['user_id']]);
        } catch (Exception $e) {
            $error = 'Uruchom migrację follows_privacy_migration.sql w phpMyAdmin.';
        }
    }
}

// Aktualne ustawienia prywatności
$me = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$me->execute([$_SESSION['user_id']]);
$me = $me->fetch();
$priv = [
    'show_wardrobe' => $me['show_wardrobe'] ?? 1,
    'show_outfits'  => $me['show_outfits']  ?? 1,
    'show_posts'    => $me['show_posts']    ?? 1,
    'show_fits'     => $me['show_fits']     ?? 1,
    'show_follows'  => $me['show_follows']  ?? 1,
];

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Konto</div>
  <h1 class="page-title">SETTINGS</h1>
</section>

<div class="settings-wrap">

  <?php if ($success): ?>
    <div class="form-success" style="margin-bottom:24px;"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="form-error" style="margin-bottom:24px;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ZMIANA USERNAME -->
  <div class="settings-section">
    <div class="settings-section-title">/// Nazwa użytkownika</div>
    <div class="settings-section-desc">Aktualna: @<?= htmlspecialchars($_SESSION['username']) ?></div>
    <form method="POST" class="settings-form">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="username">
      <div class="form-group">
        <label class="form-label">Nowa nazwa (3-20 znaków)</label>
        <input type="text" name="username" class="form-input"
               maxlength="20" placeholder="nowy_nick"
               value="<?= $section === 'username' ? htmlspecialchars($_POST['username'] ?? '') : '' ?>">
      </div>
      <button type="submit" class="btn">Zmień nazwę →</button>
    </form>
  </div>

  <!-- ZMIANA EMAIL -->
  <div class="settings-section">
    <div class="settings-section-title">/// Email</div>
    <form method="POST" class="settings-form">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="email">
      <div class="form-group">
        <label class="form-label">Nowy email</label>
        <input type="email" name="email" class="form-input"
               placeholder="nowy@email.com"
               value="<?= $section === 'email' ? htmlspecialchars($_POST['email'] ?? '') : '' ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Aktualne hasło (weryfikacja)</label>
        <input type="password" name="password" class="form-input">
      </div>
      <button type="submit" class="btn">Zmień email →</button>
    </form>
  </div>

  <!-- ZMIANA HASŁA -->
  <div class="settings-section">
    <div class="settings-section-title">/// Hasło</div>
    <form method="POST" class="settings-form">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="password">
      <div class="form-group">
        <label class="form-label">Aktualne hasło</label>
        <input type="password" name="current_password" class="form-input">
      </div>
      <div class="form-group">
        <label class="form-label">Nowe hasło (min. 8 znaków)</label>
        <input type="password" name="new_password" class="form-input">
      </div>
      <div class="form-group">
        <label class="form-label">Potwierdź nowe hasło</label>
        <input type="password" name="confirm_password" class="form-input">
      </div>
      <button type="submit" class="btn">Zmień hasło →</button>
    </form>
  </div>

  <!-- PRYWATNOŚĆ -->
  <div class="settings-section">
    <div class="settings-section-title">/// Prywatność</div>
    <div class="settings-section-desc">Wybierz co inni widzą na Twoim profilu. Odznaczone sekcje są ukryte dla innych użytkowników.</div>
    <form method="POST" class="settings-form">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="privacy">
      <label class="privacy-toggle">
        <input type="checkbox" name="show_fits" <?= $priv['show_fits'] ? 'checked' : '' ?>>
        <span>Pokazuj moje fity</span>
      </label>
      <label class="privacy-toggle">
        <input type="checkbox" name="show_outfits" <?= $priv['show_outfits'] ? 'checked' : '' ?>>
        <span>Pokazuj moje outfity</span>
      </label>
      <label class="privacy-toggle">
        <input type="checkbox" name="show_wardrobe" <?= $priv['show_wardrobe'] ? 'checked' : '' ?>>
        <span>Pokazuj moją szafę</span>
      </label>
      <label class="privacy-toggle">
        <input type="checkbox" name="show_posts" <?= $priv['show_posts'] ? 'checked' : '' ?>>
        <span>Pokazuj moje posty</span>
      </label>
      <label class="privacy-toggle">
        <input type="checkbox" name="show_follows" <?= $priv['show_follows'] ? 'checked' : '' ?>>
        <span>Pokazuj obserwujących i obserwowanych</span>
      </label>
      <button type="submit" class="btn" style="margin-top:8px;">Zapisz prywatność →</button>
    </form>
  </div>

  <div style="margin-top:40px; padding-top:32px; border-top:1px solid var(--border);">
    <a href="/grailos/profile.php?username=<?= urlencode($_SESSION['username']) ?>"
       class="btn btn-outline">← Wróć do profilu</a>
  </div>

</div>

<?php require_once 'includes/footer.php'; ?>