<?php
// php/login_process.php
session_start();
include '../config/db_connect.php';

// تنظيف الرقم من الفراغات
$phone = trim($_POST['phone']);

// التحقق: هل الرقم من 9 خانات ويبدأ بـ 77, 78, 79؟
if (!preg_match('/^(77|78|79)[0-9]{7}$/', $phone)) {
    header("Location: ../user/login.php?error=invalid_format");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: ../user/login.php?error=csrf_error");
        exit();
    }

    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, password, first_name, status, failed_attempts, lockout_time, service_id, banned_until, session_version FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row['lockout_time'] && strtotime($row['lockout_time']) > time()) {
            $expiry = strtotime($row['lockout_time']);
            $_SESSION['login_phone'] = $phone;
            header("Location: ../user/login.php?error=locked&expiry=$expiry");
            exit();
        }
        
        $ban_check = $conn->prepare("SELECT TIMESTAMPDIFF(MINUTE, NOW(), banned_until) as remaining_minutes FROM users WHERE user_id = ? AND banned_until > NOW()");
        $ban_check->bind_param("i", $row['user_id']);
        $ban_check->execute();
        $ban_res = $ban_check->get_result()->fetch_assoc();
        $ban_check->close();

        if ($ban_res && $ban_res['remaining_minutes'] !== null) {
            $remaining = max(1, $ban_res['remaining_minutes']);
            $_SESSION['login_phone'] = $phone;
            header("Location: ../user/login.php?error=banned_temporary&remaining=$remaining");
            exit();
        }

        if ($row['status'] !== 'active') {
            $warn_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND type = 'warning'");
            $warn_stmt->bind_param("i", $row['user_id']);
            $warn_stmt->execute();
            $warn_count = $warn_stmt->get_result()->fetch_assoc()['count'];
            $warn_stmt->close();
            
            if ($warn_count >= 3) {
                $ban_stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE user_id = ?");
                $ban_stmt->bind_param("i", $row['user_id']);
                $ban_stmt->execute();
                $ban_stmt->close();
                $_SESSION['login_phone'] = $phone;
                header("Location: ../user/login.php?error=banned_warnings");
                exit();
            }

            $_SESSION['login_phone'] = $phone;
            header("Location: ../user/login.php?error=banned");
            exit();
        }

        if (password_verify($password, $row['password'])) {
            if (isset($_POST['rememberMe']) || isset($_POST['remember_me'])) {
                $token = bin2hex(random_bytes(20)); 
                $cookie_value = $row['user_id'] . ':' . $token;
                $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                setcookie('remember_me', $cookie_value, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'domain' => '',
                    'secure' => $is_https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                $hashed_token = password_hash($token, PASSWORD_DEFAULT);
                $rem_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
                $rem_stmt->bind_param("si", $hashed_token, $row['user_id']);
                $rem_stmt->execute();
                $rem_stmt->close();
            }

            $up_stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, lockout_time = NULL WHERE user_id = ?");
            $up_stmt->bind_param("i", $row['user_id']);
            $up_stmt->execute();
            $up_stmt->close();
            
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['user_name'] = $row['first_name']; 
            $_SESSION['first_name'] = $row['first_name'];
            $_SESSION['session_version'] = (int)($row['session_version'] ?? 1);
            $_SESSION['last_activity'] = time();
            
            if (is_null($row['service_id']) || $row['service_id'] == 0) {
                $_SESSION['show_service_prompt'] = true;
            }
            
            unset($_SESSION['login_phone']);
            
            $log_stmt = $conn->prepare("INSERT INTO loginlogs (user_id, login_time) VALUES (?, NOW())");
            $log_stmt->bind_param("i", $row['user_id']);
            $log_stmt->execute();
            $log_stmt->close();
            
            header("Location: ../user/user_home.php");
            exit();

        } else {
            $failed_attempts = $row['failed_attempts'] + 1;
            
            if ($failed_attempts >= 5) {
                $expiry_timestamp = time() + (15 * 60); 
                $lockout_time = date('Y-m-d H:i:s', $expiry_timestamp);
                $up_fail = $conn->prepare("UPDATE users SET failed_attempts = ?, lockout_time = ? WHERE user_id = ?");
                $up_fail->bind_param("isi", $failed_attempts, $lockout_time, $row['user_id']);
                $up_fail->execute();
                $up_fail->close();
                $_SESSION['login_phone'] = $phone;
                header("Location: ../user/login.php?error=locked&expiry=$expiry_timestamp");
            } else {
                $up_fail = $conn->prepare("UPDATE users SET failed_attempts = ? WHERE user_id = ?");
                $up_fail->bind_param("ii", $failed_attempts, $row['user_id']);
                $up_fail->execute();
                $up_fail->close();
                $_SESSION['login_phone'] = $phone;
                header("Location: ../user/login.php?error=invalid_credentials");
            }
            exit();
        }
    } else {
        $stmt->close();
        $_SESSION['login_phone'] = $phone;
        header("Location: ../user/login.php?error=invalid_credentials");
        exit();
    }
    $conn->close();
} else {
    header("Location: ../user/login.php");
    exit();
}
?>
