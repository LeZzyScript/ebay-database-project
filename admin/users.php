<?php
require_once "includes/auth.php";
requireAdminLogin();
require_once "../config/db.php";

// Handle actions
$message = '';
$messageType = '';

// Suspend/Activate user
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $uid = $conn->real_escape_string($_GET['id']);
    $action = $_GET['toggle'];
    $status = ($action === 'suspend') ? 'suspended' : 'active';
    
    if ($conn->query("UPDATE User SET User_Status = '$status' WHERE User_ID = '$uid'")) {
        $message = "User " . ($status === 'suspended' ? "suspended" : "activated") . " successfully!";
        $messageType = "success";
    } else {
        $message = "Failed to update user.";
        $messageType = "danger";
    }
}

// Delete user
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $uid = $conn->real_escape_string($_GET['id']);
    if ($conn->query("DELETE FROM User WHERE User_ID = '$uid'")) {
        $message = "User deleted successfully!";
        $messageType = "success";
    } else {
        $message = "Failed to delete user.";
        $messageType = "danger";
    }
}


// Search/filter
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$accTypeFilter = $_GET['acc_type'] ?? '';

$where = [];
if ($search) {
    $searchEscaped = $conn->real_escape_string($search);
    $where[] = "(User_FName LIKE '%$searchEscaped%' OR User_LName LIKE '%$searchEscaped%' OR User_Email LIKE '%$searchEscaped%' OR User_AccName LIKE '%$searchEscaped%')";
}
if ($statusFilter) {
    $where[] = "User_Status = '" . $conn->real_escape_string($statusFilter) . "'";
}
if ($accTypeFilter) {
    $where[] = "User_AccType = '" . $conn->real_escape_string($accTypeFilter) . "'";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$query = "SELECT * FROM User $whereClause ORDER BY User_DateReg DESC";
$users = $conn->query($query);

$title = "User Management";
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
        .status-active { background: #d4edda; color: #155724; }
        .status-suspended { background: #f8d7da; color: #721c24; }
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
            <a href="users.php" class="nav-link-custom d-block active">
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

            <a href="orders.php" class="nav-link-custom d-block">
                <i class="bi bi-receipt"></i> Orders
            </a>
            <hr class="mx-3 my-3" style="border-color:rgba(255,255,255,0.1)">
        </div>
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
                    <i class="bi bi-shield-lock-fill me-1"></i> Level <?= $_SESSION['admin_level'] ?> Admin
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
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, email, username..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Account Type</label>
                    <select name="acc_type" class="form-select">
                        <option value="">All</option>
                        <option value="individual" <?= $accTypeFilter === 'individual' ? 'selected' : '' ?>>Individual</option>
                        <option value="business" <?= $accTypeFilter === 'business' ? 'selected' : '' ?>>Business</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Account Type</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && $users->num_rows > 0): ?>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><code><?= $user['User_ID'] ?></code></td>
                                    <td><?= htmlspecialchars($user['User_FName'] . ' ' . $user['User_LName']) ?></td>
                                    <td><?= htmlspecialchars($user['User_AccName']) ?></td>
                                    <td><?= htmlspecialchars($user['User_Email']) ?></td>
                                    <td>
                                        <span class="badge <?= $user['User_AccType'] === 'seller' ? 'bg-warning' : 'bg-secondary' ?> bg-opacity-10 text-dark">
                                            <?= ucfirst($user['User_AccType']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $user['User_Status'] === 'active' ? 'status-active' : 'status-suspended' ?>">
                                            <?= ucfirst($user['User_Status']) ?>
                                        </span>
                                    </td>
                                    <td><small><?= date('M d, Y', strtotime($user['User_DateReg'])) ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">

                                            
                                            <?php if ($user['User_Status'] === 'active'): ?>
                                                <a href="?toggle=suspend&id=<?= $user['User_ID'] ?>&<?= http_build_query($_GET) ?>" 
                                                   class="btn btn-outline-warning" onclick="return confirm('Suspend this user?')">
                                                    <i class="bi bi-ban"></i> Suspend
                                                </a>
                                            <?php else: ?>
                                                <a href="?toggle=activate&id=<?= $user['User_ID'] ?>&<?= http_build_query($_GET) ?>" 
                                                   class="btn btn-outline-success" onclick="return confirm('Activate this user?')">
                                                    <i class="bi bi-check-circle"></i> Activate
                                                </a>
                                            <?php endif; ?>
                                            <a href="?delete=1&id=<?= $user['User_ID'] ?>&<?= http_build_query($_GET) ?>" 
                                               class="btn btn-outline-danger" onclick="return confirm('Permanently delete this user? This action cannot be undone.')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    No users found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>