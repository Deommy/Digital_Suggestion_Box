<?php
// check-session.php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Anti-cache (important for logout)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

echo json_encode([
    'loggedIn' => isset($_SESSION['user_id']) && !empty($_SESSION['user_id']),
    'role' => $_SESSION['role'] ?? null,
    'fullName' => $_SESSION['full_name'] ?? null,
    'email' => $_SESSION['email'] ?? null
]);
