<?php
// php/admin_login_process.php
require_once '../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: ../admin/admin_login.php?error=csrf_error");
        exit();
    }

    require_once '../admin/admin_functions.php';

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT admin_id, username, password, full_name, role, permissions, status, failed_attempts, lockout_time, session_version FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $stmt->close();

        // 1. فحص هل الحساب معطل
        if (isset($row['status']) && $row['status'] === 'inactive') {
            header("Location: ../admin/admin_login.php?error=account_disabled");
            exit();
        }

        // 2. فحص القفل المؤقت
        if ($row['lockout_time'] && strtotime($row['lockout_time']) > time()) {
            header("Location: ../admin/admin_login.php?error=locked");
            exit();
        }

        if (password_verify($password, $row['password'])) {
            // تصفير المحاولات الفاشلة
            $rst_stmt = $conn->prepare("UPDATE admins SET failed_attempts = 0, lockout_time = NULL WHERE admin_id = ?");
            $rst_stmt->bind_param("i", $row['admin_id']);
            $rst_stmt->execute();
            $rst_stmt->close();

            session_regenerate_id(true);
            
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['admin_name'] = $row['full_name'];
            $_SESSION['admin_username'] = $row['username'];
            $_SESSION['admin_role'] = $row['role'] ?? 'sub_admin';
            $perms = json_decode($row['permissions'] ?? '[]', true);
            $_SESSION['admin_permissions'] = is_array($perms) ? $perms : [];
            $_SESSION['role'] = 'admin';
            $_SESSION['session_version'] = (int)($row['session_version'] ?? 1);
            $_SESSION['last_activity'] = time();

            if (isset($_POST['remember_me'])) {
                $token = bin2hex(random_bytes(20));
                $cookie_value = $row['admin_id'] . ':' . $token;
                $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                setcookie('admin_remember_me', $cookie_value, [
                    'expires'  => time() + (86400 * 30),
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $is_https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                
                $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                $rem_stmt = $conn->prepare("UPDATE admins SET remember_token = ? WHERE admin_id = ?");
                $rem_stmt->bind_param("si", $hashed_token, $row['admin_id']);
                $rem_stmt->execute();
                $rem_stmt->close();
            }

            logAdminAction($conn, $row['admin_id'], 'admin_login_success', 'تسجيل دخول ناجح للمشرف: ' . $row['username']);

            header("Location: ../admin/dashboard.php");
            exit();
        } else {
            // زيادة عداد المحاولات الفاشلة
            $new_fails = (int)($row['failed_attempts'] ?? 0) + 1;
            if ($new_fails >= 5) {
                $lock_until = date('Y-m-d H:i:s', time() + (15 * 60));
                $up_fail = $conn->prepare("UPDATE admins SET failed_attempts = ?, lockout_time = ? WHERE admin_id = ?");
                $up_fail->bind_param("isi", $new_fails, $lock_until, $row['admin_id']);
                $up_fail->execute();
                $up_fail->close();
                logAdminAction($conn, $row['admin_id'], 'admin_locked', 'قفل مؤقت لحساب المشرف لتجاوز 5 محاولات فاشلة');
                header("Location: ../admin/admin_login.php?error=locked");
            } else {
                $up_fail = $conn->prepare("UPDATE admins SET failed_attempts = ? WHERE admin_id = ?");
                $up_fail->bind_param("ii", $new_fails, $row['admin_id']);
                $up_fail->execute();
                $up_fail->close();
                logAdminAction($conn, $row['admin_id'], 'admin_login_failed', 'محاولة فاشلة لتسجيل الدخول');
                header("Location: ../admin/admin_login.php?error=invalid_credentials");
            }
            exit();
        }
    } else {
        $stmt->close();
        header("Location: ../admin/admin_login.php?error=invalid_credentials");
        exit();
    }
} else {
    header("Location: ../admin/admin_login.php");
    exit();
}
?>
