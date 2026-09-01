<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CSRF Token Validation ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token is invalid or missing, stop execution.
        die("CSRF token validation failed. الطلب مرفوض لأسباب أمنية.");
    }
    
    // قبول الطلب (يتحول إلى Accepted)
    if (isset($_POST['action']) && $_POST['action'] === 'accept_request') {
        $req_id = intval($_POST['request_id']);
        
        $stmt_accept = $conn->prepare("UPDATE requests SET status = 'accepted' WHERE request_id = ? AND provider_id = ? AND status = 'pending'");
        $stmt_accept->bind_param("ii", $req_id, $user_id);
        $stmt_accept->execute();
        $accepted = ($stmt_accept->affected_rows > 0);
        $stmt_accept->close();

        if ($accepted) {
            // إرسال إيميل للطالب بأن طلبه قد قُبل
            $stmt = $conn->prepare("SELECT u.email, u.first_name FROM requests r JOIN users u ON r.requester_id = u.user_id WHERE r.request_id = ?");
            $stmt->bind_param("i", $req_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($res) {
                $subject = "تم قبول طلبك - إثراء";
                $msg = "مرحباً {$res['first_name']}،\n\nتم قبول طلب الخدمة الخاص بك. يمكنك الآن التواصل مع مقدم الخدمة.";
                @mail($res['email'], $subject, $msg, "From: no-reply@ithraa.com\r\nContent-Type: text/plain; charset=UTF-8");
            }
        }
        
        header("Location: ../user/requests.php#req-$req_id");
        exit();
    }

    // رفض الطلب (يتحول إلى Rejected ويبقى ظاهراً)
    if (isset($_POST['action']) && $_POST['action'] === 'reject_request') {
        $req_id = intval($_POST['request_id']);
        
        // التحقق من أن المستخدم هو مقدم الخدمة وأن الطلب معلق قبل أي استرجاع
        $stmt_info = $conn->prepare("SELECT r.requester_id, r.points_cost, u.email, u.first_name FROM requests r JOIN users u ON r.requester_id = u.user_id WHERE r.request_id = ? AND r.provider_id = ? AND r.status = 'pending'");
        $stmt_info->bind_param("ii", $req_id, $user_id);
        $stmt_info->execute();
        $req_data = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();

        if ($req_data) {
            // تحديث الحالة إلى مرفوض
            $stmt_rej = $conn->prepare("UPDATE requests SET status = 'rejected' WHERE request_id = ? AND provider_id = ? AND status = 'pending'");
            $stmt_rej->bind_param("ii", $req_id, $user_id);
            $stmt_rej->execute();
            
            if ($stmt_rej->affected_rows > 0) {
                // إعادة النقاط (Refund) للطالب فقط عند نجاح التحديث
                $stmt_ref = $conn->prepare("UPDATE users SET points = points + ? WHERE user_id = ?");
                $stmt_ref->bind_param("ii", $req_data['points_cost'], $req_data['requester_id']);
                $stmt_ref->execute();
                $stmt_ref->close();
                
                require_once 'wallet_system.php';
                logPointTransaction($conn, $req_data['requester_id'], $req_data['points_cost'], 'refund', 'استرجاع نقاط بسبب رفض مقدم الخدمة للطلب', $req_id);
                
                // إرسال إيميل للطالب
                $subject = "تحديث بخصوص طلبك - إثراء";
                $msg = "مرحباً {$req_data['first_name']}،\n\nعذراً، تم رفض طلب الخدمة الخاص بك وتم إعادة النقاط لرصيدك.";
                @mail($req_data['email'], $subject, $msg, "From: no-reply@ithraa.com\r\nContent-Type: text/plain; charset=UTF-8");
            }
            $stmt_rej->close();
        }

        header("Location: ../user/requests.php#req-$req_id");
        exit();
    }

    // إنهاء الخدمة (من أحد الطرفين)
    if (isset($_POST['action']) && $_POST['action'] === 'finish_service') {
        $req_id = intval($_POST['request_id']);
        
        // التحقق من وجود الطلب وأن المستخدم طرف فيه وأن حالته مقبولة أو قيد التنفيذ
        $stmt_check = $conn->prepare("SELECT provider_id, requester_id, points_cost, provider_confirmed, requester_confirmed, status FROM requests WHERE request_id = ?");
        $stmt_check->bind_param("i", $req_id);
        $stmt_check->execute();
        $check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();
        
        if ($check && in_array($check['status'], ['accepted', 'pending'])) {
            $is_provider = ($user_id == $check['provider_id']);
            $is_requester = ($user_id == $check['requester_id']);
            
            if ($is_provider || $is_requester) {
                // نحدد أي عمود سنقوم بتحديثه بناءً على هوية المستخدم الحقيقية
                $confirm_col = $is_provider ? 'provider_confirmed' : 'requester_confirmed';
                
                // تحديث تأكيد المستخدم الحالي
                $stmt = $conn->prepare("UPDATE requests SET $confirm_col = 1, confirmed_at = IF(confirmed_at IS NULL, NOW(), confirmed_at) WHERE request_id = ?");
                $stmt->bind_param("i", $req_id);
                $stmt->execute();
                $stmt->close();
                
                // إعادة جلب حالة التأكيد للطرفين
                $stmt_recheck = $conn->prepare("SELECT provider_confirmed, requester_confirmed FROM requests WHERE request_id = ?");
                $stmt_recheck->bind_param("i", $req_id);
                $stmt_recheck->execute();
                $updated_check = $stmt_recheck->get_result()->fetch_assoc();
                $stmt_recheck->close();
                
                if ($updated_check && $updated_check['provider_confirmed'] == 1 && $updated_check['requester_confirmed'] == 1) {
                    // إذا الطرفين أكدوا، تتحول الحالة إلى مكتملة فوراً
                    $stmt_complete = $conn->prepare("UPDATE requests SET status = 'completed' WHERE request_id = ? AND status != 'completed'");
                    $stmt_complete->bind_param("i", $req_id);
                    $stmt_complete->execute();
                    
                    if ($stmt_complete->affected_rows > 0) {
                        // إضافة النقاط لمقدم الخدمة
                        $stmt_add = $conn->prepare("UPDATE users SET points = points + ? WHERE user_id = ?");
                        $stmt_add->bind_param("ii", $check['points_cost'], $check['provider_id']);
                        $stmt_add->execute();
                        $stmt_add->close();
                        
                        require_once 'wallet_system.php';
                        logPointTransaction($conn, $check['provider_id'], $check['points_cost'], 'earn', 'أرباح خدمة مكتملة', $req_id);
                        
                        // فحص ومكافأة الإحالة إذا كان هذا أول طلب مكتمل للمستخدم
                        checkAndRewardReferral($conn, $check['provider_id']);
                        checkAndRewardReferral($conn, $check['requester_id']);
                        
                        // إشعار الطرفين عبر الإيميل بالاكتمال
                        $stmt_emails = $conn->prepare("
                            SELECT u_req.email as req_email, u_req.first_name as req_name,
                                   u_prov.email as prov_email, u_prov.first_name as prov_name
                            FROM requests r
                            JOIN users u_req ON r.requester_id = u_req.user_id
                            JOIN users u_prov ON r.provider_id = u_prov.user_id
                            WHERE r.request_id = ?
                        ");
                        $stmt_emails->bind_param("i", $req_id);
                        $stmt_emails->execute();
                        $emails = $stmt_emails->get_result()->fetch_assoc();
                        $stmt_emails->close();
                        
                        if ($emails) {
                            $headers = "From: no-reply@ithraa.com\r\nContent-Type: text/plain; charset=UTF-8";
                            @mail($emails['req_email'], "اكتمال الخدمة - إثراء", "مرحباً {$emails['req_name']}،\n\nتم اكتمال الخدمة بنجاح. يرجى تقييم مقدم الخدمة.", $headers);
                            @mail($emails['prov_email'], "اكتمال الخدمة - إثراء", "مرحباً {$emails['prov_name']}،\n\nتم اكتمال الخدمة بنجاح.", $headers);
                        }
                    }
                    $stmt_complete->close();
                } else {
                    // إذا لم يكتمل التأكيد من الطرفين، نرسل إشعاراً للطرف الآخر لتنبيهه
                    $other_party_id = $is_provider ? $check['requester_id'] : $check['provider_id'];
                    
                    $search_str = "%إنهاء الخدمة بينكما%";
                    $notif_check = $conn->prepare("SELECT notification_id FROM notifications WHERE user_id = ? AND type = 'info' AND is_read = 0 AND message LIKE ?");
                    $notif_check->bind_param("is", $other_party_id, $search_str);
                    $notif_check->execute();
                    if ($notif_check->get_result()->num_rows === 0) {
                        $notif_msg = "قام الطرف الآخر بإنهاء الخدمة بينكما. يرجى التوجه لصفحة <a href='requests.php#req-{$req_id}' style='color:#021C7B; font-weight:bold; text-decoration:underline;'>الطلبات</a> والضغط على (إنهاء الخدمة) لتأكيد الانتهاء وتوثيقه.";
                        $notif_insert = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'info')");
                        $notif_insert->bind_param("is", $other_party_id, $notif_msg);
                        $notif_insert->execute();
                        $notif_insert->close();
                    }
                    $notif_check->close();
                }
            }
        }
        
        header("Location: ../user/requests.php#req-$req_id");
        exit();
    }

    // إلغاء الطلب (من قبل الطالب وهو في حالة الانتظار)
    if (isset($_POST['action']) && $_POST['action'] === 'cancel_request') {
        $req_id = intval($_POST['request_id']);
        
        // التحقق أولاً من أن الطلب يخص المستخدم وحالته معلقة 'pending' قبل أي تعديل
        $stmt_check = $conn->prepare("SELECT points_cost FROM requests WHERE request_id = ? AND requester_id = ? AND status = 'pending'");
        $stmt_check->bind_param("ii", $req_id, $user_id);
        $stmt_check->execute();
        $req_res = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($req_res) {
            // حذف الطلب
            $stmt_del = $conn->prepare("DELETE FROM requests WHERE request_id = ? AND requester_id = ? AND status = 'pending'");
            $stmt_del->bind_param("ii", $req_id, $user_id);
            $stmt_del->execute();
            
            if ($stmt_del->affected_rows > 0) {
                // إعادة النقاط فقط عند نجاح الحذف
                $cost = intval($req_res['points_cost']);
                $stmt_ref = $conn->prepare("UPDATE users SET points = points + ? WHERE user_id = ?");
                $stmt_ref->bind_param("ii", $cost, $user_id);
                $stmt_ref->execute();
                $stmt_ref->close();

                require_once 'wallet_system.php';
                logPointTransaction($conn, $user_id, $cost, 'refund', 'استرجاع نقاط بسبب إلغاء الطلب من قبل الطالب', $req_id);
            }
            $stmt_del->close();
        }
        
        // عند الحذف، نوجه المستخدم لتبويب الصادرة لأن الكرت لم يعد موجوداً
        header("Location: ../user/requests.php?active_tab=pills-outgoing");
        exit();
    }

    // تحديث تفاصيل الطلب (للطلبات الصادرة المعلقة فقط)
    if (isset($_POST['action']) && $_POST['action'] === 'update_details') {
        $req_id = intval($_POST['request_id']);
        $new_details = trim($_POST['details']);
        $stmt = $conn->prepare("UPDATE requests SET details = ? WHERE request_id = ? AND requester_id = ? AND status = 'pending'");
        $stmt->bind_param("sii", $new_details, $req_id, $user_id);
        $stmt->execute();
        header("Location: ../user/requests.php#req-$req_id");
        exit();
    }

    // إبلاغ عن طلب
    if (isset($_POST['action']) && $_POST['action'] === 'report_request') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : 'بلاغ';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $req_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

        if (!empty($content)) {
            // جلب بيانات المُبلّغ عنه (الطرف الآخر) والتحقق من أن المستخدم طرف في الطلب
            if ($req_id > 0) {
                $stmt_info = $conn->prepare("SELECT r.requester_id, r.provider_id, 
                           u_req.first_name as req_f, u_req.last_name as req_l, u_req.phone as req_p,
                           u_prov.first_name as prov_f, u_prov.last_name as prov_l, u_prov.phone as prov_p
                           FROM requests r
                           LEFT JOIN users u_req ON r.requester_id = u_req.user_id
                           LEFT JOIN users u_prov ON r.provider_id = u_prov.user_id
                           WHERE r.request_id = ? AND (r.requester_id = ? OR r.provider_id = ?)");
                $stmt_info->bind_param("iii", $req_id, $user_id, $user_id);
                $stmt_info->execute();
                $res_info = $stmt_info->get_result();
                
                if ($res_info && $res_info->num_rows > 0) {
                    $info = $res_info->fetch_assoc();
                    
                    // تحديد من هو المبلغ عنه
                    if ($user_id == $info['requester_id']) {
                        // أنا الطالب -> المبلغ عنه هو مقدم الخدمة
                        $target_id = $info['provider_id'];
                        $target_name = $info['prov_f'] . ' ' . $info['prov_l'];
                    } else {
                        // أنا مقدم الخدمة -> المبلغ عنه هو الطالب
                        $target_id = $info['requester_id'];
                        $target_name = $info['req_f'] . ' ' . $info['req_l'];
                    }
                    
                    // إضافة فاصل وبيانات مخفية لاستخراجها لاحقاً
                    $content .= "\n\n--------------------------------\n";
                    $content .= "⚠️ بيانات المُبلّغ عنه:\n";
                    $content .= "الاسم: $target_name\n";
                    $content .= "رقم المستخدم (ID): $target_id\n";
                }
                $stmt_info->close();
            }

            // إدخال البلاغ في قاعدة البيانات
            $stmt = $conn->prepare("INSERT INTO reports (user_id, title, content, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $user_id, $title, $content);
            $stmt->execute();
            $stmt->close();

            // إرسال إشعار للمستخدم
            $notif_msg = "تم استلام بلاغك: $title. سيتم مراجعته من قبل الإدارة.";
            $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'info', NOW())");
            $stmt_notif->bind_param("is", $user_id, $notif_msg);
            $stmt_notif->execute();
            $stmt_notif->close();
        }
        header("Location: ../user/requests.php?msg=report_sent");
        exit();
    }

    // تقديم تقييم
    if (isset($_POST['action']) && $_POST['action'] === 'submit_review') {
        $req_id = intval($_POST['request_id']);
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        $reviewer_id = $user_id; // The logged-in user is the reviewer

        // 1. التحقق من أن التقييم بين 1 و 5
        if ($rating < 1 || $rating > 5) {
            header("Location: ../user/requests.php?error=rating_required&active_tab=pills-outgoing#req-$req_id");
            exit();
        }

        // 2. التحقق من أن الطلب يخص المستخدم وحالته مكتملة وجلب مزود الخدمة بأمان من قاعدة البيانات
        $check_req_stmt = $conn->prepare("SELECT provider_id, status FROM requests WHERE request_id = ? AND requester_id = ?");
        $check_req_stmt->bind_param("ii", $req_id, $reviewer_id);
        $check_req_stmt->execute();
        $req_row = $check_req_stmt->get_result()->fetch_assoc();
        $check_req_stmt->close();
        
        if (!$req_row) {
            header("Location: ../user/requests.php?error=invalid_request&active_tab=pills-outgoing");
            exit();
        }

        if ($req_row['status'] !== 'completed') {
            header("Location: ../user/requests.php?error=not_completed&active_tab=pills-outgoing#req-$req_id");
            exit();
        }

        $prov_id = intval($req_row['provider_id']);

        // 3. التحقق من عدم وجود تقييم مسبق لهذا الطلب
        $check_review_stmt = $conn->prepare("SELECT review_id FROM reviews WHERE request_id = ?");
        $check_review_stmt->bind_param("i", $req_id);
        $check_review_stmt->execute();
        $has_review = ($check_review_stmt->get_result()->num_rows > 0);
        $check_review_stmt->close();

        if ($has_review) {
            header("Location: ../user/requests.php?error=already_reviewed&active_tab=pills-outgoing#req-$req_id");
            exit();
        }

        // 4. إدخال التقييم الجديد في قاعدة البيانات مع ضمان القيد الفريد
        $conn->query("ALTER TABLE reviews ADD UNIQUE KEY IF NOT EXISTS unique_request_review (request_id)");
        $insert_stmt = $conn->prepare("INSERT INTO reviews (request_id, reviewer_id, provider_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $insert_stmt->bind_param("iiiis", $req_id, $reviewer_id, $prov_id, $rating, $comment);
        
        if ($insert_stmt->execute()) {
            $insert_stmt->close();
            header("Location: ../user/requests.php?msg=review_submitted&active_tab=pills-outgoing#req-$req_id");
            exit();
        } else {
            error_log("Review insert error: " . $conn->error);
            $insert_stmt->close();
            header("Location: ../user/requests.php?error=db_error&active_tab=pills-outgoing#req-$req_id");
            exit();
        }
    }
} else {
    header("Location: ../user/requests.php");
    exit();
}
?>