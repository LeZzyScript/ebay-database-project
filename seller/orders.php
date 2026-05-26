<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || empty($_SESSION['is_seller'])) {
    header('Location: ../buyer/dashboard.php');
    exit;
}

require_once('../config/firebase.php');

$uid = $_SESSION['firebase_uid'];

// Process Accept/Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $action = $_POST['action'];
    $oid = $_POST['order_id'];
    
    // Verify the order belongs to this seller by checking if they have items in it
    $order = getData("orders/{$oid}");
    $valid = false;
    if ($order) {
        $orderItems = getData("orders/{$oid}/items");
        if ($orderItems) {
            foreach ($orderItems as $itemId => $itemData) {
                $productId = $itemData['productId'] ?? '';
                $product = getData("products/{$productId}");
                if ($product && ($product['sellerId'] ?? '') === $uid) {
                    $valid = true;
                    break;
                }
            }
        }
    }
    
    if ($valid) {
        if ($action === 'accept') {
            updateData("orders/{$oid}/status", 'Processing');
            $_SESSION['flash'] = "Order accepted.";
        } elseif ($action === 'reject') {
            updateData("orders/{$oid}/status", 'Rejected');
            $_SESSION['flash'] = "Order rejected.";
        }
    }
    header("Location: orders.php");
    exit;
}

// Fetch all orders that contain this seller's products
$orders = [];
$ordersData = getData('orders');
if ($ordersData) {
    foreach ($ordersData as $orderId => $orderData) {
        $status = $orderData['status'] ?? 'Processing';
        if ($status === 'Rejected') {
            continue;
        }
        $orderItems = getData("orders/{$orderId}/items");
        if ($orderItems) {
            $hasSellerItem = false;
            $sellerSubtotal = 0;
            $sellerTotalQty = 0;
            
            foreach ($orderItems as $itemId => $itemData) {
                $productId = $itemData['productId'] ?? '';
                $product = getData("products/{$productId}");
                if ($product && ($product['sellerId'] ?? '') === $uid) {
                    $hasSellerItem = true;
                    $sellerSubtotal += ($itemData['subtotal'] ?? 0);
                    $sellerTotalQty += ($itemData['quantity'] ?? 0);
                }
            }
            
            if ($hasSellerItem) {
                // Get buyer info
                $buyerUid = $orderData['userId'] ?? '';
                $buyerUser = getData("users/{$buyerUid}");
                $buyerProfile = $buyerUser['profile'] ?? [];
                
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
                    'Order_PayStat' => $orderData['paymentStatus'] ?? 'Paid',
                    'Order_ShipAdd' => $orderData['shippingAddress'] ?? '',
                    'Order_ReqStatus' => ($status === 'Pending') ? 'Pending' : (($status === 'Rejected') ? 'Rejected' : 'Approved'),
                    'User_FName' => $buyerProfile['firstName'] ?? '',
                    'User_LName' => $buyerProfile['lastName'] ?? '',
                    'User_AccName' => $buyerProfile['accountName'] ?? '',
                    'User_Contact' => $buyerProfile['contact'] ?? '',
                    'Ship_ID' => ($status !== 'Pending' && $status !== 'Rejected') ? $orderId : null,
                    'Ship_Status' => $shipStatus,
                    'Ship_PickupDate' => $pickupDate->format('Y-m-d'),
                    'Ship_TransitDate' => $transitDate->format('Y-m-d'),
                    'Ship_DelivDate' => $deliveryDate->format('Y-m-d'),
                    'Ship_Location' => $shipStatus === 'Delivered' ? 'Delivered' : 'Philippines',
                    'Seller_Subtotal' => $sellerSubtotal,
                    'Seller_TotalQty' => $sellerTotalQty
                ];
            }
        }
    }
}

// Sort by date descending
usort($orders, function($a, $b) {
    return strtotime($b['Order_Date']) - strtotime($a['Order_Date']);
});

// Fetch items per order (only this seller's products)
$orderItems = [];
if (!empty($orders)) {
    foreach ($orders as $ord) {
        $orderId = $ord['Order_ID'];
        $itemsData = getData("orders/{$orderId}/items");
        if ($itemsData) {
            foreach ($itemsData as $itemId => $itemData) {
                $productId = $itemData['productId'] ?? '';
                $product = getData("products/{$productId}");
                if ($product && ($product['sellerId'] ?? '') === $uid) {
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

$title    = 'Sales Orders';
$basePath = '../';
include('../layout/layout.php');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.so-wrap {
    max-width: 1040px;
    margin: 28px auto;
    padding: 0 20px 60px;
}
.so-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 20px;
    text-decoration: none;
}
.so-back:hover { color: var(--text); text-decoration: none; }
.so-back svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.so-title { font-size: 24px; font-weight: 700; margin-bottom: 4px; }
.so-count { font-size: 14px; color: var(--muted); margin-bottom: 24px; }

/* Filter bar */
.filter-bar { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.filter-btn {
    padding: 7px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--muted);
    cursor: pointer;
    transition: all .15s;
    font-family: var(--font);
}
.filter-btn:hover  { border-color: var(--text); color: var(--text); }
.filter-btn.active { background: var(--text); color: #fff; border-color: var(--text); }

/* Empty state */
.so-empty {
    text-align: center;
    padding: 64px 20px;
    background: #f7f7f7;
    border-radius: 12px;
}
.so-empty i  { font-size: 56px; color: var(--muted); display: block; margin-bottom: 16px; }
.so-empty h3 { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
.so-empty p  { color: var(--muted); font-size: 14px; }

/* Order card */
.order-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 20px; overflow: hidden; }

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
    transition: background .15s;
}
.order-header:hover { background: #efefef; }
.order-meta { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; flex: 1; min-width: 0; }
.order-id   { font-size: 14px; font-weight: 700; }
.order-date { font-size: 13px; color: var(--muted); }
.order-revenue { font-size: 15px; font-weight: 700; white-space: nowrap; }
.order-buyer { font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 4px; }

/* Badges */
.status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 20px;
    font-size: 12px; font-weight: 600; white-space: nowrap;
}
.badge-paid       { background:#d4edda; color:#155724; }
.badge-pending    { background:#fff3cd; color:#856404; }
.badge-cancelled  { background:#f8d7da; color:#721c24; }
.badge-delivered  { background:#d1ecf1; color:#0c5460; }
.badge-transit    { background:#fff8e1; color:#7d6608; }
.badge-pickup     { background:#cce5ff; color:#004085; }
.badge-processing { background:#e8f4fd; color:#1a5276; }
.badge-unshipped  { background:#e9ecef; color:#495057; }

.chevron { font-size: 18px; color: var(--muted); transition: transform .2s; }
.chevron.open { transform: rotate(180deg); }

/* Expandable body */
.order-body { display: none; }
.order-body.open { display: block; }

/* Buyer info strip */
.buyer-strip {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px 20px;
    background: #fafeff;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
}
.buyer-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--blue); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px; flex-shrink: 0;
    font-family: var(--font);
}
.buyer-name  { font-weight: 700; font-size: 14px; margin-bottom: 2px; }
.buyer-meta  { color: var(--muted); line-height: 1.5; }

/* Items */
.order-items { padding: 0 20px; }
.order-item {
    display: flex; gap: 16px;
    padding: 18px 0;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
}
.order-item:last-child { border-bottom: none; }
.item-img-wrap {
    width: 88px; height: 88px; flex-shrink: 0;
    border: 1px solid var(--border); border-radius: 8px;
    overflow: hidden; background: #fafafa;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none;
}
.item-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.item-img-wrap i   { font-size: 2.2rem; color: var(--muted); }
.item-info { flex: 1; min-width: 0; }
.item-title {
    font-size: 14px; font-weight: 600; color: var(--blue);
    text-decoration: none; display: block;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-bottom: 4px;
}
.item-title:hover { text-decoration: underline; }
.item-price { font-size: 16px; font-weight: 700; margin-top: 6px; }
.item-qty   { font-size: 13px; color: var(--muted); margin-top: 2px; }
.item-sub   { font-size: 14px; font-weight: 700; white-space: nowrap; padding-top: 4px; flex-shrink: 0; }

/* Shipment timeline */
.ship-section {
    padding: 20px;
    border-top: 1px solid var(--border);
    background: #fafafa;
}
.ship-section-title {
    font-size: 14px; font-weight: 700;
    margin-bottom: 16px;
    display: flex; align-items: center; gap: 6px;
}
.ship-section-title i { color: var(--blue); }

/* Timeline */
.timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-bottom: 16px;
}
.timeline::before {
    content: '';
    position: absolute;
    top: 15px; left: 0; right: 0;
    height: 4px; background: var(--border); z-index: 1;
}
.tl-fill {
    position: absolute;
    top: 15px; left: 0;
    height: 4px; background: var(--blue); z-index: 2;
    transition: width .4s ease;
}
.step { position: relative; z-index: 3; text-align: center; width: 80px; }
.step-circle {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--border); color: #fff;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 6px; font-size: 14px;
    transition: background .3s;
}
.step.done   .step-circle,
.step.active .step-circle { background: var(--blue); }
.step-label { font-size: 12px; font-weight: 600; color: var(--muted); }
.step.done .step-label, .step.active .step-label { color: var(--text); }
.step-date  { font-size: 11px; color: var(--muted); margin-top: 3px; }

.ship-meta {
    display: flex; align-items: center; gap: 10px;
    background: #fff; border: 1px solid var(--border);
    border-radius: 8px; padding: 12px 16px;
    font-size: 13px; margin-top: 8px;
}
.ship-meta i { color: var(--blue); font-size: 18px; }
.no-ship-note {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--muted);
    padding: 12px 0;
}

/* Order footer */
.order-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--border);
    background: #fafafa;
    display: flex; justify-content: flex-end; align-items: center;
    gap: 12px; flex-wrap: wrap;
    font-size: 14px;
}
.order-footer-total { font-weight: 700; font-size: 15px; }

@media (max-width: 640px) {
    .order-meta  { gap: 8px; }
    .item-img-wrap { width: 68px; height: 68px; }
    .step { width: 60px; }
    .step-label { font-size: 10px; }
}
</style>

<div class="so-wrap">

    <a href="dashboard.php" class="so-back">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Seller Hub
    </a>

    <h1 class="so-title">Sales Orders</h1>
    <div class="so-count"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> from your listings</div>

    <?php if (empty($orders)): ?>
        <div class="so-empty">
            <i class="bi bi-bag-x"></i>
            <h3>No orders yet</h3>
            <p>When buyers purchase your products, their orders will appear here.</p>
        </div>

    <?php else: ?>

        <!-- Filter bar -->
        <div class="filter-bar">
            <button class="filter-btn active" onclick="filterOrders('all', this)">All orders</button>
            <button class="filter-btn" onclick="filterOrders('unshipped', this)">Unshipped</button>
            <button class="filter-btn" onclick="filterOrders('processing', this)">Processing</button>
            <button class="filter-btn" onclick="filterOrders('transit', this)">In Transit</button>
            <button class="filter-btn" onclick="filterOrders('delivered', this)">Delivered</button>
        </div>

        <div id="orderList">
        <?php foreach ($orders as $ord):
            $items    = $orderItems[$ord['Order_ID']] ?? [];
            $payStat  = strtolower($ord['Order_PayStat'] ?? 'pending');
            $shipStat = $ord['Ship_Status'] ?? '';

            // Payment badge
            $payBadge = match($payStat) {
                'paid'      => ['badge-paid',     'bi-check-circle-fill', 'Paid'],
                'cancelled' => ['badge-cancelled', 'bi-x-circle-fill',    'Cancelled'],
                default     => ['badge-pending',   'bi-clock-fill',       'Pending Payment'],
            };

            // Shipment badge + filter key
            if (!$ord['Ship_ID']) {
                $shipBadge  = ['badge-unshipped',  'bi-box-seam',                  'Unshipped'];
                $filterKey  = 'unshipped';
            } else {
                [$shipBadge, $filterKey] = match($shipStat) {
                    'Picked Up'  => [['badge-pickup',     'bi-truck',                     'Picked Up'],   'transit'],
                    'In Transit' => [['badge-transit',    'bi-arrow-right-circle-fill',   'In Transit'],  'transit'],
                    'Delivered'  => [['badge-delivered',  'bi-house-check-fill',          'Delivered'],   'delivered'],
                    default      => [['badge-processing', 'bi-gear-fill',                 'Processing'],  'processing'],
                };
            }

            // Buyer initials
            $buyerInitials = strtoupper(
                substr($ord['User_FName'], 0, 1) . substr($ord['User_LName'], 0, 1)
            );

            // Timeline
            $steps = ['Processing', 'Picked Up', 'In Transit', 'Delivered'];
            $progressIndex = $ord['Ship_ID'] ? (array_search($shipStat, $steps) ?: 0) : 0;
            $tlWidth = $ord['Ship_ID'] ? round(($progressIndex / (count($steps) - 1)) * 100) : 0;
        ?>
        <div class="order-card" data-filter="<?= $filterKey ?>">

            <!-- Header -->
            <div class="order-header" onclick="toggleOrder('<?= $ord['Order_ID'] ?>')">
                <div class="order-meta">
                    <span class="order-id">Order #<?= htmlspecialchars($ord['Order_ID']) ?></span>
                    <span class="order-date">
                        <i class="bi bi-calendar3" style="margin-right:3px;"></i>
                        <?= date('M j, Y', strtotime($ord['Order_Date'])) ?>
                    </span>
                    <span class="order-buyer">
                        <i class="bi bi-person"></i>
                        <?= htmlspecialchars($ord['User_AccName']) ?>
                    </span>
                    <span class="status-badge <?= $payBadge[0] ?>">
                        <i class="bi <?= $payBadge[1] ?>"></i><?= $payBadge[2] ?>
                    </span>
                    <span class="status-badge <?= $shipBadge[0] ?>">
                        <i class="bi <?= $shipBadge[1] ?>"></i><?= $shipBadge[2] ?>
                    </span>
                    <span class="order-revenue">₱<?= number_format($ord['Seller_Subtotal'], 2) ?></span>
                </div>
                <i class="bi bi-chevron-down chevron" id="chev-<?= $ord['Order_ID'] ?>"></i>
            </div>

            <!-- Expandable body -->
            <div class="order-body" id="body-<?= $ord['Order_ID'] ?>">

                <!-- Buyer info -->
                <div class="buyer-strip">
                    <div class="buyer-avatar"><?= $buyerInitials ?></div>
                    <div>
                        <div class="buyer-name"><?= htmlspecialchars($ord['User_FName'] . ' ' . $ord['User_LName']) ?></div>
                        <div class="buyer-meta">
                            <?= htmlspecialchars($ord['User_Contact']) ?><br>
                            <?= htmlspecialchars($ord['Order_ShipAdd']) ?>
                        </div>
                    </div>
                </div>

                <!-- Items (this seller's only) -->
                <div class="order-items">
                    <?php if (empty($items)): ?>
                        <div style="padding:16px 0;color:var(--muted);font-size:14px;">No item details available.</div>
                    <?php else: ?>
                        <?php foreach ($items as $it): ?>
                        <div class="order-item">
                            <a href="../product/product.php?id=<?= urlencode($it['Prod_ID']) ?>"
                               class="item-img-wrap" target="_blank">
                                <?php if (!empty($it['Prod_Image'])): ?>
                                    <img src="<?= htmlspecialchars($it['Prod_Image']) ?>" alt="">
                                <?php else: ?>
                                    <i class="bi bi-box"></i>
                                <?php endif; ?>
                            </a>
                            <div class="item-info">
                                <a href="../product/product.php?id=<?= urlencode($it['Prod_ID']) ?>"
                                   class="item-title" target="_blank">
                                    <?= htmlspecialchars($it['Prod_Title']) ?>
                                </a>
                                <div class="item-price">₱<?= number_format($it['Item_Price'], 2) ?></div>
                                <div class="item-qty">Qty: <?= (int)$it['Item_Qty'] ?></div>
                            </div>
                            <div class="item-sub">₱<?= number_format($it['Item_Sub'], 2) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Shipment tracking / Request Status -->
                <div class="ship-section">
                    <div class="ship-section-title">
                        <i class="bi bi-truck"></i> Shipment Status
                    </div>

                    <?php if ($ord['Order_ReqStatus'] === 'Pending'): ?>
                        <div style="background:#fffbea; border:1px solid #f0c040; border-radius:10px; padding:14px 16px; margin-bottom:4px;">
                            <div style="font-size:13px; font-weight:600; margin-bottom:10px; color:#856404;">
                                <i class="bi bi-hourglass-split"></i> This order is awaiting your response.
                            </div>
                            <div style="display:flex; gap:10px;">
                                <form method="POST" action="orders.php" style="margin:0;">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="order_id" value="<?= $ord['Order_ID'] ?>">
                                    <button type="submit" style="padding:9px 22px; border-radius:20px; font-size:13px; font-weight:700; background:#2e7d32; color:#fff; border:none; cursor:pointer; font-family:var(--font);">
                                        <i class="bi bi-check-circle-fill"></i> Accept Order
                                    </button>
                                </form>
                                <form method="POST" action="orders.php" style="margin:0;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="order_id" value="<?= $ord['Order_ID'] ?>">
                                    <button type="submit" style="padding:9px 22px; border-radius:20px; font-size:13px; font-weight:700; background:#c62828; color:#fff; border:none; cursor:pointer; font-family:var(--font);">
                                        <i class="bi bi-x-circle-fill"></i> Reject Order
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($ord['Order_ReqStatus'] === 'Rejected'): ?>
                        <div class="no-ship-note" style="color:var(--red);">
                            <i class="bi bi-x-circle"></i> You have rejected this order.
                        </div>
                    <?php else: ?>

                    <?php if ($ord['Ship_ID']): ?>
                        <div class="timeline">
                            <div class="tl-fill" style="width:<?= $tlWidth ?>%;"></div>
                            <?php foreach ($steps as $i => $step):
                                $cls  = $i < $progressIndex ? 'done' : ($i === $progressIndex ? 'active' : '');
                                $icon = $i <= $progressIndex
                                    ? ($i < $progressIndex ? '<i class="bi bi-check2"></i>' : '<i class="bi bi-circle-fill" style="font-size:8px;"></i>')
                                    : '';
                                $dateMap = [
                                    'Processing' => $ord['Order_Date'],
                                    'Picked Up'  => $ord['Ship_PickupDate'],
                                    'In Transit' => $ord['Ship_TransitDate'],
                                    'Delivered'  => $ord['Ship_DelivDate'],
                                ];
                                $d = $dateMap[$step] ?? null;
                            ?>
                                <div class="step <?= $cls ?>">
                                    <div class="step-circle"><?= $icon ?></div>
                                    <div class="step-label"><?= $step ?></div>
                                    <div class="step-date"><?= $d ? date('M j', strtotime($d)) : '—' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="ship-meta">
                            <i class="bi bi-geo-alt-fill"></i>
                            Current location: <strong><?= htmlspecialchars($ord['Ship_Location'] ?? 'Warehouse') ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="no-ship-note">
                            <i class="bi bi-info-circle" style="color:var(--blue);"></i>
                            No shipment assigned yet. The order has been received and is awaiting pickup.
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Order footer -->
                <div class="order-footer">
                    <span style="color:var(--muted);">Your revenue from this order:</span>
                    <span class="order-footer-total">₱<?= number_format($ord['Seller_Subtotal'], 2) ?></span>
                </div>

            </div><!-- /.order-body -->
        </div><!-- /.order-card -->
        <?php endforeach; ?>
        </div><!-- /#orderList -->

    <?php endif; ?>
</div>

<?php include('../layout/footer.php'); ?>

<script>
function toggleOrder(id) {
    const body = document.getElementById('body-' + id);
    const chev = document.getElementById('chev-' + id);
    if (!body) return;
    const open = body.classList.contains('open');
    body.classList.toggle('open', !open);
    chev.classList.toggle('open', !open);
}

function filterOrders(status, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.order-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.filter === status) ? '' : 'none';
    });
}
</script>
