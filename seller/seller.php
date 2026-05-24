<?php
session_start();
if (!isset($_SESSION['account_id']) || empty($_SESSION['is_seller'])) {
    header('Location: ../buyer/dashboard.php');
    exit;
}

// Fetch user info from DB
require_once('../config/db.php');

$user = null;
$stmt = $conn->prepare('
    SELECT u.*, s.*, b.* 
    FROM User u 
    LEFT JOIN Seller s ON u.User_ID = s.Sell_UserID
    LEFT JOIN Business b ON s.Sell_ID = b.Bus_SellID
    WHERE u.User_ID = ?
');
$stmt->bind_param('s', $_SESSION['account_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user) {
    session_destroy();
    header('Location: ../auth/signin.php');
    exit;
}

$title    = htmlspecialchars($user['User_AccName']) . " - Seller Profile";
$basePath = '../';
include('../layout/layout.php');

$memberSince = date('M d, Y', strtotime($user['Sell_JoinDate']));
$location    = 'Philippines';

$initials     = strtoupper(substr($user['User_FName'], 0, 1) . substr($user['User_LName'], 0, 1));
$avatarColors = ['#E53238', '#0064D2', '#3EBD30', '#8B5CF6', '#F5AF02', '#EC4899', '#06B6D4'];
$avatarBg     = $avatarColors[ord($user['User_FName'][0]) % count($avatarColors)];
?>

<style>
    .profile-header {
        background: var(--bg);
        border-bottom: 1px solid #e5e5e5;
        padding: 28px 0 0;
    }
    .profile-header-inner {
        max-width: 1000px;
        margin: 0 auto;
        padding: 0 24px;
    }
    .profile-top {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    .profile-identity {
        display: flex;
        align-items: center;
        gap: 18px;
    }
    .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
        border: 2px solid rgba(0,0,0,.08);
        letter-spacing: -1px;
        font-family: var(--font);
    }
    .profile-details {
        display: flex;
        flex-direction: column;
    }
    .profile-username {
        font-size: 28px;
        font-weight: 700;
        color: var(--text);
    }
    .profile-rating {
        font-size: 14px;
        color: var(--muted);
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .star-icon { color: #f5c518; }
    .edit-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        border: 1.5px solid var(--border);
        background: #fff;
        border-radius: 20px;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        font-family: var(--font);
        cursor: pointer;
        color: var(--text);
        text-decoration: none;
        transition: border-color .15s, background .15s;
    }
    .edit-btn:hover { background: var(--bg); border-color: var(--text); text-decoration: none; }
    
    .profile-tabs {
        display: flex;
        gap: 0;
        margin-top: 8px;
    }
    .profile-tab {
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 500;
        color: var(--muted);
        border-bottom: 3px solid transparent;
        cursor: pointer;
        text-decoration: none;
        transition: color .15s;
    }
    .profile-tab.active {
        color: var(--text);
        border-bottom-color: var(--text);
        font-weight: 600;
    }
    .profile-tab:hover { color: var(--text); text-decoration: none; }

    .profile-body {
        max-width: 1000px;
        margin: 32px auto;
        padding: 0 24px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
    }
    
    .about-title {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 24px;
    }
    .about-info {
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #fff;
        border: 1px solid var(--border);
        padding: 24px;
        border-radius: 12px;
    }
    .about-row {
        font-size: 14px;
        color: var(--muted);
    }
    .about-row strong { color: var(--text); }
    .tag {
        display: inline-block;
        background: var(--bg);
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 8px;
        color: var(--text);
    }

    @media (max-width: 768px) {
        .profile-body { grid-template-columns: 1fr; }
    }
</style>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-header-inner">
        <div class="profile-top">
            <div class="profile-identity">
                <!-- Avatar -->
                <div class="profile-avatar" style="background:<?= $avatarBg ?>"><?= $initials ?></div>
                <div class="profile-details">
                    <div class="profile-username">
                        <?= htmlspecialchars($user['User_AccName']) ?>
                        <?php if ($user['Bus_Verified']): ?>
                            <span class="tag" style="background:#e8f4fd; color:var(--blue);">Verified Business</span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-rating">
                        <span class="star-icon">⭐</span>
                        <strong>100%</strong> positive Feedback (500+ items sold)
                    </div>
                </div>
            </div>
            <a href="edit-profile.php" class="edit-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Seller Profile
            </a>
        </div>

        <!-- Tabs -->
        <div class="profile-tabs">
            <a class="profile-tab active" href="seller.php">About</a>
            <a class="profile-tab" href="#">Items for sale (3)</a>
            <a class="profile-tab" href="#">Feedback</a>
        </div>
    </div>
</div>

<!-- About Section -->
<div class="profile-body">
    
    <div>
        <h2 class="about-title">Business Information</h2>
        <div class="about-info">
            <div class="about-row">
                Business Name: <strong><?= htmlspecialchars($user['Bus_Name']) ?></strong>
            </div>
            <div class="about-row">
                Business Type: <strong><?= htmlspecialchars(ucfirst($user['Bus_Type'])) ?></strong>
            </div>
            <div class="about-row">
                Registration Num: <strong><?= htmlspecialchars($user['Bus_RegNum']) ?></strong>
            </div>
            <div class="about-row" style="margin-top:8px;">
                <strong>Contact Details</strong>
            </div>
            <div class="about-row">
                <?= htmlspecialchars($user['Bus_Phone']) ?>
            </div>
            <div class="about-row">
                <?= htmlspecialchars($user['Bus_Address']) ?>
            </div>
        </div>
    </div>

    <div>
        <h2 class="about-title">Seller Information</h2>
        <div class="about-info">
            <div class="about-row">
                Location: <strong><?= htmlspecialchars($location) ?></strong>
            </div>
            <div class="about-row">
                Selling since: <strong><?= $memberSince ?></strong>
            </div>
            <div class="about-row">
                Seller Type: <strong><?= htmlspecialchars(ucfirst($user['Sell_Type'])) ?></strong>
            </div>
            <div class="about-row">
                Status: <strong><?= htmlspecialchars($user['Sell_Status']) ?></strong>
            </div>
            
            <hr style="border:none; border-top:1px solid var(--border); margin:8px 0;">
            
            <div class="about-row" style="margin-top:4px;">
                <strong>Owner Details</strong>
            </div>
            <div class="about-row">
                <?= htmlspecialchars($user['User_FName'].' '.$user['User_LName']) ?>
            </div>
            <div class="about-row">
                <?= htmlspecialchars($user['User_Email']) ?>
            </div>
        </div>
    </div>

</div>

<?php include('../layout/footer.php'); ?>
