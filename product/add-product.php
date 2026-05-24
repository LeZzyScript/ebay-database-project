<?php
session_start();
if (!isset($_SESSION['account_id']) || empty($_SESSION['is_seller'])) {
    header('Location: ../buyer/dashboard.php');
    exit;
}

require_once('../config/db.php');

// -------------------------------------------------------------
// AUTO-SETUP: Create Product table if it doesn't exist
// -------------------------------------------------------------
$conn->query("CREATE TABLE IF NOT EXISTS Product (
    Prod_ID     CHAR(8) PRIMARY KEY,
    Prod_SellID CHAR(8) NOT NULL,
    Prod_CatID  CHAR(8),
    Prod_Title  VARCHAR(100) NOT NULL,
    Prod_Desc   VARCHAR(255) NOT NULL,
    Prod_Price  DECIMAL(10,2) NOT NULL,
    Prod_Image  VARCHAR(255) DEFAULT NULL,
    Prod_Stock  INT NOT NULL DEFAULT 0,
    Prod_Status VARCHAR(10) NOT NULL DEFAULT 'active',
    Prod_DateAdd DATE NOT NULL,
    Prod_DateUpd DATE,
    FOREIGN KEY (Prod_SellID) REFERENCES Seller(Sell_ID),
    FOREIGN KEY (Prod_CatID) REFERENCES Category(Cat_ID)
)");

// Fetch seller's Sell_ID
$sellId = null;
$stmt = $conn->prepare("SELECT Sell_ID FROM Seller WHERE Sell_UserID = ?");
$stmt->bind_param("s", $_SESSION['account_id']);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $sellId = $row['Sell_ID'];
}
$stmt->close();

if (!$sellId) {
    die("Seller profile not found.");
}

$error = '';
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $catId    = trim($_POST['category'] ?? '');
    $desc     = trim($_POST['desc'] ?? '');
    $price    = floatval($_POST['price'] ?? 0);
    $stock    = intval($_POST['stock'] ?? 0);
    $imageUrl = trim($_POST['image_url'] ?? '');
    $today    = date('Y-m-d');

    $isAuction = isset($_POST['is_auction']) ? 1 : 0;
    $aucStartPrice = floatval($_POST['auc_start_price'] ?? 0);
    $aucDuration = intval($_POST['auc_duration'] ?? 7);

    if (!$title || !$catId || !$desc || $price <= 0 || $stock < 0 || ($isAuction && $aucStartPrice <= 0)) {
        $error = "Please fill in all required fields correctly.";
    } else {
        // Generate PRODxxxx
        $prodId = 'PROD0001';
        $res = $conn->query("SELECT Prod_ID FROM Product ORDER BY Prod_ID DESC LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $lastId = $row['Prod_ID'];
            if (preg_match('/^PROD(\d{4})$/', $lastId, $matches)) {
                $nextNum = intval($matches[1]) + 1;
                $prodId = sprintf("PROD%04d", $nextNum);
            }
        }

        $stmt = $conn->prepare("INSERT INTO Product (Prod_ID, Prod_SellID, Prod_CatID, Prod_Title, Prod_Desc, Prod_Price, Prod_Image, Prod_Stock, Prod_DateAdd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssdsss", $prodId, $sellId, $catId, $title, $desc, $price, $imageUrl, $stock, $today);
        
        if ($stmt->execute()) {
            if ($isAuction) {
                // Generate AUCTxxxx
                $aucId = 'AUCT0001';
                $resAuc = $conn->query("SELECT Auc_ID FROM Auction ORDER BY Auc_ID DESC LIMIT 1");
                if ($resAuc && $rowAuc = $resAuc->fetch_assoc()) {
                    $lastAucId = $rowAuc['Auc_ID'];
                    if (preg_match('/^AUCT(\d{4})$/', $lastAucId, $matches)) {
                        $nextAucNum = intval($matches[1]) + 1;
                        $aucId = sprintf("AUCT%04d", $nextAucNum);
                    }
                }
                
                $startDate = date('Y-m-d H:i:s');
                $endDate = date('Y-m-d H:i:s', strtotime("+$aucDuration days"));
                
                $stmtA = $conn->prepare("INSERT INTO Auction (Auc_ID, Auc_ProdID, Auc_StartPrice, Auc_HighBid, Auc_StartDate, Auc_EndDate, Auc_Status) VALUES (?, ?, ?, 0, ?, ?, 'Active')");
                $stmtA->bind_param("ssdss", $aucId, $prodId, $aucStartPrice, $startDate, $endDate);
                $stmtA->execute();
                $stmtA->close();
            }

            $_SESSION['flash'] = "Product '$title' added successfully!";
            header("Location: ../seller/dashboard.php");
            exit;
        } else {
            $error = "Failed to add product: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch categories for the dropdown
$categories = [];
$catRes = $conn->query("SELECT Cat_ID, Cat_Name FROM Category WHERE Cat_Status = 'active' ORDER BY Cat_Name ASC");
if ($catRes) {
    while ($r = $catRes->fetch_assoc()) {
        $categories[] = $r;
    }
}

$pageTitle = "Add a Product";
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
        <h2>List a New Product</h2>
        <p style="color:var(--muted); font-size:14px;">Fill in the details to add an item to your store.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="title">Product Title *</label>
            <input type="text" id="title" name="title" class="form-control" required placeholder="e.g. Vintage Leather Jacket">
        </div>

        <div class="form-group">
            <label for="category">Category *</label>
            <select id="category" name="category" class="form-control" required>
                <option value="">Select a category...</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['Cat_ID'] ?>"><?= htmlspecialchars($c['Cat_Name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="desc">Description *</label>
            <textarea id="desc" name="desc" class="form-control" required placeholder="Describe your product..."></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="price">Price (₱) *</label>
                <input type="number" step="0.01" min="0.01" id="price" name="price" class="form-control" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label for="stock">Available Stock *</label>
                <input type="number" min="0" step="1" id="stock" name="stock" class="form-control" required placeholder="1">
            </div>
        </div>

        <div class="form-group">
            <label for="image_url">Image URL</label>
            <input type="url" id="image_url" name="image_url" class="form-control" placeholder="https://example.com/image.jpg">
            <small style="color:var(--muted); font-size:12px; margin-top:4px; display:block;">Provide a direct link to the product image.</small>
        </div>

        <div class="form-group" style="margin-top: 20px; border-top: 1px solid var(--border); padding-top: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="is_auction" name="is_auction" value="1" onchange="toggleAuctionFields()">
                <span style="font-weight: 700; font-size: 16px;">List as Auction</span>
            </label>
            <p style="color:var(--muted); font-size: 13px; margin-top: 4px;">Allow buyers to place bids on this item.</p>
        </div>

        <div id="auction_fields" style="display: none; background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e0e0e0;">
            <div class="form-row">
                <div class="form-group">
                    <label for="auc_start_price">Starting Bid (₱) *</label>
                    <input type="number" step="0.01" min="0.01" id="auc_start_price" name="auc_start_price" class="form-control" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label for="auc_duration">Auction Duration *</label>
                    <select id="auc_duration" name="auc_duration" class="form-control">
                        <option value="1">1 Day</option>
                        <option value="3">3 Days</option>
                        <option value="5">5 Days</option>
                        <option value="7" selected>7 Days</option>
                        <option value="10">10 Days</option>
                    </select>
                </div>
            </div>
            <small style="color:var(--muted); font-size: 12px;"><i class="bi bi-info-circle"></i> The 'Buy it Now' price above will be available until the first bid is placed.</small>
        </div>

        <button type="submit" class="submit-btn">List Product</button>
    </form>
</div>

<script>
function toggleAuctionFields() {
    const isChecked = document.getElementById('is_auction').checked;
    const fields = document.getElementById('auction_fields');
    const startPrice = document.getElementById('auc_start_price');
    
    if (isChecked) {
        fields.style.display = 'block';
        startPrice.setAttribute('required', 'true');
    } else {
        fields.style.display = 'none';
        startPrice.removeAttribute('required');
    }
}
</script>

<?php include("../layout/footer.php"); ?>
