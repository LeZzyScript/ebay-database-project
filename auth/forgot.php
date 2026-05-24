<?php
session_start();
if (isset($_SESSION['account_id'])) {
    header('Location: ../index.php');
    exit;
}

require_once('../config/db.php');

$step     = $_POST['step'] ?? 'find';   // 'find' | 'reset'
$error    = '';
$success  = '';
$emailVal = '';
$userId   = '';

// ── STEP 1 : Find the account ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'find') {
    $emailVal = trim($_POST['email'] ?? '');

    if (!$emailVal) {
        $error = 'Please enter your email address or username.';
        $step  = 'find';
    } else {
        $stmt = $conn->prepare('SELECT User_ID, User_FName FROM User WHERE User_Email = ? OR User_AccName = ?');
        $stmt->bind_param('ss', $emailVal, $emailVal);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $userId = $row['User_ID'];
            $step   = 'reset';
        } else {
            $error = 'No account found with that email or username.';
            $step  = 'find';
        }
        $stmt->close();
    }
}

// ── STEP 2 : Reset the password ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $userId  = trim($_POST['user_id']  ?? '');
    $emailVal= trim($_POST['email']   ?? '');
    $pw      = $_POST['password']      ?? '';
    $cpw     = $_POST['cpassword']     ?? '';

    if (!$pw) {
        $error = 'Please enter a new password.';
    } elseif (strlen($pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pw !== $cpw) {
        $error = 'Passwords do not match.';
    } elseif (!$userId) {
        $error = 'Session expired. Please start over.';
        $step  = 'find';
    } else {
        $hashed = password_hash($pw, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE User SET User_Password = ? WHERE User_ID = ?');
        $stmt->bind_param('ss', $hashed, $userId);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash'] = 'Password reset successfully! Please sign in with your new password.';
        header('Location: signin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Reset Password | eBay Philippines</title>
    <meta name="description" content="Reset your eBay Philippines account password."/>
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
            --green:      #2e7d32;
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
            margin-bottom: 8px;
            letter-spacing: -0.2px;
        }
        .card-subtitle {
            font-size: 14px;
            color: var(--muted);
            text-align: center;
            margin-bottom: 24px;
            line-height: 1.5;
        }

        /* ── ALERT ── */
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

        /* Progress indicator */
        .steps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 28px;
        }
        .step-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--border);
            transition: background 0.2s;
        }
        .step-dot.active { background: var(--blue); }
        .step-line {
            flex: 1;
            max-width: 60px;
            height: 2px;
            background: var(--border);
        }

        /* Account chip (step 2) */
        .account-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .chip-icon {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--blue);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .chip-icon svg { width: 16px; height: 16px; fill: currentColor; }
        .chip-label { font-weight: 600; color: var(--text); }

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
        .pw-toggle svg {
            width: 18px; height: 18px;
            stroke: currentColor; fill: none;
            stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }

        /* Strength meter */
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: var(--border);
            margin-top: 6px;
            overflow: hidden;
        }
        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
        }
        .strength-label {
            font-size: 11px;
            margin-top: 4px;
            color: var(--muted);
        }

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
            margin-top: 4px;
        }
        .btn-primary:hover  { background: var(--blue-hover); }
        .btn-primary:active { transform: scale(.99); }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--muted);
        }
        .back-link a { color: var(--text); font-weight: 600; }

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
        <a href="signin.php">Back to sign in</a>
    </div>
</nav>

<!-- Main -->
<main>
    <div class="card">

        <h1 class="card-title">Reset your password</h1>

        <!-- Progress dots -->
        <div class="steps">
            <div class="step-dot <?= ($step === 'find'  || $step === 'reset') ? 'active' : '' ?>"></div>
            <div class="step-line"></div>
            <div class="step-dot <?= $step === 'reset' ? 'active' : '' ?>"></div>
        </div>

        <?php if ($step === 'find'): ?>
        <!-- ── STEP 1: Find account ─────────────────────────────── -->
        <p class="card-subtitle">Enter the email address or username associated with your account.</p>

        <?php if ($error): ?>
        <div class="alert-error" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="forgot.php" id="findForm">
            <input type="hidden" name="step" value="find"/>

            <div class="field">
                <label for="email">Email or username</label>
                <input type="text" id="email" name="email"
                       value="<?= htmlspecialchars($emailVal) ?>"
                       required autofocus autocomplete="email"
                       placeholder=""/>
            </div>

            <button class="btn-primary" type="submit" id="findBtn">Continue</button>
        </form>

        <?php else: ?>
        <!-- ── STEP 2: Set new password ────────────────────────── -->
        <p class="card-subtitle">Choose a strong new password for your account.</p>

        <?php if ($error): ?>
        <div class="alert-error" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Account chip -->
        <div class="account-chip">
            <div class="chip-icon">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v1h20v-1c0-3.3-6.7-5-10-5z"/></svg>
            </div>
            <span class="chip-label"><?= htmlspecialchars($emailVal) ?></span>
        </div>

        <form method="POST" action="forgot.php" id="resetForm">
            <input type="hidden" name="step"    value="reset"/>
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>"/>
            <input type="hidden" name="email"   value="<?= htmlspecialchars($emailVal) ?>"/>

            <div class="field">
                <label for="password">New password</label>
                <div class="pw-wrap">
                    <input type="password" id="password" name="password"
                           required autofocus autocomplete="new-password"
                           oninput="checkStrength(this.value)"
                           placeholder="Min. 8 characters"/>
                    <button type="button" class="pw-toggle" id="pwToggle1" aria-label="Show or hide password">
                        <svg id="eyeIcon1" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel"></div>
            </div>

            <div class="field">
                <label for="cpassword">Confirm new password</label>
                <div class="pw-wrap">
                    <input type="password" id="cpassword" name="cpassword"
                           required autocomplete="new-password"
                           placeholder="Re-enter your password"/>
                    <button type="button" class="pw-toggle" id="pwToggle2" aria-label="Show or hide password">
                        <svg id="eyeIcon2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button class="btn-primary" type="submit" id="resetBtn">Reset password</button>
        </form>
        <?php endif; ?>

        <p class="back-link">Remembered it? <a href="signin.php">Sign in</a></p>

    </div>
</main>

<!-- Footer -->
<footer>
    <div class="footer-inner">
        <div class="footer-links">
            <a href="#">Accessibility</a>
            <a href="#">User Agreement</a>
            <a href="#">Privacy</a>
            <a href="#">Payments Terms of Use</a>
            <a href="#">Cookies</a>
        </div>
        <p class="footer-copy">Copyright &copy; 1995&ndash;2026 eBay Inc. All Rights Reserved.</p>
    </div>
</footer>

<script>
/* ── Eye toggle ── */
(function () {
    const eyeOpen   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    const eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';

    function setupToggle(btnId, iconId, inputId) {
        const btn = document.getElementById(btnId);
        const ico = document.getElementById(iconId);
        const inp = document.getElementById(inputId);
        if (!btn || !inp) return;
        btn.addEventListener('click', function () {
            const show = inp.type === 'password';
            inp.type   = show ? 'text' : 'password';
            ico.innerHTML = show ? eyeClosed : eyeOpen;
        });
    }

    setupToggle('pwToggle1', 'eyeIcon1', 'password');
    setupToggle('pwToggle2', 'eyeIcon2', 'cpassword');
}());

/* ── Password strength ── */
function checkStrength(pw) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    if (!fill) return;

    let score = 0;
    if (pw.length >= 8)  score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const levels = [
        { w: '0%',   bg: 'transparent', text: '' },
        { w: '33%',  bg: '#e53238',     text: 'Weak' },
        { w: '66%',  bg: '#f5af02',     text: 'Fair' },
        { w: '100%', bg: '#86b817',     text: 'Strong' },
    ];
    // Map score 0-4 → levels 0-3
    const lvl = score === 0 ? 0 : score <= 1 ? 1 : score <= 3 ? 2 : 3;
    fill.style.width      = levels[lvl].w;
    fill.style.background = levels[lvl].bg;
    label.textContent     = levels[lvl].text;
    label.style.color     = levels[lvl].bg || 'var(--muted)';
}

/* ── Loading state on submit ── */
['findForm', 'resetForm'].forEach(function (id) {
    const f = document.getElementById(id);
    if (!f) return;
    f.addEventListener('submit', function () {
        const btn = f.querySelector('button[type="submit"]');
        if (btn) { btn.textContent = 'Please wait…'; btn.disabled = true; }
    });
});
</script>
</body>
</html>
