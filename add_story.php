<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php'); exit;
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
            header('Location: /'); exit;
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

<div class="admin-form-wrap" style="max-width:540px;">
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

    <!-- ROZMIAR / OBRÓT zaznaczonego -->
    <div class="story-size-bar">
      <span class="story-size-label">Zaznaczony:</span>
      <button type="button" class="story-mini-btn" onclick="scaleSelected(0.85)">A−</button>
      <button type="button" class="story-mini-btn" onclick="scaleSelected(1.18)">A+</button>
      <button type="button" class="story-mini-btn" onclick="rotateSelected(-15)">⟲</button>
      <button type="button" class="story-mini-btn" onclick="rotateSelected(15)">⟳</button>
      <button type="button" class="story-mini-btn story-mini-del" onclick="deleteSelected()">✕</button>
    </div>

    <!-- ZAKŁADKI NARZĘDZI -->
    <div class="story-edit-tabs">
      <button type="button" class="story-edit-tab active" onclick="storyTab('image', this)">Zdjęcie</button>
      <button type="button" class="story-edit-tab" onclick="storyTab('filters', this)">Filtry</button>
      <button type="button" class="story-edit-tab" onclick="storyTab('text', this)">Tekst</button>
      <button type="button" class="story-edit-tab" onclick="storyTab('badges', this)">Naklejki</button>
    </div>

    <!-- ZDJĘCIE: skalowanie / dopasowanie -->
    <div class="story-tool" id="tool-image">
      <div class="story-filter-row">
        <button type="button" class="story-filter-btn active" id="modeCover" onclick="setBgMode('cover', this)">Wypełnij</button>
        <button type="button" class="story-filter-btn" id="modeContain" onclick="setBgMode('contain', this)">Dopasuj (całe)</button>
        <button type="button" class="story-filter-btn" onclick="resetBg()">Reset</button>
      </div>
      <div class="story-text-extra">
        <span class="story-size-label">Zoom:</span>
        <input type="range" id="bgZoom" min="0.4" max="3" step="0.05" value="1"
               oninput="setBgZoom(this.value)" style="flex:1;">
      </div>
      <div class="story-size-label" style="margin-top:8px; text-transform:none; letter-spacing:0.5px;">
        Tryb „Dopasuj" pokaże całe zdjęcie bez ucinania. Przeciągnij zdjęcie po kanwie, aby je przesunąć.
      </div>
    </div>

    <!-- FILTRY -->
    <div class="story-tool" id="tool-filters" style="display:none;">
      <div class="story-filter-row" id="filterRow"></div>
    </div>

    <!-- TEKST -->
    <div class="story-tool" id="tool-text" style="display:none;">
      <div class="story-text-controls">
        <input type="text" id="storyTextInput" class="form-input" placeholder="Wpisz tekst..." maxlength="60">
        <input type="color" id="storyTextColor" value="#ffffff" class="story-color" title="Kolor tekstu">
        <button type="button" class="btn" style="font-size:11px;padding:8px 14px;" onclick="addStoryText()">+ Dodaj</button>
      </div>
      <div class="story-text-extra">
        <label class="story-bg-toggle"><input type="checkbox" id="storyTextBg"> Tło</label>
        <input type="color" id="storyTextBgColor" value="#FF2400" class="story-color" title="Kolor tła">
        <span class="story-size-label">Czcionka:</span>
      </div>
      <div class="story-font-row" id="fontRow"></div>
    </div>

    <!-- NAKLEJKI -->
    <div class="story-tool" id="tool-badges" style="display:none;">
      <div class="story-badge-row" id="badgeRow"></div>
    </div>

    <!-- AKCJE -->
    <div class="story-edit-actions">
      <button type="button" class="btn btn-outline" onclick="resetStory()">← Zmień zdjęcie</button>
      <button type="button" class="btn" id="storyPublishBtn" style="margin-left:auto;"
              onclick="publishStory()">Opublikuj storkę →</button>
    </div>
    <div id="storyEditError" class="form-error" style="display:none; margin-top:12px;"></div>
  </div>
</div>

<script>
const canvas = document.getElementById('storyCanvas');
const ctx = canvas.getContext('2d');
let baseImg = null;
let curFilter = 'none';
let objects = [];   // {type:'text'|'badge', text, x, y, size, color, font, rot, bg}
let selected = -1;
let drag = null;     // przeciąganie obiektu
let bgDrag = null;   // przeciąganie tła

// Skala/pozycja zdjęcia bazowego
let bgMode = 'cover';   // 'cover' | 'contain'
let bgScale = 1;
let bgOffsetX = 0, bgOffsetY = 0;

// --- Filtry ---
const FILTERS = [
  ['Brak','none'],['B&W','grayscale(1)'],['Sepia','sepia(0.8)'],
  ['Vivid','saturate(1.8) contrast(1.1)'],['Noir','grayscale(1) contrast(1.4) brightness(0.9)'],
  ['Fade','contrast(0.85) brightness(1.1) saturate(0.8)'],
  ['Chłodny','saturate(1.2) hue-rotate(190deg) brightness(1.05)'],
  ['Ciepły','sepia(0.4) saturate(1.4) brightness(1.05)'],
  ['Retro','sepia(0.5) contrast(1.2) saturate(1.3) hue-rotate(-10deg)'],
  ['Crisp','contrast(1.25) saturate(1.1) brightness(1.03)'],
  ['Dream','brightness(1.12) saturate(1.25) blur(0.4px)'],['Invert','invert(1)'],
  ['Mglisty','blur(1.2px) brightness(1.05)'],['Wyblakły','grayscale(0.4) brightness(1.1) contrast(0.9)'],
  ['Kontrast','contrast(1.55)'],['Ciemny','brightness(0.72) contrast(1.12)'],['Jasny','brightness(1.28)'],
  ['Cyber','saturate(2) hue-rotate(280deg) contrast(1.2)'],['Mint','hue-rotate(90deg) saturate(1.3)'],
  ['Gold','sepia(0.8) saturate(2) hue-rotate(-20deg) contrast(1.1)'],
  ['Lomo','saturate(1.6) contrast(1.3) brightness(0.95)'],['Frost','brightness(1.15) saturate(0.7) hue-rotate(170deg)'],
];

// --- Naklejki tekstowe (resellerskie / streetwear) ---
const RED='#FF2400', GREEN='#1a7d3c', BLACK='#0a0a0a';
const BADGES = [
  ['ASAP',BLACK],['BID',GREEN],['BIN',GREEN],['BUMP',BLACK],['CW',BLACK],['COP OR DROP?',RED],
  ['CMNDS',BLACK],['CMDNN',RED],['DS',RED],['DROP',RED],['FAKE',RED],['FIT',BLACK],['FLEX',RED],
  ['GRAIL',RED],['GRAILOS',RED],['GOAT',RED],['HEAT',RED],['HO',BLACK],['HMU',GREEN],['HYPE',RED],
  ['ID',BLACK],['KMWTW',BLACK],['LC',BLACK],['L',BLACK],['LPU',GREEN],['OG',BLACK],['PC',GREEN],
  ['PFA',GREEN],['PROXY',BLACK],['RESTOCK',GREEN],['RESELL',RED],['RETAIL',BLACK],['SCAM',RED],
  ['SCAMMER',RED],['SELL',RED],['STEAL',RED],['TTS',BLACK],['VNDS',BLACK],['W2C',GREEN],
  ['WTT',RED],['WTB',GREEN],['WTS',RED],
];

// --- Czcionki ---
const FONTS = [
  ["'Barlow Condensed', sans-serif",'Barlow'], ["'Bebas Neue', sans-serif",'Bebas'],
  ["Impact, sans-serif",'Impact'], ["'Arial Black', sans-serif",'Heavy'],
  ["Arial, sans-serif",'Arial'], ["Verdana, sans-serif",'Verdana'],
  ["Tahoma, sans-serif",'Tahoma'], ["'Trebuchet MS', sans-serif",'Trebu'],
  ["Georgia, serif",'Georgia'], ["'Times New Roman', serif",'Times'],
  ["'Palatino Linotype', serif",'Palatino'], ["Garamond, serif",'Garamond'],
  ["'Courier New', monospace",'Courier'], ["'Lucida Console', monospace",'Lucida'],
  ["'Comic Sans MS', cursive",'Comic'], ["'Brush Script MT', cursive",'Script'],
];
let curFont = FONTS[0][0];

// Render palet
const fr = document.getElementById('filterRow');
FILTERS.forEach((f, i) => {
  const b = document.createElement('button');
  b.type='button'; b.className='story-filter-btn'+(i===0?' active':''); b.textContent=f[0];
  b.onclick = () => setStoryFilter(f[1], b);
  fr.appendChild(b);
});

const br = document.getElementById('badgeRow');
BADGES.forEach(([txt,col]) => {
  const b = document.createElement('button');
  b.type='button'; b.className='story-badge-pick'; b.textContent=txt;
  b.style.background = col;
  b.onclick = () => addBadge(txt, col);
  br.appendChild(b);
});

const fontRow = document.getElementById('fontRow');
FONTS.forEach((f, i) => {
  const b = document.createElement('button');
  b.type='button'; b.className='story-font-btn'+(i===0?' active':''); b.textContent=f[1];
  b.style.fontFamily = f[0];
  b.onclick = () => {
    curFont = f[0];
    document.querySelectorAll('.story-font-btn').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    if (selected>=0 && objects[selected].type==='text'){ objects[selected].font=f[0]; render(); }
  };
  fontRow.appendChild(b);
});

function loadStoryImage(input) {
  if (!input.files[0]) return;
  const r = new FileReader();
  r.onload = e => {
    const im = new Image();
    im.onload = () => {
      baseImg = im;
      objects = []; selected = -1; curFilter = 'none';
      bgMode = 'cover'; bgScale = 1; bgOffsetX = 0; bgOffsetY = 0;
      document.getElementById('bgZoom').value = 1;
      document.getElementById('storyPick').style.display = 'none';
      document.getElementById('storyEditor').style.display = 'block';
      render();
    };
    im.src = e.target.result;
  };
  r.readAsDataURL(input.files[0]);
}

function drawBase() {
  const cw = canvas.width, ch = canvas.height;
  const ir = baseImg.width / baseImg.height, cr = cw / ch;
  let w, h;
  if (bgMode === 'cover') {
    if (ir > cr) { h = ch; w = ch * ir; } else { w = cw; h = cw / ir; }
  } else { // contain — całe zdjęcie
    if (ir > cr) { w = cw; h = cw / ir; } else { h = ch; w = ch * ir; }
  }
  w *= bgScale; h *= bgScale;
  const x = (cw - w) / 2 + bgOffsetX, y = (ch - h) / 2 + bgOffsetY;
  ctx.fillStyle = '#0a0a0a';
  ctx.fillRect(0, 0, cw, ch);
  ctx.drawImage(baseImg, x, y, w, h);
}

function drawObj(o, idx, showSelection) {
  ctx.save();
  ctx.translate(o.x, o.y);
  if (o.rot) ctx.rotate(o.rot * Math.PI / 180);
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  if (o.type === 'text') {
    ctx.font = `700 ${o.size}px ${o.font}`;
    const m = ctx.measureText(o.text);
    o._w = m.width; o._h = o.size;
    if (o.bg) {
      const padX = o.size * 0.32, padY = o.size * 0.2;
      ctx.fillStyle = o.bg;
      ctx.fillRect(-o._w/2 - padX, -o._h/2 - padY, o._w + padX*2, o._h + padY*2);
      ctx.fillStyle = o.color;
      ctx.fillText(o.text, 0, 0);
    } else {
      ctx.lineWidth = Math.max(2, o.size / 12);
      ctx.strokeStyle = 'rgba(0,0,0,0.55)';
      ctx.strokeText(o.text, 0, 0);
      ctx.fillStyle = o.color;
      ctx.fillText(o.text, 0, 0);
    }
  } else { // badge
    ctx.font = `700 ${o.size}px 'Bebas Neue', sans-serif`;
    const m = ctx.measureText(o.text);
    const padX = o.size * 0.45, padY = o.size * 0.28;
    const w = m.width + padX * 2, h = o.size + padY * 2;
    o._w = w; o._h = h;
    ctx.fillStyle = o.color;
    ctx.fillRect(-w/2, -h/2, w, h);
    ctx.fillStyle = '#fff';
    ctx.fillText(o.text, 0, 0);
  }

  if (showSelection && idx === selected) {
    ctx.strokeStyle = '#FF2400';
    ctx.lineWidth = 2;
    ctx.strokeRect(-o._w/2 - 8, -o._h/2 - 6, o._w + 16, o._h + 12);
  }
  ctx.restore();
}

function render(showSelection = true) {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  if (!baseImg) return;
  ctx.filter = curFilter;
  drawBase();
  ctx.filter = 'none';
  objects.forEach((o, idx) => drawObj(o, idx, showSelection));
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
    const hw = (o._w || o.size) / 2 + 10, hh = (o._h || o.size) / 2 + 8;
    if (x >= o.x - hw && x <= o.x + hw && y >= o.y - hh && y <= o.y + hh) return i;
  }
  return -1;
}

function pointerDown(e) {
  const p = evtPos(e);
  const hit = hitTest(p.x, p.y);
  selected = hit;
  if (hit >= 0) {
    drag = { dx: objects[hit].x - p.x, dy: objects[hit].y - p.y };
  } else {
    bgDrag = { px: p.x, py: p.y, ox: bgOffsetX, oy: bgOffsetY };
  }
  e.preventDefault();
  render();
}
function pointerMove(e) {
  if (drag !== null && selected >= 0) {
    const p = evtPos(e);
    objects[selected].x = p.x + drag.dx;
    objects[selected].y = p.y + drag.dy;
    e.preventDefault(); render();
  } else if (bgDrag !== null) {
    const p = evtPos(e);
    bgOffsetX = bgDrag.ox + (p.x - bgDrag.px);
    bgOffsetY = bgDrag.oy + (p.y - bgDrag.py);
    e.preventDefault(); render();
  }
}
function pointerUp() { drag = null; bgDrag = null; }

// --- Gesty wielodotykowe: pinch-zoom + obrót ---
let gesture = null;
const tDist  = t => Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY);
const tAngle = t => Math.atan2(t[1].clientY - t[0].clientY, t[1].clientX - t[0].clientX) * 180 / Math.PI;

function onTouchStart(e) {
  if (e.touches.length === 2) {
    drag = null; bgDrag = null;
    gesture = { dist: tDist(e.touches), angle: tAngle(e.touches) };
    e.preventDefault();
  } else {
    pointerDown(e);
  }
}
function onTouchMove(e) {
  if (e.touches.length === 2 && gesture) {
    const d = tDist(e.touches), a = tAngle(e.touches);
    const ratio = gesture.dist ? d / gesture.dist : 1;
    const da = a - gesture.angle;
    if (selected >= 0) {
      objects[selected].size = Math.max(14, Math.min(420, objects[selected].size * ratio));
      objects[selected].rot  = (objects[selected].rot || 0) + da;
    } else {
      bgScale = Math.max(0.3, Math.min(5, bgScale * ratio));
      document.getElementById('bgZoom').value = Math.min(3, bgScale);
    }
    gesture.dist = d; gesture.angle = a;
    e.preventDefault(); render();
  } else if (e.touches.length === 1) {
    pointerMove(e);
  }
}
function onTouchEnd(e) { if (e.touches.length < 2) gesture = null; pointerUp(); }

canvas.addEventListener('mousedown', pointerDown);
canvas.addEventListener('mousemove', pointerMove);
window.addEventListener('mouseup', pointerUp);
canvas.addEventListener('touchstart', onTouchStart, {passive:false});
canvas.addEventListener('touchmove', onTouchMove, {passive:false});
window.addEventListener('touchend', onTouchEnd);

// --- Zdjęcie: tryb / zoom / reset ---
function setBgMode(mode, btn) {
  bgMode = mode;
  document.getElementById('modeCover').classList.toggle('active', mode==='cover');
  document.getElementById('modeContain').classList.toggle('active', mode==='contain');
  render();
}
function setBgZoom(v) { bgScale = parseFloat(v); render(); }
function resetBg() {
  bgScale = 1; bgOffsetX = 0; bgOffsetY = 0;
  document.getElementById('bgZoom').value = 1;
  render();
}

function addStoryText() {
  const t = document.getElementById('storyTextInput').value.trim();
  if (!t) return;
  const bg = document.getElementById('storyTextBg').checked
           ? document.getElementById('storyTextBgColor').value : null;
  objects.push({ type:'text', text:t, x:canvas.width/2, y:canvas.height/2,
                 size:54, color:document.getElementById('storyTextColor').value, font:curFont, rot:0, bg });
  selected = objects.length - 1;
  document.getElementById('storyTextInput').value = '';
  render();
}
function addBadge(txt, col) {
  objects.push({ type:'badge', text:txt, x:canvas.width/2, y:canvas.height/2, size:46, color:col, rot:0 });
  selected = objects.length - 1; render();
}
function deleteSelected() { if (selected >= 0) { objects.splice(selected,1); selected=-1; render(); } }
function scaleSelected(f) {
  if (selected < 0) return;
  objects[selected].size = Math.max(14, Math.min(420, objects[selected].size * f));
  render();
}
function rotateSelected(deg) {
  if (selected < 0) return;
  objects[selected].rot = (objects[selected].rot || 0) + deg; render();
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
  selected = -1; render(false);
  const btn = document.getElementById('storyPublishBtn');
  btn.disabled = true; btn.textContent = 'Wysyłanie...';
  canvas.toBlob(async (blob) => {
    const fd = new FormData();
    fd.append('image', blob, 'story.jpg');
    fd.append('csrf_token', '<?= csrf_token() ?>');
    fd.append('ajax', '1');
    try {
      const res = await fetch('/add_story.php', { method:'POST', body: fd });
      const data = await res.json();
      if (data.ok) { window.location.href = '/'; return; }
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
