<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php?redirect=buyer/orders.php');
    exit;
}

require_once('../config/firebase.php');
$uid = $_SESSION['firebase_uid'];

// Fetch won auctions pending checkout
$pendingAuctions = [];
$allAuctions = getData('auctions') ?: [];
foreach ($allAuctions as $aucId => $aucData) {
    $aucStatus = $aucData['status'] ?? 'active';
    $endDate = $aucData['endDate'] ?? '';
    
    // Treat as ended if status is ended or if active but past end date
    $isEnded = ($aucStatus === 'ended') || ($aucStatus === 'active' && strtotime($endDate) < time());
    
    if ($isEnded) {
        // Find highest bid
        $highestBid = 0;
        $winnerId = null;
        $bidsData = $aucData['bids'] ?? [];
        if ($bidsData) {
            foreach ($bidsData as $bid) {
                $amt = floatval($bid['amount'] ?? 0);
                if ($amt > $highestBid) {
                    $highestBid = $amt;
                    $winnerId = $bid['userId'] ?? null;
                }
            }
        }
        
        // If current user is the winner and has not paid yet
        if ($winnerId === $uid) {
            $prodId = $aucData['productId'] ?? '';
            $product = getData("products/{$prodId}");
            if ($product) {
                $pendingAuctions[] = [
                    'aucId' => $aucId,
                    'prodId' => $prodId,
                    'title' => $product['title'] ?? 'Unknown Item',
                    'image' => $product['image'] ?? '',
                    'price' => $highestBid,
                    'endDate' => $endDate
                ];
            }
        }
    }
}

// Fetch all orders for this buyer
$ordersData = getData('orders');
$orders = [];
if ($ordersData) {
    foreach ($ordersData as $orderId => $orderData) {
        if (($orderData['userId'] ?? '') === $uid) {
            // Calculate shipment dates based on order date
            $orderDate = $orderData['date'] ?? date('Y-m-d');
            $orderDateObj = new DateTime($orderDate);
            $pickupDate = clone $orderDateObj;
            $pickupDate->modify('+2 days');
            $transitDate = clone $pickupDate;
            $transitDate->modify('+3 days');
            $deliveryDate = clone $transitDate;
            $deliveryDate->modify('+2 days');
            
            // Map order status to shipment status
            $status = $orderData['status'] ?? 'Processing';
            $shipStatus = 'Processing';
            if ($status === 'Shipped') {
                $shipStatus = 'In Transit';
            } elseif ($status === 'Delivered') {
                $shipStatus = 'Delivered';
            }
            
            $orders[] = [
                'Order_ID' => $orderId,
                'Order_Date' => $orderDate,
                'Order_Total' => $orderData['total'] ?? 0,
                'Order_PayStat' => $orderData['paymentStatus'] ?? 'Paid',
                'Order_ReqStatus' => ($status === 'Pending') ? 'Pending' : (($status === 'Rejected') ? 'Rejected' : 'Approved'),
                'Ship_ID' => ($status !== 'Pending' && $status !== 'Rejected') ? $orderId : null,
                'Ship_Status' => $shipStatus,
                'Ship_PickupDate' => $pickupDate->format('Y-m-d'),
                'Ship_TransitDate' => $transitDate->format('Y-m-d'),
                'Ship_DelivDate' => $deliveryDate->format('Y-m-d')
            ];
        }
    }
}

// Sort by date descending
usort($orders, function($a, $b) {
    return strtotime($b['Order_Date']) - strtotime($a['Order_Date']);
});

// Fetch feedbacks left by this user (dynamic fetch from Firebase)
$feedbackMap = [];
$allFeedbacks = getData('feedbacks') ?: [];
foreach ($allFeedbacks as $fId => $fData) {
    if (($fData['userId'] ?? '') === $uid) {
        $feedbackMap[$fData['orderId']] = true;
    }
}

// Fetch order items grouped by order_id
$orderItems = [];
if (!empty($orders)) {
    foreach ($orders as $ord) {
        $orderId = $ord['Order_ID'];
        $orderItemsData = getData("orders/{$orderId}/items");
        if ($orderItemsData) {
            foreach ($orderItemsData as $itemId => $itemData) {
                $productId = $itemData['productId'] ?? '';
                $product = getData("products/{$productId}");
                if ($product) {
                    $orderItems[$orderId][] = [
                        'Item_OrderID' => $orderId,
                        'Item_Qty' => $itemData['quantity'] ?? 1,
                        'Item_Price' => $itemData['price'] ?? 0,
                        'Item_Sub' => $itemData['subtotal'] ?? 0,
                        'Prod_ID' => $productId,
                        'Prod_Title' => $product['title'] ?? '',
                        'Prod_Image' => $product['image'] ?? ''
                    ];
                }
            }
        }
    }
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

    <?php if (!empty($pendingAuctions)): ?>
        <div style="background:#fff3cd; border:1px solid #ffeeba; border-radius:12px; padding:20px; margin-bottom:24px;">
            <h2 style="font-size:16px; font-weight:700; color:#856404; margin-bottom:12px; display:flex; align-items:center; gap:8px;"><i class="bi bi-trophy-fill" style="color:#f5af02; font-size: 1.2rem;"></i> Auctions Won — Pending Checkout</h2>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($pendingAuctions as $auc): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:16px; border:1px solid #ffeeba; border-radius:8px; gap:16px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:16px;">
                            <?php if (!empty($auc['image'])): ?>
                                <img src="<?= htmlspecialchars($auc['image']) ?>" alt="Item Image" style="width:60px; height:60px; object-fit:cover; border-radius:8px; border:1px solid var(--border); background:#fafafa;">
                            <?php else: ?>
                                <div style="width:60px; height:60px; border-radius:8px; background:#f0f0f0; display:flex; align-items:center; justify-content:center; border:1px solid var(--border); color:var(--muted); font-size:24px;">📦</div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight:600; font-size:14px; color:var(--text);"><?= htmlspecialchars($auc['title']) ?></div>
                                <div style="font-size:12px; color:var(--muted); margin-top:2px;">Winning Bid: <strong style="color:var(--text)">₱<?= number_format($auc['price'], 2) ?></strong></div>
                            </div>
                        </div>
                        <div>
                            <a href="../checkout/checkout.php?auction_id=<?= urlencode($auc['aucId']) ?>" style="background:var(--blue); color:#fff; padding:8px 20px; border-radius:20px; font-size:13px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:6px; transition:background 0.2s; font-family:var(--font);" onmouseover="this.style.background='#2b55d9'" onmouseout="this.style.background='var(--blue)'">
                                <i class="bi bi-cart-check-fill"></i> Checkout
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

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
