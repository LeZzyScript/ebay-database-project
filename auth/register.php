<?php
session_start();
if (isset($_SESSION['firebase_uid'])) { header('Location: ../index.php'); exit; }

require_once('../config/firebase.php');

$errors  = [];
$success = false;
$accType = 'personal';
// Repopulate values on error
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accType = $_POST['account_type'] ?? 'personal';

    // ── Collect & sanitize ──
    $fname   = trim($_POST['fname']   ?? '');
    $lname   = trim($_POST['lname']   ?? '');
    $accname = trim($_POST['accname'] ?? '');
    $email   = trim($_POST['email']   ?? '');
    $pw      = $_POST['password']     ?? '';
    $cpw     = $_POST['cpassword']    ?? '';
    $contact = trim($_POST['contact'] ?? '');

    // Address parts → concatenated
    $addr = implode(', ', array_filter(array_map('trim', [
        $_POST['addr_home']   ?? '',
        $_POST['addr_street'] ?? '',
        $_POST['addr_brgy']   ?? '',
        $_POST['addr_city']   ?? '',
        $_POST['addr_prov']   ?? '',
        $_POST['addr_zip']    ?? '',
    ])));

    $old = $_POST;

    // ── Validations ──
    if (!$fname)   $errors[] = 'First name is required.';
    if (!$lname)   $errors[] = 'Last name is required.';
    if (!$accname) $errors[] = 'Account username is required.';

    if (!$email) {
        $errors[] = 'Email is required.';
    } elseif (!str_contains($email, '@')) {
        $errors[] = 'Email must contain an @ sign.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$pw)            $errors[] = 'Password is required.';
    elseif (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';

    if ($pw !== $cpw)    $errors[] = 'Passwords do not match.';
    if (!$contact) {
        $errors[] = 'Contact number is required.';
    } elseif (!preg_match('/^[0-9]{11}$/', $contact)) {
        $errors[] = 'Contact number must be exactly 11 digits.';
    }
    if (!$addr)          $errors[] = 'Please complete all address fields.';

    // Duplicate email check - Firebase will handle this, but we can check existing users
    try {
        $auth->getUserByEmail($email);
        $errors[] = 'An account with this email already exists.';
    } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
        // Email doesn't exist, continue
    }

    // Duplicate username check - need to query Firebase Realtime Database
    $users = getData('users');
    if ($users) {
        foreach ($users as $uid => $userData) {
            if (isset($userData['profile']['accountName']) && $userData['profile']['accountName'] === $accname) {
                $errors[] = 'Username is already taken.';
                break;
            }
        }
    }

    // Seller/Business fields
    $sell_type  = trim($_POST['sell_type']   ?? '');
    $bus_name   = trim($_POST['bus_name']    ?? '');
    $bus_taxid  = trim($_POST['bus_taxid']   ?? '');
    $bus_regnum = trim($_POST['bus_regnum']  ?? '');
    $bus_type   = trim($_POST['bus_type']    ?? '');
    $bus_phone  = trim($_POST['bus_phone']   ?? '');
    $bus_addr   = trim($_POST['bus_address'] ?? '');

    if ($accType === 'business') {
        if (!$sell_type)  $errors[] = 'Seller type is required.';
        if (!$bus_name)   $errors[] = 'Business name is required.';
        if (!$bus_taxid)  $errors[] = 'Tax ID is required.';
        if (!$bus_regnum) $errors[] = 'Registration number is required.';
        if (!$bus_type)   $errors[] = 'Business type is required.';
        if (!$bus_phone)  $errors[] = 'Business phone is required.';
        if (!$bus_addr)   $errors[] = 'Business address is required.';
    }


    // ── Insert if no errors ──
    if (empty($errors)) {
        try {
            // Create user in Firebase Auth
            $userProperties = [
                'email' => $email,
                'emailVerified' => false,
                'password' => $pw,
                'displayName' => $fname . ' ' . $lname,
            ];
            
            $createdUser = $auth->createUser($userProperties);
            $uid = $createdUser->uid;
            $today = date('Y-m-d');
            
            // Determine account type
            $dbAccType = 'buyer';
            if ($accType === 'business') $dbAccType = 'seller';
            
            // Prepare user data for Firebase Realtime Database
            $userData = [
                'profile' => [
                    'firstName' => $fname,
                    'lastName' => $lname,
                    'accountName' => $accname,
                    'email' => $email,
                    'contact' => $contact,
                    'address' => $addr
                ],
                'accountType' => $dbAccType,
                'status' => 'active',
                'dateRegistered' => $today
            ];
            
            // Add seller data if business account
            if ($accType === 'business') {
                $sellId = generateId('SELL');
                $busId = generateId('BUSI');
                
                $userData['sellerData'] = [
                    'sellerId' => $sellId,
                    'type' => $sell_type,
                    'feeRate' => 5.00,
                    'status' => 'active',
                    'joinDate' => $today,
                    'business' => [
                        'businessId' => $busId,
                        'name' => $bus_name,
                        'taxId' => $bus_taxid,
                        'regNumber' => $bus_regnum,
                        'regNum' => $bus_regnum,
                        'verified' => false,
                        'verificationDate' => null,
                        'type' => $bus_type,
                        'phone' => $bus_phone,
                        'address' => $bus_addr
                    ]
                ];
            }
            
            // Store user data in Firebase Realtime Database
            setData("users/{$uid}", $userData);
            
            if (empty($errors)) {
                $_SESSION['flash'] = 'Account created successfully! Please sign in.';
                header('Location: signin.php');
                exit;
            }
        } catch (\Kreait\Firebase\Exception\AuthException $e) {
            $errors[] = "Failed to create account: " . $e->getMessage();
        } catch (\Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Create an Account | eBay</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet"/>
    <style>
        .server-errors {
            background: #fff0f0;
            border: 1px solid #f5c6c6;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .err-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            color: #cc1100;
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 4px;
        }
        .err-item:last-child { margin-bottom: 0; }
        .err-item svg { flex-shrink: 0; margin-top: 2px; color: #cc1100; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --blue: #3665f3;
            --blue-hover: #2b55d9;
            --text: #191919;
            --muted: #767676;
            --border: #c7c7c7;
            --border-f: #3665f3;
            --bg: #f7f7f7;
            --red: #cc1100;
            --font: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }

        body {
            font-family: var(--font);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            background: #fff;
        }

        a { color: var(--blue); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ── LAYOUT ── */
        .split-layout {
            display: flex;
            width: 100%;
            height: 100vh;
        }

        .left-pane {
            flex: 1;
            display: none; /* Hidden on mobile */
            padding: 32px;
            background: #fff;
        }

        .left-pane img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 24px;
        }

        .right-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 24px 40px;
            overflow-y: auto;
        }

        /* ── HEADER ── */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 40px;
        }

        .logo img { height: 40px; }

        .signin-link {
            font-size: 14px;
            color: var(--muted);
        }

        .signin-link a {
            color: var(--text);
            font-weight: 600;
            text-decoration: underline;
        }

        /* ── FORM CONTAINER ── */
        .form-container {
            max-width: 460px;
            width: 100%;
            margin: 0 auto;
        }

        h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 24px;
            letter-spacing: -0.5px;
        }

        /* ── ACCOUNT TYPE TOGGLE ── */
        .account-type-toggle {
            display: flex;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 4px;
            margin-bottom: 24px;
        }

        .toggle-btn {
            flex: 1;
            padding: 10px 0;
            text-align: center;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: transparent;
            color: var(--text);
            transition: background 0.2s, color 0.2s;
        }

        .toggle-btn.active {
            background: var(--text);
            color: #fff;
        }

        /* ── FORM FIELDS ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .field-full {
            grid-column: span 2;
        }

        .field-group {
            margin-bottom: 16px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 24px 0 16px;
            grid-column: span 2;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }

        .field { position: relative; }

        .field input, .field select {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 15px;
            font-family: var(--font);
            color: var(--text);
            outline: none;
            transition: border-color 0.2s;
            background: #fff;
        }

        .field input::placeholder { color: var(--muted); }

        .field input:focus, .field select:focus {
            border-color: var(--border-f);
        }

        .field.error input, .field.error select {
            border-color: var(--red);
        }

        .error-msg {
            color: var(--red);
            font-size: 12px;
            margin-top: 4px;
            display: none;
        }

        .field.error .error-msg {
            display: block;
        }

        /* Password Toggle */
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 48px; }
        .pw-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            padding: 4px;
        }
        .pw-toggle:hover { color: var(--text); }
        .pw-toggle svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

        /* ── TERMS & BUTTON ── */
        .terms-text {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .btn-primary {
            width: 100%;
            background: var(--blue);
            color: #fff;
            border: none;
            border-radius: 30px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            font-family: var(--font);
            cursor: pointer;
            transition: background 0.2s;
            margin-bottom: 40px;
        }

        .btn-primary:hover { background: var(--blue-hover); }

        /* Hide business fields by default */
        .business-fields { display: none; }

        /* ── RESPONSIVE ── */
        @media (min-width: 900px) {
            .left-pane { display: block; }
        }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .field-full { grid-column: span 1; }
            .section-title { grid-column: span 1; }
            .right-pane { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="split-layout">
    <!-- Left Pane with Image -->
    <div class="left-pane">
        <!-- Placeholder image resembling the reference -->
        <img src="https://images.unsplash.com/photo-1529156069898-49953e39b3ac?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" alt="Happy people">
    </div>

    <!-- Right Pane with Form -->
    <div class="right-pane">
        <div class="top-header">
            <a href="../index.php" class="logo">
                <img src="../assets/img/Ebay.png" alt="eBay">
            </a>
            <div class="signin-link">
                Already have an account? <a href="signin.php">Sign in</a>
            </div>
        </div>

        <div class="form-container">
            <h1>Create an account</h1>

            <div class="account-type-toggle">
                <button type="button" class="toggle-btn active" id="btnPersonal" onclick="setAccountType('personal')">Personal</button>
                <button type="button" class="toggle-btn" id="btnBusiness" onclick="setAccountType('business')">Business</button>

            </div>

            <?php if (!empty($errors)): ?>
            <div class="server-errors">
                <?php foreach ($errors as $e): ?>
                <div class="err-item">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?= htmlspecialchars($e) ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form id="registerForm" method="POST" action="register.php" novalidate>
                <input type="hidden" id="accountType" name="account_type" value="<?= htmlspecialchars($accType) ?>">

                <!-- Personal Information -->
                <div class="form-grid">
                    <div class="field">
                        <input type="text" id="fname" name="fname" placeholder="First name" value="<?= htmlspecialchars($old['fname'] ?? '') ?>" required>
                        <div class="error-msg">First name is required</div>
                    </div>
                    <div class="field">
                        <input type="text" id="lname" name="lname" placeholder="Last name" value="<?= htmlspecialchars($old['lname'] ?? '') ?>" required>
                        <div class="error-msg">Last name is required</div>
                    </div>
                    <div class="field field-full">
                        <input type="text" id="accname" name="accname" placeholder="Account username" value="<?= htmlspecialchars($old['accname'] ?? '') ?>" required>
                        <div class="error-msg">Account username is required</div>
                    </div>
                    <div class="field field-full">
                        <input type="email" id="email" name="email" placeholder="Email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
                        <div class="error-msg" id="emailError">Please enter a valid email address containing '@'</div>
                    </div>

                    <div class="field field-full">
                        <div class="pw-wrap">
                            <input type="password" id="password" name="password" placeholder="Password (min. 8 characters)" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('password', this)">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="error-msg">Password is required (min. 8 characters)</div>
                    </div>

                    <div class="field field-full">
                        <div class="pw-wrap">
                            <input type="password" id="cpassword" name="cpassword" placeholder="Confirm Password" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('cpassword', this)">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <div class="error-msg" id="cpasswordError">Passwords do not match</div>
                    </div>

                    <div class="field field-full">
                        <input type="tel" id="contact" name="contact" placeholder="Contact number (e.g. 09123456789)" value="<?= htmlspecialchars($old['contact'] ?? '') ?>" required>
                        <div class="error-msg" id="contactError">Contact number is required</div>
                    </div>

                    <!-- Address Components -->
                    <div class="section-title">Home Address</div>
                    <div class="field">
                        <input type="text" id="addr_home" name="addr_home" placeholder="Home No. / Bldg" value="<?= htmlspecialchars($old['addr_home'] ?? '') ?>" required>
                        <div class="error-msg">Required</div>
                    </div>
                    <div class="field">
                        <input type="text" id="addr_street" name="addr_street" placeholder="Street" value="<?= htmlspecialchars($old['addr_street'] ?? '') ?>" required>
                        <div class="error-msg">Required</div>
                    </div>
                    <div class="field">
                        <input type="text" id="addr_brgy" name="addr_brgy" placeholder="Barangay" value="<?= htmlspecialchars($old['addr_brgy'] ?? '') ?>" required>
                        <div class="error-msg">Required</div>
                    </div>
                    <div class="field">
                        <input type="text" id="addr_city" name="addr_city" placeholder="City / Municipality" value="<?= htmlspecialchars($old['addr_city'] ?? '') ?>" required>
                        <div class="error-msg">Required</div>
                    </div>
                    <div class="field">
                        <input type="text" id="addr_prov" name="addr_prov" placeholder="Province" value="<?= htmlspecialchars($old['addr_prov'] ?? '') ?>" required>
                        <div class="error-msg">Required</div>
                    </div>
                    <div class="field">
                        <input type="text" id="addr_zip" name="addr_zip" placeholder="Zip Code" value="<?= htmlspecialchars($old['addr_zip'] ?? '') ?>" required>
                        <div class="error-msg">Required</div>
                    </div>
                </div>

                <!-- Business / Seller Information -->
                <div class="form-grid business-fields" id="businessSection">
                    <div class="section-title">Seller &amp; Business Details</div>
                    <div class="field field-full">
                        <select id="sell_type" name="sell_type">
                            <option value="">Select Seller Type...</option>
                            <option value="retail"    <?= (($old['sell_type'] ?? '') === 'retail')    ? 'selected' : '' ?>>Retail</option>
                            <option value="wholesale" <?= (($old['sell_type'] ?? '') === 'wholesale') ? 'selected' : '' ?>>Wholesale</option>
                            <option value="dropship"  <?= (($old['sell_type'] ?? '') === 'dropship')  ? 'selected' : '' ?>>Dropshipping</option>
                        </select>
                        <div class="error-msg">Seller type is required</div>
                    </div>
                    <div class="field field-full">
                        <input type="text" id="bus_name" name="bus_name" placeholder="Legal business name" value="<?= htmlspecialchars($old['bus_name'] ?? '') ?>">
                        <div class="error-msg">Business name is required</div>
                    </div>
                    <div class="field">
                        <input type="text" id="bus_taxid" name="bus_taxid" placeholder="Tax ID (TIN)" value="<?= htmlspecialchars($old['bus_taxid'] ?? '') ?>">
                        <div class="error-msg">Tax ID is required</div>
                    </div>
                    <div class="field">
                        <input type="text" id="bus_regnum" name="bus_regnum" placeholder="Registration Number" value="<?= htmlspecialchars($old['bus_regnum'] ?? '') ?>">
                        <div class="error-msg">Registration Number is required</div>
                    </div>
                    <div class="field field-full">
                        <input type="text" id="bus_type" name="bus_type" placeholder="Business Type (e.g. Corporation, Sole Prop.)" value="<?= htmlspecialchars($old['bus_type'] ?? '') ?>">
                        <div class="error-msg">Business type is required</div>
                    </div>
                    <div class="field field-full">
                        <input type="tel" id="bus_phone" name="bus_phone" placeholder="Business Phone" value="<?= htmlspecialchars($old['bus_phone'] ?? '') ?>">
                        <div class="error-msg">Business phone is required</div>
                    </div>
                    <div class="field field-full">
                        <input type="text" id="bus_address" name="bus_address" placeholder="Full Business Address" value="<?= htmlspecialchars($old['bus_address'] ?? '') ?>">
                        <div class="error-msg">Business address is required</div>
                    </div>
                </div>



                <div class="terms-text">
                    By selecting Create account, you agree to our <a href="#">User Agreement</a> and acknowledge reading our <a href="#">User Privacy Notice</a>.
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">Create personal account</button>
            </form>
        </div>
    </div>
</div>

<script>
    const btnPersonal = document.getElementById('btnPersonal');
    const btnBusiness = document.getElementById('btnBusiness');
    const businessSection = document.getElementById('businessSection');
    const accountTypeInput = document.getElementById('accountType');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('registerForm');

    // Toggle between Personal and Business
    function setAccountType(type) {
        btnPersonal.classList.remove('active');
        btnBusiness.classList.remove('active');
        businessSection.style.display = 'none';
        
        document.querySelectorAll('#businessSection input, #businessSection select').forEach(el => el.removeAttribute('required'));

        if (type === 'personal') {
            btnPersonal.classList.add('active');
            submitBtn.textContent = 'Create personal account';
            accountTypeInput.value = 'personal';
        } else if (type === 'business') {
            btnBusiness.classList.add('active');
            businessSection.style.display = 'grid';
            submitBtn.textContent = 'Create business account';
            accountTypeInput.value = 'business';
            document.querySelectorAll('#businessSection input, #businessSection select').forEach(el => el.setAttribute('required', 'true'));
        }
    }

    // Initialize state from PHP value
    setAccountType('<?= htmlspecialchars($accType) ?>');

    btnPersonal.addEventListener('click', () => setAccountType('personal'));
    btnBusiness.addEventListener('click', () => setAccountType('business'));

    // Password visibility toggle
    const eyeOpen = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    const eyeClose = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';

    window.togglePw = function(inputId, btn) {
        const inp = document.getElementById(inputId);
        const svg = btn.querySelector('svg');
        if (inp.type === 'password') {
            inp.type = 'text';
            svg.innerHTML = eyeClose;
        } else {
            inp.type = 'password';
            svg.innerHTML = eyeOpen;
        }
    };

    // Form Validation
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        let isValid = true;

        // Clear previous errors
        document.querySelectorAll('.field').forEach(f => f.classList.remove('error'));

        // Check required fields based on account type
        let fieldsToCheck = form.querySelectorAll('.form-grid:not(#businessSection) input[required]');

        fieldsToCheck.forEach(input => {
            if (!input.value.trim()) {
                input.closest('.field').classList.add('error');
                isValid = false;
            }
        });

        // Email validation (must contain @)
        const email = document.getElementById('email');
        if (email.value.trim() && !email.value.includes('@')) {
            email.closest('.field').classList.add('error');
            isValid = false;
        }

        // Contact number validation (only numbers, exactly 11 digits)
        const contact = document.getElementById('contact');
        const contactError = document.getElementById('contactError');
        if (contact.value.trim() && !/^[0-9]{11}$/.test(contact.value.trim())) {
            contact.closest('.field').classList.add('error');
            if (contactError) contactError.textContent = 'Contact number must be exactly 11 digits.';
            isValid = false;
        } else if (!contact.value.trim()) {
            if (contactError) contactError.textContent = 'Contact number is required';
        }

        // Password match validation
        const pw = document.getElementById('password');
        const cpw = document.getElementById('cpassword');
        if (pw.value !== cpw.value && cpw.value.trim() !== '') {
            cpw.closest('.field').classList.add('error');
            isValid = false;
        }

        if (isValid) {
            form.submit();
        }
    });
</script>
</body>
</html>
