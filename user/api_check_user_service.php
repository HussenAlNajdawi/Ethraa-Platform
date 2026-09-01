<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo json_encode(['has_service' => false]);
    exit;
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT service_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if($row = $res->fetch_assoc()) {
    if (!empty($row['service_id'])) {
        echo json_encode(['has_service' => true]);
        exit;
    }
}
echo json_encode(['has_service' => false]);
?>
