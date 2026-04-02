<?php
session_start();
// ================================
// config.php
// ================================

// --------------------
// DB CONFIG
// --------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'digital_suggestion_box');

// --------------------
// EMAIL CONFIG (GMAIL SENDER)
// --------------------
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', 'classschedule2526@gmail.com'); // ✅ sender Gmail
define('MAIL_PASSWORD', 'yqfwdrakyboijzwk');           // ✅ Gmail App Password
define('MAIL_PORT', 587);

define('MAIL_FROM', 'classschedule2526@gmail.com');     // ✅ same as sender
define('MAIL_FROM_NAME', 'Digital Suggestion Box');

// --------------------
// DB CONNECTION
// --------------------
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        error_log("DB Connection failed: " . $conn->connect_error);
        return null;
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

// --------------------
// SESSION
// --------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --------------------
// AUTH HELPERS
// --------------------
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.html');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: student-dashboard.php');
        exit();
    }
}

function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        header('Location: admin-dashboard.php');
        exit();
    }
}

// --------------------
// INPUT + VALIDATION
// --------------------
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ✅ School email only
function isValidInstitutionalEmail($email) {
    return preg_match('/@panpacificu\.edu\.ph$/i', trim($email));
}

// --------------------
// VERIFICATION CODE
// --------------------
function generateVerificationCode() {
    return str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// --------------------
// REAL EMAIL SENDER (PHPMailer)
// --------------------
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function sendEmail($to, $subject, $messageHtml) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $messageHtml;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

function sendReplyNotification($email, $name) {
    $subject = "Admin Replied to Your Suggestion";

    $message = "
        Hello $name,<br><br>
        <b>Admin has replied to your suggestion.</b><br><br>
        Please log in to your account to view the reply.<br><br>
        Thank you.
    ";

    return sendEmail($email, $subject, $message);
}


// --------------------
// ANONYMOUS NAME
// --------------------
// --------------------
// ANONYMOUS NAME (UNIQUE PER USER)
// --------------------

function getRandomAnonymousName(mysqli $conn): string
{
    $adjectives = [
        'Silent','Curious','Brave','Calm','Wise',
        'Hidden','Gentle','Swift','Quiet','Bright'
    ];

    $animals = [
        'Owl','Fox','Cat','Wolf','Hawk',
        'Bear','Dolphin','Tiger','Panda','Eagle'
    ];

    // try hanggang makakuha ng unique
    do {
        $alias =
            $adjectives[array_rand($adjectives)] . ' ' .
            $animals[array_rand($animals)] . '-' .
            random_int(1000, 9999);

        $stmt = $conn->prepare(
            "SELECT 1 FROM users WHERE anonymous_alias = ? LIMIT 1"
        );
        if (!$stmt) {
            return 'Anonymous-' . random_int(1000, 9999);
        }

        $stmt->bind_param("s", $alias);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

    } while ($exists);

    return $alias;
}

function getOrCreateAnonymousAlias(mysqli $conn, int $userId): string
{
    // 👉 deterministic alias based on userId
    $adjectives = [
        'Silent','Curious','Brave','Calm','Wise',
        'Hidden','Gentle','Swift','Quiet','Bright'
    ];

    $animals = [
        'Owl','Fox','Cat','Wolf','Hawk',
        'Bear','Dolphin','Tiger','Panda','Eagle'
    ];

    // 👉 generate fixed alias based on userId
    $adj = $adjectives[$userId % count($adjectives)];
    $animal = $animals[$userId % count($animals)];
    $number = 1000 + ($userId % 9000);

    $alias = "$adj $animal-$number";

    // 👉 check if already hashed in DB
    $stmt = $conn->prepare(
        "SELECT anonymous_alias FROM users WHERE user_id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (empty($row['anonymous_alias'])) {
        // 👉 store HASH ONLY
        $hashed = hash('sha256', $alias);

        $stmt2 = $conn->prepare(
            "UPDATE users SET anonymous_alias = ? WHERE user_id = ?"
        );
        $stmt2->bind_param("si", $hashed, $userId);
        $stmt2->execute();
        $stmt2->close();
    }

    // 👉 ALWAYS return readable alias for UI
    return $alias;
}
// --------------------
// AI RESPONSE
// --------------------
function generateAIResponse() {
    $responses = [
        "Thank you for your suggestion. An administrator will review and respond shortly.",
        "We appreciate your feedback. Please wait for the official reply from an administrator.",
        "Your suggestion has been received. An admin will reply as soon as possible.",
        "Thank you! Your message is now pending review by the admin team."
    ];
    return $responses[array_rand($responses)];
}

// --------------------
// CONVERSATION HELPER
// --------------------
function getOrCreateConversation(mysqli $conn, int $studentId, string $channel): int
{
    $channel = ($channel === 'anon') ? 'anon' : 'nonanon';

    // check existing
    $stmt = $conn->prepare("SELECT conversation_id FROM conversations WHERE student_id=? AND channel=? LIMIT 1");
    if (!$stmt) {
        die("SQL ERROR: " . $conn->error);
    }

    $stmt->bind_param("is", $studentId, $channel);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return (int)$row['conversation_id'];
    }
    $stmt->close();

    // create new
    $anonAlias = null;

    if ($channel === 'anon') {
        $anonAlias = getOrCreateAnonymousAlias($conn, $studentId);

        // 👉 HASH (your requirement)
        $anonAlias = hash('sha256', $anonAlias);
    }

    $stmt2 = $conn->prepare("
        INSERT INTO conversations (student_id, channel, anon_alias, last_message_at)
        VALUES (?, ?, ?, NOW())
    ");

    if (!$stmt2) {
        die("SQL ERROR: " . $conn->error);
    }

    $stmt2->bind_param("iss", $studentId, $channel, $anonAlias);

    if (!$stmt2->execute()) {
        die("EXECUTE ERROR: " . $stmt2->error);
    }

    $id = (int)$stmt2->insert_id;
    $stmt2->close();

    return $id;
}

$host = "localhost";
$db   = "Digital_Suggestion_Box"; // ← PALITAN kung iba name ng DB mo
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB ERROR: " . $e->getMessage());
}