<?php
require_once __DIR__ . '/auth.php';

// Czy kolumna source_url istnieje
$has_source = true;
try { $pdo->query("SELECT source_url FROM drops LIMIT 1"); }
catch (Exception $e) { $has_source = false; }

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
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $col = [];
            foreach ($header as $i => $h) $col[strtolower(trim($h))] = $i;

            if (!isset($col['name']) || !isset($col['brand']) || !isset($col['drop_date'])) {
                $result = ['error' => 'CSV musi mieć przynajmniej kolumny: name, brand, drop_date.'];
            } else {
                // Preload do dedup/merge
                $src2id = [];  // lower(source_url) => id
                $key2id = [];  // lower(brand|name|datetime) => id
                $sel = "SELECT id, " . ($has_source ? "source_url," : "") . " brand, name, drop_date FROM drops";
                foreach ($pdo->query($sel) as $r) {
                    if ($has_source && !empty($r['source_url'])) $src2id[strtolower($r['source_url'])] = (int)$r['id'];
                    $key2id[strtolower(trim($r['brand'].'|'.$r['name'].'|'.$r['drop_date']))] = (int)$r['id'];
                }
                $has_imgs = [];
                foreach ($pdo->query("SELECT DISTINCT drop_id FROM drop_images") as $r) {
                    $has_imgs[(int)$r['drop_id']] = true;
                }

                $get = fn($row, $key) => isset($col[$key]) && isset($row[$col[$key]]) ? trim($row[$col[$key]]) : '';

                $inserted = 0; $merged = 0; $errors = 0; $rownum = 1; $max = 5000;

                $cols = 'name, brand, drop_date, image_url, link, description' . ($has_source ? ', source_url' : '');
                $ph   = implode(',', array_fill(0, substr_count($cols, ',') + 1, '?'));
                $ins_main = $pdo->prepare("INSERT INTO drops ($cols) VALUES ($ph)");
                $ins_img  = $pdo->prepare("INSERT INTO drop_images (drop_id, image_url, sort_order) VALUES (?,?,?)");
                $fill     = $pdo->prepare("UPDATE drops SET
                                image_url   = COALESCE(NULLIF(image_url,''), ?),
                                link        = COALESCE(NULLIF(link,''), ?),
                                description = COALESCE(NULLIF(description,''), ?)
                              WHERE id = ?");

                $pdo->beginTransaction();
                while (($row = fgetcsv($fh, 0, $delim)) !== false && $rownum <= $max) {
                    $rownum++;
                    $name  = $get($row, 'name');
                    $brand = $get($row, 'brand');
                    $date_raw = $get($row, 'drop_date');
                    if ($name === '' || $brand === '' || $date_raw === '') { $errors++; continue; }

                    $ts = strtotime($date_raw);
                    if ($ts === false) { $errors++; continue; }
                    $drop_date = date('Y-m-d H:i:s', $ts);

                    $image = $get($row, 'image_url') ?: null;
                    $link  = $get($row, 'link') ?: null;
                    $desc  = $get($row, 'description') ?: null;
                    $src   = $get($row, 'source_url') ?: null;
                    $imgs  = $get($row, 'images');
                    $key   = strtolower(trim($brand.'|'.$name.'|'.$drop_date));

                    // Dedup
                    $existId = null;
                    if ($src !== null && isset($src2id[strtolower($src)])) $existId = $src2id[strtolower($src)];
                    elseif (isset($key2id[$key]))                          $existId = $key2id[$key];

                    try {
                        if ($existId !== null) {
                            $fill->execute([$image, $link, $desc, $existId]);
                            if ($imgs !== '' && !isset($has_imgs[$existId])) {
                                $o = 1;
                                foreach (array_filter(array_map('trim', explode('|', $imgs))) as $iu) {
                                    $ins_img->execute([$existId, $iu, $o++]);
                                }
                                $has_imgs[$existId] = true;
                            }
                            $merged++;
                            continue;
                        }

                        $vals = [$name, $brand, $drop_date, $image, $link, $desc];
                        if ($has_source) $vals[] = $src;
                        $ins_main->execute($vals);
                        $did = (int)$pdo->lastInsertId();

                        if ($imgs !== '') {
                            $o = 1;
                            foreach (array_filter(array_map('trim', explode('|', $imgs))) as $iu) {
                                $ins_img->execute([$did, $iu, $o++]);
                            }
                            $has_imgs[$did] = true;
                        }

                        if ($src !== null) $src2id[strtolower($src)] = $did;
                        $key2id[$key] = $did;
                        $inserted++;
                    } catch (Exception $e) {
                        $errors++;
                    }
                }
                $pdo->commit();
                fclose($fh);
                $result = ['ok' => true, 'inserted' => $inserted, 'merged' => $merged, 'errors' => $errors];
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Admin → Drops</div>
  <h1 class="page-title">IMPORT<br>DROPÓW</h1>
  <p class="page-sub">Masowy import dropów z deduplikacją</p>
</section>

<div class="admin-form-wrap" style="max-width:760px;">
  <?php if (!$has_source): ?>
    <div class="form-error" style="margin-bottom:16px;">
      Uruchom migrację <strong>drops_source_migration.sql</strong> w phpMyAdmin, aby dedupować po
      adresie źródłowym (na razie dedup po marka+nazwa+data).
    </div>
  <?php endif; ?>

  <?php if ($result && isset($result['error'])): ?>
    <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($result['error']) ?></div>
  <?php elseif ($result && !empty($result['ok'])): ?>
    <div class="form-success" style="margin-bottom:16px;">
      Dodano: <strong><?= $result['inserted'] ?></strong> ·
      Scalono: <strong><?= $result['merged'] ?></strong> ·
      Błędne wiersze: <strong><?= $result['errors'] ?></strong>
      <br><a href="/grailos/admin/manage_drops.php" style="color:var(--red)">Zobacz dropy →</a>
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
    <p style="color:var(--gray); font-size:13px; line-height:1.7;">
      Pierwszy wiersz to nagłówki. Wymagane: <strong>name</strong>, <strong>brand</strong>, <strong>drop_date</strong>.
      Opcjonalne: <strong>image_url, link, description, images, source_url</strong>.
    </p>
    <ul style="color:var(--gray); font-size:13px; line-height:1.8; padding-left:18px;">
      <li><strong>drop_date</strong> — data i godzina, dowolny czytelny format (np. 2026-06-20 10:00)</li>
      <li><strong>image_url</strong> — główne zdjęcie · <strong>images</strong> — dodatkowe oddzielone <code>|</code></li>
      <li><strong>link</strong> — link do zakupu · <strong>source_url</strong> — URL strony dropu (dedup)</li>
    </ul>
    <p style="color:var(--gray); font-size:13px; line-height:1.7;">
      <strong>Duplikaty:</strong> drop istniejący (po source_url lub marka+nazwa+data) nie jest
      dublowany — uzupełniane są tylko puste pola.
    </p>
    <pre style="background:#111; border:1px solid var(--border); padding:14px; overflow-x:auto; font-size:12px; color:#aaa;">name,brand,drop_date,image_url,link,description,source_url
Air Jordan 1 High,Air Jordan,2026-06-20 10:00,https://.../aj1.webp,https://nike.com/...,Nowy drop,https://nike.com/launch/aj1</pre>
  </div>

  <div style="margin-top:24px;">
    <a href="/grailos/admin/" class="btn btn-outline">← Admin</a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
