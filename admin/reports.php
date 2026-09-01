<?php
session_start();
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
requireAdminPermission('manage_reports');

// ✅ إصلاح تلقائي: التحقق من وجود عمود 'status' وإضافته إذا كان مفقوداً لتجنب الأخطاء
$check_status = $conn->query("SHOW COLUMNS FROM reports LIKE 'status'");
if ($check_status->num_rows == 0) {
    $conn->query("ALTER TABLE reports ADD COLUMN status ENUM('pending', 'resolved') DEFAULT 'pending'");
}

// معالجة حذف البلاغ
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM reports WHERE report_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: reports.php?msg=deleted");
    exit();
}

// معالجة الحذف الجماعي
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("طلب غير صالح.");
    }
    if (!empty($_POST['selected_reports'])) {
        $ids = array_map('intval', $_POST['selected_reports']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM reports WHERE report_id IN ($placeholders)");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $stmt->close();
        header("Location: reports.php?msg=bulk_deleted");
        exit();
    }
}

// معالجة إرسال الإنذار
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_warning'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $target_id = intval($_POST['target_user_id']);
    
    // التحقق من حالة المستخدم أولاً
    $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $target_id);
    $stmt->execute();
    $user_status = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($user_status && $user_status['status'] === 'banned') {
        header("Location: reports.php?error=already_banned");
        exit();
    } else {
        $raw_warning = $_POST['warning_message'];
        $warning_msg = "إنذار إداري: " . $conn->real_escape_string($raw_warning);
        
        // إرسال الإنذار كإشعار من نوع warning
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'warning', NOW())");
        $stmt->bind_param("is", $target_id, $warning_msg);
        $stmt->execute();
        $stmt->close();
        
        // --- إرسال بريد إلكتروني ---
        $stmt = $conn->prepare("SELECT email, first_name FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $u_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($u_info) {
            $subject = "تنبيه إداري - إثراء";
            $email_body = "مرحباً {$u_info['first_name']}،\n\nلقد تلقيت إنذاراً من الإدارة بخصوص بلاغ مقدم ضدك:\n$raw_warning\n\nيرجى الالتزام بشروط الاستخدام لتجنب حظر الحساب.\n\nإدارة منصة إثراء.";
            $headers = "From: no-reply@ithraa.com\r\nContent-Type: text/plain; charset=UTF-8";
            @mail($u_info['email'], $subject, $email_body, $headers);
        }
        
        // التحقق من عدد الإنذارات للحظر التلقائي
        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND type = 'warning'");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $check_warn = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($check_warn['c'] >= 3) {
            $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE user_id = ?");
            $stmt->bind_param("i", $target_id);
            $stmt->execute();
            $stmt->close();
            $msg_param = "warning_banned";
        } else {
            $msg_param = "warning_sent";
        }

        // تحديث حالة البلاغ إلى "تم اتخاذ إجراء" (resolved)
        if (isset($_POST['report_id_for_warning'])) {
            $rid = intval($_POST['report_id_for_warning']);
            $stmt = $conn->prepare("UPDATE reports SET status = 'resolved' WHERE report_id = ?");
            $stmt->bind_param("i", $rid);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: reports.php?msg=$msg_param");
        exit();
    }
}

// معالجة تغيير الحالة يدوياً
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("UPDATE reports SET status = IF(status='resolved', 'pending', 'resolved') WHERE report_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: reports.php");
    exit();
}

// استعلام لجلب البلاغات مع اسم المُبلّغ
// تم تعديل الاستعلام ليتوافق مع هيكل الجدول: user_id, title, content
$sql = "SELECT r.*, u.first_name, u.last_name, u.phone,
               m.sender_id as reported_user_id,
               ru.first_name as reported_first_name,
               ru.last_name as reported_last_name
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.user_id 
        LEFT JOIN messages m ON r.message_id = m.message_id
        LEFT JOIN users ru ON m.sender_id = ru.user_id
        ORDER BY r.created_at DESC";
$reports = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة البلاغات - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
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
                    <h2 class="fs-2 m-0">البلاغات والشكاوى</h2>
                </div>
            </nav>
            <div class="container-fluid px-4">
                <div class="row my-5">
                    <div class="col">
                        <form method="POST" id="bulkDeleteForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="d-flex justify-content-end mb-2">
                            <?php if($reports && $reports->num_rows > 0): ?>
                            <button type="button" class="btn btn-danger text-white rounded-pill fw-bold d-inline-flex align-items-center justify-content-center shadow-sm" id="bulkDeleteBtn" style="min-width: 125px; height: 38px; padding: 6px 18px; font-size: 0.88rem; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.2); transition: all 0.3s ease;" onclick="confirmBulkDelete()">
                                <i class="fa-solid fa-trash-can ms-2"></i> حذف المحدد
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="table-responsive bg-white rounded shadow-sm">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAll" style="cursor: pointer;">
                                        </th>
                                        <th>#</th>
                                        <th>المُبلِّغ</th>
                                        <th>المُبلَّغ عنه</th>
                                        <th>العنوان</th>
                                        <th>التفاصيل</th>
                                        <th>التاريخ</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($reports && $reports->num_rows > 0): ?>
                                        <?php while($row = $reports->fetch_assoc()): 
                                            // --- منطق استخراج البيانات وتنظيف النص ---
                                            $reported_name = 'غير محدد';
                                            $reported_id = 'N/A';
                                            $full_content = $row['content'];
                                            $clean_content = $full_content;

                                            // 1. فصل النص الأصلي عن البيانات المضافة (للعرض في المودال)
                                            $separator = "\n\n--------------------------------\n";
                                            if (strpos($full_content, $separator) !== false) {
                                                $clean_content = strstr($full_content, $separator, true);
                                            }

                                            // 2. استخراج اسم المبلغ عنه والـ ID من قاعدة البيانات
                                            if (!empty($row['reported_first_name'])) {
                                                $reported_name = $row['reported_first_name'] . ' ' . $row['reported_last_name'];
                                                $reported_id = $row['reported_user_id'];
                                            } else {
                                                // التوافق مع البلاغات القديمة
                                                if (preg_match('/الاسم: (.*)\n/u', $full_content, $matches_name)) {
                                                    $reported_name = trim($matches_name[1]);
                                                }
                                                if (preg_match('/رقم المستخدم \(ID\): (\d+)/', $full_content, $matches_id)) {
                                                    $reported_id = $matches_id[1];
                                                }
                                            }
                                        ?>
                                        <tr class="<?php echo (isset($row['status']) && $row['status'] == 'resolved') ? 'table-success' : ''; ?>" style="<?php echo (isset($row['status']) && $row['status'] == 'resolved') ? '--bs-table-bg: #f0fff4;' : ''; ?>">
                                            <td class="text-center">
                                                <input type="checkbox" name="selected_reports[]" value="<?php echo $row['report_id']; ?>" class="form-check-input report-checkbox" style="cursor: pointer;">
                                            </td>
                                            <td><?php echo $row['report_id']; ?></td>
                                            <td>
                                                <?php if($row['first_name']): ?>
                                                    <a href="user_details.php?id=<?php echo $row['user_id']; ?>&source=reports" class="text-decoration-none fw-bold text-primary" title="عرض تفاصيل المستخدم">
                                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    مستخدم محذوف
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($reported_id !== 'N/A'): ?>
                                                    <a href="user_details.php?id=<?php echo htmlspecialchars($reported_id); ?>&source=reports" class="text-decoration-none fw-bold text-primary" title="عرض تفاصيل المستخدم">
                                                        <?php echo htmlspecialchars($reported_name); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($reported_name); ?>
                                                <?php endif; ?>
                                            </td>
                                            <?php 
                                            // إزالة رقم الطلب من العنوان للعرض الأنيق
                                            $display_title = preg_replace('/ \(طلب \d+\)$/', '', $row['title']); 
                                            ?>
                                            <td><?php echo htmlspecialchars($display_title); ?></td>
                                            <td>
                                                <!-- نمرر $clean_content بدلاً من المحتوى الكامل لإخفاء البيانات من المودال -->
                                                <button type="button" class="btn btn-sm btn-link text-decoration-none fw-bold text-primary p-0 btn-show-report" style="transition: all 0.3s ease;"
                                                        data-title="<?php echo htmlspecialchars($display_title, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-content="<?php echo htmlspecialchars($clean_content, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-reporter="<?php echo htmlspecialchars(($row['first_name'] . ' ' . $row['last_name']) ?: 'مستخدم محذوف', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-reported="<?php echo htmlspecialchars($reported_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-user-id="<?php echo ($reported_id !== 'N/A' ? (int)$reported_id : (int)$row['user_id']); ?>"
                                                        data-report-id="<?php echo (int)$row['report_id']; ?>"
                                                        data-status="<?php echo htmlspecialchars($row['status'] ?? 'pending', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-reporter-id="<?php echo !empty($row['user_id']) ? (int)$row['user_id'] : ''; ?>"
                                                        data-reported-id="<?php echo ($reported_id !== 'N/A') ? htmlspecialchars($reported_id, ENT_QUOTES, 'UTF-8') : ''; ?>"
                                                        onclick="openReportModalFromBtn(this)" title="عرض تفاصيل البلاغ">
                                                    <i class="fas fa-eye me-1"></i> عرض
                                                </button>
                                            </td>
                                            <td><?php echo $row['created_at']; ?></td>
                                            <td>
                                                <?php if(isset($row['status']) && $row['status'] == 'resolved'): ?>
                                                <a href="?action=toggle_status&id=<?php echo $row['report_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="status-pill active text-decoration-none" title="انقر للتبديل إلى: قيد الانتظار">تم الإجراء <i class="fas fa-check ms-1"></i></a>
                                                <?php else: ?>
                                                <a href="?action=toggle_status&id=<?php echo $row['report_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="status-pill pending text-decoration-none" title="انقر للتبديل إلى: تم الإجراء">قيد الانتظار</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <i class="fa-solid fa-shield-halved fa-4x text-success mb-3" style="opacity: 0.6;"></i>
                                                <h5 class="text-muted fw-bold">المنصة آمنة! لا توجد أي بلاغات حالياً.</h5>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal عرض تفاصيل البلاغ -->
    <div class="modal fade" id="reportDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
                <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
                    <div style="width: 80px; height: 80px; background-color: #e3f2fd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                        <i class="fas fa-file-alt" style="color: #0d6efd; font-size: 36px;"></i>
                    </div>
                    <h4 class="modal-title fw-bold text-primary" id="modalReportTitle" style="font-family: 'Cairo', sans-serif; text-align: center;"></h4>
                    <div class="text-center mt-2">
                        <p class="text-muted small mb-1">المُبلِّغ: <span id="modalReporterName" class="fw-bold text-dark"></span></p>
                        <p class="text-muted small mb-0">المُبلَّغ عنه: <span id="modalReportedName" class="fw-bold text-dark"></span></p>
                    </div>
                </div>
                <div class="modal-body pt-4">
                    <div class="p-3 bg-light rounded-3 border border-light">
                        <p id="modalReportContent" style="white-space: pre-wrap; color: #555; font-size: 1rem; line-height: 1.6; margin: 0;"></p>
                    </div>
                    
                    <!-- قسم إرسال الإنذار -->
                    <div class="collapse mt-3" id="warningCollapse">
                        <div class="card card-body border-0 shadow-sm" style="background-color: #fff5f5; border-radius: 15px;">
                            <h6 class="text-danger fw-bold mb-2"><i class="fas fa-exclamation-triangle"></i> إرسال إنذار للمستخدم</h6>
                            <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="report_id_for_warning" id="warningReportId">
                                <div class="mb-2">
                                    <label class="form-label small text-muted fw-bold">ID المستخدم المستهدف:</label>
                                    <input type="number" name="target_user_id" id="warningTargetId" class="form-control bg-light" style="max-width: 150px; cursor: not-allowed;" required readonly>
                                    <small class="text-muted" style="font-size: 0.75rem;">(يتم تعبئته تلقائياً بـ ID المُبلّغ عنه إن وجد)</small>
                                </div>
                                <textarea name="warning_message" class="form-control mb-2 border-danger" rows="2" placeholder="اكتب سبب الإنذار هنا..." required style="background-color: #fff; border-radius: 10px;"></textarea>
                                <div class="text-end">
                                    <button type="submit" name="send_warning" class="btn btn-sm btn-danger fw-bold px-3" style="border-radius: 8px;">إرسال الآن</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2">
                    <button type="button" id="btnShowWarning" class="btn btn-outline-danger fw-bold px-4 py-2" style="border-radius: 12px;" data-bs-toggle="collapse" data-bs-target="#warningCollapse">
                        <i class="fas fa-bullhorn me-1"></i> إرسال إنذار
                    </button>
                    <button type="button" class="btn btn-secondary fw-bold px-4 py-2" style="border-radius: 12px;" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("menu-toggle").onclick = function () { document.getElementById("wrapper").classList.toggle("toggled"); };

        function confirmDelete(e, url) {
            e.preventDefault();
            Swal.fire({
                title: 'تأكيد الحذف',
                text: "هل أنت متأكد من حذف هذا البلاغ نهائياً؟",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#f1f3f5',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'تراجع',
                customClass: { cancelButton: 'text-dark' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            })
        }

        function confirmBulkDelete() {
            var anyChecked = document.querySelectorAll('.report-checkbox:checked').length > 0;
            if (!anyChecked) {
                Swal.fire({
                    title: 'لم يتم التحديد!',
                    text: 'يرجى تحديد بلاغ واحد على الأقل لإتمام عملية الحذف.',
                    icon: 'info',
                    confirmButtonColor: '#021C7B',
                    confirmButtonText: 'حسناً'
                });
                return;
            }
            Swal.fire({
                title: 'تأكيد الحذف الجماعي',
                text: "هل أنت متأكد من حذف البلاغات المحددة؟",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#f1f3f5',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'تراجع',
                customClass: { cancelButton: 'text-dark' }
            }).then((result) => {
                if (result.isConfirmed) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'bulk_delete';
                    input.value = '1';
                    document.getElementById('bulkDeleteForm').appendChild(input);
                    document.getElementById('bulkDeleteForm').submit();
                }
            })
        }

        function openReportModalFromBtn(btn) {
            const title = btn.getAttribute('data-title') || '';
            const content = btn.getAttribute('data-content') || '';
            const reporter = btn.getAttribute('data-reporter') || '';
            const reportedName = btn.getAttribute('data-reported') || '';
            const userId = btn.getAttribute('data-user-id') || '';
            const reportId = btn.getAttribute('data-report-id') || '';
            const status = btn.getAttribute('data-status') || '';
            const reporterId = btn.getAttribute('data-reporter-id') || '';
            const reportedId = btn.getAttribute('data-reported-id') || '';
            showReportDetails(title, content, reporter, reportedName, userId, reportId, status, reporterId, reportedId);
        }

        function showReportDetails(title, content, reporter, reportedName, userId, reportId, status, reporterId, reportedId) {
            document.getElementById('modalReportTitle').textContent = title;
            document.getElementById('modalReportContent').textContent = content;
            
            const reporterContainer = document.getElementById('modalReporterName');
            reporterContainer.innerHTML = '';
            if (reporterId && reporterId !== '') {
                const a = document.createElement('a');
                a.href = `user_details.php?id=${encodeURIComponent(reporterId)}&source=reports`;
                a.className = 'text-decoration-none text-primary';
                a.title = 'عرض تفاصيل المستخدم';
                a.textContent = reporter;
                reporterContainer.appendChild(a);
            } else {
                reporterContainer.textContent = reporter;
            }
            
            const reportedContainer = document.getElementById('modalReportedName');
            reportedContainer.innerHTML = '';
            if (reportedId && reportedId !== 'N/A' && reportedId !== '') {
                const a = document.createElement('a');
                a.href = `user_details.php?id=${encodeURIComponent(reportedId)}&source=reports`;
                a.className = 'text-decoration-none text-primary';
                a.title = 'عرض تفاصيل المستخدم';
                a.textContent = reportedName;
                reportedContainer.appendChild(a);
            } else {
                reportedContainer.textContent = reportedName;
            }
            
            // تعيين معرف المستخدم في نموذج الإنذار
            document.getElementById('warningTargetId').value = userId;
            document.getElementById('warningReportId').value = reportId;
            
            // إخفاء نموذج الإنذار عند فتح النافذة من جديد
            var collapseElement = document.getElementById('warningCollapse');
            var bsCollapse = new bootstrap.Collapse(collapseElement, { toggle: false });
            bsCollapse.hide();
            
            // إخفاء زر الإنذار إذا كانت الحالة "تم الإجراء" (resolved)
            if (status === 'resolved') {
                document.getElementById('btnShowWarning').style.display = 'none';
            } else {
                document.getElementById('btnShowWarning').style.display = '';
            }

            var myModal = new bootstrap.Modal(document.getElementById('reportDetailsModal'));
            myModal.show();
        }

        // كود تحديد الكل
        document.getElementById('selectAll').addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('.report-checkbox');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = document.getElementById('selectAll').checked;
            });
        });

        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('msg')) {
                let msg = urlParams.get('msg');
                if (msg === 'deleted') {
                    Swal.fire('تم الحذف', 'تم حذف البلاغ بنجاح.', 'success');
                } else if (msg === 'bulk_deleted') {
                    Swal.fire('تم الحذف', 'تم حذف البلاغات المحددة بنجاح.', 'success');
                } else if (msg === 'warning_sent') {
                    Swal.fire('تم الإرسال', 'تم إرسال الإنذار للمستخدم بنجاح.', 'success');
                } else if (msg === 'warning_banned') {
                    Swal.fire('تم الإرسال والحظر', 'تم إرسال الإنذار. تم حظر المستخدم تلقائياً لتجاوزه الحد المسموح (3 إنذارات).', 'warning');
                }
                window.history.replaceState(null, null, window.location.pathname);
            }
            if (urlParams.has('error') && urlParams.get('error') === 'already_banned') {
                Swal.fire('عذراً', 'لا يمكن إرسال إنذار لأن المستخدم محظور بالفعل.', 'error');
                window.history.replaceState(null, null, window.location.pathname);
            }

            // تفعيل التلميحات (Tooltips)
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>