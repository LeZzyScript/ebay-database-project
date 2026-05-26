<?php
session_start();
require_once('../config/firebase.php');

$prodId = trim($_GET['id'] ?? '');
if (!$prodId) {
    header('Location: ../index.php');
    exit;
}

// Require login — redirect back here after sign-in
if (!isset($_SESSION['firebase_uid'])) {
    $redirect = urlencode('product/product.php?id=' . $prodId);
    header('Location: ../auth/signin.php?redirect=' . $redirect);
    exit;
}

// Fetch product data
$product = getData("products/{$prodId}");

if (!$product) {
    $_SESSION['flash'] = "Product not found.";
    header('Location: ../index.php');
    exit;
}

// Fetch category data if categoryId exists
$category = null;
if (isset($product['categoryId'])) {
    $category = getData("categories/{$product['categoryId']}");
}

// Fetch seller data
$seller = null;
$sellerUser = null;
if (isset($product['sellerId'])) {
    $sellerId = $product['sellerId'];
    // 1. Direct lookup by User ID
    $userData = getData("users/{$sellerId}");
    if ($userData && isset($userData['sellerData'])) {
        $seller = $userData['sellerData'];
        $sellerUser = $userData['profile'] ?? null;
    } else {
        // 2. Fallback search by Seller ID (e.g. SELL0001)
        $allUsers = getData('users');
        if ($allUsers) {
            foreach ($allUsers as $uKey => $uData) {
                if (isset($uData['sellerData']['sellerId']) && $uData['sellerData']['sellerId'] === $sellerId) {
                    $seller = $uData['sellerData'];
                    $sellerUser = $uData['profile'] ?? null;
                    break;
                }
            }
        }
    }
}

// Check if auction
$isAuction = false;
$auction = null;
if (isset($product['auctionData'])) {
    $isAuction = true;
    $auction = $product['auctionData'];
    // Auto-close expired auctions
    if ($auction['status'] === 'active' && strtotime($auction['endDate']) < time()) {
        updateData("products/{$prodId}/auctionData/status", 'ended');
        updateData("auctions/{$auction['auctionId']}/status", 'ended');
        $auction['status'] = 'ended';
    }
    
    // Count bids
    $totalBids = 0;
    $bids = getData("auctions/{$auction['auctionId']}/bids");
    if ($bids) {
        $totalBids = count($bids);
    }
}

// Fetch seller rating (simplified - would need order/feedback data in production)
$sellerRating = 0;
$sellerReviews = 0;

// Fetch related products — same category, exclude current
$related = [];
if (isset($product['categoryId'])) {
    $allProducts = getData('products');
    if ($allProducts) {
        foreach ($allProducts as $relId => $relData) {
            if ($relId !== $prodId && isset($relData['categoryId']) && $relData['categoryId'] === $product['categoryId']) {
                $related[] = array_merge(['Prod_ID' => $relId], $relData);
            }
        }
    }
}
// Fall back to latest products if not enough same-category results
if (count($related) < 4) {
    $related = [];
    $allProducts = getData('products');
    if ($allProducts) {
        foreach ($allProducts as $relId => $relData) {
            if ($relId !== $prodId) {
                $related[] = array_merge(['Prod_ID' => $relId], $relData);
            }
        }
    }
    // Sort by dateAdded
    usort($related, function($a, $b) {
        return strtotime($b['dateAdded'] ?? '1970-01-01') - strtotime($a['dateAdded'] ?? '1970-01-01');
    });
    $related = array_slice($related, 0, 10);
}

// Check if already in wishlist
$isWishlisted = false;
$uid = getCurrentUserId();
if ($uid) {
    $wishlist = getData("wishlists/{$uid}/items/{$prodId}");
    $isWishlisted = ($wishlist !== null);
}

$isOwner = false;
if ($uid && isset($product['sellerId'])) {
    $currentUser = getData("users/{$uid}");
    $currentSellerId = $currentUser['sellerData']['sellerId'] ?? '';
    if ($uid === $product['sellerId'] || ($currentSellerId && $currentSellerId === $product['sellerId'])) {
        $isOwner = true;
    }
}

$title    = htmlspecialchars($product['title']);
$basePath = '../';
include('../layout/layout.php');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.pdp-wrap {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 20px;
}
.pdp-breadcrumb {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 20px;
}
.pdp-breadcrumb a {
    color: var(--muted);
    text-decoration: none;
}
.pdp-breadcrumb a:hover { text-decoration: underline; }

.pdp-layout {
    display: flex;
    gap: 40px;
    align-items: flex-start;
}

/* Left: image */
.pdp-img-col {
    flex: 0 0 500px;
    max-width: 500px;
}
.pdp-img-main {
    width: 100%;
    aspect-ratio: 1 / 1;
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fafafa;
}
.pdp-img-main img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.pdp-img-main i {
    font-size: 8rem;
    color: var(--muted);
}

/* Right: details */
.pdp-info-col {
    flex: 1;
    min-width: 0;
}
.pdp-cond {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 8px;
}
.pdp-title {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.3;
    margin-bottom: 16px;
    color: var(--text);
}
.pdp-price-row {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 8px;
}
.pdp-price {
    font-size: 32px;
    font-weight: 700;
    color: var(--text);
}
.pdp-shipping {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
}
.pdp-returns {
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 16px;
}
.pdp-stock {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 20px;
}
.pdp-stock span {
    color: <?php echo (($product['stock'] ?? 0) > 0) ? '#2e7d32' : 'var(--red)'; ?>;
    font-weight: 600;
}
.pdp-divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 20px 0;
}
.pdp-btn {
    display: block;
    width: 100%;
    padding: 14px;
    border-radius: 24px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    border: none;
    transition: all 0.2s;
    margin-bottom: 12px;
}
.pdp-btn-cart {
    background: #fff;
    color: var(--blue);
    border: 1px solid var(--blue);
}
.pdp-btn-cart:hover { background: #f0f7ff; }
.pdp-btn-buy {
    background: var(--blue);
    color: #fff;
    border: 1px solid var(--blue);
}
.pdp-btn-buy:hover { opacity: 0.9; }
.pdp-btn-watch {
    background: #fff;
    color: var(--text);
    border: 1px solid var(--border);
}
.pdp-btn-watch:hover { background: #f7f7f7; }
.pdp-seller-box {
    background: #f7f7f7;
    border-radius: 10px;
    padding: 16px;
    margin-top: 20px;
}
.pdp-seller-label {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 4px;
}
.pdp-seller-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--blue);
    text-decoration: none;
}
.pdp-seller-name:hover { text-decoration: underline; }
.pdp-seller-sub {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
}

/* Description section */
.pdp-desc-section {
    margin-top: 48px;
    border-top: 1px solid var(--border);
    padding-top: 32px;
}
.pdp-desc-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 16px;
}
.pdp-desc-body {
    font-size: 15px;
    line-height: 1.7;
    color: var(--text);
    white-space: pre-line;
}
.pdp-meta {
    margin-top: 24px;
    font-size: 13px;
    color: var(--muted);
}

.qty-btn {
    width: 32px; height: 32px;
    border: 1px solid var(--border);
    border-radius: 50%;
    background: #fff;
    font-size: 18px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: border-color 0.15s;
}
.qty-btn:hover { border-color: var(--text); }

@media (max-width: 860px) {
    .pdp-layout { flex-direction: column; }
    .pdp-img-col { flex: none; max-width: 100%; width: 100%; }
}

/* Related products strip */
.pdp-related {
    margin-top: 48px;
    border-top: 1px solid var(--border);
    padding-top: 32px;
}
.pdp-related-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.pdp-related-head h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
}
.pdp-related-head a {
    font-size: 14px;
    color: var(--blue);
    text-decoration: none;
}
.pdp-related-head a:hover { text-decoration: underline; }


</style>
<div class="pdp-wrap">
    <!-- Breadcrumb -->
    <div class="pdp-breadcrumb">
        <a href="../index.php">eBay</a> &rsaquo;
        <?php if ($category): ?>
            <a href="../category/category.php?id=<?= urlencode($product['categoryId']) ?>">
                <?= htmlspecialchars($category['name']) ?>
            </a> &rsaquo;
        <?php endif; ?>
        <span><?= htmlspecialchars($product['title']) ?></span>
    </div>

    <div class="pdp-layout">

        <!-- Image Column -->
        <div class="pdp-img-col">
            <div class="pdp-img-main">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['title']) ?>">
                <?php else: ?>
                    <i class="bi bi-box"></i>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Column -->
        <div class="pdp-info-col">
            <div class="pdp-cond">Brand New</div>
            <h1 class="pdp-title"><?= htmlspecialchars($product['title']) ?></h1>

            <div class="pdp-price-row">
                <span class="pdp-price">₱<?= number_format($product['price'], 2) ?></span>
            </div>
            <div class="pdp-shipping">Free shipping</div>
            <div class="pdp-returns">Free returns</div>

            <div class="pdp-stock">
                <?php if (($product['stock'] ?? 0) > 0): ?>
                    <span><?= $product['stock'] ?> available</span>
                <?php else: ?>
                    <span>Out of stock</span>
                <?php endif; ?>
            </div>

            <hr class="pdp-divider">

            <?php if ($isOwner): ?>
                <div style="background: #fdfdfd; border: 1.5px solid var(--border); border-radius: 12px; padding: 16px; text-align: center; margin-bottom: 20px;">
                    <p style="font-size: 14px; font-weight: 600; color: var(--muted); margin-bottom: 10px;"><i class="bi bi-shield-check" style="color:var(--blue);"></i> This is your product listing.</p>
                    <div style="display:flex; gap:8px;">
                        <a href="edit-product.php?id=<?= urlencode($prodId) ?>" class="pdp-btn pdp-btn-buy" style="text-decoration: none; display: block; margin-bottom: 0; padding: 10px; font-size: 14px; flex: 1;">Edit Listing</a>
                        <form action="delete-product.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this listing?');" style="margin:0; flex: 1;">
                            <input type="hidden" name="prod_id" value="<?= htmlspecialchars($prodId) ?>">
                            <button type="submit" class="pdp-btn" style="background:#fff; color:#e53238; border:1px solid #e53238; margin:0; width: 100%; font-family: var(--font); padding: 10px; font-size: 14px;">Delete Listing</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isAuction): ?>
                <?php
                $currentBid = ($auction['currentHighBid'] ?? 0) > 0 ? ($auction['currentHighBid'] ?? 0) : ($auction['startPrice'] ?? 0);
                $minBid = $totalBids > 0 ? $currentBid + 10 : $currentBid; // Minimum bid increment is 10
                $endTime = strtotime($auction['endDate']);
                $timeLeft = $endTime - time();
                
                $days = floor($timeLeft / 86400);
                $hours = floor(($timeLeft % 86400) / 3600);
                $mins = floor(($timeLeft % 3600) / 60);
                $timeString = $days > 0 ? "{$days}d {$hours}h left" : "{$hours}h {$mins}m left";
                if ($timeLeft < 0) $timeString = "Ended";
                ?>
                <div style="background:#f7f7f7; padding:16px; border-radius:8px; margin-bottom:16px; border:1px solid var(--border);" id="pdp-auction-panel">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="font-size:14px; color:var(--muted);">Current Bid:</span>
                        <span style="font-weight:600; font-size:14px;" id="pdp-bid-count"><?= $totalBids ?> bids</span>
                    </div>
                    <div style="font-size:32px; font-weight:700; margin-bottom:12px;" id="pdp-bid-price">₱<?= number_format($currentBid, 2) ?></div>
                    <div style="font-size:14px; color:var(--muted); margin-bottom:16px;">
                        <i class="bi bi-clock-history"></i> <span id="pdp-countdown-timer"><?= $timeString ?></span> <span id="pdp-end-date-string">(<?= date('M j, g:i A', $endTime) ?>)</span>
                    </div>
                    
                    <div id="pdp-bidding-area">
                        <?php if ($timeLeft > 0): ?>
                            <?php if ($isOwner): ?>
                                <div style="display:flex; gap:8px; margin-bottom: 8px;">
                                    <input type="number" id="pdp-bid-amount" disabled class="form-control" style="width:100px; padding:10px; border:1px solid var(--border); border-radius:24px; text-align:center; background:#f0f0f0; cursor:not-allowed;" value="<?= $minBid ?>">
                                    <button class="pdp-btn" style="margin:0; flex:1; background:#c7c7c7; border-color:#c7c7c7; color:#fff; cursor:not-allowed;" disabled>Place Bid</button>
                                </div>
                                <div style="font-size:12px; color:var(--muted); text-align:center;">Sellers cannot place bids on their own auctions.</div>
                                <?php if (($auction['status'] ?? '') === 'active'): ?>
                                    <form action="../auction/stop_auction.php" method="POST" style="margin-top:12px;" onsubmit="return confirm('Are you sure you want to manually stop this auction?');">
                                        <input type="hidden" name="auction_id" value="<?= htmlspecialchars($auction['auctionId']) ?>">
                                        <input type="hidden" name="redirect" value="../product/product.php?id=<?= urlencode($prodId) ?>">
                                        <button type="submit" class="pdp-btn" style="background:#fff8e1; border:1px solid #ffeeba; color:#856404; font-weight:600; margin:0; width:100%; cursor:pointer;"><i class="bi bi-stop-circle"></i> Stop Auction Manually</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <form method="POST" action="../auction/place_bid.php" style="display:flex; gap:8px;" id="pdp-bid-form">
                                    <input type="hidden" name="prod_id" value="<?= htmlspecialchars($prodId) ?>">
                                    <input type="number" name="bid_amount" id="pdp-bid-amount" min="<?= $minBid ?>" step="0.01" class="form-control" style="width:100px; padding:10px; border:1px solid var(--border); border-radius:24px; text-align:center;" value="<?= $minBid ?>">
                                    <button type="submit" class="pdp-btn pdp-btn-buy" style="margin:0; flex:1;" id="pdp-place-bid-btn">Place Bid</button>
                                </form>
                                <div style="font-size:12px; color:var(--muted); text-align:center; margin-top:8px;" id="pdp-bid-hint">Enter ₱<?= number_format($minBid, 2) ?> or more</div>
                            <?php endif; ?>
                            <a href="../auction/auction.php?id=<?= urlencode($auction['auctionId']) ?>" style="display:block; text-align:center; margin-top:12px; font-size:13px; color:var(--blue);"><i class="bi bi-arrow-up-right-square"></i> View full auction page & bid history &rarr;</a>
                        <?php else: ?>
                            <button class="pdp-btn" style="background:#e0e0e0; color:var(--muted); margin:0;" disabled>Auction Ended</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php if (($product['stock'] ?? 0) > 0): ?>
                    <?php if ($isOwner): ?>
                        <div class="pdp-qty-row">
                            <label style="font-size:14px;font-weight:600;">Quantity:</label>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:6px;margin-bottom:16px;">
                                <button class="qty-btn" style="cursor:not-allowed; opacity:0.5;" type="button" disabled>−</button>
                                <input type="number" value="1" disabled style="width:52px;text-align:center;border:1px solid var(--border);border-radius:6px;padding:6px;font-size:15px;font-weight:600;background:#f0f0f0;cursor:not-allowed;">
                                <button class="qty-btn" style="cursor:not-allowed; opacity:0.5;" type="button" disabled>+</button>
                                <span style="font-size:13px;color:var(--muted);"><?= $product['stock'] ?? 0 ?> available</span>
                            </div>
                        </div>
                        <button class="pdp-btn" style="background:#c7c7c7; color:#fff; border:none; cursor:not-allowed; margin-bottom: 12px;" disabled>Buy It Now (Disabled)</button>
                        <button class="pdp-btn" style="background:#fff; color:#c7c7c7; border:1px solid #e0e0e0; cursor:not-allowed; margin-bottom: 0;" disabled><i class="bi bi-cart-plus"></i> Add to cart (Disabled)</button>
                    <?php else: ?>
                        <div class="pdp-qty-row">
                            <label for="pdpQty" style="font-size:14px;font-weight:600;">Quantity:</label>
                            <div style="display:flex;align-items:center;gap:8px;margin-top:6px;margin-bottom:16px;">
                                <button class="qty-btn" onclick="adjustQty(-1)" type="button">−</button>
                                <input id="pdpQty" type="number" value="1" min="1" max="<?= $product['stock'] ?? 0 ?>"
                                       style="width:52px;text-align:center;border:1px solid var(--border);border-radius:6px;padding:6px;font-size:15px;font-weight:600;">
                                <button class="qty-btn" onclick="adjustQty(1)" type="button">+</button>
                                <span style="font-size:13px;color:var(--muted);"><?= $product['stock'] ?? 0 ?> available</span>
                            </div>
                        </div>
                        
                        <form method="GET" action="../checkout/checkout.php" style="margin-bottom: 12px;">
                            <input type="hidden" name="buy_now" value="<?= htmlspecialchars($prodId) ?>">
                            <input type="hidden" name="qty" id="buyNowQty" value="1">
                            <button type="submit" class="pdp-btn pdp-btn-buy" style="margin-bottom: 0;">Buy It Now</button>
                        </form>
                        
                        <button class="pdp-btn pdp-btn-cart" id="addToCartBtn"
                                onclick="addToCart(<?= htmlspecialchars(json_encode($prodId), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="bi bi-cart-plus"></i> Add to cart
                        </button>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="pdp-btn" style="background:#e0e0e0;color:var(--muted);cursor:not-allowed;" disabled>
                        Out of stock
                    </button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($isOwner): ?>
                <button class="pdp-btn pdp-btn-watch" style="opacity:0.6; cursor:not-allowed;" disabled>
                    <i class="bi bi-heart"></i> Watch this item (Disabled)
                </button>
            <?php else: ?>
                <button class="pdp-btn pdp-btn-watch" id="watchBtn"
                        onclick="toggleWish(this, <?= htmlspecialchars(json_encode($prodId), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($product['title']), ENT_QUOTES, 'UTF-8') ?>)">
                    <?php if ($isWishlisted): ?>
                        <i class="bi bi-heart-fill" style="color:var(--red)"></i> Watching
                    <?php else: ?>
                        <i class="bi bi-heart"></i> Watch this item
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <!-- Seller Info -->
            <?php
            $sellerName = $sellerUser['accountName'] ?? trim(($sellerUser['firstName'] ?? '') . ' ' . ($sellerUser['lastName'] ?? '')) ?: 'Unknown Seller';
            $ratingText = $sellerReviews > 0 ? "{$sellerRating} / 5.0 ★ ({$sellerReviews} reviews)" : "No reviews yet";
            ?>
            <div class="pdp-seller-box">
                <div class="pdp-seller-label">Sold by</div>
                <a href="#" class="pdp-seller-name"><?= htmlspecialchars($sellerName) ?></a>
                <div class="pdp-seller-sub"><?= htmlspecialchars($ratingText) ?></div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="pdp-desc-section">
        <div class="pdp-desc-title">Item description from the seller</div>
        <div class="pdp-desc-body"><?= htmlspecialchars($product['description']) ?></div>
        <div class="pdp-meta">
            Listed on: <?= date('F j, Y', strtotime($product['dateAdded'])) ?>
            <?php if ($category): ?>
                &nbsp;&bull;&nbsp; Category: <?= htmlspecialchars($category['name']) ?>
            <?php endif; ?>
            &nbsp;&bull;&nbsp; Item ID: <?= htmlspecialchars($prodId) ?>
        </div>
    </div>

    <!-- Related / Similar Items -->
    <?php if (!empty($related)): ?>
    <div class="pdp-related">
        <div class="pdp-related-head">
            <h2>Similar items you might like</h2>
            <?php if ($category): ?>
                <a href="../category/category.php?id=<?= urlencode($product['categoryId']) ?>">See all in <?= htmlspecialchars($category['name']) ?> &rsaquo;</a>
            <?php endif; ?>
        </div>
        <div class="scroll-row">
            <?php foreach ($related as $r): ?>
                <a href="product.php?id=<?= urlencode($r['Prod_ID']) ?>" class="prod-card" style="text-decoration:none; color:inherit; transition:transform 0.2s, box-shadow 0.2s;">
                        <div class="prod-img">
                            <?php if (!empty($r['image'])): ?>
                                <img src="<?= htmlspecialchars($r['image']) ?>" alt="<?= htmlspecialchars($r['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <i class="bi bi-box" style="font-size:5rem;color:var(--muted);"></i>
                            <?php endif; ?>
                        </div>
                        <p class="prod-cond">Brand New</p>
                        <p class="prod-name"><?= htmlspecialchars($r['title']) ?></p>
                        <div class="prod-prices">
                            <span class="prod-price">₱<?= number_format($r['price'], 2) ?></span>
                        </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include('../layout/footer.php'); ?>

<script>
const MAX_STOCK = <?= (int)($product['stock'] ?? 0) ?>;

function adjustQty(delta) {
    const inp = document.getElementById('pdpQty');
    const val = Math.min(MAX_STOCK, Math.max(1, parseInt(inp.value || 1) + delta));
    inp.value = val;
    
    // Sync with Buy It Now form
    const buyNowQty = document.getElementById('buyNowQty');
    if (buyNowQty) buyNowQty.value = val;
}

function addToCart(prodId) {
    const qty = parseInt(document.getElementById('pdpQty')?.value || 1);
    const btn = document.getElementById('addToCartBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding…';

    fetch('../cart/add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'prod_id=' + encodeURIComponent(prodId) + '&qty=' + qty
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('🛒 ' + data.message);
            const badge = document.getElementById('cartBadge');
            if (badge) badge.textContent = data.cart_count;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Added to cart';
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart';
                btn.disabled = false;
            }, 2000);
        } else {
            showToast(data.message || 'Could not add to cart');
            btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart';
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Something went wrong');
        btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart';
        btn.disabled = false;
    });
}

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
        if (watching) {
            btn.innerHTML = '<i class="bi bi-heart"></i> Watch this item';
            showToast(`Removed "${name}" from Watchlist`);
        } else {
            btn.innerHTML = '<i class="bi bi-heart-fill" style="color:var(--red)"></i> Watching';
            showToast(`❤️ "${data.message || 'Added to Watchlist'}`);
        }
        btn.disabled = false;
    })
    .catch(() => { showToast('Something went wrong'); btn.disabled = false; });
}

<?php if ($isAuction): ?>
// Live countdown timer in JS (client-side tick)
let endTimeMs = <?= $endTime * 1000 ?>;
let isAuctionActive = <?= $auction['status'] === 'active' && $timeLeft > 0 ? 'true' : 'false' ?>;
let pollTimer = null;

function updatePdpCountdown() {
    if (!isAuctionActive) return;
    let rem = Math.floor((endTimeMs - Date.now()) / 1000);
    if (rem <= 0) {
        document.getElementById('pdp-countdown-timer').textContent = 'Ended';
        isAuctionActive = false;
        setAuctionEndedUI();
        if (pollTimer) clearInterval(pollTimer);
        return;
    }
    let d = Math.floor(rem / 86400);
    let h = Math.floor((rem % 86400) / 3600);
    let m = Math.floor((rem % 3600) / 60);
    let s = rem % 60;
    document.getElementById('pdp-countdown-timer').textContent = d > 0
        ? `${d}d ${h}h ${m}m left`
        : `${h}h ${m}m ${s}s left`;
}

function setAuctionEndedUI() {
    document.getElementById('pdp-countdown-timer').textContent = 'Ended';
    const bidArea = document.getElementById('pdp-bidding-area');
    if (bidArea) {
        bidArea.innerHTML = '<button class="pdp-btn" style="background:#e0e0e0; color:var(--muted); margin:0;" disabled>Auction Ended</button>';
    }
}

function pollPdpAuction() {
    if (!isAuctionActive) return;
    fetch('../auction/get_bid.php?prod_id=' + encodeURIComponent('<?= $prodId ?>'))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update price and bid count
                const bidPriceEl = document.getElementById('pdp-bid-price');
                const bidCountEl = document.getElementById('pdp-bid-count');
                if (bidPriceEl) {
                    bidPriceEl.textContent = '₱' + parseFloat(data.currentBid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                if (bidCountEl) {
                    bidCountEl.textContent = data.bidCount + ' bid' + (data.bidCount !== 1 ? 's' : '');
                }

                // Update bidding inputs if active
                if (data.status === 'active' && data.timeLeft > 0) {
                    const bidInput = document.getElementById('pdp-bid-amount');
                    const bidHint = document.getElementById('pdp-bid-hint');
                    if (bidInput) {
                        bidInput.min = data.minBid;
                        if (parseFloat(bidInput.value || 0) < parseFloat(data.minBid)) {
                            bidInput.value = data.minBid;
                        }
                    }
                    if (bidHint) {
                        bidHint.textContent = 'Enter ₱' + parseFloat(data.minBid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' or more';
                    }
                } else {
                    isAuctionActive = false;
                    setAuctionEndedUI();
                    if (pollTimer) clearInterval(pollTimer);
                }
            }
        })
        .catch(err => console.error('Error polling auction details:', err));
}

if (isAuctionActive) {
    setInterval(updatePdpCountdown, 1000);
    updatePdpCountdown();
    pollTimer = setInterval(pollPdpAuction, 3000);
    pollPdpAuction();
}
<?php endif; ?>
</script>
