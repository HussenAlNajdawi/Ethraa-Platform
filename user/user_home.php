<?php 
session_start();
require_once '../config/db_connect.php'; // تأكد أن مسار ملف الاتصال صحيح

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // الـ ID الخاص بالمستخدم الحالي

// 2. معالجة الاسم (لأخذ الاسم الأول فقط)
$full_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "مستخدم"; // تأكد من اسم السيشن عند تسجيل الدخول
$name_parts = explode(' ', $full_name);
$first_name = $name_parts[0];


// --- 3. حساب عدد الطلبات المستهلكة اليوم (من الداتا بيس) ---
// الحد اليومي المسموح
// جلب الحد اليومي الخاص بالمستخدم من قاعدة البيانات
$stmt_limit = $conn->prepare("SELECT daily_limit FROM users WHERE user_id = ?");
$stmt_limit->bind_param("i", $user_id);
$stmt_limit->execute();
$res_limit = $stmt_limit->get_result();
$daily_limit = ($res_limit && $res_limit->num_rows > 0) ? (int)$res_limit->fetch_assoc()['daily_limit'] : 3;
$stmt_limit->close();

// استعلام لجلب عدد طلباتك اليوم
// requester_id = أنت
// DATE(created_at) = تاريخ اليوم
$sql_orders = "SELECT COUNT(*) as count FROM requests 
               WHERE requester_id = ? 
               AND DATE(created_at) = CURDATE()";

$stmt = $conn->prepare($sql_orders);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$used_orders = $row['count']; // العدد الحقيقي من الداتا بيس

// حساب المتبقي ونسبة الشريط
$remaining_orders = $daily_limit - $used_orders;
if ($remaining_orders < 0) $remaining_orders = 0;
$progress_percent = ($remaining_orders / $daily_limit) * 100;


// --- 4. حساب عدد الأشخاص الذين ساعدتهم (بصمتك) ---
// provider_id = أنت (مقدم الخدمة)
// status = 'completed' (الخدمة مكتملة)
$sql_helped = "SELECT COUNT(*) as count FROM requests 
               WHERE provider_id = ? 
               AND status = 'completed'"; 

$stmt2 = $conn->prepare($sql_helped);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$row2 = $result2->fetch_assoc();

$helped_people_count = $row2['count']; // الرقم الحقيقي

// --- 5. جلب وقت الفراغ المحفوظ لعرضه في الصناديق ---
$sql_time = "SELECT free_time_start, free_time_end, availability_type FROM users WHERE user_id = ?";
$stmt_time = $conn->prepare($sql_time);
$stmt_time->bind_param("i", $user_id);
$stmt_time->execute();
$res_time = $stmt_time->get_result();
$row_time = $res_time->fetch_assoc();


// القيم الافتراضية (فراغ)
$val_start_h = ""; $val_start_m = "";
$val_end_h = "";   $val_end_m = "";
$av_type = $row_time['availability_type'] ?? 'specific';

// إذا وجدنا وقتاً محفوظاً، نقوم بتفكيكه
if ($row_time['free_time_start'] && $row_time['free_time_end']) {
    // الوقت يأتي بصيغة HH:MM:SS
    $time_s_parts = explode(':', $row_time['free_time_start']);
    $val_start_h = $time_s_parts[0]; 
    $val_start_m = $time_s_parts[1];

    $time_e_parts = explode(':', $row_time['free_time_end']);
    $val_end_h = $time_e_parts[0];
    $val_end_m = $time_e_parts[1];
}
$stmt_time->close();
// إغلاق الاتصالات (اختياري لكن جيد للأداء)
$stmt->close();
$stmt2->close();

// تحديد الترحيب حسب الوقت (توقيت الأردن)
date_default_timezone_set('Asia/Amman');
$hour = date('H');
$greeting = ($hour >= 5 && $hour < 17) ? 'صباح الخير' : 'مساء الخير';

include '../includes/user_header.php'; 
?>
<?php include '../includes/user_navbar.php'; ?>
<div class="top-green-bg"></div>

<div class="container welcome-container">
    
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="welcome-card">
    <div class="circle-decoration c-top-left"></div>
    <div class="circle-decoration c-top-left2"></div>
    <div class="circle-decoration c-bottom-right"></div>
    <div class="circle-decoration c-bottom-right2"></div>
                <h1 class="welcome-title"><?php echo htmlspecialchars($greeting . '، ' . $first_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                
                <p class="imprint-text-card">
                    لقد ساهمت في إثراء حياة <span class="counter-num"><?php echo $helped_people_count; ?></span> أشخاص
                    <img src="../assets/images/handshake.svg" width="24" alt="handshake" class="ms-1 align-middle">
                </p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center g-4 mt-2">
        
        <div class="col-md-5 d-flex flex-column">
            <div class="dashboard-card flex-grow-1">
                <h5 class="dash-title">أوقات التوافر</h5>
                
                <form action="../php/save_time.php" method="POST" id="timeForm" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <!-- مفتاح التبديل -->
                    <div class="availability-switch mb-3">
                        <input type="radio" name="availability_type" id="type_specific" value="specific" <?php if($av_type == 'specific') echo 'checked'; ?> onchange="toggleTimeInputs()">
                        <label for="type_specific">وقت محدد</label>

                        <input type="radio" name="availability_type" id="type_always" value="always" <?php if($av_type == 'always') echo 'checked'; ?> onchange="this.form.submit()">
                        <label for="type_always">متاح دائماً</label>

                        <input type="radio" name="availability_type" id="type_unavailable" value="unavailable" <?php if($av_type == 'unavailable') echo 'checked'; ?> onchange="this.form.submit()">
                        <label for="type_unavailable">غير متاح</label>
                    </div>

                    <!-- حاوية الوقت -->
                    <div id="specificTimeContainer" style="<?php echo ($av_type != 'specific') ? 'display:none;' : ''; ?>">
                        <div class="time-container-mobile d-flex align-items-center justify-content-between gap-2 mt-3">
                            <div class="time-inputs-row d-flex align-items-center gap-2">
                                <span class="time-group-label mb-0">من</span>
                                <div class="time-input-box">
                                    <input type="number" name="start_mm" class="time-field" placeholder="00" min="0" max="59" value="<?php echo $val_start_m; ?>">
                                    <span class="time-colon">:</span>
                                    <input type="number" name="start_hh" class="time-field" placeholder="00" min="0" max="23" value="<?php echo $val_start_h; ?>">
                                </div>
                                <div class="time-arrow mx-1"><i class="fa-solid fa-arrow-left"></i></div>
                                <span class="time-group-label mb-0">إلى</span>
                                <div class="time-input-box">
                                    <input type="number" name="end_mm" class="time-field" placeholder="00" min="0" max="59" value="<?php echo $val_end_m; ?>">
                                    <span class="time-colon">:</span>
                                    <input type="number" name="end_hh" class="time-field" placeholder="00" min="0" max="23" value="<?php echo $val_end_h; ?>">
                                </div>
                            </div>
                            <div class="time-buttons-row d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-light text-danger fw-bold rounded-pill px-3 py-1" onclick="resetTimeInputs()" title="تصفير">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                                <button type="submit" class="btn btn-custom-save rounded-pill fw-bold px-4 py-1">
                                    حفظ
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-5 d-flex flex-column">
            <div class="dashboard-card flex-grow-1">
                <h5 class="dash-title">عدد الطلبات المتبقية</h5>
                <div class="order-card-content">
                    <div style="flex: 1;">
                        <div class="progress-container">
                            <div class="progress-bar-custom" style="width: <?php echo $progress_percent; ?>%;"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between px-1 mt-2">
                            <span class="progress-text">الحد اليومي: <?php echo $daily_limit; ?> طلبات</span>
                            <span class="progress-text fw-bold remaining-text">متبقي: <?php echo $remaining_orders; ?> طلبات</span>
                        </div>
                    </div>

                    <div class="order-icon-box">
                        <img src="../assets/images/order.svg" alt="order">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="cats-grid">
                <?php
                // جلب الخدمات الرئيسية من قاعدة البيانات
                $sql_cats = "SELECT * FROM services WHERE parent_id IS NULL ORDER BY service_id ASC";
                $res_cats = $conn->query($sql_cats);

                // خريطة أيقونات الصفحة الرئيسية
                $home_icons = [
                    1 => 'book.svg',
                    2 => 'health-icon.svg',
                    3 => 'law-icon.svg',
                    4 => 'pc.svg',
                    5 => 'community.svg',
                    6 => 'maintenance.svg'
                ];

                while ($cat = $res_cats->fetch_assoc()):
                    $cat_id = $cat['service_id'];
                    if (!empty($cat['image'])) {
                        $icon = $cat['image'];
                    } else {
                        $icon = isset($home_icons[$cat_id]) ? $home_icons[$cat_id] : 'community.svg';
                    }
                ?>
                <a href="services_list.php?main_id=<?php echo $cat_id; ?>" class="cat-item text-center text-decoration-none">
                    <div class="cat-circle"><img src="../assets/images/<?php echo $icon; ?>" width="35"></div>
                    <div class="cat-label"><?php echo htmlspecialchars($cat['name']); ?></div>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleTimeInputs() {
    const isSpecific = document.getElementById('type_specific').checked;
    const container = document.getElementById('specificTimeContainer');
    
    container.style.display = isSpecific ? 'block' : 'none';
}

function resetTimeInputs() {
    const inputs = ['start_hh', 'start_mm', 'end_hh', 'end_mm'];
    inputs.forEach(name => {
        const input = document.querySelector(`input[name="${name}"]`);
        if (input) input.value = '00';
    });
}

// مراقبة التغييرات في حقول الوقت لإظهار زر الحفظ
document.addEventListener('DOMContentLoaded', function() {
    const timeInputs = document.querySelectorAll('.time-field');
    const initialValues = {};

    // حفظ القيم الأولية عند تحميل الصفحة
    timeInputs.forEach(input => {
        initialValues[input.name] = input.value;
        
        // الانتقال العكسي عند الضغط على Backspace والحقل فارغ
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value.length === 0) {
                const index = Array.from(timeInputs).indexOf(this);
                if (index > 0) {
                    timeInputs[index - 1].focus();
                }
            }
        });

        // إضافة مستمع للحدث عند الكتابة
        input.addEventListener('input', function() {
            // التحقق من صحة الأرقام (Validation)
            if (this.value !== '') {
                const max = parseInt(this.getAttribute('max'));
                const min = parseInt(this.getAttribute('min'));
                if (parseInt(this.value) > max) this.value = max;
                if (parseInt(this.value) < min) this.value = min;
            }

            // الانتقال التلقائي للحقل التالي عند كتابة رقمين
            if (this.value.length >= 2) {
                const index = Array.from(timeInputs).indexOf(this);
                if (index > -1 && index < timeInputs.length - 1) {
                    timeInputs[index + 1].focus();
                }
            }
        });

        // رسالة نجاح حفظ الوقت
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'time_saved') {
            Swal.fire({
                icon: 'success',
                title: 'تم الحفظ',
                text: 'تم تحديث أوقات التوافر الخاصة بك بنجاح!',
                timer: 2500,
                showConfirmButton: false
            });
            window.history.replaceState(null, null, window.location.pathname);
        }
    });
});
</script>

<!-- Modal تقديم خدمة (للأعضاء الجدد) -->
<div class="modal fade" id="newServiceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px;">
      <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
        <div style="width: 80px; height: 80px; background-color: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
            <i class="fa-solid fa-hand-holding-heart" style="color: #2e7d32; font-size: 40px;"></i>
        </div>
        <h4 class="modal-title fw-bold text-success">أهلاً بك في إثراء!</h4>
      </div>
      <div class="modal-body text-center pt-2">
        <p class="text-muted fw-bold mb-4" style="font-size: 1.1rem;">
            هل تود الانضمام لقائمة مقدمي الخدمات ومساعدة الآخرين الآن؟<br>
            <span class="small text-secondary fw-normal">يمكنك تحديد مجالك وتخصصك ليتمكن الآخرون من الوصول إليك.</span>
        </p>
        
        <div class="d-flex justify-content-center gap-3">
            <button type="button" class="btn btn-light fw-bold px-4 py-2" style="border-radius: 12px; background-color: #f1f3f5; min-width: 120px;" data-bs-dismiss="modal">لاحقاً</button>
            <a href="my_account.php?focus=service" class="btn btn-success fw-bold px-4 py-2" style="border-radius: 12px; min-width: 120px; background-color: #66BF26; border: none;">نعم، أريد ذلك</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// إظهار مودال تقديم الخدمة إذا كان مطلوباً
<?php if (isset($_SESSION['show_service_prompt']) && $_SESSION['show_service_prompt'] === true): ?>
    document.addEventListener('DOMContentLoaded', function() {
        var newServiceModal = new bootstrap.Modal(document.getElementById('newServiceModal'));
        newServiceModal.show();
    });
    <?php unset($_SESSION['show_service_prompt']); // حذفه حتى لا يظهر مرة أخرى عند التحديث ?>
<?php endif; ?>
</script>
</body>
</html>