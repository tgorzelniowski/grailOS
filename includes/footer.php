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
  <p class="footer-copy">© 2025 GrailOS. All rights reserved.</p>
</footer>

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