<?php
require_once "includes/auth.php";
requireAdminLogin();
require_once "../config/db.php";

$message = '';
$messageType = '';

// Verify business
if (isset($_GET['verify']) && isset($_GET['id'])) {
    $bid = $conn->real_escape_string($_GET['id']);
    if ($conn->query("UPDATE Business SET Bus_Verified = 1 WHERE Bus_ID = '$bid'")) {
        $message = "Business verified successfully!";
        $messageType = "success";
    }
}

// Reject business
if (isset($_GET['reject']) && isset($_GET['id'])) {
    $bid = $conn->real_escape_string($_GET['id']);
    if ($conn->query("DELETE FROM Business WHERE Bus_ID = '$bid'")) {
        $message = "Business application rejected and removed.";
        $messageType = "danger";
    }
}

// Get pending sellers with business info
$query = "SELECT b.*, s.Sell_ID, s.Sell_UserID, s.Sell_Status, 
                 u.User_FName, u.User_LName, u.User_Email, u.User_Contact
          FROM Business b
          JOIN Seller s ON b.Bus_SellID = s.Sell_ID
          JOIN User u ON s.Sell_UserID = u.User_ID
          WHERE b.Bus_Verified = 0
          ORDER BY s.Sell_JoinDate DESC";
$pendingSellers = $conn->query($query);

// Get verified sellers
$query2 = "SELECT b.*, s.Sell_ID, s.Sell_UserID, s.Sell_Status, 
                  u.User_FName, u.User_LName, u.User_Email, u.User_Contact
           FROM Business b
           JOIN Seller s ON b.Bus_SellID = s.Sell_ID
           JOIN User u ON s.Sell_UserID = u.User_ID
           WHERE b.Bus_Verified = 1
           ORDER BY u.User_FName";
$verifiedSellers = $conn->query($query2);

$title = "Seller Management";
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
        .table-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 24px; }
        .status-pending { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-verified { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
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
            <a href="sellers.php" class="nav-link-custom d-block active">
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

        <!-- Pending Verifications -->
        <div class="table-card">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-warning"></i>Pending Verification</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Business Name</th><th>Owner</th><th>Tax ID</th><th>Reg Number</th><th>Business Type</th><th>Phone</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($pendingSellers && $pendingSellers->num_rows > 0): ?>
                            <?php while ($row = $pendingSellers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['Bus_Name']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['User_FName'] . ' ' . $row['User_LName']) ?><br><small class="text-muted"><?= $row['User_Email'] ?></small></td>
                                    <td><?= htmlspecialchars($row['Bus_TaxID']) ?></td>
                                    <td><?= htmlspecialchars($row['Bus_RegNum']) ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($row['Bus_Type']) ?></span></td>
                                    <td><?= htmlspecialchars($row['Bus_Phone']) ?></td>
                                    <td>
                                        <a href="?verify=1&id=<?= $row['Bus_ID'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Verify this business?')"><i class="bi bi-check-lg"></i> Verify</a>
                                        <a href="?reject=1&id=<?= $row['Bus_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this business application?')"><i class="bi bi-x-lg"></i> Reject</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No pending verifications.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Verified Sellers -->
        <div class="table-card">
            <h5 class="fw-bold mb-3"><i class="bi bi-check-circle me-2 text-success"></i>Verified Sellers</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Business Name</th><th>Owner</th><th>Business Type</th><th>Phone</th><th>Address</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($verifiedSellers && $verifiedSellers->num_rows > 0): ?>
                            <?php while ($row = $verifiedSellers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['Bus_Name']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['User_FName'] . ' ' . $row['User_LName'])?></td>
                                    <td><?= htmlspecialchars($row['Bus_Type']) ?></td>
                                    <td><?= htmlspecialchars($row['Bus_Phone']) ?></td>
                                    <td><small><?= htmlspecialchars(substr($row['Bus_Address'], 0, 50)) ?>...</small></td>
                                    <td><span class="status-verified"><i class="bi bi-check-circle-fill me-1"></i>Verified</span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No verified sellers yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>