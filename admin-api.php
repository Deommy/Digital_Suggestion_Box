<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function json_out($arr, $code = 200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// ✅ Require admin session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  json_out(['success' => false, 'message' => 'Not authenticated'], 401);
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
  case 'getAnalytics':
    handleGetAnalytics();
    break;
  case 'listConversations':
    handleListConversations();
    break;
  case 'getConversation':
    handleGetConversation();
    break;
  case 'sendMessage':
    handleSendMessage();
    break;
  default:
    json_out(['success' => false, 'message' => 'Invalid action'], 400);
}


// -------------------- Handlers --------------------

function handleGetAnalytics(){
  $conn = getDBConnection();
  if (!$conn) json_out(['success'=>false,'message'=>'DB connection failed'], 500);

  $sql = "
    SELECT
      SUM(CASE WHEN channel='anon' THEN 1 ELSE 0 END) AS anonymous_count,
      SUM(CASE WHEN channel='nonanon' THEN 1 ELSE 0 END) AS nonanonymous_count
    FROM conversations
  ";
  $res = $conn->query($sql);
  if (!$res) {
    $err = $conn->error;
    $conn->close();
    json_out(['success'=>false,'message'=>'Query failed','error'=>$err], 500);
  }

  $row = $res->fetch_assoc() ?: [];
  $conn->close();

  json_out([
    'success' => true,
    'analytics' => [
      'anonymous' => (int)($row['anonymous_count'] ?? 0),
      'nonAnonymous' => (int)($row['nonanonymous_count'] ?? 0),
    ]
  ]);
}

function handleListConversations(){
  $conn = getDBConnection();
  if (!$conn) json_out(['success'=>false,'message'=>'DB connection failed'], 500);

  $filter = strtolower(trim($_GET['filter'] ?? 'all')); // all | anonymous | nonanonymous

  $date = $_GET['date'] ?? ''; // ✅ ADD THIS

  $whereDate = "";

if (!empty($date)) {
    $safeDate = $conn->real_escape_string($date);
    $whereDate = " AND DATE(c.last_message_at) = '$safeDate' ";
}

$where = "WHERE 1=1";

if ($filter === 'anonymous') {
    $where .= " AND c.channel='anon'";
}
else if ($filter === 'nonanonymous') {
    $where .= " AND c.channel='nonanon'";
}

$where .= $whereDate;

  $sql = "
    SELECT
      c.conversation_id,
      c.channel,
      c.anon_alias,
      c.last_message_at,
      u.full_name,
      u.email,
(
  SELECT COUNT(*)
  FROM messages m
  WHERE m.conversation_id = c.conversation_id
) AS message_count,
    
      (
        SELECT m.message_text
        FROM messages m
        WHERE m.conversation_id = c.conversation_id
          AND (m.is_deleted IS NULL OR m.is_deleted = 0)
        ORDER BY m.created_at DESC
        LIMIT 1
      ) AS last_preview,
      (
        SELECT m.sender_role
        FROM messages m
        WHERE m.conversation_id = c.conversation_id
          AND (m.is_deleted IS NULL OR m.is_deleted = 0)
        ORDER BY m.created_at DESC
        LIMIT 1
      ) AS last_sender
    FROM conversations c
    JOIN users u ON u.user_id = c.student_id
    $where
    ORDER BY c.last_message_at DESC, c.conversation_id DESC
    LIMIT 200
  ";

  $res = $conn->query($sql);
  if (!$res) {
    $err = $conn->error;
    $conn->close();
    json_out(['success'=>false,'message'=>'Query failed','error'=>$err], 500);
  }

  $items = [];
  while ($row = $res->fetch_assoc()) {
    $isAnon = ($row['channel'] === 'anon');
if ($isAnon) {
    $displayName = getOrCreateAnonymousAlias($conn, $row['conversation_id']);
} else {
    $displayName = $row['full_name'] ?? 'Unknown';
}
    $items[] = [
      'conversationId' => (int)$row['conversation_id'],
      'channel' => $row['channel'],
      'isAnonymous' => $isAnon ? 1 : 0,
      'senderName' => $displayName,
      'email' => $isAnon ? '' : ($row['email'] ?? ''),
      'createdAt' => $row['last_message_at'] ?? null,
      'lastPreview' => $row['last_preview'] ?? '',
      'lastSender' => $row['last_sender'] ?? '',
      'messageCount' => (int)($row['message_count'] ?? 0),
    ];
  }

  $conn->close();
  json_out(['success'=>true,'messages'=>$items]); // NOTE: "messages" to keep your UI JS stable
}

function handleGetConversation(){
  $conn = getDBConnection();
  if (!$conn) json_out(['success'=>false,'message'=>'DB connection failed'], 500);

  $conversationId = (int)($_GET['conversationId'] ?? 0);
  if ($conversationId <= 0) {
    $conn->close();
    json_out(['success'=>false,'message'=>'Invalid conversationId'], 400);
  }

  // Get convo header info
  $stmt = $conn->prepare("
    SELECT c.channel, c.anon_alias, u.full_name, u.email
    FROM conversations c
    JOIN users u ON u.user_id = c.student_id
    WHERE c.conversation_id = ?
    LIMIT 1
  ");
  if (!$stmt) {
    $err = $conn->error;
    $conn->close();
    json_out(['success'=>false,'message'=>'Prepare failed','error'=>$err], 500);
  }

  $stmt->bind_param("i", $conversationId);
  $stmt->execute();
  $res = $stmt->get_result();
  $hdr = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$hdr) {
    $conn->close();
    json_out(['success'=>false,'message'=>'Conversation not found'], 404);
  }

  $isAnon = ($hdr['channel'] === 'anon');
  if ($isAnon) {
    $displayName = getOrCreateAnonymousAlias($conn, $conversationId);
} else {
    $displayName = $hdr['full_name'] ?? 'Unknown';
}

  // Messages
  $stmt2 = $conn->prepare("
    SELECT message_id, sender_role, message_text, created_at, is_deleted, is_edited, deleted_at, edited_at
    FROM messages
    WHERE conversation_id = ?
    ORDER BY created_at ASC
  ");
  if (!$stmt2) {
    $err = $conn->error;
    $conn->close();
    json_out(['success'=>false,'message'=>'Prepare failed','error'=>$err], 500);
  }

  $stmt2->bind_param("i", $conversationId);
  $stmt2->execute();
  $rres = $stmt2->get_result();

  $msgs = [];
  while ($m = $rres->fetch_assoc()) {
    $msgs[] = [
      'messageId' => (int)$m['message_id'],
      'senderRole' => $m['sender_role'], // student/admin
      'senderName' => ($m['sender_role'] === 'admin') ? 'Admin' : $displayName,
      'message' => $m['message_text'],
      'createdAt' => $m['created_at'],
      'isDeleted' => ((int)($m['is_deleted'] ?? 0) === 1) ? 1 : 0,
      'isEdited' => ((int)($m['is_edited'] ?? 0) === 1) ? 1 : 0,
      'deletedAt' => $m['deleted_at'] ?? null,
      'editedAt' => $m['edited_at'] ?? null,
    ];
  }
  $stmt2->close();
  $conn->close();

  json_out([
    'success' => true,
    'conversation' => [
      'conversationId' => $conversationId,
      'channel' => $hdr['channel'],
      'senderName' => $displayName,
      'email' => $isAnon ? '' : ($hdr['email'] ?? '')
    ],
    'thread' => $msgs
  ]);
}

function handleSendMessage(){
  $conn = getDBConnection();
  if (!$conn) json_out(['success'=>false,'message'=>'DB connection failed'], 500);

  $conversationId = (int)($_POST['conversationId'] ?? 0);
  $reply = trim($_POST['reply'] ?? '');

  if ($conversationId <= 0 || $reply === '') {
    $conn->close();
    json_out(['success'=>false,'message'=>'Missing conversationId or reply'], 400);
  }

  $adminId = (int)($_SESSION['user_id'] ?? 0);

  $stmt = $conn->prepare("
    INSERT INTO messages (conversation_id, sender_role, sender_id, message_text, created_at, is_deleted, is_edited)
    VALUES (?, 'admin', ?, ?, NOW(), 0, 0)
  ");
  if (!$stmt) {
    $err = $conn->error;
    $conn->close();
    json_out(['success'=>false,'message'=>'Prepare failed','error'=>$err], 500);
  }

  $stmt->bind_param("iis", $conversationId, $adminId, $reply);
  if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    json_out(['success'=>false,'message'=>'Failed to send','error'=>$err], 500);
  }

  // ✅ ================= EMAIL TRIGGER =================

// GET USER EMAIL
$stmt2 = $conn->prepare("
  SELECT u.email, u.full_name
  FROM conversations c
  JOIN users u ON u.user_id = c.student_id
  WHERE c.conversation_id = ?
  LIMIT 1
");

$stmt2->bind_param("i", $conversationId);
$stmt2->execute();
$res = $stmt2->get_result();
$user = $res->fetch_assoc();
$stmt2->close();

// SEND EMAIL
if ($user && !empty($user['email'])) {
    sendReplyNotification(
        $user['email'],
        $user['full_name']
    );
}

// ✅ =================================================

  $stmt->close();
  $conn->query("UPDATE conversations SET last_message_at = NOW() WHERE conversation_id=".(int)$conversationId);
  $conn->close();

  json_out(['success'=>true]);
}
