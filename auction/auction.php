<?php
session_start();
if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth/signin.php?redirect=auction/auction.php?id=' . urlencode($_GET['id'] ?? ''));
    exit;
}
require_once('../config/db.php');
$userId = $_SESSION['account_id'];
$aucId  = trim($_GET['id'] ?? '');

if (!$aucId) { header('Location: ../index.php'); exit; }

// Fetch auction + product info
$stmt = $conn->prepare("
    SELECT a.*, p.Prod_Title, p.Prod_Desc, p.Prod_Image, p.Prod_ID,
           c.Cat_Name, c.Cat_ID,
           u.User_AccName, u.User_FName, u.User_LName
    FROM Auction a
    JOIN Product p ON a.Auc_ProdID = p.Prod_ID
    LEFT JOIN Category c ON p.Prod_CatID = c.Cat_ID
    LEFT JOIN Seller s ON p.Prod_SellID = s.Sell_ID
    LEFT JOIN User u ON s.Sell_UserID = u.User_ID
    WHERE a.Auc_ID = ?
");
$stmt->bind_param("s", $aucId);
$stmt->execute();
$auc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$auc) { $_SESSION['flash'] = "Auction not found."; header('Location: ../index.php'); exit; }

// Auto-close if past end date
if ($auc['Auc_Status'] === 'Active' && strtotime($auc['Auc_EndDate']) < time()) {
    $conn->query("UPDATE Auction SET Auc_Status='Ended' WHERE Auc_ID='" . $conn->real_escape_string($aucId) . "'");
    $auc['Auc_Status'] = 'Ended';
}

// Fetch all bids for this auction
$bids = [];
$stmtB = $conn->prepare("
    SELECT b.Bid_Amount, b.Bid_Date, b.Bid_UserID,
           u.User_AccName
    FROM Bid b
    JOIN User u ON b.Bid_UserID = u.User_ID
    WHERE b.Bid_AucID = ?
    ORDER BY b.Bid_Amount DESC
");
$stmtB->bind_param("s", $aucId);
$stmtB->execute();
$resB = $stmtB->get_result();
while ($r = $resB->fetch_assoc()) { $bids[] = $r; }
$stmtB->close();

$bidCount   = count($bids);
$currentBid = $auc['Auc_HighBid'] > 0 ? floatval($auc['Auc_HighBid']) : floatval($auc['Auc_StartPrice']);
$minBid     = $bidCount > 0 ? $currentBid + 10 : $currentBid;
$timeLeft   = strtotime($auc['Auc_EndDate']) - time();
$days  = floor($timeLeft / 86400);
$hours = floor(($timeLeft % 86400) / 3600);
$mins  = floor(($timeLeft % 3600) / 60);
$timeStr = $timeLeft < 0 ? 'Ended' : ($days > 0 ? "{$days}d {$hours}h left" : "{$hours}h {$mins}m left");

// Is current user the winner?
$myTopBid = 0;
foreach ($bids as $b) {
    if ($b['Bid_UserID'] === $userId && $b['Bid_Amount'] > $myTopBid) {
        $myTopBid = floatval($b['Bid_Amount']);
    }
}
$isWinner = $auc['Auc_Status'] === 'Ended' && $myTopBid >= $currentBid && $bidCount > 0;
$isActive = $auc['Auc_Status'] === 'Active' && $timeLeft > 0;

$sellerName = $auc['User_AccName'] ?? trim($auc['User_FName'] . ' ' . $auc['User_LName']);
$title    = 'Auction — ' . htmlspecialchars($auc['Prod_Title']);
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
        <a href="../product/product.php?id=<?= urlencode($auc['Prod_ID']) ?>" style="color:var(--muted);text-decoration:none;"><?= htmlspecialchars($auc['Prod_Title']) ?></a> &rsaquo;
        <span>Auction</span>
    </div>

    <div class="auc-layout">
        <!-- Image -->
        <div class="auc-img-col">
            <div class="auc-img-main">
                <?php if (!empty($auc['Prod_Image'])): ?>
                    <img src="<?= htmlspecialchars($auc['Prod_Image']) ?>" alt="<?= htmlspecialchars($auc['Prod_Title']) ?>">
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
            $badgeClass = match($auc['Auc_Status']) {
                'Active' => 'badge-active',
                'Paid'   => 'badge-paid',
                default  => 'badge-ended',
            };
            $badgeText = match($auc['Auc_Status']) {
                'Active' => '<i class="bi bi-broadcast"></i> Live Auction',
                'Paid'   => '<i class="bi bi-check-circle-fill"></i> Paid',
                default  => '<i class="bi bi-stop-circle"></i> Auction Ended',
            };
            ?>
            <div class="auc-badge <?= $badgeClass ?>"><?= $badgeText ?></div>
            <h1 class="auc-title"><?= htmlspecialchars($auc['Prod_Title']) ?></h1>

            <!-- Winner box -->
            <?php if ($isWinner): ?>
            <div class="auc-winner-box">
                <div style="font-weight:700;font-size:16px;margin-bottom:6px;"><i class="bi bi-trophy-fill" style="color:#f5af02;"></i> You won this auction!</div>
                <div style="font-size:13px;color:var(--muted);margin-bottom:12px;">Your winning bid: <strong>₱<?= number_format($myTopBid, 2) ?></strong>. Complete your purchase to secure this item.</div>
                <a href="../checkout/checkout.php?auction_id=<?= urlencode($aucId) ?>" class="auc-pay-btn">
                    <i class="bi bi-bag-check"></i> Pay Now — ₱<?= number_format($myTopBid, 2) ?>
                </a>
            </div>
            <?php elseif ($auc['Auc_Status'] === 'Paid'): ?>
            <div style="background:#e3f2fd;border:1px solid #90caf9;border-radius:12px;padding:16px;margin-bottom:16px;font-size:14px;color:#1565c0;font-weight:600;">
                <i class="bi bi-bag-check-fill"></i> This auction has been purchased by the winner.
            </div>
            <?php elseif ($auc['Auc_Status'] === 'Ended' && !$isWinner && $bidCount > 0): ?>
            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:16px;margin-bottom:16px;font-size:14px;color:#856404;">
                <i class="bi bi-exclamation-circle-fill"></i> This auction has ended. Another bidder won.
            </div>
            <?php endif; ?>

            <!-- Bid panel -->
            <div class="auc-panel">
                <div class="auc-panel-row">
                    <span class="auc-sub"><?= $bidCount > 0 ? 'Current bid' : 'Starting bid' ?></span>
                    <span class="auc-sub"><?= $bidCount ?> bid<?= $bidCount != 1 ? 's' : '' ?></span>
                </div>
                <div class="auc-price">₱<?= number_format($currentBid, 2) ?></div>

                <?php if ($auc['Auc_Status'] === 'Active'): ?>
                <div class="auc-timer">
                    <i class="bi bi-clock-history"></i>
                    <span id="countdown"><?= htmlspecialchars($timeStr) ?></span>
                    <span style="color:var(--muted);">&bull; Ends <?= date('M j, Y g:i A', strtotime($auc['Auc_EndDate'])) ?></span>
                </div>
                <?php if ($isActive): ?>
                <form method="POST" action="../auction/place_bid.php" class="auc-bid-form">
                    <input type="hidden" name="prod_id" value="<?= htmlspecialchars($auc['Prod_ID']) ?>">
                    <input type="number" name="bid_amount" class="auc-bid-input"
                           min="<?= $minBid ?>" step="0.01" value="<?= $minBid ?>" placeholder="Your bid">
                    <button type="submit" class="auc-bid-btn"><i class="bi bi-arrow-up-circle"></i> Place Bid</button>
                </form>
                <div style="font-size:12px;color:var(--muted);margin-top:8px;text-align:center;">Enter ₱<?= number_format($minBid, 2) ?> or more</div>
                <?php endif; ?>
                <?php else: ?>
                <div class="auc-timer"><i class="bi bi-stop-circle"></i> <span>Auction ended <?= date('M j, Y g:i A', strtotime($auc['Auc_EndDate'])) ?></span></div>
                <?php endif; ?>
            </div>

            <?php if ($myTopBid > 0 && $isActive): ?>
            <div style="font-size:13px;color:var(--muted);padding:8px 0;">
                Your current bid: <strong>₱<?= number_format($myTopBid, 2) ?></strong>
                <?= $myTopBid >= $currentBid ? '<span style="color:#2e7d32;font-weight:600;"> — You are the highest bidder 🏆</span>' : '<span style="color:#e53238;"> — You have been outbid</span>' ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bid History -->
    <?php if (!empty($bids)): ?>
    <div class="bids-section">
        <h2 style="font-size:20px;font-weight:700;margin-bottom:16px;">Bid History (<?= $bidCount ?>)</h2>
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
                <tbody>
                    <?php foreach ($bids as $i => $b): ?>
                    <tr <?= $i === 0 ? 'class="bid-winner-row"' : '' ?>>
                        <td><?= $i === 0 ? '<i class="bi bi-trophy-fill" style="color:#f5af02;"></i>' : ($i + 1) ?></td>
                        <td>
                            <?= $i === 0 ? '<i class="bi bi-person-check-fill" style="color:#2e7d32;"></i> ' : '' ?>
                            <?= htmlspecialchars($b['User_AccName']) ?>
                            <?= $b['Bid_UserID'] === $userId ? ' <span style="background:#e3f2fd;color:#1565c0;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">You</span>' : '' ?>
                        </td>
                        <td><strong>₱<?= number_format($b['Bid_Amount'], 2) ?></strong></td>
                        <td style="color:var(--muted);"><?= date('M j, Y g:i A', strtotime($b['Bid_Date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include('../layout/footer.php'); ?>
<script>
// Live countdown
<?php if ($isActive && $timeLeft > 0): ?>
let endTs = <?= strtotime($auc['Auc_EndDate']) * 1000 ?>;
function updateCountdown() {
    let rem = Math.floor((endTs - Date.now()) / 1000);
    if (rem <= 0) { document.getElementById('countdown').textContent = 'Ended'; return; }
    let d = Math.floor(rem / 86400);
    let h = Math.floor((rem % 86400) / 3600);
    let m = Math.floor((rem % 3600) / 60);
    let s = rem % 60;
    document.getElementById('countdown').textContent = d > 0
        ? `${d}d ${h}h ${m}m left`
        : `${h}h ${m}m ${s}s left`;
}
setInterval(updateCountdown, 1000);
updateCountdown();
<?php endif; ?>
</script>
