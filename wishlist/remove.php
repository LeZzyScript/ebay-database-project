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

$stmt = $conn->prepare("DELETE FROM Wishlist WHERE Wish_UserID = ? AND Wish_ProdID = ?");
$stmt->bind_param("ss", $userId, $prodId);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to remove from wishlist']);
    $stmt->close();
    exit;
}
$stmt->close();

echo json_encode(['success' => true]);
