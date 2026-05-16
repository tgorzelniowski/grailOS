<?php require_once 'includes/header.php'; ?>

<!-- NAGŁÓWEK STRONY -->
<section class="page-header">
  <div class="page-header-tag">/// Browse</div>
  <h1 class="page-title">CATALOG</h1>
  <p class="page-sub">Znajdź swój grail. Przeglądaj, filtruj, inspiruj się.</p>
</section>

<!-- FILTRY -->
<div class="catalog-filters">
  <form method="GET" action="/grailos/catalog.php" class="filters-form">

    <input
      type="text"
      name="search"
      placeholder="Szukaj marki lub nazwy..."
      value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
      class="filter-input"
    >

    <select name="category" class="filter-select">
      <option value="">Wszystkie kategorie</option>
      <option value="hoodie"   <?= ($_GET['category'] ?? '') === 'hoodie'   ? 'selected' : '' ?>>Hoodie</option>
      <option value="tshirt"   <?= ($_GET['category'] ?? '') === 'tshirt'   ? 'selected' : '' ?>>T-Shirt</option>
      <option value="pants"    <?= ($_GET['category'] ?? '') === 'pants'    ? 'selected' : '' ?>>Spodnie</option>
      <option value="jacket"   <?= ($_GET['category'] ?? '') === 'jacket'   ? 'selected' : '' ?>>Kurtka</option>
      <option value="sneakers" <?= ($_GET['category'] ?? '') === 'sneakers' ? 'selected' : '' ?>>Sneakersy</option>
      <option value="cap"      <?= ($_GET['category'] ?? '') === 'cap'      ? 'selected' : '' ?>>Czapka</option>
      <option value="other"    <?= ($_GET['category'] ?? '') === 'other'    ? 'selected' : '' ?>>Inne</option>
    </select>

    <select name="sort" class="filter-select">
      <option value="newest" <?= ($_GET['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Najnowsze</option>
      <option value="price_asc"  <?= ($_GET['sort'] ?? '') === 'price_asc'  ? 'selected' : '' ?>>Cena: rosnąco</option>
      <option value="price_desc" <?= ($_GET['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Cena: malejąco</option>
    </select>

    <button type="submit" class="btn">Szukaj</button>

    <?php if (!empty($_GET['search']) || !empty($_GET['category'])): ?>
      <a href="/grailos/catalog.php" class="btn btn-outline">Resetuj</a>
    <?php endif; ?>

  </form>
</div>

<!-- SIATKA PRODUKTÓW -->
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

  $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

  $sort = match($_GET['sort'] ?? 'newest') {
    'price_asc'  => 'ORDER BY price ASC',
    'price_desc' => 'ORDER BY price DESC',
    default      => 'ORDER BY created_at DESC',
  };

  $stmt = $pdo->prepare("SELECT * FROM clothing $where $sort");
  $stmt->execute($params);
  $items = $stmt->fetchAll();
?>

<div class="section-label">
  /// <?= count($items) ?> <?= count($items) === 1 ? 'produkt' : 'produktów' ?> w katalogu
</div>

<div class="clothing-grid">
  <?php if ($items): ?>
    <?php foreach ($items as $item): ?>
      <div class="clothing-card">
        <div class="clothing-card-img">
          <?php if ($item['image_url']): ?>
            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
          <?php else: ?>
            NO IMG
          <?php endif; ?>
        </div>
        <div class="clothing-card-brand"><?= htmlspecialchars($item['brand']) ?></div>
        <div class="clothing-card-name"><?= htmlspecialchars($item['name']) ?></div>
        <div class="clothing-card-price">
          <?= $item['price'] ? number_format($item['price'], 2) . ' zł' : '—' ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="clothing-card" style="grid-column: span 3; text-align:center; padding: 80px; color: #444; letter-spacing: 3px; text-transform: uppercase; font-size: 13px;">
      Brak wyników. Spróbuj innej frazy.
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>