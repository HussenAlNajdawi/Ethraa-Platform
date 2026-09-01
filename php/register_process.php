<?php
// php/register_process.php
session_start();
include '../config/db_connect.php'; 

require '../assets/PHPMailer/Exception.php';
require '../assets/PHPMailer/PHPMailer.php';
require '../assets/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- CSRF Token Validation ---
    if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("رمز غير صالح (حماية من CSRF). الطلب مرفوض لأسباب أمنية.");
    }

    $first_name = $_POST['first_name'];
    $last_name  = $_POST['last_name'];
    $birth_date = $_POST['birth_date'] ?? '';
    $phone      = trim($_POST['phone']); 
    $password   = $_POST['password'];
    $email      = isset($_POST['email']) ? trim($_POST['email']) : '';
    $referrer_id = isset($_POST['referrer_id']) ? intval($_POST['referrer_id']) : 0;

    // 1. التحقق من العمر (يجب أن يكون 18 عاماً أو أكثر)
    if (empty($birth_date)) {
        $_SESSION['form_data'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'birth_date' => $birth_date,
            'phone' => $phone,
            'email' => $email,
            'ref' => $referrer_id
        ];
        header("Location: ../user/register.php?error=invalid_age");
        exit();
    }

    try {
        $bdate = new DateTime($birth_date);
        $today = new DateTime();
        $age = $today->diff($bdate)->y;

        if ($age < 18 || $bdate > $today) {
            $_SESSION['form_data'] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'birth_date' => $birth_date,
                'phone' => $phone,
                'email' => $email,
                'ref' => $referrer_id
            ];
            header("Location: ../user/register.php?error=underage");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['form_data'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'birth_date' => $birth_date,
            'phone' => $phone,
            'email' => $email,
            'ref' => $referrer_id
        ];
        header("Location: ../user/register.php?error=invalid_age");
        exit();
    }

    if (strlen($password) < 8 || !preg_match("/[0-9]/", $password) || !preg_match("/[a-z]/i", $password)) {
        $_SESSION['form_data'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'birth_date' => $birth_date,
            'phone' => $phone,
            'email' => $email,
            'ref' => $referrer_id
        ];
        header("Location: ../user/register.php?error=weak_password");
        exit();
    }

    if (!preg_match('/^(77|78|79)[0-9]{7}$/', $phone)) {
        $_SESSION['form_data'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'birth_date' => $birth_date,
            'phone' => $phone,
            'email' => $email,
            'ref' => $referrer_id
        ];
        header("Location: ../user/register.php?error=invalid_phone");
        exit();
    }

    $checkQuery = $conn->prepare("SELECT user_id FROM users WHERE phone = ?");
    $checkQuery->bind_param("s", $phone);
    $checkQuery->execute();
    if ($checkQuery->get_result()->num_rows > 0) {
        $checkQuery->close();
        $_SESSION['form_data'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'birth_date' => $birth_date,
            'email' => $email,
            'ref' => $referrer_id
        ];
        header("Location: ../user/register.php?error=exists");
        exit();
    }
    $checkQuery->close();

    if (!empty($email)) {
        $checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        if ($checkEmail->get_result()->num_rows > 0) {
            $checkEmail->close();
            $_SESSION['form_data'] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'birth_date' => $birth_date,
                'phone' => $phone,
                'ref' => $referrer_id
            ];
            header("Location: ../user/register.php?error=email_exists");
            exit();
        }
        $checkEmail->close();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $pending_email = empty($email) ? null : $email;
    $verification_token = bin2hex(random_bytes(32));

    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS pending_email VARCHAR(255) NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS referrer_id INT NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_rewarded TINYINT(1) DEFAULT 0");

    $valid_referrer = null;
    if ($referrer_id > 0) {
        $checkRef = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
        $checkRef->bind_param("i", $referrer_id);
        $checkRef->execute();
        if ($checkRef->get_result()->num_rows > 0) {
            $valid_referrer = $referrer_id;
        }
        $checkRef->close();
    }

    $insert_stmt = $conn->prepare("INSERT INTO users (first_name, last_name, birth_date, phone, password, pending_email, points, status, verification_token, referrer_id, referral_rewarded) VALUES (?, ?, ?, ?, ?, ?, 3, 'active', ?, ?, 0)");
    $insert_stmt->bind_param("sssssssi", $first_name, $last_name, $birth_date, $phone, $hashed_password, $pending_email, $verification_token, $valid_referrer);

    if ($insert_stmt->execute()) {
        $new_user_id = $insert_stmt->insert_id;
        $insert_stmt->close();

        unset($_SESSION['form_data']); 

        $welcome_msg = "أهلاً بك في منصة إثراء! لقد حصلت على 3 نقاط مجانية كهدية تسجيل. تصفح قسم الخدمات للبدء بطلب خدمة.";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'info', NOW())");
        $notif_stmt->bind_param("is", $new_user_id, $welcome_msg);
        $notif_stmt->execute();
        $notif_stmt->close();

        if ($valid_referrer && $valid_referrer != $new_user_id) {
            // إشعار الداعي بتسجيل الصديق (دون تحويل النقاط إلا بعد إكمال أول خدمة لمنع استنساخ الحسابات الوهمية)
            $ref_msg = "قام صديقك ({$first_name}) بإنشاء حساب بناءً على رابط دعوتك! ستتم إضافة نقطتك المجانية فور إكماله لأول خدمة له بنجاح على المنصة.";
            $ref_notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'info', NOW())");
            $ref_notif->bind_param("is", $valid_referrer, $ref_msg);
            $ref_notif->execute();
            $ref_notif->close();
        }

        if (!empty($email)) {
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
                $mail->addAddress($email, $first_name);

                $verify_link = "http://localhost/Ethraa/user/verify.php?token=$verification_token&email=" . urlencode($email);
                
                $mail->isHTML(true);
                $mail->Subject = 'تأكيد البريد الإلكتروني - إثراء';
                $mail->Body    = "
                    <div style='direction: rtl; text-align: right; font-family: Arial, sans-serif;'>
                        <h3>مرحباً " . htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') . "،</h3>
                        <p>شكراً لتسجيلك في منصة إثراء. يرجى الضغط على الرابط أدناه لتأكيد بريدك الإلكتروني:</p>
                        <a href='$verify_link' style='background-color: #66BF26; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>تأكيد البريد الإلكتروني</a>
                        <p>أو انسخ الرابط التالي:<br>$verify_link</p>
                    </div>";
                
                $mail->send();
            } catch (Exception $e) {
                error_log("Failed to send verification email: " . $mail->ErrorInfo);
            }
            header("Location: ../user/login.php?success=verify_email");
            exit();
        }

        header("Location: ../user/login.php?success=registered");
        exit();
    } else {
        error_log("Registration failed: " . $conn->error);
        header("Location: ../user/register.php?error=reg_failed");
        exit();
    }
    
    $conn->close();

} else {
    header("Location: ../user/register.php");
    exit();
}
?>
