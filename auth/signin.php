<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once('../config/firebase.php');

// Check if already logged in
if (isset($_SESSION['firebase_uid'])) {
    $userData = getCurrentUserData();
    if ($userData) {
        $accountType = $userData['accountType'] ?? 'buyer';
        
        if ($accountType === 'admin') {
            header('Location: ../admin/dashboard.php');
        } elseif ($accountType === 'seller') {
            header('Location: ../seller/dashboard.php');
        } else {
            header('Location: ../buyer/dashboard.php');
        }
        exit;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Capture and sanitize redirect target (relative paths only, no external URLs)
$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
if (strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
    $redirect = '';
}
$redirect = preg_replace('/[^a-zA-Z0-9\/_\.\?=&%\-]/', '', $redirect);

$error    = '';
$step     = 'email'; // 'email' or 'password'
$emailVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailVal = trim($_POST['email'] ?? '');
    $step     = $_POST['step'] ?? 'email';

    // If both email and password were posted without a step (e.g. modal form),
    // treat it as the password authentication step directly.
    if ($step === 'email' && !empty($_POST['password'])) {
        $step = 'password';
    }

    if ($step === 'email') {
        if (!$emailVal) {
            $error = 'Please enter a valid email address or username.';
            $step  = 'email';
        } else {
            $step = 'password';
        }
    } elseif ($step === 'password') {
        $password = $_POST['password'] ?? '';
        if (!$password) {
            $error = 'Please enter your password.';
        } else {
            try {
                // Check if input is username or email
                $loginEmail = $emailVal;
                
                // If it looks like a username (no @ symbol), look up the email in Firebase
                if (!filter_var($emailVal, FILTER_VALIDATE_EMAIL)) {
                    $allUsers = getData('users');
                    $foundEmail = null;
                    if ($allUsers) {
                        foreach ($allUsers as $uid => $userData) {
                            if (isset($userData['profile']['accountName']) && 
                                strtolower($userData['profile']['accountName']) === strtolower($emailVal)) {
                                $foundEmail = $userData['profile']['email'] ?? null;
                                break;
                            }
                        }
                    }
                    if (!$foundEmail) {
                        $error = 'The username or password you entered doesn\'t match any account.';
                        $step = 'password';
                    } else {
                        $loginEmail = $foundEmail;
                    }
                }
                
                // Sign in with Firebase Admin SDK
                $user = $auth->signInWithEmailAndPassword($loginEmail, $password);
                
                // Get UID from SignInResult — must use firebaseUserId() method, not ->uid property
                $uid = $user->firebaseUserId();
                
                if (!$uid) {
                    $error = 'Authentication failed: could not retrieve user ID. Please try again.';
                    $step = 'password';
                    throw new \Exception('firebaseUserId() returned null');
                }
                
                // Get user data from Firebase Realtime Database
                $userData = getData("users/{$uid}");
                
                if ($userData) {
                    $_SESSION['firebase_uid'] = $uid;
                    $_SESSION['display_name'] = ($userData['profile']['firstName'] ?? '') . ' ' . ($userData['profile']['lastName'] ?? '');
                    $_SESSION['account_type'] = $userData['accountType'] ?? 'buyer';
                    
                    // Check if seller
                    $_SESSION['is_seller'] = ($_SESSION['account_type'] === 'seller');
                    
                    // Check if admin
                    $_SESSION['is_admin'] = ($_SESSION['account_type'] === 'admin');
                    if ($_SESSION['is_admin']) {
                        $adminsData = getData('admins');
                        $adminFound = false;
                        if ($adminsData) {
                            foreach ($adminsData as $adminId => $adminRec) {
                                if (isset($adminRec['userId']) && $adminRec['userId'] === $uid) {
                                    $_SESSION['admin_id'] = $adminId;
                                    $_SESSION['admin_level'] = $adminRec['level'] ?? 1;
                                    $_SESSION['admin_perm'] = $adminRec['permissions'] ?? '';
                                    $adminFound = true;
                                    break;
                                }
                            }
                        }
                        if (!$adminFound && isset($userData['adminData'])) {
                            $_SESSION['admin_level'] = $userData['adminData']['level'] ?? 1;
                            $_SESSION['admin_perm'] = $userData['adminData']['permissions'] ?? '';
                        }
                    }

                    if ($redirect) {
                        header('Location: ../' . $redirect);
                    } elseif ($_SESSION['is_admin']) {
                        header('Location: ../admin/dashboard.php');
                    } elseif ($_SESSION['is_seller']) {
                        header('Location: ../seller/dashboard.php');
                    } else {
                        header('Location: ../buyer/dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'User data not found. Please contact support.';
                    $step = 'password';
                }
            } catch (\Kreait\Firebase\Exception\Auth\InvalidPassword $e) {
                $error = 'The email or password you entered doesn\'t match any account.';
                $step = 'password';
            } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
                $error = 'The email or password you entered doesn\'t match any account.';
                $step = 'password';
            } catch (\Exception $e) {
                $error = 'An error occurred. Please try again.';
                $step = 'password';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sign In | eBay Philippines</title>
    <meta name="description" content="Sign in to your eBay Philippines account."/>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet"/>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue:       #3665f3;
            --blue-hover: #2b55d9;
            --text:       #191919;
            --muted:      #767676;
            --border:     #c7c7c7;
            --border-f:   #3665f3;
            --bg:         #f7f7f7;
            --red:        #cc1100;
            --font: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }

        html, body { height: 100%; }

        body {
            font-family: var(--font);
            background: #fff;
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        a { color: var(--blue); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ── TOP NAV ── */
        .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            border-bottom: 1px solid #e5e5e5;
        }
        .logo img { height: 34px; width: auto; display: block; }

        .top-nav-links { font-size: 13px; color: var(--muted); }
        .top-nav-links a { color: var(--muted); }
        .top-nav-links a:hover { color: var(--text); }

        /* ── MAIN ── */
        main {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 20px 60px;
        }

        /* ── CARD ── */
        .card {
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 44px 36px 36px;
        }

        .card-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            text-align: center;
            margin-bottom: 22px;
            letter-spacing: -0.2px;
        }

        /* ── NEW / CREATE ROW ── */
        .new-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 24px;
        }
        .new-row span { font-size: 13px; color: var(--muted); }
        .new-row a {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            border: 1.5px solid var(--border);
            border-radius: 20px;
            padding: 5px 14px;
            text-decoration: none;
            transition: border-color .15s;
        }
        .new-row a:hover { border-color: var(--text); text-decoration: none; }

        /* ── ERROR ── */
        .alert-error {
            background: #fff0f0;
            border: 1px solid #f5c6c6;
            color: var(--red);
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .alert-error svg { flex-shrink: 0; margin-top: 1px; }

        /* ── INPUT ── */
        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 5px;
        }
        .field input {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            font-family: var(--font);
            color: var(--text);
            background: #fff;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .field input:focus {
            border-color: var(--border-f);
            box-shadow: 0 0 0 3px rgba(54,101,243,0.12);
        }

        /* Password field + toggle */
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 46px; }
        .pw-toggle {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--muted);
            display: flex; align-items: center;
            padding: 4px;
            transition: color .15s;
        }
        .pw-toggle:hover { color: var(--text); }
        .pw-toggle svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        .field-hint {
            display: flex;
            justify-content: flex-end;
            margin-top: 5px;
        }
        .field-hint a { font-size: 12px; }

        /* ── EMAIL CHIP (shown on password step) ── */
        .email-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 13px;
        }
        .email-chip .chip-email { font-weight: 600; color: var(--text); }
        .email-chip a { font-size: 12px; color: var(--blue); }

        /* ── BUTTON ── */
        .btn-primary {
            width: 100%;
            background: var(--blue);
            color: #fff;
            border: none;
            border-radius: 28px;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            transition: background .15s, transform .1s;
        }
        .btn-primary:hover  { background: var(--blue-hover); }
        .btn-primary:active { transform: scale(.99); }

        /* ── STAY SIGNED IN ── */
        .stay-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
        }
        .stay-row input[type="checkbox"] {
            width: 15px; height: 15px;
            accent-color: var(--blue);
            cursor: pointer;
            flex-shrink: 0;
        }
        .stay-row label {
            font-size: 13px;
            color: var(--text);
            cursor: pointer;
            display: flex; align-items: center; gap: 6px;
        }
        .info-icon {
            width: 16px; height: 16px;
            border: 1px solid var(--border);
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 9px; color: var(--muted); cursor: default;
            flex-shrink: 0;
        }

        /* ── FOOTER ── */
        footer {
            border-top: 1px solid #e5e5e5;
            padding: 18px 24px;
            background: #fff;
        }
        .footer-inner {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .footer-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 4px 14px;
        }
        .footer-links a { font-size: 12px; color: var(--muted); }
        .footer-links a:hover { color: var(--text); }
        .footer-copy { font-size: 12px; color: var(--muted); text-align: center; }

        @media (max-width: 460px) {
            .card { border: none; padding: 28px 16px; }
            main { padding: 20px 0 40px; }
        }
    </style>
</head>
<body>

<!-- Top nav -->
<nav class="top-nav">
    <a class="logo" href="../index.php">
        <img src="../assets/img/Ebay.png" alt="eBay Philippines"/>
    </a>
    <div class="top-nav-links">
        <a href="#">Tell us what you think</a>
    </div>
</nav>

<!-- Main -->
<main>
    <div class="card">

        <h1 class="card-title">Sign in to your account</h1>

        <!-- New to eBay row -->
        <div class="new-row">
            <span>New to eBay?</span>
            <a href="register.php">Create account</a>
        </div>

        <?php if ($error): ?>
        <div class="alert-error" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($step === 'email'): ?>
        <!-- ── STEP 1: Email ── -->
        <form method="POST" action="signin.php" id="emailForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
            <input type="hidden" name="step" value="email"/>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>"/>

            <div class="field">
                <label for="email">Email or username</label>
                <input type="text" id="email" name="email"
                       value="<?= htmlspecialchars($emailVal) ?>"
                       required autocomplete="email" autofocus
                       placeholder=""/>
            </div>

            <button class="btn-primary" type="submit" id="continueBtn">Continue</button>

            <div class="stay-row">
                <input type="checkbox" id="staySignedIn" name="remember" checked/>
                <label for="staySignedIn">
                    Stay signed in
                    <span class="info-icon" title="Only use on your personal device">?</span>
                </label>
            </div>
        </form>

        <?php else: ?>
        <!-- ── STEP 2: Password ── -->
        <form method="POST" action="signin.php" id="passwordForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
            <input type="hidden" name="step" value="password"/>
            <input type="hidden" name="email" value="<?= htmlspecialchars($emailVal) ?>"/>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>"/>

            <!-- Email chip -->
            <div class="email-chip">
                <span class="chip-email"><?= htmlspecialchars($emailVal) ?></span>
                <a href="signin.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>">Edit</a>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password"
                           required autocomplete="current-password" autofocus
                           placeholder=""/>
                    <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show or hide password">
                        <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="field-hint">
                    <a href="forgot.php">Forgot password?</a>
                </div>
            </div>

            <button class="btn-primary" type="submit" id="signinBtn">Sign in</button>

            <div class="stay-row">
                <input type="checkbox" id="staySignedIn" name="remember" checked/>
                <label for="staySignedIn">
                    Stay signed in
                    <span class="info-icon" title="Only use on your personal device">?</span>
                </label>
            </div>
        </form>
        <?php endif; ?>

    </div>
</main>

<!-- Footer -->
<footer>
    <div class="footer-inner">
        <div class="footer-links">
            <a href="#">Accessibility</a>
            <a href="#">User Agreement</a>
            <a href="#">Privacy</a>
            <a href="#">Consumer Health Data</a>
            <a href="#">Payments Terms of Use</a>
            <a href="#">Cookies</a>
            <a href="#">CA Privacy Notice</a>
            <a href="#">Your Privacy Choices</a>
            <a href="#">AdChoice</a>
        </div>
        <p class="footer-copy">Copyright &copy; 1995&ndash;2026 eBay Inc. All Rights Reserved.</p>
    </div>
</footer>

<script>
/* Password show/hide */
(function () {
    const btn = document.getElementById('pwToggle');
    if (!btn) return;
    const inp = document.getElementById('password');
    const ico = document.getElementById('eyeIcon');
    const open  = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    const closed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
    btn.addEventListener('click', function () {
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        ico.innerHTML = show ? closed : open;
    });
}());

/* Loading state on submit */
['emailForm','passwordForm'].forEach(function(id){
    const f = document.getElementById(id);
    if (!f) return;
    f.addEventListener('submit', function(){
        const btn = f.querySelector('button[type="submit"]');
        if (btn) { btn.textContent = 'Please wait…'; btn.disabled = true; }
    });
});
</script>
</body>
</html>
