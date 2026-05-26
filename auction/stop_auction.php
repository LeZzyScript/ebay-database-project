<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php');
    exit;
}

require_once('../config/firebase.php');
$uid = $_SESSION['firebase_uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aucId = trim($_POST['auction_id'] ?? '');
    $redirect = trim($_POST['redirect'] ?? '../seller/dashboard.php');
    
    if (!$aucId) {
        $_SESSION['flash'] = "Invalid auction ID.";
        header("Location: $redirect");
        exit;
    }
    
    // Fetch auction
    $auction = getData("auctions/{$aucId}");
    if (!$auction) {
        $_SESSION['flash'] = "Auction not found.";
        header("Location: $redirect");
        exit;
    }
    
    $productId = $auction['productId'] ?? '';
    if (!$productId) {
        $_SESSION['flash'] = "Product associated with the auction not found.";
        header("Location: $redirect");
        exit;
    }
    
    // Fetch product to verify seller ownership
    $product = getData("products/{$productId}");
    if (!$product) {
        $_SESSION['flash'] = "Product not found.";
        header("Location: $redirect");
        exit;
    }
    
    // Check ownership
    $isOwner = false;
    if ($uid === ($product['sellerId'] ?? '')) {
        $isOwner = true;
    } else {
        $currentUser = getData("users/{$uid}");
        $currentSellerId = $currentUser['sellerData']['sellerId'] ?? '';
        if ($currentSellerId && $currentSellerId === ($product['sellerId'] ?? '')) {
            $isOwner = true;
        }
    }
    
    if (!$isOwner) {
        $_SESSION['flash'] = "You are not authorized to stop this auction.";
        header("Location: $redirect");
        exit;
    }
    
    // Verify auction status is active
    if (($auction['status'] ?? '') !== 'active') {
        $_SESSION['flash'] = "Auction is not active.";
        header("Location: $redirect");
        exit;
    }
    
    // Update auction in Firebase
    $now = date('Y-m-d H:i:s');
    updateData("auctions/{$aucId}/status", 'ended');
    updateData("auctions/{$aucId}/endDate", $now);
    
    // Update product's auction data in Firebase
    updateData("products/{$productId}/auctionData/status", 'ended');
    updateData("products/{$productId}/auctionData/endDate", $now);
    
    $_SESSION['flash'] = "Auction stopped successfully! Expiry date updated.";
    header("Location: $redirect");
    exit;
} else {
    header('Location: ../seller/dashboard.php');
    exit;
}
