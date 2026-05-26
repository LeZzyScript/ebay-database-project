<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['firebase_uid'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once('../config/firebase.php');

$uid = $_SESSION['firebase_uid'];
$prodId = trim($_POST['prod_id'] ?? '');

if (!$prodId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

// Get current product to verify it exists
$product = getData("products/{$prodId}");

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$today = date('Y-m-d');

// Check if already wishlisted
$existing = getData("wishlists/{$uid}/items/{$prodId}");

if ($existing) {
    echo json_encode(['success' => true, 'message' => '"' . ($product['title'] ?? 'Item') . '" is already in your Watchlist']);
    exit;
}

// Add to wishlist
setData("wishlists/{$uid}/items/{$prodId}", [
    'dateAdded' => $today,
    'priceAdded' => $product['price'] ?? 0
]);

echo json_encode(['success' => true, 'message' => '"' . ($product['title'] ?? 'Item') . '" added to Watchlist']);
