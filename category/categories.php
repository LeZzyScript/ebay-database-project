<?php
session_start();
require_once('../config/firebase.php');

$title = "All Categories";
$basePath = '../';
include("../layout/layout.php");

// -------------------------------------------------------------
// AUTO-SETUP: Create default categories if they don't exist
// -------------------------------------------------------------
$allCategories = getData('categories');
if (!$allCategories || empty($allCategories)) {
    // Insert default categories
    $defaultCategories = [
        'CATG0001' => ['name' => 'Electronics', 'icon' => 'bi-phone', 'description' => 'Phones, laptops, tablets and more', 'status' => 'active'],
        'CATG0002' => ['name' => 'Clothing & shoes', 'icon' => 'bi-handbag', 'description' => 'Brand-name clothing, shoes and accessories', 'status' => 'active'],
        'CATG0003' => ['name' => 'Home & garden', 'icon' => 'bi-house-door', 'description' => 'Furniture, decor, gardening tools', 'status' => 'active'],
        'CATG0004' => ['name' => 'Motors', 'icon' => 'bi-car-front', 'description' => 'Vehicle parts and accessories', 'status' => 'active'],
        'CATG0005' => ['name' => 'Video games', 'icon' => 'bi-joystick', 'description' => 'Games, consoles, gaming accessories', 'status' => 'active'],
        'CATG0006' => ['name' => 'Jewelry & watches', 'icon' => 'bi-gem', 'description' => 'Fine jewelry, luxury watches', 'status' => 'active'],
        'CATG0007' => ['name' => 'Books', 'icon' => 'bi-book', 'description' => 'Fiction, non-fiction, rare books', 'status' => 'active'],
        'CATG0008' => ['name' => 'Sporting goods', 'icon' => 'bi-trophy', 'description' => 'Sports equipment and gear', 'status' => 'active'],
        'CATG0009' => ['name' => 'Toys', 'icon' => 'bi-dice-6', 'description' => 'Action figures, board games, collectible toys', 'status' => 'active'],
        'CATG0010' => ['name' => 'Collectibles & art', 'icon' => 'bi-collection', 'description' => 'Rare cards, coins, vintage items, art', 'status' => 'active'],
        'CATG0011' => ['name' => 'Business & industrial', 'icon' => 'bi-briefcase', 'description' => 'Office supplies, industrial equipment', 'status' => 'active'],
        'CATG0012' => ['name' => 'Health & beauty', 'icon' => 'bi-heart-pulse', 'description' => 'Wellness products, cosmetics, skincare', 'status' => 'active']
    ];
    
    foreach ($defaultCategories as $catId => $catData) {
        setData("categories/{$catId}", $catData);
    }
    $allCategories = $defaultCategories;
}

// Fetch categories from Firebase
$categories = [];
if ($allCategories) {
    foreach ($allCategories as $catId => $catData) {
        $status = $catData['status'] ?? '';
        if ($status === 'active') {
            $categories[] = [
                'Cat_ID'          => $catId,
                'Cat_Name'        => $catData['name'] ?? '',
                'Cat_Icon'        => $catData['icon'] ?? 'bi-tag',
                'Cat_Description' => $catData['description'] ?? '',
                'Cat_Status'      => $status
            ];
        }
    }
    // Sort by name
    usort($categories, function($a, $b) {
        return strcmp($a['Cat_Name'], $b['Cat_Name']);
    });
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
