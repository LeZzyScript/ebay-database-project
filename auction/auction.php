<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php?redirect=auction/auction.php?id=' . urlencode($_GET['id'] ?? ''));
    exit;
}
require_once('../config/firebase.php');
$uid = $_SESSION['firebase_uid'];
$aucId  = trim($_GET['id'] ?? '');

if (!$aucId) { header('Location: ../index.php'); exit; }

// Fetch auction data from Firebase
$auction = getData("auctions/{$aucId}");

if (!$auction) { $_SESSION['flash'] = "Auction not found."; header('Location: ../index.php'); exit; }

// Auto-close if past end date
if (($auction['status'] ?? '') === 'active' && strtotime($auction['endDate'] ?? '') < time()) {
    updateData("auctions/{$aucId}/status", 'ended');
    $auction['status'] = 'ended';
}

// Fetch product data
$productId = $auction['productId'] ?? '';
$product = getData("products/{$productId}");

if (!$product) { $_SESSION['flash'] = "Product not found."; header('Location: ../index.php'); exit; }

$isOwner = false;
if (isset($product['sellerId'])) {
    if ($uid === $product['sellerId']) {
        $isOwner = true;
    } else {
        $currentUser = getData("users/{$uid}");
        $currentSellerId = $currentUser['sellerData']['sellerId'] ?? '';
        if ($currentSellerId && $currentSellerId === $product['sellerId']) {
            $isOwner = true;
        }
    }
}

// Fetch category data
$category = null;
if (isset($product['categoryId'])) {
    $category = getData("categories/{$product['categoryId']}");
}

// Fetch seller data
$sellerUser = null;
if (isset($product['sellerId'])) {
    $allUsers = getData('users');
    if ($allUsers) {
        foreach ($allUsers as $userId => $userData) {
            if (isset($userData['sellerData']['sellerId']) && $userData['sellerData']['sellerId'] === $product['sellerId']) {
                $sellerUser = $userData['profile'];
                break;
            }
        }
    }
}

// Fetch all bids for this auction
$bidsData = getData("auctions/{$aucId}/bids");
$bids = [];
if ($bidsData) {
    foreach ($bidsData as $bidId => $bidData) {
        $bidUser = getData("users/{$bidData['userId']}");
        if ($bidUser) {
            $bids[] = [
                'Bid_Amount' => $bidData['amount'],
                'Bid_Date' => $bidData['date'],
                'Bid_UserID' => $bidData['userId'],
                'User_AccName' => $bidUser['profile']['accountName'] ?? ''
            ];
        }
    }
}

// Sort bids by amount descending
usort($bids, function($a, $b) {
    return $b['Bid_Amount'] - $a['Bid_Amount'];
});

$bidCount   = count($bids);
$currentBid = ($auction['currentHighBid'] ?? 0) > 0 ? floatval($auction['currentHighBid']) : floatval($auction['startPrice'] ?? 0);
$minBid     = $bidCount > 0 ? $currentBid + 10 : $currentBid;
$timeLeft   = strtotime($auction['endDate'] ?? '') - time();
$days  = floor($timeLeft / 86400);
$hours = floor(($timeLeft % 86400) / 3600);
$mins  = floor(($timeLeft % 3600) / 60);
$timeStr = $timeLeft < 0 ? 'Ended' : ($days > 0 ? "{$days}d {$hours}h left" : "{$hours}h {$mins}m left");

// Is current user the winner?
$myTopBid = 0;
foreach ($bids as $b) {
    if ($b['Bid_UserID'] === $uid && $b['Bid_Amount'] > $myTopBid) {
        $myTopBid = floatval($b['Bid_Amount']);
    }
}
$isWinner = ($auction['status'] ?? '') === 'ended' && $myTopBid >= $currentBid && $bidCount > 0;
$isActive = ($auction['status'] ?? '') === 'active' && $timeLeft > 0;

$sellerName = $sellerUser['accountName'] ?? trim(($sellerUser['firstName'] ?? '') . ' ' . ($sellerUser['lastName'] ?? ''));
$title    = 'Auction — ' . htmlspecialchars($product['title'] ?? '');
$basePath = '../';
include('../layout/layout.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.auc-wrap { max-width: 1100px; margin: 28px auto; padding: 0 20px; }
.auc-layout { display: flex; gap: 36px; align-items: flex-start; }
.auc-img-col { flex: 0 0 420px; max-width: 420px; }
.auc-img-main { width:100%; aspect-ratio:1/1; border:1px solid var(--border); border-radius:12px; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#fafafa; }
.auc-img-main img { width:100%; height:100%; object-fit:contain; }
.auc-info-col { flex: 1; min-width: 0; }
.auc-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:12px; }
.badge-active   { background:#cce5ff; color:#004085; }
.badge-ended    { background:#e9ecef; color:#495057; }
.badge-paid     { background:#d4edda; color:#155724; }
.auc-title      { font-size:24px; font-weight:700; margin-bottom:16px; line-height:1.3; }
.auc-panel      { background:#f7f7f7; border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:16px; }
.auc-panel-row  { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:8px; }
.auc-price      { font-size:36px; font-weight:700; }
.auc-sub        { font-size:13px; color:var(--muted); }
.auc-timer      { display:flex; align-items:center; gap:8px; font-size:14px; color:var(--muted); margin-bottom:16px; }
.auc-bid-form   { display:flex; gap:8px; }
.auc-bid-input  { flex:1; padding:12px 16px; border:1px solid var(--border); border-radius:24px; font-size:15px; font-family:var(--font); outline:none; }
.auc-bid-input:focus { border-color:var(--blue); }
.auc-bid-btn    { padding:12px 24px; background:var(--blue); color:#fff; border:none; border-radius:24px; font-size:15px; font-weight:700; cursor:pointer; }
.auc-bid-btn:hover { opacity:0.9; }
.auc-pay-btn    { display:block; text-align:center; padding:14px; background:#2e7d32; color:#fff; border-radius:24px; font-size:16px; font-weight:700; text-decoration:none; margin-bottom:8px; }
.auc-pay-btn:hover { opacity:0.9; color:#fff; }
.auc-winner-box { background:#d4edda; border:1px solid #b7dfbb; border-radius:12px; padding:16px; margin-bottom:16px; }
.auc-seller-box { background:#fff; border:1px solid var(--border); border-radius:10px; padding:14px; margin-top:12px; font-size:14px; }

.bids-section   { margin-top:40px; border-top:1px solid var(--border); padding-top:28px; }
.bids-table     { width:100%; border-collapse:collapse; font-size:14px; }
.bids-table th  { padding:10px 14px; text-align:left; background:#f7f7f7; color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; }
.bids-table td  { padding:12px 14px; border-top:1px solid var(--border); }
.bids-table tr:first-child td { border-top:none; }
.bid-winner-row td { background:#f0fff4; font-weight:600; }

@media(max-width:800px) {
    .auc-layout { flex-direction:column; }
    .auc-img-col { flex:none; max-width:100%; width:100%; }
}
</style>

<?php if (!empty($_SESSION['flash'])): ?>
<div style="max-width:1100px;margin:16px auto 0;padding:0 20px;">
    <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;padding:12px 16px;font-size:14px;color:#2e7d32;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_SESSION['flash']) ?>
    </div>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<div class="auc-wrap">
    <!-- Breadcrumb -->
    <div style="font-size:12px;color:var(--muted);margin-bottom:20px;">
        <a href="../index.php" style="color:var(--muted);text-decoration:none;">eBay</a> &rsaquo;
        <a href="../product/product.php?id=<?= urlencode($productId) ?>" style="color:var(--muted);text-decoration:none;"><?= htmlspecialchars($product['title'] ?? '') ?></a> &rsaquo;
        <span>Auction</span>
    </div>

    <div class="auc-layout">
        <!-- Image -->
        <div class="auc-img-col">
            <div class="auc-img-main">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['title'] ?? '') ?>">
                <?php else: ?>
                    <i class="bi bi-box" style="font-size:6rem;color:var(--muted);"></i>
                <?php endif; ?>
            </div>
            <!-- Seller box -->
            <div class="auc-seller-box">
                <div style="font-size:12px;color:var(--muted);margin-bottom:4px;">Sold by</div>
                <strong><?= htmlspecialchars($sellerName) ?></strong>
            </div>
        </div>

        <!-- Info -->
        <div class="auc-info-col">
            <?php
            $badgeClass = match($auction['status'] ?? '') {
                'active' => 'badge-active',
                'paid'   => 'badge-paid',
                default  => 'badge-ended',
            };
            $badgeText = match($auction['status'] ?? '') {
                'active' => '<i class="bi bi-broadcast"></i> Live Auction',
                'paid'   => '<i class="bi bi-check-circle-fill"></i> Paid',
                default  => '<i class="bi bi-stop-circle"></i> Auction Ended',
            };
            ?>
            <div class="auc-badge <?= $badgeClass ?>" id="auc-badge-status"><?= $badgeText ?></div>
            <h1 class="auc-title"><?= htmlspecialchars($product['title'] ?? '') ?></h1>

            <!-- Winner box -->
            <div id="auc-winner-section">
                <?php if ($isWinner): ?>
                <div class="auc-winner-box">
                    <div style="font-weight:700;font-size:16px;margin-bottom:6px;"><i class="bi bi-trophy-fill" style="color:#f5af02;"></i> You won this auction!</div>
                    <div style="font-size:13px;color:var(--muted);margin-bottom:12px;">Your winning bid: <strong>₱<?= number_format($myTopBid, 2) ?></strong>. Complete your purchase to secure this item.</div>
                    <a href="../checkout/checkout.php?auction_id=<?= urlencode($aucId) ?>" class="auc-pay-btn">
                        <i class="bi bi-bag-check"></i> Pay Now — ₱<?= number_format($myTopBid, 2) ?>
                    </a>
                </div>
                <?php elseif (($auction['status'] ?? '') === 'paid'): ?>
                <div style="background:#e3f2fd;border:1px solid #90caf9;border-radius:12px;padding:16px;margin-bottom:16px;font-size:14px;color:#1565c0;font-weight:600;">
                    <i class="bi bi-bag-check-fill"></i> This auction has been purchased by the winner.
                </div>
                <?php elseif (($auction['status'] ?? '') === 'ended' && !$isWinner && $bidCount > 0): ?>
                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:16px;margin-bottom:16px;font-size:14px;color:#856404;">
                    <i class="bi bi-exclamation-circle-fill"></i> This auction has ended. Another bidder won.
                </div>
                <?php endif; ?>
            </div>

            <!-- Bid panel -->
            <div class="auc-panel">
                <div class="auc-panel-row">
                    <span class="auc-sub" id="auc-bid-label"><?= $bidCount > 0 ? 'Current bid' : 'Starting bid' ?></span>
                    <span class="auc-sub" id="auc-bid-count"><?= $bidCount ?> bid<?= $bidCount != 1 ? 's' : '' ?></span>
                </div>
                <div class="auc-price" id="auc-price">₱<?= number_format($currentBid, 2) ?></div>

                <div id="auc-action-area">
                    <?php if (($auction['status'] ?? '') === 'active'): ?>
                    <div class="auc-timer">
                        <i class="bi bi-clock-history"></i>
                        <span id="countdown"><?= htmlspecialchars($timeStr) ?></span>
                        <span style="color:var(--muted);" id="auc-end-time-string">&bull; Ends <?= date('M j, Y g:i A', strtotime($auction['endDate'] ?? '')) ?></span>
                    </div>
                    <?php if ($isActive): ?>
                    <form method="POST" action="../auction/place_bid.php" class="auc-bid-form" id="auc-bid-form">
                        <input type="hidden" name="prod_id" value="<?= htmlspecialchars($productId) ?>">
                        <input type="number" name="bid_amount" id="auc-bid-input" class="auc-bid-input"
                               min="<?= $minBid ?>" step="0.01" value="<?= $minBid ?>" placeholder="Your bid">
                        <button type="submit" class="auc-bid-btn" id="auc-place-bid-btn"><i class="bi bi-arrow-up-circle"></i> Place Bid</button>
                    </form>
                    <div style="font-size:12px;color:var(--muted);margin-top:8px;text-align:center;" id="auc-bid-hint">Enter ₱<?= number_format($minBid, 2) ?> or more</div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="auc-timer"><i class="bi bi-stop-circle"></i> <span id="auc-timer-ended-text">Auction ended <?= date('M j, Y g:i A', strtotime($auction['endDate'] ?? '')) ?></span></div>
                    <?php endif; ?>

                    <?php if ($isOwner && ($auction['status'] ?? '') === 'active'): ?>
                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border);">
                        <form action="../auction/stop_auction.php" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to manually stop this auction?');">
                            <input type="hidden" name="auction_id" value="<?= htmlspecialchars($aucId) ?>">
                            <input type="hidden" name="redirect" value="../auction/auction.php?id=<?= urlencode($aucId) ?>">
                            <button type="submit" style="width:100%; padding:12px; background:#fff8e1; border:1px solid #ffeeba; color:#856404; border-radius:24px; font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:background 0.2s; font-family:var(--font);" onmouseover="this.style.background='#ffe082'" onmouseout="this.style.background='#fff8e1'"><i class="bi bi-stop-circle-fill" style="color:#b78103;"></i> Stop Auction Manually</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="auc-my-bid-status" style="font-size:13px;color:var(--muted);padding:8px 0; <?= ($myTopBid > 0 && $isActive) ? '' : 'display:none;' ?>">
                Your current bid: <strong>₱<span id="auc-my-bid-amount"><?= number_format($myTopBid, 2) ?></span></strong>
                <span id="auc-my-bid-comparison"><?= $myTopBid >= $currentBid ? '<span style="color:#2e7d32;font-weight:600;"> — You are the highest bidder 🏆</span>' : '<span style="color:#e53238;"> — You have been outbid</span>' ?></span>
            </div>
        </div>
    </div>

    <!-- Bid History -->
    <div class="bids-section" id="auc-bids-history-section" style="<?= !empty($bids) ? '' : 'display:none;' ?>">
        <h2 style="font-size:20px;font-weight:700;margin-bottom:16px;" id="auc-bids-history-title">Bid History (<?= $bidCount ?>)</h2>
        <div style="background:#fff;border:1px solid var(--border);border-radius:12px;overflow:hidden;">
            <table class="bids-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Bidder</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="auc-bids-tbody">
                    <?php foreach ($bids as $i => $b): ?>
                    <tr <?= $i === 0 ? 'class="bid-winner-row"' : '' ?>>
                        <td><?= $i === 0 ? '<i class="bi bi-trophy-fill" style="color:#f5af02;"></i>' : ($i + 1) ?></td>
                        <td>
                            <?= $i === 0 ? '<i class="bi bi-person-check-fill" style="color:#2e7d32;"></i> ' : '' ?>
                            <?= htmlspecialchars($b['User_AccName']) ?>
                            <?= $b['Bid_UserID'] === $uid ? ' <span style="background:#e3f2fd;color:#1565c0;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">You</span>' : '' ?>
                        </td>
                        <td><strong>₱<?= number_format($b['Bid_Amount'], 2) ?></strong></td>
                        <td style="color:var(--muted);"><?= date('M j, Y g:i A', strtotime($b['Bid_Date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include('../layout/footer.php'); ?>
<script>
const currentUserId = '<?= $uid ?>';
const productId = '<?= $productId ?>';
const aucId = '<?= $aucId ?>';

// Live countdown
let endTs = <?= strtotime($auction['endDate'] ?? '') * 1000 ?>;
let isAuctionActive = <?= ($auction['status'] ?? '') === 'active' && $timeLeft > 0 ? 'true' : 'false' ?>;
let pollTimer = null;

function updateCountdown() {
    if (!isAuctionActive) return;
    let rem = Math.floor((endTs - Date.now()) / 1000);
    if (rem <= 0) {
        isAuctionActive = false;
        document.getElementById('countdown').textContent = 'Ended';
        fetch('../auction/get_bid.php?prod_id=' + encodeURIComponent(productId))
            .then(r => r.json())
            .then(data => {
                let winnerId = null;
                let winningAmt = 0;
                if (data.bids && data.bids.length > 0) {
                    winnerId = data.bids[0].userId;
                    winningAmt = data.bids[0].amount;
                }
                setAuctionEndedUI(data.bidCount > 0, winnerId, winningAmt);
            });
        if (pollTimer) clearInterval(pollTimer);
        return;
    }
    let d = Math.floor(rem / 86400);
    let h = Math.floor((rem % 86400) / 3600);
    let m = Math.floor((rem % 3600) / 60);
    let s = rem % 60;
    document.getElementById('countdown').textContent = d > 0
        ? `${d}d ${h}h ${m}m left`
        : `${h}h ${m}m ${s}s left`;
}

function setAuctionEndedUI(hasBids, winnerId, winningAmount) {
    const countdownEl = document.getElementById('countdown');
    if (countdownEl) countdownEl.textContent = 'Ended';
    
    const actionArea = document.getElementById('auc-action-area');
    if (actionArea) {
        actionArea.innerHTML = '<div class="auc-timer"><i class="bi bi-stop-circle"></i> <span>Auction ended</span></div>';
    }

    const myBidStatusEl = document.getElementById('auc-my-bid-status');
    if (myBidStatusEl) myBidStatusEl.style.display = 'none';

    const badgeEl = document.getElementById('auc-badge-status');
    if (badgeEl && !badgeEl.classList.contains('badge-paid')) {
        badgeEl.className = 'auc-badge badge-ended';
        badgeEl.innerHTML = '<i class="bi bi-stop-circle"></i> Auction Ended';
    }

    const winnerSec = document.getElementById('auc-winner-section');
    if (winnerSec) {
        if (hasBids && winnerId) {
            if (winnerId === currentUserId) {
                winnerSec.innerHTML = `
                    <div class="auc-winner-box">
                        <div style="font-weight:700;font-size:16px;margin-bottom:6px;"><i class="bi bi-trophy-fill" style="color:#f5af02;"></i> You won this auction!</div>
                        <div style="font-size:13px;color:var(--muted);margin-bottom:12px;">Your winning bid: <strong>₱${parseFloat(winningAmount).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</strong>. Complete your purchase to secure this item.</div>
                        <a href="../checkout/checkout.php?auction_id=${encodeURIComponent(aucId)}" class="auc-pay-btn">
                            <i class="bi bi-bag-check"></i> Pay Now — ₱${parseFloat(winningAmount).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}
                        </a>
                    </div>
                `;
            } else {
                winnerSec.innerHTML = `
                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:16px;margin-bottom:16px;font-size:14px;color:#856404;">
                        <i class="bi bi-exclamation-circle-fill"></i> This auction has ended. Another bidder won.
                    </div>
                `;
            }
        } else {
            winnerSec.innerHTML = '';
        }
    }
}

function pollAuctionDetails() {
    if (!isAuctionActive) return;
    fetch('../auction/get_bid.php?prod_id=' + encodeURIComponent(productId))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const priceEl = document.getElementById('auc-price');
                const bidCountEl = document.getElementById('auc-bid-count');
                const bidLabelEl = document.getElementById('auc-bid-label');
                
                if (priceEl) {
                    priceEl.textContent = '₱' + parseFloat(data.currentBid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                if (bidCountEl) {
                    bidCountEl.textContent = data.bidCount + ' bid' + (data.bidCount !== 1 ? 's' : '');
                }
                if (bidLabelEl) {
                    bidLabelEl.textContent = data.bidCount > 0 ? 'Current bid' : 'Starting bid';
                }

                if (data.status !== 'active' || data.timeLeft <= 0) {
                    isAuctionActive = false;
                    let winnerId = null;
                    let winningAmt = 0;
                    if (data.bids && data.bids.length > 0) {
                        winnerId = data.bids[0].userId;
                        winningAmt = data.bids[0].amount;
                    }
                    setAuctionEndedUI(data.bidCount > 0, winnerId, winningAmt);
                    if (pollTimer) clearInterval(pollTimer);
                } else {
                    const bidInput = document.getElementById('auc-bid-input');
                    const bidHint = document.getElementById('auc-bid-hint');
                    if (bidInput) {
                        bidInput.min = data.minBid;
                        if (parseFloat(bidInput.value || 0) < parseFloat(data.minBid)) {
                            bidInput.value = data.minBid;
                        }
                    }
                    if (bidHint) {
                        bidHint.textContent = 'Enter ₱' + parseFloat(data.minBid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' or more';
                    }

                    let myTopBid = 0;
                    data.bids.forEach(b => {
                        if (b.userId === currentUserId && b.amount > myTopBid) {
                            myTopBid = b.amount;
                        }
                    });
                    const myBidStatusEl = document.getElementById('auc-my-bid-status');
                    if (myBidStatusEl) {
                        if (myTopBid > 0) {
                            myBidStatusEl.style.display = 'block';
                            document.getElementById('auc-my-bid-amount').textContent = parseFloat(myTopBid).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            const comparisonEl = document.getElementById('auc-my-bid-comparison');
                            if (myTopBid >= parseFloat(data.currentBid)) {
                                comparisonEl.innerHTML = '<span style="color:#2e7d32;font-weight:600;"> — You are the highest bidder 🏆</span>';
                            } else {
                                comparisonEl.innerHTML = '<span style="color:#e53238;"> — You have been outbid</span>';
                            }
                        } else {
                            myBidStatusEl.style.display = 'none';
                        }
                    }
                }

                const bidsHistorySection = document.getElementById('auc-bids-history-section');
                const tbody = document.getElementById('auc-bids-tbody');
                const titleEl = document.getElementById('auc-bids-history-title');

                if (data.bids && data.bids.length > 0) {
                    if (bidsHistorySection) bidsHistorySection.style.display = 'block';
                    if (titleEl) titleEl.textContent = 'Bid History (' + data.bidCount + ')';
                    
                    if (tbody) {
                        tbody.innerHTML = '';
                        data.bids.forEach((b, index) => {
                            const isWinnerRow = index === 0;
                            const tr = document.createElement('tr');
                            if (isWinnerRow) tr.className = 'bid-winner-row';

                            let trophyCol = isWinnerRow ? '<i class="bi bi-trophy-fill" style="color:#f5af02;"></i>' : (index + 1);
                            let userCheck = isWinnerRow ? '<i class="bi bi-person-check-fill" style="color:#2e7d32;"></i> ' : '';
                            let youBadge = b.userId === currentUserId ? ' <span style="background:#e3f2fd;color:#1565c0;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">You</span>' : '';

                            tr.innerHTML = `
                                <td>${trophyCol}</td>
                                <td>
                                    ${userCheck}
                                    ${escapeHtml(b.userAccName)}
                                    ${youBadge}
                                </td>
                                <td><strong>₱${parseFloat(b.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></td>
                                <td style="color:var(--muted);">${escapeHtml(b.date)}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }
                } else {
                    if (bidsHistorySection) bidsHistorySection.style.display = 'none';
                }
            }
        })
        .catch(err => console.error('Error polling auction details:', err));
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

if (isAuctionActive) {
    setInterval(updateCountdown, 1000);
    updateCountdown();
    pollTimer = setInterval(pollAuctionDetails, 3000);
    pollAuctionDetails();
} else {
    fetch('../auction/get_bid.php?prod_id=' + encodeURIComponent(productId))
        .then(r => r.json())
        .then(data => {
            if (data.success && data.status !== 'active') {
                let winnerId = null;
                let winningAmt = 0;
                if (data.bids && data.bids.length > 0) {
                    winnerId = data.bids[0].userId;
                    winningAmt = data.bids[0].amount;
                }
                setAuctionEndedUI(data.bidCount > 0, winnerId, winningAmt);
            }
        });
}
</script>
