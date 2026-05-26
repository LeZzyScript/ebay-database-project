<?php
require_once "includes/auth.php";
requireAdminLogin();
require_once "../config/firebase.php";

$message = '';
$messageType = '';

// Delete Category
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $cid = $_GET['id'];
    deleteData("categories/{$cid}");
    $message = "Category deleted successfully!";
    $messageType = "success";
}

// Add/Edit Category (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['cat_name']);
    $desc = trim($_POST['cat_desc']);
    $icon = trim($_POST['cat_icon']);
    $status = $_POST['cat_status'];

    if (isset($_POST['cat_id']) && !empty($_POST['cat_id'])) {
        // Edit
        $cid = $_POST['cat_id'];
        updateData("categories/{$cid}", [
            'name' => $name,
            'description' => $desc,
            'icon' => $icon,
            'status' => $status
        ]);
        $message = "Category updated successfully!";
        $messageType = "success";
    } else {
        // Add
        $catId = generateId('CATG');
        setData("categories/{$catId}", [
            'name' => $name,
            'description' => $desc,
            'icon' => $icon,
            'status' => $status
        ]);
        $message = "Category created successfully!";
        $messageType = "success";
    }
}

// Fetch all categories
$categories = [];
$categoriesData = getData('categories');
if ($categoriesData) {
    foreach ($categoriesData as $catId => $catData) {
        $categories[] = [
            'Cat_ID' => $catId,
            'Cat_Name' => $catData['name'] ?? '',
            'Cat_Description' => $catData['description'] ?? '',
            'Cat_Icon' => $catData['icon'] ?? 'bi-tag',
            'Cat_Status' => $catData['status'] ?? 'active'
        ];
    }
}

// Sort by name
usort($categories, function($a, $b) {
    return strcmp($a['Cat_Name'], $b['Cat_Name']);
});

$title = "Category Management";
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
        .nav-link-custom { color: #a0a5b5; padding: 12px 20px; margin: 4px 12px; border-radius: 10px; display: block; text-decoration: none; transition: all 0.2s; }
        .nav-link-custom:hover, .nav-link-custom.active { background: #2a2f3f; color: white; }
        .nav-link-custom i { width: 24px; margin-right: 10px; }
        .top-nav { background: white; border-radius: 16px; padding: 12px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .table-card { background: white; border-radius: 16px; padding: 20px; }
        .status-active { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-inactive { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand"><span>e</span><span>b</span><span>a</span><span>y</span></div>
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
            <a href="categories.php" class="nav-link-custom d-block active">
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

    <div class="main-content">
        <div class="top-nav">
            <div>
                <h4 class="mb-0 fw-bold"><?= $title ?></h4>
                <small class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Admin') ?></small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary rounded-pill px-3 py-2"><i class="bi bi-shield-lock-fill me-1"></i> Level <?= $_SESSION['admin_level'] ?? 1 ?> Admin</span>
                <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3" style="text-decoration: none;"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Categories Table -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0 fw-bold">All Categories</h5>
                <button class="btn btn-primary btn-sm px-3" onclick="openModal()"><i class="bi bi-plus-lg me-1"></i> Add Category</button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr><th>Icon</th><th>ID</th><th>Name</th><th>Description</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><i class="bi <?= htmlspecialchars($cat['Cat_Icon']) ?> fs-5 text-primary"></i></td>
                                    <td><code><?= $cat['Cat_ID'] ?></code></td>
                                    <td><strong><?= htmlspecialchars($cat['Cat_Name']) ?></strong></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($cat['Cat_Description']) ?></small></td>
                                    <td><span class="status-<?= $cat['Cat_Status'] ?>"><?= ucfirst($cat['Cat_Status']) ?></span></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="editModal('<?= $cat['Cat_ID'] ?>', '<?= htmlspecialchars(addslashes($cat['Cat_Name'])) ?>', '<?= htmlspecialchars(addslashes($cat['Cat_Description'])) ?>', '<?= htmlspecialchars(addslashes($cat['Cat_Icon'])) ?>', '<?= $cat['Cat_Status'] ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?delete=1&id=<?= $cat['Cat_ID'] ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this category? This will also affect products linked to it.')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No categories found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="cat_id" id="cat_id">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="cat_name" id="cat_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="cat_desc" id="cat_desc" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon (Bootstrap Icon Class)</label>
                        <input type="text" name="cat_icon" id="cat_icon" class="form-control" placeholder="e.g. bi-laptop" required>
                        <small class="text-muted"><a href="https://icons.getbootstrap.com/" target="_blank">View icons</a></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="cat_status" id="cat_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
        function openModal() {
            document.getElementById('modalTitle').textContent = 'Add Category';
            document.getElementById('cat_id').value = '';
            document.getElementById('cat_name').value = '';
            document.getElementById('cat_desc').value = '';
            document.getElementById('cat_icon').value = 'bi-tag';
            document.getElementById('cat_status').value = 'active';
            modal.show();
        }
        function editModal(id, name, desc, icon, status) {
            document.getElementById('modalTitle').textContent = 'Edit Category';
            document.getElementById('cat_id').value = id;
            document.getElementById('cat_name').value = name;
            document.getElementById('cat_desc').value = desc;
            document.getElementById('cat_icon').value = icon;
            document.getElementById('cat_status').value = status;
            modal.show();
        }
    </script>
</body>
</html>
