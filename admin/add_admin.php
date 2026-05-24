<?php
require_once "includes/auth.php";
requireAdminLogin();
require_once "../config/db.php";

$message = '';
$messageType = '';

// Helper function to generate ID
function genID($prefix, $conn, $table, $col) {
    do {
        $id = $prefix . strtoupper(substr(md5(uniqid()), 0, 6));
        $check = $conn->prepare("SELECT 1 FROM $table WHERE $col = ? LIMIT 1");
        $check->bind_param("s", $id);
        $check->execute();
        $result = $check->get_result();
    } while ($result && $result->num_rows > 0);
    return $id;
}

// Delete admin account (Level 3 only, cannot delete self)
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $adminToDelete = $_GET['id'];
    $currentAdminId = $_SESSION['admin_id'];
    
    // Check if trying to delete self
    if ($adminToDelete == $currentAdminId) {
        $message = "You cannot delete your own admin account!";
        $messageType = "danger";
    } else {
        // Get the user ID associated with this admin
        $getUser = $conn->prepare("SELECT Admin_UserID FROM Admin WHERE Admin_ID = ?");
        $getUser->bind_param("s", $adminToDelete);
        $getUser->execute();
        $userResult = $getUser->get_result();
        
        if ($userResult->num_rows > 0) {
            $userData = $userResult->fetch_assoc();
            $userId = $userData['Admin_UserID'];
            
            $conn->begin_transaction();
            try {
                // Delete from Admin table first
                $deleteAdmin = $conn->prepare("DELETE FROM Admin WHERE Admin_ID = ?");
                $deleteAdmin->bind_param("s", $adminToDelete);
                $deleteAdmin->execute();
                
                // Delete from User table
                $deleteUser = $conn->prepare("DELETE FROM User WHERE User_ID = ?");
                $deleteUser->bind_param("s", $userId);
                $deleteUser->execute();
                
                $conn->commit();
                $message = "Admin account deleted successfully!";
                $messageType = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Failed to delete admin: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "Admin not found.";
            $messageType = "danger";
        }
    }
}

// Process add admin form
if (isset($_POST['add_admin'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $username = trim($_POST['username']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $adminLevel = (int)$_POST['admin_level'];
    $adminPerm = match($adminLevel) {
        1 => 'sellers,products,orders,couriers',
        2 => 'sellers,products,orders,couriers,admins',
        default => 'full',
    };
    
    // Validation
    if (empty($email) || empty($password) || empty($fname) || empty($lname) || empty($username)) {
        $message = "Please fill in all required fields.";
        $messageType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "danger";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
        $messageType = "danger";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
        $messageType = "danger";
    } elseif ($adminLevel < 1 || $adminLevel > 3) {
        $message = "Admin level must be between 1 and 3.";
        $messageType = "danger";
    } else {
        // Check if email already exists
        $checkEmail = $conn->prepare("SELECT 1 FROM User WHERE User_Email = ? LIMIT 1");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        
        if ($checkEmail->get_result()->num_rows > 0) {
            $message = "Email already exists. Please use a different email.";
            $messageType = "danger";
        } else {
            // Check if username already exists
            $checkUser = $conn->prepare("SELECT 1 FROM User WHERE User_AccName = ? LIMIT 1");
            $checkUser->bind_param("s", $username);
            $checkUser->execute();
            
            if ($checkUser->get_result()->num_rows > 0) {
                $message = "Username already taken. Please choose another.";
                $messageType = "danger";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $today  = date('Y-m-d');

                // Sequential USER ID
                $uid = 'USER0001';
                $res = $conn->query("SELECT User_ID FROM User WHERE User_ID LIKE 'USER%' ORDER BY User_ID DESC LIMIT 1");
                if ($res && $row = $res->fetch_assoc()) {
                    if (preg_match('/^USER(\d+)$/', $row['User_ID'], $m)) {
                        $uid = sprintf("USER%04d", intval($m[1]) + 1);
                    }
                }

                // Sequential ADMN ID
                $aid = 'ADMN0001';
                $res = $conn->query("SELECT Admin_ID FROM Admin WHERE Admin_ID LIKE 'ADMN%' ORDER BY Admin_ID DESC LIMIT 1");
                if ($res && $row = $res->fetch_assoc()) {
                    if (preg_match('/^ADMN(\d+)$/', $row['Admin_ID'], $m)) {
                        $aid = sprintf("ADMN%04d", intval($m[1]) + 1);
                    }
                }

                $conn->begin_transaction();
                try {
                    $insertUser = $conn->prepare("
                        INSERT INTO User (User_ID, User_FName, User_LName, User_AccName, User_Email,
                                         User_Password, User_Contact, User_Address, User_DateReg, User_AccType, User_Status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'admin', 'active')
                    ");
                    $insertUser->bind_param("sssssssss", $uid, $fname, $lname, $username, $email,
                                           $hashed, $contact, $address, $today);
                    $insertUser->execute();

                    $insertAdmin = $conn->prepare("
                        INSERT INTO Admin (Admin_ID, Admin_UserID, Admin_Level, Admin_Perm, Admin_LastLog)
                        VALUES (?, ?, ?, ?, NULL)
                    ");
                    $insertAdmin->bind_param("ssis", $aid, $uid, $adminLevel, $adminPerm);
                    $insertAdmin->execute();
                    
                    $conn->commit();
                    $message = "Admin account created successfully!";
                    $messageType = "success";
                    
                    // Clear form
                    $_POST = [];
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Failed to create admin account: " . $e->getMessage();
                    $messageType = "danger";
                }
            }
        }
    }
}

// Get all existing admins
$adminsQuery = $conn->query("
    SELECT a.*, u.User_FName, u.User_LName, u.User_Email, u.User_AccName, u.User_Status
    FROM Admin a
    JOIN User u ON a.Admin_UserID = u.User_ID
    ORDER BY a.Admin_Level DESC, u.User_FName ASC
");

$title = "Add Admin - eBay Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #f5f7fb; }
        .sidebar { width: 260px; background: #1a1f2e; min-height: 100vh; position: fixed; left: 0; top: 0; }
        .main-content { margin-left: 260px; padding: 20px 30px; }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .brand { font-size: 28px; font-weight: 800; }
        .sidebar-brand .brand span:nth-child(1) { color: #3665F3; }
        .sidebar-brand .brand span:nth-child(2) { color: #E53238; }
        .sidebar-brand .brand span:nth-child(3) { color: #F5AF02; }
        .sidebar-brand .brand span:nth-child(4) { color: #86B817; }
        .nav-link-custom { color: #a0a5b5; padding: 12px 20px; margin: 4px 12px; border-radius: 10px; display: block; text-decoration: none; }
        .nav-link-custom:hover, .nav-link-custom.active { background: #2a2f3f; color: white; }
        .nav-link-custom i { width: 24px; margin-right: 10px; }
        .top-nav { background: white; border-radius: 16px; padding: 12px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .card-custom { background: white; border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .level-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .level-3 { background: #d4edda; color: #155724; }
        .level-2 { background: #cce5ff; color: #004085; }
        .level-1 { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand"><span>e</span><span>b</span><span>a</span><span>y</span></div>
            <small>Admin Control Panel</small>
        </div>
        <div class="mt-3">
            <a href="dashboard.php" class="nav-link-custom d-block">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a href="users.php" class="nav-link-custom d-block">
                <i class="bi bi-people"></i> User Management
            </a>
            <a href="add_admin.php" class="nav-link-custom d-block active">
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
            <a href="couriers.php" class="nav-link-custom d-block">
                <i class="bi bi-truck"></i> Couriers
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
                <h4 class="mb-0 fw-bold">Add New Admin</h4>
                <small class="text-muted">Create new administrator accounts</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-danger rounded-pill px-3 py-2">
                    <i class="bi bi-shield-lock-fill me-1"></i> Super Admin (Level <?= $_SESSION['admin_level'] ?>)
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3" style="text-decoration: none;">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Admin Form -->
        <div class="card-custom">
            <h5 class="fw-700 mb-4"><i class="bi bi-person-plus me-2"></i>Create New Admin Account</h5>
            
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="fname" class="form-control" value="<?= htmlspecialchars($_POST['fname'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="lname" class="form-control" value="<?= htmlspecialchars($_POST['lname'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" placeholder="City, Province">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Admin Level <span class="text-danger">*</span></label>
                        <select name="admin_level" class="form-select" required>
                            <option value="1" <?= ($_POST['admin_level'] ?? '1') == '1' ? 'selected' : '' ?>>Level 1 — Moderator (Sellers, Products, Orders, Couriers)</option>
                            <option value="2" <?= ($_POST['admin_level'] ?? '') == '2' ? 'selected' : '' ?>>Level 2 — Manager (All except Users &amp; Categories)</option>
                            <option value="3" <?= ($_POST['admin_level'] ?? '') == '3' ? 'selected' : '' ?>>Level 3 — Super Admin (All functions)</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="add_admin" class="btn btn-primary px-4">
                        <i class="bi bi-check-lg me-1"></i> Create Admin
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary px-4 ms-2">
                        <i class="bi bi-x-lg me-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Existing Admins List -->
        <div class="card-custom">
            <h5 class="fw-700 mb-4"><i class="bi bi-person-badge me-2"></i>Existing Administrators</h5>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Admin Level</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Permissions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($adminsQuery && $adminsQuery->num_rows > 0): ?>
                            <?php while ($admin = $adminsQuery->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($admin['User_FName'] . ' ' . $admin['User_LName']) ?></td>
                                    <td>@<?= htmlspecialchars($admin['User_AccName']) ?></td>
                                    <td><?= htmlspecialchars($admin['User_Email']) ?></td>
                                    <td>
                                        <span class="level-badge level-<?= $admin['Admin_Level'] ?>">
                                            Level <?= $admin['Admin_Level'] ?>
                                            <?= $admin['Admin_Level'] == 3 ? '(Super Admin)' : ($admin['Admin_Level'] == 2 ? '(Manager)' : '(Moderator)') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $admin['User_Status'] == 'active' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= ucfirst($admin['User_Status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $admin['Admin_LastLog'] ? date('M d, Y H:i', strtotime($admin['Admin_LastLog'])) : 'Never' ?></td>
                                    <td><code><small><?= htmlspecialchars($admin['Admin_Perm']) ?></small></code></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_admin.php?id=<?= $admin['Admin_ID'] ?>"
                                               class="btn btn-primary btn-sm"
                                               title="Edit Admin">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <?php if ($admin['Admin_ID'] != $_SESSION['admin_id']): ?>
                                                <a href="?delete=1&id=<?= $admin['Admin_ID'] ?>"
                                                   class="btn btn-danger btn-sm">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="bi bi-shield-check"></i> You
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No other admins found.
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