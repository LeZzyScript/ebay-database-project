<?php
session_start();
if (!isset($_SESSION['firebase_uid']) || empty($_SESSION['is_seller'])) {
    header('Location: ../buyer/dashboard.php');
    exit;
}

// Fetch user info from Firebase
require_once('../config/firebase.php');

$uid = $_SESSION['firebase_uid'];
$user = getData("users/{$uid}");

if (!$user) {
    session_destroy();
    header('Location: ../auth/signin.php');
    exit;
}

$profile = $user['profile'] ?? [];
$sellerData = $user['sellerData'] ?? [];
$business = $sellerData['business'] ?? [];

$positivePercentage = 100;
$totalFeedbacks = 0;
$feedbacksData = getData('feedbacks') ?: [];
if ($feedbacksData) {
    $ratings = [];
    foreach ($feedbacksData as $fId => $fData) {
        if (($fData['sellerId'] ?? '') === $uid) {
            $ratings[] = $fData['rating'] ?? 0;
        }
    }
    $totalFeedbacks = count($ratings);
    if ($totalFeedbacks > 0) {
        $positiveCount = 0;
        foreach ($ratings as $r) {
            if ($r >= 4) $positiveCount++;
        }
        $positivePercentage = round(($positiveCount / $totalFeedbacks) * 100);
    }
}

$title    = htmlspecialchars($profile['accountName'] ?? 'Seller') . " - Seller Profile";
$basePath = '../';
include('../layout/layout.php');

$memberSince = date('M d, Y', strtotime($sellerData['joinDate'] ?? 'now'));
$location    = 'Philippines';

$firstName = $profile['firstName'] ?? '';
$lastName = $profile['lastName'] ?? '';
$initials     = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$avatarColors = ['#E53238', '#0064D2', '#3EBD30', '#8B5CF6', '#F5AF02', '#EC4899', '#06B6D4'];
$avatarBg     = $avatarColors[ord($firstName[0] ?? 'A') % count($avatarColors)];
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
                        <?= htmlspecialchars($profile['accountName'] ?? 'Seller') ?>
                        <?php if ($business['verified'] ?? false): ?>
                            <span class="tag" style="background:#e8f4fd; color:var(--blue);">Verified Business</span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-rating">
                        <span class="star-icon">⭐</span>
                        <strong><?= $positivePercentage ?>%</strong> positive Feedback (<?= $totalFeedbacks ?> review<?= $totalFeedbacks !== 1 ? 's' : '' ?>)
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
                Business Name: <strong><?= htmlspecialchars($business['name'] ?? '') ?></strong>
            </div>
            <div class="about-row">
                Business Type: <strong><?= htmlspecialchars(ucfirst($business['type'] ?? '')) ?></strong>
            </div>
            <div class="about-row">
                Registration Num: <strong><?= htmlspecialchars($business['regNumber'] ?? $business['regNum'] ?? '') ?></strong>
            </div>
            <div class="about-row" style="margin-top:8px;">
                <strong>Contact Details</strong>
            </div>
            <div class="about-row">
                <?= htmlspecialchars($business['phone'] ?? '') ?>
            </div>
            <div class="about-row">
                <?= htmlspecialchars($business['address'] ?? '') ?>
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
                Seller Type: <strong><?= htmlspecialchars(ucfirst($sellerData['type'] ?? '')) ?></strong>
            </div>
            <div class="about-row">
                Status: <strong><?= htmlspecialchars($sellerData['status'] ?? '') ?></strong>
            </div>
            
            <hr style="border:none; border-top:1px solid var(--border); margin:8px 0;">
            
            <div class="about-row" style="margin-top:4px;">
                <strong>Owner Details</strong>
            </div>
            <div class="about-row">
                <?= htmlspecialchars($firstName.' '.$lastName) ?>
            </div>
            <div class="about-row">
                <?= htmlspecialchars($profile['email'] ?? '') ?>
            </div>
        </div>
    </div>

</div>

<?php include('../layout/footer.php'); ?>
