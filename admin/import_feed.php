<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/catalog_import.php';

$FEED_DIR = __DIR__ . '/../assets/uploads/feeds/';
if (!is_dir($FEED_DIR)) mkdir($FEED_DIR, 0755, true);

// Nasze pola → kandydaci nazw w feedzie (auto-mapowanie)
$OUR_FIELDS = [
    'name'        => ['g:title','title','name','product_name','productname'],
    'brand'       => ['g:brand','brand','manufacturer'],
    'price'       => ['g:price','price','sale_price','g:sale_price','search_price','displayprice'],
    'image_url'   => ['g:image_link','image_link','image','image_url','imageurl','large_image','merchant_image_url','aw_image_url'],
    'images'      => ['g:additional_image_link','additional_image_link','additional_images','images','alternate_image'],
    'deeplink'    => ['g:link','link','url','product_url','aw_deep_link','deeplink','clickurl','merchant_deep_link'],
    'ean'         => ['g:gtin','gtin','ean','barcode'],
    'sku'         => ['g:mpn','mpn','sku','g:id','product_id','id'],
    'category'    => ['g:product_type','product_type','g:google_product_category','category','merchant_category'],
    'description' => ['g:description','description','desc','product_short_description'],
];
$FIELD_LABELS = [
    'name'=>'Nazwa *','brand'=>'Marka','price'=>'Cena','image_url'=>'Zdjęcie główne',
    'images'=>'Dodatkowe zdjęcia','deeplink'=>'Link partnerski (deeplink) *','ean'=>'EAN/GTIN',
    'sku'=>'SKU/MPN','category'=>'Kategoria','description'=>'Opis',
];

function feed_store_from_url(string $url): string {
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
    $host = preg_replace('/^www\./', '', $host);
    $base = explode('.', $host)[0] ?? 'Sklep';
    $fix = ['end'=>'END.','stockx'=>'StockX','goat'=>'GOAT','ssense'=>'SSENSE','farfetch'=>'Farfetch','zalando'=>'Zalando'];
    return $fix[$base] ?? ($base ? ucfirst($base) : 'Sklep');
}

// Zwraca [items(array assoc), fields(list)] z pliku feedu
function feed_parse(string $path, string $type, int $max = 5000): array {
    $items = [];
    if ($type === 'csv') {
        $fh = fopen($path, 'r');
        $first = fgets($fh); rewind($fh);
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts); $delim = array_key_first($counts) ?: ',';
        $header = fgetcsv($fh, 0, $delim);
        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $header = array_map(fn($h) => strtolower(trim($h)), $header);
            $n = 0;
            while (($r = fgetcsv($fh, 0, $delim)) !== false && $n < $max) {
                $n++; $row = [];
                foreach ($header as $i => $h) $row[$h] = trim($r[$i] ?? '');
                $items[] = $row;
            }
        }
        fclose($fh);
    } else { // xml
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        if ($xml === false) return [[], []];
        $nodes = feed_xml_items($xml);
        $n = 0;
        foreach ($nodes as $node) {
            if ($n >= $max) break; $n++;
            $items[] = feed_xml_to_assoc($node);
        }
    }
    $fields = $items ? array_keys($items[0]) : [];
    // zbierz wszystkie klucze (różne itemy mogą mieć różne pola)
    foreach ($items as $it) foreach (array_keys($it) as $k) if (!in_array($k, $fields, true)) $fields[] = $k;
    return [$items, $fields];
}

function feed_xml_items(SimpleXMLElement $xml): array {
    if (isset($xml->channel->item)) return iterator_to_array($xml->channel->item);   // RSS / Google Shopping
    if (isset($xml->entry))         return iterator_to_array($xml->entry);            // Atom
    // Generic: znajdź powtarzający się węzeł (do 2 poziomów)
    foreach ([$xml, ...iterator_to_array($xml->children())] as $root) {
        if (!($root instanceof SimpleXMLElement)) continue;
        $counts = [];
        foreach ($root->children() as $c) { $nm = $c->getName(); $counts[$nm] = ($counts[$nm] ?? 0) + 1; }
        if ($counts) {
            arsort($counts);
            $top = array_key_first($counts);
            if ($counts[$top] > 1) return iterator_to_array($root->$top);
        }
    }
    return [];
}

function feed_xml_to_assoc(SimpleXMLElement $item): array {
    $out = [];
    $add = function($k, $v) use (&$out) {
        $k = strtolower($k); $v = trim((string)$v);
        if ($v === '') return;
        $out[$k] = isset($out[$k]) ? $out[$k] . '|' . $v : $v;
    };
    foreach ($item->children() as $c) $add($c->getName(), $c);
    // namespace Google Shopping (g:)
    foreach ($item->getNamespaces(true) as $prefix => $ns) {
        if ($prefix === '') continue;
        foreach ($item->children($ns) as $c) $add($prefix . ':' . $c->getName(), $c);
    }
    return $out;
}

$result = null;
$step   = 'upload';
$fields = [];
$auto   = [];
$token  = '';
$ftype  = '';
$sample = [];

// Wykrycie kolumny ean (do bannera)
$has_ean = true;
try { $pdo->query("SELECT ean FROM clothing LIMIT 1"); }
catch (Exception $e) { $has_ean = false; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // === KROK 1: upload + parsowanie ===
    if (isset($_FILES['feed'])) {
        if ($_FILES['feed']['error'] !== UPLOAD_ERR_OK) {
            $result = ['error' => 'Błąd przesyłania pliku.'];
        } else {
            $name = strtolower($_FILES['feed']['name']);
            $ftype = (str_ends_with($name, '.csv')) ? 'csv' : 'xml';
            $token = bin2hex(random_bytes(16));
            $dest = $FEED_DIR . $token . '.' . $ftype;
            if (!move_uploaded_file($_FILES['feed']['tmp_name'], $dest)) {
                $result = ['error' => 'Nie udało się zapisać pliku.'];
            } else {
                [$items, $fields] = feed_parse($dest, $ftype, 3);  // tylko próbka do mapowania
                if (!$fields) {
                    $result = ['error' => 'Nie wykryto pól w feedzie (zły format XML/CSV?).'];
                    @unlink($dest);
                } else {
                    $sample = $items[0] ?? [];
                    // auto-mapowanie
                    foreach ($OUR_FIELDS as $our => $cands) {
                        $auto[$our] = '';
                        foreach ($cands as $c) {
                            if (in_array($c, $fields, true)) { $auto[$our] = $c; break; }
                        }
                    }
                    $step = 'map';
                }
            }
        }
    }
    // === KROK 3: import wg mapowania ===
    elseif (isset($_POST['do_import'])) {
        $token = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
        $ftype = ($_POST['ftype'] ?? 'xml') === 'csv' ? 'csv' : 'xml';
        $map   = $_POST['map'] ?? [];
        $store = trim($_POST['store_name'] ?? '');
        $brandOv = trim($_POST['brand_override'] ?? '');
        $catOv   = trim($_POST['category_override'] ?? '');
        $path  = $FEED_DIR . $token . '.' . $ftype;

        if (!$token || !is_file($path)) {
            $result = ['error' => 'Sesja importu wygasła — wgraj feed ponownie.'];
        } else {
            [$items, ] = feed_parse($path, $ftype, 5000);
            $pick = fn($it, $our) => (!empty($map[$our]) && isset($it[$map[$our]])) ? $it[$map[$our]] : '';

            $rows = [];
            foreach ($items as $it) {
                $deeplink = $pick($it, 'deeplink');
                $price    = $pick($it, 'price');
                $rows[] = [
                    'name'        => $pick($it, 'name'),
                    'brand'       => $brandOv ?: $pick($it, 'brand'),
                    'category'    => $catOv ?: $pick($it, 'category'),
                    'price'       => $price,
                    'image_url'   => $pick($it, 'image_url'),
                    'description' => $pick($it, 'description'),
                    'images'      => $pick($it, 'images'),     // '|'-rozdzielone
                    'ean'         => $pick($it, 'ean'),
                    'sku'         => $pick($it, 'sku'),
                    'source_url'  => $deeplink,
                    'links'       => $deeplink !== '' ? [[($store ?: feed_store_from_url($deeplink)), $deeplink, $price]] : [],
                ];
            }
            $result = catalog_import($pdo, $rows);
            $result['ok'] = true;
            @unlink($path);  // sprzątanie
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Catalog</div>
  <h1 class="page-title">IMPORT<br>Z FEEDU</h1>
  <p class="page-sub">Feedy afiliacyjne (Awin / Impact / Rakuten) → katalog</p>
</section>

<div class="admin-form-wrap" style="max-width:760px;">

  <?php if (!$has_ean): ?>
    <div class="form-error" style="margin-bottom:16px;">
      Uruchom migrację <strong>catalog_ean_migration.sql</strong> w phpMyAdmin, aby dedupować po EAN/GTIN
      (na razie dedup po SKU → marka+nazwa).
    </div>
  <?php endif; ?>

  <?php if ($result && isset($result['error'])): ?>
    <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($result['error']) ?></div>
  <?php elseif ($result && !empty($result['ok'])): ?>
    <div class="form-success" style="margin-bottom:16px;">
      Dodano: <strong><?= $result['inserted'] ?></strong> ·
      Scalono: <strong><?= $result['merged'] ?></strong> ·
      Błędne wiersze: <strong><?= $result['errors'] ?></strong>
      <br><a href="/admin/manage_clothing.php" style="color:var(--red)">Zobacz katalog →</a>
    </div>
  <?php endif; ?>

  <?php if ($step === 'map'): ?>
    <!-- KROK 2: MAPOWANIE -->
    <form method="POST" class="admin-form">
      <?= csrf_field() ?>
      <input type="hidden" name="do_import" value="1">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="ftype" value="<?= htmlspecialchars($ftype) ?>">

      <div class="form-section-title">/// Mapowanie pól feedu</div>
      <p style="color:var(--gray); font-size:13px; margin-bottom:16px;">
        Auto-dopasowane dla formatu Google Shopping. Popraw jeśli trzeba.
      </p>

      <?php foreach ($OUR_FIELDS as $our => $cands): ?>
        <div class="form-group">
          <label class="form-label"><?= $FIELD_LABELS[$our] ?></label>
          <select name="map[<?= $our ?>]" class="form-input">
            <option value="">— pomiń —</option>
            <?php foreach ($fields as $f): ?>
              <option value="<?= htmlspecialchars($f) ?>" <?= ($auto[$our] ?? '') === $f ? 'selected' : '' ?>>
                <?= htmlspecialchars($f) ?>
                <?php if (isset($sample[$f]) && $sample[$f] !== ''): ?>
                  — <?= htmlspecialchars(mb_substr($sample[$f], 0, 40)) ?>
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endforeach; ?>

      <div class="form-section-title" style="margin-top:24px;">/// Opcje</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nazwa sklepu (do linku)</label>
          <input type="text" name="store_name" class="form-input" placeholder="np. END. (auto z domeny gdy puste)">
        </div>
        <div class="form-group">
          <label class="form-label">Wymuś markę (opcjonalnie)</label>
          <input type="text" name="brand_override" class="form-input" placeholder="np. Nike">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Wymuś kategorię (opcjonalnie)</label>
        <input type="text" name="category_override" class="form-input" placeholder="sneakers / hoodie / ...">
      </div>

      <div style="display:flex; gap:12px; margin-top:16px;">
        <button type="submit" class="btn">Importuj feed →</button>
        <a href="/admin/import_feed.php" class="btn btn-outline">Anuluj</a>
      </div>
    </form>

  <?php else: ?>
    <!-- KROK 1: UPLOAD -->
    <form method="POST" enctype="multipart/form-data" class="admin-form">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Plik feedu (XML lub CSV)</label>
        <input type="file" name="feed" class="form-input form-file" accept=".xml,.csv,text/xml,text/csv" required>
      </div>
      <button type="submit" class="btn">Wczytaj i mapuj →</button>
    </form>

    <div class="edit-section" style="margin-top:32px;">
      <div class="form-section-title">/// Jak to działa</div>
      <ul style="color:var(--gray); font-size:13px; line-height:1.8; padding-left:18px;">
        <li>Pobierz <strong>product feed</strong> z sieci afiliacyjnej (Awin / Impact / Rakuten) — XML (Google Shopping) lub CSV.</li>
        <li>Wgraj plik → dostaniesz ekran <strong>mapowania kolumn</strong> (auto-dopasowane).</li>
        <li>Link partnerski (deeplink) trafia do „gdzie kupić", EAN/GTIN służy do deduplikacji.</li>
        <li>Ten sam produkt z różnych sieci <strong>scala się</strong> — dokleja kolejny link zamiast tworzyć duplikat.</li>
      </ul>
    </div>
  <?php endif; ?>

  <div style="margin-top:24px;">
    <a href="/admin/" class="btn btn-outline">← Admin</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
