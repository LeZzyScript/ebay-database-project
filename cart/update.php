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
$qty    = intval($_POST['qty'] ?? 0);

if (!$prodId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

// qty <= 0 means remove
if ($qty <= 0) {
    $stmt = $conn->prepare("DELETE FROM Cart WHERE Cart_UserID = ? AND Cart_ProdID = ?");
    $stmt->bind_param("ss", $userId, $prodId);
    $stmt->execute();
    $stmt->close();
} else {
    // Check stock limit
    $stockStmt = $conn->prepare("SELECT Prod_Stock FROM Product WHERE Prod_ID = ?");
    $stockStmt->bind_param("s", $prodId);
    $stockStmt->execute();
    $stockRow = $stockStmt->get_result()->fetch_assoc();
    $stockStmt->close();

    if (!$stockRow) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    $qty = min($qty, $stockRow['Prod_Stock']);
    $today = date('Y-m-d');

    $stmt = $conn->prepare("UPDATE Cart SET Cart_Qty = ?, Cart_DateUpd = ? WHERE Cart_UserID = ? AND Cart_ProdID = ?");
    $stmt->bind_param("isss", $qty, $today, $userId, $prodId);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
        $stmt->close();
        exit;
    }
    $stmt->close();
}

// Refresh session cart count
$stmt2 = $conn->prepare("SELECT COALESCE(SUM(Cart_Qty), 0) AS total FROM Cart WHERE Cart_UserID = ?");
$stmt2->bind_param("s", $userId);
$stmt2->execute();
$countRow = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$_SESSION['cart_count'] = (int)$countRow['total'];

echo json_encode([
    'success'    => true,
    'cart_count' => $_SESSION['cart_count'],
    'new_qty'    => $qty,
]);
