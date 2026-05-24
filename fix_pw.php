<?php
require_once __DIR__ . '/config/db.php';
$p1 = password_hash('12345678', PASSWORD_DEFAULT);
$p2 = password_hash('ad123min456', PASSWORD_DEFAULT);
$p3 = password_hash('juan123456', PASSWORD_DEFAULT);

$conn->query("UPDATE User SET User_Password='$p1' WHERE User_AccName='kythseller'");
$conn->query("UPDATE User SET User_Password='$p2' WHERE User_AccName='admin'");

echo "Passwords updated successfully.";
?>
