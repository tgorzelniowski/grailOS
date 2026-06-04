<footer class="footer">
  <div class="footer-left">
    <div class="footer-logo">GRAIL<span>OS</span></div>
    <p class="footer-tagline">The operating system for your wardrobe.</p>
  </div>

  <!-- NEWSLETTER -->
  <div class="footer-newsletter">
    <div class="footer-newsletter-label">/// Newsletter</div>
    <p class="footer-newsletter-sub">Dropy, nowości, exkluzywy — prosto na maila.</p>
    <form class="footer-newsletter-form" id="newsletterForm" onsubmit="newsletterSubmit(event)">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="footer-newsletter-row">
        <input type="email" name="email" id="nlEmail"
               class="footer-newsletter-input"
               placeholder="twoj@email.com" required>
        <button type="submit" class="btn footer-newsletter-btn">OK →</button>
      </div>
      <div class="footer-newsletter-msg" id="nlMsg"></div>
    </form>
  </div>

  <div class="footer-links">
    <a href="/grailos/about.php">About</a>
    <a href="/grailos/contact.php">Contact</a>
    <a href="/grailos/privacy.php">Polityka prywatności</a>
    <a href="/grailos/terms.php">Regulamin</a>
  </div>
  <p class="footer-copy">© 2026 GrailOS. All rights reserved.</p>
</footer>

<!-- CUSTOM CONFIRM MODAL -->
<div id="grailConfirmOverlay">
  <div id="grailConfirmBox">
    <p id="grailConfirmMsg"></p>
    <div class="grail-confirm-btns">
      <button id="grailConfirmYes" class="btn">Usuń</button>
      <button class="btn btn-outline" onclick="grailConfirmClose()">Anuluj</button>
    </div>
  </div>
</div>

<style>
#grailConfirmOverlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.78);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(3px);
}
#grailConfirmOverlay.open { display: flex; }
#grailConfirmBox {
  background: #111;
  border: 1px solid #2a2a2a;
  padding: 36px 40px;
  min-width: 280px;
  max-width: 360px;
  width: 90%;
  text-align: center;
}
#grailConfirmMsg {
  font-size: 14px;
  letter-spacing: 1px;
  color: #fff;
  margin-bottom: 28px;
  line-height: 1.6;
  text-transform: uppercase;
}
.grail-confirm-btns {
  display: flex;
  gap: 12px;
  justify-content: center;
}
.grail-confirm-btns .btn { min-width: 90px; }
</style>

<script>
let _grailCb = null;

function grailConfirm(msg, onYes) {
  _grailCb = onYes;
  document.getElementById('grailConfirmMsg').textContent = msg;
  document.getElementById('grailConfirmOverlay').classList.add('open');
  document.getElementById('grailConfirmYes').focus();
}

function grailConfirmClose() {
  document.getElementById('grailConfirmOverlay').classList.remove('open');
  _grailCb = null;
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('grailConfirmYes').addEventListener('click', () => {
    const cb = _grailCb;
    grailConfirmClose();
    if (cb) cb();
  });
  document.getElementById('grailConfirmOverlay').addEventListener('click', e => {
    if (e.target.id === 'grailConfirmOverlay') grailConfirmClose();
  });
});
</script>

<script>
async function newsletterSubmit(e) {
  e.preventDefault();
  const form  = e.target;
  const msg   = document.getElementById('nlMsg');
  const email = document.getElementById('nlEmail').value.trim();
  msg.textContent = '';

  const body = new URLSearchParams({
    csrf_token : form.querySelector('[name=csrf_token]').value,
    email,
  });

  try {
    const res  = await fetch('/grailos/newsletter_subscribe.php', {
      method  : 'POST',
      headers : { 'Content-Type': 'application/x-www-form-urlencoded' },
      body    : body.toString(),
    });
    const data = await res.json();
    msg.textContent = data.msg || data.error || 'Coś poszło nie tak.';
    msg.style.color = data.ok ? '#2ecc71' : 'var(--red)';
    if (data.ok) form.reset();
  } catch {
    msg.textContent = 'Błąd połączenia.';
    msg.style.color = 'var(--red)';
  }
}
</script>

</body>
</html>