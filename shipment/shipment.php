<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php');
    exit;
}

require_once('../config/firebase.php');
$uid = $_SESSION['firebase_uid'];
$orderId = $_GET['order_id'] ?? '';

if (empty($orderId)) {
    $_SESSION['flash'] = "Order ID is missing.";
    header("Location: ../buyer/dashboard.php");
    exit;
}

// Fetch Order details
$order = getData("orders/{$orderId}");

if (!$order || ($order['userId'] ?? '') !== $uid) {
    $_SESSION['flash'] = "Order not found or access denied.";
    header("Location: ../buyer/dashboard.php");
    exit;
}

// Fetch Order Items
$items = [];
$orderItems = getData("orders/{$orderId}/items");
if ($orderItems) {
    foreach ($orderItems as $itemId => $itemData) {
        $productId = $itemData['productId'] ?? '';
        $product = getData("products/{$productId}");
        if ($product) {
            $items[] = [
                'Item_Qty' => $itemData['quantity'] ?? 1,
                'Item_Price' => $itemData['price'] ?? 0,
                'Prod_Title' => $product['title'] ?? '',
                'Prod_Image' => $product['image'] ?? ''
            ];
        }
    }
}

// For shipment tracking, we'll use order status as a proxy
// In a real implementation, you'd have a separate shipments collection
$today = date('Y-m-d');
$orderDate = $order['date'] ?? $today;
$status = $order['status'] ?? 'Processing';

// Calculate estimated delivery dates (simplified)
$orderDateObj = new DateTime($orderDate);
$pickupDate = clone $orderDateObj;
$pickupDate->modify('+2 days');
$transitDate = clone $pickupDate;
$transitDate->modify('+3 days');
$deliveryDate = clone $transitDate;
$deliveryDate->modify('+2 days');

// Map order status to shipment status
$shipmentStatus = 'Processing';
if ($status === 'Processing') {
    $shipmentStatus = 'Processing';
} elseif ($status === 'Shipped') {
    $shipmentStatus = 'In Transit';
} elseif ($status === 'Delivered') {
    $shipmentStatus = 'Delivered';
}

// Build details array for compatibility
$details = [
    'Order_Date' => $orderDate,
    'Order_Total' => $order['total'] ?? 0,
    'Order_ShipAdd' => $order['shippingAddress'] ?? '',
    'Ship_Status' => $shipmentStatus,
    'Ship_PickupDate' => $pickupDate->format('Y-m-d'),
    'Ship_TransitDate' => $transitDate->format('Y-m-d'),
    'Ship_DelivDate' => $deliveryDate->format('Y-m-d'),
    'Ship_Location' => $shipmentStatus === 'Delivered' ? 'Delivered' : 'Philippines'
];

$title = "Track Shipment";
$basePath = '../';
include('../layout/layout.php');

// Determine timeline progress
$steps = ['Processing', 'Picked Up', 'In Transit', 'Delivered'];
$currentStatus = $details['Ship_Status'];
$progressIndex = array_search($currentStatus, $steps);
if ($progressIndex === false) $progressIndex = 0;
?>

<style>
.track-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}
.track-header {
    margin-bottom: 24px;
}
.track-header h1 {
    font-size: 28px;
    font-weight: 700;
}
.track-header p {
    color: var(--muted);
    font-size: 15px;
}

.track-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 24px;
}
.est-delivery {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 32px;
    color: #2e7d32;
}

/* Timeline */
.timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-bottom: 40px;
}
.timeline::before {
    content: '';
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--border);
    z-index: 1;
}
.timeline-fill {
    position: absolute;
    top: 15px;
    left: 0;
    height: 4px;
    background: var(--blue);
    z-index: 2;
    transition: width 0.5s ease;
}

.step {
    position: relative;
    z-index: 3;
    text-align: center;
    width: 80px;
}
.step-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--border);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-size: 16px;
    transition: background 0.3s;
}
.step.active .step-circle {
    background: var(--blue);
}
.step.completed .step-circle {
    background: var(--blue);
}
.step-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
}
.step.active .step-label,
.step.completed .step-label {
    color: var(--text);
}
.step-date {
    font-size: 11px;
    color: var(--muted);
    margin-top: 4px;
}

.shipment-info {
    background: #f7f7f7;
    padding: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.shipment-icon {
    font-size: 32px;
    color: var(--blue);
}
.shipment-details {
    flex: 1;
}
.shipment-name {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 4px;
}
.shipment-meta {
    font-size: 14px;
    color: var(--muted);
}

.items-section h3 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}
.item-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}
.item-img {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    border: 1px solid var(--border);
    object-fit: cover;
}
.item-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fafafa;
    color: var(--muted);
    font-size: 32px;
}
.item-info {
    flex: 1;
}
.item-title {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
}
.item-qty {
    font-size: 13px;
    color: var(--muted);
}
</style>

<div class="track-container">
    <div class="track-header">
        <h1>Track your package</h1>
        <p>Order #<?= htmlspecialchars($orderId) ?> • Placed on <?= date('M j, Y', strtotime($details['Order_Date'])) ?></p>
    </div>
    
    <div class="track-card">
        <?php if ($status === 'Pending'): ?>
            <div style="text-align:center; padding: 24px 0;">
                <i class="bi bi-hourglass-split" style="font-size:4rem; color:var(--muted); display:block; margin-bottom:16px;"></i>
                <div class="est-delivery" style="color:var(--text); margin-bottom:10px;">Awaiting Seller Acceptance</div>
                <p style="color:var(--muted); font-size:14px; max-width: 500px; margin: 0 auto;">Shipment tracking and estimated delivery will begin as soon as the seller accepts your order.</p>
            </div>
        <?php elseif ($status === 'Rejected'): ?>
            <div style="text-align:center; padding: 24px 0;">
                <i class="bi bi-x-circle" style="font-size:4rem; color:#e53238; display:block; margin-bottom:16px;"></i>
                <div class="est-delivery" style="color:#e53238; margin-bottom:10px;">Order Rejected</div>
                <p style="color:var(--muted); font-size:14px; max-width: 500px; margin: 0 auto;">This order has been rejected by the seller.</p>
            </div>
        <?php else: ?>
            <div class="est-delivery">
                <?php if ($currentStatus === 'Delivered'): ?>
                    Delivered on <?= date('D, M j', strtotime($details['Ship_DelivDate'])) ?>
                <?php else: ?>
                    Estimated Delivery: <?= date('D, M j', strtotime($details['Ship_DelivDate'])) ?>
                <?php endif; ?>
            </div>
            
            <?php
                $width = ($progressIndex / (count($steps) - 1)) * 100;
            ?>
            <div class="timeline">
                <div class="timeline-fill" style="width: <?= $width ?>%;"></div>
                
                <?php foreach ($steps as $i => $step): 
                    $class = '';
                    $icon = '';
                    if ($i < $progressIndex) {
                        $class = 'completed';
                        $icon = '<i class="bi bi-check2"></i>';
                    } elseif ($i === $progressIndex) {
                        $class = 'active';
                        $icon = '<i class="bi bi-circle-fill" style="font-size:8px;"></i>';
                    } else {
                        $icon = '<i class="bi bi-circle-fill" style="font-size:8px;opacity:0;"></i>';
                    }
                    
                    $stepDate = '';
                    if ($step === 'Processing') $stepDate = $details['Order_Date'];
                    if ($step === 'Picked Up') $stepDate = $details['Ship_PickupDate'];
                    if ($step === 'In Transit') $stepDate = $details['Ship_TransitDate'];
                    if ($step === 'Delivered') $stepDate = $details['Ship_DelivDate'];
                ?>
                    <div class="step <?= $class ?>">
                        <div class="step-circle"><?= $icon ?></div>
                        <div class="step-label"><?= $step ?></div>
                        <div class="step-date"><?= date('M j', strtotime($stepDate)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="shipment-info">
                <div class="shipment-icon"><i class="bi bi-truck"></i></div>
                <div class="shipment-details">
                    <div class="shipment-name">Standard Delivery</div>
                    <div class="shipment-meta">
                        Current Location: <?= htmlspecialchars($details['Ship_Location'] ?? 'Warehouse') ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="track-card items-section">
        <h3>Items in this shipment</h3>
        <?php foreach ($items as $it): ?>
            <div class="item-row">
                <?php if (!empty($it['Prod_Image'])): ?>
                    <img src="<?= htmlspecialchars($it['Prod_Image']) ?>" class="item-img" alt="Product">
                <?php else: ?>
                    <div class="item-placeholder"><i class="bi bi-box"></i></div>
                <?php endif; ?>
                <div class="item-info">
                    <div class="item-title"><?= htmlspecialchars($it['Prod_Title']) ?></div>
                    <div class="item-qty">Qty: <?= $it['Item_Qty'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include('../layout/footer.php'); ?>
