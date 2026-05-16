<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Browse</div>
  <h1 class="page-title">CATALOG</h1>
  <p class="page-sub">Znajdź swój grail. Przeglądaj, filtruj, inspiruj się.</p>
</section>

<?php
$brands = $pdo->query("SELECT DISTINCT brand FROM clothing ORDER BY brand ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="catalog-filters">
  <form method="GET" action="/grailos/catalog.php" class="filters-form">

    <input type="text" name="search" placeholder="Szukaj..."
           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
           class="filter-input">

    <select name="category" class="filter-select">
      <option value="">Wszystkie kategorie</option>
      <?php foreach (['hoodie','tshirt','pants','jacket','sneakers','cap','other'] as $cat): ?>
        <option value="<?= $cat ?>" <?= ($_GET['category'] ?? '') === $cat ? 'selected' : '' ?>>
          <?= ucfirst($cat) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="brand" class="filter-select">
      <option value="">Wszystkie marki</option>
      <?php foreach ($brands as $brand): ?>
        <option value="<?= htmlspecialchars($brand) ?>"
          <?= ($_GET['brand'] ?? '') === $brand ? 'selected' : '' ?>>
          <?= htmlspecialchars($brand) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <div class="filter-price-wrap">
      <input type="number" name="min_price" placeholder="Min zł"
             value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>"
             class="filter-input filter-price" min="0">
      <span class="price-sep">—</span>
      <input type="number" name="max_price" placeholder="Max zł"
             value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"
             class="filter-input filter-price" min="0">
    </div>

    <select name="sort" class="filter-select">
      <option value="newest"     <?= ($_GET['sort'] ?? '') === 'newest'     ? 'selected' : '' ?>>Najnowsze</option>
      <option value="price_asc"  <?= ($_GET['sort'] ?? '') === 'price_asc'  ? 'selected' : '' ?>>Cena: rosnąco</option>
      <option value="price_desc" <?= ($_GET['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Cena: malejąco</option>
      <option value="az"         <?= ($_GET['sort'] ?? '') === 'az'         ? 'selected' : '' ?>>A-Z</option>
    </select>

    <button type="submit" class="btn">Filtruj</button>

    <?php if (!empty($_GET['search']) || !empty($_GET['category']) || !empty($_GET['brand']) || !empty($_GET['min_price']) || !empty($_GET['max_price'])): ?>
      <a href="/grailos/catalog.php" class="btn btn-outline">Resetuj</a>
    <?php endif; ?>

  </form>
</div>

<?php
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

$stmt = $pdo->prepare("SELECT * FROM clothing $where $sort");
$stmt->execute($params);
$items = $stmt->fetchAll();
?>

<div class="section-label">/// <?= count($items) ?> produktów</div>

<div class="clothing-grid">
  <?php if ($items): ?>
    <?php foreach ($items as $item):
      $in_wardrobe = false;
      if (isset($_SESSION['user_id'])) {
        $wcheck = $pdo->prepare("SELECT id FROM wardrobe_items WHERE user_id = ? AND clothing_id = ?");
        $wcheck->execute([$_SESSION['user_id'], $item['id']]);
        $in_wardrobe = (bool) $wcheck->fetch();
      }
    ?>
      <div class="clothing-card">
        <div class="clothing-card-img">
          <?php if ($item['image_url']): ?>
            <img src="<?= htmlspecialchars($item['image_url']) ?>"
                 alt="<?= htmlspecialchars($item['name']) ?>">
          <?php else: ?>
            NO IMG
          <?php endif; ?>
        </div>
        <div class="clothing-card-brand"><?= htmlspecialchars($item['brand']) ?></div>
        <div class="clothing-card-name"><?= htmlspecialchars($item['name']) ?></div>
        <div class="clothing-card-footer">
          <div class="clothing-card-price">
            <?= $item['price'] ? number_format($item['price'], 2) . ' zł' : '—' ?>
          </div>
          <?php if (isset($_SESSION['user_id'])): ?>
            <form method="POST" action="/grailos/wardrobe_toggle.php">
              <?= csrf_field() ?>
              <input type="hidden" name="clothing_id" value="<?= $item['id'] ?>">
              <button type="submit" class="wardrobe-btn <?= $in_wardrobe ? 'in-wardrobe' : '' ?>"
                      title="<?= $in_wardrobe ? 'Usuń z szafy' : 'Dodaj do szafy' ?>">
                <?= $in_wardrobe ? '✓' : '+' ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3; padding:80px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:13px;">
      Brak wyników.
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>