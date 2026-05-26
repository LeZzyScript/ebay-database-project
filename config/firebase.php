<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Database;

// Initialize Firebase
$factory = (new Factory)
    ->withServiceAccount(__DIR__ . '/firebase-service-account.json')
    ->withDatabaseUri('https://ebay-project-bf896-default-rtdb.asia-southeast1.firebasedatabase.app');

// Firebase instances
$database = $factory->createDatabase();
$auth = $factory->createAuth();

// Helper function to get database reference
function getDbRef($path = '') {
    global $database;
    if (empty($path)) {
        return $database->getReference();
    }
    return $database->getReference($path);
}

// Helper function to get data
function getData($path) {
    $snapshot = getDbRef($path)->getSnapshot();
    return $snapshot->exists() ? $snapshot->getValue() : null;
}

// Helper function to set data
function setData($path, $data) {
    return getDbRef($path)->set($data);
}

// Helper function to update data
function updateData($path, $data) {
    if (is_array($data)) {
        return getDbRef($path)->update($data);
    } else {
        return getDbRef($path)->set($data);
    }
}

// Helper function to push data (generates unique key)
function pushData($path, $data) {
    $newReference = getDbRef($path)->push($data);
    return $newReference->getKey();
}

// Helper function to delete data
function deleteData($path) {
    return getDbRef($path)->remove();
}

// Helper function to query data
function queryData($path, $orderBy, $limitToLast = null) {
    $query = getDbRef($path)->orderByChild($orderBy);
    if ($limitToLast !== null) {
        $query = $query->limitToLast($limitToLast);
    }
    $snapshot = $query->getSnapshot();
    return $snapshot->exists() ? $snapshot->getValue() : null;
}

// Helper function to check if user is logged in via Firebase Auth
function isUserLoggedIn() {
    if (!isset($_SESSION['firebase_uid'])) {
        return false;
    }
    return !empty($_SESSION['firebase_uid']);
}

// Helper function to get current user ID
function getCurrentUserId() {
    return $_SESSION['firebase_uid'] ?? null;
}

// Helper function to get current user data
function getCurrentUserData() {
    $uid = getCurrentUserId();
    if (!$uid) {
        return null;
    }
    return getData("users/{$uid}");
}

// Generate unique ID (8 characters like original system)
function generateId($prefix = '') {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $prefix . $randomString;
}
