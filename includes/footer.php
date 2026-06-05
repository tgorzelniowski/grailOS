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

<!-- REPORT MODAL -->
<?php if (isset($_SESSION['user_id'])): ?>
<div id="grailReportOverlay">
  <div id="grailReportBox">
    <div class="grail-report-head">
      <span class="grail-report-title">/// Zgłoś treść</span>
      <button class="grail-report-x" onclick="grailReportClose()">✕</button>
    </div>
    <p class="grail-report-sub" id="grailReportSub"></p>
    <div class="form-group">
      <label class="form-label">Powód</label>
      <select id="grailReportReason" class="form-input">
        <option value="Spam">Spam</option>
        <option value="Obraźliwe / nękanie">Obraźliwe / nękanie</option>
        <option value="Treści niecenzuralne / NSFW">Treści niecenzuralne / NSFW</option>
        <option value="Mowa nienawiści">Mowa nienawiści</option>
        <option value="Nieprawdziwe informacje">Nieprawdziwe informacje</option>
        <option value="Podszywanie się">Podszywanie się</option>
        <option value="Inne">Inne</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Szczegóły (opcjonalnie)</label>
      <textarea id="grailReportDetails" class="form-input form-textarea" rows="3" maxlength="1000"
                placeholder="Opisz problem..."></textarea>
    </div>
    <div id="grailReportMsg" class="grail-report-msg"></div>
    <div class="grail-confirm-btns" style="justify-content:flex-end;">
      <button class="btn btn-outline" onclick="grailReportClose()">Anuluj</button>
      <button class="btn" id="grailReportSend" onclick="grailReportSend()">Wyślij zgłoszenie →</button>
    </div>
  </div>
</div>

<style>
#grailReportOverlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.78); z-index: 9999;
  align-items: center; justify-content: center;
  backdrop-filter: blur(3px); padding: 20px;
}
#grailReportOverlay.open { display: flex; }
#grailReportBox {
  background: #111; border: 1px solid #2a2a2a;
  padding: 28px 32px; max-width: 440px; width: 100%;
}
.grail-report-head {
  display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;
}
.grail-report-title {
  font-size: 11px; letter-spacing: 4px; text-transform: uppercase; color: var(--red);
}
.grail-report-x {
  background: transparent; border: none; color: var(--gray); font-size: 16px; cursor: pointer;
}
.grail-report-x:hover { color: var(--white); }
.grail-report-sub {
  font-size: 12px; color: var(--gray); letter-spacing: 1px; margin: 0 0 18px;
}
.grail-report-msg {
  font-size: 12px; letter-spacing: 1px; min-height: 16px; margin-bottom: 10px;
}
.report-btn {
  background: transparent; border: none; color: #555;
  font-family: 'Barlow Condensed', sans-serif; font-size: 11px; letter-spacing: 2px;
  text-transform: uppercase; cursor: pointer; padding: 4px 8px; transition: color 0.2s;
}
.report-btn:hover { color: var(--red); }
</style>

<script>
let _reportType = '', _reportId = 0;

function grailReport(type, id, label) {
  _reportType = type; _reportId = id;
  document.getElementById('grailReportSub').textContent = label || ('Zgłaszasz: ' + type + ' #' + id);
  document.getElementById('grailReportMsg').textContent = '';
  document.getElementById('grailReportDetails').value = '';
  document.getElementById('grailReportReason').selectedIndex = 0;
  const send = document.getElementById('grailReportSend');
  send.disabled = false; send.textContent = 'Wyślij zgłoszenie →';
  document.getElementById('grailReportOverlay').classList.add('open');
}
function grailReportClose() {
  document.getElementById('grailReportOverlay').classList.remove('open');
}
async function grailReportSend() {
  const send = document.getElementById('grailReportSend');
  const msg  = document.getElementById('grailReportMsg');
  send.disabled = true; send.textContent = 'Wysyłanie...';
  const body = new URLSearchParams({
    csrf_token: '<?= csrf_token() ?>',
    content_type: _reportType,
    content_id: _reportId,
    reason: document.getElementById('grailReportReason').value,
    details: document.getElementById('grailReportDetails').value,
  });
  try {
    const res = await fetch('/grailos/report_submit.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: body.toString(),
    });
    const data = await res.json();
    if (data.ok) {
      msg.style.color = '#2ecc71';
      msg.textContent = data.already ? 'Już zgłoszono — dziękujemy.' : 'Zgłoszenie wysłane. Dziękujemy!';
      setTimeout(grailReportClose, 1200);
    } else {
      msg.style.color = 'var(--red)';
      msg.textContent = data.error || 'Błąd.';
      send.disabled = false; send.textContent = 'Wyślij zgłoszenie →';
    }
  } catch (e) {
    msg.style.color = 'var(--red)'; msg.textContent = 'Błąd połączenia.';
    send.disabled = false; send.textContent = 'Wyślij zgłoszenie →';
  }
}
document.addEventListener('DOMContentLoaded', () => {
  const ov = document.getElementById('grailReportOverlay');
  if (ov) ov.addEventListener('click', e => { if (e.target.id === 'grailReportOverlay') grailReportClose(); });
});
</script>
<?php endif; ?>

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