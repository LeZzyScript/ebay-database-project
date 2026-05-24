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

$stmt = $conn->prepare("DELETE FROM Cart WHERE Cart_UserID = ? AND Cart_ProdID = ?");
$stmt->bind_param("ss", $userId, $prodId);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
    $stmt->close();
    exit;
}
$stmt->close();

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
]);
