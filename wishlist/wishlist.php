<?php
session_start();
if (!isset($_SESSION['account_id'])) {
    header('Location: ../auth/signin.php?redirect=wishlist/wishlist.php');
    exit;
}

require_once('../config/db.php');

$userId = $_SESSION['account_id'];

$items = [];
$stmt = $conn->prepare("
    SELECT w.Wish_ProdID, w.Wish_DateAdd, w.Wish_PriceAdd,
           p.Prod_Title, p.Prod_Price, p.Prod_Image, p.Prod_Stock, p.Prod_Status, p.Prod_CatID,
           c.Cat_Name
    FROM Wishlist w
    JOIN Product p  ON w.Wish_ProdID = p.Prod_ID
    LEFT JOIN Category c ON p.Prod_CatID = c.Cat_ID
    WHERE w.Wish_UserID = ?
    ORDER BY w.Wish_DateAdd DESC
");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

$title    = 'Watchlist';
$basePath = '../';
include('../layout/layout.php');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
.wl-wrap {
    max-width: 1000px;
    margin: 28px auto;
    padding: 0 20px;
}
.wl-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 6px;
}
.wl-count {
    font-size: 14px;
    color: var(--muted);
    margin-bottom: 24px;
}
.wl-empty {
    text-align: center;
    padding: 64px 20px;
    background: #f7f7f7;
    border-radius: 12px;
}
.wl-empty i { font-size: 56px; color: var(--muted); display: block; margin-bottom: 16px; }
.wl-empty h3 { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
.wl-empty a {
    display: inline-block;
    margin-top: 16px;
    background: var(--blue);
    color: #fff;
    padding: 12px 28px;
    border-radius: 24px;
    font-weight: 600;
    text-decoration: none;
}

.wl-item {
    display: flex;
    gap: 20px;
    padding: 20px 0;
    border-bottom: 1px solid var(--border);
    align-items: flex-start;
}
.wl-item:last-child { border-bottom: none; }
.wl-img {
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
.wl-img img { width: 100%; height: 100%; object-fit: cover; }
.wl-img i { font-size: 3rem; color: var(--muted); }
.wl-info { flex: 1; min-width: 0; }
.wl-item-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--blue);
    text-decoration: none;
    display: block;
    margin-bottom: 4px;
}
.wl-item-title:hover { text-decoration: underline; }
.wl-cat { font-size: 13px; color: var(--muted); margin-bottom: 8px; }
.wl-price { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
.wl-price-note { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
.wl-unavailable { font-size: 13px; color: var(--red); font-weight: 600; margin-bottom: 8px; }
.wl-date { font-size: 12px; color: var(--muted); margin-top: auto; }
.wl-actions { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
.wl-btn {
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--blue);
    background: #fff;
    color: var(--blue);
    transition: background 0.15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.wl-btn:hover { background: #f0f7ff; }
.wl-btn-remove {
    border-color: var(--border);
    color: var(--muted);
}
.wl-btn-remove:hover { background: #f7f7f7; color: var(--red); border-color: var(--red); }
</style>

<div class="wl-wrap">
    <h1 class="wl-title">Watchlist</h1>
    <div class="wl-count"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></div>

    <?php if (empty($items)): ?>
        <div class="wl-empty">
            <i class="bi bi-heart"></i>
            <h3>Your Watchlist is empty</h3>
            <p style="color:var(--muted);">Save items you're interested in to keep track of them.</p>
            <a href="../buyer/dashboard.php">Continue shopping</a>
        </div>
    <?php else: ?>
        <div id="wlList">
        <?php foreach ($items as $item): ?>
            <div class="wl-item" id="wl-<?= htmlspecialchars($item['Wish_ProdID']) ?>">
                <a href="../product/product.php?id=<?= urlencode($item['Wish_ProdID']) ?>" class="wl-img">
                    <?php if (!empty($item['Prod_Image'])): ?>
                        <img src="<?= htmlspecialchars($item['Prod_Image']) ?>" alt="<?= htmlspecialchars($item['Prod_Title']) ?>">
                    <?php else: ?>
                        <i class="bi bi-box"></i>
                    <?php endif; ?>
                </a>
                <div class="wl-info">
                    <a href="../product/product.php?id=<?= urlencode($item['Wish_ProdID']) ?>" class="wl-item-title">
                        <?= htmlspecialchars($item['Prod_Title']) ?>
                    </a>
                    <?php if (!empty($item['Cat_Name'])): ?>
                        <div class="wl-cat"><?= htmlspecialchars($item['Cat_Name']) ?></div>
                    <?php endif; ?>

                    <?php if ($item['Prod_Status'] !== 'active'): ?>
                        <div class="wl-unavailable">This item is no longer available</div>
                    <?php else: ?>
                        <div class="wl-price">₱<?= number_format($item['Prod_Price'], 2) ?></div>
                        <?php if (abs($item['Prod_Price'] - $item['Wish_PriceAdd']) > 0.01): ?>
                            <div class="wl-price-note">
                                Was ₱<?= number_format($item['Wish_PriceAdd'], 2) ?> when saved
                                <?php if ($item['Prod_Price'] < $item['Wish_PriceAdd']): ?>
                                    &nbsp;<span style="color:#2e7d32;font-weight:600;">Price dropped!</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="wl-actions">
                        <a href="../product/product.php?id=<?= urlencode($item['Wish_ProdID']) ?>" class="wl-btn">
                            <i class="bi bi-eye"></i> View item
                        </a>
                        <button class="wl-btn wl-btn-remove"
                                onclick="removeItem('<?= $item['Wish_ProdID'] ?>')">
                            <i class="bi bi-heart-slash"></i> Remove
                        </button>
                    </div>
                    <div class="wl-date">Saved on <?= date('F j, Y', strtotime($item['Wish_DateAdd'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include('../layout/footer.php'); ?>

<script>
function removeItem(prodId) {
    fetch('remove.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'prod_id=' + encodeURIComponent(prodId)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { showToast(data.message || 'Error'); return; }
        document.getElementById('wl-' + prodId)?.remove();
        const count = document.querySelectorAll('.wl-item').length;
        document.querySelector('.wl-count').textContent = count + ' item' + (count !== 1 ? 's' : '');
        if (count === 0) location.reload();
    });
}
</script>
