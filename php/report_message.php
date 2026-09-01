<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'غير مصرح']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'report_message') {
    echo json_encode(['status' => 'error', 'message' => 'طلب غير صالح']);
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'أخرى';

if ($request_id <= 0 || $message_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'بيانات مفقودة']);
    exit();
}

// التحقق من أن الرسالة تنتمي للطلب وأن المستخدم طرف في المحادثة
$stmt_msg = $conn->prepare("
    SELECT m.message_text, m.sender_id, m.request_id, r.requester_id, r.provider_id 
    FROM messages m 
    JOIN requests r ON m.request_id = r.request_id 
    WHERE m.message_id = ? AND m.request_id = ?
");
$stmt_msg->bind_param("ii", $message_id, $request_id);
$stmt_msg->execute();
$msg_data = $stmt_msg->get_result()->fetch_assoc();
$stmt_msg->close();

if (!$msg_data || ($user_id != $msg_data['requester_id'] && $user_id != $msg_data['provider_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'غير مصرح لك بالإبلاغ عن هذه الرسالة']);
    exit();
}

$title = 'إبلاغ: ' . $reason;
$content = 'محتوى الرسالة المُبلَّغ عنها: "' . $msg_data['message_text'] . '"';

$stmt = $conn->prepare("INSERT INTO reports (user_id, message_id, title, content) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiss", $user_id, $message_id, $title, $content);

if ($stmt->execute()) {
    // التحقق من عدد البلاغات على نفس الرسالة
    $stmt_count = $conn->prepare("SELECT COUNT(*) as c FROM reports WHERE message_id = ?");
    $stmt_count->bind_param("i", $message_id);
    $stmt_count->execute();
    $report_count = $stmt_count->get_result()->fetch_assoc()['c'];
    $stmt_count->close();

    if ($report_count >= 3) {
        // إخفاء الرسالة وتغريم المرسل
        $conn->query("UPDATE messages SET is_hidden = 1 WHERE message_id = $message_id");
        require_once 'moderation_system.php';
        applyUserPenalty($conn, $msg_data['sender_id'], 1);
    }

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'حدث خطأ أثناء تسجيل البلاغ']);
}
$stmt->close();
?>

