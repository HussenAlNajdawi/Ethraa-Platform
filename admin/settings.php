<?php
session_start();
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) { 
    header('Location: admin_login.php'); 
    exit(); 
}
requireAdminPermission('manage_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $daily_limit = intval($_POST['daily_limit'] ?? 3);
    $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
    
    // بيانات التواصل
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    
    // إعدادات البريد SMTP
    $smtp_user   = trim($_POST['smtp_user'] ?? '');
    $smtp_pass   = trim($_POST['smtp_pass'] ?? '');
    $smtp_host   = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
    $smtp_port   = trim($_POST['smtp_port'] ?? '465');
    $smtp_secure = trim($_POST['smtp_secure'] ?? 'ssl');
    $from_email  = trim($_POST['from_email'] ?? $smtp_user);
    
    // روابط التواصل الاجتماعي
    $normalizeLink = function($link) {
        $link = trim($link ?? '');
        if (empty($link) || $link === '#') return '#';
        if (str_starts_with($link, '#') && strlen($link) > 1) $link = ltrim($link, '#');
        $link = trim($link);
        if (empty($link) || $link === '#') return '#';
        if (!preg_match('~^(?:f|ht)tps?://~i', $link)) {
            $link = 'https://' . ltrim($link, '/');
        }
        return $link;
    };

    $fb = $normalizeLink($_POST['facebook_link'] ?? '');
    $tw = $normalizeLink($_POST['twitter_link'] ?? '');
    $ig = $normalizeLink($_POST['instagram_link'] ?? '');
    $li = $normalizeLink($_POST['linkedin_link'] ?? '');

    $save_settings = [
        'daily_limit'      => $daily_limit,
        'maintenance_mode' => $maintenance,
        'contact_phone'    => $contact_phone,
        'contact_email'    => $contact_email,
        'smtp_user'        => $smtp_user,
        'smtp_pass'        => $smtp_pass,
        'smtp_host'        => $smtp_host,
        'smtp_port'        => $smtp_port,
        'smtp_secure'      => $smtp_secure,
        'from_email'       => $from_email,
        'facebook_link'    => $fb,
        'twitter_link'     => $tw,
        'instagram_link'   => $ig,
        'linkedin_link'    => $li
    ];

    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    foreach ($save_settings as $key => $val) {
        $stmt->bind_param("sss", $key, $val, $val);
        $stmt->execute();
    }
    $stmt->close();

    // تحديث الحد اليومي للمستخدمين
    $stmt2 = $conn->prepare("UPDATE users SET daily_limit = ?");
    $stmt2->bind_param("i", $daily_limit);
    $stmt2->execute();
    $stmt2->close();

    logAdminAction($conn, $_SESSION['admin_id'], 'UPDATE_SETTINGS', 'قام بتحديث إعدادات المنصة، التواصل، وخادم البريد.');
    $success_msg = 'تم حفظ جميع الإعدادات ومعلومات المنصة بنجاح!';
}

$settings = [];
$res = $conn->query('SELECT * FROM settings');
if ($res) {
    while ($row = $res->fetch_assoc()) { 
        $settings[$row['setting_key']] = $row['setting_value']; 
    }
}
?>
<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>إعدادات المنصة - إثراء</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <link rel='stylesheet' href='../assets/css/style.css'>
    <link rel='stylesheet' href='../assets/css/dark_mode.css'>
    <link rel='stylesheet' href='../assets/css/admin.css'>
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
</head>
<body>
    <div class='d-flex' id='wrapper'>
        <?php include 'sidebar.php'; ?>
        <div id='page-content-wrapper'>
            <nav class='navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4'>
                <div class='d-flex align-items-center'>
                    <i class='fas fa-align-left primary-text fs-4 me-3' id='menu-toggle'></i>
                    <h2 class='fs-2 m-0'>إعدادات المنصة العامة</h2>
                </div>
            </nav>
            <div class='container-fluid px-4'>
                <?php if (isset($success_msg)): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            safeSwal({
                                icon: 'success',
                                title: 'تم الحفظ',
                                text: <?php echo json_encode($success_msg); ?>,
                                confirmButtonText: 'حسناً',
                                confirmButtonColor: '#021C7B'
                            });
                        });
                    </script>
                <?php endif; ?>
                
                <form method='POST'>
                    <!-- البطاقة 1: الإعدادات العامة للمنصة -->
                    <div class='card shadow-sm border-0 mb-4' style='border-radius: 15px;'>
                        <div class='card-body p-4'>
                            <h4 class='mb-3 text-primary fw-bold'>الإعدادات الأساسية</h4>
                            <div class='row g-3'>
                                <div class='col-md-6'>
                                    <label class='form-label fw-bold'>الحد الأقصى للطلبات اليومية (لكل مستخدم)</label>
                                    <input type='number' name='daily_limit' class='form-control' min='1' max='50' value='<?php echo htmlspecialchars($settings['daily_limit'] ?? '3'); ?>' required>
                                </div>
                                <div class='col-md-6 d-flex align-items-center mt-4'>
                                    <div class='form-check form-switch fs-5'>
                                        <input class='form-check-input' type='checkbox' name='maintenance_mode' id='maintenanceSwitch' <?php echo (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == '1') ? 'checked' : ''; ?>>
                                        <label class='form-check-label fw-bold ms-2' for='maintenanceSwitch'>تفعيل وضع الصيانة (إغلاق الموقع للزوار)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- البطاقة 2: معلومات التواصل الرسمية (الفوتر) -->
                    <div class='card shadow-sm border-0 mb-4' style='border-radius: 15px;'>
                        <div class='card-body p-4'>
                            <h4 class='mb-3 text-primary fw-bold'>معلومات التواصل (المعروضة في الفوتر وصفحات الموقع)</h4>
                            <p class='text-muted small'>هذه البيانات تظهر لزوار ومستخدمي المنصة في أسفل الصفحات وقسم اتصل بنا.</p>
                            <div class='row g-3'>
                                <div class='col-md-6'>
                                    <label class='form-label fw-bold'>رقم هاتف المنصة / الدعم</label>
                                    <input type='text' name='contact_phone' class='form-control' dir='ltr' style='text-align: right;' placeholder='(962) 777 999 00' value='<?php echo htmlspecialchars($settings['contact_phone'] ?? '(962) 777 999 00'); ?>'>
                                </div>
                                <div class='col-md-6'>
                                    <label class='form-label fw-bold'>بريد التواصل والدعم (المعروض للعامة)</label>
                                    <input type='email' name='contact_email' class='form-control' dir='ltr' style='text-align: right;' placeholder='athara@gmail.com' value='<?php echo htmlspecialchars($settings['contact_email'] ?? 'athara@gmail.com'); ?>'>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- البطاقة 3: إعدادات بريد الإرسال وخادم SMTP -->
                    <div class='card shadow-sm border-0 mb-4' style='border-radius: 15px;'>
                        <div class='card-body p-4'>
                            <h4 class='mb-3 text-primary fw-bold'>إعدادات خادم البريد (SMTP) لإرسال الإيميلات والإشعارات</h4>
                            <p class='text-muted small'>يستخدم هذا الحساب في إرسال رسائل التحقق، وتأكيد الحساب، وروابط استعادة كلمة المرور، وتنبيهات الحجوزات للمستخدمين.</p>
                            <div class='row g-3'>
                                <div class='col-md-6'>
                                    <label class='form-label fw-bold'>البريد الإلكتروني المرسل (SMTP Email)</label>
                                    <input type='email' name='smtp_user' class='form-control' dir='ltr' style='text-align: right;' placeholder='user@gmail.com' value='<?php echo htmlspecialchars($settings['smtp_user'] ?? 'hussen16337@gmail.com'); ?>' required>
                                </div>
                                <div class='col-md-6'>
                                    <label class='form-label fw-bold'>كلمة مرور التطبيق (App Password)</label>
                                    <input type='password' name='smtp_pass' class='form-control' dir='ltr' style='text-align: right;' placeholder='كلمة مرور التطبيقات (App Password)' value='<?php echo htmlspecialchars($settings['smtp_pass'] ?? 'vked yfpi insz xtla'); ?>' required>
                                    <div class='form-text small text-muted'>في حسابات Gmail، يرجى إنشاء واستخدام "كلمة مرور التطبيقات" المكونة من 16 حرفاً.</div>
                                </div>
                                <div class='col-md-4'>
                                    <label class='form-label fw-bold'>خادم البريد (SMTP Host)</label>
                                    <input type='text' name='smtp_host' class='form-control' dir='ltr' value='<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>' required>
                                </div>
                                <div class='col-md-4'>
                                    <label class='form-label fw-bold'>منفذ البريد (SMTP Port)</label>
                                    <input type='number' name='smtp_port' class='form-control' dir='ltr' value='<?php echo htmlspecialchars($settings['smtp_port'] ?? '465'); ?>' required>
                                </div>
                                <div class='col-md-4'>
                                    <label class='form-label fw-bold'>نوع التشفير (Encryption)</label>
                                    <select name='smtp_secure' class='form-select'>
                                        <option value='ssl' <?php echo (($settings['smtp_secure'] ?? 'ssl') === 'ssl') ? 'selected' : ''; ?>>SSL (Port 465)</option>
                                        <option value='tls' <?php echo (($settings['smtp_secure'] ?? '') === 'tls') ? 'selected' : ''; ?>>TLS (Port 587)</option>
                                    </select>
                                </div>
                                <div class='col-md-12'>
                                    <label class='form-label fw-bold'>اسم وعنوان المرسل الظاهر في الرسائل (From Email)</label>
                                    <input type='email' name='from_email' class='form-control' dir='ltr' style='text-align: right;' placeholder='منصة إثراء <noreply@ethraa.com>' value='<?php echo htmlspecialchars($settings['from_email'] ?? $settings['smtp_user'] ?? 'hussen16337@gmail.com'); ?>'>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- البطاقة 4: روابط التواصل الاجتماعي -->
                    <div class='card shadow-sm border-0 mb-4' style='border-radius: 15px;'>
                        <div class='card-body p-4'>
                            <h4 class='mb-3 text-primary fw-bold'>روابط وسائل التواصل الاجتماعي</h4>
                            <div class='row g-3'>
                                <div class='col-md-3'>
                                    <label class='form-label fw-bold'>فيسبوك</label>
                                    <input type='text' name='facebook_link' class='form-control' dir='ltr' value='<?php echo htmlspecialchars($settings['facebook_link'] ?? '#'); ?>'>
                                </div>
                                <div class='col-md-3'>
                                    <label class='form-label fw-bold'>تويتر / X</label>
                                    <input type='text' name='twitter_link' class='form-control' dir='ltr' value='<?php echo htmlspecialchars($settings['twitter_link'] ?? '#'); ?>'>
                                </div>
                                <div class='col-md-3'>
                                    <label class='form-label fw-bold'>إنستغرام</label>
                                    <input type='text' name='instagram_link' class='form-control' dir='ltr' value='<?php echo htmlspecialchars($settings['instagram_link'] ?? '#'); ?>'>
                                </div>
                                <div class='col-md-3'>
                                    <label class='form-label fw-bold'>لينكد إن</label>
                                    <input type='text' name='linkedin_link' class='form-control' dir='ltr' value='<?php echo htmlspecialchars($settings['linkedin_link'] ?? '#'); ?>'>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class='d-flex justify-content-start mb-4'>
                        <button type='submit' class='btn btn-admin-primary px-4 py-2 fw-bold shadow-sm' style='border-radius: 8px; font-size: 0.95rem;'>
                            <i class='fa-solid fa-save me-2'></i> حفظ التعديلات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
    <script>
        var el = document.getElementById('wrapper');
        var toggleButton = document.getElementById('menu-toggle');
        if (toggleButton && el) {
            toggleButton.onclick = function () { el.classList.toggle('toggled'); };
        }
    </script>
</body>
</html>
