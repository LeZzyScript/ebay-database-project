<?php
require_once('config/firebase.php');

$email = 'admin@ebay.com';
$password = 'ad123min456';
$username = 'admin';
$fname = 'Super';
$lname = 'Admin';

try {
    // Check if user already exists in Auth
    try {
        $existingUser = $auth->getUserByEmail($email);
        $uid = $existingUser->uid;
        echo "User already exists in Firebase Auth. UID: $uid<br>";
    } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
        // Create user in Firebase Auth
        $user = $auth->createUser([
            'email' => $email,
            'password' => $password,
            'displayName' => "$fname $lname"
        ]);
        $uid = $user->uid;
        echo "Successfully created user in Firebase Auth. UID: $uid<br>";
    }

    $today = date('Y-m-d');
    
    // Create/update user in Firebase Realtime Database
    setData("users/{$uid}", [
        'accountType' => 'admin',
        'dateRegistered' => $today,
        'status' => 'active',
        'profile' => [
            'firstName' => $fname,
            'lastName' => $lname,
            'accountName' => $username,
            'email' => $email,
            'contact' => '09123456789',
            'address' => 'Manila, Philippines'
        ]
    ]);
    echo "Successfully wrote user profile to database.<br>";

    // Check if admin record already exists in 'admins' node
    $adminsData = getData('admins');
    $adminId = null;
    if ($adminsData) {
        foreach ($adminsData as $id => $rec) {
            if (isset($rec['userId']) && $rec['userId'] === $uid) {
                $adminId = $id;
                break;
            }
        }
    }

    if (!$adminId) {
        $adminId = generateId('ADMN');
    }

    setData("admins/{$adminId}", [
        'userId' => $uid,
        'level' => 3,
        'permissions' => 'full',
        'lastLogin' => null
    ]);
    echo "Successfully wrote admin record to database. Admin ID: $adminId<br>";
    echo "<strong>Admin setup completed!</strong> You can now log in using email: <strong>$email</strong> or username: <strong>$username</strong> and password: <strong>$password</strong>.<br>";

} catch (Exception $e) {
    echo "Error creating admin account: " . $e->getMessage() . "<br>";
}
