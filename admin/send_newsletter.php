<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/mailer.php';

$sent    = 0;
$error   = 0;
$msg     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$subject || !$content) {
        $msg = 'error:Wypełnij temat i treść wiadomości.';
    } else {
        $subs = $pdo->query("
            SELECT email, name, unsubscribe_token
            FROM newsletter_subscribers
            WHERE confirmed = 1
        ")->fetchAll();

        foreach ($subs as $sub) {
            $unsub_url = MAIL_SITE_URL . '/newsletter_unsubscribe.php?token=' . $sub['unsubscribe_token'];
            $greeting  = $sub['name'] ? 'Cześć ' . htmlspecialchars($sub['name']) . '!' : 'Cześć!';
            $body_html = nl2br(htmlspecialchars($content));

            $body = "<p style='color:#444;font-size:15px;line-height:1.7;'>$greeting</p>
                <div style='color:#333;font-size:15px;line-height:1.8;margin-top:16px;'>$body_html</div>
                <p style='font-size:12px;color:#aaa;margin-top:40px;border-top:1px solid #eee;padding-top:16px;'>
                  Nie chcesz już otrzymywać newslettera?
                  <a href='$unsub_url' style='color:#FF2400;'>Wypisz się</a>
                </p>";

            $html = mail_template(htmlspecialchars($subject), $body);

            if (send_mail($sub['email'], $sub['name'] ?? 'Subskrybent', $subject, $html)) {
                $sent++;
            } else {
                $error++;
            }
        }

        $msg = "ok:Wysłano do $sent subskrybentów. Błędy: $error.";
    }
}

$total_subs = $pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE confirmed = 1")->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin</div>
  <h1 class="page-title">NEWSLETTER</h1>
  <p class="page-sub">Aktywnych subskrybentów: <?= (int)$total_subs ?></p>
</section>

<?php if ($msg): ?>
  <?php [$type, $text] = explode(':', $msg, 2); ?>
  <div class="<?= $type === 'ok' ? 'form-success' : 'form-error' ?>"
       style="margin:24px 40px;">
    <?= htmlspecialchars($text) ?>
  </div>
<?php endif; ?>

<div class="admin-form-wrap">
  <form method="POST" class="admin-form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Temat wiadomości</label>
      <input type="text" name="subject" class="form-input"
             value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
             placeholder="np. Nowe dropy tego tygodnia" maxlength="200">
    </div>
    <div class="form-group">
      <label class="form-label">Treść (plain text, zostanie sformatowany)</label>
      <textarea name="content" class="form-input form-textarea"
                rows="10" maxlength="5000"
                placeholder="Treść newslettera..."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
    </div>
    <div style="display:flex;gap:12px;align-items:center;">
      <button type="button" class="btn"
              onclick="grailConfirm('Wysłać newsletter do <?= (int)$total_subs ?> subskrybentów?', () => this.closest('form').submit())">
        Wyślij newsletter →
      </button>
      <a href="/admin/" class="btn btn-outline">← Admin</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>