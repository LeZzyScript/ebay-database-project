<?php
session_start();
if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth/signin.php?redirect=buyer/orders.php');
    exit;
}

require_once('../config/db.php');
$userId = $_SESSION['account_id'];

// Advance shipment statuses based on dates
$today = date('Y-m-d');
$conn->query("UPDATE Shipment SET Ship_Status = 'Delivered' WHERE Ship_Status != 'Delivered' AND Ship_DelivDate <= '$today'");
$conn->query("UPDATE Shipment SET Ship_Status = 'In Transit' WHERE Ship_Status IN ('Processing', 'Picked Up') AND Ship_TransitDate <= '$today' AND Ship_DelivDate > '$today'");
$conn->query("UPDATE Shipment SET Ship_Status = 'Picked Up' WHERE Ship_Status = 'Processing' AND Ship_PickupDate <= '$today' AND Ship_TransitDate > '$today'");

// Fetch all orders for this buyer, newest first
$orders = [];
$stmt = $conn->prepare("
    SELECT o.*,
           s.Ship_ID, s.Ship_Status, s.Ship_PickupDate, s.Ship_TransitDate, s.Ship_DelivDate
    FROM `Order` o
    LEFT JOIN Shipment s ON o.Order_ID = s.Ship_OrderID
    WHERE o.Order_UserID = ?
    ORDER BY o.Order_Date DESC
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// Fetch feedbacks left by this user
$feedbackMap = [];
$stmtF = $conn->prepare("SELECT Feed_OrderID FROM Feedback WHERE Feed_UserID = ?");
$stmtF->bind_param("s", $userId);
$stmtF->execute();
$resF = $stmtF->get_result();
while ($row = $resF->fetch_assoc()) {
    $feedbackMap[$row['Feed_OrderID']] = true;
}
$stmtF->close();

// Fetch order items grouped by order_id
$orderItems = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'Order_ID');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $types = str_repeat('s', count($orderIds));
    $stmtI = $conn->prepare("
        SELECT oi.Item_OrderID, oi.Item_Qty, oi.Item_Price, oi.Item_Sub,
               p.Prod_ID, p.Prod_Title, p.Prod_Image
        FROM OrderItem oi
        JOIN Product p ON oi.Item_ProdID = p.Prod_ID
        WHERE oi.Item_OrderID IN ($placeholders)
        ORDER BY oi.Item_ID
    ");
    $stmtI->bind_param($types, ...$orderIds);
    $stmtI->execute();
    $resI = $stmtI->get_result();
    while ($item = $resI->fetch_assoc()) {
        $orderItems[$item['Item_OrderID']][] = $item;
    }
    $stmtI->close();
}

$title    = 'My Orders';
$basePath = '../';
include('../layout/layout.php');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
/* ── Container ── */
.orders-wrap {
    max-width: 1000px;
    margin: 28px auto;
    padding: 0 20px;
}
.orders-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}
.orders-count {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 28px;
}

/* ── Empty state ── */
.orders-empty {
    text-align: center;
    padding: 64px 20px;
    background: #f7f7f7;
    border-radius: 12px;
}
.orders-empty i  { font-size: 56px; color: var(--muted); display: block; margin-bottom: 16px; }
.orders-empty h3 { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
.orders-empty a  {
    display: inline-block;
    margin-top: 16px;
    background: var(--blue);
    color: #fff;
    padding: 12px 28px;
    border-radius: 24px;
    font-weight: 600;
    text-decoration: none;
}

/* ── Order card ── */
.order-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
}

/* Card header */
.order-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: #f7f7f7;
    border-bottom: 1px solid var(--border);
    gap: 12px;
    flex-wrap: wrap;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
}
.order-header:hover { background: #efefef; }

.order-meta {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    flex: 1;
    min-width: 0;
}
.order-id {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
}
.order-date {
    font-size: 13px;
    color: var(--muted);
}
.order-total {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.badge-paid        { background: #d4edda; color: #155724; }
.badge-pending     { background: #fff3cd; color: #856404; }
.badge-cancelled   { background: #f8d7da; color: #721c24; }
.badge-shipped     { background: #cce5ff; color: #004085; }
.badge-delivered   { background: #d1ecf1; color: #0c5460; }
.badge-unshipped   { background: #e9ecef; color: #495057; }
.badge-processing  { background: #e8f4fd; color: #1a5276; }
.badge-in-transit  { background: #fff8e1; color: #7d6608; }

.order-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.chevron {
    font-size: 18px;
    color: var(--muted);
    transition: transform 0.2s;
}
.chevron.open { transform: rotate(180deg); }

/* Track button */
.btn-track {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    background: var(--blue);
    color: #fff;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
}
.btn-track:hover { background: #2b55d9; color: #fff; text-decoration: none; }

/* Items list (collapsible) */
.order-items {
    display: none;
    padding: 0 20px;
}
.order-items.open { display: block; }

/* Item row — mirrors cart.php */
.order-item {
    display: flex;
    gap: 20px;
    padding: 20px 0;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
}
.order-item:last-child { border-bottom: none; }

.item-img-wrap {
    width: 100px;
    height: 100px;
    flex-shrink: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fafafa;
    text-decoration: none;
}
.item-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.item-img-wrap i   { font-size: 2.5rem; color: var(--muted); }

.item-info { flex: 1; min-width: 0; }
.item-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--blue);
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.item-title:hover { text-decoration: underline; }
.item-cond  { font-size: 13px; color: var(--muted); margin-bottom: 8px; }
.item-price { font-size: 18px; font-weight: 700; }
.item-qty   { font-size: 13px; color: var(--muted); margin-top: 4px; }

.item-sub {
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
    text-align: right;
    flex-shrink: 0;
    white-space: nowrap;
    padding-top: 4px;
}

/* Order footer (total row) */
.order-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    background: #fafafa;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    align-items: center;
    font-size: 14px;
    flex-wrap: wrap;
}
.order-footer-total {
    font-weight: 700;
    font-size: 16px;
}

/* Filter bar */
.filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.filter-btn {
    padding: 7px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.15s;
    font-family: var(--font);
}
.filter-btn:hover  { border-color: var(--text); color: var(--text); }
.filter-btn.active { background: var(--text); color: #fff; border-color: var(--text); }

@media (max-width: 600px) {
    .order-meta  { gap: 10px; }
    .item-img-wrap { width: 72px; height: 72px; }
    .item-title    { font-size: 14px; }
    .item-price    { font-size: 16px; }
}
</style>

<div class="orders-wrap">
    <h1 class="orders-title">My Orders</h1>
    <div class="orders-count"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> total</div>

    <?php if (empty($orders)): ?>
        <div class="orders-empty">
            <i class="bi bi-bag-x"></i>
            <h3>No orders yet</h3>
            <p style="color:var(--muted);">When you buy something, your orders will show up here.</p>
            <a href="../buyer/dashboard.php">Start shopping</a>
        </div>

    <?php else: ?>

        <!-- Filter buttons -->
        <div class="filter-bar" id="filterBar">
            <button class="filter-btn active" onclick="filterOrders('all', this)">All orders</button>
            <button class="filter-btn" onclick="filterOrders('paid', this)">Paid</button>
            <button class="filter-btn" onclick="filterOrders('pending', this)">Pending</button>
            <button class="filter-btn" onclick="filterOrders('cancelled', this)">Cancelled</button>
        </div>

        <div id="orderList">
        <?php foreach ($orders as $ord):
            $items    = $orderItems[$ord['Order_ID']] ?? [];
            $payStat  = strtolower($ord['Order_PayStat'] ?? 'pending');
            $shipStat = $ord['Ship_Status'] ?? '';

            // Pay badge
            $payBadge = match($payStat) {
                'paid'      => ['badge-paid',      'bi-check-circle-fill', 'Paid'],
                'cancelled' => ['badge-cancelled',  'bi-x-circle-fill',    'Cancelled'],
                default     => ['badge-pending',    'bi-clock-fill',       'Pending'],
            };

            // Ship / Req badge
            $reqStat = $ord['Order_ReqStatus'] ?? 'Pending';
            if ($reqStat === 'Pending') {
                $shipBadge = ['badge-pending', 'bi-hourglass-split', 'Pending Seller Acceptance'];
            } elseif ($reqStat === 'Rejected') {
                $shipBadge = ['badge-cancelled', 'bi-x-circle-fill', 'Rejected by Seller'];
            } else {
                if (!$ord['Ship_ID']) {
                    $shipBadge = ['badge-unshipped', 'bi-box-seam', 'Unshipped'];
                } else {
                    $shipBadge = match($shipStat) {
                        'Picked Up'  => ['badge-shipped',     'bi-truck',           'Picked Up'],
                        'In Transit' => ['badge-in-transit',  'bi-arrow-right-circle-fill', 'In Transit'],
                        'Delivered'  => ['badge-delivered',   'bi-house-check-fill','Delivered'],
                        default      => ['badge-processing',  'bi-gear-fill',       'Processing'],
                    };
                }
            }

            // Thumb images (first 3 items)
            $thumbs = array_slice($items, 0, 3);
        ?>
        <div class="order-card" data-pay="<?= htmlspecialchars($payStat) ?>">

            <!-- Clickable header -->
            <div class="order-header" onclick="toggleOrder('<?= $ord['Order_ID'] ?>')">
                <div class="order-meta">
                    <span class="order-id">Order #<?= htmlspecialchars($ord['Order_ID']) ?></span>
                    <span class="order-date">
                        <i class="bi bi-calendar3" style="margin-right:4px;"></i>
                        <?= date('M j, Y', strtotime($ord['Order_Date'])) ?>
                    </span>
                    <span class="status-badge <?= $payBadge[0] ?>">
                        <i class="bi <?= $payBadge[1] ?>"></i><?= $payBadge[2] ?>
                    </span>
                    <?php if ($reqStat !== 'Pending' && $reqStat !== 'Rejected'): ?>
                        <?php if ($ord['Ship_ID']): ?>
                        <span class="status-badge <?= $shipBadge[0] ?>">
                            <i class="bi <?= $shipBadge[1] ?>"></i><?= $shipBadge[2] ?>
                        </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="status-badge <?= $shipBadge[0] ?>">
                            <i class="bi <?= $shipBadge[1] ?>"></i><?= $shipBadge[2] ?>
                        </span>
                    <?php endif; ?>
                    <span class="order-total">₱<?= number_format($ord['Order_Total'], 2) ?></span>
                </div>
                <div class="order-actions">
                    <?php if ($ord['Ship_ID']): ?>
                        <a href="../shipment/shipment.php?order_id=<?= urlencode($ord['Order_ID']) ?>"
                           class="btn-track" target="_blank" onclick="event.stopPropagation()">
                            <i class="bi bi-truck"></i> Track
                        </a>
                    <?php endif; ?>
                    <i class="bi bi-chevron-down chevron" id="chev-<?= $ord['Order_ID'] ?>"></i>
                </div>
            </div>

            <!-- Expandable items -->
            <div class="order-items" id="items-<?= $ord['Order_ID'] ?>">

                <?php if (empty($items)): ?>
                    <div style="padding:20px 0; color:var(--muted); font-size:14px;">No item details available.</div>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                    <div class="order-item">
                        <a href="../product/product.php?id=<?= urlencode($it['Prod_ID']) ?>"
                           class="item-img-wrap" target="_blank">
                            <?php if (!empty($it['Prod_Image'])): ?>
                                <img src="<?= htmlspecialchars($it['Prod_Image']) ?>"
                                     alt="<?= htmlspecialchars($it['Prod_Title']) ?>">
                            <?php else: ?>
                                <i class="bi bi-box"></i>
                            <?php endif; ?>
                        </a>
                        <div class="item-info">
                            <a href="../product/product.php?id=<?= urlencode($it['Prod_ID']) ?>"
                               class="item-title" target="_blank">
                                <?= htmlspecialchars($it['Prod_Title']) ?>
                            </a>
                            <div class="item-cond">Brand New</div>
                            <div class="item-price">₱<?= number_format($it['Item_Price'], 2) ?></div>
                            <div class="item-qty">Qty: <?= $it['Item_Qty'] ?></div>
                        </div>
                        <div class="item-sub">₱<?= number_format($it['Item_Sub'], 2) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Order footer -->
                <div class="order-footer">
                    <span style="color:var(--muted);">Order total:</span>
                    <span class="order-footer-total">₱<?= number_format($ord['Order_Total'], 2) ?></span>
                    <?php if ($ord['Ship_ID']): ?>
                    <a href="../shipment/shipment.php?order_id=<?= urlencode($ord['Order_ID']) ?>"
                       class="btn-track" target="_blank">
                        <i class="bi bi-truck"></i> Track Package
                    </a>
                    <?php endif; ?>
                    <?php if (strtolower($shipStat) === 'delivered' && !isset($feedbackMap[$ord['Order_ID']])): ?>
                    <a href="feedback.php?order_id=<?= urlencode($ord['Order_ID']) ?>" class="btn-track" style="background:#2e7d32;">
                        <i class="bi bi-star"></i> Leave Feedback
                    </a>
                    <?php elseif (isset($feedbackMap[$ord['Order_ID']])): ?>
                    <span style="font-size:13px; color:#2e7d32; font-weight:600; margin-left:8px;"><i class="bi bi-check-circle"></i> Feedback left</span>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
        </div><!-- /#orderList -->

    <?php endif; ?>
</div>

<?php include('../layout/footer.php'); ?>

<script>
/* ── Toggle order items panel ── */
function toggleOrder(orderId) {
    const panel = document.getElementById('items-' + orderId);
    const chev  = document.getElementById('chev-'  + orderId);
    if (!panel) return;
    const isOpen = panel.classList.contains('open');
    panel.classList.toggle('open', !isOpen);
    chev.classList.toggle('open', !isOpen);
}

/* ── Filter by payment status ── */
function filterOrders(status, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('.order-card').forEach(card => {
        const pay = card.dataset.pay;
        card.style.display = (status === 'all' || pay === status) ? '' : 'none';
    });
}
</script>
