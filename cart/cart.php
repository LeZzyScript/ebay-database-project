<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php?redirect=cart/cart.php');
    exit;
}

require_once('../config/firebase.php');

$uid = $_SESSION['firebase_uid'];

// Fetch cart items
$cartItems = getData("carts/{$uid}/items");
$items = [];

if ($cartItems) {
    foreach ($cartItems as $prodId => $cartData) {
        $product = getData("products/{$prodId}");
        if ($product) {
            $items[] = [
                'Cart_ProdID' => $prodId,
                'Cart_Qty' => $cartData['quantity'] ?? 1,
                'Prod_Title' => $product['title'] ?? '',
                'Prod_Price' => $product['price'] ?? 0,
                'Prod_Image' => $product['image'] ?? '',
                'Prod_Stock' => $product['stock'] ?? 0,
                'Prod_Status' => $product['status'] ?? 'active'
            ];
        }
    }
}

$total = array_sum(array_map(fn($i) => $i['Prod_Price'] * $i['Cart_Qty'], $items));

$title    = 'Shopping Cart';
$basePath = '../';
include('../layout/layout.php');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.cart-wrap {
    max-width: 1100px;
    margin: 28px auto;
    padding: 0 20px;
    display: flex;
    gap: 28px;
    align-items: flex-start;
}
.cart-main { flex: 1; min-width: 0; }
.cart-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 20px;
}
.cart-empty {
    text-align: center;
    padding: 64px 20px;
    background: #f7f7f7;
    border-radius: 12px;
}
.cart-empty i { font-size: 56px; color: var(--muted); display: block; margin-bottom: 16px; }
.cart-empty h3 { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
.cart-empty a {
    display: inline-block;
    margin-top: 16px;
    background: var(--blue);
    color: #fff;
    padding: 12px 28px;
    border-radius: 24px;
    font-weight: 600;
    text-decoration: none;
}

/* Cart item rows */
.cart-item {
    display: flex;
    gap: 20px;
    padding: 20px 0;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
}
.cart-item:last-child { border-bottom: none; }
.cart-item-img {
    width: 120px;
    height: 120px;
    flex-shrink: 0;
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fafafa;
    text-decoration: none;
}
.cart-item-img img { width: 100%; height: 100%; object-fit: cover; }
.cart-item-img i { font-size: 3rem; color: var(--muted); }
.cart-item-info { flex: 1; min-width: 0; }
.cart-item-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--blue);
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
}
.cart-item-title:hover { text-decoration: underline; }
.cart-item-cond { font-size: 13px; color: var(--muted); margin-bottom: 12px; }
.cart-item-price { font-size: 20px; font-weight: 700; margin-bottom: 12px; }
.cart-qty-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.qty-btn {
    width: 32px; height: 32px;
    border: 1px solid var(--border);
    border-radius: 50%;
    background: #fff;
    font-size: 18px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: border-color 0.15s;
}
.qty-btn:hover { border-color: var(--text); }
.qty-display {
    min-width: 32px;
    text-align: center;
    font-weight: 600;
    font-size: 15px;
}
.cart-item-remove {
    background: none;
    border: none;
    color: var(--muted);
    font-size: 13px;
    cursor: pointer;
    margin-left: 8px;
    text-decoration: underline;
    padding: 0;
}
.cart-item-remove:hover { color: var(--red); }
.cart-item-subtotal {
    font-size: 14px;
    color: var(--muted);
    margin-top: 8px;
}
.cart-out-of-stock {
    font-size: 13px;
    color: var(--red);
    font-weight: 600;
    margin-top: 4px;
}

/* Summary sidebar */
.cart-summary {
    width: 300px;
    flex-shrink: 0;
    background: #f7f7f7;
    border-radius: 12px;
    padding: 24px;
    position: sticky;
    top: 20px;
}
.cart-summary h3 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    margin-bottom: 10px;
}
.summary-divider {
    border: none;
    border-top: 1px solid var(--border);
    margin: 14px 0;
}
.summary-total {
    display: flex;
    justify-content: space-between;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
}
.checkout-btn {
    display: block;
    width: 100%;
    background: var(--blue);
    color: #fff;
    border: none;
    border-radius: 24px;
    padding: 14px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: opacity 0.2s;
}
.checkout-btn:hover { opacity: 0.9; color: #fff; }
.checkout-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.summary-note {
    font-size: 12px;
    color: var(--muted);
    text-align: center;
    margin-top: 12px;
}

@media (max-width: 760px) {
    .cart-wrap { flex-direction: column; }
    .cart-summary { width: 100%; position: static; }
}
</style>

<div class="cart-wrap">
    <div class="cart-main">
        <h1 class="cart-title">Shopping Cart</h1>

        <?php if (empty($items)): ?>
            <div class="cart-empty">
                <i class="bi bi-cart-x"></i>
                <h3>Your cart is empty</h3>
                <p style="color:var(--muted);">Browse items and add them to your cart.</p>
                <a href="../buyer/dashboard.php">Continue shopping</a>
            </div>
        <?php else: ?>
            <div id="cartItemList">
            <?php foreach ($items as $item): ?>
                <div class="cart-item" id="item-<?= htmlspecialchars($item['Cart_ProdID']) ?>">
                    <a href="../product/product.php?id=<?= urlencode($item['Cart_ProdID']) ?>" class="cart-item-img">
                        <?php if (!empty($item['Prod_Image'])): ?>
                            <img src="<?= htmlspecialchars($item['Prod_Image']) ?>" alt="<?= htmlspecialchars($item['Prod_Title']) ?>">
                        <?php else: ?>
                            <i class="bi bi-box"></i>
                        <?php endif; ?>
                    </a>
                    <div class="cart-item-info">
                        <a href="../product/product.php?id=<?= urlencode($item['Cart_ProdID']) ?>" class="cart-item-title">
                            <?= htmlspecialchars($item['Prod_Title']) ?>
                        </a>
                        <div class="cart-item-cond">Brand New</div>
                        <div class="cart-item-price">₱<?= number_format($item['Prod_Price'], 2) ?></div>

                        <?php if ($item['Prod_Status'] !== 'active' || $item['Prod_Stock'] < 1): ?>
                            <div class="cart-out-of-stock">This item is no longer available</div>
                        <?php else: ?>
                            <div class="cart-qty-row">
                                <button class="qty-btn" onclick="changeQty('<?= $item['Cart_ProdID'] ?>', -1)" title="Decrease">−</button>
                                <span class="qty-display" id="qty-<?= $item['Cart_ProdID'] ?>"><?= $item['Cart_Qty'] ?></span>
                                <button class="qty-btn" onclick="changeQty('<?= $item['Cart_ProdID'] ?>', 1)" title="Increase"
                                    <?= $item['Cart_Qty'] >= $item['Prod_Stock'] ? 'disabled' : '' ?>>+</button>
                                <button class="cart-item-remove" onclick="removeItem('<?= $item['Cart_ProdID'] ?>')">Remove</button>
                            </div>
                            <div class="cart-item-subtotal" id="sub-<?= $item['Cart_ProdID'] ?>">
                                Subtotal: ₱<?= number_format($item['Prod_Price'] * $item['Cart_Qty'], 2) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($items)): ?>
    <div class="cart-summary" id="cartSummary">
        <h3>Order Summary</h3>
        <div class="summary-row">
            <span>Items (<?= array_sum(array_column($items, 'Cart_Qty')) ?>)</span>
            <span id="summarySubtotal">₱<?= number_format($total, 2) ?></span>
        </div>
        <div class="summary-row">
            <span>Shipping</span>
            <span style="color:#2e7d32;">Free</span>
        </div>
        <hr class="summary-divider">
        <div class="summary-total">
            <span>Total</span>
            <span id="summaryTotal">₱<?= number_format($total, 2) ?></span>
        </div>
        <a href="../checkout/checkout.php" class="checkout-btn">Go to checkout</a>
        <p class="summary-note">Shipping and taxes calculated at checkout.</p>
    </div>
    <?php endif; ?>
</div>

<?php include('../layout/footer.php'); ?>

<script>
const PRICES = <?= json_encode(array_column($items, 'Prod_Price', 'Cart_ProdID')) ?>;

function post(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data).toString()
    }).then(r => r.json());
}

function changeQty(prodId, delta) {
    const qtyEl = document.getElementById('qty-' + prodId);
    const current = parseInt(qtyEl.textContent);
    const newQty = current + delta;

    post('update.php', {prod_id: prodId, qty: newQty})
        .then(data => {
            if (!data.success) { showToast(data.message || 'Error'); return; }
            if (data.new_qty <= 0) {
                document.getElementById('item-' + prodId)?.remove();
            } else {
                qtyEl.textContent = data.new_qty;
                const sub = document.getElementById('sub-' + prodId);
                if (sub) sub.textContent = 'Subtotal: ₱' + (PRICES[prodId] * data.new_qty).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
            }
            updateCartBadge(data.cart_count);
            recalcTotal();
        });
}

function removeItem(prodId) {
    post('remove.php', {prod_id: prodId})
        .then(data => {
            if (!data.success) { showToast(data.message || 'Error'); return; }
            document.getElementById('item-' + prodId)?.remove();
            updateCartBadge(data.cart_count);
            recalcTotal();
            if (data.cart_count === 0) location.reload();
        });
}

function updateCartBadge(count) {
    const badge = document.getElementById('cartBadge');
    if (badge) badge.textContent = count;
}

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('.cart-item').forEach(row => {
        const id = row.id.replace('item-', '');
        const qty = parseInt(document.getElementById('qty-' + id)?.textContent ?? 0);
        total += (PRICES[id] ?? 0) * qty;
    });
    const fmt = total.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    const sub = document.getElementById('summarySubtotal');
    const tot = document.getElementById('summaryTotal');
    if (sub) sub.textContent = '₱' + fmt;
    if (tot) tot.textContent = '₱' + fmt;
}
</script>
