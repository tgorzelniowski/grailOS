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
        // Zapis do bazy (kopia zapasowa)
        $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)")
            ->execute([$name, $email, $message]);

        // Wyślij wiadomość na skrzynkę kontaktową
        require_once __DIR__ . '/includes/mailer.php';
        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#222;line-height:1.6;">'
              . '<h2 style="margin:0 0 12px;">Nowa wiadomość z formularza kontaktowego</h2>'
              . '<p><strong>Od:</strong> ' . htmlspecialchars($name) . ' &lt;' . htmlspecialchars($email) . '&gt;</p>'
              . '<p><strong>Wiadomość:</strong></p>'
              . '<p style="white-space:pre-wrap;background:#f5f5f5;padding:14px;border-radius:6px;">'
              . nl2br(htmlspecialchars($message)) . '</p>'
              . '<p style="color:#888;font-size:13px;">Aby odpowiedzieć, napisz bezpośrednio na: '
              . htmlspecialchars($email) . '</p></div>';

        send_mail('kontakt@grailos.pl', 'GrailOS Kontakt',
                  'Kontakt ze strony: ' . $name, $html);

        $success = 'Wiadomość wysłana! Odezwiemy się wkrótce.';
    }
}
?>

<section class="page-header">
  <div class="page-header-tag">/// Get in touch</div>
  <h1 class="page-title">CONTACT</h1>
  <p class="page-sub">Masz pytanie? Napisz do nas.</p>
</section>

<div class="contact-wrap">

  <!-- DWA BANERY KONTAKTOWE -->
  <div class="contact-cards">
    <div class="contact-card">
      <div class="contact-card-icon">✉</div>
      <div class="contact-card-tag">/// Masz pytanie?</div>
      <h3 class="contact-card-title">Kontakt ogólny</h3>
      <p class="contact-card-desc">Pytania, pomoc, sugestie, zgłoszenia — pisz śmiało.</p>
      <a href="mailto:kontakt@grailos.pl" class="contact-card-mail">kontakt@grailos.pl</a>
    </div>
    <div class="contact-card contact-card--accent">
      <div class="contact-card-icon">🤝</div>
      <div class="contact-card-tag">/// Dla marek</div>
      <h3 class="contact-card-title">Współpraca</h3>
      <p class="contact-card-desc">Chcesz podjąć współpracę z GrailOS? Odezwij się do nas.</p>
      <a href="mailto:wspolpraca@grailos.pl" class="contact-card-mail">wspolpraca@grailos.pl</a>
    </div>
  </div>

  <!-- FORMULARZ -->
  <div class="contact-form-card">
    <div class="contact-card-tag">/// Wyślij wiadomość</div>
    <?php if ($error): ?><div class="form-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="form-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" class="admin-form" style="margin-top:18px;">
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
      <button type="submit" class="btn" style="width:100%;">Wyślij →</button>
    </form>
  </div>

  <!-- SOCIALS -->
  <div class="contact-socials-card">
    <div class="contact-card-tag">/// Znajdź nas</div>
    <div class="contact-socials-row">
      <a href="https://www.instagram.com/grailos.pl/" target="_blank" rel="noopener" class="contact-social">Instagram</a>
      <a href="https://www.tiktok.com/@grailos" target="_blank" rel="noopener" class="contact-social">TikTok</a>
      <a href="https://discord.com/" target="_blank" rel="noopener" class="contact-social">Discord</a>
    </div>
  </div>

</div>

<?php require_once 'includes/footer.php'; ?>