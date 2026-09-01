<?php
require_once '../config/session_bootstrap.php';

// حذف التوكن من قاعدة البيانات دائماً إذا كان المستخدم مسجل دخول
if (isset($_SESSION['user_id'])) {
    require_once '../config/db_connect.php';
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// حذف كوكي تذكرني
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// تفريغ وتدمير الجلسة ومسح كوكي الجلسة من المتصفح
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: login.php");
exit();
?>