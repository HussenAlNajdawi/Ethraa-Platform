<?php
require_once '../config/db_connect.php';

// إنشاء CSRF Token إذا لم يكن موجوداً
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// إذا كان الأدمن مسجل دخول مسبقاً، حوله للوحة التحكم
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// --- كود "تذكرني" للأدمن ---
if (!isset($_SESSION['admin_id']) && isset($_COOKIE['admin_remember_me'])) {
    
    $cookie_parts = explode(':', $_COOKIE['admin_remember_me']);
    if (count($cookie_parts) === 2) {
        $admin_id = (int)$cookie_parts[0];
        $token = $cookie_parts[1];

        $stmt = $conn->prepare("SELECT admin_id, username, full_name, role, permissions, status, remember_token, session_version, lockout_time FROM admins WHERE admin_id = ? LIMIT 1");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            $stmt->close();

            if (isset($row['status']) && $row['status'] === 'inactive') {
                setcookie('admin_remember_me', '', time() - 3600, '/');
                header("Location: admin_login.php?error=account_disabled");
                exit();
            }

            $is_locked = ($row['lockout_time'] && strtotime($row['lockout_time']) > time());
            if ($is_locked) {
                setcookie('admin_remember_me', '', time() - 3600, '/');
                header("Location: admin_login.php?error=locked");
                exit();
            }

            if (!empty($row['remember_token']) && password_verify($token, $row['remember_token'])) {
                session_regenerate_id(true);

                // تدوير التوكن
                $new_token = bin2hex(random_bytes(20));
                $new_hashed_token = password_hash($new_token, PASSWORD_DEFAULT);
                $up_stmt = $conn->prepare("UPDATE admins SET remember_token = ? WHERE admin_id = ?");
                $up_stmt->bind_param("si", $new_hashed_token, $row['admin_id']);
                $up_stmt->execute();
                $up_stmt->close();

                $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                setcookie('admin_remember_me', $row['admin_id'] . ':' . $new_token, [
                    'expires'  => time() + (86400 * 30),
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $is_https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                $_SESSION['admin_id'] = $row['admin_id'];
                $_SESSION['admin_name'] = $row['full_name'];
                $_SESSION['admin_username'] = $row['username'];
                $_SESSION['admin_role'] = $row['role'] ?? 'sub_admin';
                $perms = json_decode($row['permissions'] ?? '[]', true);
                $_SESSION['admin_permissions'] = is_array($perms) ? $perms : [];
                $_SESSION['role'] = 'admin';
                $_SESSION['session_version'] = (int)($row['session_version'] ?? 1);
                $_SESSION['last_activity'] = time();

                header("Location: dashboard.php");
                exit();
            } else {
                setcookie('admin_remember_me', '', time() - 3600, '/');
            }
        } else {
            $stmt->close();
            setcookie('admin_remember_me', '', time() - 3600, '/');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دخول المشرفين - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>"> 
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>"> 
    <link rel="stylesheet" href="../assets/css/login.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/login.css'); ?>"> 
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <!-- سكربت الوضع الليلي -->
    <script src="../assets/js/dark_mode.js" defer></script>
</head>
<body>

    <div class="login-card">
        
        <a href="../index.php"><img src="../assets/images/close.svg" class="close-btn" alt="إغلاق"></a>

        <div class="form-section">
            <h3 class="page-title">بوابة الإدارة</h3>
            
            <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger text-center p-2 small mb-4" style="border-radius: 12px; font-weight: bold;">
                    <?php 
                        if ($_GET['error'] === 'locked') {
                            echo 'تم قفل الحساب مؤقتاً لمدة 15 دقيقة لتكرار المحاولات الخاطئة.';
                        } elseif ($_GET['error'] === 'account_disabled') {
                            echo 'تم تعطيل هذا الحساب من قبل الإدارة العامة.';
                        } elseif ($_GET['error'] === 'timeout') {
                            echo 'انتهت صلاحية الجلسة بسبب عدم النشاط. يرجى تسجيل الدخول مجدداً.';
                        } elseif ($_GET['error'] === 'session_expired' || $_GET['error'] === 'csrf_error') {
                            echo 'انتهت صلاحية الجلسة أو الرمز الأمني، يرجى إعادة المحاولة.';
                        } else {
                            echo 'اسم المستخدم أو كلمة المرور غير صحيحة';
                        }
                    ?>
                </div>
            <?php endif; ?>

            <form action="../php/admin_login_process.php" method="POST">
                <!-- إضافة CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                
                <div class="custom-input-group">
                    <input type="text" name="username" class="form-control custom-input" 
                           placeholder="اسم المستخدم" 
                           style="text-align: right;" required>
                </div>

                <div class="custom-input-group">
                    <input type="password" name="password" id="passAdmin" 
                           class="form-control custom-input" 
                           placeholder="كلمة السر" required>
                    <img src="../assets/images/mdi_eye-off.svg" id="eyeAdmin" class="input-icon" onclick="togglePassword('passAdmin', 'eyeAdmin')" alt="show">
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe" value="1">
                        <label class="form-check-label text-muted small" for="rememberMe">تذكرني</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-login-blue">
                    دخول
                </button>
                
                <div class="text-center mt-4">
                    <span class="text-muted small">هذه الصفحة مخصصة للمشرفين فقط</span>
                </div>
            </form>
        </div>

        <div class="blue-sidebar d-none d-lg-flex">
    <h1 class="brand-title">إثراء</h1>
    
    <div class="d-flex align-items-center justify-content-center gap-2 mt-2">
        <p class="brand-subtitle ">بوابة الإدارة والتحكم</p>
        
        <img src="../assets/images/security.svg" alt="Security" width="28" style="opacity: 0.9;">
    </div>
</div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            const path = "../assets/images/";

            if (input.type === "password") {
                input.type = "text";
                icon.src = path + "mdi_eye-open.svg"; 
            } else {
                input.type = "password";
                icon.src = path + "mdi_eye-off.svg";
            }
        }
    </script>
</body>
</html>