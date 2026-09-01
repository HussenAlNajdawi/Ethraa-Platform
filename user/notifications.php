<?php
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// جلب الطلبات الواردة (كمقدم خدمة)
$sql_incoming = "SELECT r.*, u.first_name, u.last_name, s.name as service_name 
                 FROM requests r 
                 LEFT JOIN users u ON r.requester_id = u.user_id 
                 LEFT JOIN services s ON r.service_id = s.service_id
                 WHERE r.provider_id = ? AND r.hidden_for_provider = 0
                 ORDER BY r.created_at DESC";

// جلب الطلبات الصادرة (كطالب خدمة)
$sql_outgoing = "SELECT r.*, u.first_name, u.last_name, s.name as service_name 
                 FROM requests r 
                 LEFT JOIN users u ON r.provider_id = u.user_id 
                 LEFT JOIN services s ON r.service_id = s.service_id
                 WHERE r.requester_id = ? AND r.hidden_for_requester = 0
                 ORDER BY r.created_at DESC";

// جلب إشعارات النظام من الجدول الموجود
$sql_system = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";

$notifications = [];

// معالجة الواردة
$stmt = $conn->prepare($sql_incoming);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res_in = $stmt->get_result();
$stmt->close();
while($row = $res_in->fetch_assoc()) {
    $notifications[] = [
        'type' => 'incoming',
        'id' => $row['request_id'],
        'checkbox_val' => 'inc_' . $row['request_id'], // تمييز النوع للحذف
        'name' => ($row['first_name'] ?? 'مستخدم') . ' ' . ($row['last_name'] ?? ''),
        'service' => $row['service_name'] ?? 'خدمة عامة',
        'status' => $row['status'],
        'date' => $row['created_at']
    ];
}

// معالجة الصادرة
$stmt = $conn->prepare($sql_outgoing);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res_out = $stmt->get_result();
$stmt->close();
while($row = $res_out->fetch_assoc()) {
    $notifications[] = [
        'type' => 'outgoing',
        'id' => $row['request_id'],
        'checkbox_val' => 'out_' . $row['request_id'], // تمييز النوع للحذف
        'name' => ($row['first_name'] ?? 'مستخدم') . ' ' . ($row['last_name'] ?? ''),
        'service' => $row['service_name'] ?? 'خدمة عامة',
        'status' => $row['status'],
        'date' => $row['created_at']
    ];
}

// معالجة إشعارات النظام (باستخدام الجدول الموجود)
$stmt = $conn->prepare($sql_system);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res_sys = $stmt->get_result();
$stmt->close();
while($row = $res_sys->fetch_assoc()) {
    $notifications[] = [
        'type' => 'system',
        'notif_id' => $row['notification_id'], // إضافة المعرف للحذف والاعتراض
        'checkbox_val' => 'sys_' . $row['notification_id'], // تمييز النوع للحذف
        'message' => $row['message'],
        'date' => $row['created_at'],
        'is_read' => $row['is_read'],
        'db_type' => $row['type']
    ];
}

// جلب جميع الإنذارات التي قدم المستخدم اعتراضاً عليها مسبقاً
$appealed_notifs = [];
$app_stmt = $conn->prepare("SELECT notification_id FROM appeals WHERE user_id = ?");
$app_stmt->bind_param("i", $user_id);
$app_stmt->execute();
$app_res = $app_stmt->get_result();
while ($ar = $app_res->fetch_assoc()) {
    $appealed_notifs[] = $ar['notification_id'];
}
$app_stmt->close();

// تحديث الإشعارات لتصبح مقروءة عند فتح الصفحة
$up_notif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$up_notif->bind_param("i", $user_id);
$up_notif->execute();
$up_notif->close();

// ترتيب حسب التاريخ الأحدث
usort($notifications, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$page_title = 'الإشعارات - إثراء';
$page_css = '
    <link rel="stylesheet" href="../assets/css/account_style.css?v=' . filemtime(__DIR__ . '/../assets/css/account_style.css') . '">
    <link rel="stylesheet" href="../assets/css/notifications.css?v=' . filemtime(__DIR__ . '/../assets/css/notifications.css') . '">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
';
include '../includes/user_header.php';
?>

<?php include '../includes/user_navbar.php'; ?>

<div class="container">
    <div class="account-container" style="min-height: 80vh;">
        
        <h1 class="page-title">الإشعارات</h1>

        <!-- أزرار التحكم -->
        <div class="d-flex justify-content-between align-items-center mb-3 px-2">
            <div>
                <button type="button" class="btn btn-danger rounded-pill fw-bold d-inline-flex align-items-center justify-content-center shadow-sm" style="min-width: 125px; height: 38px; padding: 6px 18px; font-size: 0.88rem;" onclick="submitDeleteForm()">
                    <i class="fa-solid fa-trash-can ms-2"></i> حذف المحدد
                </button>
            </div>
            <div>
                <form id="deleteAllForm" action="../php/manage_notifications.php" method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="delete_all">
                    <button type="button" class="btn btn-outline-danger rounded-pill fw-bold d-inline-flex align-items-center justify-content-center shadow-sm" style="min-width: 125px; height: 38px; padding: 6px 18px; font-size: 0.88rem;" onclick="confirmDeleteAllNotifications()">
                        <i class="fa-solid fa-trash-can ms-2"></i> حذف الكل
                    </button>
                </form>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-12">
                
                <?php if(empty($notifications)): ?>
                    <div class="text-center mt-5">
                        <i class="fa-regular fa-bell-slash fa-4x text-muted mb-3" style="opacity: 0.5;"></i>
                        <h4 class="text-muted">لا توجد إشعارات حالياً</h4>
                    </div>
                <?php else: ?>
                    
                    <form id="deleteForm" action="../php/manage_notifications.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="delete_selected">
                    
                    <?php foreach($notifications as $notif): ?>
                        <?php 
                            // تحديد الأيقونة واللون والنص حسب الحالة
                            $statusClass = ''; $iconClass = ''; $icon = ''; $text = ''; $can_appeal = false; $can_delete = false; $is_appealed = false;

                            $safe_name = htmlspecialchars($notif['name'] ?? '', ENT_QUOTES, 'UTF-8');
                            $safe_service = htmlspecialchars($notif['service'] ?? '', ENT_QUOTES, 'UTF-8');
                            $safe_message = htmlspecialchars($notif['message'] ?? '', ENT_QUOTES, 'UTF-8');

                            if ($notif['type'] == 'incoming') {
                                if ($notif['status'] == 'pending') { $statusClass = 'status-pending'; $iconClass = 'icon-pending'; $icon = 'fa-user-clock'; $text = "لديك طلب خدمة جديد من <strong>{$safe_name}</strong> في مجال <strong>{$safe_service}</strong>"; }
                                elseif ($notif['status'] == 'accepted') { $statusClass = 'status-accepted'; $iconClass = 'icon-accepted'; $icon = 'fa-check'; $text = "لقد وافقت على طلب <strong>{$safe_name}</strong>"; }
                                elseif ($notif['status'] == 'rejected') { $statusClass = 'status-rejected'; $iconClass = 'icon-rejected'; $icon = 'fa-times'; $text = "لقد رفضت طلب <strong>{$safe_name}</strong>"; }
                                elseif ($notif['status'] == 'completed') { $statusClass = 'status-completed'; $iconClass = 'icon-completed'; $icon = 'fa-handshake'; $text = "تم اكتمال الخدمة مع <strong>{$safe_name}</strong> بنجاح"; }
                                $can_delete = true; // ✅ السماح بحذف (إخفاء) إشعارات الطلبات الواردة
                            } elseif ($notif['type'] == 'outgoing') {
                                if ($notif['status'] == 'pending') { $statusClass = 'status-pending'; $iconClass = 'icon-pending'; $icon = 'fa-hourglass-half'; $text = "طلبك المقدم لـ <strong>{$safe_name}</strong> قيد الانتظار"; }
                                elseif ($notif['status'] == 'accepted') { $statusClass = 'status-accepted'; $iconClass = 'icon-accepted'; $icon = 'fa-check-double'; $text = "وافق <strong>{$safe_name}</strong> على طلبك!"; }
                                elseif ($notif['status'] == 'rejected') { $statusClass = 'status-rejected'; $iconClass = 'icon-rejected'; $icon = 'fa-ban'; $text = "عذراً، رفض <strong>{$safe_name}</strong> طلبك"; }
                                elseif ($notif['status'] == 'completed') { $statusClass = 'status-completed'; $iconClass = 'icon-completed'; $icon = 'fa-star'; $text = "قمت بإنهاء الخدمة مع <strong>{$safe_name}</strong>"; }
                                $can_delete = true; // ✅ السماح بحذف (إخفاء) إشعارات الطلبات الصادرة
                            } 
                            // التعامل مع إشعارات النظام
                            elseif ($notif['type'] == 'system') {
                                // إضافة علامة "جديد" إذا كان الإشعار غير مقروء
                                $new_badge = ($notif['is_read'] == 0) ? ' <span class="badge bg-danger rounded-pill" style="font-size: 0.6rem;">جديد</span>' : '';

                                // الاعتماد على نوع الإشعار من قاعدة البيانات
                                if ($notif['db_type'] == 'warning') {
                                    $statusClass = 'status-warning'; 
                                    $iconClass = 'icon-warning'; 
                                    $icon = 'fa-triangle-exclamation'; 
                                    $text = "<strong class='text-danger'>تنبيه إداري:</strong> " . $safe_message . $new_badge; 
                                    
                                    $is_appealed = in_array($notif['notif_id'], $appealed_notifs);
                                    $can_appeal = !$is_appealed; // تفعيل زر الاعتراض فقط إذا لم يعترض مسبقاً
                                    
                                    $can_delete = false; // ⛔ منع حذف التنبيهات المهمة
                                } else {
                                    $statusClass = 'status-info'; 
                                    $iconClass = 'icon-info'; 
                                    $icon = 'fa-circle-info'; 
                                    $text = "<strong>إشعار:</strong> " . $safe_message . $new_badge;
                                    $can_delete = true; // ✅ السماح بحذف الإشعارات العادية
                                }
                            }

                            // إذا لم يتم التعرف على الحالة أو النص فارغ، تخطي العرض
                            if (empty($text)) continue;
                        ?>

                        <div class="notification-card <?php echo $statusClass; ?>">
                            
                            <!-- Checkbox للحذف (فقط لإشعارات النظام) -->
                            <div class="ms-3">
                                <?php if($can_delete): ?>
                                    <input type="checkbox" name="selected_ids[]" value="<?php echo $notif['checkbox_val']; ?>" class="form-check-input custom-checkbox">
                                <?php else: ?>
                                    <input type="checkbox" disabled class="form-check-input custom-checkbox" style="opacity: 0.3; cursor: not-allowed;" title="لا يمكن حذف هذا الإشعار">
                                <?php endif; ?>
                            </div>

                            <div class="d-flex align-items-center w-100 content-wrapper">
                                <div class="notif-icon <?php echo $iconClass; ?>">
                                    <i class="fa-solid <?php echo $icon; ?> fa-lg"></i>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-text mb-1"><?php echo $text; ?></div>
                                    <div class="notif-time"><i class="fa-regular fa-clock ms-1"></i> <?php echo date('Y/m/d h:i A', strtotime($notif['date'])); ?></div>
                                    
                                    <?php if($can_appeal): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-2 py-0 px-2" style="font-size: 0.8rem;" onclick="openAppealModal(<?php echo $notif['notif_id']; ?>)">
                                            <i class="fa-solid fa-gavel ms-1"></i> اعتراض
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal الاعتراض -->
<div class="modal fade" id="appealModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
      <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
        <div style="width: 80px; height: 80px; background-color: #fff5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; box-shadow: 0 5px 15px rgba(220, 53, 69, 0.1);">
            <i class="fa-solid fa-gavel" style="color: #dc3545; font-size: 36px;"></i>
        </div>
        <h4 class="modal-title fw-bold text-danger" style="font-family: 'Cairo', sans-serif;">تقديم اعتراض</h4>
        <button type="button" class="btn-close position-absolute" style="left: 25px; top: 25px;" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="text-center text-muted small mb-4 fw-bold" style="font-size: 0.9rem;">يرجى توضيح سبب اعتراضك على هذا التنبيه ليتم مراجعته من قبل الإدارة.</p>
        <form action="../php/manage_notifications.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="submit_appeal">
            <input type="hidden" name="notification_id" id="appealNotifId">
            
            <div class="mb-4">
                <textarea name="appeal_reason" class="form-control bg-light border-0" rows="4" placeholder="اكتب تفاصيل اعتراضك هنا..." required style="border-radius: 15px; resize: none; padding: 15px; font-size: 0.95rem; box-shadow: inset 0 2px 5px rgba(0,0,0,0.03);"></textarea>
            </div>

            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-light fw-bold px-4 py-2" style="border-radius: 12px; background-color: #f1f3f5; flex: 1; color: #555;" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger fw-bold px-4 py-2" style="border-radius: 12px; flex: 1; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);">إرسال</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function submitDeleteForm() {
        const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
        if (checkboxes.length === 0) {
            Swal.fire({
                title: 'لم يتم التحديد!',
                text: 'يرجى تحديد إشعار واحد على الأقل للحذف.',
                icon: 'info',
                confirmButtonColor: '#021C7B',
                confirmButtonText: 'حسناً'
            });
            return;
        }
        Swal.fire({
            title: 'تأكيد الحذف',
            text: `هل أنت متأكد من حذف ${checkboxes.length} إشعار محدد؟`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteForm').submit();
            }
        });
    }

    function confirmDeleteAllNotifications() {
        Swal.fire({
            title: 'حذف كافة الإشعارات؟',
            text: 'هل أنت متأكد من رغبتك في مسح كافة الإشعارات نهائياً؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، مسح الكل',
            cancelButtonText: 'إلغاء'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('deleteAllForm').submit();
            }
        });
    }

    function openAppealModal(id) {
        document.getElementById('appealNotifId').value = id;
        var myModal = new bootstrap.Modal(document.getElementById('appealModal'));
        myModal.show();
    }

    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'appeal_sent') {
            Swal.fire('تم الإرسال', 'تم إرسال اعتراضك بنجاح وسيتم مراجعته من قبل الإدارة.', 'success');
            window.history.replaceState(null, null, window.location.pathname);
        }
        if (urlParams.get('error') === 'appeal_exists') {
            Swal.fire('تنبيه', 'لقد قمت بتقديم اعتراض على هذا الإنذار مسبقاً.', 'info');
            window.history.replaceState(null, null, window.location.pathname);
        }
    });
</script>
</body>
</html>
