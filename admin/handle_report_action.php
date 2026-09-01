<?php
session_start();
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
requireAdminPermission('manage_reports');

if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    die("طلب غير صالح.");
}

$action = $_GET['action'] ?? '';
$user_id = intval($_GET['user_id'] ?? 0);
$report_id = intval($_GET['report_id'] ?? 0);

if ($user_id > 0 && $report_id > 0) {
    $action_desc = '';
    if ($action === 'mute_1h') {
        $stmt = $conn->prepare("UPDATE users SET muted_until = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $action_desc = "كتم مستخدم لمدة ساعة بناءً على بلاغ (رقم المستخدم: $user_id، رقم البلاغ: $report_id)";
    } elseif ($action === 'ban_1d') {
        $stmt = $conn->prepare("UPDATE users SET banned_until = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $action_desc = "حظر مؤقت لمستخدم ليوم واحد بناءً على بلاغ (رقم المستخدم: $user_id، رقم البلاغ: $report_id)";
    }
    
    // Mark report as resolved
    $stmt = $conn->prepare("UPDATE reports SET status = 'resolved' WHERE report_id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $stmt->close();
    
    if ($action_desc) {
        logAdminAction($conn, $_SESSION['admin_id'], 'MODERATE_REPORT', $action_desc);
    }
}

header("Location: reports.php");
exit();
?>
