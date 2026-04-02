<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('html_errors', 0); // ← IMPORTANT

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ✅ Support JSON body
$raw = file_get_contents('php://input');
$jsonData = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $jsonData = $decoded;
}

// ✅ Combine: FormData + JSON + Querystring
$INPUT = array_merge($_GET ?? [], $_POST ?? [], $jsonData ?? []);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = $INPUT['action'] ?? '';

// ---------- Router ----------
if ($method === 'POST') {
    if ($action === 'send')   { handleSendMessage($INPUT);   exit; }
    if ($action === 'edit')   { handleEditMessage($INPUT);   exit; }
    if ($action === 'delete') { handleDeleteMessage($INPUT); exit; }

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request (missing/unknown action)',
        'debug' => [
            'method' => $method,
            'contentType' => $_SERVER['CONTENT_TYPE'] ?? null,
            'receivedKeys' => array_keys($INPUT),
        ]
    ]);
    exit;
}

if ($method === 'GET') {
    if ($action === 'getMessages') { handleGetMessages($INPUT); exit; }
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;


// ---------- Helpers ----------
function toBool($v) {
    if (is_bool($v)) return $v;
    if (is_numeric($v)) return ((int)$v) === 1;
    $s = strtolower(trim((string)$v));
    return in_array($s, ['1','true','yes','on'], true);
}

function respondDbError($connOrStmt, $publicMsg = 'Database error') {
    $err = '';
    if ($connOrStmt instanceof mysqli_stmt) $err = $connOrStmt->error;
    elseif ($connOrStmt instanceof mysqli)  $err = $connOrStmt->error;
    echo json_encode(['success' => false, 'message' => $publicMsg, 'sqlError' => $err]);
}

function normalizeChannel($INPUT): string {
    // Prefer explicit channel
    if (!empty($INPUT['channel'])) {
        $ch = strtolower(trim((string)$INPUT['channel']));
        return ($ch === 'anon') ? 'anon' : 'nonanon';
    }
    // Backward compat: old toggle param isAnonymous
    $isAnon = toBool($INPUT['isAnonymous'] ?? '0');
    return $isAnon ? 'anon' : 'nonanon';
}

function getConversationIdIfExists(mysqli $conn, int $studentId, string $channel): int {
    $channel = ($channel === 'anon') ? 'anon' : 'nonanon';
    $stmt = $conn->prepare("SELECT conversation_id FROM conversations WHERE student_id=? AND channel=? LIMIT 1");
    if (!$stmt) return 0;
    $stmt->bind_param("is", $studentId, $channel);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ? (int)$row['conversation_id'] : 0;
}


// ---------- Handlers ----------

// ✅ SEND message to correct thread (anon/nonanon)
function handleSendMessage($INPUT) {
    global $userId;

    $message = sanitizeInput($INPUT['message'] ?? '');
    if ($message === '') {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        return;
    }

    $channel = normalizeChannel($INPUT); // anon | nonanon

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // ✅ Create or reuse the correct conversation for this channel
    // (function must be added in config.php Step 3.1)
    $convoId = getOrCreateConversation($conn, $userId, $channel);

    // ✅ Insert message into messages table
    $stmt = $conn->prepare("
        INSERT INTO messages (conversation_id, sender_role, sender_id, message_text, is_deleted, is_edited, created_at)
        VALUES (?, 'student', ?, ?, 0, 0, NOW())
    ");
    if (!$stmt) {
        respondDbError($conn, 'Prepare failed');
        $conn->close();
        return;
    }

    $stmt->bind_param("iis", $convoId, $userId, $message);

    if (!$stmt->execute()) {
        respondDbError($stmt, 'Failed to send message');
        $stmt->close();
        $conn->close();
        return;
    }
    $stmt->close();

    // ✅ Update last_message_at so admin list sorts correctly
    $conn->query("UPDATE conversations SET last_message_at = NOW() WHERE conversation_id=" . (int)$convoId);

    $conn->close();
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'conversationId' => $convoId,
        'channel' => $channel
    ]);
}


// ✅ EDIT message (only student messages in this convo)
function handleEditMessage($INPUT) {
    global $userId;

    $messageId = (int)($INPUT['messageId'] ?? 0); // NOTE: now messageId (not suggestionId)
    $newMessage = sanitizeInput($INPUT['message'] ?? '');

    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid messageId']);
        return;
    }
    if ($newMessage === '') {
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // Check ownership + not deleted + student role
    $check = $conn->prepare("
        SELECT is_deleted
        FROM messages
        WHERE message_id=? AND sender_role='student' AND sender_id=?
        LIMIT 1
    ");
    if (!$check) {
        respondDbError($conn, 'Prepare failed');
        $conn->close();
        return;
    }

    $check->bind_param("ii", $messageId, $userId);
    $check->execute();
    $res = $check->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $check->close();

    if (!$row) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        return;
    }
    if ((int)$row['is_deleted'] === 1) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'You cannot edit a deleted message']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE messages
        SET message_text=?, is_edited=1, edited_at=NOW()
        WHERE message_id=? AND sender_role='student' AND sender_id=?
    ");
    if (!$stmt) {
        respondDbError($conn, 'Prepare failed');
        $conn->close();
        return;
    }

    $stmt->bind_param("sii", $newMessage, $messageId, $userId);

    if (!$stmt->execute()) {
        respondDbError($stmt, 'Failed to edit message');
        $stmt->close();
        $conn->close();
        return;
    }

    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Message updated']);
}


// ✅ DELETE message (soft delete)
function handleDeleteMessage($INPUT) {
    global $userId;

    $messageId = (int)($INPUT['messageId'] ?? 0); // NOTE: now messageId (not suggestionId)

    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid messageId']);
        return;
    }

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE messages
        SET is_deleted=1, deleted_at=NOW()
        WHERE message_id=? AND sender_role='student' AND sender_id=?
    ");
    if (!$stmt) {
        respondDbError($conn, 'Prepare failed');
        $conn->close();
        return;
    }

    $stmt->bind_param("ii", $messageId, $userId);

    if (!$stmt->execute()) {
        respondDbError($stmt, 'Failed to delete message');
        $stmt->close();
        $conn->close();
        return;
    }

    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'message' => 'Message deleted']);
}


// ✅ GET messages for the selected channel thread
function handleGetMessages($INPUT) {
    global $userId;

    $channel = normalizeChannel($INPUT); // anon | nonanon

    $conn = getDBConnection();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }

    // IMPORTANT: for get, do NOT auto-create convo if none exists.
    $convoId = getConversationIdIfExists($conn, $userId, $channel);
    if ($convoId <= 0) {
        $conn->close();
        echo json_encode(['success' => true, 'channel' => $channel, 'conversationId' => 0, 'messages' => []]);
        return;
    }

    // Get display name for this convo
    $stmtHeader = $conn->prepare("
        SELECT c.channel, c.anon_alias, u.full_name
        FROM conversations c
        JOIN users u ON u.user_id = c.student_id
        WHERE c.conversation_id = ?
        LIMIT 1
    ");
    $displayName = "Student";
    if ($stmtHeader) {
    $stmtHeader->bind_param("i", $convoId);
    $stmtHeader->execute();
    $resH = $stmtHeader->get_result();

    if ($h = $resH->fetch_assoc()) {
        if ($h['channel'] === 'anon') {
            $displayName = getOrCreateAnonymousAlias($conn, $userId);
        } else {
            $displayName = $h['full_name'] ?? 'Student';
        }
    }

    $stmtHeader->close();
}

    $stmt = $conn->prepare("
        SELECT message_id, sender_role, message_text, created_at, is_deleted, is_edited, deleted_at, edited_at
        FROM messages
        WHERE conversation_id = ?
        ORDER BY created_at ASC
    ");
    if (!$stmt) {
        respondDbError($conn, 'Prepare failed');
        $conn->close();
        return;
    }

    $stmt->bind_param("i", $convoId);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'messageId'   => (int)$row['message_id'],
            'senderRole'  => $row['sender_role'], // student/admin
            'senderName'  => ($row['sender_role'] === 'admin') ? 'Admin' : $displayName,
            'message'     => $row['message_text'],
            'createdAt'   => $row['created_at'],
            'isDeleted'   => ((int)$row['is_deleted'] === 1 ? 1 : 0),
            'isEdited'    => ((int)$row['is_edited'] === 1 ? 1 : 0),
            'deletedAt'   => $row['deleted_at'],
            'editedAt'    => $row['edited_at'],
        ];
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'channel' => $channel,
        'conversationId' => $convoId,
        'messages' => $messages
    ]);
}
?>
