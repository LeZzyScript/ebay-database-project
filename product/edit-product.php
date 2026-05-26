<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || empty($_SESSION['is_seller'])) {
    header('Location: ../buyer/dashboard.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: ../seller/dashboard.php');
    exit;
}

$prodId = $_GET['id'];
require_once('../config/firebase.php');

$uid = $_SESSION['firebase_uid'];

// Fetch existing product
$product = getData("products/{$prodId}");

if (!$product || ($product['sellerId'] ?? '') !== $uid) {
    $_SESSION['flash'] = "Product not found or access denied.";
    header("Location: ../seller/dashboard.php");
    exit;
}

$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $catId    = trim($_POST['category'] ?? '');
    $desc     = trim($_POST['desc'] ?? '');
    $price    = floatval($_POST['price'] ?? 0);
    $stock    = intval($_POST['stock'] ?? 0);
    $imageUrl = trim($_POST['image_url'] ?? '');
    $today    = date('Y-m-d');

    if (!$title || !$catId || !$desc || $price <= 0 || $stock < 0) {
        $error = "Please fill in all required fields correctly.";
    } else {
        updateData("products/{$prodId}", [
            'title' => $title,
            'description' => $desc,
            'price' => $price,
            'image' => $imageUrl,
            'stock' => $stock,
            'categoryId' => $catId,
            'dateUpdated' => $today
        ]);

        $_SESSION['flash'] = "Listing updated successfully!";
        header("Location: ../seller/dashboard.php");
        exit;
    }
} else {
    // Populate form with existing data
    $title    = $product['title'] ?? '';
    $catId    = $product['categoryId'] ?? '';
    $desc     = $product['description'] ?? '';
    $price    = $product['price'] ?? 0;
    $stock    = $product['stock'] ?? 0;
    $imageUrl = $product['image'] ?? '';
}

// Fetch categories for the dropdown
$categories = [];
$categoriesData = getData('categories');
if ($categoriesData) {
    foreach ($categoriesData as $catIdKey => $catData) {
        if (($catData['status'] ?? '') === 'active') {
            $categories[] = [
                'Cat_ID' => $catIdKey,
                'Cat_Name' => $catData['name'] ?? ''
            ];
        }
    }
}

$pageTitle = "Edit Listing - " . htmlspecialchars($title);
$basePath = '../';
include("../layout/layout.php");
?>

<style>
.form-container {
    max-width: 600px;
    margin: 40px auto;
    background: #fff;
    padding: 32px;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.form-header {
    text-align: center;
    margin-bottom: 24px;
}
.form-header h2 {
    font-size: 24px;
    font-weight: 700;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
}
.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    font-family: var(--font);
    outline: none;
    transition: border-color 0.2s;
}
.form-control:focus {
    border-color: var(--blue);
}
textarea.form-control {
    resize: vertical;
    min-height: 100px;
}
.form-row {
    display: flex;
    gap: 16px;
}
.form-row .form-group {
    flex: 1;
}
.submit-btn {
    width: 100%;
    padding: 14px;
    background: var(--blue);
    color: #fff;
    border: none;
    border-radius: 24px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.submit-btn:hover {
    background: var(--blue-hover);
}
.cancel-btn {
    width: 100%;
    padding: 14px;
    background: transparent;
    color: var(--blue);
    border: 1px solid var(--blue);
    border-radius: 24px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 12px;
    display: block;
    text-align: center;
    text-decoration: none;
}
.cancel-btn:hover {
    background: #f0f7ff;
}
.alert {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}
.alert-error {
    background: #ffebe8;
    color: var(--red);
    border: 1px solid #ffcdd2;
}
</style>

<div class="form-container">
    <div class="form-header">
        <h2>Edit Listing</h2>
        <p style="color:var(--muted); font-size:14px;">Item number: <?= htmlspecialchars($prodId) ?></p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="title">Product Title *</label>
            <input type="text" id="title" name="title" class="form-control" required value="<?= htmlspecialchars($title) ?>">
        </div>

        <div class="form-group">
            <label for="category">Category *</label>
            <select id="category" name="category" class="form-control" required>
                <option value="">Select a category...</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['Cat_ID'] ?>" <?= ($catId === $c['Cat_ID']) ? 'selected' : '' ?>><?= htmlspecialchars($c['Cat_Name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="desc">Description *</label>
            <textarea id="desc" name="desc" class="form-control" required><?= htmlspecialchars($desc) ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="price">Price (₱) *</label>
                <input type="number" step="0.01" min="0.01" id="price" name="price" class="form-control" required value="<?= htmlspecialchars((string)$price) ?>">
            </div>
            <div class="form-group">
                <label for="stock">Available Stock *</label>
                <input type="number" min="0" step="1" id="stock" name="stock" class="form-control" required value="<?= htmlspecialchars((string)$stock) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="image_url">Image URL</label>
            <input type="url" id="image_url" name="image_url" class="form-control" value="<?= htmlspecialchars($imageUrl) ?>">
            <small style="color:var(--muted); font-size:12px; margin-top:4px; display:block;">Provide a direct link to the product image.</small>
        </div>

        <button type="submit" class="submit-btn">Update Listing</button>
        <a href="../seller/dashboard.php" class="cancel-btn">Cancel</a>
    </form>
</div>

<?php include("../layout/footer.php"); ?>
