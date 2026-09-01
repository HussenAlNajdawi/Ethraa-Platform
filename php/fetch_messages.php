<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'غير مصرح']);
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
$last_msg_id = isset($_GET['last_msg_id']) ? (int)$_GET['last_msg_id'] : 0;

if ($request_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'رقم الطلب غير صحيح']);
    exit();
}

// Check permission
$sql_check = "SELECT requester_id, provider_id FROM requests WHERE request_id = ?";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'الطلب غير موجود']);
    exit();
}
$req = $res->fetch_assoc();
$stmt->close();

if ($user_id != $req['requester_id'] && $user_id != $req['provider_id']) {
    echo json_encode(['status' => 'error', 'message' => 'ليس لديك صلاحية']);
    exit();
}

// Mark unread messages to this user as read
$sql_update = "UPDATE messages SET is_read = 1 WHERE request_id = ? AND receiver_id = ? AND is_read = 0";
$stmt_up = $conn->prepare($sql_update);
$stmt_up->bind_param("ii", $request_id, $user_id);
$stmt_up->execute();
$stmt_up->close();

// Fetch new messages
$sql_fetch = "SELECT * FROM messages WHERE request_id = ? AND message_id > ? ORDER BY message_id ASC";
$stmt_f = $conn->prepare($sql_fetch);
$stmt_f->bind_param("ii", $request_id, $last_msg_id);
$stmt_f->execute();
$res_f = $stmt_f->get_result();

$messages = [];
while ($row = $res_f->fetch_assoc()) {
    $row['is_mine'] = ($row['sender_id'] == $user_id);
    $row['formatted_time'] = date('h:i A', strtotime($row['created_at']));
    if (isset($row['is_hidden']) && $row['is_hidden'] == 1) {
        $row['message_text'] = '🚫 تم إخفاء هذه الرسالة بسبب بلاغات المستخدمين.';
    }
    $messages[] = $row;
}

$stmt_f->close();

// Fetch the max message_id that has been read by the other party
$sql_read = "SELECT MAX(message_id) as max_read FROM messages WHERE request_id = ? AND sender_id = ? AND is_read = 1";
$stmt_r = $conn->prepare($sql_read);
$stmt_r->bind_param("ii", $request_id, $user_id);
$stmt_r->execute();
$max_read = $stmt_r->get_result()->fetch_assoc()['max_read'];
$stmt_r->close();

// Check if other party is typing
$is_requester = ($user_id == $req['requester_id']);
$typing_col = $is_requester ? 'provider_typing_at' : 'requester_typing_at';
$sql_typing = "SELECT TIMESTAMPDIFF(SECOND, $typing_col, NOW()) as diff FROM requests WHERE request_id = ?";
$stmt_t = $conn->prepare($sql_typing);
$stmt_t->bind_param("i", $request_id);
$stmt_t->execute();
$typing_res = $stmt_t->get_result()->fetch_assoc();
$stmt_t->close();

$is_typing = false;
if ($typing_res && $typing_res['diff'] !== null) {
    if ($typing_res['diff'] >= 0 && $typing_res['diff'] <= 4) {
        $is_typing = true;
    }
}

echo json_encode(['status' => 'success', 'messages' => $messages, 'last_read_id' => $max_read, 'is_typing' => $is_typing]);
?>

