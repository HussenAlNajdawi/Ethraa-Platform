<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CSRF Token Validation ---
    if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("رمز غير صالح (حماية من CSRF). الطلب مرفوض لأسباب أمنية.");
    }

    $email = trim($_POST['email'] ?? '');
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // 1. التحقق من التطابق
    if ($password !== $confirm) {
        header("Location: ../reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email) . "&error=mismatch");
        exit();
    }

    // التحقق من قوة كلمة المرور
    if (strlen($password) < 8 || !preg_match("/[0-9]/", $password) || !preg_match("/[a-z]/i", $password)) {
        header("Location: ../reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email) . "&error=weak");
        exit();
    }

    // 2. التحقق من التوكن مرة أخرى (لزيادة الأمان)
    $current_date = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("SELECT id, token FROM password_resets WHERE email = ? AND expires_at >= ?");
    $stmt->bind_param("ss", $email, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $token_valid = false;
    while ($row = $result->fetch_assoc()) {
        if (password_verify($token, $row['token'])) {
            $token_valid = true;
            break;
        }
    }
    $stmt->close();
    
    if ($token_valid) {
        // التأكد من أن كلمة المرور الجديدة تختلف عن الحالية
        $stmt_usr = $conn->prepare("SELECT password FROM users WHERE email = ?");
        $stmt_usr->bind_param("s", $email);
        $stmt_usr->execute();
        $usr_res = $stmt_usr->get_result()->fetch_assoc();
        $stmt_usr->close();

        if ($usr_res && password_verify($password, $usr_res['password'])) {
            header("Location: ../reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email) . "&error=same_password");
            exit();
        }

        // 3. تحديث كلمة المرور وإبطال جميع الجلسات وتوكنات التذكر القديمة
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, remember_token = NULL, session_version = session_version + 1 WHERE email = ?");
        $update->bind_param("ss", $hashed_password, $email);
        
        if ($update->execute()) {
            $update->close();

            // 4. حذف التوكن المستخدم عبر Prepared Statement
            $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $del_stmt->bind_param("s", $email);
            $del_stmt->execute();
            $del_stmt->close();
            
            // توجيه لصفحة الدخول مع رسالة نجاح
            header("Location: ../user/login.php?success=password_reset");
            exit();
        } else {
            error_log("Password reset DB error: " . $conn->error);
            header("Location: ../reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email) . "&error=db_error");
            exit();
        }
    } else {
        header("Location: ../reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email) . "&error=expired");
        exit();
    }
}
?>