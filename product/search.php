<?php
session_start();
require_once('../config/firebase.php');

$q = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 24; // 24 items (e.g., 2 cols x 12 rows)
$offset = ($page - 1) * $limit;

// Fetch all products and categories
$productsData = getData('products');
$categoriesData = getData('categories');

// Filter products based on search query
$filteredProducts = [];
if ($productsData) {
    foreach ($productsData as $prodId => $prodData) {
        if (($prodData['status'] ?? '') !== 'active') {
            continue;
        }
        
        if ($q !== '') {
            $title = strtolower($prodData['title'] ?? '');
            $catId = $prodData['categoryId'] ?? '';
            $catName = '';
            if ($catId && isset($categoriesData[$catId])) {
                $catName = strtolower($categoriesData[$catId]['name'] ?? '');
            }
            $searchQ = strtolower($q);
            
            if (strpos($title, $searchQ) === false && strpos($catName, $searchQ) === false) {
                continue;
            }
        }
        
        $filteredProducts[] = array_merge(['Prod_ID' => $prodId], $prodData);
    }
}

// Sort by date added (descending)
usort($filteredProducts, function($a, $b) {
    return strtotime($b['dateAdded'] ?? '0') - strtotime($a['dateAdded'] ?? '0');
});

$totalItems = count($filteredProducts);
$totalPages = ceil($totalItems / $limit);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

// Apply pagination
$paginatedProducts = array_slice($filteredProducts, $offset, $limit);

// Map to old field names for compatibility
$products = [];
foreach ($paginatedProducts as $prod) {
    $products[] = [
        'Prod_ID' => $prod['Prod_ID'],
        'Prod_Title' => $prod['title'] ?? '',
        'Prod_Price' => $prod['price'] ?? 0,
        'Prod_Image' => $prod['image'] ?? '',
        'Prod_Stock' => $prod['stock'] ?? 0,
        'Prod_Status' => $prod['status'] ?? '',
        'Prod_CatID' => $prod['categoryId'] ?? '',
        'Cat_Name' => isset($categoriesData[$prod['categoryId'] ?? '']) ? ($categoriesData[$prod['categoryId']]['name'] ?? '') : ''
    ];
}

$title = $q !== '' ? 'Search: ' . htmlspecialchars($q) : 'Browse All Products';
$basePath = '../';
include('../layout/layout.php');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
.search-container {
    max-width: 1200px;
    margin: 24px auto;
    padding: 0 20px;
}
.search-header {
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.search-header h1 {
    font-size: 24px;
    font-weight: 700;
}
.search-results-count {
    color: var(--muted);
    font-size: 14px;
}
.filters-bar {
    display: flex;
    gap: 12px;
}

/* Grid: 2 columns min, responsive up to more */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}
@media (max-width: 600px) {
    .product-grid {
        grid-template-columns: repeat(2, 1fr); /* Force 2 columns on mobile */
        gap: 12px;
    }
}

.prod-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px;
    transition: box-shadow 0.2s, transform 0.2s;
    display: flex;
    flex-direction: column;
    position: relative;
    text-decoration: none;
    color: inherit;
    height: 100%;
}
.prod-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
.prod-img {
    width: 100%;
    aspect-ratio: 1 / 1;
    background: #f7f7f7;
    border-radius: 8px;
    margin-bottom: 12px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.prod-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.wish-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    z-index: 2;
    transition: transform 0.1s;
}
.wish-btn:active { transform: scale(0.9); }
.wish-btn i { font-size: 14px; color: var(--text); }
.wish-btn i.bi-heart-fill { color: var(--red); }

.prod-cond { font-size: 11px; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.prod-name { font-size: 14px; font-weight: 500; line-height: 1.4; margin-bottom: 8px; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.prod-price { font-size: 18px; font-weight: 700; color: var(--text); margin-bottom: 12px; }

.card-cart-btn {
    width: 100%;
    padding: 8px 0;
    border-radius: 20px;
    border: 1px solid var(--blue);
    background: #fff;
    color: var(--blue);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: auto;
}
.card-cart-btn:hover { background: #f0f7ff; }

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-bottom: 60px;
}
.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text);
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s, border-color 0.2s;
}
.page-btn:hover:not(.disabled) {
    background: var(--bg);
    border-color: var(--text);
}
.page-btn.active {
    background: var(--blue);
    color: #fff;
    border-color: var(--blue);
}
.page-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}
.page-info {
    font-size: 14px;
    color: var(--muted);
    margin: 0 12px;
}
</style>

<div class="search-container">
    <div class="search-header">
        <div>
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="search-results-count">
                Showing <?= min($offset + 1, $totalItems) ?> - <?= min($offset + $limit, $totalItems) ?> of <?= $totalItems ?> results
            </div>
        </div>
    </div>

    <?php if (count($products) > 0): ?>
        <div class="product-grid">
            <?php foreach ($products as $prod): ?>
                <a href="product.php?id=<?= urlencode($prod['Prod_ID']) ?>" class="prod-card">
                    <div class="prod-img">
                        <?php if (!empty($prod['Prod_Image'])): ?>
                            <img src="<?= htmlspecialchars($prod['Prod_Image']) ?>" alt="Product">
                        <?php else: ?>
                            <i class="bi bi-box" style="font-size:3rem;color:var(--muted);"></i>
                        <?php endif; ?>
                        <button class="wish-btn" onclick="event.preventDefault(); event.stopPropagation(); toggleWish(this, '<?= htmlspecialchars($prod['Prod_ID']) ?>', '<?= htmlspecialchars(addslashes($prod['Prod_Title'])) ?>')">
                            <i class="bi bi-heart"></i>
                        </button>
                    </div>
                    <div class="prod-cond"><?= htmlspecialchars($prod['Cat_Name'] ?? 'General') ?></div>
                    <div class="prod-name"><?= htmlspecialchars($prod['Prod_Title']) ?></div>
                    <div class="prod-price">₱<?= number_format($prod['Prod_Price'], 2) ?></div>
                    
                    <button class="card-cart-btn" onclick="event.preventDefault(); event.stopPropagation(); addToCart(this, '<?= htmlspecialchars($prod['Prod_ID']) ?>')">
                        <i class="bi bi-cart-plus"></i> Add to cart
                    </button>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                $prevParams = $_GET; $prevParams['page'] = $page - 1;
                $nextParams = $_GET; $nextParams['page'] = $page + 1;
                $qsPrev = http_build_query($prevParams);
                $qsNext = http_build_query($nextParams);
                ?>
                <a href="?<?= $qsPrev ?>" class="page-btn <?= ($page <= 1) ? 'disabled' : '' ?>" title="Previous"><i class="bi bi-chevron-left"></i></a>
                
                <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>
                
                <a href="?<?= $qsNext ?>" class="page-btn <?= ($page >= $totalPages) ? 'disabled' : '' ?>" title="Next"><i class="bi bi-chevron-right"></i></a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div style="text-align:center; padding: 60px 20px; background:#f9f9f9; border-radius:12px; border:1px solid var(--border);">
            <i class="bi bi-search" style="font-size:48px; color:var(--muted);"></i>
            <h2 style="margin-top:16px; font-size:20px;">No exact matches found</h2>
            <p style="color:var(--muted); margin-top:8px;">Try searching for something else or check your spelling.</p>
        </div>
    <?php endif; ?>
</div>

<?php include('../layout/footer.php'); ?>

<script>
function toggleWish(btn, prodId, name) {
    const watching = btn.querySelector('i').classList.contains('bi-heart-fill');
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
        const icon = btn.querySelector('i');
        if (watching) {
            icon.classList.replace('bi-heart-fill', 'bi-heart');
            showToast(`Removed "${name}" from Watchlist`);
        } else {
            icon.classList.replace('bi-heart', 'bi-heart-fill');
            showToast(`❤️ "${name}" added to Watchlist`);
        }
        btn.disabled = false;
    })
    .catch(() => { showToast('Something went wrong'); btn.disabled = false; });
}

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
            setTimeout(() => { btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart'; btn.disabled = false; }, 2000);
        } else {
            showToast(data.message || 'Could not add to cart');
            btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart';
            btn.disabled = false;
        }
    })
    .catch(() => { showToast('Something went wrong'); btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to cart'; btn.disabled = false; });
}
</script>
