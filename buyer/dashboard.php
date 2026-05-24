<?php
session_start();
if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth/signin.php');
    exit;
}
if (!empty($_SESSION['is_admin'])) {
    header('Location: ../admin/dashboard.php');
    exit;
}


require_once('../config/db.php');

// Fetch categories
$categories = [];
$res = $conn->query("SELECT * FROM Category WHERE Cat_Status='active' LIMIT 12");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch products
$latestProducts = [];
$res2 = $conn->query("SELECT * FROM Product ORDER BY Prod_ID DESC LIMIT 6");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $latestProducts[] = $row;
    }
}

// Fetch recent orders
$recentOrders = [];
$userId = $_SESSION['account_id'];
$res3 = $conn->prepare("SELECT * FROM `Order` WHERE Order_UserID = ? ORDER BY Order_Date DESC LIMIT 3");
$res3->bind_param("s", $userId);
$res3->execute();
$r = $res3->get_result();
while ($row = $r->fetch_assoc()) {
    $recentOrders[] = $row;
}
$res3->close();

// Fetch dashboard stats
$activeOrders = 0;
$completedOrders = 0;
$stmt4 = $conn->prepare("
    SELECT s.Ship_Status
    FROM `Order` o
    LEFT JOIN Shipment s ON o.Order_ID = s.Ship_OrderID
    WHERE o.Order_UserID = ?
");
$stmt4->bind_param("s", $userId);
$stmt4->execute();
$res4 = $stmt4->get_result();
while ($row = $res4->fetch_assoc()) {
    if (strtolower($row['Ship_Status'] ?? '') === 'delivered') {
        $completedOrders++;
    } else {
        $activeOrders++;
    }
}
$stmt4->close();

$stmt5 = $conn->prepare("SELECT COUNT(*) as c FROM Wishlist WHERE Wish_UserID = ?");
$stmt5->bind_param("s", $userId);
$stmt5->execute();
$wishlistItems = $stmt5->get_result()->fetch_assoc()['c'] ?? 0;
$stmt5->close();

$stmt6 = $conn->prepare("SELECT COUNT(*) as c FROM Feedback WHERE Feed_UserID = ?");
$stmt6->bind_param("s", $userId);
$stmt6->execute();
$feedbackScore = $stmt6->get_result()->fetch_assoc()['c'] ?? 0;
$stmt6->close();

// Fetch active auctions the buyer is bidding on
$activeBids = [];
$stmtBids = $conn->prepare("
    SELECT w.Wish_PriceAdd as My_Bid,
           p.Prod_ID, p.Prod_Title, p.Prod_Image,
           a.Auc_HighBid, a.Auc_StartPrice, a.Auc_EndDate, a.Auc_Status
    FROM Wishlist w
    JOIN Product p ON w.Wish_ProdID = p.Prod_ID
    JOIN Auction a ON a.Auc_ProdID = p.Prod_ID
    WHERE w.Wish_UserID = ? AND w.Wish_PriceAdd > 0 AND a.Auc_Status = 'Active'
    ORDER BY a.Auc_EndDate ASC
");
$stmtBids->bind_param("s", $userId);
$stmtBids->execute();
$resBids = $stmtBids->get_result();
while ($row = $resBids->fetch_assoc()) {
    $activeBids[] = $row;
}
$stmtBids->close();

// Fetch won auctions (ended, user is highest bidder, not yet paid)
$wonAuctions = [];
$stmtWon = $conn->prepare("
    SELECT a.Auc_ID, a.Auc_HighBid, a.Auc_EndDate,
           p.Prod_ID, p.Prod_Title, p.Prod_Image,
           MAX(b.Bid_Amount) as My_Winning_Bid
    FROM Bid b
    JOIN Auction a ON b.Bid_AucID = a.Auc_ID
    JOIN Product p ON a.Auc_ProdID = p.Prod_ID
    WHERE b.Bid_UserID = ?
      AND a.Auc_Status = 'Ended'
      AND b.Bid_Amount >= a.Auc_HighBid
    GROUP BY a.Auc_ID
");
$stmtWon->bind_param("s", $userId);
$stmtWon->execute();
$resWon = $stmtWon->get_result();
while ($row = $resWon->fetch_assoc()) { $wonAuctions[] = $row; }
$stmtWon->close();

$title    = 'Dashboard';
$basePath = '../';
include('../layout/layout.php');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.wish-btn i { font-size: 1.2rem !important; color: var(--text); }
.wish-btn i.bi-heart-fill { color: var(--red); }
.card-cart-btn {
    width: 100%;
    margin-top: 8px;
    padding: 7px 0;
    border-radius: 20px;
    border: 1px solid var(--blue);
    background: #fff;
    color: var(--blue);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.card-cart-btn:hover { background: #f0f7ff; }
</style>

<!-- ══════════════════════════════════
     WELCOME STRIP
══════════════════════════════════ -->
<div class="easy-strip" style="margin-top:12px;">
    <div>
        <h2>Welcome back, <?= htmlspecialchars($_SESSION['display_name'] ?? 'there') ?>! 👋</h2>
        <p>Here's what's happening with your account today.</p>
    </div>
    <a href="../product/search.php" class="easy-btn">Shop now</a>
</div>

<!-- ══════════════════════════════════
     QUICK STATS
══════════════════════════════════ -->
<div class="sec">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">

        <?php
        $stats = [
            ['icon'=>'🛒', 'label'=>'Active Orders',   'val'=> number_format($activeOrders)],
            ['icon'=>'❤️', 'label'=>'Watchlist Items',  'val'=> number_format($wishlistItems)],
            ['icon'=>'📦', 'label'=>'Completed Orders', 'val'=> number_format($completedOrders)],
            ['icon'=>'⭐', 'label'=>'Feedback Left',    'val'=> number_format($feedbackScore)],
        ];
        foreach ($stats as $s): ?>
            <div style="background:var(--bg);border-radius:12px;padding:20px;display:flex;align-items:center;gap:14px;">
                <span style="font-size:32px;"><?= $s['icon'] ?></span>
                <div>
                    <div style="font-size:22px;font-weight:700;"><?= $s['val'] ?></div>
                    <div style="font-size:12px;color:var(--muted);"><?= $s['label'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
</div>

<!-- ══════════════════════════════════
     RECENT ORDERS
══════════════════════════════════ -->
<?php if (count($recentOrders) > 0): ?>
<div class="sec">
    <div class="sec-head">
        <h2>Recent Orders</h2>
        <a href="../buyer/orders.php" class="see-all" target="_blank">See all</a>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($recentOrders as $ord): ?>
            <div style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-weight:700;margin-bottom:4px;">Order #<?= htmlspecialchars($ord['Order_ID']) ?></div>
                    <div style="font-size:14px;color:var(--muted);">Placed on <?= date('M j, Y', strtotime($ord['Order_Date'])) ?> • Total: ₱<?= number_format($ord['Order_Total'], 2) ?></div>
                </div>
                <a href="../shipment/shipment.php?order_id=<?= urlencode($ord['Order_ID']) ?>" style="background:var(--blue);color:#fff;padding:8px 24px;border-radius:24px;text-decoration:none;font-weight:600;font-size:14px;">Track Package</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════
     ACTIVE BIDS
══════════════════════════════════ -->
<?php if (!empty($activeBids)): ?>
<div class="sec">
    <div class="sec-head">
        <h2>📣 Active Bids</h2>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($activeBids as $bid):
            $timeLeft = strtotime($bid['Auc_EndDate']) - time();
            $days = floor($timeLeft / 86400);
            $hours = floor(($timeLeft % 86400) / 3600);
            $mins = floor(($timeLeft % 3600) / 60);
            $timeStr = $days > 0 ? "{$days}d {$hours}h left" : "{$hours}h {$mins}m left";
            if ($timeLeft < 0) $timeStr = "Ended";
            $currentBid = $bid['Auc_HighBid'] > 0 ? $bid['Auc_HighBid'] : $bid['Auc_StartPrice'];
            $isWinning = floatval($bid['My_Bid']) >= floatval($currentBid);
        ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px;display:flex;gap:16px;align-items:center;">
            <?php if (!empty($bid['Prod_Image'])): ?>
                <img src="<?= htmlspecialchars($bid['Prod_Image']) ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border);flex-shrink:0;">
            <?php else: ?>
                <div style="width:64px;height:64px;background:#f0f0f0;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--muted);flex-shrink:0;"><i class="bi bi-box" style="font-size:1.8rem;"></i></div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
                <a href="../product/product.php?id=<?= urlencode($bid['Prod_ID']) ?>" style="font-weight:700;font-size:14px;color:var(--blue);text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($bid['Prod_Title']) ?></a>
                <div style="font-size:13px;color:var(--muted);margin-top:4px;">
                    Current: <strong>₱<?= number_format($currentBid, 2) ?></strong>
                    &bull; Your bid: ₱<?= number_format($bid['My_Bid'], 2) ?>
                    &bull; <i class="bi bi-clock"></i> <?= $timeStr ?>
                </div>
            </div>
            <div style="flex-shrink:0;text-align:right;">
                <?php if ($isWinning): ?>
                    <span style="background:#d4edda;color:#155724;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;"><i class="bi bi-trophy-fill"></i> Winning</span>
                <?php else: ?>
                    <span style="background:#fff3cd;color:#856404;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;"><i class="bi bi-exclamation-circle-fill"></i> Outbid</span>
                <?php endif; ?>
                <a href="../product/product.php?id=<?= urlencode($bid['Prod_ID']) ?>" style="display:block;margin-top:8px;font-size:13px;color:var(--blue);">Bid again &rarr;</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════
     WON AUCTIONS (PENDING PAYMENT)
══════════════════════════════════ -->
<?php if (!empty($wonAuctions)): ?>
<div class="sec">
    <div class="sec-head">
        <h2>🏆 Won Auctions</h2>
    </div>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($wonAuctions as $won): ?>
        <div style="background:#f0fff4;border:1px solid #c3e6cb;border-radius:12px;padding:16px;display:flex;gap:16px;align-items:center;">
            <?php if (!empty($won['Prod_Image'])): ?>
                <img src="<?= htmlspecialchars($won['Prod_Image']) ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #c3e6cb;flex-shrink:0;">
            <?php else: ?>
                <div style="width:64px;height:64px;background:#e2f0e5;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#155724;flex-shrink:0;"><i class="bi bi-box" style="font-size:1.8rem;"></i></div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
                <a href="../auction/auction.php?id=<?= urlencode($won['Auc_ID']) ?>" style="font-weight:700;font-size:14px;color:#155724;text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($won['Prod_Title']) ?></a>
                <div style="font-size:13px;color:#155724;margin-top:4px;">
                    Winning Bid: <strong>₱<?= number_format($won['My_Winning_Bid'], 2) ?></strong>
                    &bull; Ended on <?= date('M j, Y', strtotime($won['Auc_EndDate'])) ?>
                </div>
            </div>
            <div style="flex-shrink:0;text-align:right;">
                <a href="../checkout/checkout.php?auction_id=<?= urlencode($won['Auc_ID']) ?>" style="background:#28a745;color:#fff;padding:8px 24px;border-radius:24px;text-decoration:none;font-weight:600;font-size:14px;display:inline-block;">Pay Now</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════
     RECOMMENDED FOR YOU
══════════════════════════════════ -->
<div class="sec">
    <div class="sec-head">
        <h2>Recommended for you</h2>
        <a href="../product/search.php" class="see-all">See all</a>
    </div>
    <div class="scroll-row">
        <?php foreach ($latestProducts as $prod): ?>
            <a href="../product/product.php?id=<?= urlencode($prod['Prod_ID']) ?>" class="prod-card" style="text-decoration:none; color:inherit; transition:transform 0.2s, box-shadow 0.2s;">
                <div class="prod-img">
                    <?php if (!empty($prod['Prod_Image'])): ?>
                        <img src="<?= htmlspecialchars($prod['Prod_Image']) ?>" alt="Product" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-box" style="font-size:5rem;color:var(--muted);"></i>
                    <?php endif; ?>
                    <button class="wish-btn" onclick="event.preventDefault(); event.stopPropagation(); toggleWish(this, <?= htmlspecialchars(json_encode($prod['Prod_ID']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode(strip_tags($prod['Prod_Title'])), ENT_QUOTES, 'UTF-8') ?>)">
                        <i class="bi bi-heart"></i>
                    </button>
                </div>
                <p class="prod-cond">Brand New</p>
                <p class="prod-name"><?= htmlspecialchars($prod['Prod_Title']) ?></p>
                <div class="prod-prices">
                    <span class="prod-price">₱<?= number_format($prod['Prod_Price'], 2) ?></span>
                </div>
                <button class="card-cart-btn"
                        onclick="event.preventDefault(); event.stopPropagation(); addToCart(this, <?= htmlspecialchars(json_encode($prod['Prod_ID']), ENT_QUOTES, 'UTF-8') ?>)">
                    <i class="bi bi-cart-plus"></i> Add to cart
                </button>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php include('../layout/footer.php'); ?>

<script>
function toggleWish(btn, prodId, name) {
    const watching = btn.querySelector('i').classList.contains('bi-heart-fill');
    const url = watching ? '../wishlist/remove.php' : '../wishlist/add.php';
    btn.disabled = true;
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'prod_id=' + encodeURIComponent(prodId)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showToast(data.message || 'Error'); btn.disabled = false; return; }
        const icon = btn.querySelector('i');
        if (watching) {
            icon.classList.replace('bi-heart-fill', 'bi-heart');
            showToast(`Removed "${name}" from Watchlist`);
        } else {
            icon.classList.replace('bi-heart', 'bi-heart-fill');
            showToast(`❤️ "${name}" added to Watchlist`);
        }
        btn.disabled = false;
    })
    .catch(() => { showToast('Something went wrong'); btn.disabled = false; });
}

function addToCart(btn, prodId) {
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding…';
    fetch('../cart/add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'prod_id=' + encodeURIComponent(prodId) + '&qty=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('🛒 ' + data.message);
            const badge = document.getElementById('cartBadge');
            if (badge) badge.textContent = data.cart_count;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Added';
            setTimeout(() => { btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart'; btn.disabled = false; }, 2000);
        } else {
            showToast(data.message || 'Could not add to cart');
            btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart';
            btn.disabled = false;
        }
    })
    .catch(() => { showToast('Something went wrong'); btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart'; btn.disabled = false; });
}
</script>
