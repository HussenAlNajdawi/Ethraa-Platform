<?php
require_once '../config/db_connect.php';

// التحقق من أن المستخدم ضغط زر التأكيد
if (isset($_POST['confirm_booking'])) {
    
    // التحقق من تسجيل الدخول
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../user/login.php");
        exit();
    }

    // --- CSRF Token Validation ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("رمز غير صالح (حماية من CSRF). الطلب مرفوض لأسباب أمنية.");
    }

    $requester_id = $_SESSION['user_id'];
    $provider_id  = intval($_POST['provider_id']);
    $details      = trim($_POST['details']);
    $main_id      = $_POST['main_id'] ?? '';
    $cost         = 1; // تكلفة الخدمة ثابتة
    $redirect_url = "../user/services_list.php?main_id=$main_id&booking_error=";

    // منع المستخدم من حجز خدمة لنفسه
    if ($requester_id == $provider_id) {
        header("Location: " . $redirect_url . "self_booking#card-" . $provider_id);
        exit();
    }

    // معرفة نوع الخدمة ومقدم الخدمة والتحقق من التوفر
    $srv_stmt = $conn->prepare("SELECT service_id, availability_type, free_time_start, free_time_end FROM users WHERE user_id = ? AND service_id IS NOT NULL");
    $srv_stmt->bind_param("i", $provider_id);
    $srv_stmt->execute();
    $srv_res = $srv_stmt->get_result();

    if ($srv_res->num_rows === 0) {
        $srv_stmt->close();
        header("Location: " . $redirect_url . "provider_unavailable#card-" . $provider_id);
        exit();
    }

    $provider_data = $srv_res->fetch_assoc();
    $service_id = $provider_data['service_id'];
    $av_type = $provider_data['availability_type'];
    $srv_stmt->close();

    if ($av_type === 'unavailable') {
        header("Location: " . $redirect_url . "unavailable#card-" . $provider_id);
        exit();
    }

    // جلب الحد اليومي للمستخدم
    $limit_stmt = $conn->prepare("SELECT daily_limit FROM users WHERE user_id = ?");
    $limit_stmt->bind_param("i", $requester_id);
    $limit_stmt->execute();
    $daily_limit = $limit_stmt->get_result()->fetch_assoc()['daily_limit'] ?? 3;
    $limit_stmt->close();

    // بدء المعاملة المصرفية وقفل السجلات لمنع حالات التسابق (Race Conditions)
    $conn->begin_transaction();

    try {
        // 1. قفل السجلات والتحقق من عدم وجود طلب نشط حالياً للطالب
        $check_stmt = $conn->prepare("SELECT request_id FROM requests WHERE requester_id = ? AND status IN ('pending', 'accepted') FOR UPDATE");
        $check_stmt->bind_param("i", $requester_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($check_res->num_rows > 0) {
            $check_stmt->close();
            $conn->rollback();
            header("Location: " . $redirect_url . "active_request#card-" . $provider_id);
            exit();
        }
        $check_stmt->close();

        // 2. التحقق من الحد اليومي للطلبات
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE requester_id = ? AND DATE(created_at) = CURDATE()");
        $count_stmt->bind_param("i", $requester_id);
        $count_stmt->execute();
        $daily_count = $count_stmt->get_result()->fetch_assoc()['count'];
        $count_stmt->close();
        
        if ($daily_count >= $daily_limit) {
            $conn->rollback();
            header("Location: ../user/requests.php?error=daily_limit");
            exit();
        }

        // 3. خصم النقاط بشكل ذري مشروط (Atomic Conditional Deduction) لمنع الرصيد السالب والإنفاق المزدوج
        $update_points = $conn->prepare("UPDATE users SET points = points - ? WHERE user_id = ? AND points >= ?");
        $update_points->bind_param("iii", $cost, $requester_id, $cost);
        $update_points->execute();
        
        if ($update_points->affected_rows <= 0) {
            $update_points->close();
            $conn->rollback();
            
            // جلب الرصيد الحالي للعرض
            $p_stmt = $conn->prepare("SELECT points FROM users WHERE user_id = ?");
            $p_stmt->bind_param("i", $requester_id);
            $p_stmt->execute();
            $curr_points = $p_stmt->get_result()->fetch_assoc()['points'] ?? 0;
            $p_stmt->close();
            
            header("Location: " . $redirect_url . "no_points&points=" . $curr_points . "#card-" . $provider_id);
            exit();
        }
        $update_points->close();

        // 4. تسجيل الطلب في جدول requests
        $insert_req = $conn->prepare("INSERT INTO requests (requester_id, provider_id, service_id, status, points_cost, details, created_at) VALUES (?, ?, ?, 'pending', ?, ?, NOW())");
        $insert_req->bind_param("iiiss", $requester_id, $provider_id, $service_id, $cost, $details);
        $insert_req->execute();
        $new_req_id = $insert_req->insert_id;
        $insert_req->close();

        // اعتماد التغييرات في قاعدة البيانات
        $conn->commit();
        
        require_once 'wallet_system.php';
        logPointTransaction($conn, $requester_id, $cost, 'spend', 'حجز خدمة من مقدم خدمة', $new_req_id);

        // إرسال إشعار عبر البريد الإلكتروني لمقدم الخدمة
        $prov_q = $conn->prepare("SELECT email, first_name FROM users WHERE user_id = ?");
        $prov_q->bind_param("i", $provider_id);
        $prov_q->execute();
        $prov_info = $prov_q->get_result()->fetch_assoc();
        $prov_q->close();

        $req_q = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $req_q->bind_param("i", $requester_id);
        $req_q->execute();
        $req_info = $req_q->get_result()->fetch_assoc();
        $req_q->close();

        if ($prov_info && !empty($prov_info['email'])) {
            $to_email = $prov_info['email'];
            $subject = "طلب خدمة جديد - إثراء";
            $message = "مرحباً " . $prov_info['first_name'] . "،\n\nلديك طلب خدمة جديد من " . $req_info['first_name'] . " " . $req_info['last_name'] . ".\n\nالتفاصيل: " . $details . "\n\nيرجى تسجيل الدخول لاتخاذ الإجراء.";
            $headers = "From: no-reply@ithraa.com\r\nContent-Type: text/plain; charset=UTF-8";
            @mail($to_email, $subject, $message, $headers);
        }

        header("Location: ../user/requests.php?msg=booking_success&active_tab=pills-outgoing#req-" . $new_req_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: " . $redirect_url . "generic_error#card-" . $provider_id);
        exit();
    }

} else {
    header("Location: ../user/services_list.php");
    exit();
}
?>