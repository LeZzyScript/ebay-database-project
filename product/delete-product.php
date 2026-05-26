<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || empty($_SESSION['is_seller'])) {
    header('Location: ../buyer/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['prod_id'])) {
    header('Location: ../seller/dashboard.php');
    exit;
}

require_once('../config/firebase.php');

$uid = $_SESSION['firebase_uid'];
$prodId = $_POST['prod_id'];

// Fetch product to verify ownership
$product = getData("products/{$prodId}");

if ($product && ($product['sellerId'] ?? '') === $uid) {
    // Delete product from Firebase
    deleteData("products/{$prodId}");
    $_SESSION['flash'] = "Listing removed successfully.";
} else {
    $_SESSION['flash'] = "Failed to remove listing.";
}

header("Location: ../seller/dashboard.php");
exit;
