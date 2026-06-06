<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/catalog_import.php';

// Do bannera w UI (informacja o migracji)
$has_sku = true;
try { $pdo->query("SELECT sku FROM clothing LIMIT 1"); }
catch (Exception $e) { $has_sku = false; }

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    csrf_verify();
    $delim_choice = $_POST['delim'] ?? 'auto';

    if ($_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $result = ['error' => 'Błąd przesyłania pliku.'];
    } else {
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');

        // Separator: auto-wykrycie z pierwszej linii albo ręczny wybór
        if ($delim_choice === 'auto') {
            $first = fgets($fh);
            rewind($fh);
            $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
            arsort($counts);
            $delim = array_key_first($counts) ?: ',';
        } else {
            $delim = ['comma'=>',', 'semicolon'=>';', 'tab'=>"\t"][$delim_choice] ?? ',';
        }

        $header = fgetcsv($fh, 0, $delim);
        if (!$header) {
            $result = ['error' => 'Pusty plik lub zły format.'];
        } else {
            // Mapowanie nagłówków → indeks (lowercase, bez BOM)
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $col = [];
            foreach ($header as $i => $h) $col[strtolower(trim($h))] = $i;

            if (!isset($col['name'])) {
                $result = ['error' => 'CSV musi mieć przynajmniej kolumnę: name.'];
            } else {
                $get = fn($row, $key) => isset($col[$key]) && isset($row[$col[$key]]) ? trim($row[$col[$key]]) : '';

                // Zbuduj znormalizowane wiersze → wspólny silnik
                $rows = [];
                $max = 5000; $n = 0;
                while (($row = fgetcsv($fh, 0, $delim)) !== false && $n < $max) {
                    $n++;
                    $rows[] = [
                        'name'        => $get($row, 'name'),
                        'brand'       => $get($row, 'brand'),
                        'category'    => $get($row, 'category'),
                        'price'       => $get($row, 'price'),
                        'image_url'   => $get($row, 'image_url'),
                        'description' => $get($row, 'description'),
                        'images'      => $get($row, 'images'),
                        'links'       => $get($row, 'links'),
                        'sku'         => $get($row, 'sku'),
                        'ean'         => $get($row, 'ean'),
                        'source_url'  => $get($row, 'source_url'),
                    ];
                }
                fclose($fh);

                $result = catalog_import($pdo, $rows);
                $result['ok'] = true;
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
      Dodano: <strong><?= $result['inserted'] ?></strong> ·
      Scalono (linki+braki): <strong><?= $result['merged'] ?? 0 ?></strong> ·
      Pominięto: <strong><?= $result['skipped'] ?></strong> ·
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
        <option value="auto">Auto (wykryj sam)</option>
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
      <li><strong>source_url</strong> — URL strony produktu (opcjonalnie; pod przyszłe aktualizacje)</li>
    </ul>
    <p style="color:var(--gray); font-size:13px; line-height:1.7;">
      <strong>Duplikaty:</strong> jeśli produkt już istnieje (po sku lub marka+nazwa), import
      <strong>nie tworzy kopii</strong> — dokleja tylko nowe linki do sklepów i uzupełnia puste
      pola (opis/zdjęcie/cena). Dzięki temu możesz importować ten sam produkt z wielu sklepów.
    </p>
    <p style="color:#555; font-size:12px; margin-top:10px;">Przykład wiersza:</p>
    <pre style="background:#111; border:1px solid var(--border); padding:14px; overflow-x:auto; font-size:12px; color:#aaa;">sku,name,brand,category,price,image_url,description,images,links
DZ5485-612,Air Jordan 1 Chicago,Air Jordan,sneakers,980.00,https://.../aj1.webp,Kultowy model,https://.../2.webp|https://.../3.webp,StockX|https://stockx.com/...|1100 ; GOAT|https://goat.com/...|1050</pre>
  </div>

  <div style="margin-top:24px;">
    <a href="/grailos/admin/" class="btn btn-outline">← Admin</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
