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
$qty    = max(1, intval($_POST['qty'] ?? 1));

if (!$prodId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

// Verify product exists, is active, and has stock
$stmt = $conn->prepare("SELECT Prod_Stock, Prod_Title FROM Product WHERE Prod_ID = ? AND Prod_Status = 'active'");
$stmt->bind_param("s", $prodId);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prod) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}
if ($prod['Prod_Stock'] < 1) {
    echo json_encode(['success' => false, 'message' => 'This item is out of stock']);
    exit;
}

$today = date('Y-m-d');

// INSERT new row or increment qty on duplicate
$stmt2 = $conn->prepare("
    INSERT INTO Cart (Cart_UserID, Cart_ProdID, Cart_Qty, Cart_DateAdd)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE Cart_Qty = Cart_Qty + ?, Cart_DateUpd = ?
");
$stmt2->bind_param("ssisss", $userId, $prodId, $qty, $today, $qty, $today);

if (!$stmt2->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
    $stmt2->close();
    exit;
}
$stmt2->close();

// Refresh session cart count
$stmt3 = $conn->prepare("SELECT COALESCE(SUM(Cart_Qty), 0) AS total FROM Cart WHERE Cart_UserID = ?");
$stmt3->bind_param("s", $userId);
$stmt3->execute();
$countRow = $stmt3->get_result()->fetch_assoc();
$stmt3->close();

$_SESSION['cart_count'] = (int)$countRow['total'];

echo json_encode([
    'success'    => true,
    'cart_count' => $_SESSION['cart_count'],
    'message'    => '"' . $prod['Prod_Title'] . '" added to cart',
]);
