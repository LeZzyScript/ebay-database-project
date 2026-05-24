<?php
$conn = new mysqli("localhost", "root", "kyth119904", "ebayph", 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS Cart (
    Cart_UserID  CHAR(8) NOT NULL,
    Cart_ProdID  CHAR(8) NOT NULL,
    Cart_Qty     INT NOT NULL DEFAULT 1,
    Cart_DateAdd DATE NOT NULL,
    Cart_DateUpd DATE,
    PRIMARY KEY (Cart_UserID, Cart_ProdID)
)");

$conn->query("CREATE TABLE IF NOT EXISTS Wishlist (
    Wish_UserID   CHAR(8) NOT NULL,
    Wish_ProdID   CHAR(8) NOT NULL,
    Wish_DateAdd  DATE NOT NULL,
    Wish_PriceAdd DECIMAL(10,2) NOT NULL,
    Wish_Notified TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (Wish_UserID, Wish_ProdID)
)");

// Bid table: tracks all bids placed on auctions
$conn->query("CREATE TABLE IF NOT EXISTS Bid (
    Bid_ID      CHAR(8) PRIMARY KEY,
    Bid_AucID   CHAR(8) NOT NULL,
    Bid_UserID  CHAR(8) NOT NULL,
    Bid_Amount  DECIMAL(10,2) NOT NULL,
    Bid_Date    DATETIME NOT NULL
)");
?>