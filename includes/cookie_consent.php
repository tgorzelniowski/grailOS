<?php
function cookie_consent_level(): string { return $_COOKIE['grailos_consent'] ?? ''; }
function cookies_all_accepted(): bool   { return cookie_consent_level() === 'all'; }
function cookies_decided(): bool        { return cookie_consent_level() !== ''; }
?>
<?php if (!cookies_decided()): ?>

<!-- COOKIE CONSENT MODAL -->
<div class="cookie-overlay" id="cookieOverlay" role="dialog"
     aria-modal="true" aria-label="Zgoda na pliki cookie">

  <div class="cookie-modal">

    <div class="cookie-modal-header">
      <div class="cookie-modal-logo">GRAIL<span>OS</span></div>
      <div class="cookie-modal-tag">/// Pliki cookie</div>
    </div>

    <h2 class="cookie-modal-title">Szanujemy<br>Twoją prywatność</h2>

    <p class="cookie-modal-text">
      Używamy plików cookie, aby strona działała poprawnie i byśmy mogli
      analizować sposób korzystania z serwisu.
    </p>

    <div class="cookie-types">
      <div class="cookie-type">
        <div class="cookie-type-header">
          <span class="cookie-type-name">Niezbędne</span>
          <span class="cookie-type-badge cookie-type-required">Zawsze aktywne</span>
        </div>
        <p class="cookie-type-desc">
          Sesja, bezpieczeństwo (CSRF), preferencje strony.
          Bez nich serwis nie działa.
        </p>
      </div>
      <div class="cookie-type">
        <div class="cookie-type-header">
          <span class="cookie-type-name">Analityczne</span>
          <span class="cookie-type-badge cookie-type-optional">Opcjonalne</span>
        </div>
        <p class="cookie-type-desc">
          Informacje o urządzeniu, przeglądarce i strefie czasowej —
          pomagają nam ulepszać serwis.
        </p>
      </div>
    </div>

    <div class="cookie-modal-actions">
      <button class="btn btn-outline" onclick="cookieChoose('necessary')">
        Tylko niezbędne
      </button>
      <button class="btn" onclick="cookieChoose('all')">
        Akceptuj wszystkie →
      </button>
    </div>

    <p class="cookie-modal-policy">
      <a href="/privacy.php">Polityka prywatności</a>
    </p>

  </div>
</div>

<script>
(function() {
  const CSRF = <?= json_encode(csrf_token()) ?>;

  // Zablokuj scroll body gdy modal widoczny
  document.body.style.overflow = 'hidden';

  window.cookieChoose = function(action) {
    // Consent Mode: zaktualizuj zgodę natychmiast po decyzji (bez przeładowania)
    if (typeof gtag === 'function') {
      var v = action === 'all' ? 'granted' : 'denied';
      gtag('consent', 'update', {
        'ad_storage': v,
        'ad_user_data': v,
        'ad_personalization': v,
        'analytics_storage': v,
        'functionality_storage': v,
        'personalization_storage': v
      });
    }

    document.body.style.overflow = '';
    const overlay = document.getElementById('cookieOverlay');
    if (overlay) {
      overlay.style.opacity   = '0';
      overlay.style.transform = 'scale(1.04)';
      setTimeout(() => overlay.remove(), 350);
    }

    const body = new URLSearchParams({
      csrf_token : CSRF,
      action,
      screen : (screen.width || '') + 'x' + (screen.height || ''),
      tz     : Intl.DateTimeFormat().resolvedOptions().timeZone || '',
    });

    fetch('/cookie_accept.php', {
      method  : 'POST',
      headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
      body    : body.toString(),
    }).catch(() => {});
  };
})();
</script>

<?php endif; ?>