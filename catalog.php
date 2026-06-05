<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Browse</div>
  <h1 class="page-title">CATALOG</h1>
  <p class="page-sub">Znajdź swój grail. Przeglądaj, filtruj, inspiruj się.</p>
</section>

<?php
$brands = $pdo->query("SELECT DISTINCT brand FROM clothing ORDER BY brand ASC")->fetchAll(PDO::FETCH_COLUMN);

$conditions = [];
$params = [];

if (!empty($_GET['search'])) {
    $conditions[] = "(name LIKE :search OR brand LIKE :search)";
    $params['search'] = '%' . $_GET['search'] . '%';
}
if (!empty($_GET['category'])) {
    $conditions[] = "category = :category";
    $params['category'] = $_GET['category'];
}
if (!empty($_GET['brand'])) {
    $conditions[] = "brand = :brand";
    $params['brand'] = $_GET['brand'];
}
if (!empty($_GET['min_price'])) {
    $conditions[] = "price >= :min_price";
    $params['min_price'] = (float) $_GET['min_price'];
}
if (!empty($_GET['max_price'])) {
    $conditions[] = "price <= :max_price";
    $params['max_price'] = (float) $_GET['max_price'];
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sort  = match($_GET['sort'] ?? 'newest') {
    'price_asc'  => 'ORDER BY price ASC',
    'price_desc' => 'ORDER BY price DESC',
    'az'         => 'ORDER BY name ASC',
    default      => 'ORDER BY created_at DESC',
};

// Paginacja
$per_page = 96;
$page     = max(1, (int)($_GET['page'] ?? 1));

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM clothing $where");
$count_stmt->execute($params);
$total_items = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_items / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT * FROM clothing $where $sort LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Link bazowy paginacji (zachowaj filtry)
$qs = $_GET; unset($qs['page']);
$base_qs   = http_build_query($qs);
$page_link = '/grailos/catalog.php?' . ($base_qs ? $base_qs . '&' : '') . 'page=';

$item_ids  = array_column($items, 'id');
$hover_map = [];
if ($item_ids) {
    $ph    = implode(',', array_fill(0, count($item_ids), '?'));
    $hstmt = $pdo->prepare("
        SELECT clothing_id, image_url
        FROM clothing_images
        WHERE clothing_id IN ($ph)
        GROUP BY clothing_id
        ORDER BY sort_order ASC
    ");
    $hstmt->execute($item_ids);
    foreach ($hstmt->fetchAll() as $row) {
        if (!isset($hover_map[$row['clothing_id']])) {
            $hover_map[$row['clothing_id']] = $row['image_url'];
        }
    }
}
?>

<!-- TOGGLE FILTRÓW (mobile) -->
<button type="button" class="catalog-filter-toggle" onclick="toggleCatalogFilters(this)">
  <span>Filtry</span><span id="catFilterArrow">▾</span>
</button>

<div class="catalog-layout">

  <!-- SIDEBAR FILTRY -->
  <aside class="catalog-sidebar" id="catalogSidebar">
    <form method="GET" action="/grailos/catalog.php" id="catalogFilterForm">

      <div class="filter-section">
        <div class="filter-section-title">Szukaj</div>
        <input type="text" name="search"
               placeholder="Wpisz nazwę lub markę..."
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
               class="sidebar-filter-input">
      </div>

      <div class="filter-section">
        <div class="filter-section-title">Kategoria</div>
        <label class="filter-label">
          <input type="radio" name="category" value=""
                 <?= empty($_GET['category']) ? 'checked' : '' ?>
                 onchange="this.form.submit()">
          Wszystkie
        </label>
        <?php foreach (['hoodie','tshirt','pants','jacket','sneakers','cap','other'] as $cat): ?>
          <label class="filter-label">
            <input type="radio" name="category" value="<?= $cat ?>"
                   <?= ($_GET['category'] ?? '') === $cat ? 'checked' : '' ?>
                   onchange="this.form.submit()">
            <?= ucfirst($cat) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="filter-section">
        <div class="filter-section-title">Marka</div>
        <label class="filter-label">
          <input type="radio" name="brand" value=""
                 <?= empty($_GET['brand']) ? 'checked' : '' ?>
                 onchange="this.form.submit()">
          Wszystkie
        </label>
        <?php foreach ($brands as $brand): ?>
          <label class="filter-label">
            <input type="radio" name="brand" value="<?= htmlspecialchars($brand) ?>"
                   <?= ($_GET['brand'] ?? '') === $brand ? 'checked' : '' ?>
                   onchange="this.form.submit()">
            <?= htmlspecialchars($brand) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="filter-section">
        <div class="filter-section-title">Cena (zł)</div>
        <div class="sidebar-price-row">
          <input type="number" name="min_price" placeholder="Min"
                 value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>"
                 class="sidebar-price-input" min="0">
          <span class="price-sep">—</span>
          <input type="number" name="max_price" placeholder="Max"
                 value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"
                 class="sidebar-price-input" min="0">
        </div>
      </div>

      <div class="filter-section">
        <div class="filter-section-title">Sortowanie</div>
        <?php foreach ([
          'newest'     => 'Najnowsze',
          'price_asc'  => 'Cena ↑',
          'price_desc' => 'Cena ↓',
          'az'         => 'A–Z',
        ] as $val => $label): ?>
          <label class="filter-label">
            <input type="radio" name="sort" value="<?= $val ?>"
                   <?= ($_GET['sort'] ?? 'newest') === $val ? 'checked' : '' ?>
                   onchange="this.form.submit()">
            <?= $label ?>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="sidebar-filter-btns">
        <button type="submit" class="btn" style="width:100%;">Filtruj</button>
        <?php if (!empty($_GET['search']) || !empty($_GET['category']) || !empty($_GET['brand']) || !empty($_GET['min_price']) || !empty($_GET['max_price'])): ?>
          <a href="/grailos/catalog.php" class="btn btn-outline"
             style="width:100%; text-align:center; display:block;">Resetuj ×</a>
        <?php endif; ?>
      </div>

    </form>
  </aside>

  <!-- PRODUKTY -->
  <div class="catalog-content">

    <div class="catalog-toolbar">
      <span class="catalog-count">/// <?= $total_items ?> produktów<?= $total_pages > 1 ? ' · strona ' . $page . '/' . $total_pages : '' ?></span>
    </div>

    <div class="clothing-grid clothing-grid--catalog">
      <?php if ($items): ?>
        <?php foreach ($items as $item):
          $hover_img   = $hover_map[$item['id']] ?? null;
          $in_wardrobe = false;
          if (isset($_SESSION['user_id'])) {
            $wcheck = $pdo->prepare("SELECT id FROM wardrobe_items WHERE user_id = ? AND clothing_id = ?");
            $wcheck->execute([$_SESSION['user_id'], $item['id']]);
            $in_wardrobe = (bool)$wcheck->fetch();
          }
        ?>
          <div class="clothing-card">
            <a href="/grailos/item.php?id=<?= $item['id'] ?>" class="clothing-card-link">
              <div class="clothing-card-img">
                <?php if ($item['image_url']): ?>
                  <img src="<?= htmlspecialchars($item['image_url']) ?>"
                       class="img-main" alt="<?= htmlspecialchars($item['name']) ?>">
                  <?php if ($hover_img): ?>
                    <img src="<?= htmlspecialchars($hover_img) ?>"
                         class="img-hover" alt="">
                  <?php endif; ?>
                <?php else: ?>
                  <span class="no-img-text">NO IMG</span>
                <?php endif; ?>
              </div>
              <div class="clothing-card-brand"><?= htmlspecialchars($item['brand']) ?></div>
              <div class="clothing-card-name"><?= htmlspecialchars($item['name']) ?></div>
            </a>
            <div class="clothing-card-footer">
              <div class="clothing-card-price">
                <?= $item['price'] ? number_format($item['price'], 2) . ' zł' : '—' ?>
              </div>
              <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST" action="/grailos/wardrobe_toggle.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="clothing_id" value="<?= $item['id'] ?>">
                  <button type="submit"
                          class="wardrobe-btn <?= $in_wardrobe ? 'in-wardrobe' : '' ?>"
                          title="<?= $in_wardrobe ? 'Usuń z szafy' : 'Dodaj do szafy' ?>">
                    <?= $in_wardrobe ? '✓' : '+' ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="grid-column:span 4; padding:80px; text-align:center; color:#444;
                    letter-spacing:3px; text-transform:uppercase; font-size:13px;">
          Brak wyników.
        </div>
      <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
      <div class="catalog-pagination">
        <?php if ($page > 1): ?>
          <a href="<?= $page_link . ($page - 1) ?>" class="page-btn page-nav">← Poprzednia</a>
        <?php endif; ?>
        <?php
          $start = max(1, $page - 2);
          $end   = min($total_pages, $page + 2);
          if ($start > 1) echo '<a href="' . $page_link . '1" class="page-btn">1</a>';
          if ($start > 2) echo '<span class="page-dots">…</span>';
          for ($p = $start; $p <= $end; $p++):
        ?>
          <a href="<?= $page_link . $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor;
          if ($end < $total_pages - 1) echo '<span class="page-dots">…</span>';
          if ($end < $total_pages) echo '<a href="' . $page_link . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
        ?>
        <?php if ($page < $total_pages): ?>
          <a href="<?= $page_link . ($page + 1) ?>" class="page-btn page-nav">Następna →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

</div>

<script>
function toggleCatalogFilters(btn) {
  const sb = document.getElementById('catalogSidebar');
  sb.classList.toggle('open');
  document.getElementById('catFilterArrow').textContent = sb.classList.contains('open') ? '▴' : '▾';
}
</script>

<?php require_once 'includes/footer.php'; ?>
