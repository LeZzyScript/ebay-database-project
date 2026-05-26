<?php
header('Content-Type: application/json');
require_once('../config/firebase.php');

$prodId = $_GET['prod_id'] ?? '';
if (!$prodId) {
    echo json_encode(['success' => false, 'message' => 'Missing product ID']);
    exit;
}

$product = getData("products/{$prodId}");
if (!$product || !isset($product['auctionData'])) {
    echo json_encode(['success' => false, 'message' => 'Product is not an auction']);
    exit;
}

$auctionId = $product['auctionData']['auctionId'] ?? '';
$auction = getData("auctions/{$auctionId}");

if (!$auction) {
    echo json_encode(['success' => false, 'message' => 'Auction not found']);
    exit;
}

// Auto-close expired auctions in database
$endTime = strtotime($auction['endDate'] ?? 0);
$timeLeft = $endTime - time();
if (($auction['status'] ?? '') === 'active' && $timeLeft < 0) {
    updateData("auctions/{$auctionId}/status", 'ended');
    updateData("products/{$prodId}/auctionData/status", 'ended');
    $auction['status'] = 'ended';
}

$bids = getData("auctions/{$auctionId}/bids") ?: [];
$bidCount = count($bids);
$currentBid = ($auction['currentHighBid'] ?? 0) > 0 ? floatval($auction['currentHighBid']) : floatval($auction['startPrice'] ?? 0);
$minBid = $bidCount > 0 ? $currentBid + 10 : $currentBid;

// Get bid list sorted by amount desc for the bid history table
$bidsList = [];
$usersData = getData('users');
foreach ($bids as $bidId => $bidData) {
    $bidUserId = $bidData['userId'];
    $bidUserAccName = $usersData[$bidUserId]['profile']['accountName'] ?? 'Unknown';
    $bidsList[] = [
        'amount' => floatval($bidData['amount']),
        'date' => date('M j, Y g:i A', strtotime($bidData['date'])),
        'userId' => $bidUserId,
        'userAccName' => $bidUserAccName
    ];
}

usort($bidsList, function($a, $b) {
    return $b['amount'] - $a['amount'];
});

echo json_encode([
    'success' => true,
    'auctionId' => $auctionId,
    'currentBid' => $currentBid,
    'minBid' => $minBid,
    'bidCount' => $bidCount,
    'timeLeft' => $timeLeft,
    'status' => $auction['status'] ?? 'active',
    'bids' => $bidsList
]);
