<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Wishlists';

$wishlists = db()->fetchAll("SELECT w.*, c.name as customer_name, c.phone, p.name as product_name, p.price, p.sku FROM wishlists w JOIN customers c ON w.customer_id=c.id JOIN products p ON w.product_id=p.id ORDER BY w.created_at DESC");

// Group by customer
$by_customer = [];
foreach ($wishlists as $w) {
    $by_customer[$w['customer_id']]['name']  = $w['customer_name'];
    $by_customer[$w['customer_id']]['phone'] = $w['phone'];
    $by_customer[$w['customer_id']]['items'][] = $w;
}

// Most wished products
$top_wished = db()->fetchAll("SELECT p.name, p.price, COUNT(w.id) as wish_count FROM wishlists w JOIN products p ON w.product_id=p.id GROUP BY w.product_id ORDER BY wish_count DESC LIMIT 10");

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Wishlists</h1>
        <p>Track customer wishlist data — <?= count($wishlists) ?> total items across <?= count($by_customer) ?> customers</p>
    </div>
</div>

<div class="grid-2 fade-in">
    <!-- By Customer -->
    <div class="card">
        <div class="card-header"><span class="card-title">Wishlist by Customer</span></div>
        <div class="card-body" style="padding:0;">
            <?php foreach($by_customer as $cid => $cd): ?>
            <div style="padding:14px 20px;border-bottom:1px solid var(--border-color);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div>
                        <span class="font-bold"><?= htmlspecialchars($cd['name']) ?></span>
                        <span class="text-muted" style="font-size:0.75rem;margin-left:8px;"><?= $cd['phone'] ?></span>
                    </div>
                    <span class="badge badge-info"><?= count($cd['items']) ?> items</span>
                </div>
                <?php foreach($cd['items'] as $item): ?>
                <div style="display:flex;justify-content:space-between;font-size:0.8rem;padding:4px 0;color:var(--text-secondary);">
                    <span><?= htmlspecialchars($item['product_name']) ?></span>
                    <span class="text-gold font-bold"><?= formatCurrency($item['price']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php if(empty($by_customer)): ?>
            <div class="empty-state"><i class="fa-solid fa-heart"></i><p>No wishlist data yet</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Wished Products -->
    <div class="card">
        <div class="card-header"><span class="card-title">Most Wished Products</span></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>#</th><th>Product</th><th>Price</th><th>Wishes</th></tr></thead>
                <tbody>
                    <?php foreach($top_wished as $i=>$p): ?>
                    <tr>
                        <td class="text-muted"><?= $i+1 ?></td>
                        <td class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= formatCurrency($p['price']) ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span class="text-gold font-bold"><?= $p['wish_count'] ?></span>
                                <i class="fa-solid fa-heart" style="color:var(--danger);font-size:0.8rem;"></i>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($top_wished)): ?><tr><td colspan="4" class="text-center text-muted">No data</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
