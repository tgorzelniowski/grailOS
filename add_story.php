<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}

$error = '';
$ajax  = !empty($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (empty($_FILES['image']['name'])) {
        $error = 'Zdjęcie jest wymagane.';
    } else {
        $result = upload_image($_FILES['image'], 'stories');
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $pdo->prepare("INSERT INTO stories (user_id, image_url) VALUES (?, ?)")
                ->execute([$_SESSION['user_id'], $result['path']]);
            if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
            header('Location: /grailos/'); exit;
        }
    }
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $error]);
        exit;
    }
}

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Stories</div>
  <h1 class="page-title">DODAJ<br>STORKĘ</h1>
  <p class="page-sub">Znika po 24h. Widzą ją tylko Twoi obserwujący.</p>
</section>

<div class="admin-form-wrap" style="max-width:520px;">
  <?php if ($error): ?>
    <div class="form-error" style="margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?= csrf_field() ?>

  <!-- KROK 1: wybór zdjęcia -->
  <div id="storyPick" class="form-group">
    <label class="form-label">Zdjęcie (JPG, PNG, WebP — max 5MB)</label>
    <input type="file" id="storyFile" class="form-input form-file"
           accept="image/jpeg,image/png,image/webp" onchange="loadStoryImage(this)">
  </div>

  <!-- KROK 2: edytor -->
  <div id="storyEditor" style="display:none;">
    <div class="story-edit-stage">
      <canvas id="storyCanvas" width="540" height="960"></canvas>
    </div>

    <!-- ZAKŁADKI NARZĘDZI -->
    <div class="story-edit-tabs">
      <button type="button" class="story-edit-tab active" onclick="storyTab('filters', this)">Filtry</button>
      <button type="button" class="story-edit-tab" onclick="storyTab('text', this)">Tekst</button>
      <button type="button" class="story-edit-tab" onclick="storyTab('stickers', this)">Naklejki</button>
    </div>

    <!-- FILTRY -->
    <div class="story-tool" id="tool-filters">
      <div class="story-filter-row">
        <button type="button" class="story-filter-btn active" onclick="setStoryFilter('none', this)">Brak</button>
        <button type="button" class="story-filter-btn" onclick="setStoryFilter('grayscale(1)', this)">B&amp;W</button>
        <button type="button" class="story-filter-btn" onclick="setStoryFilter('sepia(0.8)', this)">Sepia</button>
        <button type="button" class="story-filter-btn" onclick="setStoryFilter('saturate(1.8) contrast(1.1)', this)">Vivid</button>
        <button type="button" class="story-filter-btn" onclick="setStoryFilter('saturate(1.2) hue-rotate(190deg) brightness(1.05)', this)">Chłodny</button>
        <button type="button" class="story-filter-btn" onclick="setStoryFilter('sepia(0.4) saturate(1.4) brightness(1.05)', this)">Ciepły</button>
      </div>
    </div>

    <!-- TEKST -->
    <div class="story-tool" id="tool-text" style="display:none;">
      <div class="story-text-controls">
        <input type="text" id="storyTextInput" class="form-input" placeholder="Wpisz tekst..." maxlength="60">
        <input type="color" id="storyTextColor" value="#ffffff" class="story-color">
        <button type="button" class="btn" style="font-size:11px;padding:8px 14px;" onclick="addStoryText()">+ Dodaj</button>
      </div>
    </div>

    <!-- NAKLEJKI -->
    <div class="story-tool" id="tool-stickers" style="display:none;">
      <div class="story-sticker-row" id="stickerRow"></div>
    </div>

    <!-- AKCJE -->
    <div class="story-edit-actions">
      <button type="button" class="btn btn-outline" style="font-size:11px;padding:8px 14px;"
              onclick="deleteSelected()">Usuń zaznaczony</button>
      <div style="margin-left:auto; display:flex; gap:10px;">
        <button type="button" class="btn btn-outline" onclick="resetStory()">← Zmień zdjęcie</button>
        <button type="button" class="btn" id="storyPublishBtn" onclick="publishStory()">Opublikuj storkę →</button>
      </div>
    </div>
    <div id="storyEditError" class="form-error" style="display:none; margin-top:12px;"></div>
  </div>
</div>

<script>
const canvas = document.getElementById('storyCanvas');
const ctx = canvas.getContext('2d');
let baseImg = null;
let curFilter = 'none';
let objects = [];      // {type:'text'|'sticker', text, x, y, size, color}
let selected = -1;
let drag = null;

const STICKERS = ['🔥','💯','⭐','😎','👟','🧢','💸','❤️','✨','👑','🤙','😤','🛒','📸','🌀','⚡'];

// Wypełnij paletę naklejek
const sr = document.getElementById('stickerRow');
STICKERS.forEach(em => {
  const b = document.createElement('button');
  b.type = 'button'; b.className = 'story-sticker'; b.textContent = em;
  b.onclick = () => addSticker(em);
  sr.appendChild(b);
});

function loadStoryImage(input) {
  if (!input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    const im = new Image();
    im.onload = () => {
      baseImg = im;
      objects = []; selected = -1; curFilter = 'none';
      document.getElementById('storyPick').style.display = 'none';
      document.getElementById('storyEditor').style.display = 'block';
      render();
    };
    im.src = e.target.result;
  };
  r.readAsDataURL(input.files[0]);
}

function coverDraw(img) {
  const cw = canvas.width, ch = canvas.height;
  const ir = img.width / img.height, cr = cw / ch;
  let w, h, x, y;
  if (ir > cr) { h = ch; w = ch * ir; x = (cw - w) / 2; y = 0; }
  else { w = cw; h = cw / ir; x = 0; y = (ch - h) / 2; }
  ctx.drawImage(img, x, y, w, h);
}

function render(showSelection = true) {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  if (!baseImg) return;
  ctx.filter = curFilter;
  coverDraw(baseImg);
  ctx.filter = 'none';

  objects.forEach((o, idx) => {
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    if (o.type === 'text') {
      ctx.font = `700 ${o.size}px 'Barlow Condensed', sans-serif`;
      ctx.lineWidth = Math.max(2, o.size / 12);
      ctx.strokeStyle = 'rgba(0,0,0,0.55)';
      ctx.strokeText(o.text, o.x, o.y);
      ctx.fillStyle = o.color;
      ctx.fillText(o.text, o.x, o.y);
    } else {
      ctx.font = `${o.size}px serif`;
      ctx.fillText(o.text, o.x, o.y);
    }
    const m = ctx.measureText(o.text);
    o._w = m.width; o._h = o.size;
    if (showSelection && idx === selected) {
      ctx.strokeStyle = '#FF2400';
      ctx.lineWidth = 2;
      ctx.strokeRect(o.x - o._w/2 - 8, o.y - o._h/2 - 6, o._w + 16, o._h + 12);
    }
  });
}

function evtPos(e) {
  const rect = canvas.getBoundingClientRect();
  const cx = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
  const cy = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
  return { x: cx * canvas.width / rect.width, y: cy * canvas.height / rect.height };
}

function hitTest(x, y) {
  for (let i = objects.length - 1; i >= 0; i--) {
    const o = objects[i];
    if (x >= o.x - o._w/2 - 8 && x <= o.x + o._w/2 + 8 &&
        y >= o.y - o._h/2 - 6 && y <= o.y + o._h/2 + 6) return i;
  }
  return -1;
}

function pointerDown(e) {
  const p = evtPos(e);
  const hit = hitTest(p.x, p.y);
  selected = hit;
  if (hit >= 0) { drag = { dx: objects[hit].x - p.x, dy: objects[hit].y - p.y }; e.preventDefault(); }
  render();
}
function pointerMove(e) {
  if (drag === null || selected < 0) return;
  const p = evtPos(e);
  objects[selected].x = p.x + drag.dx;
  objects[selected].y = p.y + drag.dy;
  e.preventDefault();
  render();
}
function pointerUp() { drag = null; }

canvas.addEventListener('mousedown', pointerDown);
canvas.addEventListener('mousemove', pointerMove);
window.addEventListener('mouseup', pointerUp);
canvas.addEventListener('touchstart', pointerDown, {passive:false});
canvas.addEventListener('touchmove', pointerMove, {passive:false});
window.addEventListener('touchend', pointerUp);

function addStoryText() {
  const t = document.getElementById('storyTextInput').value.trim();
  if (!t) return;
  objects.push({ type:'text', text:t, x:canvas.width/2, y:canvas.height/2,
                 size:54, color:document.getElementById('storyTextColor').value });
  selected = objects.length - 1;
  document.getElementById('storyTextInput').value = '';
  render();
}

function addSticker(em) {
  objects.push({ type:'sticker', text:em, x:canvas.width/2, y:canvas.height/2, size:90, color:'#fff' });
  selected = objects.length - 1;
  render();
}

function deleteSelected() {
  if (selected >= 0) { objects.splice(selected, 1); selected = -1; render(); }
}

function setStoryFilter(f, btn) {
  curFilter = f;
  document.querySelectorAll('.story-filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  render();
}

function storyTab(name, btn) {
  document.querySelectorAll('.story-tool').forEach(t => t.style.display = 'none');
  document.getElementById('tool-' + name).style.display = 'block';
  document.querySelectorAll('.story-edit-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
}

function resetStory() {
  document.getElementById('storyEditor').style.display = 'none';
  document.getElementById('storyPick').style.display = 'block';
  document.getElementById('storyFile').value = '';
  baseImg = null;
}

function publishStory() {
  if (!baseImg) return;
  selected = -1;
  render(false); // bez ramki zaznaczenia
  const btn = document.getElementById('storyPublishBtn');
  btn.disabled = true; btn.textContent = 'Wysyłanie...';
  canvas.toBlob(async (blob) => {
    const fd = new FormData();
    fd.append('image', blob, 'story.jpg');
    fd.append('csrf_token', '<?= csrf_token() ?>');
    fd.append('ajax', '1');
    try {
      const res = await fetch('/grailos/add_story.php', { method:'POST', body: fd });
      const data = await res.json();
      if (data.ok) { window.location.href = '/grailos/'; return; }
      throw new Error(data.error || 'Błąd wysyłki.');
    } catch (err) {
      const e = document.getElementById('storyEditError');
      e.style.display = 'block'; e.textContent = err.message;
      btn.disabled = false; btn.textContent = 'Opublikuj storkę →';
    }
  }, 'image/jpeg', 0.9);
}
</script>

<?php require_once 'includes/footer.php'; ?>
