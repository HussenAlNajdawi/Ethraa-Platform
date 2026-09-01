<?php
require_once '../config/db_connect.php';

// إنشاء CSRF Token إذا لم يكن موجوداً
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    
    $cookie_parts = explode(':', $_COOKIE['remember_me']);
    
    if (count($cookie_parts) === 2) {
        $user_id = (int)$cookie_parts[0];
        $token = $cookie_parts[1];

        $stmt = $conn->prepare("SELECT user_id, password, first_name, status, banned_until, lockout_time, remember_token, service_id, session_version FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            // فحص حالة الحظر أو التعطيل أو القفل
            $is_banned = ($user['status'] !== 'active') || ($user['banned_until'] && strtotime($user['banned_until']) > time());
            $is_locked = ($user['lockout_time'] && strtotime($user['lockout_time']) > time());

            if ($is_banned || $is_locked) {
                // حذف الكوكي وإبطال التوكن لمنع تكرار المحاولات
                setcookie('remember_me', '', time() - 3600, '/');
                $rem_stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE user_id = ?");
                $rem_stmt->bind_param("i", $user_id);
                $rem_stmt->execute();
                $rem_stmt->close();
                
                $err = $is_banned ? 'banned' : 'locked';
                header("Location: login.php?error=$err");
                exit();
            }

            // التأكد من صحة التوكن
            if (!empty($user['remember_token']) && password_verify($token, $user['remember_token'])) {
                // 1. تجديد Session ID لمنع تثبيت الجلسة
                session_regenerate_id(true);

                // 2. تدوير التوكن (Token Rotation) لزيادة الأمان
                $new_token = bin2hex(random_bytes(20));
                $new_hashed_token = password_hash($new_token, PASSWORD_DEFAULT);
                
                $up_token = $conn->prepare("UPDATE users SET remember_token = ? WHERE user_id = ?");
                $up_token->bind_param("si", $new_hashed_token, $user['user_id']);
                $up_token->execute();
                $up_token->close();

                $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                setcookie('remember_me', $user['user_id'] . ':' . $new_token, [
                    'expires'  => time() + (86400 * 30),
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $is_https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['user_name'] = $user['first_name'];
                $_SESSION['session_version'] = (int)($user['session_version'] ?? 1);
                $_SESSION['last_activity'] = time();

                header("Location: user_home.php");
                exit();
            } else {
                setcookie('remember_me', '', time() - 3600, '/');
            }
        } else {
            $stmt->close();
            setcookie('remember_me', '', time() - 3600, '/');
        }
    }
}

$saved_phone = isset($_SESSION['login_phone']) ? $_SESSION['login_phone'] : '';

// كود PHP بسيط لحساب الوقت المتبقي عند تحميل الصفحة
$seconds_left = 0;
if (isset($_GET['expiry'])) {
    $expiry_time = (int)$_GET['expiry'];
    $current_time = time();
    $seconds_left = $expiry_time - $current_time;
    // إذا انتهى الوقت، نجعل النتيجة صفر
    if ($seconds_left < 0) $seconds_left = 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>"> 
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>"> 
    <link rel="stylesheet" href="../assets/css/login.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/login.css'); ?>"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <!-- سكربت الوضع الليلي -->
    <script src="../assets/js/dark_mode.js" defer></script>
</head>
<body>

    <div class="login-card">
        <a href="../index.php"><img src="../assets/images/close.svg" class="close-btn" alt="إغلاق"></a>

        <div class="form-section">
            <h3 class="page-title">تسجيل الدخول</h3>
            
            <?php if(isset($_GET['error']) && ($_GET['error'] == 'csrf_error' || $_GET['error'] == 'session_expired')): ?>
                <div class="alert alert-warning text-center p-2 small mb-4" style="border-radius: 12px; font-weight: bold;">
                    <i class="fa-solid fa-clock-rotate-left ms-1"></i> انتهت صلاحية الجلسة أو الرمز الأمني، يرجى إعادة المحاولة.
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['error']) && ($_GET['error'] == 'banned' || $_GET['error'] == 'banned_warnings' || $_GET['error'] == 'maintenance')): ?>
                <div class="alert alert-danger text-center p-2 small mb-4" style="border-radius: 12px; font-weight: bold;">
                    <?php if($_GET['error'] == 'banned_warnings'): ?>
                        <i class="fa-solid fa-ban ms-1"></i> تم حظر حسابك نهائياً بسبب تجاوز عدد الإنذارات (3 إنذارات).
                    <?php elseif($_GET['error'] == 'maintenance'): ?>
                        <i class="fa-solid fa-wrench ms-1"></i> الموقع الآن في وضع الصيانة وتم تسجيل خروجك. يرجى العودة لاحقاً.
                    <?php else: ?>
                        <i class="fa-solid fa-ban ms-1"></i> تم حظر حسابك من قبل الإدارة.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error']) && ($_GET['error'] == 'locked' || $_GET['error'] == 'temp_banned')): ?>
                <div class="alert alert-danger text-center p-2 small mb-4" style="border-radius: 12px; font-weight: bold;">
                    <?php if ($_GET['error'] == 'temp_banned'): ?>
                        تم حظر حسابك مؤقتاً بسبب المخالفات.<br>
                    <?php else: ?>
                        تم قفل الحساب مؤقتاً لمحاولات خاطئة.<br>
                    <?php endif; ?>
                    يرجى الانتظار: <span id="countdownTimer" style="font-size: 1.1em; color: #a94442;">جاري الحساب...</span>
                </div>
                
                <script>
                    let timeLeft = <?php echo $seconds_left; ?>;
                    const timerElement = document.getElementById('countdownTimer');
                    
                    function updateDisplay() {
                        if (timeLeft <= 0) {
                            clearInterval(countdown);
                            timerElement.innerHTML = "انتهت مدة الحظر!";
                            setTimeout(function(){ window.location.href = 'login.php'; }, 1500);
                        } else {
                            let hours = Math.floor(timeLeft / 3600);
                            let minutes = Math.floor((timeLeft % 3600) / 60);
                            let seconds = timeLeft % 60;
                            seconds = seconds < 10 ? '0' + seconds : seconds;
                            minutes = minutes < 10 ? '0' + minutes : minutes;
                            if (hours > 0) {
                                timerElement.innerHTML = hours + ":" + minutes + ":" + seconds + " ساعة";
                            } else {
                                timerElement.innerHTML = minutes + ":" + seconds + " دقيقة";
                            }
                        }
                    }

                    updateDisplay();
                    const countdown = setInterval(function() {
                        timeLeft -= 1;
                        updateDisplay();
                    }, 1000);
                </script>
            <?php endif; ?>

            <form action="../php/login_process.php" method="POST">
                <!-- إضافة CSRF Token المخفي -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="custom-input-group" style="direction: ltr;">
                    <input type="tel" name="phone" class="form-control custom-input <?php if(isset($_GET['error']) && $_GET['error'] == 'no_user') echo 'input-error'; ?>" 
                           placeholder="79xxxxxxx" 
                           maxlength="9"
                           style="text-align: right;" 
                           value="<?php echo htmlspecialchars($saved_phone); ?>" 
                           required>
                    <span style="position: absolute; left: 15px; top: 15px; color: #555; font-size: 14px;">+962</span>
                </div>
                
                <?php if(isset($_GET['error']) && $_GET['error'] == 'no_user'): ?>
                    <div class="text-danger small mb-2" style="font-weight:bold; margin-top: -15px; margin-bottom: 15px;">
                        رقم الهاتف غير مسجل. <a href="register.php" style="text-decoration:underline;">إنشاء حساب؟</a>
                    </div>
                <?php endif; ?>

                <div class="custom-input-group">
                    <input type="password" name="password" id="passLogin" 
                           class="form-control custom-input <?php if(isset($_GET['error']) && in_array($_GET['error'], ['wrong_pass', 'no_user', 'invalid_credentials'])) echo 'input-error'; ?>" 
                           placeholder="كلمة السر" 
                           required>
                    <img src="../assets/images/mdi_eye-off.svg" id="eyeLogin" class="input-icon" onclick="togglePassword('passLogin', 'eyeLogin')" alt="show">
                </div>

                <?php if(isset($_GET['error']) && in_array($_GET['error'], ['wrong_pass', 'no_user', 'invalid_credentials'])): ?>
                    <div class="text-danger small mb-2" style="font-weight:bold; margin-top: -15px; margin-bottom: 15px;">
                        رقم الهاتف أو كلمة المرور غير صحيحة.
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-4">
                    <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe" value="1">
                    <label class="form-check-label text-muted small" for="rememberMe">تذكرني</label>
                    </div>
                    <a href="../forgot_password.php" class="text-decoration-none small text-muted">نسيت كلمة السر؟</a>
                </div>

                <button type="submit" class="btn btn-login-blue" <?php if(isset($_GET['error']) && $_GET['error'] == 'locked' && $seconds_left > 0) echo 'disabled style="opacity:0.6; cursor:not-allowed;"'; ?>>
                    دخول
                </button>
                
                <div class="text-center mt-3">
                    <span class="text-muted">ليس لديك حساب؟ </span>
                    <a href="register.php" class="fw-bold text-dark text-decoration-none">إنشاء حساب</a>
                </div>
            </form>
        </div>

        <div class="blue-sidebar d-none d-lg-flex">
            <h1 class="brand-title">إثراء</h1>
            <p class="brand-subtitle">أهلاً بك مجدداً</p>
        </div>
    </div>

    <script>
    // عرض الرسائل المنبثقة عند تحميل الصفحة
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            let successText = '';
            let successTitle = 'تم بنجاح!';
            if (urlParams.get('success') === 'registered') {
                successText = 'تم إنشاء حسابك بنجاح. يمكنك الآن تسجيل الدخول.';
            } else if (urlParams.get('success') === 'password_reset') {
                successTitle = 'تم تغيير كلمة المرور';
                successText = 'يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة.';
            } else if (urlParams.get('success') === 'verify_email') {
                successTitle = 'تم إنشاء الحساب';
                successText = 'تم إرسال رابط تأكيد إلى بريدك الإلكتروني. يرجى التحقق منه.';
            } else if (urlParams.get('success') === 'verified') {
                successTitle = 'تم تفعيل البريد';
                successText = 'تم تأكيد بريدك الإلكتروني بنجاح! يمكنك الآن تسجيل الدخول.';
            }

            if(successText) {
                Swal.fire({
                    icon: 'success',
                    title: successTitle,
                    text: successText,
                });
            }
        } else if (urlParams.has('msg') && urlParams.get('msg') === 'logged_out_all') {
             Swal.fire('تم تسجيل الخروج', 'تم تسجيل خروجك من جميع الأجهزة بنجاح.', 'info');
        }
    });

    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        // المسار الأساسي للمجلد
        const path = "../assets/images/";

        if (input.type === "password") {
            input.type = "text";
            // ✅ تغيير الصورة إلى العين المفتوحة عند كشف الباسورد
            icon.src = path + "mdi_eye-open.svg";
        } else {
            input.type = "password";
            // ✅ العودة لصورة العين المغلقة عند إخفاء الباسورد
            icon.src = path + "mdi_eye-off.svg";
        }
    }
</script>
<script>
    const phoneInput = document.querySelector('input[name="phone"]');
    const loginBtn = document.querySelector('button[type="submit"]');

    phoneInput.addEventListener('input', function() {
        // القيمة المدخلة
        let val = this.value;

        // 1. السماح بالأرقام فقط (حذف أي حروف)
        this.value = val.replace(/[^0-9]/g, '');

        // 2. التحقق من التنسيق الأردني (يبدأ بـ 77 أو 78 أو 79 ويكون 9 أرقام)
        // Regex: يبدأ بـ 7، ثم (7 أو 8 أو 9)، ثم 7 أرقام عشوائية
        const jordanPhoneRegex = /^(77|78|79)[0-9]{7}$/;

        if (jordanPhoneRegex.test(this.value)) {
            // صحيح: حدود خضراء وتفعيل الزر
            this.classList.remove('input-error');
            this.classList.add('input-success');
            loginBtn.disabled = false;
            loginBtn.style.opacity = "1";
        } else {
            // خطأ: حدود حمراء
            this.classList.remove('input-success');
            this.classList.add('input-error');
            // يمكنك تعطيل الزر إذا أردت إجبارهم على التصحيح
            // loginBtn.disabled = true;
            // loginBtn.style.opacity = "0.6";
        }
    });
</script>
</body>
</html>