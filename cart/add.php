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
$qty    = max(1, intval($_POST['qty'] ?? 1));

if (!$prodId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

// Verify product exists, is active, and has stock
$product = getData("products/{$prodId}");

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}
if (($product['stock'] ?? 0) < 1) {
    echo json_encode(['success' => false, 'message' => 'This item is out of stock']);
    exit;
}

$today = date('Y-m-d');

// Get existing cart item
$existingItem = getData("carts/{$uid}/items/{$prodId}");

if ($existingItem) {
    // Update existing item quantity
    $newQty = ($existingItem['quantity'] ?? 0) + $qty;
    updateData("carts/{$uid}/items/{$prodId}", [
        'quantity' => $newQty,
        'dateUpdated' => $today
    ]);
} else {
    // Add new cart item
    setData("carts/{$uid}/items/{$prodId}", [
        'quantity' => $qty,
        'dateAdded' => $today,
        'dateUpdated' => $today
    ]);
}

// Calculate total cart count
$cartItems = getData("carts/{$uid}/items");
$cartCount = 0;
if ($cartItems) {
    foreach ($cartItems as $item) {
        $cartCount += ($item['quantity'] ?? 0);
    }
}

$_SESSION['cart_count'] = $cartCount;

echo json_encode([
    'success'    => true,
    'cart_count' => $cartCount,
    'message'    => '"' . ($product['title'] ?? 'Item') . '" added to cart',
]);
