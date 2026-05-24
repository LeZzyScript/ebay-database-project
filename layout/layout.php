<?php
// layout/layout.php
// eBay Philippines — Shared Header & Navbar
// Include at the TOP of every page, exactly like the reference project does:
//
//   $title = "Page Name";
//   include("layout/layout.php");   // from root
//   include("../layout/layout.php"); // from a subfolder (auth/, etc.)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Generate CSRF token once per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn  = isset($_SESSION['account_id']);
$isSeller    = !empty($_SESSION['is_seller']);
$isAdmin     = !empty($_SESSION['is_admin']);
$displayName = htmlspecialchars($_SESSION['display_name'] ?? '');
$cartCount   = (int)($_SESSION['cart_count'] ?? 0);

// Base path: set $basePath = '../' in subfolders before including this file
$p = $basePath ?? '';
if ($isAdmin) {
    $dashUrl    = $p.'admin/dashboard.php';
    $profileUrl = $p.'admin/dashboard.php';
} elseif ($isSeller) {
    $dashUrl    = $p.'seller/dashboard.php';
    $profileUrl = $p.'seller/seller.php';
} else {
    $dashUrl    = $p.'buyer/dashboard.php';
    $profileUrl = $p.'buyer/buyer.php';
}

// Category nav items
$catNavItems = [
    'All Products'                    => $p.'product/search.php',
    'Motors'                          => $p.'category/category.php?id=CATG0004',
    'Electronics'                     => $p.'category/category.php?id=CATG0001',
    'Collectibles'                    => $p.'category/category.php?id=CATG0010',
    'Home and garden'                 => $p.'category/category.php?id=CATG0003',
    'Clothing, shoes and accessories' => $p.'category/category.php?id=CATG0002',
    'Toys'                            => $p.'category/category.php?id=CATG0009',
    'Sporting goods'                  => $p.'category/category.php?id=CATG0008',
    'Business and industrial'         => $p.'category/category.php?id=CATG0011',
    'Jewelry and watches'             => $p.'category/category.php?id=CATG0006',
    'Health and beauty'               => $p.'category/category.php?id=CATG0012',
];

$activeCat = $activeCat ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($title ?? 'eBay Philippines') ?> | eBay Philippines</title>
    <style>@font-face{font-family:'Market Sans';src:local('Market Sans'),local('MarketSans');font-weight:100 900;}</style>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet" />
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <link rel="stylesheet" href="<?= $p ?>assets/css/style.css" />
</head>
<body>

<!-- ═══════════════════════════════════════
     UTILITY BAR
═══════════════════════════════════════ -->
<div class="util-bar">
    <div class="util-left">
        <?php if ($isLoggedIn): ?>
            <a href="<?= $profileUrl ?>" style="color:var(--text);font-weight:600;text-decoration:none;">Hi, <?= $displayName ?>! &#9660;</a>
            &nbsp;|&nbsp;
            <a href="<?= $p ?>auth/logout.php">Sign out</a>
        <?php else: ?>
            Hi!&nbsp;<a href="<?= $p ?>auth/signin.php">Sign in</a>&nbsp;or&nbsp;<a href="<?= $p ?>auth/register.php">register</a>
        <?php endif; ?>
    </div>
    <div class="util-right">
        <a href="<?= $p ?>deals.php">Deals</a>
        <a href="<?= $p ?>brand-outlet.php">Brand Outlet</a>
        <a href="<?= $p ?>gift-cards.php">Gift Cards</a>
        <a href="<?= $p ?>help.php">Help &amp; Contact</a>
        <div class="util-icons">
            <a href="<?= $p ?>notifications.php" title="Notifications">
                <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            </a>
            <a href="<?= $p ?>cart/cart.php" title="Cart">
                <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </a>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════
     MAIN HEADER
═══════════════════════════════════════ -->
<header>
    <div class="main-header">

        <!-- Logo -->
        <div class="logo-wrap">
            <a href="<?= $isLoggedIn ? $dashUrl : $p.'index.php' ?>">
                <img src="<?= $p ?>assets/img/Ebay.png" alt="eBay Philippines" />
            </a>
        </div>
        <!-- Search Form — real GET submission to search.php -->
        <form class="search-form" method="GET" action="<?= $p ?>product/search.php" role="search">
            <input
                class="search-input"
                id="searchInput"
                type="text"
                name="q"
                value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                placeholder="Search for anything"
                aria-label="Search eBay"
                autocomplete="off"
            />
            <select class="search-cat" name="cat" aria-label="Category">
                <?php
                $searchCats = [
                    ''               => 'All Categories',
                    'electronics'    => 'Electronics',
                    'fashion'        => 'Fashion',
                    'motors'         => 'Motors',
                    'home-garden'    => 'Home & Garden',
                    'collectibles'   => 'Collectibles',
                    'sporting-goods' => 'Sporting Goods',
                    'toys'           => 'Toys',
                    'books'          => 'Books',
                    'jewelry'        => 'Jewelry & Watches',
                ];
                $selectedCat = $_GET['cat'] ?? '';
                foreach ($searchCats as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $val === $selectedCat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="search-submit" type="submit" aria-label="Search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
        </form>

        <a href="<?= $p ?>product/search.php?advanced=1" class="adv-link">Advanced</a>

        <!-- Icon Buttons -->
        <div class="hdr-icons">
            <a href="<?= $isLoggedIn ? $p.'wishlist/wishlist.php' : $p.'auth/signin.php?redirect=wishlist/wishlist.php' ?>"
               class="hdr-icon" title="Watchlist">
                <svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </a>

            <?php if ($isLoggedIn): ?>
                <div class="dropdown" style="position:relative;">
                    <div class="user-dropdown" id="userDropdown" style="display:none;">
                        <p class="dropdown-name"><?= $displayName ?></p>
                        <hr class="dropdown-divider" />
                        <a href="<?= $p ?>buyer/orders.php" class="dropdown-item-link" target="_blank">My Orders</a>
                        <a href="<?= $p ?>wishlist/wishlist.php"   class="dropdown-item-link">Watchlist</a>
                        <a href="<?= $p ?>sell.php"        class="dropdown-item-link">Sell</a>
                        <hr class="dropdown-divider" />
                        <a href="<?= $p ?>auth/logout.php" class="dropdown-item-link text-red">Sign out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= $p ?>auth/signin.php" class="hdr-icon" title="Sign in">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>
            <?php endif; ?>

            <a href="<?= $p ?>cart/cart.php" class="hdr-icon" title="Cart" style="position:relative;">
                <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <span class="cart-badge" id="cartBadge"><?= $cartCount ?></span>
            </a>
        </div>

    </div><!-- /.main-header -->

    <!-- Category Nav -->
    <nav class="cat-nav" aria-label="Main categories">
        <div class="cat-nav-inner">
            <?php foreach ($catNavItems as $label => $url): ?>
                <a href="<?= htmlspecialchars($url) ?>"
                   class="cat-link <?= ($activeCat === $label) ? 'active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

</header>

<!-- ═══════════════════════════════════════
     SIGN-IN / SIGN-UP MODAL
═══════════════════════════════════════ -->
<div class="modal-bg" id="modalBg" onclick="bgClick(event)">
    <div class="modal-box" role="dialog" aria-modal="true">
        <button class="modal-x" onclick="closeModal()" aria-label="Close">&times;</button>

        <!-- Sign-in pane -->
        <div id="signinPane">
            <h2>Sign in</h2>
            <p class="modal-sub">Stay signed in for the best experience</p>
            <form method="POST" action="<?= $p ?>auth/signin.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                <input class="m-input" type="email"    name="email"    placeholder="Email or username" required />
                <input class="m-input" type="password" name="password" placeholder="Password"         required />
                <button class="m-submit" type="submit">Sign in</button>
            </form>
            <div class="m-divider">or</div>
            <a href="auth/google.php" class="m-google">
                <svg width="18" height="18" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Continue with Google
            </a>
            <div class="m-switch">New to eBay? <a onclick="switchPane('signup')">Create account</a></div>
        </div>

        <!-- Sign-up pane -->
        <div id="signupPane" style="display:none">
            <h2>Create account</h2>
            <p class="modal-sub">Join eBay Philippines &mdash; it&rsquo;s free</p>
            <form method="POST" action="<?= $p ?>auth/register.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
                <input class="m-input" type="text"     name="fname"    placeholder="First name"    required />
                <input class="m-input" type="text"     name="lname"    placeholder="Last name"     required />
                <input class="m-input" type="email"    name="email"    placeholder="Email address" required />
                <input class="m-input" type="password" name="password" placeholder="Password"      required minlength="8" />
                <button class="m-submit" type="submit">Create account</button>
            </form>
            <div class="m-switch">Already have an account? <a onclick="switchPane('signin')">Sign in</a></div>
        </div>

    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<!-- ═══════════════════════════════════════
     SHARED JS (modal, toast, dropdown)
     Lives in layout so every page gets it
═══════════════════════════════════════ -->
<script>
    /* ── Modal ── */
    function openModal(p) {
        document.getElementById('modalBg').classList.add('open');
        switchPane(p);
    }
    function closeModal() {
        document.getElementById('modalBg').classList.remove('open');
    }
    function bgClick(e) {
        if (e.target === document.getElementById('modalBg')) closeModal();
    }
    function switchPane(p) {
        document.getElementById('signinPane').style.display = p === 'signin' ? 'block' : 'none';
        document.getElementById('signupPane').style.display = p === 'signup' ? 'block' : 'none';
    }

    /* ── User dropdown ── */
    function toggleDropdown() {
        const d = document.getElementById('userDropdown');
        d.style.display = d.style.display === 'none' ? 'block' : 'none';
    }
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('userDropBtn');
        const drop = document.getElementById('userDropdown');
        if (btn && drop && !btn.contains(e.target) && !drop.contains(e.target)) {
            drop.style.display = 'none';
        }
    });

    /* ── Toast ── */
    let _toastTimer;
    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(_toastTimer);
        _toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
    }

    /* ── Cat nav active (client-side fallback) ── */
    document.querySelectorAll('.cat-link').forEach(l => {
        l.addEventListener('click', () => {
            document.querySelectorAll('.cat-link').forEach(x => x.classList.remove('active'));
            l.classList.add('active');
        });
    });

    /* ── Flash message from PHP session ── */
    <?php if (!empty($_SESSION['flash'])): ?>
        showToast(<?= json_encode($_SESSION['flash']) ?>);
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
</script>
