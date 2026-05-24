<?php
// Admin authentication helper
session_start();

function isAdminLoggedIn() {
    return isset($_SESSION['account_id']) && 
           isset($_SESSION['is_admin']) && 
           $_SESSION['is_admin'] === true;
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header("Location: ../../auth/signin.php");
        exit();
    }
}

function requireAdminLevel($minLevel) {
    requireAdminLogin();
    if (!isset($_SESSION['admin_level']) || $_SESSION['admin_level'] < $minLevel) {
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
}

function hasAdminPermission($permission) {
    if (!isAdminLoggedIn()) return false;
    $perms = explode(',', $_SESSION['admin_perm'] ?? '');
    return in_array($permission, $perms) || ($_SESSION['admin_level'] ?? 0) >= 3;
}
?>