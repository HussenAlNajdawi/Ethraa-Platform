<?php
session_start();
require_once '../config/db_connect.php';

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

    // 1. حذف إشعارات محددة (نظام أو طلبات)
    if ($action === 'delete_selected') {
        if (!empty($_POST['selected_ids'])) {
            
            $sys_ids = [];
            $inc_ids = [];
            $out_ids = [];

            foreach ($_POST['selected_ids'] as $val) {
                if (strpos($val, 'sys_') === 0) $sys_ids[] = intval(substr($val, 4));
                elseif (strpos($val, 'inc_') === 0) $inc_ids[] = intval(substr($val, 4));
                elseif (strpos($val, 'out_') === 0) $out_ids[] = intval(substr($val, 4));
            }

            // أ. حذف إشعارات النظام
            if (!empty($sys_ids)) {
                $ids_str = implode(',', $sys_ids);
                $conn->query("DELETE FROM notifications WHERE notification_id IN ($ids_str) AND user_id = $user_id AND message NOT LIKE '%تنبيه%' AND message NOT LIKE '%إنذار%'");
            }

            // ب. إخفاء الطلبات الواردة (كمقدم خدمة)
            if (!empty($inc_ids)) {
                $ids_str = implode(',', $inc_ids);
                $conn->query("UPDATE requests SET hidden_for_provider = 1 WHERE request_id IN ($ids_str) AND provider_id = $user_id");
            }

            // ج. إخفاء الطلبات الصادرة (كطالب خدمة)
            if (!empty($out_ids)) {
                $ids_str = implode(',', $out_ids);
                $conn->query("UPDATE requests SET hidden_for_requester = 1 WHERE request_id IN ($ids_str) AND requester_id = $user_id");
            }
        }
        header("Location: ../user/notifications.php?msg=deleted");
    }

    // 2. حذف جميع الإشعارات (نظام + طلبات)
    elseif ($action === 'delete_all') {
        // حذف إشعارات النظام
        $conn->query("DELETE FROM notifications WHERE user_id = $user_id AND message NOT LIKE '%تنبيه%' AND message NOT LIKE '%إنذار%'");
        // إخفاء جميع الطلبات الواردة والصادرة
        $conn->query("UPDATE requests SET hidden_for_provider = 1 WHERE provider_id = $user_id");
        $conn->query("UPDATE requests SET hidden_for_requester = 1 WHERE requester_id = $user_id");
        
        header("Location: ../user/notifications.php?msg=deleted_all");
    }

    // 3. تقديم اعتراض
    elseif ($action === 'submit_appeal') {
        $notif_id = (int)$_POST['notification_id'];
        $reason = trim($_POST['appeal_reason']);

        if (!empty($reason) && $notif_id > 0) {
            // التحقق من أن الإشعار يخص المستخدم
            $check = $conn->query("SELECT notification_id FROM notifications WHERE notification_id = $notif_id AND user_id = $user_id");
            if ($check->num_rows > 0) {
                
                // التحقق من عدم وجود اعتراض مسبق لنفس الإنذار لمنع التكرار
                $check_appeal = $conn->query("SELECT appeal_id FROM appeals WHERE notification_id = $notif_id");
                if ($check_appeal->num_rows > 0) {
                    header("Location: ../user/notifications.php?error=appeal_exists");
                    exit();
                }

                $stmt = $conn->prepare("INSERT INTO appeals (user_id, notification_id, reason) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $user_id, $notif_id, $reason);
                if ($stmt->execute()) {
                    // إرسال إشعار للمستخدم بتأكيد استلام الاعتراض
                    $msg = "تم استلام اعتراضك بنجاح وسيتم مراجعته من قبل الإدارة في أقرب وقت.";
                    $conn->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$msg', 'info', NOW())");
                    
                    header("Location: ../user/notifications.php?msg=appeal_sent");
                } else {
                    header("Location: ../user/notifications.php?error=appeal_failed");
                }
            }
        } else {
            header("Location: ../user/notifications.php?error=empty_reason");
        }
    }

    // 4. الاشتراك في تنبيهات التوفر
    elseif ($action === 'subscribe_availability') {
        $prov_id = intval($_POST['provider_id']);
        $main_id = $_POST['main_id'] ?? '';
        // استخدام INSERT IGNORE لتجنب التكرار بفضل القيد UNIQUE في قاعدة البيانات
        // نفترض وجود جدول availability_subscriptions (requester_id, provider_id)
        $conn->query("INSERT IGNORE INTO availability_subscriptions (requester_id, provider_id) VALUES ($user_id, $prov_id)");
        header("Location: ../user/services_list.php?main_id=$main_id&subscribe_success=1#card-" . $prov_id);
        exit();
    }

    // 5. إلغاء الاشتراك في تنبيهات التوفر
    elseif ($action === 'unsubscribe_availability') {
        $prov_id = intval($_POST['provider_id']);
        $main_id = $_POST['main_id'] ?? '';
        $conn->query("DELETE FROM availability_subscriptions WHERE requester_id = $user_id AND provider_id = $prov_id");
        header("Location: ../user/services_list.php?main_id=$main_id&unsubscribe_success=1#card-" . $prov_id);
        exit();
    }
} else {
    header("Location: ../user/notifications.php");
}
?>