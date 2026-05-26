<?php
require_once "includes/auth.php";
requireAdminLogin();
require_once "../config/firebase.php";

$message = '';
$messageType = '';

// Update order status
if (isset($_POST['update_status'])) {
    $orderId = $_POST['order_id'];
    $payStat = $_POST['order_paystat'];
    $shipStat = $_POST['ship_status'] ?? '';
    
    updateData("orders/{$orderId}/paymentStatus", $payStat);
    
    if ($shipStat) {
        // In Firebase, shipment status is stored in the order
        updateData("orders/{$orderId}/status", $shipStat);
    }
    
    $message = "Order status updated!";
    $messageType = "success";
}

// Delete order permanently
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $oid = $_GET['id'];
    deleteData("orders/{$oid}");
    $message = "Order deleted successfully from database!";
    $messageType = "success";
}

// Get orders with user info
$statusFilter = $_GET['status'] ?? '';
$shipFilter  = $_GET['ship_status'] ?? '';
$orders = [];
$ordersData = getData('orders');

if ($ordersData) {
    foreach ($ordersData as $orderId => $orderData) {
        // Apply payment filter (case-insensitive)
        if ($statusFilter && strtolower($orderData['paymentStatus'] ?? '') !== strtolower($statusFilter)) {
            continue;
        }
        // Apply ship status filter
        $rawStatus = $orderData['status'] ?? 'Pending';
        if ($shipFilter && strtolower($rawStatus) !== strtolower($shipFilter)) {
            continue;
        }
        
        // Get user info
        $userId = $orderData['userId'] ?? '';
        $userData = getData("users/{$userId}");
        $profile = $userData['profile'] ?? [];
        
        // Calculate total from items
        $orderItems = getData("orders/{$orderId}/items");
        $total = 0;
        if ($orderItems) {
            foreach ($orderItems as $itemData) {
                $total += ($itemData['subtotal'] ?? 0);
            }
        }
        
        // Map order status
        $status = $orderData['status'] ?? 'Pending';
        $shipStatus = $status;
        if ($status === 'Shipped') {
            $shipStatus = 'In Transit';
        }
        // Only show shipment info for accepted orders
        $hasShipment = !in_array($status, ['Pending', 'Rejected']);
        
        $orders[] = [
            'Order_ID'      => $orderId,
            'User_FName'    => $profile['firstName'] ?? '',
            'User_LName'    => $profile['lastName'] ?? '',
            'User_Email'    => $profile['email'] ?? '',
            'Order_Total'   => $total,
            'Order_PayStat' => $orderData['paymentStatus'] ?? 'Paid',
            'Order_Status'  => $status,
            'Ship_Status'   => $shipStatus,
            'Ship_ID'       => $hasShipment ? $orderId : null,
            'Order_Date'    => $orderData['date'] ?? date('Y-m-d')
        ];
    }
}

// Sort by date descending
usort($orders, function($a, $b) {
    return strtotime($b['Order_Date']) - strtotime($a['Order_Date']);
});

$title = "Order Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?> - eBay Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; font-family: 'Inter', sans-serif; }
        .sidebar { width: 260px; background: #1a1f2e; min-height: 100vh; position: fixed; left: 0; top: 0; }
        .main-content { margin-left: 260px; padding: 20px 30px; }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .brand { font-size: 28px; font-weight: 800; }
        .sidebar-brand .brand span:nth-child(1) { color: #3665F3; }
        .sidebar-brand .brand span:nth-child(2) { color: #E53238; }
        .sidebar-brand .brand span:nth-child(3) { color: #F5AF02; }
        .sidebar-brand .brand span:nth-child(4) { color: #86B817; }
        .sidebar-brand small { color: #6c757d; font-size: 11px; }
        .nav-link-custom {
            color: #a0a5b5;
            padding: 12px 20px;
            margin: 4px 12px;
            border-radius: 10px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .nav-link-custom { color: #a0a5b5; padding: 12px 20px; margin: 4px 12px; border-radius: 10px; display: block; text-decoration: none; }
        .nav-link-custom:hover, .nav-link-custom.active { background: #2a2f3f; color: white; }
        .nav-link-custom i { width: 24px; margin-right: 10px; }
        .top-nav { background: white; border-radius: 16px; padding: 12px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .table-card { background: white; border-radius: 16px; padding: 20px; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-shipped { background: #cce5ff; color: #004085; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand">
                <span>e</span><span>b</span><span>a</span><span>y</span>
            </div>
            <small class="d-block mt-1">Admin Control Panel</small>
        </div>
        <div class="mt-3">
            <a href="dashboard.php" class="nav-link-custom d-block">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="users.php" class="nav-link-custom d-block">
                <i class="bi bi-people"></i> User Management
            </a>
            <a href="add_admin.php" class="nav-link-custom d-block">
                <i class="bi bi-person-badge"></i> Add Admin
            </a>
            <a href="sellers.php" class="nav-link-custom d-block">
                <i class="bi bi-building"></i> Sellers & Verification
            </a>
            <a href="products.php" class="nav-link-custom d-block">
                <i class="bi bi-box"></i> Products
            </a>
            <a href="categories.php" class="nav-link-custom d-block">
                <i class="bi bi-tags"></i> Categories
            </a>

            <a href="orders.php" class="nav-link-custom d-block active">
                <i class="bi bi-receipt"></i> Orders
            </a>
            <hr class="mx-3 my-3" style="border-color:rgba(255,255,255,0.1)">
            <a href="../auth/logout.php" class="nav-link-custom d-block">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-nav">
            <div>
                <h4 class="mb-0 fw-bold"><?= $title ?></h4>
                <small class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Admin') ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary rounded-pill px-3 py-2">
                    <i class="bi bi-shield-lock-fill me-1"></i> Level <?= $_SESSION['admin_level'] ?? 1 ?> Admin
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3" style="text-decoration: none;">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="table-card mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Payment Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Orders</option>
                        <option value="Paid"      <?= $statusFilter === 'Paid'      ? 'selected' : '' ?>>Paid</option>
                        <option value="Pending"   <?= $statusFilter === 'Pending'   ? 'selected' : '' ?>>Pending Payment</option>
                        <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Order Status</label>
                    <select name="ship_status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="Pending"    <?= $shipFilter === 'Pending'    ? 'selected' : '' ?>>Pending Acceptance</option>
                        <option value="Processing" <?= $shipFilter === 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Shipped"    <?= $shipFilter === 'Shipped'    ? 'selected' : '' ?>>Shipped / In Transit</option>
                        <option value="Delivered"  <?= $shipFilter === 'Delivered'  ? 'selected' : '' ?>>Delivered</option>
                        <option value="Rejected"   <?= $shipFilter === 'Rejected'   ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="orders.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Order ID</th><th>Customer</th><th>Total Amount</th><th>Payment</th><th>Order Status</th><th>Shipping</th><th>Order Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $orderStatusBadge = match($order['Order_Status'] ?? 'Pending') {
                                    'Pending'    => ['bg-warning text-dark', 'bi-hourglass-split', 'Pending'],
                                    'Processing' => ['bg-info text-dark',    'bi-gear-fill',        'Processing'],
                                    'Shipped'    => ['bg-primary',            'bi-truck',            'Shipped'],
                                    'Delivered'  => ['bg-success',            'bi-house-check-fill', 'Delivered'],
                                    'Rejected'   => ['bg-danger',             'bi-x-circle-fill',    'Rejected'],
                                    default      => ['bg-secondary',          'bi-question',         $order['Order_Status'] ?? ''],
                                };
                                ?>
                                <tr>
                                    <td><code>#<?= $order['Order_ID'] ?></code></td>
                                    <td>
                                        <?= htmlspecialchars($order['User_FName'] . ' ' . $order['User_LName']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($order['User_Email']) ?></small>
                                    </td>
                                    <td><strong>₱<?= number_format($order['Order_Total'] ?? 0, 2) ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($order['Order_PayStat'] ?? 'paid') ?>">
                                            <?= ucfirst($order['Order_PayStat'] ?? 'Paid') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $orderStatusBadge[0] ?>">
                                            <i class="bi <?= $orderStatusBadge[1] ?> me-1"></i><?= $orderStatusBadge[2] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['Ship_ID']): ?>
                                            <span class="badge bg-info text-dark"><?= ucfirst($order['Ship_Status'] ?? 'Processing') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= date('M d, Y', strtotime($order['Order_Date'])) ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statusModal<?= $order['Order_ID'] ?>">
                                                <i class="bi bi-pencil"></i> Update
                                            </button>
                                            <a href="?delete=1&id=<?= $order['Order_ID'] ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this order permanently from database?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Update Status Modal -->
                                <div class="modal fade" id="statusModal<?= $order['Order_ID'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Order Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="order_id" value="<?= $order['Order_ID'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Payment Status</label>
                                                        <select name="order_paystat" class="form-select">
                                                            <option value="Pending" <?= ($order['Order_PayStat'] ?? '') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                            <option value="Paid" <?= ($order['Order_PayStat'] ?? '') === 'Paid' ? 'selected' : '' ?>>Paid</option>
                                                            <option value="Cancelled" <?= ($order['Order_PayStat'] ?? '') === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                            <label class="form-label">Order / Shipment Status</label>
                                                            <select name="ship_status" class="form-select">
                                                                <option value="Pending"    <?= ($order['Order_Status'] ?? '') === 'Pending'    ? 'selected' : '' ?>>Pending (Awaiting Seller)</option>
                                                                <option value="Processing" <?= ($order['Order_Status'] ?? '') === 'Processing' ? 'selected' : '' ?>>Processing (Accepted)</option>
                                                                <option value="Shipped"    <?= ($order['Order_Status'] ?? '') === 'Shipped'    ? 'selected' : '' ?>>Shipped / In Transit</option>
                                                                <option value="Delivered"  <?= ($order['Order_Status'] ?? '') === 'Delivered'  ? 'selected' : '' ?>>Delivered</option>
                                                                <option value="Rejected"   <?= ($order['Order_Status'] ?? '') === 'Rejected'   ? 'selected' : '' ?>>Rejected</option>
                                                            </select>
                                                        </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>