<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['account_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once('../config/db.php');

$userId = $_SESSION['account_id'];
$prodId = trim($_POST['prod_id'] ?? '');

if (!$prodId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

// Get current product price to store at time of wishlisting
$stmt = $conn->prepare("SELECT Prod_Price, Prod_Title FROM Product WHERE Prod_ID = ? AND Prod_Status = 'active'");
$stmt->bind_param("s", $prodId);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prod) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$today = date('Y-m-d');

// INSERT — ignore if already wishlisted (idempotent)
$stmt2 = $conn->prepare("
    INSERT IGNORE INTO Wishlist (Wish_UserID, Wish_ProdID, Wish_DateAdd, Wish_PriceAdd)
    VALUES (?, ?, ?, ?)
");
$stmt2->bind_param("sssd", $userId, $prodId, $today, $prod['Prod_Price']);

if (!$stmt2->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist']);
    $stmt2->close();
    exit;
}
$stmt2->close();

echo json_encode(['success' => true, 'message' => '"' . $prod['Prod_Title'] . '" added to Watchlist']);
