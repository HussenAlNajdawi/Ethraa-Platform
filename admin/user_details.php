<?php
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}
requireAdminPermission('manage_users');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET['id']);

// جلب بيانات المستخدم
$sql = "SELECT u.*, s.name as sub_service_name, p.name as main_service_name 
        FROM users u 
        LEFT JOIN services s ON u.service_id = s.service_id 
        LEFT JOIN services p ON s.parent_id = p.service_id 
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: users.php?error=user_not_found");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// جلب إحصائيات المستخدم
// 1. التقييم
$rating_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count FROM reviews WHERE provider_id = ?";
$stmt = $conn->prepare($rating_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rating_res = $stmt->get_result()->fetch_assoc();
$avg_rating = $rating_res['avg_rating'] ? round($rating_res['avg_rating'], 1) : 0;
$rating_count = $rating_res['rating_count'];
$stmt->close();

// 2. عدد الطلبات المقدمة (كمقدم خدمة)
$prov_sql = "SELECT COUNT(*) as c FROM requests WHERE provider_id = ? AND status = 'completed'";
$stmt = $conn->prepare($prov_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$prov_count = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// 3. عدد الطلبات المطلوبة (كطالب خدمة)
$req_sql = "SELECT COUNT(*) as c FROM requests WHERE requester_id = ? AND status = 'completed'";
$stmt = $conn->prepare($req_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$req_count = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// 4. عدد الإنذارات
$warn_sql = "SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND type = 'warning'";
$stmt = $conn->prepare($warn_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$warn_count = $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// تحديد مسار زر الرجوع
$back_url = "users.php";
$back_title = "عودة لقائمة المستخدمين";
if (isset($_GET['source']) && $_GET['source'] == 'reports') {
    $back_url = "reports.php";
    $back_title = "عودة لقائمة البلاغات";
} elseif (isset($_GET['source']) && $_GET['source'] == 'appeals') {
    $back_url = "appeals.php";
    $back_title = "عودة لقائمة الاعتراضات";
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفاصيل المستخدم - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <style>
        .user-details-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 30px;
            margin-bottom: 20px;
        }
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(45deg, #104496, #3498db);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        .detail-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            width: 160px;
            font-weight: bold;
            color: #555;
            display: flex;
            align-items: center;
        }
        .detail-label i {
            width: 25px;
            color: #104496;
        }
        .detail-value {
            color: #333;
            flex-grow: 1;
            font-weight: 600;
        }
        .stat-box {
            background-color: #fff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            height: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #104496;
        }
        .dark-mode .user-details-card { background-color: #2c2c2c; }
        .dark-mode .detail-row { border-bottom-color: #444; }
        .dark-mode .detail-label { color: #ccc; }
        .dark-mode .detail-value { color: #eee; }        
        .dark-mode .stat-box { background-color: #2c2c2c; }
        .badge-status {
            padding: 6px 20px;
            border-radius: 20px;
            font-size: 0.95rem;
            font-weight: bold;
            display: inline-block;
        }
        .back-btn-custom {
            display: inline-block;
            transition: transform 0.2s ease;
        }
        .back-btn-custom:hover {
            transform: translateX(-5px);
        }
        html.dark-mode .back-btn-custom img {
            filter: brightness(0) invert(0.8);
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>
        
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">تفاصيل المستخدم</h2>
                </div>
            </nav>

            <div class="container-fluid px-4">
                <div class="d-flex justify-content-end mb-4">
                    <a href="<?php echo $back_url; ?>" class="back-btn-custom" title="<?php echo $back_title; ?>">
                        <img src="../assets/images/arrow-back.svg" width="45" alt="رجوع">
                    </a>
                </div>

                <div class="row">
                    <!-- المعلومات الأساسية -->
                    <div class="col-lg-4 mb-4">
                        <div class="user-details-card text-center">
                            <?php $char = mb_substr($user['first_name'], 0, 1, "UTF-8"); ?>
                            <div class="user-avatar shadow-sm">
                                <?php echo htmlspecialchars($char); ?>
                            </div>
                            <h4 class="fw-bold mb-1" style="color: var(--dark-blue);"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                            <p class="text-muted mb-3" style="font-family: monospace;">ID: #<?php echo $user['user_id']; ?></p>
                            
                            <?php if($user['status'] == 'active'): ?>
                                <span class="status-pill active"><i class="fas fa-check-circle me-1"></i> حساب نشط</span>
                            <?php else: ?>
                                <span class="status-pill blocked"><i class="fas fa-ban me-1"></i> حساب محظور</span>
                            <?php endif; ?>

                            <div class="mt-4 pt-3 border-top">
                                <h2 class="fw-bold mb-0" style="color: var(--dark-blue); font-size: 2.5rem;"><?php echo $user['points']; ?></h2>
                                <span class="text-muted fw-bold">النقاط الحالية</span>
                            </div>
                        </div>

                        <!-- إحصائيات سريعة -->
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="stat-box shadow-sm">
                                    <i class="fas fa-star text-warning stat-icon"></i>
                                    <div class="stat-value"><?php echo $avg_rating; ?></div>
                                    <div class="text-muted small fw-bold">التقييم (<?php echo $rating_count; ?>)</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box shadow-sm">
                                    <i class="fas fa-triangle-exclamation text-danger stat-icon"></i>
                                    <div class="stat-value text-danger"><?php echo $warn_count; ?></div>
                                    <div class="text-muted small fw-bold">الإنذارات</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box shadow-sm">
                                    <i class="fas fa-hand-holding-heart text-success stat-icon"></i>
                                    <div class="stat-value text-success"><?php echo $prov_count; ?></div>
                                    <div class="text-muted small fw-bold">خدمات قدمها</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box shadow-sm">
                                    <i class="fas fa-hands-helping text-info stat-icon"></i>
                                    <div class="stat-value text-info"><?php echo $req_count; ?></div>
                                    <div class="text-muted small fw-bold">خدمات طلبها</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- التفاصيل الكاملة -->
                    <div class="col-lg-8 mb-4">
                        <div class="user-details-card h-100">
                            <h5 class="fw-bold mb-4 pb-2" style="color: #021C7B; border-bottom: 2px solid #eee;">المعلومات الشخصية والتواصل</h5>
                            
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-phone"></i> رقم الهاتف:</div>
                                <div class="detail-value" dir="ltr" style="text-align: right;">+962 <?php echo htmlspecialchars($user['phone']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-envelope"></i> البريد الإلكتروني:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['email'] ?: 'غير متوفر'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-map-marker-alt"></i> المحافظة:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['governorate'] ?: 'غير محدد'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-calendar-alt"></i> تاريخ الميلاد:</div>
                                <div class="detail-value"><?php echo htmlspecialchars($user['birth_date'] ?: 'غير محدد'); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-venus-mars"></i> الجنس:</div>
                                <div class="detail-value">
                                    <?php 
                                    if($user['gender'] == 'male') echo 'ذكر'; 
                                    elseif($user['gender'] == 'female') echo 'أنثى'; 
                                    else echo 'غير محدد'; 
                                    ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-clock"></i> تاريخ التسجيل:</div>
                                <div class="detail-value"><?php echo isset($user['created_at']) ? date('Y/m/d h:i A', strtotime($user['created_at'])) : 'غير محدد'; ?></div>
                            </div>

                            <h5 class="fw-bold mt-5 mb-4 pb-2" style="color: #021C7B; border-bottom: 2px solid #eee;">معلومات الخدمة</h5>
                            
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-briefcase"></i> الخدمة المُقدمة:</div>
                                <div class="detail-value">
                                    <?php 
                                    if(!empty($user['sub_service_name'])) {
                                        echo htmlspecialchars($user['main_service_name'] . ' - ' . $user['sub_service_name']);
                                    } else {
                                        echo '<span class="text-muted" style="font-weight: normal;">لم يحدد خدمة بعد</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label"><i class="fas fa-user-clock"></i> أوقات التوفر:</div>
                                <div class="detail-value">
                                    <?php 
                                    if($user['availability_type'] == 'always') echo '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> متاح دائماً</span>';
                                    elseif($user['availability_type'] == 'unavailable') echo '<span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i> غير متاح حالياً</span>';
                                    elseif($user['availability_type'] == 'specific') {
                                        echo 'من <span dir="ltr">' . date('h:i A', strtotime($user['free_time_start'])) . '</span> إلى <span dir="ltr">' . date('h:i A', strtotime($user['free_time_end'])) . '</span>';
                                    } else {
                                        echo '<span class="text-muted" style="font-weight: normal;">غير محدد</span>';
                                    }
                                    ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- سكربت الوضع الليلي -->
    <script src="../assets/js/dark_mode.js" defer></script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");
        toggleButton.onclick = function () { el.classList.toggle("toggled"); };
    </script>
</body>
</html>