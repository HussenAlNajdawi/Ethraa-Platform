<?php
// php/db_connect.php

// عدنا للإعدادات الافتراضية
$servername = "localhost"; 
$username = "root";
$password = "";
$dbname = "ethraa_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// دالة تنظيف المدخلات (موجودة مسبقاً)
function sanitizeInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); 
    return $conn->real_escape_string($data);
}

// --- التحقق من وضع الصيانة ---
$is_admin_area = stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || stripos($_SERVER['SCRIPT_NAME'], 'admin_login_process.php') !== false;
$is_maintenance_page = stripos($_SERVER['SCRIPT_NAME'], 'maintenance.php') !== false;

if (!$is_admin_area && !$is_maintenance_page) {
    $stmt_m = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    if ($stmt_m) {
        $stmt_m->execute();
        $res_m = $stmt_m->get_result()->fetch_assoc();
        $stmt_m->close();
        if ($res_m && $res_m['setting_value'] === '1') {
            // Check if user is logged in as admin (we need session for this, but it might not be started here)
            // It's safer to just redirect all non-admin routes if session isn't admin
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION['admin_id'])) {
                // إذا كان المستخدم العادي مسجلاً للدخول، نقوم بإنهاء جلسته طرده لصفحة تسجيل الدخول
                if (isset($_SESSION['user_id'])) {
                    // تفريغ الجلسة بالكامل وتدميرها
                    $_SESSION = array();
                    session_destroy();
                    
                    // مسح الكوكيز الخاصة بتسجيل الدخول إذا وجدت
                    if (isset($_COOKIE['remember_me'])) {
                        setcookie('remember_me', '', time() - 3600, '/');
                    }
                    
                    // توجيهه إلى صفحة تسجيل الدخول
                    $in_subfolder = (stripos($_SERVER['SCRIPT_NAME'], '/user/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/php/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
                    $login_url = $in_subfolder ? (stripos($_SERVER['SCRIPT_NAME'], '/user/') !== false ? 'login.php?error=maintenance' : '../user/login.php?error=maintenance') : 'user/login.php?error=maintenance';
                    header("Location: $login_url");
                    exit();
                }

                // إذا لم يكن مسجلاً للدخول، نوجهه لصفحة الصيانة كالمعتاد
                $in_subfolder = (stripos($_SERVER['SCRIPT_NAME'], '/user/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/php/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
                $base_url = $in_subfolder ? '../maintenance.php' : 'maintenance.php';
                header("Location: $base_url");
                exit();
            }
        }
    }
}

// --- إعدادات أمان جلسات PHP قبل بدئها ---
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// ضمان وجود أعمدة الأمان في قاعدة البيانات
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS session_version INT DEFAULT 1");
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS session_version INT DEFAULT 1");
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0");
$conn->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS lockout_time DATETIME NULL");

// --- إعدادات الحماية العامة (Security Headers) ---
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self';");
}

// --- إدارة دورة حياة الجلسات (Session Lifecycle & Security) ---
if (session_status() === PHP_SESSION_ACTIVE) {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // 1. فحص مهلة الخمول (Idle Session Timeout)
    $now = time();
    $idle_limit = isset($_SESSION['admin_id']) ? 1800 : 3600; // 30 دقيقة للمشرفين، 60 دقيقة للمستخدمين

    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > $idle_limit)) {
        $was_admin = isset($_SESSION['admin_id']);
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        $in_subfolder = (stripos($_SERVER['SCRIPT_NAME'], '/user/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/php/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
        if ($was_admin) {
            $redirect_to = $in_subfolder ? (stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? 'admin_login.php?error=timeout' : '../admin/admin_login.php?error=timeout') : 'admin/admin_login.php?error=timeout';
        } else {
            $redirect_to = $in_subfolder ? (stripos($_SERVER['SCRIPT_NAME'], '/user/') !== false ? 'login.php?error=timeout' : '../user/login.php?error=timeout') : 'user/login.php?error=timeout';
        }
        header("Location: $redirect_to");
        exit();
    }
    $_SESSION['last_activity'] = $now;

    // 2. التحقق من صلاحية الجلسة عبر session_version لإبطال الجلسات القديمة فور تغيير كلمة المرور
    if (isset($_SESSION['user_id']) && isset($_SESSION['session_version'])) {
        $stmt_sv = $conn->prepare("SELECT session_version, status, banned_until FROM users WHERE user_id = ?");
        if ($stmt_sv) {
            $stmt_sv->bind_param("i", $_SESSION['user_id']);
            $stmt_sv->execute();
            $user_sec = $stmt_sv->get_result()->fetch_assoc();
            $stmt_sv->close();

            if (!$user_sec || (int)$user_sec['session_version'] !== (int)$_SESSION['session_version'] || $user_sec['status'] !== 'active' || ($user_sec['banned_until'] && strtotime($user_sec['banned_until']) > time())) {
                $_SESSION = array();
                if (isset($_COOKIE['remember_me'])) {
                    setcookie('remember_me', '', time() - 3600, '/');
                }
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();
                $in_subfolder = (stripos($_SERVER['SCRIPT_NAME'], '/user/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/php/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
                $login_url = $in_subfolder ? (stripos($_SERVER['SCRIPT_NAME'], '/user/') !== false ? 'login.php?error=session_expired' : '../user/login.php?error=session_expired') : 'user/login.php?error=session_expired';
                header("Location: $login_url");
                exit();
            }
        }
    } elseif (isset($_SESSION['admin_id']) && isset($_SESSION['session_version'])) {
        $stmt_asv = $conn->prepare("SELECT session_version FROM admins WHERE admin_id = ?");
        if ($stmt_asv) {
            $stmt_asv->bind_param("i", $_SESSION['admin_id']);
            $stmt_asv->execute();
            $admin_sec = $stmt_asv->get_result()->fetch_assoc();
            $stmt_asv->close();

            if (!$admin_sec || (int)$admin_sec['session_version'] !== (int)$_SESSION['session_version']) {
                $_SESSION = array();
                if (isset($_COOKIE['admin_remember_me'])) {
                    setcookie('admin_remember_me', '', time() - 3600, '/');
                }
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }
                session_destroy();
                $in_subfolder = (stripos($_SERVER['SCRIPT_NAME'], '/user/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/php/') !== false || stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
                $admin_login_url = $in_subfolder ? (stripos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? 'admin_login.php?error=session_expired' : '../admin/admin_login.php?error=session_expired') : 'admin/admin_login.php?error=session_expired';
                header("Location: $admin_login_url");
                exit();
            }
        }
    }
}
?>