<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة السر - إثراء</title>
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
        
        <a href="user/login.php">
            <img src="assets/images/close.svg" class="close-btn" alt="عودة">
        </a>

        <div class="form-section">
            <h3 class="page-title" style="margin-bottom: 10px;">نسيت كلمة السر؟</h3>
            <p class="text-muted text-center mb-4" style="font-size: 14px;">
                لا تقلق، أدخل بريدك الإلكتروني وسنرسل لك رابط إعادة التعيين.
            </p>

            <form action="php/send_reset_link.php" method="POST" onsubmit="return validateEmailForm()">
                
                <div class="custom-input-group">
                    <input type="email" name="email" id="emailInput" class="form-control custom-input" 
                           placeholder="البريد الإلكتروني المسجل" required>
                    <img src="assets/images/email_b.svg" class="input-icon" alt="email">
                </div>

                <button type="submit" class="btn btn-login-blue">إرسال الرابط</button>
                
                <div class="text-center mt-3">
                    <a href="user/login.php" class="text-decoration-none fw-bold small text-muted">العودة لتسجيل الدخول</a>
                </div>
            </form>
        </div>

        <div class="blue-sidebar d-none d-lg-flex">
            <h1 class="brand-title">استعادة الحساب</h1>
            <p class="brand-subtitle">سنساعدك في الدخول</p>
        </div>

    </div>

    <script>
        // عرض الرسائل المنبثقة عند تحميل الصفحة
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('msg') && urlParams.get('msg') === 'sent') {
                Swal.fire({
                    icon: 'success',
                    title: 'تم الإرسال بنجاح!',
                    text: 'لقد تم إرسال رابط استعادة كلمة المرور إلى بريدك الإلكتروني.',
                });
            } else if (urlParams.has('error') && urlParams.get('error') === 'not_found') {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: 'البريد الإلكتروني الذي أدخلته غير مسجل لدينا.',
                });
            }
        });

        function validateEmailForm() {
            const emailInput = document.getElementById('emailInput');
            
            // تعبير نمطي للتحقق من صيغة البريد الإلكتروني
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailRegex.test(emailInput.value)) {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الإدخال',
                    text: 'يرجى إدخال بريد إلكتروني صحيح (مثال: user@example.com).',
                });
                emailInput.focus();
                return false; // منع الإرسال
            }
            
            return true;
        }
    </script>

</body>
</html>