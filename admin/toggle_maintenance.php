<?php
session_start();
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || !hasAdminPermission('manage_settings')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$status = isset($_POST['status']) && $_POST['status'] == '1' ? '1' : '0';

$stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
$stmt->bind_param("s", $status);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
