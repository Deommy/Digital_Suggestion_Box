<?php
// student-dashboard.php
require_once 'config.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ No redirect loop: one clean redirect only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.html');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ✅ Your schema uses users.user_id (NOT users.id)
$stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: logout.php');
    exit;
}

$userName  = $user['full_name'];
$userEmail = $user['email'];

// Load HTML template
$html = file_get_contents(__DIR__ . '/student-dashboard.html');

echo strtr($html, [
    '{{USER_NAME}}'  => htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
    '{{USER_EMAIL}}' => htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'),
]);

