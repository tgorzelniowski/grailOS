<?php
// Pomocnicze funkcje dla cookie consent
function cookie_consent_level(): string {
    return $_COOKIE['grailos_consent'] ?? '';
}
function cookies_all_accepted(): bool {
    return cookie_consent_level() === 'all';
}
function cookies_decided(): bool {
    return cookie_consent_level() !== '';
}
?>
<?php if (!cookies_decided()): ?>
<div class="cookie-banner" id="cookieBanner" role="dialog"
     aria-label="Zgoda na pliki cookie" aria-live="polite">

  <div class="cookie-banner-body">
    <div class="cookie-banner-tag">/// Pliki cookie</div>
    <p class="cookie-banner-text">
      Używamy plików cookie do zapewnienia działania strony (niezbędne)
      oraz do analizy urządzeń i preferencji użytkowników (analityczne).
      Przeczytaj naszą <a href="/grailos/privacy.php">Politykę prywatności</a>.
    </p>
  </div>

  <div class="cookie-banner-actions">
    <button class="btn btn-outline cookie-btn-necessary"
            onclick="cookieChoose('necessary')">
      Tylko niezbędne
    </button>
    <button class="btn cookie-btn-all"
            onclick="cookieChoose('all')">
      Akceptuj wszystkie →
    </button>
  </div>

</div>

<script>
(function() {
  const CSRF = <?= json_encode(csrf_token()) ?>;

  window.cookieChoose = function(action) {
    const banner = document.getElementById('cookieBanner');
    if (banner) {
      banner.style.transform = 'translateY(100%)';
      banner.style.opacity   = '0';
    }

    const body = new URLSearchParams({
      csrf_token : CSRF,
      action     : action,
      screen     : (screen.width || '') + 'x' + (screen.height || ''),
      tz         : Intl.DateTimeFormat().resolvedOptions().timeZone || '',
    });

    fetch('/grailos/cookie_accept.php', {
      method  : 'POST',
      headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
      body    : body.toString(),
    }).catch(() => {});
  };
})();
</script>
<?php endif; ?>