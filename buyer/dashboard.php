<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php');
    exit;
}
if (!empty($_SESSION['is_admin'])) {
    header('Location: ../admin/dashboard.php');
    exit;
}
if (!empty($_SESSION['is_seller'])) {
    header('Location: ../seller/dashboard.php');
    exit;
}



require_once('../config/firebase.php');

// Fetch categories
$categoriesData = getData('categories');
$categories = [];
if ($categoriesData) {
    foreach ($categoriesData as $catId => $catData) {
        if (($catData['status'] ?? '') === 'active') {
            $categories[] = array_merge(['Cat_ID' => $catId], $catData);
        }
    }
}
$categories = array_slice($categories, 0, 12);

// Fetch products
$productsData = getData('products');
$latestProducts = [];
if ($productsData) {
    $productsArray = [];
    foreach ($productsData as $prodId => $prodData) {
        $productsArray[] = [
            'Prod_ID'    => $prodId,
            'Prod_Title' => $prodData['title'] ?? '',
            'Prod_Price' => $prodData['price'] ?? 0,
            'Prod_Image' => $prodData['image'] ?? '',
            'dateAdded'  => $prodData['dateAdded'] ?? '1970-01-01'
        ];
    }
    // Sort by dateAdded (descending)
    usort($productsArray, function($a, $b) {
        return strtotime($b['dateAdded']) - strtotime($a['dateAdded']);
    });
    $latestProducts = array_slice($productsArray, 0, 6);
}

// Fetch recent orders
$recentOrders = [];
$uid = $_SESSION['firebase_uid'];
$ordersData = getData('orders');
if ($ordersData) {
    foreach ($ordersData as $orderId => $orderData) {
        if (($orderData['userId'] ?? '') === $uid) {
            $recentOrders[] = [
                'Order_ID'    => $orderId,
                'Order_Date'  => $orderData['date'] ?? date('Y-m-d'),
                'Order_Total' => $orderData['total'] ?? 0,
                'status'      => $orderData['status'] ?? 'Pending'
            ];
        }
    }
}
// Sort by date (descending)
usort($recentOrders, function($a, $b) {
    return strtotime($b['Order_Date']) - strtotime($a['Order_Date']);
});
$recentOrders = array_slice($recentOrders, 0, 3);

// Fetch dashboard stats
$activeOrders = 0;
$completedOrders = 0;
if ($ordersData) {
    foreach ($ordersData as $orderId => $orderData) {
        if (($orderData['userId'] ?? '') === $uid) {
            $status = $orderData['status'] ?? '';
            if ($status === 'Processing' || $status === 'Shipped') {
                $activeOrders++;
            } elseif ($status === 'Delivered') {
                $completedOrders++;
            }
        }
    }
}

// Fetch wishlist items count
$wishlistData = getData("wishlists/{$uid}/items");
$wishlistItems = $wishlistData ? count($wishlistData) : 0;

// Fetch feedback score (dynamic count from database)
$feedbackScore = 0;
$allFeedbacks = getData('feedbacks') ?: [];
foreach ($allFeedbacks as $fId => $fData) {
    if (($fData['userId'] ?? '') === $uid) {
        $feedbackScore++;
    }
}

// Fetch active auctions the buyer is bidding on
$activeBids = [];
$auctionsData = getData('auctions');
if ($auctionsData) {
    foreach ($auctionsData as $auctionId => $auctionData) {
        if (($auctionData['status'] ?? '') === 'active') {
            $productId = $auctionData['productId'] ?? '';
            // Auto-close if past end date
            if (strtotime($auctionData['endDate'] ?? '') < time()) {
                updateData("auctions/{$auctionId}/status", 'ended');
                updateData("products/{$productId}/auctionData/status", 'ended');
                $auctionData['status'] = 'ended';
                $auctionsData[$auctionId]['status'] = 'ended';
            }
        }
        if (($auctionData['status'] ?? '') === 'active') {
            $productId = $auctionData['productId'] ?? '';
            $bidsData = getData("auctions/{$auctionId}/bids");
            if ($bidsData) {
                foreach ($bidsData as $bidId => $bidData) {
                    if ($bidData['userId'] === $uid) {
                        $product = getData("products/{$productId}");
                        if ($product) {
                            $activeBids[] = [
                                'My_Bid' => $bidData['amount'],
                                'Prod_ID' => $productId,
                                'Prod_Title' => $product['title'] ?? '',
                                'Prod_Image' => $product['image'] ?? '',
                                'Auc_HighBid' => $auctionData['currentHighBid'] ?? 0,
                                'Auc_StartPrice' => $auctionData['startPrice'] ?? 0,
                                'Auc_EndDate' => $auctionData['endDate'] ?? '',
                                'Auc_Status' => $auctionData['status'] ?? '',
                                'Auc_ID' => $auctionId
                            ];
                        }
                        break;
                    }
                }
            }
        }
    }
}

// Fetch won auctions (ended, user is highest bidder, not yet paid)
$wonAuctions = [];
if ($auctionsData) {
    foreach ($auctionsData as $auctionId => $auctionData) {
        if (($auctionData['status'] ?? '') === 'ended') {
            $productId = $auctionData['productId'] ?? '';
            $bidsData = getData("auctions/{$auctionId}/bids");
            if ($bidsData) {
                $myTopBid = 0;
                foreach ($bidsData as $bidData) {
                    if ($bidData['userId'] === $uid && $bidData['amount'] > $myTopBid) {
                        $myTopBid = $bidData['amount'];
                    }
                }
                if ($myTopBid >= ($auctionData['currentHighBid'] ?? 0)) {
                    $product = getData("products/{$productId}");
                    if ($product) {
                        $wonAuctions[] = [
                            'Auc_ID' => $auctionId,
                            'Auc_HighBid' => $auctionData['currentHighBid'] ?? 0,
                            'Auc_EndDate' => $auctionData['endDate'] ?? '',
                            'Prod_ID' => $productId,
                            'Prod_Title' => $product['title'] ?? '',
                            'Prod_Image' => $product['image'] ?? '',
                            'My_Winning_Bid' => $myTopBid
                        ];
                    }
                }
            }
        }
    }
}

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
                <?php if ($ord['status'] === 'Pending'): ?>
                    <a href="../buyer/orders.php" style="background:#f7f7f7;color:var(--muted);border:1px solid var(--border);padding:8px 20px;border-radius:24px;text-decoration:none;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="bi bi-hourglass-split"></i> Awaiting Acceptance</a>
                <?php elseif ($ord['status'] === 'Rejected'): ?>
                    <span style="background:#ffebe8;color:#e53238;border:1px solid #ffcdd2;padding:6px 16px;border-radius:24px;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:6px;"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                <?php else: ?>
                    <a href="../shipment/shipment.php?order_id=<?= urlencode($ord['Order_ID']) ?>" style="background:var(--blue);color:#fff;padding:8px 24px;border-radius:24px;text-decoration:none;font-weight:600;font-size:14px;">Track Package</a>
                <?php endif; ?>
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
