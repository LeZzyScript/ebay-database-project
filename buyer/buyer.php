<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php');
    exit;
}
if (!empty($_SESSION['is_admin'])) {
    header('Location: ../admin/dashboard.php');
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
$title    = htmlspecialchars($profile['accountName'] ?? 'User') . "'s Profile";
$basePath = '../';
include('../layout/layout.php');

$memberSince = date('M d, Y', strtotime($user['dateRegistered'] ?? 'now'));
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
    .profile-username {
        font-size: 28px;
        font-weight: 700;
        color: var(--text);
    }
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

    /* Search bar in profile */
    .profile-search-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 1000px;
        margin: 20px auto 0;
        padding: 0 24px 20px;
    }
    .profile-search {
        display: flex;
        align-items: center;
        border: 1.5px solid var(--border);
        border-radius: 24px;
        padding: 8px 16px;
        gap: 8px;
        min-width: 280px;
        background: #fff;
    }
    .profile-search input {
        border: none;
        outline: none;
        font-size: 13px;
        font-family: var(--font);
        width: 100%;
        color: var(--text);
    }
    .profile-search svg {
        width: 16px; height: 16px;
        stroke: var(--muted); fill: none;
        stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        flex-shrink: 0;
    }

    /* About section */
    .profile-body {
        max-width: 1000px;
        margin: 32px auto;
        padding: 0 24px;
    }
    .about-title {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 24px;
    }
    .about-info {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .about-row {
        font-size: 14px;
        color: var(--muted);
    }
    .about-row strong { color: var(--text); }
</style>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-header-inner">
        <div class="profile-top">
            <div class="profile-identity">
                <!-- Avatar -->
                <div class="profile-avatar" style="background:<?= $avatarBg ?>"><?= $initials ?></div>
                <div class="profile-username"><?= htmlspecialchars($profile['accountName'] ?? 'User') ?></div>
            </div>
            <a href="edit-profile.php" class="edit-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit profile
            </a>
        </div>

        <!-- Tabs -->
        <div class="profile-tabs">
            <a class="profile-tab active" href="buyer.php">About</a>
            <a class="profile-tab" href="feedback.php">Feedback</a>
        </div>
    </div>
</div>

<!-- Profile Search -->
<div class="profile-search-row">
    <div><!-- spacer --></div>
    <div class="profile-search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" placeholder="Search all items">
    </div>
</div>

<!-- About Section -->
<div class="profile-body">
    <h2 class="about-title">About</h2>
    <div class="about-info">
        <div class="about-row">
            Location: <strong><?= htmlspecialchars($location) ?></strong>
        </div>
        <div class="about-row">
            Member since: <strong><?= $memberSince ?></strong>
        </div>
        <div class="about-row" style="margin-top:8px;">
            <strong><?= htmlspecialchars($firstName.' '.$lastName) ?></strong>
        </div>
        <div class="about-row">
            <?= htmlspecialchars($profile['email'] ?? '') ?>
        </div>
        <div class="about-row">
            <?= htmlspecialchars($profile['contact'] ?? '') ?>
        </div>
        <div class="about-row">
            <?= htmlspecialchars($profile['address'] ?? '') ?>
        </div>
    </div>
</div>

<?php include('../layout/footer.php'); ?>
