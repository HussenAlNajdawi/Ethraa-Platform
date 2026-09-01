<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- CSRF Token Protection ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// =========================================================
// 1. الإنهاء التلقائي للخدمة (Auto-Complete Logic)
// إذا مر 24 ساعة على تأكيد طرف واحد على الأقل، يتم اعتبار الخدمة مكتملة
// =========================================================
try {
    // 1. جلب الطلبات التي سيتم إكمالها تلقائياً
    $to_complete_res = $conn->query("SELECT request_id, provider_id, requester_id, points_cost FROM requests WHERE status = 'accepted' AND (provider_confirmed = 1 OR requester_confirmed = 1) AND confirmed_at < (NOW() - INTERVAL 24 HOUR)");
    
    if ($to_complete_res && $to_complete_res->num_rows > 0) {
        require_once '../php/wallet_system.php';
        while ($req = $to_complete_res->fetch_assoc()) {
            $req_id = $req['request_id'];
            $prov_id = $req['provider_id'];
            $req_user_id = $req['requester_id'];
            $points = $req['points_cost'];

            // 2. تحديث الحالة أولاً بشكل ذري (Atomic State Transition) لمنع حالات التسابق ومضاعفة النقاط
            $up_req = $conn->prepare("UPDATE requests SET status = 'completed' WHERE request_id = ? AND status = 'accepted'");
            $up_req->bind_param("i", $req_id);
            $up_req->execute();
            $was_completed = ($up_req->affected_rows > 0);
            $up_req->close();

            // 3. فقط إذا نجح تعديل الحالة (أي لم يقم طلب آخر بتعديلها مسبقاً)، نقوم بتحويل النقاط
            if ($was_completed) {
                $up_points = $conn->prepare("UPDATE users SET points = points + ? WHERE user_id = ?");
                $up_points->bind_param("ii", $points, $prov_id);
                $up_points->execute();
                $up_points->close();
                
                logPointTransaction($conn, $prov_id, $points, 'earn', 'اكتساب مقابل خدمة (تلقائياً)', $req_id);

                // فحص مكافأة الإحالة إذا كان هذا أول طلب مكتمل للمستخدم
                checkAndRewardReferral($conn, $prov_id);
                checkAndRewardReferral($conn, $req_user_id);
            }
        }
    }
} catch (Exception $e) {
    // تجاهل الخطأ
}

// =========================================================
// 3. جلب البيانات (SELECT Queries)
// =========================================================
// ملاحظة: قمنا بإزالة أي شرط "WHERE status = ..." لكي تظهر كل الطلبات

// الطلبات الواردة (أنا مقدم الخدمة)
$sql_incoming = "SELECT r.*, u.first_name, u.last_name, u.phone, u.hide_phone, s.name as service_name, p.name as parent_service_name 
                 FROM requests r 
                 LEFT JOIN users u ON r.requester_id = u.user_id 
                 LEFT JOIN services s ON r.service_id = s.service_id
                 LEFT JOIN services p ON s.parent_id = p.service_id
                 WHERE r.provider_id = ? 
                 ORDER BY r.created_at DESC";

// الطلبات الصادرة (أنا طالب الخدمة)
$sql_outgoing = "SELECT r.*, u.first_name, u.last_name, u.phone, u.hide_phone, s.name as service_name, p.name as parent_service_name,
                 (SELECT COUNT(*) FROM reviews WHERE request_id = r.request_id) as is_rated 
                 FROM requests r 
                 LEFT JOIN users u ON r.provider_id = u.user_id 
                 LEFT JOIN services s ON r.service_id = s.service_id
                 LEFT JOIN services p ON s.parent_id = p.service_id
                 WHERE r.requester_id = ? 
                 ORDER BY r.created_at DESC";

// تنفيذ الاستعلامات
$stmt = $conn->prepare($sql_incoming);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$incoming_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare($sql_outgoing);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$outgoing_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'الطلبات - إثراء';
$page_css = '
    <link rel="stylesheet" href="../assets/css/account_style.css?v=' . filemtime(__DIR__ . '/../assets/css/account_style.css') . '">
    <link rel="stylesheet" href="../assets/css/requests.css?v=' . filemtime(__DIR__ . '/../assets/css/requests.css') . '">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
';
include '../includes/user_header.php';
?>

<?php include '../includes/user_navbar.php'; ?>

<div class="container">
    <div class="account-container" style="min-height: 80vh;">
        
        <div class="d-flex flex-column align-items-center">
            <h1 class="page-title">إدارة الطلبات</h1>

            <div class="d-flex justify-content-center mb-5">
                <ul class="nav nav-pills p-0" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pills-incoming-tab" data-bs-toggle="pill" data-bs-target="#pills-incoming" type="button">
                            <i class="fa-solid fa-inbox ms-2"></i>الطلبات الواردة
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-outgoing-tab" data-bs-toggle="pill" data-bs-target="#pills-outgoing" type="button">
                            <i class="fa-solid fa-paper-plane ms-2"></i>الطلبات الصادرة
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="tab-content" id="pills-tabContent">
            
            <div class="tab-pane fade show active" id="pills-incoming">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <?php if(count($incoming_requests) > 0): ?>
                            <?php foreach($incoming_requests as $row): 
                                // تحديد النصوص والألوان بناءً على الحالة
                                $status = strtolower(trim($row['status']));
                                $status_text = '';
                                $badge_class = '';
                                
                                switch($status) {
                                    case 'pending': 
                                        $status_text = 'قيد الانتظار'; $badge_class = 'badge-pending'; $card_class = 'status-pending'; 
                                        break;
                                    case 'accepted': 
                                        $status_text = 'قيد الإجراء'; $badge_class = 'badge-accepted'; $card_class = 'status-accepted'; 
                                        break;
                                    case 'rejected': 
                                        $status_text = 'مرفوض'; $badge_class = 'badge-rejected'; $card_class = 'status-rejected'; 
                                        break;
                                    case 'completed': 
                                        $status_text = 'مكتمل'; $badge_class = 'badge-completed'; $card_class = 'status-completed'; 
                                        break;
                                    default:
                                        // إظهار نص بديل في حال كانت الحالة فارغة أو غير معروفة
                                        $status_text = !empty($row['status']) ? $row['status'] : 'غير محدد'; 
                                        $badge_class = 'bg-secondary text-white'; 
                                        $card_class = 'border border-secondary';
                                }

                                // تجهيز اسم الخدمة (رئيسي : فرعي)
                                $service_display = htmlspecialchars($row['service_name']);
                                if (!empty($row['parent_service_name'])) {
                                    $service_display = htmlspecialchars($row['parent_service_name']) . ' : ' . $service_display;
                                }
                            ?>
                                <div id="req-<?php echo $row['request_id']; ?>" class="request-card <?php echo $card_class; ?>">
                                    <div class="d-flex align-items-start">
                                        <?php $char = mb_substr($row['first_name'], 0, 1, "UTF-8"); ?>
                                        <div class="request-icon-box text-white shadow-sm" style="background: linear-gradient(45deg, #104496, #3498db); font-size: 1.4rem; font-weight: 800;">
                                            <?php echo htmlspecialchars($char); ?>
                                        </div>
                                        
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div>
                                                    <h6 class="fw-bold mb-0" style="color: #001A75; font-size: 1rem;">
                                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                    </h6>
                                                </div>
                                                <span class="status-badge <?php echo $badge_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="text-muted small mb-2">
                                                <i class="fa-solid fa-briefcase ms-1"></i> الخدمة: <strong><?php echo $service_display; ?></strong>
                                                <span class="mx-2">|</span>
                                                <i class="fa-regular fa-clock ms-1"></i> <?php echo date('Y/m/d h:i A', strtotime($row['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if(!empty($row['details'])): 
                                        $details_len = mb_strlen($row['details'], 'UTF-8');
                                        $is_long = $details_len > 100;
                                    ?>
                                        <div class="request-details-wrapper">
                                            <div class="request-details-box <?php echo $is_long ? 'collapsed' : ''; ?>" id="details-box-<?php echo $row['request_id']; ?>">
                                                <i class="fa-solid fa-circle-info ms-1 text-primary"></i> <?php echo htmlspecialchars($row['details']); ?>
                                            </div>
                                            <?php if($is_long): ?>
                                                <button type="button" class="btn-read-more" onclick="toggleDetails(<?php echo $row['request_id']; ?>)" id="btn-read-more-<?php echo $row['request_id']; ?>">عرض المزيد</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- أزرار الإجراءات -->
                                    <div class="request-actions">
                                    <?php if($status == 'pending'): ?>
                                        <form method="POST" action="../php/request_operations.php" onsubmit="confirmRequestAction(event, 'accept')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="accept_request">
                                            <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                            <button type="submit" class="btn-action-sm btn-accept"><i class="fa-solid fa-check"></i> قبول</button>
                                        </form>
                                        <form method="POST" action="../php/request_operations.php" onsubmit="confirmRequestAction(event, 'reject')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="reject_request">
                                            <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                            <button type="submit" class="btn-action-sm btn-reject"><i class="fa-solid fa-xmark"></i> رفض</button>
                                        </form>

                                    <?php elseif($status == 'accepted'): ?>
                                        <?php if($row['provider_confirmed'] == 1): ?>
                                            <span class="btn-action-sm btn-waiting">
                                                <i class="fa-solid fa-hourglass-half"></i> بانتظار الطرف الآخر
                                            </span>
                                        <?php else: ?>
                                            <form method="POST" action="../php/request_operations.php" onsubmit="confirmRequestAction(event, 'finish')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="finish_service">
                                                <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                                <input type="hidden" name="role" value="provider">
                                                <button type="submit" class="btn-action-sm btn-finish"><i class="fa-solid fa-flag-checkered"></i> إنهاء الخدمة</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="chat.php?request_id=<?php echo $row['request_id']; ?>" class="btn-action-sm btn-chat" title="دردشة"><i class="fa-solid fa-comments"></i> دردشة</a>
                                        <?php if(!$row['hide_phone']): ?>
                                            <a href="https://wa.me/962<?php echo $row['phone']; ?>" target="_blank" class="btn-action-sm btn-whatsapp" title="واتساب"><i class="fa-brands fa-whatsapp"></i> واتساب</a>
                                            <a href="tel:+962<?php echo $row['phone']; ?>" class="btn-action-sm btn-call" title="اتصال"><i class="fa-solid fa-phone"></i> اتصال</a>
                                        <?php endif; ?>
                                        
                                    <?php elseif($status == 'completed' || $status == 'rejected'): ?>
                                        <button type="button" class="btn-action-sm btn-report" onclick="openReportModal(<?php echo $row['request_id']; ?>)">
                                            <i class="fa-solid fa-triangle-exclamation"></i> إبلاغ
                                        </button>
                                        <?php if($status == 'completed'): ?>
                                            <a href="chat.php?request_id=<?php echo $row['request_id']; ?>" class="btn-action-sm btn-chat" title="دردشة"><i class="fa-solid fa-comments"></i> دردشة</a>
                                            <?php if(!$row['hide_phone']): ?>
                                                <a href="https://wa.me/962<?php echo $row['phone']; ?>" target="_blank" class="btn-action-sm btn-whatsapp" title="واتساب"><i class="fa-brands fa-whatsapp"></i> واتساب</a>
                                                <a href="tel:+962<?php echo $row['phone']; ?>" class="btn-action-sm btn-call" title="اتصال"><i class="fa-solid fa-phone"></i> اتصال</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fa-solid fa-inbox fa-4x text-muted mb-3" style="opacity: 0.2;"></i>
                                <h5 class="text-muted">لا توجد طلبات واردة حالياً</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pills-outgoing">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <?php if(count($outgoing_requests) > 0): ?>
                            <?php foreach($outgoing_requests as $row): 
                                $status = strtolower(trim($row['status']));
                                $status_text = '';
                                $badge_class = '';
                                
                                switch($status) {
                                    case 'pending': 
                                        $status_text = 'قيد الانتظار'; $badge_class = 'badge-pending'; $card_class = 'status-pending'; 
                                        break;
                                    case 'accepted': 
                                        $status_text = 'قيد الإجراء'; $badge_class = 'badge-accepted'; $card_class = 'status-accepted'; 
                                        break;
                                    case 'rejected': 
                                        $status_text = 'مرفوض'; $badge_class = 'badge-rejected'; $card_class = 'status-rejected'; 
                                        break;
                                    case 'completed': 
                                        $status_text = 'مكتمل'; $badge_class = 'badge-completed'; $card_class = 'status-completed'; 
                                        break;
                                    default:
                                        $status_text = !empty($row['status']) ? $row['status'] : 'غير محدد'; 
                                        $badge_class = 'bg-secondary text-white'; 
                                        $card_class = 'border border-secondary';
                                }

                                // تجهيز اسم الخدمة (رئيسي : فرعي)
                                $service_display = htmlspecialchars($row['service_name']);
                                if (!empty($row['parent_service_name'])) {
                                    $service_display = htmlspecialchars($row['parent_service_name']) . ' : ' . $service_display;
                                }
                            ?>
                                <div id="req-<?php echo $row['request_id']; ?>" class="request-card <?php echo $card_class; ?>">
                                    <div class="d-flex align-items-start">
                                        <?php $char = mb_substr($row['first_name'], 0, 1, "UTF-8"); ?>
                                        <div class="request-icon-box text-white shadow-sm" style="background: linear-gradient(45deg, #66BF26, #8fd954); font-size: 1.4rem; font-weight: 800;">
                                            <?php echo htmlspecialchars($char); ?>
                                        </div>
                                        
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div>
                                                    <h6 class="fw-bold mb-0" style="color: #001A75; font-size: 1rem;">
                                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                    </h6>
                                                </div>
                                                <span class="status-badge <?php echo $badge_class; ?>">
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="text-muted small mb-2">
                                                <i class="fa-solid fa-briefcase ms-1"></i> الخدمة: <strong><?php echo $service_display; ?></strong>
                                                <span class="mx-2">|</span>
                                                <i class="fa-regular fa-clock ms-1"></i> <?php echo date('Y/m/d h:i A', strtotime($row['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if(!empty($row['details']) || $status == 'pending'): 
                                        $details_text = !empty($row['details']) ? $row['details'] : 'لا توجد تفاصيل';
                                        $details_len = mb_strlen($details_text, 'UTF-8');
                                        $is_long = $details_len > 100;
                                    ?>
                                        <div class="request-details-wrapper">
                                            <div class="request-details-box d-flex justify-content-between align-items-start <?php echo $is_long ? 'collapsed' : ''; ?>" id="details-box-<?php echo $row['request_id']; ?>">
                                                <div>
                                                    <i class="fa-solid fa-circle-info ms-1 text-primary"></i> 
                                                    <span id="details-text-<?php echo $row['request_id']; ?>"><?php echo htmlspecialchars($details_text); ?></span>
                                                </div>
                                                <?php if($status == 'pending'): ?>
                                                    <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-decoration-none flex-shrink-0" onclick="openEditModal(<?php echo $row['request_id']; ?>)" title="تعديل الوصف">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if($is_long): ?>
                                                <button type="button" class="btn-read-more" onclick="toggleDetails(<?php echo $row['request_id']; ?>)" id="btn-read-more-<?php echo $row['request_id']; ?>">عرض المزيد</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- أزرار الإجراءات -->
                                    <div class="request-actions">
                                    <?php if($status == 'pending'): ?>
                                        <form method="POST" action="../php/request_operations.php" onsubmit="confirmRequestAction(event, 'cancel')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="cancel_request">
                                            <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                            <button type="submit" class="btn-action-sm btn-cancel"><i class="fa-solid fa-ban"></i> إلغاء</button>
                                        </form>
                                        <button type="button" class="btn-action-sm btn-report" onclick="openReportModal(<?php echo $row['request_id']; ?>)">
                                            <i class="fa-solid fa-triangle-exclamation"></i> إبلاغ
                                        </button>

                                    <?php elseif($status == 'accepted'): ?>
                                        <?php if($row['requester_confirmed'] == 1): ?>
                                            <span class="btn-action-sm btn-waiting">
                                                <i class="fa-solid fa-hourglass-half"></i> بانتظار الطرف الآخر
                                            </span>
                                        <?php else: ?>
                                            <form method="POST" action="../php/request_operations.php" onsubmit="confirmRequestAction(event, 'finish')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="finish_service">
                                                <input type="hidden" name="request_id" value="<?php echo $row['request_id']; ?>">
                                                <input type="hidden" name="role" value="requester">
                                                <button type="submit" class="btn-action-sm btn-finish"><i class="fa-solid fa-flag-checkered"></i> إنهاء الخدمة</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="chat.php?request_id=<?php echo $row['request_id']; ?>" class="btn-action-sm btn-chat" title="دردشة"><i class="fa-solid fa-comments"></i> دردشة</a>
                                        <?php if(!$row['hide_phone']): ?>
                                            <a href="https://wa.me/962<?php echo $row['phone']; ?>" target="_blank" class="btn-action-sm btn-whatsapp" title="واتساب"><i class="fa-brands fa-whatsapp"></i> واتساب</a>
                                            <a href="tel:+962<?php echo $row['phone']; ?>" class="btn-action-sm btn-call" title="اتصال"><i class="fa-solid fa-phone"></i> اتصال</a>
                                        <?php endif; ?>

                                    <?php elseif($status == 'completed'): ?>
                                        <?php if(isset($row['is_rated']) && $row['is_rated'] > 0): ?>
                                            <button type="button" class="btn-action-sm btn-rated" disabled><i class="fa-solid fa-check"></i> تم التقييم</button>
                                        <?php else: ?>
                                            <button type="button" class="btn-action-sm btn-rate" onclick="openRateModal(<?php echo $row['request_id']; ?>, <?php echo $row['provider_id']; ?>)">
                                                <i class="fa-regular fa-star"></i> تقييم
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn-action-sm btn-report" onclick="openReportModal(<?php echo $row['request_id']; ?>)">
                                            <i class="fa-solid fa-triangle-exclamation"></i> إبلاغ
                                        </button>
                                        <a href="chat.php?request_id=<?php echo $row['request_id']; ?>" class="btn-action-sm btn-chat" title="دردشة"><i class="fa-solid fa-comments"></i> دردشة</a>
                                        <?php if(!$row['hide_phone']): ?>
                                            <a href="https://wa.me/962<?php echo $row['phone']; ?>" target="_blank" class="btn-action-sm btn-whatsapp" title="واتساب"><i class="fa-brands fa-whatsapp"></i> واتساب</a>
                                            <a href="tel:+962<?php echo $row['phone']; ?>" class="btn-action-sm btn-call" title="اتصال"><i class="fa-solid fa-phone"></i> اتصال</a>
                                        <?php endif; ?>

                                    <?php elseif($status == 'rejected'): ?>
                                        <button type="button" class="btn-action-sm btn-report" onclick="openReportModal(<?php echo $row['request_id']; ?>)">
                                            <i class="fa-solid fa-triangle-exclamation"></i> إبلاغ
                                        </button>
                                    <?php endif; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fa-solid fa-paper-plane fa-4x text-muted mb-3" style="opacity: 0.2;"></i>
                                <h5 class="text-muted">لم ترسل أي طلبات بعد</h5>
                                <p class="text-muted small">ابدأ الآن بتصفح مقدمي الخدمات المتاحين.</p>
                                <a href="services_list.php" class="btn btn-primary mt-2 rounded-pill px-4 fw-bold" style="background-color: #104496; border: none; box-shadow: 0 4px 10px rgba(16, 68, 150, 0.2);">استكشف الخدمات</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Modal الإبلاغ -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px;">
      <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
        <div style="width: 70px; height: 70px; background-color: #fff5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #dc3545; font-size: 32px;"></i>
        </div>
        <h4 class="modal-title fw-bold text-danger">إبلاغ عن طلب</h4>
      </div>
      <div class="modal-body pt-2">
        <form method="POST" action="../php/request_operations.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="report_request">
            <input type="hidden" name="request_id" id="reportRequestId">
            <div class="mb-3">
                <input type="text" name="title" class="form-control bg-light border-0 mb-3" placeholder="عنوان البلاغ" required style="border-radius: 15px; padding: 15px;">
                <textarea name="content" class="form-control bg-light border-0" rows="4" placeholder="نص البلاغ..." required style="border-radius: 15px; padding: 15px;"></textarea>
            </div>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-light fw-bold flex-grow-1" style="border-radius: 12px;" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-danger fw-bold flex-grow-1" style="border-radius: 12px;">إرسال البلاغ</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal التقييم -->
<div class="modal fade" id="rateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px;">
      <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
        <h4 class="modal-title fw-bold text-warning">تقييم الخدمة</h4>
      </div>
      <div class="modal-body pt-2 text-center">
        <form method="POST" action="../php/request_operations.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="submit_review">
            <input type="hidden" name="request_id" id="rateRequestId">
            <input type="hidden" name="provider_id" id="rateProviderId">
            
            <div class="star-rating mb-3">
                <input type="radio" id="star5" name="rating" value="5" required /><label for="star5" title="ممتاز"></label>
                <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="جيد جداً"></label>
                <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="جيد"></label>
                <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="مقبول"></label>
                <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="سيء"></label>
            </div>
            
            <div class="mb-3">
                <textarea name="comment" class="form-control bg-light border-0" rows="3" placeholder="اكتب تعليقك هنا..." style="border-radius: 15px; padding: 15px;"></textarea>
            </div>
            
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-light fw-bold flex-grow-1" style="border-radius: 12px;" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-warning text-white fw-bold flex-grow-1" style="border-radius: 12px;">إرسال التقييم</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal تعديل التفاصيل -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px;">
      <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
        <h4 class="modal-title fw-bold text-primary">تعديل تفاصيل الطلب</h4>
      </div>
      <div class="modal-body pt-2">
        <form method="POST" action="../php/request_operations.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="update_details">
            <input type="hidden" name="request_id" id="editRequestId">
            <div class="mb-3">
                <textarea name="details" id="editRequestDetails" class="form-control bg-light border-0" rows="4" required style="border-radius: 15px; padding: 15px;"></textarea>
            </div>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-light fw-bold flex-grow-1" style="border-radius: 12px;" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary fw-bold flex-grow-1" style="border-radius: 12px;">حفظ التعديلات</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal تجاوز الحد اليومي -->
<div class="modal fade" id="limitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center p-4" style="border-radius: 25px; border: none;">
      <div class="modal-body">
        <div style="width: 80px; height: 80px; background-color: #fff3cd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #ffc107; font-size: 40px;"></i>
        </div>
        <h4 class="fw-bold text-warning mb-3">عذراً، لقد وصلت للحد الأقصى!</h4>
        <p class="text-muted mb-4" style="font-size: 1.1rem;">
            لقد استهلكت رصيدك اليومي من الطلبات (3 طلبات).<br>
            يرجى الانتظار حتى يوم غد لتقديم طلبات جديدة.
        </p>
        <button type="button" class="btn btn-warning text-white w-100 py-2 fw-bold" style="border-radius: 12px;" data-bs-dismiss="modal">حسناً، فهمت</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. تفعيل التبويب بناءً على الرابط (لزر الإلغاء الذي يحذف الكرت)
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('active_tab');
    if (activeTab) {
        const tabBtn = document.querySelector(`button[data-bs-target="#${activeTab}"]`);
        if (tabBtn) {
            const tab = new bootstrap.Tab(tabBtn);
            tab.show();
        }
    }

    // 2. التمرير إلى الكرت المحدد (لباقي الأزرار)
    if (window.location.hash) {
        const targetId = window.location.hash.substring(1); // إزالة #
        const targetElement = document.getElementById(targetId);
        
        if (targetElement) {
            // معرفة التبويب الذي يحتوي العنصر وفتحه تلقائياً
            const tabPane = targetElement.closest('.tab-pane');
            if (tabPane) {
                const tabId = tabPane.getAttribute('id');
                const tabBtn = document.querySelector(`button[data-bs-target="#${tabId}"]`);
                if (tabBtn) {
                    const tab = new bootstrap.Tab(tabBtn);
                    tab.show();
                }
            }

            // التمرير السلس للعنصر
            setTimeout(() => {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // وميض بسيط لتمييز العنصر
                targetElement.style.transition = "background-color 0.5s";
                const originalBg = targetElement.style.backgroundColor;
                targetElement.style.backgroundColor = "#fff9d6"; // لون أصفر فاتح جداً
                setTimeout(() => { targetElement.style.backgroundColor = originalBg; }, 1500);
            }, 100);
        }
    }
    
    if (urlParams.get('msg') === 'booking_success') {
        Swal.fire({
            icon: 'success',
            title: 'تم إرسال الطلب بنجاح!',
            text: 'تم خصم نقطة واحدة من رصيدك. يرجى انتظار رد مقدم الخدمة.',
        });
        window.history.replaceState(null, null, window.location.pathname + window.location.hash);
    }

    // إظهار مودال الحد اليومي إذا كان الرابط يحتوي على error=daily_limit
    if (urlParams.get('error') === 'daily_limit') {
        var limitModal = new bootstrap.Modal(document.getElementById('limitModal'));
        limitModal.show();
    }

    // إظهار رسالة نجاح إرسال البلاغ
    if (urlParams.get('msg') === 'report_sent') {
        Swal.fire({
            icon: 'success',
            title: 'تم إرسال البلاغ',
            text: 'شكراً لك، تم استلام بلاغك وسيتم مراجعته من قبل الإدارة قريباً.',
        });
    }

    if (urlParams.get('msg') === 'review_submitted') {
        Swal.fire('تم التقييم', 'شكراً لك على تقييمك!', 'success');
    }

    if (urlParams.get('error') === 'rating_required') {
        Swal.fire('تنبيه', 'يرجى اختيار عدد النجوم للتقييم.', 'warning');
    }

    if (urlParams.get('error') === 'db_error') {
        let details = urlParams.get('details') || '';
        Swal.fire('خطأ في قاعدة البيانات', 'لم يتم حفظ التقييم. التفاصيل: ' + details, 'error');
    }

    if (urlParams.get('error') === 'invalid_request') {
        Swal.fire('خطأ', 'الطلب غير موجود أو لا تملك صلاحية تقييمه.', 'error');
    }

    if (urlParams.get('error') === 'already_reviewed') {
        Swal.fire('تنبيه', 'لقد قمت بتقييم هذا الطلب مسبقاً.', 'info');
    }
});

function confirmRequestAction(e, actionType) {
    e.preventDefault();
    const form = e.target;
    let title = '', text = '', confirmBtnText = '', confirmColor = '';

    switch(actionType) {
        case 'accept':
            title = 'تأكيد القبول'; text = 'هل أنت متأكد من قبولك لتقديم هذه الخدمة؟'; confirmBtnText = 'نعم، أقبل'; confirmColor = '#198754'; break;
        case 'reject':
            title = 'تأكيد الرفض'; text = 'هل أنت متأكد من رفضك لهذا الطلب؟ سيتم استرجاع نقاط الطرف الآخر.'; confirmBtnText = 'نعم، ارفض'; confirmColor = '#dc3545'; break;
        case 'finish':
            title = 'إنهاء الخدمة'; text = 'هل تؤكد أن الخدمة قد تم تقديمها واكتملت بالكامل؟'; confirmBtnText = 'نعم، اكتملت'; confirmColor = '#198754'; break;
        case 'cancel':
            title = 'إلغاء الطلب'; text = 'هل أنت متأكد من إلغاء طلبك؟ سيتم استرجاع نقاطك إلى محفظتك.'; confirmBtnText = 'نعم، ألغِ الطلب'; confirmColor = '#b78900'; break;
    }

    if (title !== '') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#f1f3f5',
            confirmButtonText: confirmBtnText,
            cancelButtonText: 'تراجع',
            customClass: { cancelButton: 'text-dark fw-bold' }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    } else {
        form.submit();
    }
}

function openReportModal(id) {
    document.getElementById('reportRequestId').value = id;
    var myModal = new bootstrap.Modal(document.getElementById('reportModal'));
    myModal.show();
}

function openRateModal(requestId, providerId) {
    document.getElementById('rateRequestId').value = requestId;
    document.getElementById('rateProviderId').value = providerId;
    new bootstrap.Modal(document.getElementById('rateModal')).show();
}

function openEditModal(id) {
    document.getElementById('editRequestId').value = id;
    var textSpan = document.getElementById('details-text-' + id);
    var currentText = textSpan ? textSpan.innerText.trim() : '';
    if(currentText === 'لا توجد تفاصيل') currentText = '';
    document.getElementById('editRequestDetails').value = currentText;
    var myModal = new bootstrap.Modal(document.getElementById('editModal'));
    myModal.show();
}

function toggleDetails(id) {
    var box = document.getElementById('details-box-' + id);
    var btn = document.getElementById('btn-read-more-' + id);
    
    if (box.classList.contains('collapsed')) {
        box.classList.remove('collapsed');
        box.classList.add('expanded');
        btn.innerText = 'عرض أقل';
    } else {
        box.classList.remove('expanded');
        box.classList.add('collapsed');
        btn.innerText = 'عرض المزيد';
    }
}
</script>
</body>
</html>