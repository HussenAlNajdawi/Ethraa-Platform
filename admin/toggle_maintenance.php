<?php
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || !hasAdminPermission('manage_settings')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

$status = isset($_POST['status']) && $_POST['status'] == '1' ? '1' : '0';

$stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
$stmt->bind_param("s", $status);
if ($stmt->execute()) {
    $status_text = ($status === '1') ? 'تفعيل وضع الصيانة' : 'تعطيل وضع الصيانة';
    logAdminAction($conn, $_SESSION['admin_id'], 'TOGGLE_MAINTENANCE', "قام بـ $status_text للمنصة");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
$stmt->close();
?>
