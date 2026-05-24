<?php
session_start();
if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth/signin.php');
    exit;
}

require_once('../config/db.php');
$userId = $_SESSION['account_id'];
$orderId = $_GET['order_id'] ?? $_POST['order_id'] ?? '';

if (!$orderId) {
    header('Location: orders.php');
    exit;
}

// 1. Verify Order belongs to user and is Delivered
$stmt = $conn->prepare("
    SELECT o.Order_ID, s.Ship_Status 
    FROM `Order` o
    LEFT JOIN Shipment s ON o.Order_ID = s.Ship_OrderID
    WHERE o.Order_ID = ? AND o.Order_UserID = ?
");
$stmt->bind_param("ss", $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order || strtolower($order['Ship_Status'] ?? '') !== 'delivered') {
    $_SESSION['flash'] = "You can only leave feedback for delivered orders.";
    header('Location: orders.php');
    exit;
}

// 2. Check if feedback already exists for this order
$stmt = $conn->prepare("SELECT 1 FROM Feedback WHERE Feed_OrderID = ? AND Feed_UserID = ? LIMIT 1");
$stmt->bind_param("ss", $orderId, $userId);
$stmt->execute();
$hasFeedback = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($hasFeedback) {
    $_SESSION['flash'] = "You have already left feedback for this order.";
    header('Location: orders.php');
    exit;
}

// 3. Get all sellers involved in this order
$sellers = [];
$stmt = $conn->prepare("
    SELECT DISTINCT p.Prod_SellID, u.User_AccName, u.User_FName, u.User_LName
    FROM OrderItem oi
    JOIN Product p ON oi.Item_ProdID = p.Prod_ID
    JOIN Seller s ON p.Prod_SellID = s.Sell_ID
    JOIN User u ON s.Sell_UserID = u.User_ID
    WHERE oi.Item_OrderID = ?
");
$stmt->bind_param("s", $orderId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $sellers[] = $row;
}
$stmt->close();

// 4. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $today = date('Y-m-d');
        foreach ($sellers as $seller) {
            $sellId = $seller['Prod_SellID'];
            $rating = (int)($_POST['rating'][$sellId] ?? 5);
            $comment = trim($_POST['comment'][$sellId] ?? '');
            
            if ($rating < 1 || $rating > 5) $rating = 5; // Fallback
            
            $feedId = 'FDBK' . strtoupper(substr(md5(uniqid('', true)), 0, 4));
            
            $stmtF = $conn->prepare("INSERT INTO Feedback (Feed_ID, Feed_OrderID, Feed_SellID, Feed_UserID, Feed_Rating, Feed_Comment, Feed_Date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtF->bind_param("ssssiss", $feedId, $orderId, $sellId, $userId, $rating, $comment, $today);
            $stmtF->execute();
        }
        $conn->commit();
        $_SESSION['flash'] = "Thank you! Your feedback has been submitted.";
        header('Location: orders.php');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to submit feedback: " . $e->getMessage();
    }
}

$title = "Leave Feedback";
$basePath = '../';
include('../layout/layout.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.fb-wrap { max-width: 600px; margin: 40px auto; padding: 0 20px; }
.fb-title { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
.fb-sub { font-size: 14px; color: var(--muted); margin-bottom: 30px; }

.fb-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}
.fb-seller-hdr {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}
.fb-seller-icon {
    width: 40px; height: 40px;
    background: var(--blue); color: #fff;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 20px; font-weight: bold;
}
.fb-seller-name { font-size: 16px; font-weight: 600; }

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 4px;
    margin-bottom: 20px;
}
.star-rating input { display: none; }
.star-rating label {
    font-size: 32px; color: #e5e5e5; cursor: pointer; transition: color 0.2s;
}
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label { color: #f5af02; }

.fb-comment {
    width: 100%; height: 100px;
    border: 1px solid var(--border); border-radius: 8px;
    padding: 12px; font-family: var(--font); font-size: 14px;
    resize: vertical; outline: none;
}
.fb-comment:focus { border-color: var(--blue); }

.fb-submit {
    width: 100%; background: var(--blue); color: #fff; border: none;
    padding: 14px; border-radius: 24px; font-size: 16px; font-weight: 600;
    cursor: pointer; transition: opacity 0.2s; margin-bottom: 40px;
}
.fb-submit:hover { opacity: 0.9; }

.alert-error { background: #fff0f0; border: 1px solid #f5c6c6; color: var(--red); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
</style>

<div class="fb-wrap">
    <h1 class="fb-title">Leave Feedback</h1>
    <p class="fb-sub">Order #<?= htmlspecialchars($orderId) ?> has been delivered. How was your experience with the seller(s)?</p>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="order_id" value="<?= htmlspecialchars($orderId) ?>">
        
        <?php foreach ($sellers as $seller): 
            $sId = $seller['Prod_SellID'];
            $sName = $seller['User_AccName'] ?: ($seller['User_FName'] . ' ' . $seller['User_LName']);
        ?>
        <div class="fb-card">
            <div class="fb-seller-hdr">
                <div class="fb-seller-icon"><i class="bi bi-shop"></i></div>
                <div>
                    <div style="font-size:12px; color:var(--muted);">Seller</div>
                    <div class="fb-seller-name"><?= htmlspecialchars($sName) ?></div>
                </div>
            </div>
            
            <div style="font-weight:600; margin-bottom:12px; font-size:14px;">Rate this seller</div>
            <div class="star-rating">
                <input type="radio" id="star5-<?= $sId ?>" name="rating[<?= $sId ?>]" value="5" checked />
                <label for="star5-<?= $sId ?>" class="bi bi-star-fill"></label>
                <input type="radio" id="star4-<?= $sId ?>" name="rating[<?= $sId ?>]" value="4" />
                <label for="star4-<?= $sId ?>" class="bi bi-star-fill"></label>
                <input type="radio" id="star3-<?= $sId ?>" name="rating[<?= $sId ?>]" value="3" />
                <label for="star3-<?= $sId ?>" class="bi bi-star-fill"></label>
                <input type="radio" id="star2-<?= $sId ?>" name="rating[<?= $sId ?>]" value="2" />
                <label for="star2-<?= $sId ?>" class="bi bi-star-fill"></label>
                <input type="radio" id="star1-<?= $sId ?>" name="rating[<?= $sId ?>]" value="1" />
                <label for="star1-<?= $sId ?>" class="bi bi-star-fill"></label>
            </div>
            
            <div style="font-weight:600; margin-bottom:8px; font-size:14px;">Tell us more (Optional)</div>
            <textarea class="fb-comment" name="comment[<?= $sId ?>]" placeholder="How was the item? Was it described accurately?"></textarea>
        </div>
        <?php endforeach; ?>
        
        <button type="submit" class="fb-submit">Submit Feedback</button>
    </form>
</div>

<?php include('../layout/footer.php'); ?>
