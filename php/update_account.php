<?php
session_start();
require_once '../config/db_connect.php';

// استدعاء ملفات PHPMailer لإرسال كود التفعيل عند تغيير الإيميل
require '../assets/PHPMailer/Exception.php';
require '../assets/PHPMailer/PHPMailer.php';
require '../assets/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- CSRF Token Validation ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("رمز غير صالح (حماية من CSRF). الطلب مرفوض لأسباب أمنية.");
    }
    
    // 1. تحديث المعلومات الشخصية
    if ($action === 'update_personal') {
        $full_name = trim($_POST['full_name']);
        $birth_date = $_POST['birth_date'];
        $gender = $_POST['gender'];

        // ✅ التحقق من العمر (18+)
        $dob = new DateTime($birth_date);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
        if ($age < 18) {
            header("Location: ../user/my_account.php?error=عذراً، يجب أن يكون عمرك 18 عاماً فأكثر لتعديل البيانات.");
            exit();
        }

        // فصل الاسم الأول والأخير
        $parts = explode(' ', $full_name, 2);
        $first_name = $parts[0];
        $last_name = isset($parts[1]) ? $parts[1] : '';

        $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, birth_date=?, gender=? WHERE user_id=?");
        $stmt->bind_param("ssssi", $first_name, $last_name, $birth_date, $gender, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $first_name; // تحديث الاسم في السيشن
            header("Location: ../user/my_account.php?msg=تم تحديث المعلومات الشخصية بنجاح");
        } else {
            header("Location: ../user/my_account.php?error=حدث خطأ أثناء التحديث");
        }
    }

    // 2. تحديث معلومات التواصل
    elseif ($action === 'update_contact') {
        $phone = trim($_POST['phone']);
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $governorate = $_POST['governorate'];
        $hide_phone = isset($_POST['hide_phone']) ? 1 : 0;

        // 1. جلب الإيميل الحالي للمقارنة
        $stmt_check = $conn->prepare("SELECT email, first_name FROM users WHERE user_id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $curr_user = $stmt_check->get_result()->fetch_assoc();
        $old_email = $curr_user['email'] ?? '';
        $first_name = $curr_user['first_name'];
        $stmt_check->close();

        if (!empty($email) && $email !== $old_email) {
            // إذا أدخل إيميل جديد غير مفرغ ومختلف عن القديم
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                header("Location: ../user/my_account.php?error=" . urlencode("صيغة البريد الإلكتروني غير صحيحة"));
                exit();
            }

            // فحص التكرار مع مستخدم آخر
            $chk_dup = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $chk_dup->bind_param("si", $email, $user_id);
            $chk_dup->execute();
            if ($chk_dup->get_result()->num_rows > 0) {
                $chk_dup->close();
                header("Location: ../user/my_account.php?error=" . urlencode("عذراً، هذا البريد الإلكتروني مستخدم من قبل حساب آخر"));
                exit();
            }
            $chk_dup->close();

            $token = bin2hex(random_bytes(32));
            
            // تحديث التوكن ووضع الإيميل في خانة الانتظار
            $stmt = $conn->prepare("UPDATE users SET phone=?, pending_email=?, governorate=?, hide_phone=?, verification_token=? WHERE user_id=?");
            $stmt->bind_param("sssisi", $phone, $email, $governorate, $hide_phone, $token, $user_id);
            
            if ($stmt->execute()) {
                // إرسال إيميل التفعيل
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

                    // محتوى الإيميل
                    $base_url = "http://localhost/Ethraa/";
                    $verify_link = $base_url . "user/verify.php?token=" . $token;
                    $safe_first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
                    $mail->isHTML(true);
                    $mail->Subject = 'تفعيل البريد الإلكتروني - منصة إثراء';
                    $mail->Body    = "
                        <div style='text-align: right; direction: rtl; font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                            <h2 style='color: #021C7B;'>مرحباً {$safe_first_name}،</h2>
                            <p>لقد قمت بتحديث بريدك الإلكتروني في منصة إثراء. لتفعيل بريدك الجديد، يرجى النقر على الرابط التالي:</p>
                            <div style='margin: 20px 0;'>
                                <a href='$verify_link' style='background-color: #66BF26; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>تفعيل البريد الإلكتروني</a>
                            </div>
                            <p>أو انسخ الرابط التالي:<br>$verify_link</p>
                        </div>";
                    
                    $mail->send();
                    $msg = "تم تحديث البيانات. يرجى التحقق من بريدك الجديد لتفعيله.";
                } catch (Exception $e) {
                    error_log("Failed to send activation email: " . $mail->ErrorInfo);
                    $msg = "تم تحديث البيانات ولكن فشل إرسال بريد التفعيل.";
                }
                header("Location: ../user/my_account.php?msg=" . urlencode($msg));
                exit();
            } else {
                header("Location: ../user/my_account.php?error=حدث خطأ، ربما البريد مستخدم مسبقاً");
                exit();
            }
        } elseif (empty($email) && !empty($old_email)) {
            // إذا كان لديه إيميل سابق وقام بمسحه لجعله فارغاً
            $stmt = $conn->prepare("UPDATE users SET phone=?, email=NULL, pending_email=NULL, verification_token=NULL, email_verified_at=NULL, email_verified=0, governorate=?, hide_phone=? WHERE user_id=?");
            $stmt->bind_param("ssii", $phone, $governorate, $hide_phone, $user_id);
            if ($stmt->execute()) {
                header("Location: ../user/my_account.php?msg=" . urlencode("تم تحديث معلومات التواصل بنجاح"));
            } else {
                header("Location: ../user/my_account.php?error=" . urlencode("حدث خطأ أثناء تحديث البيانات"));
            }
            exit();
        } else {
            // إذا لم يتغير الإيميل أو كان فارغاً وما زال فارغاً: تحديث باقي البيانات فقط
            $stmt = $conn->prepare("UPDATE users SET phone=?, governorate=?, hide_phone=? WHERE user_id=?");
            $stmt->bind_param("ssii", $phone, $governorate, $hide_phone, $user_id);
            
            if ($stmt->execute()) {
                header("Location: ../user/my_account.php?msg=تم تحديث معلومات التواصل بنجاح");
            } else {
                header("Location: ../user/my_account.php?error=حدث خطأ، ربما الرقم مستخدم مسبقاً");
            }
            exit();
        }
    }

    // 3. تحديث وقت الفراغ (الخدمة)
    elseif ($action === 'update_service') {
        $start_hh = (int)$_POST['start_hh'];
        $start_mm = (int)$_POST['start_mm'];
        $end_hh   = (int)$_POST['end_hh'];
        $end_mm   = (int)$_POST['end_mm'];
        $service_id = (int)$_POST['sub_service_id'];
        $av_type = $_POST['availability_type'];

        $start_time = str_pad($start_hh, 2, "0", STR_PAD_LEFT) . ":" . str_pad($start_mm, 2, "0", STR_PAD_LEFT) . ":00";
        $end_time   = str_pad($end_hh, 2, "0", STR_PAD_LEFT) . ":" . str_pad($end_mm, 2, "0", STR_PAD_LEFT) . ":00";

        $stmt = $conn->prepare("UPDATE users SET free_time_start=?, free_time_end=?, service_id=?, availability_type=? WHERE user_id=?");
        $stmt->bind_param("ssisi", $start_time, $end_time, $service_id, $av_type, $user_id);
        
        if ($stmt->execute()) {
            header("Location: ../user/my_account.php?msg=تم تحديث أوقات التوافر بنجاح");
        } else {
            header("Location: ../user/my_account.php?error=حدث خطأ أثناء التحديث");
        }
    }

    // 4. تغيير كلمة المرور
    elseif ($action === 'change_password') {
        $current_pass = $_POST['current_pass'] ?? '';
        $new_pass = $_POST['new_pass'] ?? '';
        $confirm_pass = $_POST['confirm_pass'] ?? '';

        // التحقق من أن كلمة المرور الجديدة مختلفة عن الحالية
        if ($current_pass === $new_pass) {
            header("Location: ../user/my_account.php?error=" . urlencode("كلمة المرور الجديدة يجب أن تكون مختلفة عن كلمة المرور الحالية."));
            exit();
        }

        // ✅ التحقق من قوة كلمة المرور (نفس شروط التسجيل)
        if (strlen($new_pass) < 8 || !preg_match("/[0-9]/", $new_pass) || !preg_match("/[a-z]/i", $new_pass)) {
            header("Location: ../user/my_account.php?error=كلمة المرور ضعيفة! يجب أن تكون 8 خانات وتحتوي حروفاً وأرقاماً.");
            exit();
        }

        if ($new_pass !== $confirm_pass) {
            header("Location: ../user/my_account.php?error=كلمات المرور الجديدة غير متطابقة");
            exit();
        }

        // التحقق من كلمة المرور القديمة
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res && password_verify($current_pass, $res['password'])) {
            // فحص إضافي: التأكد من أن كلمة المرور الجديدة لا تطابق القديمة في قاعدة البيانات
            if (password_verify($new_pass, $res['password'])) {
                header("Location: ../user/my_account.php?error=" . urlencode("كلمة المرور الجديدة يجب أن تكون مختلفة عن كلمة المرور الحالية."));
                exit();
            }

            $new_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password=?, remember_token=NULL, session_version=session_version + 1 WHERE user_id=?");
            $update->bind_param("si", $new_hashed, $user_id);
            $update->execute();
            $update->close();

            // تحديث نسخة الجلسة للجلسة الحالية لتبقى نشطة بينما يتم طرد كافة الأجهزة الأخرى
            $stmt_new_sv = $conn->prepare("SELECT session_version FROM users WHERE user_id = ?");
            $stmt_new_sv->bind_param("i", $user_id);
            $stmt_new_sv->execute();
            $new_sv_row = $stmt_new_sv->get_result()->fetch_assoc();
            $stmt_new_sv->close();
            if ($new_sv_row) {
                $_SESSION['session_version'] = (int)$new_sv_row['session_version'];
            }

            header("Location: ../user/my_account.php?msg=" . urlencode("تم تغيير كلمة المرور بنجاح وتم تسجيل الخروج من الأجهزة الأخرى!"));
            exit();
        } else {
            header("Location: ../user/my_account.php?error=كلمة المرور الحالية غير صحيحة");
            exit();
        }
    }

    // 6. تسجيل الخروج من جميع الأجهزة
    elseif ($action === 'logout_all') {
        // إبطال رمز التذكر وزيادة نسخة الجلسة لطرد كافة الجلسات الأخرى فوراً
        $up_rem = $conn->prepare("UPDATE users SET remember_token = NULL, session_version = session_version + 1 WHERE user_id = ?");
        $up_rem->bind_param("i", $user_id);
        $up_rem->execute();
        $up_rem->close();
        
        // حذف الكوكي من المتصفح الحالي
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/');
        }

        // تدمير الجلسة الحالية وحذف كوكي الجلسة
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        
        header("Location: ../user/login.php?msg=logged_out_all");
        exit();
    }

    // 5. حذف الحساب
    elseif ($action === 'delete_account') {
        // التحقق من عدم وجود طلبات معلقة أو جارية للحفاظ على حقوق المستخدمين الآخرين
        $chk_req = $conn->prepare("SELECT request_id FROM requests WHERE (requester_id = ? OR provider_id = ?) AND status IN ('pending', 'accepted')");
        $chk_req->bind_param("ii", $user_id, $user_id);
        $chk_req->execute();
        if ($chk_req->get_result()->num_rows > 0) {
            $chk_req->close();
            header("Location: ../user/my_account.php?error=" . urlencode("لا يمكنك حذف الحساب لوجود طلبات نشطة حالياً. يرجى إنهاؤها أو إلغاؤها أولاً."));
            exit();
        }
        $chk_req->close();

        // سنقوم بحذف الحساب
        $del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $del->bind_param("i", $user_id);
        $del->execute();
        $del->close();

        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/');
        }
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        header("Location: ../index.php");
        exit();
    }

} else {
    header("Location: ../user/my_account.php");
}
?>