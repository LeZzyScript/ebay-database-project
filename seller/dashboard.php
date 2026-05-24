<?php
session_start();
if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth/signin.php');
    exit;
}

require_once('../config/db.php');

// Fetch Seller ID
$sellId = null;
$stmt = $conn->prepare("SELECT Sell_ID FROM Seller WHERE Sell_UserID = ?");
$stmt->bind_param("s", $_SESSION['account_id']);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $sellId = $row['Sell_ID'];
}
$stmt->close();

// Fetch Products for this seller
$products = [];
if ($sellId) {
    // Ensure table exists to prevent crash if visited before adding a product
    $conn->query("CREATE TABLE IF NOT EXISTS Product (
        Prod_ID     CHAR(8) PRIMARY KEY,
        Prod_SellID CHAR(8) NOT NULL,
        Prod_CatID  CHAR(8),
        Prod_Title  VARCHAR(100) NOT NULL,
        Prod_Desc   VARCHAR(255) NOT NULL,
        Prod_Price  DECIMAL(10,2) NOT NULL,
        Prod_Image  VARCHAR(255) DEFAULT NULL,
        Prod_Stock  INT NOT NULL DEFAULT 0,
        Prod_Status VARCHAR(10) NOT NULL DEFAULT 'active',
        Prod_DateAdd DATE NOT NULL,
        Prod_DateUpd DATE,
        FOREIGN KEY (Prod_SellID) REFERENCES Seller(Sell_ID),
        FOREIGN KEY (Prod_CatID) REFERENCES Category(Cat_ID)
    )");

    $stmt2 = $conn->prepare("SELECT * FROM Product WHERE Prod_SellID = ? ORDER BY Prod_DateAdd DESC");
    $stmt2->bind_param("s", $sellId);
    $stmt2->execute();
    $prodRes = $stmt2->get_result();
    while ($p = $prodRes->fetch_assoc()) {
        $products[] = $p;
    }
    $stmt2->close();
}

// Format sales figure with K/M abbreviation
function fmtSales($n) {
    if ($n >= 1_000_000) return '₱' . number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return '₱' . number_format($n / 1_000, 1) . 'K';
    return '₱' . number_format($n, 2);
}

// Fetch dynamic sales and rating
$totalSales   = 0;
$orderCount   = 0;
$sellerRating = "0.0 ★";

if ($sellId) {
    // Sales + order count
    $stmtS = $conn->prepare("
        SELECT SUM(oi.Item_Sub) as total_sales,
               COUNT(DISTINCT o.Order_ID) as order_count
        FROM OrderItem oi
        JOIN Product p  ON oi.Item_ProdID  = p.Prod_ID
        JOIN `Order` o  ON oi.Item_OrderID = o.Order_ID
        WHERE p.Prod_SellID = ?
    ");
    $stmtS->bind_param("s", $sellId);
    $stmtS->execute();
    $salesRow   = $stmtS->get_result()->fetch_assoc();
    $totalSales = $salesRow['total_sales'] ?? 0;
    $orderCount = (int)($salesRow['order_count'] ?? 0);
    $stmtS->close();
    
    // Rating
    $stmtR = $conn->prepare("SELECT AVG(Feed_Rating) as avg_rating, COUNT(*) as c FROM Feedback WHERE Feed_SellID = ?");
    $stmtR->bind_param("s", $sellId);
    $stmtR->execute();
    $resR = $stmtR->get_result()->fetch_assoc();
    if ($resR['c'] > 0) {
        $sellerRating = round($resR['avg_rating'], 1) . ' ★';
    } else {
        $sellerRating = "No ratings";
    }
    $stmtR->close();
}

$title    = 'Seller Hub';
$basePath = '../';
include('../layout/layout.php');
?>

<!-- ══════════════════════════════════
     WELCOME STRIP
══════════════════════════════════ -->
<div class="easy-strip" style="margin-top:12px;">
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
                                <?php if (!empty($p['Prod_Image'])): ?>
                                    <img src="<?= htmlspecialchars($p['Prod_Image']) ?>" alt="Product Image" style="width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid var(--border); background:#f7f7f7;">
                                <?php else: ?>
                                    <div style="width:64px; height:64px; border-radius:8px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border:1px solid var(--border); color:var(--muted); font-size:24px;">📦</div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:600; font-size:14px; margin-bottom:4px;"><?= htmlspecialchars($p['Prod_Title']) ?></div>
                                    <div style="font-size:12px; color:var(--muted);">Item number: <?= htmlspecialchars($p['Prod_ID']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="padding:16px;">₱<?= number_format($p['Prod_Price'], 2) ?></td>
                        <td style="padding:16px;"><?= htmlspecialchars($p['Prod_Stock']) ?></td>
                        <td style="padding:16px;">0</td>
                        <td style="padding:16px;">
                            <div style="display:flex; gap:8px;">
                                <a href="../product/edit-product.php?id=<?= urlencode($p['Prod_ID']) ?>" style="text-decoration:none; color:var(--text); border:1px solid var(--border); background:#fff; padding:6px 12px; border-radius:16px; font-size:12px; transition:background 0.2s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='#fff'">Edit</a>
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
