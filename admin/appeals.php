<?php
session_start();
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
requireAdminPermission('manage_appeals');

// معالجة حذف الاعتراض (رفض)
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $id = intval($_GET['delete']);
    
    // جلب معرف المستخدم لإرسال إشعار بالرفض قبل الحذف
    $stmt = $conn->prepare("SELECT user_id FROM appeals WHERE appeal_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $uid = $row['user_id'];
        $msg = "عذراً، تم رفض اعتراضك.";
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'info', NOW())");
        $stmt_notif->bind_param("is", $uid, $msg);
        $stmt_notif->execute();
        $stmt_notif->close();
    }
    $stmt->close();

    $stmt_del = $conn->prepare("DELETE FROM appeals WHERE appeal_id = ?");
    $stmt_del->bind_param("i", $id);
    $stmt_del->execute();
    $stmt_del->close();
    header("Location: appeals.php?msg=deleted");
    exit();
}

// معالجة قبول الاعتراض (حذف الإنذار وحذف الاعتراض)
if (isset($_GET['accept'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $id = intval($_GET['accept']);
    
    // جلب بيانات الاعتراض
    $stmt = $conn->prepare("SELECT notification_id, user_id FROM appeals WHERE appeal_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $notif_id = $row['notification_id'];
        $user_id = $row['user_id'];
        
        // حذف الإنذار من الإشعارات
        $stmt_del_notif = $conn->prepare("DELETE FROM notifications WHERE notification_id = ?");
        $stmt_del_notif->bind_param("i", $notif_id);
        $stmt_del_notif->execute();
        $stmt_del_notif->close();
        
        // حذف الاعتراض بعد قبوله
        $stmt_del_appeal = $conn->prepare("DELETE FROM appeals WHERE appeal_id = ?");
        $stmt_del_appeal->bind_param("i", $id);
        $stmt_del_appeal->execute();
        $stmt_del_appeal->close();
        
        // إرسال إشعار بالقبول للمستخدم
        $msg = "تم قبول اعتراضك وإلغاء العقوبة بنجاح.";
        $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'info', NOW())");
        $stmt_notif->bind_param("is", $user_id, $msg);
        $stmt_notif->execute();
        $stmt_notif->close();
        
        // التحقق من عدد الإنذارات المتبقية لفك الحظر إذا لزم الأمر
        $stmt_warn = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND type = 'warning'");
        $stmt_warn->bind_param("i", $user_id);
        $stmt_warn->execute();
        $warn_count = $stmt_warn->get_result()->fetch_assoc()['c'];
        $stmt_warn->close();
        
        // إذا أصبح عدد الإنذارات أقل من 3 وكان المستخدم محظوراً، نعيد تفعيله
        if ($warn_count < 3) {
            $stmt_act = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND status = 'banned'");
            $stmt_act->bind_param("i", $user_id);
            $stmt_act->execute();
            $stmt_act->close();
        }
    }
    $stmt->close();
    header("Location: appeals.php?msg=accepted");
    exit();
}

// جلب قائمة الاعتراضات
$sql = "SELECT a.*, u.first_name, u.last_name, u.phone, n.message as notif_message 
        FROM appeals a 
        JOIN users u ON a.user_id = u.user_id 
        LEFT JOIN notifications n ON a.notification_id = n.notification_id 
        ORDER BY a.appeal_id DESC";
$appeals = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة الاعتراضات - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
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
                    <h2 class="fs-2 m-0">الاعتراضات على الإنذارات</h2>
                </div>
            </nav>
            <div class="container-fluid px-4">
                
                <?php if(isset($_GET['msg'])): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            let msgText = '';
                            <?php if($_GET['msg'] == 'deleted'): ?>
                                msgText = 'تم رفض الاعتراض وحذفه.';
                            <?php elseif($_GET['msg'] == 'accepted'): ?>
                                msgText = 'تم قبول الاعتراض وحذف الإنذار بنجاح.';
                            <?php endif; ?>
                            if (msgText) {
                                safeSwal({
                                    icon: 'success',
                                    title: 'تم بنجاح',
                                    text: msgText,
                                    confirmButtonText: 'حسناً',
                                    confirmButtonColor: '#021C7B'
                                });
                            }
                        });
                    </script>
                <?php endif; ?>

                <div class="row my-5">
                    <div class="col">
                        <div class="table-responsive bg-white rounded shadow-sm">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>ID المستخدم</th>
                                        <th>المستخدم</th>
                                        <th>سبب الاعتراض</th>
                                        <th>الإنذار الأصلي</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($appeals && $appeals->num_rows > 0): ?>
                                        <?php while($row = $appeals->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['appeal_id']; ?></td>
                                            <td><?php echo $row['user_id']; ?></td>
                                            <td>
                                                <a href="user_details.php?id=<?php echo $row['user_id']; ?>&source=appeals" class="text-decoration-none fw-bold text-primary" title="عرض تفاصيل المستخدم">
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-link text-decoration-none fw-bold text-primary p-0 btn-appeal-reason" style="transition: all 0.3s ease;" data-reason="<?php echo htmlspecialchars($row['reason'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="showAppealReason(this.getAttribute('data-reason'))" title="عرض سبب الاعتراض">
                                                    <i class="fas fa-eye me-1"></i> عرض
                                                </button>
                                            </td>
                                            <td>
                                                <?php if($row['notif_message']): ?>
                                                    <button type="button" class="btn btn-sm btn-link text-decoration-none fw-bold text-primary p-0 btn-orig-warning" style="transition: all 0.3s ease;" data-warning="<?php echo htmlspecialchars($row['notif_message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="showOriginalWarning(this.getAttribute('data-warning'))" title="عرض الإنذار الأصلي">
                                                        <i class="fas fa-eye me-1"></i> عرض
                                                    </button>
                                                <?php else: ?>
                                                    <div class="status-pill secondary">الإنذار محذوف</div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?accept=<?php echo $row['appeal_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn-custom-accept me-1" onclick="confirmAccept(event, this.href)" title="قبول الاعتراض وإزالة الإنذار">
                                                    قبول
                                                </a>
                                                <a href="?delete=<?php echo $row['appeal_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn-custom-reject" onclick="confirmReject(event, this.href)" title="رفض الاعتراض">
                                                    رفض
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <i class="fa-solid fa-scale-balanced fa-4x text-primary mb-3" style="opacity: 0.4;"></i>
                                                <h5 class="text-muted fw-bold">العدالة مُحققة! لا توجد أي اعتراضات قيد الانتظار.</h5>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("menu-toggle").onclick = function () { document.getElementById("wrapper").classList.toggle("toggled"); };

        function confirmAccept(e, url) {
            e.preventDefault();
            document.getElementById('confirmAcceptBtn').href = url;
            var myModal = new bootstrap.Modal(document.getElementById('acceptModal'));
            myModal.show();
        }

        function confirmReject(e, url) {
            e.preventDefault();
            document.getElementById('confirmRejectBtn').href = url;
            var myModal = new bootstrap.Modal(document.getElementById('rejectModal'));
            myModal.show();
        }

        function showAppealReason(reason) {
            document.getElementById('modalAppealReason').textContent = reason || '';
            var myModal = new bootstrap.Modal(document.getElementById('reasonModal'));
            myModal.show();
        }

        function showOriginalWarning(warning) {
            document.getElementById('modalOriginalWarning').textContent = warning || '';
            var myModal = new bootstrap.Modal(document.getElementById('warningModal'));
            myModal.show();
        }

        // تفعيل التلميحات
        document.addEventListener("DOMContentLoaded", function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

    <!-- Modal قبول الاعتراض -->
    <div class="modal fade" id="acceptModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
          <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
            <div style="width: 80px; height: 80px; background-color: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; box-shadow: 0 5px 15px rgba(46, 125, 50, 0.1);">
                <i class="fa-solid fa-check" style="color: #2e7d32; font-size: 36px;"></i>
            </div>
            <h4 class="modal-title fw-bold text-success" style="font-family: 'Cairo', sans-serif;">قبول الاعتراض</h4>
          </div>
          <div class="modal-body text-center pt-2">
            <p class="text-muted fw-bold mb-4" style="font-size: 1.1rem;">
                هل أنت متأكد من قبول هذا الاعتراض؟<br>
                <span class="small text-secondary" style="font-weight: normal;">سيتم حذف الإنذار الأصلي وإشعار المستخدم بالقبول.</span>
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-light fw-bold px-4 py-2" style="border-radius: 12px; background-color: #f1f3f5; min-width: 120px;" data-bs-dismiss="modal">تراجع</button>
                <a href="#" id="confirmAcceptBtn" class="btn btn-custom-accept fw-bold px-4 py-2" style="border-radius: 12px; min-width: 120px;">نعم، قبول</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal رفض الاعتراض -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
          <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
            <div style="width: 80px; height: 80px; background-color: #fff5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; box-shadow: 0 5px 15px rgba(220, 53, 69, 0.1);">
                <i class="fa-solid fa-xmark" style="color: #dc3545; font-size: 36px;"></i>
            </div>
            <h4 class="modal-title fw-bold text-danger" style="font-family: 'Cairo', sans-serif;">رفض الاعتراض</h4>
          </div>
          <div class="modal-body text-center pt-2">
            <p class="text-muted fw-bold mb-4" style="font-size: 1.1rem;">
                هل أنت متأكد من رفض هذا الاعتراض؟<br>
                <span class="small text-secondary" style="font-weight: normal;">سيتم حذف الاعتراض وإشعار المستخدم بالرفض.</span>
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-light fw-bold px-4 py-2" style="border-radius: 12px; background-color: #f1f3f5; min-width: 120px;" data-bs-dismiss="modal">تراجع</button>
                <a href="#" id="confirmRejectBtn" class="btn btn-custom-reject fw-bold px-4 py-2" style="border-radius: 12px; min-width: 120px;">نعم، رفض</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal عرض سبب الاعتراض -->
    <div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
          <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
            <div style="width: 80px; height: 80px; background-color: #e3f2fd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                <i class="fas fa-file-alt" style="color: #0d6efd; font-size: 36px;"></i>
            </div>
            <h4 class="modal-title fw-bold text-primary" style="font-family: 'Cairo', sans-serif;">سبب الاعتراض</h4>
          </div>
          <div class="modal-body pt-4">
            <div class="p-3 bg-light rounded-3 border border-light">
                <p id="modalAppealReason" style="white-space: pre-wrap; color: #555; font-size: 1rem; line-height: 1.6; margin: 0; text-align: right;"></p>
            </div>
          </div>
          <div class="modal-footer border-0 justify-content-center">
            <button type="button" class="btn btn-secondary fw-bold px-4 py-2" style="border-radius: 12px;" data-bs-dismiss="modal">إغلاق</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal عرض الإنذار الأصلي -->
    <div class="modal fade" id="warningModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
          <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
            <div style="width: 80px; height: 80px; background-color: #fff5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                <i class="fas fa-triangle-exclamation" style="color: #dc3545; font-size: 36px;"></i>
            </div>
            <h4 class="modal-title fw-bold text-danger" style="font-family: 'Cairo', sans-serif;">الإنذار الأصلي</h4>
          </div>
          <div class="modal-body pt-4">
            <div class="p-3 bg-light rounded-3 border border-light">
                <p id="modalOriginalWarning" style="white-space: pre-wrap; color: #555; font-size: 1rem; line-height: 1.6; margin: 0; text-align: right;"></p>
            </div>
          </div>
          <div class="modal-footer border-0 justify-content-center">
            <button type="button" class="btn btn-secondary fw-bold px-4 py-2" style="border-radius: 12px;" data-bs-dismiss="modal">إغلاق</button>
          </div>
        </div>
      </div>
    </div>
</body>
</html>
