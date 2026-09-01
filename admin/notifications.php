<?php
session_start();
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
requireAdminPermission('manage_notifications');

// إرسال إشعار
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notif'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $raw_message = $_POST['message'];
    $target = $_POST['target']; // 'all' or specific user_id
    $type = $_POST['type'] ?? 'info'; // استقبال نوع الإشعار

    // إضافة بادئة للنص في حال كان إنذاراً لتمييزه بوضوح عند المستخدم
    if ($type == 'warning') {
        $raw_message = " إنذار إداري: " . $raw_message;
    }

    $message = $conn->real_escape_string($raw_message);

    if ($target == 'all') {
        // إرسال للكل (قد يكون ثقيلاً إذا كان العدد كبيراً، يفضل استخدام Loop أو Insert Select)
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) SELECT user_id, ?, ?, NOW() FROM users");
        $stmt->bind_param("ss", $message, $type);
        $stmt->execute();
        $stmt->close();
        
        // --- إرسال بريد إلكتروني للجميع ---
        $all_users = $conn->query("SELECT email, first_name FROM users");
        if ($all_users) {
            $subject = ($type == 'warning') ? "تنبيه إداري - إثراء" : "إشعار من إثراء";
            $headers = "From: no-reply@ithraa.com\r\nContent-Type: text/plain; charset=UTF-8";
            while ($u = $all_users->fetch_assoc()) {
                $email_body = "مرحباً {$u['first_name']}،\n\n$raw_message\n\nإدارة منصة إثراء.";
                @mail($u['email'], $subject, $email_body, $headers);
            }
        }
        
        $msg = "تم إرسال الإشعار لجميع المستخدمين";
    } else {
        $uid = intval($target);
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $uid, $message, $type);
        $stmt->execute();
        $stmt->close();
        
        // --- إرسال بريد إلكتروني ---
        $stmt2 = $conn->prepare("SELECT email, first_name FROM users WHERE user_id = ?");
        $stmt2->bind_param("i", $uid);
        $stmt2->execute();
        $u_info = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        if ($u_info) {
            $subject = ($type == 'warning') ? "تنبيه إداري - إثراء" : "إشعار من إثراء";
            $email_body = "مرحباً {$u_info['first_name']}،\n\n$raw_message\n\nإدارة منصة إثراء.";
            $headers = "From: no-reply@ithraa.com\r\nContent-Type: text/plain; charset=UTF-8";
            @mail($u_info['email'], $subject, $email_body, $headers);
        }
        
        $msg = "تم إرسال الإشعار للمستخدم المحدد";
    }
}

// جلب آخر الإشعارات المرسلة
$filter_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$user_filter_name = "";

if ($filter_user > 0) {
    // عرض إشعارات مستخدم محدد
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $filter_user);
    $stmt->execute();
    $u_res = $stmt->get_result();
    if ($u_res && $u_res->num_rows > 0) {
        $u_row = $u_res->fetch_assoc();
        $user_filter_name = $u_row['first_name'] . ' ' . $u_row['last_name'];
    }
    $stmt->close();
    
    $sql_history = "SELECT n.message, n.type, n.created_at, 1 as recipient_count, 
                           u.first_name, u.last_name, n.user_id 
                    FROM notifications n 
                    LEFT JOIN users u ON n.user_id = u.user_id 
                    WHERE n.user_id = $filter_user
                    ORDER BY n.created_at DESC LIMIT 50";
} else {
    // عرض الإشعارات العامة
    $sql_history = "SELECT n.message, n.type, n.created_at, COUNT(*) as recipient_count, 
                           MIN(u.first_name) as first_name, MIN(u.last_name) as last_name, MIN(n.user_id) as user_id 
                    FROM notifications n 
                    LEFT JOIN users u ON n.user_id = u.user_id 
                    GROUP BY n.message, n.type, n.created_at 
                    ORDER BY n.created_at DESC LIMIT 20";
}
$recent_notifs = $conn->query($sql_history);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة الإشعارات - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <!-- مكتبة Select2 للبحث -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">إرسال الإشعارات</h2>
                </div>
            </nav>
            <div class="container-fluid px-4">
                
                <?php if(isset($msg)): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            safeSwal({
                                icon: 'success',
                                title: 'تم الإرسال',
                                text: <?php echo json_encode($msg); ?>,
                                confirmButtonText: 'حسناً',
                                confirmButtonColor: '#021C7B'
                            });
                        });
                    </script>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST" onsubmit="return showLoadingBtn()">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <label class="form-label">نص الإشعار</label>
                                <textarea name="message" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">نوع الإشعار</label>
                                <select name="type" class="form-select">
                                    <option value="info">إشعار عام (معلومات)</option>
                                    <option value="warning">إنذار (تحذير)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">المستلم</label>
                                <select name="target" id="userSelect" class="form-select">
                                    <option value="all">جميع المستخدمين</option>
                                    <?php 
                                    $users = $conn->query("SELECT user_id, first_name, last_name FROM users");
                                    while($u = $users->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (ID: ' . $u['user_id'] . ')', ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" name="send_notif" id="sendNotifBtn" class="btn btn-primary px-4 fw-bold" style="background-color: var(--main-bg-color); border-color: var(--main-bg-color); border-radius: 12px; transition: all 0.3s ease;">
                                <i class="fas fa-paper-plane me-2"></i> إرسال
                            </button>
                        </form>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3" id="history">
                    <h4 class="m-0">آخر الإشعارات المرسلة <?php echo $user_filter_name ? "<span class='fs-5 text-muted'>(للمستخدم: <span class='text-primary'>" . htmlspecialchars($user_filter_name, ENT_QUOTES, 'UTF-8') . "</span>)</span>" : ""; ?></h4>
                    <?php if ($filter_user > 0): ?>
                        <a href="notifications.php#history" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="fas fa-times me-1"></i> إلغاء الفلتر</a>
                    <?php endif; ?>
                </div>
                <div class="table-responsive bg-white rounded shadow-sm">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>المستلم</th>
                                <th>الرسالة</th>
                                <th>النوع</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recent_notifs && $recent_notifs->num_rows > 0): ?>
                            <?php while($row = $recent_notifs->fetch_assoc()): ?>
                            <tr class="<?php echo (isset($row['type']) && $row['type'] == 'warning') ? 'table-danger' : ''; ?>">
                                <td class="fw-bold text-primary recipient-name">
                                    <?php 
                                    if ($row['recipient_count'] > 1) {
                                        echo 'جميع المستخدمين';
                                    } else {
                                        $name = $row['first_name'] ? htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) : 'غير معروف';
                                        if (isset($row['user_id'])) {
                                            echo '<a href="?user_id=' . $row['user_id'] . '#history" class="text-decoration-none" title="عرض إشعارات هذا المستخدم فقط">' . $name . '</a>';
                                        } else {
                                            echo $name;
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['message']); ?></td>
                                <td>
                                    <?php if(isset($row['type']) && $row['type'] == 'warning'): ?>
                                        <div class="status-pill blocked">إنذار <i class="fas fa-triangle-exclamation ms-1"></i></div>
                                    <?php else: ?>
                                        <div class="status-pill info">عام <i class="fas fa-circle-info ms-1"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['created_at']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">لا توجد إشعارات لعرضها.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- إضافة jQuery و Select2 -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.getElementById("menu-toggle").onclick = function () { document.getElementById("wrapper").classList.toggle("toggled"); };

        // تفعيل البحث في القائمة
        $(document).ready(function() {
            $('#userSelect').select2({
                dir: "rtl",
                width: '100%',
                placeholder: "ابحث عن الاسم أو الـ ID...",
                language: {
                    noResults: function() {
                        return "لا توجد نتائج مطابقة";
                    }
                }
            });
        });

        // دالة إظهار حالة التحميل للزر لمنع النقر المزدوج
        function showLoadingBtn() {
            var btn = document.getElementById('sendNotifBtn');
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> جاري الإرسال...';
            btn.style.opacity = '0.8';
            btn.style.cursor = 'not-allowed';
            setTimeout(function() { btn.disabled = true; }, 50); // إيقاف الزر فور اعتماده
            return true;
        }
    </script>
</body>
</html>