<?php
session_start();
if (!isset($_SESSION['account_id']) || empty($_SESSION['is_seller'])) {
    header('Location: ../buyer/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['prod_id'])) {
    header('Location: ../seller/dashboard.php');
    exit;
}

require_once('../config/db.php');

$prodId = $_POST['prod_id'];

// Get seller ID
$sellId = null;
$stmt = $conn->prepare("SELECT Sell_ID FROM Seller WHERE Sell_UserID = ?");
$stmt->bind_param("s", $_SESSION['account_id']);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $sellId = $row['Sell_ID'];
}
$stmt->close();

if ($sellId) {
    // Delete product only if it belongs to this seller
    $stmt2 = $conn->prepare("DELETE FROM Product WHERE Prod_ID = ? AND Prod_SellID = ?");
    $stmt2->bind_param("ss", $prodId, $sellId);
    if ($stmt2->execute()) {
        $_SESSION['flash'] = "Listing removed successfully.";
    } else {
        $_SESSION['flash'] = "Failed to remove listing.";
    }
    $stmt2->close();
}

header("Location: ../seller/dashboard.php");
exit;
