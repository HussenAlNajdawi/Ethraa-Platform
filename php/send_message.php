<?php
require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'غير مصرح']);
    exit();
}

// التحقق من رمز الـ CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'رمز غير صالح (حماية CSRF)']);
    exit();
}

$user_id = $_SESSION['user_id'];
$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$message_text = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';
$has_attachment = (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK);

if ($request_id <= 0 || (empty($message_text) && !$has_attachment)) {
    echo json_encode(['status' => 'error', 'message' => 'يرجى كتابة رسالة أو إرفاق صورة']);
    exit();
}

$sql_check = "SELECT requester_id, provider_id, status FROM requests WHERE request_id = ?";
$stmt = $conn->prepare($sql_check);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'الطلب غير موجود']);
    exit();
}

$req = $res->fetch_assoc();
$stmt->close();

if ($user_id != $req['requester_id'] && $user_id != $req['provider_id']) {
    echo json_encode(['status' => 'error', 'message' => 'ليس لديك صلاحية للمشاركة في هذه المحادثة']);
    exit();
}

if ($req['status'] === 'completed') {
    echo json_encode(['status' => 'error', 'message' => 'الطلب مكتمل، لا يمكن إرسال المزيد من الرسائل']);
    exit();
}

$receiver_id = ($user_id == $req['requester_id']) ? $req['provider_id'] : $req['requester_id'];

// --- نظام الإشراف التلقائي (Auto-Moderation) ---
require_once 'moderation_system.php';

// 1. التحقق من حالة الكتم (Mute Check)
$stmt_mute = $conn->prepare("SELECT TIMESTAMPDIFF(MINUTE, NOW(), muted_until) as remaining_minutes FROM users WHERE user_id = ? AND muted_until > NOW()");
$stmt_mute->bind_param("i", $user_id);
$stmt_mute->execute();
$mute_res = $stmt_mute->get_result()->fetch_assoc();
$stmt_mute->close();

if ($mute_res && $mute_res['remaining_minutes'] !== null) {
    echo json_encode(['status' => 'error', 'message' => 'أنت ممنوع من إرسال الرسائل مؤقتاً بسبب مخالفاتك. يرجى المحاولة بعد ' . max(1, $mute_res['remaining_minutes']) . ' دقيقة.']);
    exit();
}

// 2. الحماية من السبام (Spam Protection)
// التحقق من إرسال أكثر من 5 رسائل في آخر 10 ثواني
$stmt_spam = $conn->prepare("SELECT COUNT(*) as msg_count FROM messages WHERE sender_id = ? AND created_at > (NOW() - INTERVAL 10 SECOND)");
$stmt_spam->bind_param("i", $user_id);
$stmt_spam->execute();
$spam_count = $stmt_spam->get_result()->fetch_assoc()['msg_count'];
$stmt_spam->close();

if ($spam_count >= 5) {
    applyUserPenalty($conn, $user_id, 2); // نقطتين للسبام
    echo json_encode(['status' => 'error', 'message' => 'تم اكتشاف إرسال رسائل متكررة بسرعة (سبام). تمت إضافة مخالفة لحسابك!']);
    exit();
}

// التحقق من تكرار نفس الرسالة 5 مرات متتالية
$stmt_dup = $conn->prepare("SELECT message_text FROM messages WHERE sender_id = ? AND request_id = ? ORDER BY created_at DESC LIMIT 4");
$stmt_dup->bind_param("ii", $user_id, $request_id);
$stmt_dup->execute();
$res_dup = $stmt_dup->get_result();

$consecutive_count = 0;
while ($row = $res_dup->fetch_assoc()) {
    if ($row['message_text'] === $message_text) {
        $consecutive_count++;
    } else {
        break; // إذا وجدنا رسالة مختلفة، نتوقف عن العد
    }
}
$stmt_dup->close();

if ($consecutive_count === 4) {
    echo json_encode(['status' => 'error', 'message' => 'لا يمكنك إرسال نفس الرسالة 5 مرات متتالية! يرجى تغيير محتوى رسالتك.']);
    exit();
}

// 3. فلترة الشتائم والكلمات الممنوعة (Profanity Check)
if (checkProfanity($message_text)) {
    applyUserPenalty($conn, $user_id, 1); // نقطة للكلمات الممنوعة
    echo json_encode(['status' => 'error', 'message' => 'تحذير: رسالتك تحتوي على كلمات ممنوعة أو أرقام تواصل خارجي. تم تسجيل مخالفة!']);
    exit();
}
// ------------------------------------------------

$attachment_path = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    // 0. حماية من إغراق السيرفر بالصور (Rate Limiting)
    if (isset($_SESSION['last_chat_upload']) && (time() - $_SESSION['last_chat_upload']) < 2) {
        echo json_encode(['status' => 'error', 'message' => 'يرجى الانتظار بضع ثوانٍ قبل إرسال صورة أخرى.']);
        exit();
    }
    $_SESSION['last_chat_upload'] = time();

    $file_tmp = $_FILES['attachment']['tmp_name'];
    $file_name = $_FILES['attachment']['name'];
    $file_size = $_FILES['attachment']['size'];
    
    // 1. تحديد حجم أقصى (5MB)
    $max_size = 5 * 1024 * 1024;
    if ($file_size > $max_size) {
        echo json_encode(['status' => 'error', 'message' => 'حجم الصورة كبير جداً. الحد الأقصى هو 5 ميغابايت.']);
        exit();
    }
    
    // 2. السماح بالامتدادات المحددة فقط
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // حماية إضافية للتحقق من نوع الملف الحقيقي (MIME) وهيكل الصورة
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
    $file_mime_type = mime_content_type($file_tmp);
    $img_info = @getimagesize($file_tmp);
    
    if (!in_array($file_extension, $allowed_extensions) || !in_array($file_mime_type, $allowed_mime_types) || $img_info === false) {
        echo json_encode(['status' => 'error', 'message' => 'صيغة الملف غير مسموحة أو الصورة غير صالحة. يرجى رفع صور فقط (JPG, PNG, WEBP).']);
        exit();
    }

    $upload_dir = '../uploads/chat/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    // 3. إعادة تسمية الملف عشوائياً (Random Name)
    $new_file_name = bin2hex(random_bytes(16)) . '_' . time() . '.' . $file_extension;
    $target_file = $upload_dir . $new_file_name;
    
    // 4. إعادة رسم الصورة وتفريغ الميتاداتا إذا توفرت مكتبة GD، أو الحفظ الآمن المباشر
    $saved = false;
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring(file_get_contents($file_tmp));
        if ($img !== false) {
            if ($file_extension === 'jpg' || $file_extension === 'jpeg') {
                $saved = imagejpeg($img, $target_file, 85);
            } elseif ($file_extension === 'png') {
                imagealphablending($img, true);
                imagesavealpha($img, true);
                $saved = imagepng($img, $target_file, 8);
            } elseif ($file_extension === 'webp') {
                if (function_exists('imagewebp')) {
                    $saved = imagewebp($img, $target_file, 85);
                } else {
                    $saved = imagejpeg($img, $target_file, 85);
                }
            }
            imagedestroy($img);
        }
    }
    
    if (!$saved) {
        // إذا لم تتوفر GD أو فشلت، نستخدم الحفظ الآمن بالاسم العشوائي الموثوق
        $saved = move_uploaded_file($file_tmp, $target_file);
    }
    
    if ($saved) {
        $attachment_path = 'uploads/chat/' . $new_file_name;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'حدث خطأ أثناء معالجة وحفظ الصورة.']);
        exit();
    }
}

$sql_insert = "INSERT INTO messages (request_id, sender_id, receiver_id, message_text, attachment) VALUES (?, ?, ?, ?, ?)";
$stmt_in = $conn->prepare($sql_insert);
$stmt_in->bind_param("iiiss", $request_id, $user_id, $receiver_id, $message_text, $attachment_path);

if ($stmt_in->execute()) {
    // التحقق من وجود إشعار غير مقروء لنفس الطلب لتجنب تكرار الإشعارات
    $notif_check = $conn->prepare("SELECT notification_id FROM notifications WHERE user_id = ? AND type = 'new_message' AND is_read = 0 AND message LIKE ?");
    $search_str = "%chat.php?request_id={$request_id}%";
    $notif_check->bind_param("is", $receiver_id, $search_str);
    $notif_check->execute();
    $notif_res = $notif_check->get_result();
    
    if ($notif_res->num_rows === 0) {
        $sender_query = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $sender_query->bind_param("i", $user_id);
        $sender_query->execute();
        $sender_res = $sender_query->get_result();
        $sender_data = $sender_res->fetch_assoc();
        $sender_name = $sender_data['first_name'] . ' ' . $sender_data['last_name'];
        $sender_query->close();

        $notif_msg = "لديك رسالة جديدة من <a href='chat.php?request_id={$request_id}' style='color: #021C7B; font-weight: bold; text-decoration: underline;'>{$sender_name}</a>";
        $notif_insert = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'new_message')");
        $notif_insert->bind_param("is", $receiver_id, $notif_msg);
        $notif_insert->execute();
        $notif_insert->close();
    }
    $notif_check->close();

    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'حدث خطأ أثناء الإرسال']);
}
$stmt_in->close();
?>

