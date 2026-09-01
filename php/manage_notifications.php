<?php
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
                $placeholders = implode(',', array_fill(0, count($sys_ids), '?'));
                $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id IN ($placeholders) AND user_id = ? AND message NOT LIKE '%تنبيه%' AND message NOT LIKE '%إنذار%'");
                $types = str_repeat('i', count($sys_ids)) . 'i';
                $params = array_merge($sys_ids, [$user_id]);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            }

            // ب. إخفاء الطلبات الواردة (كمقدم خدمة)
            if (!empty($inc_ids)) {
                $placeholders = implode(',', array_fill(0, count($inc_ids), '?'));
                $stmt = $conn->prepare("UPDATE requests SET hidden_for_provider = 1 WHERE request_id IN ($placeholders) AND provider_id = ?");
                $types = str_repeat('i', count($inc_ids)) . 'i';
                $params = array_merge($inc_ids, [$user_id]);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            }

            // ج. إخفاء الطلبات الصادرة (كطالب خدمة)
            if (!empty($out_ids)) {
                $placeholders = implode(',', array_fill(0, count($out_ids), '?'));
                $stmt = $conn->prepare("UPDATE requests SET hidden_for_requester = 1 WHERE request_id IN ($placeholders) AND requester_id = ?");
                $types = str_repeat('i', count($out_ids)) . 'i';
                $params = array_merge($out_ids, [$user_id]);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            }
        }
        header("Location: ../user/notifications.php?msg=deleted");
    }

    // 2. حذف جميع الإشعارات (نظام + طلبات)
    elseif ($action === 'delete_all') {
        // حذف إشعارات النظام
        $stmt1 = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND message NOT LIKE '%تنبيه%' AND message NOT LIKE '%إنذار%'");
        $stmt1->bind_param("i", $user_id);
        $stmt1->execute();
        $stmt1->close();

        // إخفاء جميع الطلبات الواردة والصادرة
        $stmt2 = $conn->prepare("UPDATE requests SET hidden_for_provider = 1 WHERE provider_id = ?");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        $stmt2->close();

        $stmt3 = $conn->prepare("UPDATE requests SET hidden_for_requester = 1 WHERE requester_id = ?");
        $stmt3->bind_param("i", $user_id);
        $stmt3->execute();
        $stmt3->close();
        
        header("Location: ../user/notifications.php?msg=deleted_all");
    }

    // 3. تقديم اعتراض
    elseif ($action === 'submit_appeal') {
        $notif_id = (int)$_POST['notification_id'];
        $reason = trim($_POST['appeal_reason']);

        if (!empty($reason) && $notif_id > 0) {
            // التحقق من أن الإشعار يخص المستخدم
            $chk_stmt = $conn->prepare("SELECT notification_id FROM notifications WHERE notification_id = ? AND user_id = ?");
            $chk_stmt->bind_param("ii", $notif_id, $user_id);
            $chk_stmt->execute();
            $check = $chk_stmt->get_result();
            
            if ($check->num_rows > 0) {
                $chk_stmt->close();
                
                // التحقق من عدم وجود اعتراض مسبق لنفس الإنذار لمنع التكرار
                $chk_app = $conn->prepare("SELECT appeal_id FROM appeals WHERE notification_id = ?");
                $chk_app->bind_param("i", $notif_id);
                $chk_app->execute();
                $check_appeal = $chk_app->get_result();
                
                if ($check_appeal->num_rows > 0) {
                    $chk_app->close();
                    header("Location: ../user/notifications.php?error=appeal_exists");
                    exit();
                }
                $chk_app->close();

                $stmt = $conn->prepare("INSERT INTO appeals (user_id, notification_id, reason) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $user_id, $notif_id, $reason);
                if ($stmt->execute()) {
                    $stmt->close();
                    // إرسال إشعار للمستخدم بتأكيد استلام الاعتراض
                    $msg = "تم استلام اعتراضك بنجاح وسيتم مراجعته من قبل الإدارة في أقرب وقت.";
                    $ins_notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'info', NOW())");
                    $ins_notif->bind_param("is", $user_id, $msg);
                    $ins_notif->execute();
                    $ins_notif->close();
                    
                    header("Location: ../user/notifications.php?msg=appeal_sent");
                } else {
                    $stmt->close();
                    header("Location: ../user/notifications.php?error=appeal_failed");
                }
            } else {
                $chk_stmt->close();
            }
        } else {
            header("Location: ../user/notifications.php?error=empty_reason");
        }
    }

    // 4. الاشتراك في تنبيهات التوفر
    elseif ($action === 'subscribe_availability') {
        $prov_id = intval($_POST['provider_id']);
        $main_id = $_POST['main_id'] ?? '';
        $stmt = $conn->prepare("INSERT IGNORE INTO availability_subscriptions (requester_id, provider_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $prov_id);
        $stmt->execute();
        $stmt->close();
        header("Location: ../user/services_list.php?main_id=$main_id&subscribe_success=1#card-" . $prov_id);
        exit();
    }

    // 5. إلغاء الاشتراك في تنبيهات التوفر
    elseif ($action === 'unsubscribe_availability') {
        $prov_id = intval($_POST['provider_id']);
        $main_id = $_POST['main_id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM availability_subscriptions WHERE requester_id = ? AND provider_id = ?");
        $stmt->bind_param("ii", $user_id, $prov_id);
        $stmt->execute();
        $stmt->close();
        header("Location: ../user/services_list.php?main_id=$main_id&unsubscribe_success=1#card-" . $prov_id);
        exit();
    }
} else {
    header("Location: ../user/notifications.php");
}
?>