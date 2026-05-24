<?php
session_start();
require_once('../config/db.php');

$title = "All Categories";
$basePath = '../';
include("../layout/layout.php");

// -------------------------------------------------------------
// AUTO-SETUP: Create table and insert data if it doesn't exist
// -------------------------------------------------------------
$conn->query("CREATE TABLE IF NOT EXISTS Category (
    Cat_ID CHAR(8) PRIMARY KEY,
    Cat_Name VARCHAR(100) NOT NULL,
    Cat_Icon VARCHAR(50) NOT NULL,
    Cat_Description TEXT,
    Cat_Status VARCHAR(20) NOT NULL DEFAULT 'active'
)");

$res = $conn->query("SELECT COUNT(*) as count FROM Category");
$row = $res->fetch_assoc();
if ($row['count'] == 0) {
    // Note: I swapped 'bi-tshirt' to 'bi-handbag' here to prevent the broken icon!
    $conn->query("INSERT INTO Category (Cat_ID, Cat_Name, Cat_Icon, Cat_Description, Cat_Status) VALUES
        ('CATG0001', 'Electronics', 'bi-phone', 'Phones, laptops, tablets and more', 'active'),
        ('CATG0002', 'Clothing & shoes', 'bi-handbag', 'Brand-name clothing, shoes and accessories', 'active'),
        ('CATG0003', 'Home & garden', 'bi-house-door', 'Furniture, decor, gardening tools', 'active'),
        ('CATG0004', 'Motors', 'bi-car-front', 'Vehicle parts and accessories', 'active'),
        ('CATG0005', 'Video games', 'bi-joystick', 'Games, consoles, gaming accessories', 'active'),
        ('CATG0006', 'Jewelry & watches', 'bi-gem', 'Fine jewelry, luxury watches', 'active'),
        ('CATG0007', 'Books', 'bi-book', 'Fiction, non-fiction, rare books', 'active'),
        ('CATG0008', 'Sporting goods', 'bi-trophy', 'Sports equipment and gear', 'active'),
        ('CATG0009', 'Toys', 'bi-dice-6', 'Action figures, board games, collectible toys', 'active'),
        ('CATG0010', 'Collectibles & art', 'bi-collection', 'Rare cards, coins, vintage items, art', 'active'),
        ('CATG0011', 'Business & industrial', 'bi-briefcase', 'Office supplies, industrial equipment', 'active'),
        ('CATG0012', 'Health & beauty', 'bi-heart-pulse', 'Wellness products, cosmetics, skincare', 'active')
    ");
}

// Fetch categories from DB
$categories = [];
$stmt = $conn->query("SELECT * FROM Category WHERE Cat_Status = 'active' ORDER BY Cat_Name ASC");
if ($stmt) {
    while ($row = $stmt->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<!-- Bootstrap Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.page-header {
    background: #fff;
    padding: 32px 24px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 24px;
}
.page-header-inner {
    max-width: 1200px;
    margin: 0 auto;
}
.page-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--text);
}

.cat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 24px 60px;
}
.cat-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 32px 24px;
    text-decoration: none;
    color: var(--text);
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: box-shadow 0.2s, transform 0.2s, border-color 0.2s;
}
.cat-card:hover {
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
    transform: translateY(-4px);
    border-color: var(--blue);
    text-decoration: none;
    color: var(--text);
}
.cat-icon {
    font-size: 4rem;
    color: var(--blue);
    margin-bottom: 16px;
    transition: transform 0.2s;
}
.cat-card:hover .cat-icon {
    transform: scale(1.1);
}
.cat-name {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 8px;
}
.cat-desc {
    font-size: 14px;
    color: var(--muted);
    line-height: 1.4;
}
</style>

<div class="page-header">
    <div class="page-header-inner">
        <h1>All Categories</h1>
    </div>
</div>

<div class="cat-grid">
    <?php if (count($categories) > 0): ?>
        <?php foreach ($categories as $cat): ?>
            <!-- Note: passing Cat_ID to category.php to handle the actual routing later -->
            <a href="category.php?id=<?= urlencode($cat['Cat_ID']) ?>" class="cat-card">
                <i class="bi <?= htmlspecialchars($cat['Cat_Icon']) ?> cat-icon"></i>
                <div class="cat-name"><?= htmlspecialchars($cat['Cat_Name']) ?></div>
                <div class="cat-desc"><?= htmlspecialchars($cat['Cat_Description']) ?></div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No categories found.</p>
    <?php endif; ?>
</div>

<?php include("../layout/footer.php"); ?>
