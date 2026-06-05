<?php
require_once __DIR__ . '/auth.php';

$cats = ['hoodie','tshirt','pants','jacket','sneakers','cap','other'];
$cat_synonyms = [
    'sneaker'=>'sneakers','shoes'=>'sneakers','buty'=>'sneakers','footwear'=>'sneakers',
    'tee'=>'tshirt','t-shirt'=>'tshirt','koszulka'=>'tshirt','shirt'=>'tshirt',
    'hoody'=>'hoodie','bluza'=>'hoodie','sweatshirt'=>'hoodie','crewneck'=>'hoodie',
    'spodnie'=>'pants','trousers'=>'pants','jeans'=>'pants','denim'=>'pants',
    'kurtka'=>'jacket','coat'=>'jacket','outerwear'=>'jacket',
    'czapka'=>'cap','hat'=>'cap','beanie'=>'cap',
];

// Czy kolumna sku istnieje
$has_sku = true;
try { $pdo->query("SELECT sku FROM clothing LIMIT 1"); }
catch (Exception $e) { $has_sku = false; }

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    csrf_verify();
    $delim = $_POST['delim'] ?? ',';
    $delim = ['comma'=>',', 'semicolon'=>';', 'tab'=>"\t"][$delim] ?? ',';

    if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $result = ['error' => 'Błąd przesyłania pliku.'];
    } else {
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($fh, 0, $delim);
        if (!$header) {
            $result = ['error' => 'Pusty plik lub zły format.'];
        } else {
            // Mapowanie nagłówków → indeks (lowercase, bez BOM)
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $col = [];
            foreach ($header as $i => $h) $col[strtolower(trim($h))] = $i;

            if (!isset($col['name']) || !isset($col['brand'])) {
                $result = ['error' => 'CSV musi mieć przynajmniej kolumny: name, brand.'];
            } else {
                // Preload istniejących kluczy (dedup)
                $existing_sku = [];
                $existing_bn  = [];
                foreach ($pdo->query("SELECT " . ($has_sku ? "sku," : "") . " brand, name FROM clothing") as $r) {
                    if ($has_sku && !empty($r['sku'])) $existing_sku[strtolower($r['sku'])] = true;
                    $existing_bn[strtolower(trim($r['brand'].'|'.$r['name']))] = true;
                }

                $get = fn($row, $key) => isset($col[$key]) && isset($row[$col[$key]]) ? trim($row[$col[$key]]) : '';

                $inserted = 0; $skipped = 0; $errors = 0; $rownum = 1;
                $max = 5000;

                $ins_main = $pdo->prepare($has_sku
                    ? "INSERT INTO clothing (sku, name, brand, category, price, image_url, description) VALUES (?,?,?,?,?,?,?)"
                    : "INSERT INTO clothing (name, brand, category, price, image_url, description) VALUES (?,?,?,?,?,?)");
                $ins_img  = $pdo->prepare("INSERT INTO clothing_images (clothing_id, image_url, sort_order) VALUES (?,?,?)");
                $ins_link = $pdo->prepare("INSERT INTO clothing_links (clothing_id, store_name, store_url, price) VALUES (?,?,?,?)");

                $pdo->beginTransaction();
                while (($row = fgetcsv($fh, 0, $delim)) !== false && $rownum <= $max) {
                    $rownum++;
                    $name  = $get($row, 'name');
                    $brand = $get($row, 'brand');
                    if ($name === '' || $brand === '') { $errors++; continue; }

                    $sku = $get($row, 'sku');
                    $bn  = strtolower(trim($brand.'|'.$name));

                    // Dedup
                    if ($sku !== '' && isset($existing_sku[strtolower($sku)])) { $skipped++; continue; }
                    if ($sku === '' && isset($existing_bn[$bn])) { $skipped++; continue; }

                    // Kategoria
                    $cat = strtolower($get($row, 'category'));
                    if (isset($cat_synonyms[$cat])) $cat = $cat_synonyms[$cat];
                    if (!in_array($cat, $cats, true)) $cat = 'other';

                    // Cena
                    $price_raw = str_replace([' ', ','], ['', '.'], $get($row, 'price'));
                    $price = is_numeric($price_raw) ? (float)$price_raw : null;

                    $image = $get($row, 'image_url') ?: null;
                    $desc  = $get($row, 'description') ?: null;

                    try {
                        if ($has_sku) {
                            $ins_main->execute([$sku ?: null, $name, $brand, $cat, $price, $image, $desc]);
                        } else {
                            $ins_main->execute([$name, $brand, $cat, $price, $image, $desc]);
                        }
                        $cid = $pdo->lastInsertId();

                        // Dodatkowe zdjęcia (pipe |)
                        $imgs = $get($row, 'images');
                        if ($imgs !== '') {
                            $o = 1;
                            foreach (array_filter(array_map('trim', explode('|', $imgs))) as $iu) {
                                $ins_img->execute([$cid, $iu, $o++]);
                            }
                        }
                        // Linki: "Store|url|price ; Store2|url2|price2"
                        $links = $get($row, 'links');
                        if ($links !== '') {
                            foreach (array_filter(array_map('trim', explode(';', $links))) as $entry) {
                                $parts = array_map('trim', explode('|', $entry));
                                if (count($parts) >= 2 && $parts[1] !== '') {
                                    $lp = isset($parts[2]) ? (float)str_replace([' ',','],['','.'],$parts[2]) : null;
                                    $ins_link->execute([$cid, $parts[0] ?: 'Sklep', $parts[1], $lp ?: null]);
                                }
                            }
                        }

                        // Zapamiętaj klucze (dedup w obrębie pliku)
                        if ($sku !== '') $existing_sku[strtolower($sku)] = true;
                        $existing_bn[$bn] = true;
                        $inserted++;
                    } catch (Exception $e) {
                        $errors++;
                    }
                }
                $pdo->commit();
                fclose($fh);
                $result = ['ok' => true, 'inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors];
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Catalog</div>
  <h1 class="page-title">IMPORT CSV</h1>
  <p class="page-sub">Masowy import produktów z deduplikacją</p>
</section>

<div class="admin-form-wrap" style="max-width:760px;">

  <?php if (!$has_sku): ?>
    <div class="form-error" style="margin-bottom:16px;">
      Uruchom migrację <strong>catalog_sku_migration.sql</strong> w phpMyAdmin, aby włączyć
      deduplikację po SKU (na razie dedup działa po marka+nazwa).
    </div>
  <?php endif; ?>

  <?php if ($result && isset($result['error'])): ?>
    <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($result['error']) ?></div>
  <?php elseif ($result && !empty($result['ok'])): ?>
    <div class="form-success" style="margin-bottom:16px;">
      Zaimportowano: <strong><?= $result['inserted'] ?></strong> ·
      Pominięto duplikaty: <strong><?= $result['skipped'] ?></strong> ·
      Błędne wiersze: <strong><?= $result['errors'] ?></strong>
      <br><a href="/grailos/admin/manage_clothing.php" style="color:var(--red)">Zobacz katalog →</a>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="admin-form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Plik CSV</label>
      <input type="file" name="csv" class="form-input form-file" accept=".csv,text/csv" required>
    </div>
    <div class="form-group">
      <label class="form-label">Separator kolumn</label>
      <select name="delim" class="form-input">
        <option value="comma">Przecinek ( , )</option>
        <option value="semicolon">Średnik ( ; )</option>
        <option value="tab">Tabulator</option>
      </select>
    </div>
    <button type="submit" class="btn">Importuj →</button>
  </form>

  <div class="edit-section" style="margin-top:32px;">
    <div class="form-section-title">/// Format CSV</div>
    <p style="color:var(--gray); font-size:13px; line-height:1.7; letter-spacing:0.3px;">
      Pierwszy wiersz to nagłówki. Wymagane: <strong>name</strong>, <strong>brand</strong>.
      Opcjonalne: <strong>sku, category, price, image_url, description, images, links</strong>.
    </p>
    <ul style="color:var(--gray); font-size:13px; line-height:1.8; padding-left:18px;">
      <li><strong>category</strong> — hoodie / tshirt / pants / jacket / sneakers / cap / other (synonimy auto-mapowane)</li>
      <li><strong>price</strong> — liczba, przecinek lub kropka (np. 899,00)</li>
      <li><strong>image_url</strong> — główne zdjęcie (URL zdalny lub ścieżka lokalna)</li>
      <li><strong>images</strong> — dodatkowe zdjęcia oddzielone znakiem <code>|</code></li>
      <li><strong>links</strong> — sklepy: <code>Nazwa|url|cena ; Nazwa2|url2|cena2</code></li>
      <li><strong>sku</strong> — kod produktu (klucz deduplikacji; bez niego dedup po marka+nazwa)</li>
    </ul>
    <p style="color:#555; font-size:12px; margin-top:10px;">Przykład wiersza:</p>
    <pre style="background:#111; border:1px solid var(--border); padding:14px; overflow-x:auto; font-size:12px; color:#aaa;">sku,name,brand,category,price,image_url,description,images,links
DZ5485-612,Air Jordan 1 Chicago,Air Jordan,sneakers,980.00,https://.../aj1.webp,Kultowy model,https://.../2.webp|https://.../3.webp,StockX|https://stockx.com/...|1100 ; GOAT|https://goat.com/...|1050</pre>
  </div>

  <div style="margin-top:24px;">
    <a href="/grailos/admin/" class="btn btn-outline">← Admin</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
