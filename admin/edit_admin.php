<?php
require_once "includes/auth.php";
requireAdminLogin();
requireAdminLevel(3); // Only Level 3 admins can edit admin accounts
require_once "../config/firebase.php";

$message = '';
$messageType = '';

// Get admin ID from URL
$adminId = $_GET['id'] ?? '';
if (empty($adminId)) {
    header("Location: add_admin.php");
    exit();
}

// Fetch admin data
$adminData = getData("admins/{$adminId}");
if (!$adminData) {
    header("Location: add_admin.php");
    exit();
}

$userId = $adminData['userId'] ?? '';
$userData = getData("users/{$userId}");
if (!$userData) {
    header("Location: add_admin.php");
    exit();
}

$profile = $userData['profile'] ?? [];

// Build admin array for compatibility with existing HTML
$admin = [
    'Admin_ID' => $adminId,
    'Admin_UserID' => $userId,
    'Admin_Level' => $adminData['level'] ?? 1,
    'Admin_Perm' => $adminData['permissions'] ?? '',
    'User_FName' => $profile['firstName'] ?? '',
    'User_LName' => $profile['lastName'] ?? '',
    'User_AccName' => $profile['accountName'] ?? '',
    'User_Email' => $profile['email'] ?? '',
    'User_Contact' => $profile['contact'] ?? '',
    'User_Address' => $profile['address'] ?? '',
    'User_Status' => $userData['status'] ?? 'active'
];

// Check if trying to edit self
$isSelf = ($adminId == $_SESSION['admin_id']);

// Process update form
if (isset($_POST['update_admin'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    $adminLevel = (int)$_POST['admin_level'];
    $adminPerm = trim($_POST['admin_perm']);
    $userStatus = trim($_POST['user_status']);
    
    // Password change (optional)
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($fname) || empty($lname) || empty($username) || empty($email)) {
        $message = "Please fill in all required fields.";
        $messageType = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "danger";
    } elseif ($adminLevel < 1 || $adminLevel > 3) {
        $message = "Admin level must be between 1 and 3.";
        $messageType = "danger";
    } elseif (!empty($newPassword) && strlen($newPassword) < 8) {
        $message = "Password must be at least 8 characters.";
        $messageType = "danger";
    } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "danger";
    } else {
        try {
            // Update user profile in Firebase Realtime Database
            updateData("users/{$userId}/profile", [
                'firstName' => $fname,
                'lastName' => $lname,
                'accountName' => $username,
                'email' => $email,
                'contact' => $contact,
                'address' => $address
            ]);
            
            // Update user status
            updateData("users/{$userId}/status", $userStatus);
            
            // Update password if provided
            if (!empty($newPassword)) {
                $auth->updateUser($userId, [
                    'password' => $newPassword
                ]);
            }
            
            // Update admin record in Firebase Realtime Database
            updateData("admins/{$adminId}", [
                'level' => $adminLevel,
                'permissions' => $adminPerm
            ]);
            
            // If editing self, update session variables
            if ($isSelf) {
                $_SESSION['admin_name'] = $fname . ' ' . $lname;
                $_SESSION['admin_level'] = $adminLevel;
                $_SESSION['admin_perm'] = $adminPerm;
            }
            
            $message = "Admin account updated successfully!";
            $messageType = "success";
            
            // Refresh admin data
            $adminData = getData("admins/{$adminId}");
            $userData = getData("users/{$userId}");
            $profile = $userData['profile'] ?? [];
            $admin = [
                'Admin_ID' => $adminId,
                'Admin_UserID' => $userId,
                'Admin_Level' => $adminData['level'] ?? 1,
                'Admin_Perm' => $adminData['permissions'] ?? '',
                'User_FName' => $profile['firstName'] ?? '',
                'User_LName' => $profile['lastName'] ?? '',
                'User_AccName' => $profile['accountName'] ?? '',
                'User_Email' => $profile['email'] ?? '',
                'User_Contact' => $profile['contact'] ?? '',
                'User_Address' => $profile['address'] ?? '',
                'User_Status' => $userData['status'] ?? 'active'
            ];
            
        } catch (\Exception $e) {
            $message = "Failed to update admin: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

$title = "Edit Admin - eBay Admin";
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
        .form-section { border-bottom: 1px solid #e0e0e0; padding-bottom: 20px; margin-bottom: 20px; }
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
            <a href="sellers.php" class="nav-link-custom d-block">
                <i class="bi bi-building"></i> Sellers & Verification
            </a>
            <a href="products.php" class="nav-link-custom d-block">
                <i class="bi bi-box"></i> Products
            </a>

            <a href="orders.php" class="nav-link-custom d-block">
                <i class="bi bi-receipt"></i> Orders
            </a>
            <a href="add_admin.php" class="nav-link-custom d-block active">
                <i class="bi bi-person-badge"></i> Add Admin
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
                <h4 class="mb-0 fw-bold">Edit Admin Account</h4>
                <small class="text-muted">Editing: <?= htmlspecialchars($admin['User_FName'] . ' ' . $admin['User_LName']) ?></small>
            </div>
            <span class="badge bg-danger rounded-pill px-3 py-2">
                <i class="bi bi-shield-lock-fill me-1"></i> Super Admin (Level 3)
            </span>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Edit Admin Form -->
        <div class="card-custom">
            <?php if ($isSelf): ?>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    You are editing your own account. Be careful with changing your admin level or permissions.
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h5 class="fw-700 mb-3"><i class="bi bi-person-circle me-2"></i>Personal Information</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="fname" class="form-control" value="<?= htmlspecialchars($admin['User_FName']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lname" class="form-control" value="<?= htmlspecialchars($admin['User_LName']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($admin['User_AccName']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['User_Email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($admin['User_Contact'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Status</label>
                            <select name="user_status" class="form-select">
                                <option value="active" <?= $admin['User_Status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="suspended" <?= $admin['User_Status'] == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($admin['User_Address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Admin Role Section -->
                <div class="form-section">
                    <h5 class="fw-700 mb-3"><i class="bi bi-shield-lock me-2"></i>Admin Role & Permissions</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Admin Level <span class="text-danger">*</span></label>
                            <select name="admin_level" class="form-select" required>
                                <option value="1" <?= $admin['Admin_Level'] == 1 ? 'selected' : '' ?>>Level 1 - Moderator</option>
                                <option value="2" <?= $admin['Admin_Level'] == 2 ? 'selected' : '' ?>>Level 2 - Manager</option>
                                <option value="3" <?= $admin['Admin_Level'] == 3 ? 'selected' : '' ?>>Level 3 - Super Admin</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Permissions</label>
                            <select name="admin_perm" class="form-select">
                                <option value="all" <?= $admin['Admin_Perm'] == 'all' ? 'selected' : '' ?>>All Permissions</option>
                                <option value="users,products,orders" <?= $admin['Admin_Perm'] == 'users,products,orders' ? 'selected' : '' ?>>Users, Products, Orders</option>
                                <option value="products,orders" <?= $admin['Admin_Perm'] == 'products,orders' ? 'selected' : '' ?>>Products, Orders</option>
                                <option value="orders" <?= $admin['Admin_Perm'] == 'orders' ? 'selected' : '' ?>>Orders Only</option>
                            </select>
                            <small class="text-muted">Comma-separated permissions or "all" for full access</small>
                        </div>
                    </div>
                </div>

                <!-- Change Password Section -->
                <div class="form-section">
                    <h5 class="fw-700 mb-3"><i class="bi bi-key me-2"></i>Change Password</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="mt-4">
                    <button type="submit" name="update_admin" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                    <a href="add_admin.php" class="btn btn-secondary px-4 ms-2">
                        <i class="bi bi-arrow-left me-1"></i> Back to Admin List
                    </a>
                    <?php if (!$isSelf): ?>
                        <a href="add_admin.php?delete=1&id=<?= $adminId ?>" 
                           class="btn btn-danger px-4 ms-2 float-end"
                           onclick="return confirm('Delete this admin account?')">
                            <i class="bi bi-trash me-1"></i> Delete Admin
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>