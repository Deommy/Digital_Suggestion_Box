<?php
// Authentication Handler
// auth.php

require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'signup':
        handleSignup();
        break;
    case 'verify':
        handleVerify();
        break;
    case 'resend':
        handleResend();
        break;
    case 'login':
        handleLogin();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleSignup() {
    $fullName = sanitizeInput($_POST['fullName'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($fullName) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    if (strlen($fullName) < 3) {
        echo json_encode(['success' => false, 'message' => 'Full name must be at least 3 characters']);
        return;
    }

    if (!isValidInstitutionalEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Please use a valid institutional email (@panpacificu.edu.ph)']);
        return;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain uppercase, lowercase, and numbers']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        $stmt->close();
        $conn->close();
        return;
    }
    $stmt->close();

    // Generate verification code
    $verificationCode = generateVerificationCode();
    $verificationExpires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, verification_code, verification_expires) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $fullName, $email, $passwordHash, $verificationCode, $verificationExpires);

    if ($stmt->execute()) {
        // Send verification email
        $subject = "Verify Your Email - Digital Suggestion Box";
        $message = "Hello $fullName,\n\nYour verification code is: $verificationCode\n\nThis code will expire in 15 minutes.\n\nThank you!";
        sendEmail($email, $subject, $message);

        echo json_encode([
            'success' => true, 
            'message' => 'Account created! Please check your email for verification code.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create account']);
    }

    $stmt->close();
    $conn->close();
}

function handleVerify() {
    $email = sanitizeInput($_POST['email'] ?? '');
    $code = sanitizeInput($_POST['code'] ?? '');

    if (empty($email) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Email and code are required']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // Check verification code
    $stmt = $conn->prepare("SELECT user_id, verification_code, verification_expires FROM users WHERE email = ? AND is_verified = FALSE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or account already verified']);
        $stmt->close();
        $conn->close();
        return;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Check if code matches
    if ($user['verification_code'] !== $code) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
        $conn->close();
        return;
    }

    // Check if code expired
    if (strtotime($user['verification_expires']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Verification code has expired']);
        $conn->close();
        return;
    }

    // Verify user
    $stmt = $conn->prepare("UPDATE users SET is_verified = TRUE, verification_code = NULL, verification_expires = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Email verified successfully! You can now login.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Verification failed']);
    }

    $stmt->close();
    $conn->close();
}

function handleResend() {
    $email = sanitizeInput($_POST['email'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // Check if user exists and not verified
    $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ? AND is_verified = FALSE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or account already verified']);
        $stmt->close();
        $conn->close();
        return;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Generate new code
    $verificationCode = generateVerificationCode();
    $verificationExpires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Update code
    $stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $verificationCode, $verificationExpires, $user['user_id']);
    
    if ($stmt->execute()) {
        // Send email
        $subject = "New Verification Code - Digital Suggestion Box";
        $message = "Hello {$user['full_name']},\n\nYour new verification code is: $verificationCode\n\nThis code will expire in 15 minutes.\n\nThank you!";
        sendEmail($email, $subject, $message);

        echo json_encode(['success' => true, 'message' => 'New verification code sent!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to resend code']);
    }

    $stmt->close();
    $conn->close();
}

function handleLogin() {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // Get user
    $stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role, is_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        $stmt->close();
        $conn->close();
        return;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Check if verified
    if (!$user['is_verified']) {
        echo json_encode(['success' => false, 'message' => 'Please verify your email first']);
        $conn->close();
        return;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        $conn->close();
        return;
    }

    // Update last login
    $stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $stmt->close();

    // Set session
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    echo json_encode([
        'success' => true, 
        'message' => 'Login successful!',
        'role' => $user['role']
    ]);

    $conn->close();
}
?>