<?php
require_once "includes/auth.php";
requireAdminLogin();
require_once "../config/db.php";

$message = '';
$messageType = '';

// Update order status
if (isset($_POST['update_status'])) {
    $orderId = $conn->real_escape_string($_POST['order_id']);
    $payStat = $conn->real_escape_string($_POST['order_paystat']);
    $shipStat = $conn->real_escape_string($_POST['ship_status']);
    
    $conn->query("UPDATE `Order` SET Order_PayStat = '$payStat' WHERE Order_ID = '$orderId'");
    $conn->query("UPDATE Shipment SET Ship_Status = '$shipStat' WHERE Ship_OrderID = '$orderId'");
    
    $message = "Order and Shipment status updated!";
    $messageType = "success";
}

// Get orders with user info
$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "WHERE o.Order_PayStat = '" . $conn->real_escape_string($statusFilter) . "'" : "";
$query = "SELECT o.*, u.User_FName, u.User_LName, u.User_Email, s.Ship_Status, s.Ship_ID 
          FROM `Order` o
          JOIN User u ON o.Order_UserID = u.User_ID
          LEFT JOIN Shipment s ON o.Order_ID = s.Ship_OrderID
          $where
          ORDER BY o.Order_Date DESC";
$orders = $conn->query($query);

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
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i> Filter</button>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Order ID</th><th>Customer</th><th>Total Amount</th><th>Payment</th><th>Shipping</th><th>Order Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($orders && $orders->num_rows > 0): ?>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td><code>#<?= $order['Order_ID'] ?></code></td>
                                    <td>
                                        <?= htmlspecialchars($order['User_FName'] . ' ' . $order['User_LName']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($order['User_Email']) ?></small>
                                    </td>
                                    <td><strong>₱<?= number_format($order['Order_Total'] ?? 0, 2) ?></strong></td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($order['Order_PayStat'] ?? 'pending') ?>">
                                            <?= ucfirst($order['Order_PayStat'] ?? 'Pending') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['Ship_ID']): ?>
                                            <span class="badge bg-info text-dark"><?= ucfirst($order['Ship_Status'] ?? 'Processing') ?></span><br>
                                            <small class="text-muted"><code><?= $order['Ship_ID'] ?></code></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Unshipped</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= date('M d, Y', strtotime($order['Order_Date'])) ?></small></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#statusModal<?= $order['Order_ID'] ?>">
                                            <i class="bi bi-pencil"></i> Update
                                        </button>
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
                                                    <?php if ($order['Ship_ID']): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label">Shipment Status (<code><?= $order['Ship_ID'] ?></code>)</label>
                                                            <select name="ship_status" class="form-select">
                                                                <option value="Processing" <?= ($order['Ship_Status'] ?? '') === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                                                <option value="Picked Up" <?= ($order['Ship_Status'] ?? '') === 'Picked Up' ? 'selected' : '' ?>>Picked Up</option>
                                                                <option value="In Transit" <?= ($order['Ship_Status'] ?? '') === 'In Transit' ? 'selected' : '' ?>>In Transit</option>
                                                                <option value="Delivered" <?= ($order['Ship_Status'] ?? '') === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                                            </select>
                                                        </div>
                                                    <?php else: ?>
                                                        <input type="hidden" name="ship_status" value="">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>