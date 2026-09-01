<?php
require_once '../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// إحصائيات سريعة
$users_count = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$requests_count = $conn->query("SELECT COUNT(*) as c FROM requests")->fetch_assoc()['c'];

// إحصائيات الخدمات (رئيسية وفرعية)
$main_services_count = $conn->query("SELECT COUNT(*) as c FROM services WHERE parent_id IS NULL")->fetch_assoc()['c'];
$sub_services_count = $conn->query("SELECT COUNT(*) as c FROM services WHERE parent_id IS NOT NULL")->fetch_assoc()['c'];

// إحصائيات إضافية
$banned_count = $conn->query("SELECT COUNT(*) as c FROM users WHERE status = 'banned'")->fetch_assoc()['c'];
$total_points = $conn->query("SELECT SUM(points_cost) as s FROM requests WHERE status = 'completed'")->fetch_assoc()['s'] ?? 0;

// عدد الاعتراضات الحالية
$appeals_count = $conn->query("SELECT COUNT(*) as c FROM appeals")->fetch_assoc()['c'] ?? 0;

// التنظيف التلقائي للمحادثات: حذف الرسائل للطلبات المكتملة التي مر عليها 30 يوم
$conn->query("DELETE m FROM messages m JOIN requests r ON m.request_id = r.request_id WHERE r.status = 'completed' AND m.created_at < (NOW() - INTERVAL 30 DAY)");



// عدد رسائل المحادثات
$messages_count = 0;
$res_msg = $conn->query("SELECT COUNT(*) as c FROM messages");
if($res_msg) { $messages_count = $res_msg->fetch_assoc()['c']; }

// إحصائيات الزوار للرسم البياني
$period = $_GET['period'] ?? 'week';
$visitors_labels = [];
$visitors_values = [];
$visitors_data_map = [];

if ($period == 'year') {
    // آخر 12 شهر
    for ($i = 11; $i >= 0; $i--) {
        $m = date('Y-m', strtotime("-$i months"));
        $visitors_labels[] = date('Y/m', strtotime("$m-01"));
        $visitors_data_map[$m] = 0;
    }
    $sql_visit = "SELECT DATE_FORMAT(login_time, '%Y-%m') as d, COUNT(DISTINCT user_id) as c FROM loginlogs WHERE login_time >= DATE_FORMAT(NOW() - INTERVAL 11 MONTH, '%Y-%m-01') GROUP BY d";
} elseif ($period == 'month') {
    // آخر 30 يوم
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $visitors_labels[] = date('m/d', strtotime($d));
        $visitors_data_map[$d] = 0;
    }
    $sql_visit = "SELECT DATE(login_time) as d, COUNT(DISTINCT user_id) as c FROM loginlogs WHERE login_time >= DATE(NOW()) - INTERVAL 29 DAY GROUP BY d";
} else {
    // الافتراضي: آخر 7 أيام
    $period = 'week';
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $visitors_labels[] = date('m/d', strtotime($d));
        $visitors_data_map[$d] = 0;
    }
    $sql_visit = "SELECT DATE(login_time) as d, COUNT(DISTINCT user_id) as c FROM loginlogs WHERE login_time >= DATE(NOW()) - INTERVAL 6 DAY GROUP BY d";
}

$res_visit = $conn->query($sql_visit);
if ($res_visit) {
    while ($row = $res_visit->fetch_assoc()) $visitors_data_map[$row['d']] = $row['c'];
}
$visitors_values = array_values($visitors_data_map);

// تعديل: تم تغيير الاستعلام ليعمل مع هيكل الجدول الحالي (بدون عمود status)
$reports_count = 0;
try {
    $res_rep = $conn->query("SELECT COUNT(*) as c FROM reports");
    if ($res_rep) $reports_count = $res_rep->fetch_assoc()['c'];
} catch (Exception $e) {
    // تجاهل الخطأ في حال عدم وجود الجدول
}

// توزيع المحافظات
$all_governorates = ['عمان', 'الزرقاء', 'إربد', 'البلقاء', 'مادبا', 'المفرق', 'جرش', 'عجلون', 'الكرك', 'الطفيلة', 'معان', 'العقبة'];
$gov_counts = array_fill_keys($all_governorates, 0);

$res_gov = $conn->query("SELECT governorate, COUNT(*) as c FROM users WHERE governorate IS NOT NULL AND governorate != '' GROUP BY governorate");
if ($res_gov) {
    while ($row = $res_gov->fetch_assoc()) {
        if (array_key_exists($row['governorate'], $gov_counts)) {
            $gov_counts[$row['governorate']] = $row['c'];
        }
    }
}

// ترتيب المحافظات تنازلياً حسب العدد (الأكثر أولاً)
arsort($gov_counts);

// تحضير البيانات للرسم البياني
$gov_labels = array_keys($gov_counts);
$gov_data = array_values($gov_counts);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لوحة التحكم - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <style>
        /* تأثير التحويم للكروت التفاعلية */
        .stat-card-link { transition: transform 0.2s ease, box-shadow 0.2s ease; display: block; cursor: pointer; }
        .stat-card-link:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>
        
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle" style="cursor: pointer;"></i>
                    <h2 class="fs-2 m-0">لوحة التحكم</h2>
                </div>
            </nav>

            <div class="container-fluid px-4">
                <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            safeSwal({
                                icon: 'error',
                                title: 'غير مصرح',
                                text: 'عذراً، ليس لديك الصلاحية الكافية للوصول إلى تلك الصفحة.',
                                confirmButtonText: 'حسناً',
                                confirmButtonColor: '#021C7B'
                            });
                        });
                    </script>
                <?php endif; ?>
                <div class="row g-3 my-2">
                    <!-- الصف الأول -->
                    <div class="col-md-3">
                        <a href="users.php" class="text-decoration-none stat-card-link p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                            <div>
                                <h3 class="fs-2 text-dark"><?php echo $users_count; ?></h3>
                                <p class="fs-5 text-muted mb-0">المستخدمين</p>
                            </div>
                            <i class="fas fa-users fs-1 primary-text border rounded-full secondary-bg p-3"></i>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="users.php" class="text-decoration-none stat-card-link p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                            <div>
                                <h3 class="fs-2 text-dark"><?php echo $banned_count; ?></h3>
                                <p class="fs-5 text-muted mb-0">المحظورين</p>
                            </div>
                            <i class="fas fa-ban fs-1 text-danger border rounded-full p-3" style="background-color: #ffebee;"></i>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="appeals.php" class="text-decoration-none stat-card-link p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                            <div>
                                <h3 class="fs-2 text-dark"><?php echo $appeals_count; ?></h3>
                                <p class="fs-5 text-muted mb-0">الاعتراضات</p>
                            </div>
                            <i class="fas fa-gavel fs-1 text-secondary border rounded-full p-3" style="background-color: #e2e3e5;"></i>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                            <div>
                                <h3 class="fs-2 text-dark"><?php echo $messages_count; ?></h3>
                                <p class="fs-5 text-muted mb-0">رسالة</p>
                            </div>
                            <i class="fas fa-comments fs-1 text-primary border rounded-full p-3" style="background-color: #e3f2fd;"></i>
                        </div>
                    </div>

                    <!-- الصف الثاني -->
                    <div class="col-md-3">
                        <div class="p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                            <div>
                                <h3 class="fs-2 text-dark"><?php echo $requests_count; ?></h3>
                                <p class="fs-5 text-muted mb-0">الطلبات</p>
                            </div>
                            <i class="fas fa-hand-holding-heart fs-1 primary-text border rounded-full secondary-bg p-3"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <a href="services.php" class="text-decoration-none stat-card-link p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                            <div>
                                <h3 class="fs-2 text-dark"><?php echo $main_services_count; ?></h3>
                                <p class="fs-5 text-muted mb-0">خدمات رئيسية</p>
                            </div>
                            <i class="fas fa-layer-group fs-1 primary-text border rounded-full secondary-bg p-3"></i>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="services.php" class="text-decoration-none stat-card-link p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                            <div>
                                <h3 class="fs-2 text-dark"><?php echo $sub_services_count; ?></h3>
                                <p class="fs-5 text-muted mb-0">خدمات فرعية</p>
                            </div>
                            <i class="fas fa-list-ul fs-1 primary-text border rounded-full secondary-bg p-3"></i>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="reports.php" class="text-decoration-none stat-card-link p-3 bg-white shadow-sm d-flex justify-content-around align-items-center rounded">
                            <div>
                                <h3 class="fs-2 text-dark"><?php echo $reports_count; ?></h3>
                                <p class="fs-5 text-muted mb-0">إجمالي البلاغات</p>
                            </div>
                            <i class="fa-solid fa-triangle-exclamation fs-1 text-danger border rounded-full p-3" style="background-color: #ffebee;"></i>
                        </a>
                    </div>
                </div>

                <!-- قسم الرسوم البيانية -->
                <div class="row my-4">
                    <div class="col-md-12 mb-4" id="visitors-stats">
                        <div class="p-4 bg-white shadow-sm rounded">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="fw-bold text-secondary m-0">إحصائيات الزوار</h4>
                                <div class="filter-toggle">
                                    <a href="?period=week#visitors-stats" class="filter-btn <?php echo $period == 'week' ? 'active' : ''; ?>">أسبوع</a>
                                    <a href="?period=month#visitors-stats" class="filter-btn <?php echo $period == 'month' ? 'active' : ''; ?>">شهر</a>
                                    <a href="?period=year#visitors-stats" class="filter-btn <?php echo $period == 'year' ? 'active' : ''; ?>">سنة</a>
                                </div>
                            </div>
                            <canvas id="visitorsChart" class="chart-animate" style="max-height: 400px; width: 100%;"></canvas>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="p-4 bg-white shadow-sm rounded">
                            <h4 class="mb-4 fw-bold text-secondary">توزيع المستخدمين حسب المحافظة</h4>
                            <?php if(!empty($gov_labels)): ?>
                                <canvas id="govChart" style="max-height: 400px; width: 100%;"></canvas>
                            <?php else: ?>
                                <p class="text-muted text-center">لا توجد بيانات كافية لعرض التوزيع.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");
        toggleButton.onclick = function () { el.classList.toggle("toggled"); };

        // رسم بياني للزوار
        const ctxVisitors = document.getElementById('visitorsChart').getContext('2d');
        new Chart(ctxVisitors, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($visitors_labels); ?>,
                datasets: [{
                    label: 'عدد الزوار',
                    data: <?php echo json_encode($visitors_values); ?>,
                    borderColor: '#7abd28',
                    backgroundColor: 'rgba(122, 189, 40, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        <?php if(!empty($gov_labels)): ?>
        const ctx = document.getElementById('govChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($gov_labels); ?>,
                datasets: [{
                    label: 'عدد المستخدمين',
                    data: <?php echo json_encode($gov_data); ?>,
                    backgroundColor: '#021C7B',
                    borderRadius: 5,
                    barThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: {
                        ticks: {
                            font: {
                                size: 14,
                                weight: 'bold',
                                family: 'Cairo'
                            }
                        }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
        <?php endif; ?>


    </script>
</body>
</html>