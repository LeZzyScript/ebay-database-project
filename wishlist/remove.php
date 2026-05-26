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

deleteData("wishlists/{$uid}/items/{$prodId}");

echo json_encode(['success' => true]);
