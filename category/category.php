<?php
session_start();
if (!isset($_GET['id'])) {
    header('Location: categories.php');
    exit;
}

require_once('../config/db.php');
$catId = $_GET['id'];

// Fetch category details
$category = null;
$stmt = $conn->prepare("SELECT * FROM Category WHERE Cat_ID = ? AND Cat_Status = 'active'");
$stmt->bind_param("s", $catId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $category = $row;
}
$stmt->close();

if (!$category) {
    header('Location: categories.php');
    exit;
}

// Fetch products for this category
$products = [];
$stmt2 = $conn->prepare("SELECT * FROM Product WHERE Prod_CatID = ? ORDER BY Prod_DateAdd DESC");
$stmt2->bind_param("s", $catId);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($p = $res2->fetch_assoc()) {
    $products[] = $p;
}
$stmt2->close();

$title = htmlspecialchars($category['Cat_Name']) . " for Sale | eBay";
$basePath = '../';
include('../layout/layout.php');
?>

<style>
.search-container {
    max-width: 1280px;
    margin: 20px auto;
    padding: 0 20px;
}
.breadcrumbs {
    font-size: 12px;
    color: var(--muted);
    margin-bottom: 24px;
}
.breadcrumbs a {
    color: var(--muted);
    text-decoration: none;
}
.breadcrumbs a:hover {
    text-decoration: underline;
}
.search-header {
    margin-bottom: 24px;
}
.search-header h1 {
    font-size: 24px;
    font-weight: 700;
}
.search-layout {
    display: flex;
    gap: 32px;
}
/* Sidebar */
.sidebar {
    width: 240px;
    flex-shrink: 0;
}
.filter-group {
    margin-bottom: 24px;
    border-top: 1px solid var(--border);
    padding-top: 16px;
}
.filter-group:first-child {
    border-top: none;
    padding-top: 0;
}
.filter-title {
    font-weight: 600;
    margin-bottom: 12px;
    font-size: 16px;
}
.filter-list {
    list-style: none;
    padding: 0;
    margin: 0;
    font-size: 14px;
}
.filter-list li {
    margin-bottom: 8px;
}
.filter-list label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.filter-list input[type="checkbox"], .filter-list input[type="radio"] {
    accent-color: var(--blue);
    width: 16px;
    height: 16px;
}
/* Main Results */
.main-results {
    flex: 1;
}
.results-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    background: #f7f7f7;
    padding: 12px 16px;
    border-radius: 8px;
}
.results-count {
    font-weight: 600;
}
.sort-select {
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 4px;
    font-family: var(--font);
    font-size: 14px;
}

/* Vertical List Format */
.list-card {
    display: flex;
    gap: 24px;
    padding: 24px 0;
    border-bottom: 1px solid var(--border);
}
.list-card:last-child {
    border-bottom: none;
}
.list-img-wrap {
    width: 225px;
    height: 225px;
    flex-shrink: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.list-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.list-img-wrap .placeholder-icon {
    font-size: 5rem;
    color: var(--muted);
}
.list-details {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.list-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--blue);
    text-decoration: none;
    margin-bottom: 4px;
    line-height: 1.3;
}
.list-title:hover {
    text-decoration: underline;
}
.list-cond {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 12px;
}
.list-price-wrap {
    margin-bottom: 12px;
}
.list-price {
    font-size: 24px;
    font-weight: 700;
    color: var(--text);
}
.list-shipping {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
}
.list-returns {
    font-size: 12px;
    color: var(--muted);
    margin-top: 4px;
}
.list-actions {
    margin-top: auto;
    display: flex;
    gap: 12px;
}
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff;
    border: 1px solid var(--blue);
    color: var(--blue);
    padding: 8px 16px;
    border-radius: 24px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.action-btn:hover {
    background: #f0f7ff;
}
.wishlist-btn {
    border-color: var(--border);
    color: var(--text);
}
.wishlist-btn:hover {
    background: #f7f7f7;
    border-color: var(--muted);
}

.empty-state {
    text-align: center;
    padding: 64px 20px;
    background: #f7f7f7;
    border-radius: 12px;
    margin-top: 24px;
}
.empty-state i {
    font-size: 48px;
    color: var(--muted);
    margin-bottom: 16px;
}
.empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 8px;
}
</style>

<div class="search-container">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="../index.php">eBay</a> &gt; 
        <a href="categories.php">All Categories</a> &gt; 
        <span style="color:var(--text);"><?= htmlspecialchars($category['Cat_Name']) ?></span>
    </div>

    <div class="search-header">
        <h1><?= htmlspecialchars($category['Cat_Name']) ?></h1>
    </div>

    <div class="search-layout">
        <!-- Sidebar Filters (Mock) -->
        <aside class="sidebar">
            <div class="filter-group">
                <div class="filter-title">Condition</div>
                <ul class="filter-list">
                    <li><label><input type="checkbox"> Brand New</label></li>
                    <li><label><input type="checkbox"> Open Box</label></li>
                    <li><label><input type="checkbox"> Used</label></li>
                </ul>
            </div>
            <div class="filter-group">
                <div class="filter-title">Price</div>
                <ul class="filter-list">
                    <li><label><input type="radio" name="price"> Under ₱1,000</label></li>
                    <li><label><input type="radio" name="price"> ₱1,000 to ₱5,000</label></li>
                    <li><label><input type="radio" name="price"> Over ₱5,000</label></li>
                </ul>
            </div>
            <div class="filter-group">
                <div class="filter-title">Buying Format</div>
                <ul class="filter-list">
                    <li><label><input type="checkbox"> All Listings</label></li>
                    <li><label><input type="checkbox"> Accepts Offers</label></li>
                    <li><label><input type="checkbox"> Buy It Now</label></li>
                </ul>
            </div>
        </aside>

        <!-- Main Results -->
        <main class="main-results">
            <div class="results-toolbar">
                <div class="results-count">
                    <?= count($products) ?> result<?= count($products) !== 1 ? 's' : '' ?> for "<?= htmlspecialchars($category['Cat_Name']) ?>"
                </div>
                <div>
                    <select class="sort-select">
                        <option>Sort: Best Match</option>
                        <option>Time: newly listed</option>
                        <option>Price + Shipping: lowest first</option>
                        <option>Price + Shipping: highest first</option>
                    </select>
                </div>
            </div>

            <?php if (count($products) > 0): ?>
                <div class="results-list">
                    <?php foreach ($products as $p): ?>
                        <div class="list-card">
                            <a href="../product/product.php?id=<?= urlencode($p['Prod_ID']) ?>" class="list-img-wrap" style="text-decoration:none;">
                                <?php if (!empty($p['Prod_Image'])): ?>
                                    <img src="<?= htmlspecialchars($p['Prod_Image']) ?>" alt="Product">
                                <?php else: ?>
                                    <i class="bi bi-box placeholder-icon"></i>
                                <?php endif; ?>
                            </a>
                            
                            <div class="list-details">
                                <a href="../product/product.php?id=<?= urlencode($p['Prod_ID']) ?>" class="list-title"><?= htmlspecialchars($p['Prod_Title']) ?></a>
                                <div class="list-cond">Brand New</div>
                                
                                <div class="list-price-wrap">
                                    <div class="list-price">₱<?= number_format($p['Prod_Price'], 2) ?></div>
                                </div>
                                
                                <div class="list-shipping">Free shipping</div>
                                <div class="list-returns">Free returns</div>
                                
                                <div class="list-actions">
                                    <button class="action-btn" onclick="addToCart(this, <?= htmlspecialchars(json_encode($p['Prod_ID']), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-cart-plus"></i> Add to cart
                                    </button>
                                    <button class="action-btn wishlist-btn" onclick="toggleWish(this, <?= htmlspecialchars(json_encode($p['Prod_ID']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode(strip_tags($p['Prod_Title'])), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="bi bi-heart"></i> Watch
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-search"></i>
                    <h3>No exact matches found</h3>
                    <p style="color:var(--muted);">There are currently no active listings in this category. Check back later!</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
function addToCart(btn, prodId) {
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding…';

    fetch('../cart/add.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'prod_id=' + encodeURIComponent(prodId) + '&qty=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('🛒 ' + data.message);
            const badge = document.getElementById('cartBadge');
            if (badge) badge.textContent = data.cart_count;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Added';
            setTimeout(() => {
                btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart';
                btn.disabled = false;
            }, 2000);
        } else {
            showToast(data.message || 'Could not add to cart');
            btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart';
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('Something went wrong');
        btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart';
        btn.disabled = false;
    });
}

function toggleWish(btn, prodId, name) {
    const icon = btn.querySelector('i');
    const watching = icon.classList.contains('bi-heart-fill');
    const url = watching ? '../wishlist/remove.php' : '../wishlist/add.php';

    btn.disabled = true;
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'prod_id=' + encodeURIComponent(prodId)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showToast(data.message || 'Error'); btn.disabled = false; return; }
        if (watching) {
            icon.classList.replace('bi-heart-fill', 'bi-heart');
            icon.style.color = '';
            btn.innerHTML = '<i class="bi bi-heart"></i> Watch';
            showToast(`Removed "${name}" from Watchlist`);
        } else {
            icon.classList.replace('bi-heart', 'bi-heart-fill');
            icon.style.color = 'var(--red)';
            btn.innerHTML = '<i class="bi bi-heart-fill" style="color:var(--red)"></i> Watching';
            showToast(`❤️ "${name}" added to Watchlist`);
        }
        btn.disabled = false;
    })
    .catch(() => { showToast('Something went wrong'); btn.disabled = false; });
}
</script>

<?php include('../layout/footer.php'); ?>
