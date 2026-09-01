<?php
require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_POST['request_id'])) {
    echo json_encode(['status' => 'error']);
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'CSRF failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = (int)$_POST['request_id'];

// Check permission
$stmt = $conn->prepare("SELECT requester_id, provider_id FROM requests WHERE request_id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req || ($user_id != $req['requester_id'] && $user_id != $req['provider_id'])) {
    echo json_encode(['status' => 'error']);
    exit();
}

$is_requester = ($user_id == $req['requester_id']);
$column = $is_requester ? 'requester_typing_at' : 'provider_typing_at';

$sql = "UPDATE requests SET $column = NOW() WHERE request_id = ?";
$stmt_up = $conn->prepare($sql);
$stmt_up->bind_param("i", $request_id);
$stmt_up->execute();
$stmt_up->close();

echo json_encode(['status' => 'success']);
?>
