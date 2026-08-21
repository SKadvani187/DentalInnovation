<?php
// GET /api/v1/products.php           -> list (filters: category, search, sort, min, max, page, limit)
// GET /api/v1/products.php?slug=p-001 -> single product
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$db = db();
// Real approved-review count + average per product (so cards show genuine review data, not sales).
$sel = "p.*, c.slug AS category_slug, c.name AS category_name,
        (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id=p.id AND pr.is_approved=1 AND pr.is_deleted=0) AS review_count,
        (SELECT AVG(pr.rating) FROM product_reviews pr WHERE pr.product_id=p.id AND pr.is_approved=1 AND pr.is_deleted=0) AS review_avg";

// --- single by slug ---
$slug = qstr('slug');
if ($slug !== '') {
    $row = $db->fetchOne(
        "SELECT $sel FROM products p LEFT JOIN categories c ON p.category_id=c.id
         WHERE p.slug=? AND p.is_active=1", [$slug]
    );
    if ($row) { jsonOut(['success' => true, 'product' => mapProduct($row)]); }
    // Not a product — try combos (they now carry full detail and open as a product page).
    $combo = $db->fetchOne("SELECT * FROM combos WHERE slug=? AND is_active=1 AND is_deleted=0", [$slug]);
    if ($combo) { jsonOut(['success' => true, 'product' => mapCombo($combo)]); }
    jsonErr('Product not found', 404);
}

// --- list ---
$where = ['p.is_active=1'];
$params = [];

$cat = qstr('category');
if ($cat !== '') { $where[] = 'c.slug = ?'; $params[] = $cat; }

$search = qstr('search');
if ($search !== '') { $where[] = '(p.name LIKE ? OR p.description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$min = qint('min', 0);
$max = qint('max', 0);
$priceExpr = 'COALESCE(p.discount_price, p.price)';
if ($min > 0) { $where[] = "$priceExpr >= ?"; $params[] = $min; }
if ($max > 0) { $where[] = "$priceExpr <= ?"; $params[] = $max; }

$whereStr = implode(' AND ', $where);

// sort
$sort = qstr('sort', 'all');
$order = match ($sort) {
    'price-asc'  => "$priceExpr ASC",
    'price-desc' => "$priceExpr DESC",
    'discount'   => 'p.discount_percent DESC',
    'rating'     => 'p.total_sales DESC',
    default      => 'p.id ASC',
};

// pagination
$page  = max(1, qint('page', 1));
$limit = qint('limit', 0);                 // 0 = no limit (return all)
$total = (int)$db->fetchOne(
    "SELECT COUNT(*) cnt FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr", $params
)['cnt'];

$sql = "SELECT $sel FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr ORDER BY $order";
if ($limit > 0) {
    $offset = ($page - 1) * $limit;
    $sql .= " LIMIT $limit OFFSET $offset";
}
$rows = $db->fetchAll($sql, $params);
$products = array_map('mapProduct', $rows);

jsonOut([
    'success'  => true,
    'total'    => $total,
    'page'     => $page,
    'limit'    => $limit,
    'count'    => count($products),
    'products' => $products,
]);
