<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php');
    exit;
}
require_once('../config/firebase.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = $_SESSION['firebase_uid'];
    $prodId = trim($_POST['prod_id'] ?? '');
    $bidAmount = floatval($_POST['bid_amount'] ?? 0);

    if (!$prodId || $bidAmount <= 0) {
        $_SESSION['flash'] = "Invalid bid.";
        header('Location: ../product/product.php?id=' . urlencode($prodId));
        exit;
    }

    try {
        // Get product data to find auction
        $product = getData("products/{$prodId}");
        if (!$product || !isset($product['auctionData'])) {
            $_SESSION['flash'] = "Auction not found for this product.";
            header('Location: ../product/product.php?id=' . urlencode($prodId));
            exit;
        }

        $auctionId = $product['auctionData']['auctionId'];
        $auction = getData("auctions/{$auctionId}");

        if (!$auction || ($auction['status'] ?? '') !== 'active') {
            $_SESSION['flash'] = "This auction is no longer active.";
            header('Location: ../product/product.php?id=' . urlencode($prodId));
            exit;
        }

        if (strtotime($auction['endDate'] ?? '') < time()) {
            updateData("auctions/{$auctionId}/status", 'ended');
            updateData("products/{$prodId}/auctionData/status", 'ended');
            $_SESSION['flash'] = "This auction has ended.";
            header('Location: ../product/product.php?id=' . urlencode($prodId));
            exit;
        }

        // Get existing bids
        $bidsData = getData("auctions/{$auctionId}/bids");
        $hasBids = $bidsData && count($bidsData) > 0;
        $highBid = $hasBids ? max(array_column($bidsData, 'amount')) : ($auction['startPrice'] ?? 0);
        $minBid = $hasBids ? $highBid + 10 : $highBid;

        if ($bidAmount < $minBid) {
            $_SESSION['flash'] = "Bid must be at least ₱" . number_format($minBid, 2) . ".";
            header('Location: ../product/product.php?id=' . urlencode($prodId));
            exit;
        }

        // Add new bid
        $bidId = generateId('BID');
        $now = date('Y-m-d H:i:s');
        setData("auctions/{$auctionId}/bids/{$bidId}", [
            'userId' => $uid,
            'amount' => $bidAmount,
            'date' => $now
        ]);

        // Update auction high bid
        updateData("auctions/{$auctionId}/currentHighBid", $bidAmount);

        $_SESSION['flash'] = "🏆 You are the highest bidder at ₱" . number_format($bidAmount, 2) . "!";
    } catch (Exception $e) {
        $_SESSION['flash'] = "Error: " . $e->getMessage();
    }

    header('Location: ../product/product.php?id=' . urlencode($prodId));
    exit;
} else {
    header('Location: ../index.php');
}
?>
