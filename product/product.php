<?php
session_start();
require_once('../config/db.php');

$prodId = trim($_GET['id'] ?? '');
if (!$prodId) {
    header('Location: ../index.php');
    exit;
}

// Require login — redirect back here after sign-in
if (!isset($_SESSION['account_id'])) {
    $redirect = urlencode('product/product.php?id=' . $prodId);
    header('Location: ../auth/signin.php?redirect=' . $redirect);
    exit;
}

$product = null;
$stmt = $conn->prepare("
    SELECT p.*,
           c.Cat_Name, c.Cat_ID,
           u.User_AccName, u.User_FName, u.User_LName
    FROM Product p
    LEFT JOIN Category c ON p.Prod_CatID = c.Cat_ID
    LEFT JOIN Seller s   ON p.Prod_SellID = s.Sell_ID
    LEFT JOIN User u     ON s.Sell_UserID = u.User_ID
    WHERE p.Prod_ID = ?
");
$stmt->bind_param("s", $prodId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $product = $row;
}
$stmt->close();

$isAuction = false;
$auction = null;
$stmtA = $conn->prepare("SELECT * FROM Auction WHERE Auc_ProdID = ? AND Auc_Status IN ('Active','Ended')");
$stmtA->bind_param("s", $prodId);
$stmtA->execute();
$resA = $stmtA->get_result();
if ($row = $resA->fetch_assoc()) {
    $isAuction = true;
    $auction = $row;
    // Auto-close expired auctions
    if ($auction['Auc_Status'] === 'Active' && strtotime($auction['Auc_EndDate']) < time()) {
        $conn->query("UPDATE Auction SET Auc_Status='Ended' WHERE Auc_ID='" . $conn->real_escape_string($auction['Auc_ID']) . "'");
        $auction['Auc_Status'] = 'Ended';
    }
}
$stmtA->close();

// Fetch total bids from Bid table
$totalBids = 0;
if ($isAuction) {
    $stmtB = $conn->prepare("SELECT COUNT(*) as c FROM Bid WHERE Bid_AucID = ?");
    $stmtB->bind_param("s", $auction['Auc_ID']);
    $stmtB->execute();
    $totalBids = $stmtB->get_result()->fetch_assoc()['c'] ?? 0;
    $stmtB->close();
}

if (!$product) {
    $_SESSION['flash'] = "Product not found.";
    header('Location: ../index.php');
    exit;
}

// Fetch seller rating
$sellerRating = 0;
$sellerReviews = 0;
if (!empty($product['Prod_SellID'])) {
    $stmtR = $conn->prepare("SELECT AVG(Feed_Rating) as avg_rating, COUNT(*) as review_count FROM Feedback WHERE Feed_SellID = ?");
    $stmtR->bind_param("s", $product['Prod_SellID']);
    $stmtR->execute();
    $resR = $stmtR->get_result()->fetch_assoc();
    $sellerRating = $resR['avg_rating'] ? round($resR['avg_rating'], 1) : 0;
    $sellerReviews = $resR['review_count'] ?? 0;
    $stmtR->close();
}

// Fetch related products — same category, exclude current
$related = [];
if (!empty($product['Prod_CatID'])) {
    $stmt2 = $conn->prepare("SELECT * FROM Product WHERE Prod_CatID = ? AND Prod_ID != ? ORDER BY Prod_DateAdd DESC LIMIT 10");
    $stmt2->bind_param("ss", $product['Prod_CatID'], $prodId);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($r = $res2->fetch_assoc()) { $related[] = $r; }
    $stmt2->close();
}
// Fall back to latest products if not enough same-category results
if (count($related) < 4) {
    $stmt3 = $conn->prepare("SELECT * FROM Product WHERE Prod_ID != ? ORDER BY Prod_DateAdd DESC LIMIT 10");
    $stmt3->bind_param("s", $prodId);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    $related = [];
    while ($r = $res3->fetch_assoc()) { $related[] = $r; }
    $stmt3->close();
}

// Check if already in wishlist
$isWishlisted = false;
if (isset($_SESSION['account_id'])) {
    $wStmt = $conn->prepare("SELECT 1 FROM Wishlist WHERE Wish_UserID = ? AND Wish_ProdID = ?");
    $wStmt->bind_param("ss", $_SESSION['account_id'], $prodId);
    $wStmt->execute();
    $isWishlisted = (bool)$wStmt->get_result()->fetch_row();
    $wStmt->close();
}

$title    = htmlspecialchars($product['Prod_Title']);
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
    color: <?php echo ($product['Prod_Stock'] > 0) ? '#2e7d32' : 'var(--red)'; ?>;
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
        <?php if ($product['Cat_Name']): ?>
            <a href="../category/category.php?id=<?= urlencode($product['Cat_ID']) ?>">
                <?= htmlspecialchars($product['Cat_Name']) ?>
            </a> &rsaquo;
        <?php endif; ?>
        <span><?= htmlspecialchars($product['Prod_Title']) ?></span>
    </div>

    <div class="pdp-layout">

        <!-- Image Column -->
        <div class="pdp-img-col">
            <div class="pdp-img-main">
                <?php if (!empty($product['Prod_Image'])): ?>
                    <img src="<?= htmlspecialchars($product['Prod_Image']) ?>" alt="<?= htmlspecialchars($product['Prod_Title']) ?>">
                <?php else: ?>
                    <i class="bi bi-box"></i>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Column -->
        <div class="pdp-info-col">
            <div class="pdp-cond">Brand New</div>
            <h1 class="pdp-title"><?= htmlspecialchars($product['Prod_Title']) ?></h1>

            <div class="pdp-price-row">
                <span class="pdp-price">₱<?= number_format($product['Prod_Price'], 2) ?></span>
            </div>
            <div class="pdp-shipping">Free shipping</div>
            <div class="pdp-returns">Free returns</div>

            <div class="pdp-stock">
                <?php if ($product['Prod_Stock'] > 0): ?>
                    <span><?= $product['Prod_Stock'] ?> available</span>
                <?php else: ?>
                    <span>Out of stock</span>
                <?php endif; ?>
            </div>

            <hr class="pdp-divider">

            <?php if ($isAuction): ?>
                <?php
                $currentBid = $auction['Auc_HighBid'] > 0 ? $auction['Auc_HighBid'] : $auction['Auc_StartPrice'];
                $minBid = $totalBids > 0 ? $currentBid + 10 : $currentBid; // Minimum bid increment is 10
                $endTime = strtotime($auction['Auc_EndDate']);
                $timeLeft = $endTime - time();
                
                $days = floor($timeLeft / 86400);
                $hours = floor(($timeLeft % 86400) / 3600);
                $mins = floor(($timeLeft % 3600) / 60);
                $timeString = $days > 0 ? "{$days}d {$hours}h left" : "{$hours}h {$mins}m left";
                if ($timeLeft < 0) $timeString = "Ended";
                ?>
                <div style="background:#f7f7f7; padding:16px; border-radius:8px; margin-bottom:16px; border:1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="font-size:14px; color:var(--muted);">Current Bid:</span>
                        <span style="font-weight:600; font-size:14px;"><?= $totalBids ?> bids</span>
                    </div>
                    <div style="font-size:32px; font-weight:700; margin-bottom:12px;">₱<?= number_format($currentBid, 2) ?></div>
                    <div style="font-size:14px; color:var(--muted); margin-bottom:16px;">
                        <i class="bi bi-clock-history"></i> <?= $timeString ?> (<?= date('M j, g:i A', $endTime) ?>)
                    </div>
                    
                    <?php if ($timeLeft > 0): ?>
                    <form method="POST" action="../auction/place_bid.php" style="display:flex; gap:8px;">
                        <input type="hidden" name="prod_id" value="<?= htmlspecialchars($prodId) ?>">
                        <input type="number" name="bid_amount" min="<?= $minBid ?>" step="0.01" class="form-control" style="width:100px; padding:10px; border:1px solid var(--border); border-radius:24px; text-align:center;" value="<?= $minBid ?>">
                        <button type="submit" class="pdp-btn pdp-btn-buy" style="margin:0; flex:1;">Place Bid</button>
                    </form>
                    <div style="font-size:12px; color:var(--muted); text-align:center; margin-top:8px;">Enter ₱<?= number_format($minBid, 2) ?> or more</div>
                    <a href="../auction/auction.php?id=<?= urlencode($auction['Auc_ID']) ?>" style="display:block; text-align:center; margin-top:12px; font-size:13px; color:var(--blue);"><i class="bi bi-arrow-up-right-square"></i> View full auction page &rarr;</a>
                    <?php else: ?>
                    <button class="pdp-btn" style="background:#e0e0e0; color:var(--muted); margin:0;" disabled>Auction Ended</button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if ($product['Prod_Stock'] > 0): ?>
                <div class="pdp-qty-row">
                    <label for="pdpQty" style="font-size:14px;font-weight:600;">Quantity:</label>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:6px;margin-bottom:16px;">
                        <button class="qty-btn" onclick="adjustQty(-1)" type="button">−</button>
                        <input id="pdpQty" type="number" value="1" min="1" max="<?= $product['Prod_Stock'] ?>"
                               style="width:52px;text-align:center;border:1px solid var(--border);border-radius:6px;padding:6px;font-size:15px;font-weight:600;">
                        <button class="qty-btn" onclick="adjustQty(1)" type="button">+</button>
                        <span style="font-size:13px;color:var(--muted);"><?= $product['Prod_Stock'] ?> available</span>
                    </div>
                </div>
                
                <form method="GET" action="../checkout/checkout.php" style="margin-bottom: 12px;">
                    <input type="hidden" name="buy_now" value="<?= htmlspecialchars($product['Prod_ID']) ?>">
                    <input type="hidden" name="qty" id="buyNowQty" value="1">
                    <button type="submit" class="pdp-btn pdp-btn-buy" style="margin-bottom: 0;">Buy It Now</button>
                </form>
                
                <button class="pdp-btn pdp-btn-cart" id="addToCartBtn"
                        onclick="addToCart(<?= htmlspecialchars(json_encode($product['Prod_ID']), ENT_QUOTES, 'UTF-8') ?>)">
                    <i class="bi bi-cart-plus"></i> Add to cart
                </button>
                <?php else: ?>
                <button class="pdp-btn" style="background:#e0e0e0;color:var(--muted);cursor:not-allowed;" disabled>
                    Out of stock
                </button>
                <?php endif; ?>
            <?php endif; ?>

            <button class="pdp-btn pdp-btn-watch" id="watchBtn"
                    onclick="toggleWish(this, <?= htmlspecialchars(json_encode($product['Prod_ID']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($product['Prod_Title']), ENT_QUOTES, 'UTF-8') ?>)">
                <?php if ($isWishlisted): ?>
                    <i class="bi bi-heart-fill" style="color:var(--red)"></i> Watching
                <?php else: ?>
                    <i class="bi bi-heart"></i> Watch this item
                <?php endif; ?>
            </button>

            <!-- Seller Info -->
            <?php
            $sellerName = $product['User_AccName']
                ?? trim(($product['User_FName'] ?? '') . ' ' . ($product['User_LName'] ?? ''))
                ?: 'Unknown Seller';
                
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
        <div class="pdp-desc-body"><?= htmlspecialchars($product['Prod_Desc']) ?></div>
        <div class="pdp-meta">
            Listed on: <?= date('F j, Y', strtotime($product['Prod_DateAdd'])) ?>
            <?php if (!empty($product['Cat_Name'])): ?>
                &nbsp;&bull;&nbsp; Category: <?= htmlspecialchars($product['Cat_Name']) ?>
            <?php endif; ?>
            &nbsp;&bull;&nbsp; Item ID: <?= htmlspecialchars($product['Prod_ID']) ?>
        </div>
    </div>

    <!-- Related / Similar Items -->
    <?php if (!empty($related)): ?>
    <div class="pdp-related">
        <div class="pdp-related-head">
            <h2>Similar items you might like</h2>
            <?php if (!empty($product['Cat_ID'])): ?>
                <a href="../category/category.php?id=<?= urlencode($product['Cat_ID']) ?>">See all in <?= htmlspecialchars($product['Cat_Name']) ?> &rsaquo;</a>
            <?php endif; ?>
        </div>
        <div class="scroll-row">
            <?php foreach ($related as $r): ?>
                <a href="product.php?id=<?= urlencode($r['Prod_ID']) ?>" class="prod-card" style="text-decoration:none; color:inherit; transition:transform 0.2s, box-shadow 0.2s;">
                        <div class="prod-img">
                            <?php if (!empty($r['Prod_Image'])): ?>
                                <img src="<?= htmlspecialchars($r['Prod_Image']) ?>" alt="<?= htmlspecialchars($r['Prod_Title']) ?>" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <i class="bi bi-box" style="font-size:5rem;color:var(--muted);"></i>
                            <?php endif; ?>
                        </div>
                        <p class="prod-cond">Brand New</p>
                        <p class="prod-name"><?= htmlspecialchars($r['Prod_Title']) ?></p>
                        <div class="prod-prices">
                            <span class="prod-price">₱<?= number_format($r['Prod_Price'], 2) ?></span>
                        </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include('../layout/footer.php'); ?>

<script>
const MAX_STOCK = <?= (int)$product['Prod_Stock'] ?>;

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
</script>
