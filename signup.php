<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit;
}

// Get inputs (match your JS: fullName, email, password)
$fullName = sanitizeInput($_POST['fullName'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($fullName === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please fill in all fields."]);
    exit;
}

// ✅ Institutional email only
if (!preg_match('/@panpacificu\.edu\.ph$/i', $email)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Please use a valid institutional email (@panpacificu.edu.ph)"]);
    exit;
}

// Password rules (same idea as JS)
if (strlen($password) < 8 || !preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Password must be 8+ characters and include uppercase, lowercase, and number."]);
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();

    // If already verified, block
    if ((int)$row['is_verified'] === 1) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Email already registered. Please login."]);
        exit;
    }

    // If not verified, you can resend code (optional). We'll allow re-send:
    $userId = (int)$row['id'];
    $code = generateVerificationCode();
    $expiresAt = date('Y-m-d H:i:s', time() + 10 * 60); // 10 minutes

    $upd = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires_at = ? WHERE id = ?");
    $upd->bind_param("ssi", $code, $expiresAt, $userId);
    $upd->execute();

    $message = "
        <h2>Email Verification</h2>
        <p>Hello <b>{$fullName}</b>,</p>
        <p>Your verification code is:</p>
        <h1 style='letter-spacing:4px'>{$code}</h1>
        <p>This code will expire in <b>10 minutes</b>.</p>
    ";

    $sent = sendEmail($email, "Your Verification Code", $message);

    if (!$sent) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to send verification email."]);
        exit;
    }

    echo json_encode(["success" => true, "message" => "Verification code resent. Please check your email."]);
    exit;
}

// Create user (student by default)
$hashed = password_hash($password, PASSWORD_DEFAULT);
$code = generateVerificationCode();
$expiresAt = date('Y-m-d H:i:s', time() + 10 * 60); // 10 minutes
$role = 'student';
$isVerified = 0;

$insert = $conn->prepare("
    INSERT INTO users (full_name, email, password_hash, role, is_verified, verification_code, verification_expires_at, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");
$insert->bind_param("ssssiss", $fullName, $email, $hashed, $role, $isVerified, $code, $expiresAt);

if (!$insert->execute()) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Signup failed. Please try again."]);
    exit;
}

// Send verification email
$message = "
    <h2>Email Verification</h2>
    <p>Hello <b>{$fullName}</b>,</p>
    <p>Your verification code is:</p>
    <h1 style='letter-spacing:4px'>{$code}</h1>
    <p>This code will expire in <b>10 minutes</b>.</p>
";

$sent = sendEmail($email, "Your Verification Code", $message);

if (!$sent) {
    // Optional: delete user if email failed
    $newId = $conn->insert_id;
    $del = $conn->prepare("DELETE FROM users WHERE id = ?");
    $del->bind_param("i", $newId);
    $del->execute();

    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Could not send verification email. Please try again."]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Account created! Please check your email for the verification code."
]);
