<?php
session_start();
if (!isset($_SESSION['firebase_uid'])) {
    header('Location: ../auth/signin.php');
    exit;
}

require_once('../config/firebase.php');

$uid = $_SESSION['firebase_uid'];
$errors  = [];
$success = false;

$user = getData("users/{$uid}");

if (!$user) {
    session_destroy();
    header('Location: ../auth/signin.php');
    exit;
}

$profile = $user['profile'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $fname   = trim($_POST['fname']   ?? '');
        $lname   = trim($_POST['lname']   ?? '');
        $accname = trim($_POST['accname'] ?? '');
        $email   = trim($_POST['email']   ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $newPw   = $_POST['new_password']     ?? '';
        $confPw  = $_POST['confirm_password'] ?? '';

        if (!$fname)                                      $errors[] = 'First name is required.';
        if (!$lname)                                      $errors[] = 'Last name is required.';
        if (!$accname)                                    $errors[] = 'Username is required.';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (!preg_match('/^[0-9]{11}$/', $contact))      $errors[] = 'Contact number must be exactly 11 digits.';
        if (!$address)                                    $errors[] = 'Address is required.';

        if (empty($errors)) {
            // Check if email is already used by another user
            $allUsers = getData('users');
            if ($allUsers) {
                foreach ($allUsers as $checkUid => $checkUser) {
                    if ($checkUid !== $uid && isset($checkUser['profile']['email']) && $checkUser['profile']['email'] === $email) {
                        $errors[] = 'This email is already used by another account.';
                        break;
                    }
                }
            }
        }

        if (empty($errors)) {
            // Check if username is already taken by another user
            $allUsers = getData('users');
            if ($allUsers) {
                foreach ($allUsers as $checkUid => $checkUser) {
                    if ($checkUid !== $uid && isset($checkUser['profile']['accountName']) && strtolower($checkUser['profile']['accountName']) === strtolower($accname)) {
                        $errors[] = 'This username is already taken.';
                        break;
                    }
                }
            }
        }

        if ($newPw !== '') {
            if (strlen($newPw) < 8) $errors[] = 'New password must be at least 8 characters.';
            if ($newPw !== $confPw)  $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            // Update profile in Firebase
            updateData("users/{$uid}/profile", [
                'firstName' => $fname,
                'lastName' => $lname,
                'accountName' => $accname,
                'email' => $email,
                'contact' => $contact,
                'address' => $address
            ]);

            // Update password in Firebase Auth if provided
            if ($newPw !== '') {
                try {
                    $auth->updateUser($uid, [
                        'password' => $newPw
                    ]);
                } catch (\Exception $e) {
                    $errors[] = 'Failed to update password: ' . $e->getMessage();
                }
            }

            if (empty($errors)) {
                $_SESSION['display_name'] = $fname;
                $success = true;

                // Reload user data
                $user = getData("users/{$uid}");
                $profile = $user['profile'] ?? [];
            }
        }
    }
}

$title    = 'Edit Profile';
$basePath = '../';
include('../layout/layout.php');
?>

<style>
    .ep-wrap {
        max-width: 680px;
        margin: 40px auto;
        padding: 0 24px 60px;
    }
    .ep-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--muted);
        margin-bottom: 24px;
        text-decoration: none;
    }
    .ep-back:hover { color: var(--text); text-decoration: none; }
    .ep-back svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

    .ep-title {
        font-size: 26px;
        font-weight: 700;
        margin-bottom: 32px;
    }

    .ep-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 28px;
        margin-bottom: 20px;
    }
    .ep-card-title {
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f0f0f0;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    .form-row.full { grid-template-columns: 1fr; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 13px; font-weight: 600; color: var(--text); }
    .form-group input,
    .form-group textarea {
        border: 1.5px solid var(--border);
        border-radius: 8px;
        padding: 11px 14px;
        font-size: 14px;
        font-family: var(--font);
        color: var(--text);
        outline: none;
        transition: border-color .15s;
        background: #fff;
        width: 100%;
    }
    .form-group input:focus,
    .form-group textarea:focus { border-color: var(--blue); }
    .form-group textarea { resize: vertical; min-height: 80px; }

    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 44px; }
    .pw-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: var(--muted);
        padding: 4px;
        display: flex;
    }
    .pw-toggle:hover { color: var(--text); }
    .pw-toggle svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

    .alert {
        padding: 14px 18px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .alert svg { flex-shrink: 0; width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; margin-top: 1px; }
    .alert-success { background: #f0faf0; border: 1px solid #b2dfb2; color: #256a25; }
    .alert-error   { background: #fff0f0; border: 1px solid #f5c6c6; color: #cc1100; }
    .alert ul { margin: 6px 0 0 18px; }
    .alert ul li { margin-bottom: 2px; }

    .btn-row {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }
    .btn-save {
        background: var(--blue);
        color: #fff;
        border: none;
        border-radius: 24px;
        padding: 12px 28px;
        font-size: 14px;
        font-weight: 600;
        font-family: var(--font);
        cursor: pointer;
        transition: background .15s;
    }
    .btn-save:hover { background: #0053b3; }
    .btn-cancel {
        background: #fff;
        color: var(--text);
        border: 1.5px solid var(--border);
        border-radius: 24px;
        padding: 12px 28px;
        font-size: 14px;
        font-weight: 600;
        font-family: var(--font);
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        transition: border-color .15s;
    }
    .btn-cancel:hover { border-color: var(--text); text-decoration: none; }

    .hint { font-size: 12px; color: var(--muted); margin-top: 2px; }

    @media (max-width: 600px) {
        .form-row { grid-template-columns: 1fr; }
    }
</style>

<div class="ep-wrap">
    <a href="buyer.php" class="ep-back">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back to profile
    </a>

    <h1 class="ep-title">Edit Profile</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Profile updated successfully.
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <?= count($errors) === 1
                    ? htmlspecialchars($errors[0])
                    : '<ul>' . implode('', array_map(fn($e) => '<li>' . htmlspecialchars($e) . '</li>', $errors)) . '</ul>'
                ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit-profile.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <!-- Personal Information -->
        <div class="ep-card">
            <div class="ep-card-title">Personal Information</div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fname">First Name</label>
                    <input type="text" id="fname" name="fname" value="<?= htmlspecialchars($profile['firstName'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="lname">Last Name</label>
                    <input type="text" id="lname" name="lname" value="<?= htmlspecialchars($profile['lastName'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="accname">Username</label>
                    <input type="text" id="accname" name="accname" value="<?= htmlspecialchars($profile['accountName'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($profile['email'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="tel" id="contact" name="contact" value="<?= htmlspecialchars($profile['contact'] ?? '') ?>" placeholder="09XXXXXXXXX" maxlength="11" required>
                </div>
            </div>

            <div class="form-row full">
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" required><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="ep-card">
            <div class="ep-card-title">Change Password</div>
            <p class="hint" style="margin-bottom:16px;">Leave both fields blank to keep your current password.</p>

            <div class="form-row">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters">
                        <button type="button" class="pw-toggle" onclick="togglePw('new_password', this)" tabindex="-1">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="pw-wrap">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password">
                        <button type="button" class="pw-toggle" onclick="togglePw('confirm_password', this)" tabindex="-1">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" class="btn-save">Save Changes</button>
            <a href="buyer.php" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

<script>
const eyeOpen  = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
const eyeClose = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';

function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const svg = btn.querySelector('svg');
    if (inp.type === 'password') {
        inp.type = 'text';
        svg.innerHTML = eyeClose;
    } else {
        inp.type = 'password';
        svg.innerHTML = eyeOpen;
    }
}
</script>

<?php include('../layout/footer.php'); ?>
