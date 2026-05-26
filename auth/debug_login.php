<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../config/firebase.php');

echo "<h2>Debug Login Test</h2>";
echo "<pre>";

// 1. Check session
echo "=== SESSION ===\n";
print_r($_SESSION);

// 2. Test form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    echo "\n=== LOGIN ATTEMPT ===\n";
    echo "Email: " . htmlspecialchars($email) . "\n";
    echo "Password length: " . strlen($password) . "\n";
    
    try {
        // Try sign in
        echo "\nCalling signInWithEmailAndPassword...\n";
        $user = $auth->signInWithEmailAndPassword($email, $password);
        echo "Sign-in SUCCESS\n";
        echo "User object class: " . get_class($user) . "\n";
        echo "User properties: ";
        print_r($user);
        
        // Check UID access
        $uid = $user->uid ?? null;
        echo "\nUID from \$user->uid: " . var_export($uid, true) . "\n";
        
        // Try alternate property names
        if (!$uid) {
            $reflection = new ReflectionClass($user);
            $props = $reflection->getProperties();
            echo "Available properties:\n";
            foreach ($props as $p) {
                $p->setAccessible(true);
                echo "  " . $p->getName() . " = " . var_export($p->getValue($user), true) . "\n";
            }
        }
        
        if ($uid) {
            echo "\n=== FETCHING USER DATA ===\n";
            $userData = getData("users/{$uid}");
            echo "userData result: ";
            print_r($userData);
            
            if ($userData) {
                echo "\naccountType: " . ($userData['accountType'] ?? 'NOT SET') . "\n";
                echo "profile.email: " . ($userData['profile']['email'] ?? 'NOT SET') . "\n";
            } else {
                echo "\nERROR: No user data found in database for uid: $uid\n";
                
                // List all users to check structure
                echo "\n=== ALL USERS IN DB ===\n";
                $allUsers = getData('users');
                if ($allUsers) {
                    foreach ($allUsers as $k => $v) {
                        echo "UID: $k => accountType: " . ($v['accountType'] ?? 'N/A') . "\n";
                    }
                } else {
                    echo "No users found in 'users' path\n";
                }
            }
        }
        
    } catch (\Kreait\Firebase\Exception\Auth\InvalidPassword $e) {
        echo "ERROR: InvalidPassword - " . $e->getMessage() . "\n";
    } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
        echo "ERROR: UserNotFound - " . $e->getMessage() . "\n";
    } catch (\Exception $e) {
        echo "ERROR: " . get_class($e) . " - " . $e->getMessage() . "\n";
        echo "Trace:\n" . $e->getTraceAsString() . "\n";
    }
}

echo "</pre>";
?>
<form method="POST">
    <label>Email: <input type="email" name="email" required style="margin:4px;padding:4px;" /></label><br>
    <label>Password: <input type="password" name="password" required style="margin:4px;padding:4px;" /></label><br>
    <button type="submit" style="margin:8px;padding:6px 16px;">Test Login</button>
</form>
