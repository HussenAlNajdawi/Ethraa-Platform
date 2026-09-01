<?php
// php/moderation_system.php

function applyUserPenalty($conn, $user_id, $points_to_add) {
    // جلب النقاط الحالية
    $stmt = $conn->prepare("SELECT violations_points, status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) return;

    // لا تطبق العقوبات إذا كان محظوراً نهائياً بالفعل
    if ($user['status'] === 'banned') return;

    $new_points = $user['violations_points'] + $points_to_add;
    
    $mute_until = NULL;
    $ban_until = NULL;
    $new_status = $user['status'];

    if ($new_points >= 20) {
        $new_status = 'banned';
        $ban_until = '2099-12-31 23:59:59';
    } elseif ($new_points >= 10) {
        $ban_until = date('Y-m-d H:i:s', strtotime('+1 day'));
    } elseif ($new_points >= 5) {
        $mute_until = date('Y-m-d H:i:s', strtotime('+1 hour'));
    }

    $update_sql = "UPDATE users SET violations_points = ?";
    
    $types = "i";
    $params = [$new_points];

    if ($mute_until) {
        $update_sql .= ", muted_until = ?";
        $types .= "s";
        $params[] = $mute_until;
    }
    
    if ($ban_until) {
        $update_sql .= ", banned_until = ?";
        $types .= "s";
        $params[] = $ban_until;
    }

    if ($new_status !== $user['status']) {
        $update_sql .= ", status = ?";
        $types .= "s";
        $params[] = $new_status;
    }

    $update_sql .= " WHERE user_id = ?";
    $types .= "i";
    $params[] = $user_id;

    $stmt_update = $conn->prepare($update_sql);
    $stmt_update->bind_param($types, ...$params);
    $stmt_update->execute();
    $stmt_update->close();
}

function checkProfanity($message) {
    $bad_words = [
        // كلمات بذيئة 
        'كلب', 'حمار', 'غبي', 'نصاب', 'محتال', 'زبالة', 'حقير',
        'shit', 'fuck', 'asshole', 'bitch'
    ];
    
    // إزالة التشكيل
    $clean_msg = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}]/u', '', $message);
    $clean_msg = mb_strtolower($clean_msg, 'UTF-8');
    
    foreach ($bad_words as $word) {
        if (mb_strpos($clean_msg, $word) !== false) {
            return true;
        }
    }

    return false;
}
?>
