<?php
require_once '../config/session_bootstrap.php';

// حذف التوكن من قاعدة البيانات دائماً إذا كان الأدمن مسجل دخول
if (isset($_SESSION['admin_id'])) {
    require_once '../config/db_connect.php';
    $stmt = $conn->prepare("UPDATE admins SET remember_token = NULL WHERE admin_id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $stmt->close();
}

// حذف الكوكي إذا وجد
if (isset($_COOKIE['admin_remember_me'])) {
    setcookie('admin_remember_me', '', time() - 3600, '/');
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

header("Location: admin_login.php");
exit();
?>