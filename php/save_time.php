<?php
require_once '../config/db_connect.php';

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- CSRF Token Validation ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("رمز غير صالح (حماية من CSRF). الطلب مرفوض لأسباب أمنية.");
    }

    $user_id = $_SESSION['user_id'];
    $av_type = $_POST['availability_type'] ?? 'specific';
    
    // 2. استقبال الأرقام من الفورم
    $start_hh = (int)($_POST['start_hh'] ?? 0);
    $start_mm = (int)($_POST['start_mm'] ?? 0);
    $end_hh   = (int)($_POST['end_hh'] ?? 0);
    $end_mm   = (int)($_POST['end_mm'] ?? 0);

    // 3. تنسيق الوقت ليناسب الداتا بيس (HH:MM:00)
    $start_time = str_pad($start_hh, 2, "0", STR_PAD_LEFT) . ":" . str_pad($start_mm, 2, "0", STR_PAD_LEFT) . ":00";
    $end_time   = str_pad($end_hh, 2, "0", STR_PAD_LEFT) . ":" . str_pad($end_mm, 2, "0", STR_PAD_LEFT) . ":00";

    // 4. تحديث جدول users فوراً دائماً
    $sql = "UPDATE users SET free_time_start = ?, free_time_end = ?, availability_type = ? WHERE user_id = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssi", $start_time, $end_time, $av_type, $user_id);
        
        if ($stmt->execute()) {
            // --- إرسال إشعارات للمنتظرين إذا أصبح متاحاً مع حماية من تكرار إرسال البريد ---
            $can_notify = (!isset($_SESSION['last_availability_notif_time']) || (time() - $_SESSION['last_availability_notif_time']) >= 30);
            
            if (($av_type == 'always' || $av_type == 'specific') && $can_notify) {
                $_SESSION['last_availability_notif_time'] = time();
                // جلب اسم مقدم الخدمة
                $prov_q = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
                $prov_q->bind_param("i", $user_id);
                $prov_q->execute();
                $prov_data = $prov_q->get_result()->fetch_assoc();
                $prov_q->close();
                
                $prov_name = $prov_data ? ($prov_data['first_name'] . ' ' . $prov_data['last_name']) : 'مقدم الخدمة';

                // جلب المشتركين مع بريدهم الإلكتروني وأسمائهم
                $sub_stmt = $conn->prepare("SELECT s.requester_id, u.email, u.first_name FROM availability_subscriptions s JOIN users u ON s.requester_id = u.user_id WHERE s.provider_id = ?");
                $sub_stmt->bind_param("i", $user_id);
                $sub_stmt->execute();
                $subs = $sub_stmt->get_result();

                if ($subs && $subs->num_rows > 0) {
                    $ins_notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'info', NOW())");
                    
                    while ($sub = $subs->fetch_assoc()) {
                        $req_id = $sub['requester_id'];
                        $msg = "مقدم الخدمة ($prov_name) الذي كنت تنتظره أصبح متاحاً الآن!";
                        
                        $ins_notif->bind_param("is", $req_id, $msg);
                        $ins_notif->execute();
                        
                        // إرسال بريد إلكتروني
                        if (!empty($sub['email'])) {
                            $subject = "تنبيه التوفر - إثراء";
                            $email_msg = "مرحباً {$sub['first_name']}،\n\n$msg\n\nيمكنك الآن الدخول وحجز الخدمة.";
                            $headers = "From: no-reply@ithraa.com\r\nContent-Type: text/plain; charset=UTF-8";
                            @mail($sub['email'], $subject, $email_msg, $headers);
                        }
                    }
                    $ins_notif->close();

                    // حذف الاشتراكات بعد الإشعار
                    $del_subs = $conn->prepare("DELETE FROM availability_subscriptions WHERE provider_id = ?");
                    $del_subs->bind_param("i", $user_id);
                    $del_subs->execute();
                    $del_subs->close();
                }
                $sub_stmt->close();
            }
            header("Location: ../user/user_home.php?msg=time_saved");
            exit();
        } else {
            header("Location: ../user/user_home.php?error=db_error");
            exit();
        }
        $stmt->close();
    } else {
        header("Location: ../user/user_home.php?error=stmt_error");
        exit();
    }
    
    $conn->close();
} else {
    header("Location: ../user/user_home.php");
    exit();
}
?>