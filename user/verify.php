<?php
require_once '../config/db_connect.php';

if (!isset($_GET['token'])) {
    die("رابط التأكيد غير صالح.");
}

$token = $_GET['token'];

// البحث عن المستخدم باستخدام התוكن
$sql = "SELECT user_id, pending_email FROM users WHERE verification_token = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // إذا كان هناك إيميل معلق، ننقله إلى الإيميل الرئيسي ونفعل الحساب
    if (!empty($user['pending_email'])) {
        $update_sql = "UPDATE users SET email = pending_email, pending_email = NULL, verification_token = NULL, email_verified_at = NOW() WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user['user_id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_msg'] = "تم تأكيد بريدك الإلكتروني بنجاح وتم اعتماده في حسابك!";
            $target = isset($_SESSION['user_id']) ? 'my_account.php' : 'login.php';
        } else {
            $update_stmt->close();
        }
    } else {
        // إذا لم يكن هناك إيميل معلق، ربما كان فقط تأكيد للحالي
        $update_sql = "UPDATE users SET verification_token = NULL, email_verified_at = NOW() WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        $target = isset($_SESSION['user_id']) ? 'my_account.php' : 'login.php';
} else {
    echo "رابط التأكيد منتهي الصلاحية أو غير صحيح.";
}
?>
