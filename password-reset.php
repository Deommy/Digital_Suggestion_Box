<?php
// Password Reset Handler
// password-reset.php

require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'forgot_password':
        handleForgotPassword();
        break;
    case 'reset_password':
        handleResetPassword();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleForgotPassword() {
    $email = sanitizeInput($_POST['email'] ?? '');

    // Validation
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        return;
    }

    if (!isValidInstitutionalEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Please use a valid institutional email (@school.edu)']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // Check if user exists and is verified
    $stmt = $conn->prepare("SELECT user_id, full_name, is_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Don't reveal if email doesn't exist (security best practice)
        echo json_encode(['success' => true, 'message' => 'If the email exists, a reset code has been sent']);
        $stmt->close();
        $conn->close();
        return;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user['is_verified']) {
        echo json_encode(['success' => false, 'message' => 'Please verify your email first']);
        $conn->close();
        return;
    }

    // Generate reset code
    $resetCode = generateVerificationCode();
    $resetExpires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Store reset code
    $stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $resetCode, $resetExpires, $user['user_id']);
    
    if ($stmt->execute()) {
        // Send reset code via email
        $subject = "Password Reset Code - Digital Suggestion Box";
        $message = "Hello {$user['full_name']},\n\nYour password reset code is: $resetCode\n\nThis code will expire in 15 minutes.\n\nIf you didn't request this, please ignore this email.\n\nThank you!";
        sendEmail($email, $subject, $message);

        echo json_encode([
            'success' => true, 
            'message' => 'Reset code sent to your email'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate reset code']);
    }

    $stmt->close();
    $conn->close();
}

function handleResetPassword() {
    $email = sanitizeInput($_POST['email'] ?? '');
    $code = sanitizeInput($_POST['code'] ?? '');
    $newPassword = $_POST['newPassword'] ?? '';

    // Validation
    if (empty($email) || empty($code) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    if (strlen($code) !== 6) {
        echo json_encode(['success' => false, 'message' => 'Invalid reset code']);
        return;
    }

    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        return;
    }

    if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain uppercase, lowercase, and numbers']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // Check reset code
    $stmt = $conn->prepare("SELECT user_id, verification_code, verification_expires FROM users WHERE email = ? AND is_verified = TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        $stmt->close();
        $conn->close();
        return;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Check if code matches
    if ($user['verification_code'] !== $code) {
        echo json_encode(['success' => false, 'message' => 'Invalid reset code']);
        $conn->close();
        return;
    }

    // Check if code expired
    if (strtotime($user['verification_expires']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Reset code has expired. Please request a new one.']);
        $conn->close();
        return;
    }

    // Hash new password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password and clear reset code
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, verification_code = NULL, verification_expires = NULL WHERE user_id = ?");
    $stmt->bind_param("si", $passwordHash, $user['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully! You can now login.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    }

    $stmt->close();
    $conn->close();
}
?>