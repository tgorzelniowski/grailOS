<?php
/**
 * Wspólny silnik importu katalogu — dedup + merge.
 * Używany przez admin/import_clothing.php (CSV) i admin/import_feed.php (feedy XML/CSV).
 *
 * catalog_import(PDO $pdo, array $rows): array
 *   $rows — lista rekordów (assoc), pola:
 *     ean, sku, name, brand, category, price, image_url, description,
 *     images (string '|'-rozdzielony LUB tablica URL-i),
 *     links  (tablica wpisów [store_name, url, price] LUB string "Store|url|price;...")
 *   Dedup priorytet: EAN → SKU → marka+nazwa.
 *   Duplikat: dokleja linki (dedup po URL) + uzupełnia puste pola + dodaje galerię gdy brak.
 *   Zwraca ['inserted'=>, 'merged'=>, 'skipped'=>, 'errors'=>].
 */

const CATALOG_CATS = ['hoodie','tshirt','pants','jacket','sneakers','cap','other'];
const CATALOG_CAT_SYNONYMS = [
    'sneaker'=>'sneakers','shoes'=>'sneakers','buty'=>'sneakers','footwear'=>'sneakers','trainers'=>'sneakers',
    'tee'=>'tshirt','t-shirt'=>'tshirt','koszulka'=>'tshirt','shirt'=>'tshirt','tshirts'=>'tshirt',
    'hoody'=>'hoodie','bluza'=>'hoodie','sweatshirt'=>'hoodie','crewneck'=>'hoodie','hoodies'=>'hoodie',
    'spodnie'=>'pants','trousers'=>'pants','jeans'=>'pants','denim'=>'pants',
    'kurtka'=>'jacket','coat'=>'jacket','outerwear'=>'jacket','jackets'=>'jacket',
    'czapka'=>'cap','hat'=>'cap','beanie'=>'cap','caps'=>'cap',
];

function catalog_norm_category(string $cat): string {
    $cat = strtolower(trim($cat));
    if ($cat === '') return 'other';
    if (isset(CATALOG_CAT_SYNONYMS[$cat])) return CATALOG_CAT_SYNONYMS[$cat];
    if (in_array($cat, CATALOG_CATS, true)) return $cat;
    // dopasowanie częściowe (np. "Men's Shoes" → sneakers)
    foreach (CATALOG_CAT_SYNONYMS as $k => $v) {
        if (strpos($cat, $k) !== false) return $v;
    }
    foreach (CATALOG_CATS as $c) {
        if ($c !== 'other' && strpos($cat, $c) !== false) return $c;
    }
    return 'other';
}

function catalog_parse_price($raw): ?float {
    if ($raw === null) return null;
    $s = str_replace([' ', "\u{a0}", ','], ['', '', '.'], (string)$raw);
    $s = preg_replace('/[^\d.]/', '', $s);   // usuń walutę (PLN, zł, €...)
    if ($s === '' || !is_numeric($s)) return null;
    return (float)$s;
}

function catalog_import(PDO $pdo, array $rows): array {
    // Wykrycie opcjonalnych kolumn
    $has = ['sku' => false, 'ean' => false, 'source_url' => false];
    foreach (array_keys($has) as $cextra) {
        try { $pdo->query("SELECT $cextra FROM clothing LIMIT 1"); $has[$cextra] = true; }
        catch (Exception $e) { $has[$cextra] = false; }
    }

    // Preload map dedup
    $ean2id = []; $sku2id = []; $bn2id = [];
    $sel = "SELECT id, brand, name"
         . ($has['sku'] ? ", sku" : "")
         . ($has['ean'] ? ", ean" : "")
         . " FROM clothing";
    foreach ($pdo->query($sel) as $r) {
        $id = (int)$r['id'];
        if ($has['ean'] && !empty($r['ean'])) $ean2id[strtolower(trim($r['ean']))] = $id;
        if ($has['sku'] && !empty($r['sku'])) $sku2id[strtolower(trim($r['sku']))] = $id;
        $bn2id[strtolower(trim($r['brand'].'|'.$r['name']))] = $id;
    }
    $links_map = [];
    foreach ($pdo->query("SELECT clothing_id, store_url FROM clothing_links") as $r) {
        $links_map[(int)$r['clothing_id']][strtolower(trim($r['store_url']))] = true;
    }
    $has_imgs = [];
    foreach ($pdo->query("SELECT DISTINCT clothing_id FROM clothing_images") as $r) {
        $has_imgs[(int)$r['clothing_id']] = true;
    }

    // Przygotowane zapytania
    $cols = [];
    if ($has['sku']) $cols[] = 'sku';
    if ($has['ean']) $cols[] = 'ean';
    array_push($cols, 'name', 'brand', 'category', 'price', 'image_url', 'description');
    if ($has['source_url']) $cols[] = 'source_url';
    $ins_main = $pdo->prepare("INSERT INTO clothing (".implode(',', $cols).") VALUES (".implode(',', array_fill(0, count($cols), '?')).")");
    $ins_img  = $pdo->prepare("INSERT INTO clothing_images (clothing_id, image_url, sort_order) VALUES (?,?,?)");
    $ins_link = $pdo->prepare("INSERT INTO clothing_links (clothing_id, store_name, store_url, price) VALUES (?,?,?,?)");
    $fill     = $pdo->prepare("UPDATE clothing SET
                    image_url   = COALESCE(NULLIF(image_url,''), ?),
                    description = COALESCE(NULLIF(description,''), ?),
                    price       = COALESCE(price, ?)
                  WHERE id = ?");
    $fill_src = $has['source_url'] ? $pdo->prepare("UPDATE clothing SET source_url = COALESCE(NULLIF(source_url,''), ?) WHERE id = ?") : null;

    // Normalizacja list
    $as_images = function($v): array {
        if (is_array($v)) $items = $v;
        else $items = explode('|', (string)$v);
        return array_values(array_filter(array_map('trim', $items)));
    };
    $as_links = function($v): array {
        // -> lista [store, url, price]
        $out = [];
        if (is_array($v)) {
            foreach ($v as $entry) {
                if (is_array($entry)) {
                    $out[] = [trim($entry[0] ?? 'Sklep'), trim($entry[1] ?? ''), $entry[2] ?? null];
                }
            }
        } else {
            foreach (array_filter(array_map('trim', explode(';', (string)$v))) as $e) {
                $p = array_map('trim', explode('|', $e));
                if (count($p) >= 2 && $p[1] !== '') $out[] = [$p[0] ?: 'Sklep', $p[1], $p[2] ?? null];
            }
        }
        return $out;
    };

    $addLinks = function(int $cid, array $links) use ($ins_link, &$links_map): int {
        $added = 0;
        foreach ($links as $l) {
            $url = $l[1] ?? '';
            if ($url === '') continue;
            $k = strtolower($url);
            if (isset($links_map[$cid][$k])) continue;
            $price = catalog_parse_price($l[2] ?? null);
            $ins_link->execute([$cid, $l[0] ?: 'Sklep', $url, $price]);
            $links_map[$cid][$k] = true;
            $added++;
        }
        return $added;
    };

    $inserted = $merged = $skipped = $errors = 0;

    $pdo->beginTransaction();
    foreach ($rows as $row) {
        $name  = trim($row['name'] ?? '');
        $brand = trim($row['brand'] ?? '');
        if ($name === '') { $errors++; continue; }

        $sku = trim($row['sku'] ?? '');
        $ean = trim($row['ean'] ?? '');
        $bn  = strtolower(trim($brand.'|'.$name));
        $cat = catalog_norm_category($row['category'] ?? '');
        $price = catalog_parse_price($row['price'] ?? null);
        $image = ($row['image_url'] ?? '') !== '' ? trim($row['image_url']) : null;
        $desc  = ($row['description'] ?? '') !== '' ? trim($row['description']) : null;
        $src   = ($row['source_url'] ?? '') !== '' ? trim($row['source_url']) : null;
        $imgs  = $as_images($row['images'] ?? '');
        $links = $as_links($row['links'] ?? '');

        // Dedup: EAN → SKU → marka+nazwa
        $existId = null;
        if ($has['ean'] && $ean !== '' && isset($ean2id[strtolower($ean)]))      $existId = $ean2id[strtolower($ean)];
        elseif ($has['sku'] && $sku !== '' && isset($sku2id[strtolower($sku)]))   $existId = $sku2id[strtolower($sku)];
        elseif (isset($bn2id[$bn]))                                               $existId = $bn2id[$bn];

        try {
            if ($existId !== null) {
                if ($links) $addLinks($existId, $links);
                $fill->execute([$image, $desc, $price, $existId]);
                if ($fill_src) $fill_src->execute([$src, $existId]);
                if ($imgs && !isset($has_imgs[$existId])) {
                    $o = 1; foreach ($imgs as $iu) $ins_img->execute([$existId, $iu, $o++]);
                    $has_imgs[$existId] = true;
                }
                $merged++;
                continue;
            }

            // Nowy produkt
            $vals = [];
            if ($has['sku']) $vals[] = $sku ?: null;
            if ($has['ean']) $vals[] = $ean ?: null;
            array_push($vals, $name, $brand, $cat, $price, $image, $desc);
            if ($has['source_url']) $vals[] = $src;
            $ins_main->execute($vals);
            $cid = (int)$pdo->lastInsertId();

            if ($imgs) { $o = 1; foreach ($imgs as $iu) $ins_img->execute([$cid, $iu, $o++]); $has_imgs[$cid] = true; }
            if ($links) $addLinks($cid, $links);

            if ($ean !== '') $ean2id[strtolower($ean)] = $cid;
            if ($sku !== '') $sku2id[strtolower($sku)] = $cid;
            $bn2id[$bn] = $cid;
            $inserted++;
        } catch (Exception $e) {
            $errors++;
        }
    }
    $pdo->commit();

    return ['inserted' => $inserted, 'merged' => $merged, 'skipped' => $skipped, 'errors' => $errors];
}
