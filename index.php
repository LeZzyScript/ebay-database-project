<?php
session_start();
require_once('config/firebase.php');

// Fetch up to 12 active categories
$categories = [];
$allCategories = getData('categories');
if ($allCategories) {
    $count = 0;
    foreach ($allCategories as $catId => $catData) {
        if ($count >= 12) break;
        $status = $catData['status'] ?? '';
        if ($status === 'active') {
            $categories[] = [
                'Cat_ID'          => $catId,
                'Cat_Name'        => $catData['name'] ?? '',
                'Cat_Icon'        => $catData['icon'] ?? 'bi-tag',
                'Cat_Description' => $catData['description'] ?? '',
                'Cat_Status'      => $status
            ];
            $count++;
        }
    }
}

// Fetch the 6 latest products
$latestProducts = [];
$allProducts = getData('products');
if ($allProducts) {
    $productsArray = [];
    foreach ($allProducts as $prodId => $prodData) {
        $productsArray[] = [
            'Prod_ID'    => $prodId,
            'Prod_Title' => $prodData['title'] ?? '',
            'Prod_Price' => $prodData['price'] ?? 0,
            'Prod_Image' => $prodData['image'] ?? '',
            'dateAdded'  => $prodData['dateAdded'] ?? '1970-01-01'
        ];
    }
    // Sort by dateAdded (descending) and take latest 6
    usort($productsArray, function($a, $b) {
        return strtotime($b['dateAdded']) - strtotime($a['dateAdded']);
    });
    $latestProducts = array_slice($productsArray, 0, 6);
}

$isLoggedIn = isUserLoggedIn();

$title = "Home";
include("layout/layout.php");
?>

<!-- Bootstrap Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
/* Additional styling for Bootstrap Icons in your existing layout */
.cat-tile i { font-size: 3rem; color: var(--blue); }
.live-card-img i { font-size: 4rem; color: #fff; }
.prod-img i { font-size: 5rem; color: var(--muted); }
.hero-img-card i { font-size: 4rem; color: var(--text); }

.wish-btn {
    background: rgba(255,255,255,0.9);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
/* Ensure the heart icon inside the wish button is reasonably sized */
.wish-btn i { font-size: 1.2rem !important; color: var(--text); }
.wish-btn i.bi-heart-fill { color: var(--red); }

.card-cart-btn {
    width: 100%;
    margin-top: 8px;
    padding: 7px 0;
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
    gap: 5px;
}
.card-cart-btn:hover { background: #f0f7ff; }
</style>

<div class="hero-wrap">
    <div class="hero" id="heroBanner" style="background:#F7B731;">

        <div class="hero-text">
            <h1 id="heroTitle">Best loved items at 12%* off</h1>
            <p  id="heroSub">Save on top eBay categories, from fashion to vehicle parts.</p>
            <button class="hero-cta" id="heroBtn">Get your coupon</button>
            <a href="#" class="hero-terms">*See terms.</a>
        </div>

        <div class="hero-imgs">
            <div class="hero-img-card" id="hi0"></div>
            <div class="hero-img-card" id="hi1"></div>
            <div class="hero-img-card" id="hi2"></div>
            <div class="hero-img-card" id="hi3"></div>
        </div>

        <div class="hero-dots">
            <button class="hero-dot on"  onclick="goSlide(0)"></button>
            <button class="hero-dot"     onclick="goSlide(1)"></button>
            <button class="hero-dot"     onclick="goSlide(2)"></button>
            <button class="hero-dot"     onclick="goSlide(3)"></button>
        </div>

        <div class="hero-arrows">
            <button class="hero-arrow" onclick="prevSlide()">&#8249;</button>
            <button class="hero-arrow" onclick="nextSlide()">&#8250;</button>
            <button class="hero-arrow" id="pauseBtn" onclick="togglePause()" title="Pause/Play">&#9208;</button>
        </div>

    </div>
</div>

<div class="easy-strip">
    <div>
        <h2>Shopping made easy</h2>
        <p>Enjoy reliability, secure deliveries and hassle-free shipments!</p>
    </div>
    <?php if (!isset($_SESSION['account_id'])): ?>
        <button class="easy-btn" onclick="location.href='auth/signin.php'">Start now</button>
    <?php else: ?>
        <a href="product/search.php" class="easy-btn">Shop now</a>
    <?php endif; ?>
</div>



<div class="sec">
    <div class="sec-head">
        <h2>Shop by category</h2>
        <a href="category/categories.php" class="see-all">See all categories</a>
    </div>
    <div class="cat-tiles">

        <?php foreach ($categories as $cat): ?>
            <a href="category/category.php?id=<?= urlencode($cat['Cat_ID']) ?>" class="cat-tile">
                <i class="bi <?= htmlspecialchars($cat['Cat_Icon'] ?: 'bi-tag') ?>"></i>
                <span><?= htmlspecialchars($cat['Cat_Name']) ?></span>
            </a>
        <?php endforeach; ?>

    </div>
</div>

<div class="sec">
    <div class="sec-head">
        <h2>Latest Listings</h2>
        <a href="product/search.php" class="see-all">See all</a>
    </div>
    <div class="scroll-row">

        <?php foreach ($latestProducts as $prod): ?>
            <?php
            $prodHref = $isLoggedIn
                ? 'product/product.php?id=' . urlencode($prod['Prod_ID'])
                : 'auth/signin.php?redirect=' . urlencode('product/product.php?id=' . $prod['Prod_ID']);
            ?>
            <a href="<?= $prodHref ?>" class="prod-card" style="text-decoration:none; color:inherit; transition:transform 0.2s, box-shadow 0.2s;">
                <div class="prod-img">
                    <?php if (!empty($prod['Prod_Image'])): ?>
                        <img src="<?= htmlspecialchars($prod['Prod_Image']) ?>" alt="Product" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-box" style="font-size:5rem; color:var(--muted);"></i>
                    <?php endif; ?>
                    <button class="wish-btn"
                            onclick="event.preventDefault(); event.stopPropagation(); toggleWish(this, <?= htmlspecialchars(json_encode($prod['Prod_ID']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode(html_entity_decode(strip_tags($prod['Prod_Title']))), ENT_QUOTES, 'UTF-8') ?>)">
                        <i class="bi bi-heart"></i>
                    </button>
                </div>
                <p class="prod-cond">Brand New</p>
                <p class="prod-name"><?= htmlspecialchars($prod['Prod_Title']) ?></p>
                <div class="prod-prices">
                    <span class="prod-price">₱<?= number_format($prod['Prod_Price'], 2) ?></span>
                </div>
                <button class="card-cart-btn"
                        onclick="event.preventDefault(); event.stopPropagation(); addToCart(this, <?= htmlspecialchars(json_encode($prod['Prod_ID']), ENT_QUOTES, 'UTF-8') ?>)">
                    <i class="bi bi-cart-plus"></i> Add to cart
                </button>
            </a>
        <?php endforeach; ?>

    </div>
</div>

<?php include("layout/footer.php"); ?>

<!-- ═══════════════════════════════════════
     CAROUSEL JS  (index-only)
═══════════════════════════════════════ -->
<script>
const SLIDES = [
    { bg:'#F7B731', title:'Best loved items at 12%* off',             sub:'Save on top eBay categories, from fashion to vehicle parts.', btn:'Get your coupon',   imgs:['sample/1.jpg','sample/2.jpg','sample/3.jpg','sample/4.jpg'] },
    { bg:'#D6E8FF', title:'Electronics deals this week',              sub:'Big savings on phones, laptops, tablets and more.',           btn:'Shop electronics',   imgs:['sample/5.jpg','sample/6.jpg','sample/7.jpg','sample/8.jpg'] },
    { bg:'#E8F5E9', title:'Fashion for less',                         sub:'Brand-name clothing, shoes and accessories at great prices.', btn:'Shop fashion',       imgs:['sample/9.jpg','sample/10.jpg','sample/11.jpg','sample/12.jpg'] },
    { bg:'#FCE4EC', title:'Collectibles &amp; rare finds',            sub:'Rare cards, coins, vintage items \u2014 all in one place.',  btn:'Explore now',        imgs:['sample/13.jpg','sample/14.jpg','sample/15.jpg','sample/16.jpg'] },
];

let cur = 0, paused = false, autoTimer;

function goSlide(i) {
    cur = i;
    const s = SLIDES[i];
    document.getElementById('heroBanner').style.background = s.bg;
    document.getElementById('heroTitle').innerHTML = s.title;
    document.getElementById('heroSub').textContent = s.sub;
    document.getElementById('heroBtn').textContent = s.btn;
    s.imgs.forEach((imgUrl, x) => {
        const el = document.getElementById('hi' + x);
        if (el) el.innerHTML = '<img src="' + imgUrl + '" style="width:100%; height:100%; object-fit:cover;">';
    });
    document.querySelectorAll('.hero-dot').forEach((d, x) => d.classList.toggle('on', x === i));
}

function nextSlide() { goSlide((cur + 1) % SLIDES.length); restart(); }
function prevSlide() { goSlide((cur - 1 + SLIDES.length) % SLIDES.length); restart(); }
function restart()   { clearInterval(autoTimer); if (!paused) autoTimer = setInterval(nextSlide, 5000); }
function togglePause() {
    paused = !paused;
    document.getElementById('pauseBtn').textContent = paused ? '\u25B6' : '\u23F8';
    restart();
}

function toggleWish(btn, prodId, name) {
    <?php if (!$isLoggedIn): ?>
    location.href = 'auth/signin.php?redirect=' + encodeURIComponent('product/product.php?id=' + prodId);
    return;
    <?php endif; ?>
    const watching = btn.querySelector('i').classList.contains('bi-heart-fill');
    const url = watching ? 'wishlist/remove.php' : 'wishlist/add.php';
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
    <?php if (!$isLoggedIn): ?>
    location.href = 'auth/signin.php?redirect=' + encodeURIComponent('product/product.php?id=' + prodId);
    return;
    <?php endif; ?>
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding…';
    fetch('cart/add.php', {
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

goSlide(0);
restart();
</script>
