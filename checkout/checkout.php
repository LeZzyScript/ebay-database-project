<?php
session_start();

$basePath = '../';
// Require login
if (!isset($_SESSION['firebase_uid'])) {
    $redirect = urlencode('checkout/checkout.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    header('Location: ../auth/signin.php?redirect=' . $redirect);
    exit;
}

require_once('../config/firebase.php');
$uid = $_SESSION['firebase_uid'];

$getSellerName = function($sellerId) {
    if (empty($sellerId)) return 'Unknown Seller';
    $userData = getData("users/{$sellerId}");
    if ($userData && isset($userData['sellerData'])) {
        return $userData['profile']['accountName'] ?? trim(($userData['profile']['firstName'] ?? '') . ' ' . ($userData['profile']['lastName'] ?? '')) ?: 'Unknown Seller';
    }
    $allUsers = getData('users');
    if ($allUsers) {
        foreach ($allUsers as $uKey => $uData) {
            if (isset($uData['sellerData']['sellerId']) && $uData['sellerData']['sellerId'] === $sellerId) {
                return $uData['profile']['accountName'] ?? trim(($uData['profile']['firstName'] ?? '') . ' ' . ($uData['profile']['lastName'] ?? '')) ?: 'Unknown Seller';
            }
        }
    }
    return 'Unknown Seller';
};

// 1. Handle Order Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buyNowId = $_POST['buy_now'] ?? '';
    $buyNowQty = (int)($_POST['qty'] ?? 1);
    $auctionId = $_POST['auction_id'] ?? '';
    
    // Fetch Items to purchase
    $items = [];
    if ($auctionId) {
        // Fetch auction winner info
        $auction = getData("auctions/{$auctionId}");
        
        // Auto-close if active but past end date
        if ($auction && ($auction['status'] ?? '') === 'active' && strtotime($auction['endDate'] ?? '') < time()) {
            updateData("auctions/{$auctionId}/status", 'ended');
            $productId = $auction['productId'] ?? '';
            if ($productId) {
                updateData("products/{$productId}/auctionData/status", 'ended');
            }
            $auction['status'] = 'ended';
        }

        if ($auction && ($auction['status'] ?? '') === 'ended') {
            $productId = $auction['productId'] ?? '';
            $product = getData("products/{$productId}");
            if ($product) {
                // Get user's highest bid
                $bidsData = getData("auctions/{$auctionId}/bids");
                $myTopBid = 0;
                if ($bidsData) {
                    foreach ($bidsData as $bidData) {
                        if ($bidData['userId'] === $uid && $bidData['amount'] > $myTopBid) {
                            $myTopBid = $bidData['amount'];
                        }
                    }
                }
                
                // Check if they won
                if ($myTopBid >= ($auction['currentHighBid'] ?? 0)) {
                    $product['Checkout_Qty'] = 1;
                    $product['price'] = $myTopBid;
                    $product['Prod_Price'] = $myTopBid;
                    $product['Prod_ID'] = $productId;
                    $items[] = $product;
                }
            }
        }
    } elseif ($buyNowId) {
        $product = getData("products/{$buyNowId}");
        if ($product && ($product['status'] ?? '') === 'active') {
            if (($product['stock'] ?? 0) >= $buyNowQty) {
                $product['Checkout_Qty'] = $buyNowQty;
                $product['Prod_ID'] = $buyNowId;
                $items[] = $product;
            }
        }
    } else {
        $cartItems = getData("carts/{$uid}/items");
        if ($cartItems) {
            foreach ($cartItems as $prodId => $cartData) {
                $product = getData("products/{$prodId}");
                if ($product && ($product['status'] ?? '') === 'active') {
                    $qty = $cartData['quantity'] ?? 1;
                    if (($product['stock'] ?? 0) >= $qty) {
                        $product['Checkout_Qty'] = $qty;
                        $product['Prod_ID'] = $prodId;
                        $items[] = $product;
                    }
                }
            }
        }
    }
    
    if (empty($items)) {
        $_SESSION['flash'] = "Items in your order are out of stock or unavailable.";
        header('Location: ../cart/cart.php');
        exit;
    }

    // Calculations
    $subtotal = 0;
    foreach ($items as $it) {
        $subtotal += ($it['price'] ?? 0) * $it['Checkout_Qty'];
    }
    $shipping = 192.46; // Hardcoded shipping fee
    $total = $subtotal + $shipping;

    // Fetch user shipping address
    $user = getData("users/{$uid}");
    $shipAdd = $user['profile']['address'] ?? '';
    
    // Generate Order ID
    $orderId = generateId('ORD');
    $today = date('Y-m-d');
    
    try {
        // Insert Order
        setData("orders/{$orderId}", [
            'userId' => $uid,
            'date' => $today,
            'total' => $total,
            'paymentStatus' => 'Paid',
            'shippingAddress' => $shipAdd,
            'status' => 'Pending'
        ]);

        
        // Insert Order Items and Update Stock
        foreach ($items as $it) {
            $itemId = generateId('ITM');
            $pId = $it['Prod_ID'];
            $qty = $it['Checkout_Qty'];
            $price = $it['price'] ?? 0;
            $sub = $price * $qty;
            
            setData("orders/{$orderId}/items/{$itemId}", [
                'productId' => $pId,
                'quantity' => $qty,
                'price' => $price,
                'subtotal' => $sub
            ]);
            
            // Update product stock
            $newStock = ($it['stock'] ?? 0) - $qty;
            updateData("products/{$pId}/stock", $newStock);
        }
        
        // Clear Cart if not Buy It Now or Auction
        if (!$buyNowId && !$auctionId) {
            setData("carts/{$uid}/items", []);
            $_SESSION['cart_count'] = 0;
        }
        
        // Mark auction as paid if it's an auction
        if ($auctionId) {
            updateData("auctions/{$auctionId}/status", 'paid');
            // Sync status to products' copy
            $auction = getData("auctions/{$auctionId}");
            $productId = $auction['productId'] ?? '';
            if ($productId) {
                updateData("products/{$productId}/auctionData/status", 'paid');
            }
        }
        
        $_SESSION['flash'] = "Order placed successfully! Order ID: " . $orderId;
        header("Location: ../buyer/dashboard.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['flash'] = "Checkout failed: " . $e->getMessage();
        header("Location: ../cart/cart.php");
        exit;
    }
}

// 2. Fetch Data for Display
$buyNowId = $_GET['buy_now'] ?? '';
$buyNowQty = (int)($_GET['qty'] ?? 1);
$auctionId = $_GET['auction_id'] ?? '';

$items = [];
$sellers = []; // Group items by seller for display

if ($auctionId) {
    $auction = getData("auctions/{$auctionId}");
    
    // Auto-close if active but past end date
    if ($auction && ($auction['status'] ?? '') === 'active' && strtotime($auction['endDate'] ?? '') < time()) {
        updateData("auctions/{$auctionId}/status", 'ended');
        $productId = $auction['productId'] ?? '';
        if ($productId) {
            updateData("products/{$productId}/auctionData/status", 'ended');
        }
        $auction['status'] = 'ended';
    }

    if ($auction && ($auction['status'] ?? '') === 'ended') {
        $productId = $auction['productId'] ?? '';
        $product = getData("products/{$productId}");
        if ($product) {
            // Get user's highest bid
            $bidsData = getData("auctions/{$auctionId}/bids");
            $myTopBid = 0;
            if ($bidsData) {
                foreach ($bidsData as $bidData) {
                    if ($bidData['userId'] === $uid && $bidData['amount'] > $myTopBid) {
                        $myTopBid = $bidData['amount'];
                    }
                }
            }
            
            // Get seller name
            $sellerName = $getSellerName($product['sellerId'] ?? '');
            
            $product['Checkout_Qty'] = 1;
            $product['price'] = $myTopBid;
            $product['Prod_Price'] = $myTopBid;
            $product['Prod_ID'] = $productId;
            $product['Seller_Name'] = $sellerName;
            $items[] = $product;
            $sellers[$sellerName][] = $product;
        }
    }
} elseif ($buyNowId) {
    $product = getData("products/{$buyNowId}");
    if ($product && ($product['status'] ?? '') === 'active') {
        // Get seller name
        $sellerName = $getSellerName($product['sellerId'] ?? '');
        
        $product['Checkout_Qty'] = $buyNowQty;
        $product['Prod_ID'] = $buyNowId;
        $product['Seller_Name'] = $sellerName;
        $items[] = $product;
        $sellers[$sellerName][] = $product;
    }
} else {
    $cartItems = getData("carts/{$uid}/items");
    if ($cartItems) {
        foreach ($cartItems as $prodId => $cartData) {
            $product = getData("products/{$prodId}");
            if ($product && ($product['status'] ?? '') === 'active') {
                // Get seller name
                $sellerName = $getSellerName($product['sellerId'] ?? '');
                
                $product['Checkout_Qty'] = $cartData['quantity'] ?? 1;
                $product['Prod_ID'] = $prodId;
                $product['Seller_Name'] = $sellerName;
                $items[] = $product;
                $sellers[$sellerName][] = $product;
            }
        }
    }
}

if (empty($items)) {
    header('Location: ../cart/cart.php');
    exit;
}

// Fetch user data
$user = getData("users/{$uid}");

$subtotal = 0;
$totalQty = 0;
foreach ($items as $it) {
    $subtotal += ($it['price'] ?? 0) * $it['Checkout_Qty'];
    $totalQty += $it['Checkout_Qty'];
}
$shipping = 192.46; // Hardcoded shipping fee
$total = $subtotal + $shipping;

$title = "Checkout";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eBay Checkout</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --blue:       #3665f3;
            --text:       #191919;
            --muted:      #767676;
            --border:     #e5e5e5;
            --bg:         #f7f7f7;
            --font: 'DM Sans', sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font); color: var(--text); background: #fff; line-height: 1.5; }
        
        /* Header */
        header { padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-area img { height: 40px; }
        .logo-area h1 { font-size: 24px; font-weight: 700; margin: 0; }
        .hdr-link { font-size: 13px; color: var(--text); text-decoration: none; }
        .hdr-link:hover { text-decoration: underline; }

        /* Container */
        .co-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; display: flex; gap: 40px; align-items: flex-start; }
        .co-left { flex: 1; }
        .co-right { width: 360px; flex-shrink: 0; position: sticky; top: 30px; }

        /* Banner */
        .co-banner { background: #e6f2ff; border-radius: 8px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
        .co-banner-left { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 600; }
        .co-banner-left img { height: 20px; }
        .co-banner-right { font-size: 14px; font-weight: 700; color: #0053a0; cursor: pointer; }

        /* Sections */
        .co-sec { margin-bottom: 32px; padding-bottom: 32px; border-bottom: 1px solid var(--border); }
        .co-sec h2 { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
        
        /* Payment Options */
        .pay-opt { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; font-size: 14px; cursor: pointer; }
        .pay-opt input[type="radio"] { width: 18px; height: 18px; accent-color: var(--blue); cursor: pointer; flex-shrink:0; }
        .pay-opt img { height: 24px; }
        .pay-label { flex: 1; }
        .pay-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }

        /* Card Details Form */
        #cardDetails {
            display: none;
            background: #f9f9f9;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            margin: 4px 0 16px 34px;
        }
        .card-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
        .card-row.full { grid-template-columns: 1fr; }
        .card-group { display: flex; flex-direction: column; gap: 5px; }
        .card-group label { font-size: 12px; font-weight: 600; color: var(--text); }
        .card-group input {
            border: 1.5px solid #ddd;
            border-radius: 7px;
            padding: 10px 12px;
            font-size: 14px;
            font-family: var(--font);
            outline: none;
            transition: border-color .15s;
            background: #fff;
        }
        .card-group input:focus { border-color: var(--blue); }
        .card-group input.input-error { border-color: #E53238; }
        .card-group .field-err { font-size: 11px; color: #E53238; display: none; margin-top: 2px; }
        .card-group.has-error .field-err { display: block; }
        .card-group.has-error input { border-color: #E53238; }
        .card-icons { display: flex; align-items: center; gap: 6px; margin-top: 8px; }
        .card-icons span {
            display: inline-flex; align-items: center; justify-content: center;
            width: 38px; height: 24px; border: 1px solid #ddd; border-radius: 4px;
            font-size: 10px; font-weight: 700; color: var(--muted); background: #fff;
        }
        
        /* Shipping */
        .ship-info { font-size: 14px; color: var(--text); line-height: 1.6; }
        .ship-change { font-size: 14px; color: var(--text); text-decoration: underline; cursor: pointer; margin-top: 12px; display: inline-block; }

        /* Review Order */
        .seller-hdr { font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
        .seller-img { width: 24px; height: 24px; border-radius: 50%; background: #e5e5e5; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .item-row { display: flex; gap: 16px; margin-bottom: 24px; }
        .item-img { width: 100px; height: 100px; border: 1px solid var(--border); border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .item-img img { width: 100%; height: 100%; object-fit: contain; }
        .item-details { flex: 1; }
        .item-title { font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 8px; }
        .item-qty { font-size: 13px; color: var(--muted); margin-bottom: 4px; }
        .item-price-wrap { text-align: right; }
        .item-price { font-size: 16px; font-weight: 700; }

        /* Right Summary */
        .summary-card { background: var(--bg); border-radius: 12px; padding: 24px; }
        .summary-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; }
        .summary-row { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 12px; }
        .summary-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; margin: 20px 0; padding-top: 20px; border-top: 1px solid var(--border); }
        .summary-legal { font-size: 11px; color: var(--muted); margin-bottom: 20px; }
        .summary-legal a { color: var(--blue); text-decoration: none; }
        
        .btn-confirm { width: 100%; background: #c7c7c7; color: #fff; border: none; padding: 14px; border-radius: 24px; font-size: 16px; font-weight: 600; cursor: not-allowed; transition: background 0.2s; margin-bottom: 16px; }
        .btn-confirm.active { background: var(--blue); cursor: pointer; }
        .btn-confirm.active:hover { background: #2b55d9; }
        
        .guarantee { display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 12px; font-weight: 600; color: #0053a0; }
        
        @media (max-width: 900px) {
            .co-container { flex-direction: column; }
            .co-right { width: 100%; position: static; }
        }
    </style>
</head>
<body>

<header>
    <div class="logo-area">
        <a href="../index.php"><img src="../assets/img/Ebay.png" alt="eBay"></a>
        <h1>Checkout</h1>
    </div>
    <a href="#" class="hdr-link">How do you like our checkout? Give us feedback</a>
</header>

<div class="co-container">
    <div class="co-left">
        <!-- Banner -->
        <div class="co-banner">
            <div class="co-banner-left">
                <i class="bi bi-paypal" style="color:#003087;font-size:20px;"></i>
                Pay with PayPal and shop internationally with ease.
            </div>
            <div class="co-banner-right">Select PayPal</div>
        </div>

        <!-- Pay With -->
        <div class="co-sec">
            <h2>Pay with</h2>
            
            <label class="pay-opt">
                <input type="radio" name="payment" value="paypal" onchange="enableCheckout(); toggleCardForm(false)">
                <i class="bi bi-paypal" style="color:#003087;font-size:20px;"></i>
                <div class="pay-label">PayPal</div>
            </label>
            
            <label class="pay-opt">
                <input type="radio" name="payment" value="card" onchange="enableCheckout(); toggleCardForm(this.checked)">
                <i class="bi bi-credit-card" style="font-size:20px;"></i>
                <div class="pay-label">Add new card</div>
            </label>

            <!-- Card Details Form (shown only when card is selected) -->
            <div id="cardDetails">
                <div class="card-icons">
                    <span style="color:#1a1f71;font-size:9px;">VISA</span>
                    <span style="color:#eb001b;letter-spacing:-1px;">MC</span>
                    <span style="color:#2557d6;font-size:8px;">AMEX</span>
                </div>
                <div style="margin-top:14px;">
                    <div class="card-row full">
                        <div class="card-group" id="grp-name">
                            <label for="card_name">Cardholder Name</label>
                            <input type="text" id="card_name" placeholder="Name as it appears on card" autocomplete="cc-name">
                            <span class="field-err">Cardholder name is required.</span>
                        </div>
                    </div>
                    <div class="card-row full" style="margin-top:0;">
                        <div class="card-group" id="grp-num">
                            <label for="card_number">Card Number</label>
                            <input type="text" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="cc-number" oninput="formatCardNumber(this)">
                            <span class="field-err">Please enter a valid 16-digit card number.</span>
                        </div>
                    </div>
                    <div class="card-row">
                        <div class="card-group" id="grp-exp">
                            <label for="card_expiry">Expiration Date</label>
                            <input type="text" id="card_expiry" placeholder="MM / YY" maxlength="7" autocomplete="cc-exp" oninput="formatExpiry(this)">
                            <span class="field-err">Enter a valid expiry (MM/YY).</span>
                        </div>
                        <div class="card-group" id="grp-cvv">
                            <label for="card_cvv">Security Code (CVV)</label>
                            <input type="text" id="card_cvv" placeholder="CVV" maxlength="4" autocomplete="cc-csc" oninput="this.value=this.value.replace(/\D/g,'')">
                            <span class="field-err">Enter a valid CVV (3–4 digits).</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <label class="pay-opt">
                <input type="radio" name="payment" value="gpay" onchange="enableCheckout(); toggleCardForm(false)">
                <i class="bi bi-google" style="font-size:18px;"></i>
                <div class="pay-label">Google Pay</div>
            </label>

            <label class="pay-opt">
                <input type="radio" name="payment" value="paypal_credit" onchange="enableCheckout(); toggleCardForm(false)">
                <i class="bi bi-paypal" style="color:#003087;font-size:20px;"></i>
                <div class="pay-label">
                    No Interest if paid in full in 6 months.
                    <div class="pay-sub">Apply now. <a href="#" style="color:var(--text)">See terms</a></div>
                </div>
            </label>
        </div>

        <!-- Ship To -->
        <div class="co-sec">
            <h2>Ship to</h2>
            <div class="ship-info">
                <?= htmlspecialchars(($user['profile']['firstName'] ?? '') . ' ' . ($user['profile']['lastName'] ?? '')) ?><br>
                <?= htmlspecialchars($user['profile']['address'] ?? '') ?><br>
                Philippines<br>
                <?= htmlspecialchars($user['profile']['contact'] ?? '') ?>
            </div>
            <div class="ship-change">Change</div>
        </div>

        <!-- Review Order -->
        <div class="co-sec" style="border-bottom:none;">
            <h2>Review order</h2>
            
            <?php foreach ($sellers as $sellerName => $sellerItems): ?>
                <div class="seller-hdr">
                    <div class="seller-img"><i class="bi bi-person"></i></div>
                    <?= htmlspecialchars($sellerName ?: 'Unknown Seller') ?>
                </div>
                
                <?php foreach ($sellerItems as $it): ?>
                    <div class="item-row">
                        <div class="item-img">
                            <?php if (!empty($it['image'])): ?>
                                <img src="<?= htmlspecialchars($it['image']) ?>" alt="Product">
                            <?php else: ?>
                                <i class="bi bi-box" style="font-size:3rem;color:var(--muted);"></i>
                            <?php endif; ?>
                        </div>
                        <div class="item-details">
                            <div class="item-title"><?= htmlspecialchars($it['title'] ?? '') ?></div>
                            <div class="item-qty">Qty <?= $it['Checkout_Qty'] ?></div>
                        </div>
                        <div class="item-price-wrap">
                            <div class="item-price">₱<?= number_format(($it['price'] ?? 0) * $it['Checkout_Qty'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            
        </div>
    </div>

    <!-- Right Summary -->
    <div class="co-right">
        <div class="summary-card">
            <h2 class="summary-title">Order Summary</h2>
            <div class="summary-row">
                <span>Item (<?= $totalQty ?>)</span>
                <span>₱<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span>₱<?= number_format($shipping, 2) ?></span>
            </div>
            
            <div class="summary-total">
                <span>Order total</span>
                <span>₱<?= number_format($total, 2) ?></span>
            </div>
            
            <div class="summary-legal">
                With this purchase you agree to the <a href="#">eBay International Shipping terms and conditions.</a>
            </div>
            
            <form method="POST" action="checkout.php" id="checkoutForm">
                <?php if (!empty($auctionId)): ?>
                    <input type="hidden" name="auction_id" value="<?= htmlspecialchars($auctionId) ?>">
                <?php else: ?>
                    <input type="hidden" name="buy_now" value="<?= htmlspecialchars($buyNowId) ?>">
                    <input type="hidden" name="qty" value="<?= $buyNowQty ?>">
                <?php endif; ?>
                
                <button type="button" class="btn-confirm" id="confirmBtn" onclick="submitOrder()">Confirm and pay</button>
            </form>
            
            <div style="text-align:center; font-size:13px; color:var(--text); margin-bottom:12px;" id="selectMsg">
                Select a payment method
            </div>
            
            <div class="guarantee">
                <i class="bi bi-shield-check" style="font-size:16px;"></i> Purchase protected by eBay Money Back Guarantee
            </div>
        </div>
    </div>
</div>

<script>
function enableCheckout() {
    document.getElementById('confirmBtn').classList.add('active');
    document.getElementById('selectMsg').style.display = 'none';
}

function toggleCardForm(show) {
    document.getElementById('cardDetails').style.display = show ? 'block' : 'none';
    if (!show) clearCardErrors();
}

function formatCardNumber(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 16);
    input.value = v.match(/.{1,4}/g)?.join(' ') ?? v;
}

function formatExpiry(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 4);
    if (v.length >= 3) v = v.slice(0, 2) + ' / ' + v.slice(2);
    input.value = v;
}

function clearCardErrors() {
    ['grp-name','grp-num','grp-exp','grp-cvv'].forEach(id => {
        document.getElementById(id)?.classList.remove('has-error');
    });
}

function validateCard() {
    let valid = true;
    clearCardErrors();

    const name   = document.getElementById('card_name').value.trim();
    const numRaw = document.getElementById('card_number').value.replace(/\s/g, '');
    const expRaw = document.getElementById('card_expiry').value.replace(/\s/g, '');
    const cvv    = document.getElementById('card_cvv').value.trim();

    if (!name) {
        document.getElementById('grp-name').classList.add('has-error');
        valid = false;
    }

    if (!/^\d{16}$/.test(numRaw)) {
        document.getElementById('grp-num').classList.add('has-error');
        valid = false;
    }

    // Validate expiry MM/YY — not in the past
    const expMatch = expRaw.match(/^(\d{2})\/(\d{2})$/);
    if (!expMatch) {
        document.getElementById('grp-exp').classList.add('has-error');
        valid = false;
    } else {
        const mm = parseInt(expMatch[1], 10);
        const yy = parseInt(expMatch[2], 10) + 2000;
        const now = new Date();
        const expDate = new Date(yy, mm - 1, 1);
        if (mm < 1 || mm > 12 || expDate < new Date(now.getFullYear(), now.getMonth(), 1)) {
            document.getElementById('grp-exp').classList.add('has-error');
            valid = false;
        }
    }

    if (!/^\d{3,4}$/.test(cvv)) {
        document.getElementById('grp-cvv').classList.add('has-error');
        valid = false;
    }

    return valid;
}

function submitOrder() {
    const btn = document.getElementById('confirmBtn');
    if (!btn.classList.contains('active')) return;

    const isCard = document.querySelector('input[name="payment"]:checked')?.value === 'card';
    if (isCard && !validateCard()) return;

    btn.textContent = 'Processing…';
    btn.classList.remove('active');
    document.getElementById('checkoutForm').submit();
}
</script>

</body>
</html>
