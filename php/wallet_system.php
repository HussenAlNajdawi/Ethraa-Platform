<?php
function logPointTransaction($conn, $user_id, $amount, $type, $description, $request_id = null) {
    if ($amount == 0) return true;
    
    $sql = "INSERT INTO points_transactions (user_id, request_id, amount, type, description) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiss", $user_id, $request_id, $amount, $type, $description);
    $res = $stmt->execute();
    $stmt->close();
    return $res;
}

/**
 * منح مكافأة الإحالة فقط بعد إكمال أول خدمة حقيقية للمستخدم
 */
function checkAndRewardReferral($conn, $user_id) {
    if (!$user_id) return false;

    // التأكد من وجود الأعمدة
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS referrer_id INT NULL");
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_rewarded TINYINT(1) DEFAULT 0");

    $stmt = $conn->prepare("SELECT referrer_id, referral_rewarded, first_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user_data && !empty($user_data['referrer_id']) && $user_data['referrer_id'] > 0 && $user_data['referral_rewarded'] == 0) {
        $ref_id = intval($user_data['referrer_id']);
        
        // التحقق من أن الحسابين ليسا نفس الحساب
        if ($ref_id != $user_id) {
            // تحديث ذري مشروط لمنع تكرار المكافأة
            $stmt_up = $conn->prepare("UPDATE users SET referral_rewarded = 1 WHERE user_id = ? AND referral_rewarded = 0");
            $stmt_up->bind_param("i", $user_id);
            $stmt_up->execute();
            
            if ($stmt_up->affected_rows > 0) {
                $stmt_up->close();

                // زيادة نقطة للداعي
                $stmt_ref = $conn->prepare("UPDATE users SET points = points + 1 WHERE user_id = ?");
                $stmt_ref->bind_param("i", $ref_id);
                $stmt_ref->execute();
                $stmt_ref->close();

                logPointTransaction($conn, $ref_id, 1, 'earn', 'مكافأة دعوة صديق (أكمل أول خدمة له)');

                // إرسال إشعار للداعي
                $ref_name = htmlspecialchars($user_data['first_name']);
                $msg = "تهانينا! أكمل صديقك ($ref_name) أول خدمة له بنجاح، وحصلت على نقطة واحدة مجانية كهدية دعوة.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'info', NOW())");
                $notif_stmt->bind_param("is", $ref_id, $msg);
                $notif_stmt->execute();
                $notif_stmt->close();

                return true;
            }
            $stmt_up->close();
        }
    }
    return false;
}
?>
