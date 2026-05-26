<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php');
    exit;
}

require_once('../config/firebase.php');

$uid = $_SESSION['firebase_uid'];

// Fetch Products for this seller
$products = [];
$productsData = getData('products');
if ($productsData) {
    foreach ($productsData as $prodId => $prodData) {
        if (($prodData['sellerId'] ?? '') === $uid) {
            $products[] = array_merge(['Prod_ID' => $prodId], $prodData);
        }
    }
}

// Sort by date added descending
usort($products, function($a, $b) {
    return strtotime($b['dateAdded'] ?? '0') - strtotime($a['dateAdded'] ?? '0');
});

// Format sales figure with K/M abbreviation
function fmtSales($n) {
    if ($n >= 1_000_000) return '₱' . number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return '₱' . number_format($n / 1_000, 1) . 'K';
    return '₱' . number_format($n, 2);
}

// Fetch dynamic sales and rating
$totalSales   = 0;
$orderCount   = 0;
$sellerRating = "No ratings";

// Calculate sales from orders
$ordersData = getData('orders');
if ($ordersData) {
    foreach ($ordersData as $orderId => $orderData) {
        $status = $orderData['status'] ?? 'Processing';
        if ($status === 'Rejected') {
            continue;
        }
        $orderItems = getData("orders/{$orderId}/items");
        if ($orderItems) {
            foreach ($orderItems as $itemId => $itemData) {
                $productId = $itemData['productId'] ?? '';
                $product = getData("products/{$productId}");
                if ($product && ($product['sellerId'] ?? '') === $uid) {
                    $totalSales += ($itemData['subtotal'] ?? 0);
                }
            }
        }
        $orderCount++;
    }
}

// Calculate rating from feedbacks
$feedbacksData = getData('feedbacks');
if ($feedbacksData) {
    $ratings = [];
    foreach ($feedbacksData as $feedbackId => $feedbackData) {
        if (($feedbackData['sellerId'] ?? '') === $uid) {
            $ratings[] = $feedbackData['rating'] ?? 0;
        }
    }
    if (count($ratings) > 0) {
        $avgRating = array_sum($ratings) / count($ratings);
        $sellerRating = round($avgRating, 1) . ' ★';
    }
}

$title    = 'Seller Hub';
$basePath = '../';
include('../layout/layout.php');
?>

<?php if (!empty($_SESSION['flash'])): ?>
<div style="max-width:1000px;margin:16px auto 0;padding:0 20px;">
    <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:14px;color:#2e7d32;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_SESSION['flash']) ?>
    </div>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<!-- ══════════════════════════════════
     WELCOME STRIP
     Notice that we adjusted margin here.
══════════════════════════════════ -->
<div class="easy-strip" style="margin-top:16px;">
    <div>
        <h2>Seller Hub Overview 📈</h2>
        <p>Welcome back, <?= htmlspecialchars($_SESSION['display_name'] ?? 'there') ?>. Here's a quick look at your business.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="easy-btn" onclick="location.href='orders.php'" style="background:#fff;color:var(--text);border:1.5px solid var(--border);">View Orders</button>
        <button class="easy-btn" onclick="location.href='../product/add-product.php'">Add Product</button>
    </div>
</div>

<!-- ══════════════════════════════════
     QUICK STATS
══════════════════════════════════ -->
<div class="sec">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">
        <?php
        $stats = [
            ['icon'=>'📦', 'label'=>'Active Listings', 'val'=>count($products),                   'sub'=>null,           'link'=>null],
            ['icon'=>'💵', 'label'=>'Total Sales',     'val'=>fmtSales($totalSales),               'sub'=>$orderCount.' '.($orderCount===1?'order':'orders'), 'link'=>'orders.php'],
            ['icon'=>'⭐', 'label'=>'Feedback Rating', 'val'=>$sellerRating,                       'sub'=>null,           'link'=>null],
            ['icon'=>'🔄', 'label'=>'Open Returns',    'val'=>'0',                                 'sub'=>null,           'link'=>null],
        ];
        foreach ($stats as $s):
            $wrap = $s['link'] ? "<a href=\"{$s['link']}\" style=\"text-decoration:none;color:inherit;display:contents;\">" : '';
            $wrapEnd = $s['link'] ? '</a>' : '';
        ?>
            <?= $wrap ?>
            <div style="background:var(--bg);border-radius:12px;padding:20px;display:flex;align-items:center;gap:14px;<?= $s['link'] ? 'cursor:pointer;transition:box-shadow .15s;' : '' ?>"
                 <?= $s['link'] ? 'onmouseover="this.style.boxShadow=\'0 2px 12px rgba(0,0,0,.1)\'" onmouseout="this.style.boxShadow=\'\'"' : '' ?>>
                <span style="font-size:32px;"><?= $s['icon'] ?></span>
                <div>
                    <div style="font-size:22px;font-weight:700;line-height:1.2;"><?= $s['val'] ?></div>
                    <div style="font-size:12px;color:var(--muted);margin-top:2px;"><?= $s['label'] ?></div>
                    <?php if ($s['sub'] !== null): ?>
                        <div style="font-size:11px;color:var(--blue);margin-top:3px;font-weight:600;"><?= htmlspecialchars($s['sub']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?= $wrapEnd ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══════════════════════════════════
     YOUR PRODUCTS (PLACEHOLDERS)
══════════════════════════════════ -->
<div class="sec">
    <div class="sec-head">
        <h2>Active Listings</h2>
        <a href="#" class="see-all">Manage listings</a>
    </div>
    
    <div style="background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden;">
        <table style="width:100%; text-align:left; border-collapse:collapse; font-size:14px;">
            <thead style="background:var(--bg); color:var(--muted);">
                <tr>
                    <th style="padding:12px 16px; font-weight:600;">Item</th>
                    <th style="padding:12px 16px; font-weight:600;">Price</th>
                    <th style="padding:12px 16px; font-weight:600;">Available</th>
                    <th style="padding:12px 16px; font-weight:600;">Views</th>
                    <th style="padding:12px 16px; font-weight:600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $p): ?>
                    <tr style="border-top:1px solid var(--border);">
                        <td style="padding:16px;">
                            <div style="display:flex; align-items:center; gap:16px;">
                                <?php if (!empty($p['image'])): ?>
                                    <a href="../product/product.php?id=<?= urlencode($p['Prod_ID']) ?>">
                                        <img src="<?= htmlspecialchars($p['image']) ?>" alt="Product Image" style="width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid var(--border); background:#f7f7f7;">
                                    </a>
                                <?php else: ?>
                                    <a href="../product/product.php?id=<?= urlencode($p['Prod_ID']) ?>" style="text-decoration:none;">
                                        <div style="width:64px; height:64px; border-radius:8px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border:1px solid var(--border); color:var(--muted); font-size:24px;">📦</div>
                                    </a>
                                <?php endif; ?>
                                <div>
                                    <div style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                                        <a href="../product/product.php?id=<?= urlencode($p['Prod_ID']) ?>" style="font-weight:600; font-size:14px; text-decoration:none; color:var(--text); transition:color 0.15s;" onmouseover="this.style.color='var(--blue)'; this.style.textDecoration='underline'" onmouseout="this.style.color='var(--text)'; this.style.textDecoration='none'">
                                            <?= htmlspecialchars($p['title'] ?? '') ?>
                                        </a>
                                        <?php 
                                        $isAuc = isset($p['auctionData']);
                                        $aucActive = $isAuc && ($p['auctionData']['status'] ?? '') === 'active';
                                        if ($isAuc): 
                                        ?>
                                            <span style="font-size:10px; padding:2px 8px; border-radius:12px; font-weight:700; display:inline-flex; align-items:center; gap:2px; <?php echo $aucActive ? 'background:#e3f2fd; color:#1565c0; border:1px solid #bbdefb;' : 'background:#f5f5f5; color:#757575; border:1px solid #e0e0e0;'; ?>">
                                                <?= $aucActive ? '⚡ Live Auction' : '🔒 Auction Ended' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:12px; color:var(--muted); margin-top:2px;">Item number: <?= htmlspecialchars($p['Prod_ID']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="padding:16px;">
                            ₱<?= number_format($p['price'] ?? 0, 2) ?>
                            <?php if ($isAuc): ?>
                                <div style="font-size:11px; color:var(--muted); margin-top:2px;">(Auction Start)</div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:16px;"><?= htmlspecialchars($p['stock'] ?? 0) ?></td>
                        <td style="padding:16px;">0</td>
                        <td style="padding:16px;">
                            <div style="display:flex; gap:8px; align-items:center;">
                                <a href="../product/edit-product.php?id=<?= urlencode($p['Prod_ID']) ?>" style="text-decoration:none; color:var(--text); border:1px solid var(--border); background:#fff; padding:6px 12px; border-radius:16px; font-size:12px; transition:background 0.2s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='#fff'">Edit</a>
                                
                                <?php if ($aucActive): ?>
                                    <form action="../auction/stop_auction.php" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to manually stop this auction?');">
                                        <input type="hidden" name="auction_id" value="<?= htmlspecialchars($p['auctionData']['auctionId']) ?>">
                                        <input type="hidden" name="redirect" value="../seller/dashboard.php">
                                        <button type="submit" style="border:1px solid #ffeeba; background:#fff8e1; color:#856404; padding:6px 12px; border-radius:16px; cursor:pointer; font-family:var(--font); font-size:12px; font-weight:600; transition:background 0.2s;" onmouseover="this.style.background='#ffe082'" onmouseout="this.style.background='#fff8e1'">Stop Auction</button>
                                    </form>
                                <?php endif; ?>

                                <form action="../product/delete-product.php" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to permanently delete this listing?');">
                                    <input type="hidden" name="prod_id" value="<?= htmlspecialchars($p['Prod_ID']) ?>">
                                    <button type="submit" style="border:1px solid var(--border); background:#fff; color:#e53238; padding:6px 12px; border-radius:16px; cursor:pointer; font-family:var(--font); font-size:12px; transition:background 0.2s;" onmouseover="this.style.background='#ffebe8'" onmouseout="this.style.background='#fff'">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:24px; text-align:center; color:var(--muted);">You haven't listed any products yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('../layout/footer.php'); ?>
