<?php
session_start();
if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth/signin.php');
    exit;
}
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId    = $_SESSION['account_id'];
    $prodId    = trim($_POST['prod_id'] ?? '');
    $bidAmount = floatval($_POST['bid_amount'] ?? 0);

    if (!$prodId || $bidAmount <= 0) {
        $_SESSION['flash'] = "Invalid bid.";
        header('Location: ../product/product.php?id=' . urlencode($prodId));
        exit;
    }

    $conn->begin_transaction();
    try {
        // Lock auction row
        $stmtA = $conn->prepare("SELECT Auc_ID, Auc_StartPrice, Auc_HighBid, Auc_EndDate, Auc_Status FROM Auction WHERE Auc_ProdID = ? FOR UPDATE");
        $stmtA->bind_param("s", $prodId);
        $stmtA->execute();
        $auction = $stmtA->get_result()->fetch_assoc();
        $stmtA->close();

        if (!$auction || $auction['Auc_Status'] !== 'Active') {
            throw new Exception("This auction is no longer active.");
        }

        if (strtotime($auction['Auc_EndDate']) < time()) {
            $conn->query("UPDATE Auction SET Auc_Status = 'Ended' WHERE Auc_ID = '" . $conn->real_escape_string($auction['Auc_ID']) . "'");
            throw new Exception("This auction has ended.");
        }

        // Count existing bids
        $stmtC = $conn->prepare("SELECT COUNT(*) as c, MAX(Bid_Amount) as max FROM Bid WHERE Bid_AucID = ?");
        $stmtC->bind_param("s", $auction['Auc_ID']);
        $stmtC->execute();
        $bidStats = $stmtC->get_result()->fetch_assoc();
        $stmtC->close();

        $hasBids  = $bidStats['c'] > 0;
        $highBid  = $hasBids ? floatval($bidStats['max']) : floatval($auction['Auc_StartPrice']);
        $minBid   = $hasBids ? $highBid + 10 : $highBid;

        if ($bidAmount < $minBid) {
            throw new Exception("Bid must be at least ₱" . number_format($minBid, 2) . ".");
        }

        // Insert bid record
        $bidId = 'BID' . strtoupper(substr(md5(uniqid('', true)), 0, 5));
        $now   = date('Y-m-d H:i:s');
        $stmtB = $conn->prepare("INSERT INTO Bid (Bid_ID, Bid_AucID, Bid_UserID, Bid_Amount, Bid_Date) VALUES (?, ?, ?, ?, ?)");
        $stmtB->bind_param("sssds", $bidId, $auction['Auc_ID'], $userId, $bidAmount, $now);
        $stmtB->execute();
        $stmtB->close();

        // Update Auction high bid
        $stmtU = $conn->prepare("UPDATE Auction SET Auc_HighBid = ? WHERE Auc_ID = ?");
        $stmtU->bind_param("ds", $bidAmount, $auction['Auc_ID']);
        $stmtU->execute();
        $stmtU->close();

        $conn->commit();
        $_SESSION['flash'] = "🏆 You are the highest bidder at ₱" . number_format($bidAmount, 2) . "!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash'] = "Error: " . $e->getMessage();
    }

    header('Location: ../product/product.php?id=' . urlencode($prodId));
    exit;
} else {
    header('Location: ../index.php');
}
?>
