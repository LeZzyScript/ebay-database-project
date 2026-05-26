<?php
require_once "includes/auth.php";
requireAdminLogin();
require_once "../config/firebase.php";
$stats = [];

// Buyers
$allUsers = getData('users');
$stats['total_buyers'] = 0;
$stats['total_sellers'] = 0;
$stats['new_users_week'] = 0;
$stats['suspended_users'] = 0;
$stats['pending_verification'] = 0;

$weekAgo = date('Y-m-d', strtotime('-7 days'));

if ($allUsers) {
    foreach ($allUsers as $uid => $userData) {
        $accountType = $userData['accountType'] ?? '';
        $dateReg = $userData['dateRegistered'] ?? '';
        $status = $userData['status'] ?? 'active';
        
        if ($accountType === 'buyer') {
            $stats['total_buyers']++;
            if (strtotime($dateReg) >= strtotime($weekAgo)) {
                $stats['new_users_week']++;
            }
            if ($status === 'suspended') {
                $stats['suspended_users']++;
            }
        } elseif ($accountType === 'seller') {
            $stats['total_sellers']++;
            if (strtotime($dateReg) >= strtotime($weekAgo)) {
                $stats['new_users_week']++;
            }
            if ($status === 'suspended') {
                $stats['suspended_users']++;
            }
            // Check pending verification
            if (isset($userData['sellerData']['business']['verified']) && !$userData['sellerData']['business']['verified']) {
                $stats['pending_verification']++;
            }
        }
    }
}

// Total products
$allProducts = getData('products');
$stats['total_products'] = $allProducts ? count($allProducts) : 0;

$title = "Admin Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?> - eBay Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f5f7fb; }
        .sidebar {
            width: 260px;
            background: #1a1f2e;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            transition: all 0.3s;
        }
        .main-content {
            margin-left: 260px;
            padding: 20px 30px;
        }
        .sidebar-brand {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-brand .brand {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -1px;
        }
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
        .nav-link-custom:hover, .nav-link-custom.active {
            background: #2a2f3f;
            color: white;
            text-decoration: none;
        }
        .nav-link-custom i { width: 24px; margin-right: 10px; }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .top-nav {
            background: white;
            border-radius: 16px;
            padding: 12px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
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
            <a href="dashboard.php" class="nav-link-custom d-block active">
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

            <a href="orders.php" class="nav-link-custom d-block">
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

        <!-- ── USERS & SELLERS ── -->
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-people-fill text-primary fs-5"></i>
            <h6 class="fw-bold mb-0">Users &amp; Sellers</h6>
            <hr class="flex-grow-1 my-0 ms-2" style="border-color:#e0e0e0;">
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">Buyers</div>
                            <div class="h2 fw-bold mt-1 mb-0"><?= number_format($stats['total_buyers']) ?></div>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                            <i class="bi bi-person-fill text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">Sellers</div>
                            <div class="h2 fw-bold mt-1 mb-0"><?= number_format($stats['total_sellers']) ?></div>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                            <i class="bi bi-building text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">Pending Verification</div>
                            <div class="h2 fw-bold mt-1 mb-0"><?= number_format($stats['pending_verification']) ?></div>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                            <i class="bi bi-clock-history text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small fw-semibold">Total Products</div>
                            <div class="h2 fw-bold mt-1 mb-0"><?= number_format($stats['total_products']) ?></div>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                            <i class="bi bi-box-seam text-info fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="stat-card">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus me-2"></i>New Users (Last 7 Days)</h6>
                    <div class="h2 fw-bold text-primary mb-0"><?= number_format($stats['new_users_week']) ?></div>
                    <small class="text-muted">Buyers &amp; sellers registered this week</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Suspended Accounts</h6>
                    <div class="h2 fw-bold text-danger mb-0"><?= number_format($stats['suspended_users']) ?></div>
                    <small class="text-muted">Buyers &amp; sellers requiring attention</small>
                </div>
            </div>
        </div>


    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>