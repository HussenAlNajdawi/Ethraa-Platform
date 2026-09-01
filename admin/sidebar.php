<?php
require_once 'admin_functions.php';

// جلب عدد البلاغات المعلقة
$pending_reports = 0;
if (hasAdminPermission('manage_reports')) {
    try {
        $res = $conn->query("SELECT COUNT(*) as c FROM reports WHERE status = 'pending'");
        if($res) $pending_reports = $res->fetch_assoc()['c'];
    } catch(Exception $e) {}
}

// جلب عدد الاعتراضات الحالية
$pending_appeals = 0;
if (hasAdminPermission('manage_appeals')) {
    try {
        $res = $conn->query("SELECT COUNT(*) as c FROM appeals");
        if($res) $pending_appeals = $res->fetch_assoc()['c'];
    } catch(Exception $e) {}
}
?>
<!-- سكربت الوضع الليلي ليعمل في جميع صفحات الإدارة -->
<script src="../assets/js/dark_mode.js" defer></script>
<!-- تضمين مكتبة SweetAlert2 لرسائل التأكيد في كل صفحات الإدارة -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
<script>
function safeSwal(options) {
    if (typeof Swal !== 'undefined') {
        Swal.fire(options);
    } else {
        var checkSwal = setInterval(function() {
            if (typeof Swal !== 'undefined') {
                clearInterval(checkSwal);
                Swal.fire(options);
            }
        }, 50);
        setTimeout(function() { clearInterval(checkSwal); }, 5000);
    }
}
</script>
<div id="sidebar-wrapper">
    <div class="sidebar-logo">
        <img src="../assets/images/logo.png" alt="Logo">
    </div>
    <div class="sidebar-content">
        <div class="list-group">
        <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <img src="../assets/images/dashbord.svg" width="22" alt="icon"> لوحة التحكم
        </a>

        <?php if(hasAdminPermission('manage_users')): ?>
        <a href="users.php" class="list-group-item list-group-item-action <?php echo in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'user_details.php']) ? 'active' : ''; ?>">
            <img src="../assets/images/user.svg" width="22" alt="icon"> المستخدمين
        </a>
        <?php endif; ?>

        <?php if(hasAdminPermission('manage_services')): ?>
        <a href="services.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
            <img src="../assets/images/services.svg" width="22" alt="icon"> الخدمات
        </a>
        <?php endif; ?>

        <?php if(hasAdminPermission('manage_notifications')): ?>
        <a href="notifications.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
            <img src="../assets/images/notification.svg" width="22" alt="icon"> الإشعارات
        </a>
        <?php endif; ?>

        <?php if(hasAdminPermission('manage_reports')): ?>
        <a href="reports.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?> d-flex justify-content-between align-items-center">
            <div>
                <img src="../assets/images/white-report.svg" width="22" alt="icon"> البلاغات
            </div>
            <?php if($pending_reports > 0 && basename($_SERVER['PHP_SELF']) != 'reports.php'): ?>
                <span class="badge bg-danger rounded-pill shadow-sm"><?php echo $pending_reports; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if(hasAdminPermission('manage_appeals')): ?>
        <a href="appeals.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'appeals.php' ? 'active' : ''; ?> d-flex justify-content-between align-items-center">
            <div>
                <i class="fa-solid fa-gavel" style="font-size: 18px; width: 22px; text-align: center;"></i> الاعتراضات
            </div>
            <?php if($pending_appeals > 0 && basename($_SERVER['PHP_SELF']) != 'appeals.php'): ?>
                <span class="badge bg-danger rounded-pill shadow-sm"><?php echo $pending_appeals; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if(hasAdminPermission('view_logs')): ?>
        <a href="audit_logs.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-list-check" style="font-size: 18px; width: 22px; text-align: center;"></i> سجل النشاطات
        </a>
        <?php endif; ?>

        <?php if(hasAdminPermission('manage_settings')): ?>
        <a href="settings.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-gear" style="font-size: 18px; width: 22px; text-align: center;"></i> الإعدادات
        </a>
        <?php endif; ?>

        <?php if(isSuperAdmin() || hasAdminPermission('manage_admins')): ?>
        <a href="manage_admins.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'manage_admins.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-shield" style="font-size: 18px; width: 22px; text-align: center;"></i> المشرفين والصلاحيات
        </a>
        <?php endif; ?>

        <a href="logout.php" class="list-group-item list-group-item-action text-danger" onclick="confirmAdminLogout(event, this.href)">
            <img src="../assets/images/logout.svg" width="22" alt="icon"> تسجيل خروج
        </a>
        </div>
    </div>
</div>

<script>
function confirmAdminLogout(e, url) {
    e.preventDefault();
    Swal.fire({
        title: 'تسجيل الخروج',
        text: "هل أنت متأكد من رغبتك في تسجيل الخروج من لوحة التحكم؟",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#f1f3f5',
        confirmButtonText: 'نعم، خروج',
        cancelButtonText: 'تراجع',
        customClass: { cancelButton: 'text-dark fw-bold' }
    }).then((result) => {
        if (result.isConfirmed) window.location.href = url;
    });
}
</script>