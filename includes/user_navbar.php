<?php
// includes/user_navbar.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF']);

// جلب النقاط
$nav_points = 0;
$unread_notifs = 0; // متغير لعدد الإشعارات غير المقروءة

if ($is_logged_in && isset($conn)) {
    $nav_user_id = $_SESSION['user_id'];
    
    // 🔥 تنظيف الاتصال من أي نتائج سابقة عالقة (Commands out of sync)
    // هذا يضمن تحديث النقاط حتى لو نسيت إغلاق stmt في الصفحات الأخرى
    while ($conn->more_results()) {
        $conn->next_result();
        if ($res = $conn->store_result()) {
            $res->free();
        }
    }

    // 1. النقاط (باستخدام prepare للأمان)
    $stmt_pt = $conn->prepare("SELECT points FROM users WHERE user_id = ?");
    if ($stmt_pt) {
        $stmt_pt->bind_param("i", $nav_user_id);
        $stmt_pt->execute();
        $res_pt = $stmt_pt->get_result();
        if ($row_pt = $res_pt->fetch_assoc()) {
            $nav_points = $row_pt['points'];
        }
        $stmt_pt->close();
    }

    // 2. الإشعارات غير المقروءة
    $stmt_un = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($stmt_un) {
        $stmt_un->bind_param("i", $nav_user_id);
        $stmt_un->execute();
        $res_un = $stmt_un->get_result();
        if ($row_un = $res_un->fetch_assoc()) {
            $unread_notifs = $row_un['cnt'];
        }
        $stmt_un->close();
    }
}
?>

<nav class="navbar navbar-expand-lg user-navbar">
    <div class="container-fluid p-0">
        
        <div class="nav-side-area justify-content-start">
            <a class="navbar-brand me-0" href="user_home.php">
                <img src="../assets/images/logo.png" alt="إثراء" height="70"> 
            </a>
        </div>
        
        <!-- مجموعة أيقونات اليسار في الهاتف (الإشعارات + الدارك مود + زر البرغر) -->
        <div class="d-flex d-lg-none align-items-center me-auto ms-2 h-100" style="gap: 5px;">
            <!-- زر الإشعارات من الخارج (للهاتف) -->
            <a href="notifications.php" class="text-dark border-0 bg-transparent d-flex align-items-center justify-content-center m-0 p-1 position-relative" style="height: 40px; width: 40px; text-decoration: none;">
                <i class="fa-regular fa-bell fs-5"></i>
                <?php if ($unread_notifs > 0): ?>
                    <span class="position-absolute bg-danger border border-light rounded-circle pulse-badge" style="width: 10px; height: 10px; top: 8px; right: 8px;"></span>
                <?php endif; ?>
            </a>
            
            <!-- زر الوضع الليلي كأيقونة فقط -->
            <button type="button" id="headerDarkModeToggle" class="text-dark border-0 bg-transparent d-flex align-items-center justify-content-center m-0 p-1" onclick="if(window.toggleDarkMode) toggleDarkMode();" style="height: 40px; width: 40px;">
                <i class="fa-solid fa-moon fs-5"></i>
            </button>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNav" aria-controls="userNav" aria-expanded="false" aria-label="Toggle navigation" style="position: relative;">
                <span class="navbar-toggler-icon"></span>
                <?php if ($unread_notifs > 0): ?>
                    <span class="position-absolute bg-danger border border-light rounded-circle pulse-badge" style="width: 10px; height: 10px; top: 5px; right: 5px;"></span>
                <?php endif; ?>
            </button>
        </div>

        <div class="collapse navbar-collapse justify-content-center" id="userNav">
            <ul class="navbar-nav mb-2 mb-lg-0 align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'user_home.php') ? 'active' : ''; ?>" href="user_home.php">الصفحة الرئيسية</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'my_services.php' || $current_page == 'services_list.php') ? 'active' : ''; ?>" href="my_services.php">الخدمات</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'requests.php') ? 'active' : ''; ?>" href="requests.php">الطلبات</a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'my_account.php') ? 'active' : ''; ?>" href="my_account.php">حسابي</a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>" href="notifications.php">
                        الاشعارات
                        <?php if ($unread_notifs > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1 pulse-badge" style="font-size: 0.7rem; position: relative; top: -2px;"><?php echo $unread_notifs; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            
            <!-- قسم النقاط في الموبايل -->
            <div class="nav-side-area w-100 justify-content-center align-items-center mt-3 mb-2 d-flex d-lg-none text-center">
                <?php if ($is_logged_in): ?>
                    <a href="wallet_history.php" class="text-decoration-none" title="سجل النقاط" data-bs-placement="bottom">
                        <div class="points-text-box d-flex justify-content-center align-items-center mx-auto" style="cursor: pointer;">
                            <span class="fw-bold ms-1" style="color: inherit;"><?php echo $nav_points; ?></span>
                            <span style="font-size: 0.9rem; color: inherit;">نقاط</span>
                            <img src="../assets/images/coins.svg" width="24" height="24" alt="pts" class="me-2">
                        </div>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-primary rounded-pill px-4 mx-auto">دخول</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="nav-side-area justify-content-end d-none d-lg-flex">
            <?php if ($is_logged_in): ?>
               <a href="wallet_history.php" class="text-decoration-none" title="سجل النقاط" data-bs-placement="bottom">
                   <div class="points-text-box" style="cursor: pointer;">
                        <span class="fw-bold ms-1" style="color: inherit;"><?php echo $nav_points; ?></span>
                        <span style="font-size: 0.9rem; color: inherit;">نقاط</span>
                        <img src="../assets/images/coins.svg" width="24" height="24" alt="pts" class="me-2">
                    </div>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-sm btn-primary rounded-pill px-4">دخول</a>
            <?php endif; ?>
        </div>

    </div>
</nav>

<script>
// 1. تفعيل التلميحات الأنيقة (Bootstrap Tooltips) لجميع العناصر
document.addEventListener("DOMContentLoaded", function() {
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, { container: 'body' });
        });
    }
});

// 2. وميض تبويب المتصفح في حال وجود إشعارات غير مقروءة
<?php if ($unread_notifs > 0): ?>
    let originalTitle = document.title;
    let isBlinking = false;
    setInterval(() => {
        document.title = isBlinking ? "(<?php echo $unread_notifs; ?>) إشعار جديد - إثراء" : originalTitle;
        isBlinking = !isBlinking;
    }, 1500);
<?php endif; ?>

// 3. إغلاق القائمة المنسدلة عند النقر خارجها في الهاتف
document.addEventListener("click", function (event) {
    var navbarCollapse = document.getElementById("userNav");
    var navbarToggler = document.querySelector(".navbar-toggler");

    if (navbarCollapse && navbarCollapse.classList.contains("show")) {
        // التحقق من أن النقر حدث خارج القائمة وزر البرغر
        if (!navbarCollapse.contains(event.target) && !navbarToggler.contains(event.target)) {
            if (typeof bootstrap !== 'undefined') {
                var bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                if (bsCollapse) {
                    bsCollapse.hide();
                }
            }
        }
    }
});
</script>