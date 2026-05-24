<?php
require_once "includes/auth.php";
requireAdminLogin();
require_once "../config/db.php";

$message = '';
$messageType = '';

// Delete product
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $pid = $conn->real_escape_string($_GET['id']);
    if ($conn->query("DELETE FROM Product WHERE Prod_ID = '$pid'")) {
        $message = "Product deleted successfully!";
        $messageType = "success";
    }
}

// Toggle featured/status
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $pid = $conn->real_escape_string($_GET['id']);
    $current = $_GET['current'];
    $new = $current === 'active' ? 'inactive' : 'active';
    $conn->query("UPDATE Product SET Prod_Status = '$new' WHERE Prod_ID = '$pid'");
    $message = "Product status updated.";
    $messageType = "info";
}

// Search/filter
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = [];
if ($search) {
    $searchEscaped = $conn->real_escape_string($search);
    $where[] = "(Prod_Title LIKE '%$searchEscaped%' OR Prod_Desc LIKE '%$searchEscaped%')";
}
if ($statusFilter) {
    $where[] = "Prod_Status = '" . $conn->real_escape_string($statusFilter) . "'";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$query = "SELECT p.*, u.User_FName, u.User_LName 
          FROM Product p
          LEFT JOIN User u ON p.Prod_SellID = u.User_ID
          $whereClause 
          ORDER BY p.Prod_DateAdd DESC";
$products = $conn->query($query);

$title = "Product Management";
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
        .status-active { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-inactive { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; background: #f0f0f0; }
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
            <a href="products.php" class="nav-link-custom d-block active">
                <i class="bi bi-box"></i> Products
            </a>
            <a href="categories.php" class="nav-link-custom d-block">
                <i class="bi bi-tags"></i> Categories
            </a>

            <a href="orders.php" class="nav-link-custom d-block">
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
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Search Products</label>
                    <input type="text" name="search" class="form-control" placeholder="Title or description..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Image</th><th>Title</th><th>Seller</th><th>Price</th><th>Status</th><th>Posted</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($products && $products->num_rows > 0): ?>
                            <?php while ($product = $products->fetch_assoc()): ?>
                                <tr>
                                    <td><img src="<?= $product['Prod_Image'] ?? 'https://via.placeholder.com/50' ?>" class="product-img" onerror="this.src='https://via.placeholder.com/50'"></td>
                                    <td><strong><?= htmlspecialchars(substr($product['Prod_Title'], 0, 40)) ?></strong><?= strlen($product['Prod_Title']) > 40 ? '...' : '' ?></td>
                                    <td><small><?= htmlspecialchars($product['User_FName'] ?? 'Unknown') ?></small></td>
                                    <td>₱<?= number_format($product['Prod_Price'] ?? 0, 2) ?></td>
                                    <td><span class="status-<?= $product['Prod_Status'] ?? 'inactive' ?>"><?= ucfirst($product['Prod_Status'] ?? 'Inactive') ?></span></td>
                                    <td><small><?= date('M d, Y', strtotime($product['Prod_DateAdd'])) ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?toggle_status=1&id=<?= $product['Prod_ID'] ?>&current=<?= $product['Prod_Status'] ?>" 
                                               class="btn btn-outline-warning">
                                                <i class="bi bi-<?= $product['Prod_Status'] === 'active' ? 'eye-slash' : 'eye' ?>"></i>
                                            </a>
                                            <a href="?delete=1&id=<?= $product['Prod_ID'] ?>" 
                                               class="btn btn-outline-danger" onclick="return confirm('Delete this product?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No products found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>