<?php
session_start();
require_once '../config/db_connect.php';

// استدعاء ملفات PHPMailer يدوياً
require '../assets/PHPMailer/Exception.php';
require '../assets/PHPMailer/PHPMailer.php';
require '../assets/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../forgot_password.php?msg=sent");
        exit();
    }

    // 1. التحقق من وجود البريد الإلكتروني باستخدام Prepared Statements
    $stmt_check = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $check_res = $stmt_check->get_result();

    if ($check_res && $check_res->num_rows > 0) {
        $user = $check_res->fetch_assoc();
        $stmt_check->close();

        // 🛡️ حماية (Rate Limiting): منع طلب رابط جديد إذا تم طلب واحد خلال آخر 5 دقائق
        $stmt_recent = $conn->prepare("SELECT id FROM password_resets WHERE email = ? AND expires_at > DATE_ADD(NOW(), INTERVAL 55 MINUTE)");
        $stmt_recent->bind_param("s", $email);
        $stmt_recent->execute();
        $check_recent = $stmt_recent->get_result();

        if ($check_recent && $check_recent->num_rows > 0) {
            $stmt_recent->close();
            header("Location: ../forgot_password.php?msg=sent");
            exit();
        }
        $stmt_recent->close();

        // 2. إنشاء توكن عشوائي قوي
        $token = bin2hex(random_bytes(50));
        $hashed_token = password_hash($token, PASSWORD_DEFAULT);
        $expires_at = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // 3. حذف الطلبات القديمة وإدخال الجديد
        $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $del_stmt->bind_param("s", $email);
        $del_stmt->execute();
        $del_stmt->close();

        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $hashed_token, $expires_at);
        $stmt->execute();
        $stmt->close();

        // 4. رابط الاستعادة
        $link = "http://localhost/Ethraa/reset_password.php?token=" . $token . "&email=" . urlencode($email);

        // 5. إعداد PHPMailer
        $mail = new PHPMailer(true);

        try {
            $mail_config = require __DIR__ . '/../config/mail_config.php';
            $mail->isSMTP();
            $mail->Host       = $mail_config['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $mail_config['smtp_user'];
            $mail->Password   = str_replace(' ', '', $mail_config['smtp_pass']);
            $mail->SMTPSecure = ($mail_config['smtp_secure'] === 'ssl' || $mail_config['smtp_port'] === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $mail_config['smtp_port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
            $mail->addAddress($email, $user['first_name']);

            $safe_first_name = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
            $mail->isHTML(true);
            $mail->Subject = 'استعادة كلمة المرور - إثراء';
            
            $mail->Body    = "
                <div style='direction: rtl; text-align: right; font-family: Arial, sans-serif; color: #333;'>
                    <h2>مرحباً {$safe_first_name}،</h2>
                    <p>لقد تلقينا طلباً لإعادة تعيين كلمة المرور الخاصة بحسابك في منصة إثراء.</p>
                    <p>اضغط على الزر أدناه لإعادة التعيين:</p>
                    <a href='$link' style='background-color: #021C7B; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>تغيير كلمة المرور</a>
                    <p>أو انسخ الرابط التالي:<br>$link</p>
                    <p>هذا الرابط صالح لمدة ساعة واحدة.</p>
                    <hr>
                    <small>إذا لم تطلب هذا التغيير، يرجى تجاهل هذه الرسالة.</small>
                </div>
            ";
            $mail->AltBody = "مرحباً، لإعادة تعيين كلمة المرور انسخ الرابط التالي: $link";

            $mail->send();
            header("Location: ../forgot_password.php?msg=sent");
            exit();

        } catch (Exception $e) {
            error_log("Password reset mail error: " . $mail->ErrorInfo);
            header("Location: ../forgot_password.php?msg=sent");
            exit();
        }
        
    } else {
        $stmt_check->close();
        // حماية من ثغرة (User Enumeration): توجيه رسالة نجاح وهمية حتى لو الإيميل غير مسجل
        header("Location: ../forgot_password.php?msg=sent");
        exit();
    }
}
?>