<?php 
require_once 'config/db_connect.php';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$error = '';
$valid_link = false;

// التحقق من صحة الرابط والتوكن
if (!empty($token) && !empty($email)) {
    $current_date = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("SELECT id, token FROM password_resets WHERE email = ? AND expires_at >= ?");
    $stmt->bind_param("ss", $email, $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if (password_verify($token, $row['token'])) {
            $valid_link = true;
            break;
        }
    }
    $stmt->close();
    
    if (!$valid_link) {
        $error = "عذراً، الرابط غير صالح أو منتهي الصلاحية.";
    }
} else {
    $error = "رابط غير صحيح.";
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعيين كلمة المرور الجديدة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo filemtime(__DIR__ . '/assets/css/login.css'); ?>">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/sweetalert_custom.css">
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <!-- سكربت الوضع الليلي -->
    <script src="assets/js/dark_mode.js" defer></script>
</head>
<body>
    <div class="login-card">
        <a href="user/login.php"><img src="assets/images/close.svg" class="close-btn"></a>
        <div class="form-section">
            <h3 class="page-title mb-4">كلمة المرور الجديدة</h3>
            
            <?php if ($valid_link): ?>
                <form action="php/process_new_password.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="custom-input-group mb-3">
                        <input type="password" name="password" class="form-control custom-input" placeholder="كلمة السر الجديدة" required minlength="8">
                    </div>

                    <div class="custom-input-group mb-4">
                        <input type="password" name="confirm_password" class="form-control custom-input" placeholder="تأكيد كلمة السر" required>
                    </div>

                    <button type="submit" class="btn btn-login-blue">حفظ التغييرات</button>
                </form>
            <?php else: ?>
                <div class="alert alert-danger text-center"><?php echo $error; ?></div>
                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="btn btn-secondary btn-sm">طلب رابط جديد</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="blue-sidebar d-none d-lg-flex">
            <h1 class="brand-title">إثراء</h1>
            <p class="brand-subtitle">ابدأ من جديد</p>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('error')) {
                let errorText = '';
                if (urlParams.get('error') === 'mismatch') {
                    errorText = 'كلمات المرور غير متطابقة. يرجى المحاولة مرة أخرى.';
                } else if (urlParams.get('error') === 'weak') {
                    errorText = 'كلمة المرور ضعيفة. يجب أن لا تقل عن 8 خانات وتحتوي على حروف وأرقام.';
                } else if (urlParams.get('error') === 'same_password') {
                    errorText = 'كلمة المرور الجديدة يجب أن تكون مختلفة عن كلمة المرور السابقة.';
                }

                if(errorText) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في الإدخال',
                        text: errorText,
                    });
                }
            }
        });
    </script>
</body>
</html>