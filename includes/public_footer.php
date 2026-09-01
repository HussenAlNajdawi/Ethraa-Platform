<?php
// تضمين اتصال قاعدة البيانات إن لم يكن مضمناً
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../config/db_connect.php';
}

// جلب إعدادات التواصل وروابط المنصة
$footer_settings = [];
if (isset($conn) && $conn instanceof mysqli) {
    $res_f = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('contact_phone', 'contact_email', 'facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link')");
    if ($res_f) {
        while ($rf = $res_f->fetch_assoc()) {
            $footer_settings[$rf['setting_key']] = $rf['setting_value'];
        }
    }
}
$formatLink = function($link) {
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

$footer_phone = !empty($footer_settings['contact_phone']) ? $footer_settings['contact_phone'] : '(962) 777 999 00';
$footer_email = !empty($footer_settings['contact_email']) ? $footer_settings['contact_email'] : 'athara@gmail.com';
$fb_link = $formatLink($footer_settings['facebook_link'] ?? '#');
$tw_link = $formatLink($footer_settings['twitter_link'] ?? '#');
$ig_link = $formatLink($footer_settings['instagram_link'] ?? '#');
$in_link = $formatLink($footer_settings['linkedin_link'] ?? '#');
?>
<footer class="footer">
        <div class="container">
            <div class="row text-center text-md-end">
                
                <div class="col-md-3 mb-4 text-center">
                    <img src="assets/images/logo.png" alt="إثراء" height="60" class="d-block mx-auto footer-logo-mobile">
                    <div class="social-icons">
                        <a href="<?php echo htmlspecialchars($tw_link); ?>" <?php echo ($tw_link !== '#') ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><img src="assets/images/x.svg" width="20" alt="X"></a>
                        <a href="<?php echo htmlspecialchars($in_link); ?>" <?php echo ($in_link !== '#') ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><img src="assets/images/linkedin.svg" width="20" alt="in"></a>
                        <a href="<?php echo htmlspecialchars($fb_link); ?>" <?php echo ($fb_link !== '#') ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><img src="assets/images/facebook.svg" width="20" alt="fb"></a>
                        <a href="<?php echo htmlspecialchars($ig_link); ?>" <?php echo ($ig_link !== '#') ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><img src="assets/images/instagram.svg" width="20" alt="ig"></a>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <h5>اتصل بنا</h5>
                    <ul>
                        <li dir="ltr" class="text-md-end text-center"><?php echo htmlspecialchars($footer_phone); ?></li>
                        <li class="text-md-end text-center"><?php echo htmlspecialchars($footer_email); ?></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4">
                    <h5>روابط</h5>
                    <ul>
                        <li><a href="user/login.php">تسجيل الدخول</a></li>
                        <li><a href="user/register.php">إنشاء حساب</a></li>
                        <li><a href="index.php">الرئيسية</a></li>
                        <li><a href="guide.php">دليل الاستخدام</a></li>
                    </ul>
                </div>

                <div class="col-md-3 mb-4">
                    <h5>الشركة</h5>
                    <ul>
                        <li><a href="about.php">من نحن</a></li>
                        <li><a href="terms.php">شروط الاستخدام</a></li>
                        <li><a href="about.php">رسالتنا</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            جميع الحقوق محفوظة لمنصة إثراء &copy; <?php echo date('Y'); ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- سكربت تفعيل الوضع الليلي (Dark Mode) -->
    <?php 
        // تحديد المسار الصحيح للملف حسب عمق الصفحة
        $base_url = (strpos($_SERVER['SCRIPT_NAME'], '/user/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../' : '';
    ?>
    <script src="<?php echo $base_url; ?>assets/js/dark_mode.js"></script>
</body>
</html>