<?php
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT u.*, s.name as sub_service_name, p.name as main_service_name, p.service_id as main_service_id FROM users u LEFT JOIN services s ON u.service_id = s.service_id LEFT JOIN services p ON s.parent_id = p.service_id WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// حساب تقييم المستخدم
$sql_rating = "SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM reviews WHERE provider_id = ?";
$stmt_rating = $conn->prepare($sql_rating);
$stmt_rating->bind_param("i", $user_id);
$stmt_rating->execute();
$res_rating = $stmt_rating->get_result();
$row_rating = $res_rating->fetch_assoc();

$my_rating = $row_rating['avg_rating'] ? round($row_rating['avg_rating'], 1) : 0;
$my_rating_count = $row_rating['count'];
$stmt_rating->close();

// تفكيك وقت الفراغ للعرض
$start_h = $start_m = $end_h = $end_m = "";
if ($user['free_time_start']) {
    list($start_h, $start_m) = explode(':', date('H:i', strtotime($user['free_time_start'])));
}
if ($user['free_time_end']) {
    list($end_h, $end_m) = explode(':', date('H:i', strtotime($user['free_time_end'])));
}
$av_type = $user['availability_type'] ?? 'specific';

// جلب جميع الخدمات للقوائم المنسدلة
$services_res = $conn->query("SELECT * FROM services ORDER BY name ASC");
$all_services = [];
while($row = $services_res->fetch_assoc()) {
    $all_services[] = $row;
}

$page_title = 'حسابي - إثراء';
$page_css = '
    <link rel="stylesheet" href="../assets/css/account_style.css?v=' . filemtime(__DIR__ . '/../assets/css/account_style.css') . '">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
';
include '../includes/user_header.php'; 

?>

<?php include '../includes/user_navbar.php'; ?>

<div class="container">
    <div class="account-container">
        
        <a href="logout.php" class="back-icon" title="تسجيل الخروج" onclick="confirmLogout(event, this.href)">
            <img src="../assets/images/logout.svg" width="40" style="transform: scaleX(-1);">
        </a>

        <h1 class="page-title">حسابي</h1>

            <div class="row g-4">
                
                <div class="col-md-6">
                    
                    <!-- نموذج المعلومات الشخصية -->
                    <form action="../php/update_account.php" method="POST" onsubmit="return validateAge()">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_personal">
                    <div class="info-card mb-4">
                        <div class="card-header-strip header-blue">
                            <div class="icon-circle">
                                <img src="../assets/images/user_b.svg" alt="user">
                            </div>
                            <span class="card-title-text">معلوماتي الشخصية</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="form-row-custom">
                                <label class="custom-label">الاسم كامل:</label>
                                <input type="text" name="full_name" class="custom-input" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" required>
                            </div>
                            <div class="form-row-custom">
                                <label class="custom-label">تاريخ الميلاد:</label>
                                <input type="date" name="birth_date" class="custom-input" value="<?php echo htmlspecialchars($user['birth_date']); ?>" required>
                            </div>
                            <div class="form-row-custom">
                                <label class="custom-label">الجنس:</label>
                                <div class="radio-container">
                                    <div class="d-flex align-items-center gap-1">
                                        <label>ذكر</label> <input type="radio" name="gender" value="male" <?php if($user['gender'] == 'male') echo 'checked'; ?>>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <label>انثى</label> <input type="radio" name="gender" value="female" <?php if($user['gender'] == 'female') echo 'checked'; ?>>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn-action-base btn-save-custom">حفظ التعديلات</button>
                        </div>
                    </div>
                    </form>

                    <!-- نموذج الخدمات -->
                    <form action="../php/update_account.php" method="POST" onsubmit="return validateServiceForm()" id="serviceFormSection">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_service">
                    <div class="info-card">
                        <div class="card-header-strip header-green">
                            <div class="icon-circle">
                                <img src="../assets/images/services_g.svg" alt="services">
                            </div>
                            <span class="card-title-text">الخدمة التي اقدمها</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="form-row-custom">
                                <label class="custom-label">المجال:</label> 
                                <select id="main_service" class="custom-input" onchange="filterSubServices()" required>
                                    <option value="">اختر المجال</option>
                                    <?php foreach($all_services as $s): ?>
                                        <?php if(empty($s['parent_id'])): ?>
                                            <option value="<?php echo $s['service_id']; ?>" <?php if($s['service_id'] == $user['main_service_id']) echo 'selected'; ?>><?php echo htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-row-custom">
                                <label class="custom-label">التخصص:</label>
                                <select name="sub_service_id" id="sub_service" class="custom-input" required></select>
                            </div>
                            
                            <div class="form-row-custom" style="align-items: flex-start;">
                                <label class="custom-label" style="padding-top: 12px;">أوقات التوافر:</label>
                                <div class="availability-wrapper">
                                    <!-- مفتاح التبديل -->
                                    <div class="availability-switch mb-3">
                                        <input type="radio" name="availability_type" id="acc_specific" value="specific" <?php if($av_type == 'specific') echo 'checked'; ?> onchange="toggleAccountTime()">
                                        <label for="acc_specific">وقت محدد</label>

                                        <input type="radio" name="availability_type" id="acc_always" value="always" <?php if($av_type == 'always') echo 'checked'; ?> onchange="toggleAccountTime()">
                                        <label for="acc_always">متاح دائماً</label>

                                        <input type="radio" name="availability_type" id="acc_unavailable" value="unavailable" <?php if($av_type == 'unavailable') echo 'checked'; ?> onchange="toggleAccountTime()">
                                        <label for="acc_unavailable">غير متاح</label>
                                    </div>

                                    <!-- حاوية الوقت -->
                                    <div id="accTimeBox" style="<?php echo ($av_type != 'specific') ? 'display:none;' : ''; ?> width: 100%;">
                                        <div class="d-flex w-100 mt-2">
                                            <div class="d-flex align-items-center justify-content-between w-100 gap-2" style="direction: rtl;">
                                                <span class="time-group-label mb-0">من</span>
                                                <div class="time-input-box flex-grow-1 d-flex justify-content-center">
                                                    <input type="number" name="start_mm" class="time-field" placeholder="00" min="0" max="59" value="<?php echo $start_m; ?>">
                                                    <span class="time-colon">:</span>
                                                    <input type="number" name="start_hh" class="time-field" placeholder="00" min="0" max="23" value="<?php echo $start_h; ?>">
                                                </div>
                                                <div class="time-arrow"><i class="fa-solid fa-arrow-left"></i></div>
                                                <span class="time-group-label mb-0">إلى</span>
                                                <div class="time-input-box flex-grow-1 d-flex justify-content-center">
                                                    <input type="number" name="end_mm" class="time-field" placeholder="00" min="0" max="59" value="<?php echo $end_m; ?>">
                                                    <span class="time-colon">:</span>
                                                    <input type="number" name="end_hh" class="time-field" placeholder="00" min="0" max="23" value="<?php echo $end_h; ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-action-base btn-save-custom">حفظ التعديلات</button>
                        </div>
                    </div>
                    </form>
                </div>

                <div class="col-md-6">
                    
                    <!-- نموذج معلومات التواصل -->
                    <form action="../php/update_account.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="update_contact">
                    <div class="info-card mb-4">
                        <div class="card-header-strip header-green">
                            <div class="icon-circle">
                                <img src="../assets/images/email_g.svg" alt="email">
                            </div>
                            <span class="card-title-text">معلومات التواصل</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="form-row-custom" style="align-items: flex-start;">
                                <label class="custom-label" style="padding-top: 12px;">الرقم:</label>
                                <div class="contact-wrapper">
                                    <div class="phone-wrapper" style="width: 100% !important; margin-bottom: 8px;">
                                        <span style="font-weight: bold; padding-right:10px;">+962</span>
                                        <input type="text" name="phone" style="background:transparent; border:none; flex-grow:1; outline:none; text-align:left; color: #555;" value="<?php echo $user['phone']; ?>" readonly>
                                    </div>
                                    
                                    <div class="form-check form-switch d-flex align-items-center" style="padding-left: 0; padding-right: 0;">
                                        <input class="form-check-input" type="checkbox" role="switch" id="hidePhoneSwitch" name="hide_phone" value="1" <?php if(isset($user['hide_phone']) && $user['hide_phone'] == 1) echo 'checked'; ?> style="width: 2.8em; height: 1.4em; cursor: pointer; margin: 0 0 0 8px; box-shadow: none !important; outline: none !important; border-color: #d1d5db;">
                                        <label class="form-check-label" for="hidePhoneSwitch" style="font-size: 0.80rem; white-space: nowrap;">
                                            إخفاء الرقم (تواصل عبر الموقع فقط)
                                        </label>
                                    </div>
                                    <style>
                                        #hidePhoneSwitch:checked { background-color: #28a745 !important; border-color: #28a745 !important; }
                                        #hidePhoneSwitch:focus { border-color: #d1d5db !important; }
                                    </style>
                                </div>
                            </div>
                            <div class="form-row-custom" style="align-items: flex-start;">
                                <label class="custom-label" style="padding-top: 8px;">البريد الالكتروني:</label>
                                <div class="contact-wrapper" style="display: flex; flex-direction: column;">
                                    <input type="email" name="email" class="custom-input" style="width: 100% !important; text-align: left; direction: ltr;" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="example@domain.com (اختياري)">
                                    <?php if(!empty($user['pending_email'])): ?>
                                        <small class="text-danger mt-1" style="font-size: 0.8rem;"><i class="fa-solid fa-clock"></i> بانتظار تأكيد بريدك الجديد: <?php echo htmlspecialchars($user['pending_email']); ?></small>
                                    <?php elseif(empty($user['email_verified_at']) && !empty($user['email'])): ?>
                                        <small class="text-warning mt-1" style="font-size: 0.8rem;"><i class="fa-solid fa-triangle-exclamation"></i> الإيميل الحالي غير مؤكد</small>
                                    <?php elseif(!empty($user['email_verified_at'])): ?>
                                        <small class="text-success mt-1" style="font-size: 0.8rem;"><i class="fa-solid fa-check-circle"></i> مؤكد</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-row-custom">
                                <label class="custom-label">المحافظة:</label>
                                <select name="governorate" class="custom-input">
                                    <option value="عمان" <?php if($user['governorate'] == 'عمان') echo 'selected'; ?>>عمان</option>
                                    <option value="الزرقاء" <?php if($user['governorate'] == 'الزرقاء') echo 'selected'; ?>>الزرقاء</option>
                                    <option value="إربد" <?php if($user['governorate'] == 'إربد') echo 'selected'; ?>>إربد</option>
                                    <option value="البلقاء" <?php if($user['governorate'] == 'البلقاء') echo 'selected'; ?>>البلقاء</option>
                                    <option value="مادبا" <?php if($user['governorate'] == 'مادبا') echo 'selected'; ?>>مادبا</option>
                                    <option value="المفرق" <?php if($user['governorate'] == 'المفرق') echo 'selected'; ?>>المفرق</option>
                                    <option value="جرش" <?php if($user['governorate'] == 'جرش') echo 'selected'; ?>>جرش</option>
                                    <option value="عجلون" <?php if($user['governorate'] == 'عجلون') echo 'selected'; ?>>عجلون</option>
                                    <option value="الكرك" <?php if($user['governorate'] == 'الكرك') echo 'selected'; ?>>الكرك</option>
                                    <option value="الطفيلة" <?php if($user['governorate'] == 'الطفيلة') echo 'selected'; ?>>الطفيلة</option>
                                    <option value="معان" <?php if($user['governorate'] == 'معان') echo 'selected'; ?>>معان</option>
                                    <option value="العقبة" <?php if($user['governorate'] == 'العقبة') echo 'selected'; ?>>العقبة</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-action-base btn-save-custom">حفظ التعديلات</button>
                        </div>
                    </div>
                    </form>

                    <div class="info-card">
                        <div class="card-header-strip header-blue">
                            <div class="icon-circle">
                                <img src="../assets/images/account-cog_b.svg" alt="account-cog">
                            </div>
                            <span class="card-title-text">معلومات الحساب</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="form-row-custom">
                                <label class="custom-label">التقييم الحالي:</label>
                                <div style="width: 70%; padding-right: 15px;">
                                <a href="user_reviews.php" class="d-flex align-items-center gap-2 text-decoration-none" title="عرض تفاصيل التقييمات">
                                <div class="text-warning" style="font-size: 1rem;">
                                    <?php 
                                    for($i=1; $i<=5; $i++) {
                                        echo ($i <= round($my_rating)) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star" style="color: #ccc;"></i>';
                                    }
                                    ?>
                                </div>
                                <span style="font-weight: 800; font-size: 1.2rem; color: #104496;"><?php echo $my_rating; ?></span>
                                <span class="text-muted small">(<?php echo $my_rating_count; ?>)</span>
                                </a>
                                </div>
                            </div>
                            
                            <div class="form-row-custom">
                                <label class="custom-label">كلمة المرور:</label>
                                <input type="password" class="custom-input" style="text-align: right; letter-spacing: 3px;" value="********" disabled>
                            </div>

                            <!-- رابط دعوة الأصدقاء -->
                            <div class="form-row-custom mt-3 mb-2">
                                <label class="custom-label" style="line-height: 1.4;">رابط الدعوة:<br><span class="text-success small fw-bold" style="font-size: 0.75rem;">(نقطة مجانية لكل دعوة)</span></label>
                                <div class="referral-wrapper" style="display: flex; direction: ltr; height: 45px;">
                                    <input type="text" id="referralLink" class="custom-input m-0" style="width: auto !important; flex-grow: 1; border-radius: 8px 0 0 8px !important; text-align: left; font-size: 0.85rem;" value="http://localhost/Ethraa/user/register.php?ref=<?php echo $user_id; ?>" readonly>
                                    <button class="btn btn-success m-0" type="button" onclick="copyReferralLink()" style="border-radius: 0; height: 100%; width: 50px; background-color: #66BF26; border-color: #66BF26; box-shadow: none; display: flex; align-items: center; justify-content: center;" title="نسخ الرابط"><i class="fa-solid fa-copy"></i></button>
                                    <button class="btn btn-primary m-0" type="button" onclick="shareReferralLink()" style="border-radius: 0 8px 8px 0; height: 100%; width: 50px; background-color: #104496; border-color: #104496; box-shadow: none; display: flex; align-items: center; justify-content: center;" title="مشاركة"><i class="fa-solid fa-share-nodes"></i></button>
                                </div>
                            </div>

                            <div class="d-flex gap-3 mt-3 w-100">
                                <button type="button" class="btn-action-base btn-delete-custom flex-grow-1 d-flex justify-content-center align-items-center m-0" style="height: 45px;" onclick="confirmDeleteAccount()">الغاء الحساب</button>
                                <button type="button" class="btn-action-base btn-change-pass flex-grow-1 d-flex justify-content-center align-items-center m-0" style="height: 45px;" data-bs-toggle="modal" data-bs-target="#changePassModal">تغيير كلمة المرور</button>
                            </div>
                            
                            <!-- زر تسجيل الخروج من جميع الأجهزة -->
                            <button type="button" class="btn-action-base btn-logout-all" onclick="confirmLogoutAll()">تسجيل الخروج من جميع الأجهزة</button>
                            
                            <!-- زر الدعم الفني عبر الواتساب (خاص بالهاتف فقط) -->
                            <a href="https://wa.me/96277799900" target="_blank" class="btn-action-base d-flex d-lg-none align-items-center justify-content-center gap-2 text-decoration-none" style="background-color: #25D366; color: white; margin-top: 15px; border-radius: 12px; transition: all 0.3s; padding: 10px;" onmouseover="this.style.backgroundColor='#20b858'" onmouseout="this.style.backgroundColor='#25D366'">
                                <i class="fa-brands fa-whatsapp fs-5"></i>
                                <span class="fw-bold">التواصل مع الدعم الفني (WhatsApp)</span>
                            </a>
                            
                            <!-- نماذج مخفية للتقديم عبر JavaScript -->
                            <form id="deleteAccountForm" action="../php/update_account.php" method="POST" style="display: none;">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="delete_account">
                            </form>
                            <form id="logoutAllForm" action="../php/update_account.php" method="POST" style="display: none;">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="logout_all">
                            </form>
                        </div>
                    </div>
                </div>

            </div>
    </div>
</div>

<!-- Modal تغيير كلمة المرور -->
<div class="modal fade" id="changePassModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 25px; border: none; padding: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
      <div class="modal-header border-0 justify-content-center position-relative">
        <h4 class="modal-title fw-bold" style="color: #001A75;">تغيير كلمة المرور</h4>
        <button type="button" class="btn-close position-absolute" style="left: 0;" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form action="../php/update_account.php" method="POST" onsubmit="return validatePasswordForm()">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="change_password">
            
            <div class="mb-3">
                <label class="form-label fw-bold text-secondary small">كلمة المرور الحالية</label>
                <div class="position-relative">
                    <input type="password" name="current_pass" id="currPass" class="form-control custom-input w-100" style="padding-left: 45px;" required>
                    <img src="../assets/images/mdi_eye-off.svg" id="eyeCurr" class="input-icon" style="left: 15px; right: auto; top: 50%; transform: translateY(-50%); position: absolute; cursor: pointer;" onclick="togglePassword('currPass', 'eyeCurr')" alt="show">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold text-secondary small">كلمة المرور الجديدة</label>
                <div class="position-relative">
                    <input type="password" name="new_pass" id="newPass" class="form-control custom-input w-100" style="padding-left: 45px;" required>
                    <img src="../assets/images/mdi_eye-off.svg" id="eyeNew" class="input-icon" style="left: 15px; right: auto; top: 50%; transform: translateY(-50%); position: absolute; cursor: pointer;" onclick="togglePassword('newPass', 'eyeNew')" alt="show">
                </div>
                <div id="passStrengthError" class="text-danger small fw-bold mt-1" style="display:none;">كلمة السر ضعيفة! يجب أن تكون 8 خانات وتحتوي حروفاً وأرقاماً.</div>
                <div id="passSameError" class="text-danger small fw-bold mt-1" style="display:none;">كلمة المرور الجديدة يجب أن تكون مختلفة عن كلمة المرور الحالية.</div>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold text-secondary small">تأكيد كلمة المرور</label>
                <div class="position-relative">
                    <input type="password" name="confirm_pass" id="confPass" class="form-control custom-input w-100" style="padding-left: 45px;" required>
                    <img src="../assets/images/mdi_eye-off.svg" id="eyeConf" class="input-icon" style="left: 15px; right: auto; top: 50%; transform: translateY(-50%); position: absolute; cursor: pointer;" onclick="togglePassword('confPass', 'eyeConf')" alt="show">
                </div>
                <div id="passMatchError" class="text-danger small fw-bold mt-1" style="display:none;">كلمات المرور غير متطابقة.</div>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn-action-base btn-save-custom w-100">تحديث كلمة المرور</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const allServices = <?php echo json_encode($all_services); ?>;
const currentSubId = "<?php echo $user['service_id']; ?>";

function filterSubServices() {
    const mainId = document.getElementById('main_service').value;
    const subSelect = document.getElementById('sub_service');
    subSelect.innerHTML = '<option value="">اختر التخصص</option>';
    
    if(mainId) {
        const subs = allServices.filter(s => s.parent_id == mainId);
        subs.forEach(s => {
            const option = document.createElement('option');
            option.value = s.service_id;
            option.textContent = s.name;
            if(s.service_id == currentSubId) option.selected = true;
            subSelect.appendChild(option);
        });
    }
}

// دالة لمراقبة التغييرات في النماذج وتفعيل زر الحفظ
function initFormsMonitoring() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const saveBtn = form.querySelector('.btn-save-custom');
        if (!saveBtn) return; // تخطي النماذج التي لا تحتوي على زر حفظ

        saveBtn.disabled = true; // تعطيل الزر افتراضياً

        const inputs = form.querySelectorAll('input, select');
        const initialData = new Map();

        // حفظ القيم الأولية
        inputs.forEach(el => {
            if (el.type === 'radio') {
                if (el.checked) initialData.set(el.name, el.value);
            } else if (el.type === 'checkbox') {
                initialData.set(el, el.checked);
            } else {
                initialData.set(el, el.value);
            }
            
            // مراقبة التغييرات
            el.addEventListener('input', () => checkForm(form, saveBtn, initialData));
            el.addEventListener('change', () => checkForm(form, saveBtn, initialData));
        });
    });
}

function checkForm(form, btn, initialData) {
    let isChanged = false;
    const inputs = form.querySelectorAll('input, select');
    
    for (let el of inputs) {
        if (el.type === 'radio') {
            if (el.checked) {
                const initVal = initialData.get(el.name);
                if (initVal !== el.value) isChanged = true;
            }
        } else if (el.type === 'checkbox') {
            if (initialData.has(el) && initialData.get(el) !== el.checked) {
                isChanged = true;
            }
        } else {
            if (initialData.has(el) && initialData.get(el) !== el.value) {
                isChanged = true;
            }
        }
    }
    btn.disabled = !isChanged;
}

// 1. دالة التحقق من العمر (18+)
function validateAge() {
    const dobInput = document.querySelector('input[name="birth_date"]');
    const dob = new Date(dobInput.value);
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
        age--;
    }
    if (age < 18) {
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه',
            text: 'عذراً، يجب أن يكون عمرك 18 عاماً فأكثر.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#021C7B'
        });
        return false;
    }
    return true;
}

// 2. دالة التحقق من قوة كلمة المرور وتطابقها
function validatePasswordForm() {
    const currPass = document.getElementById('currPass').value;
    const newPass = document.getElementById('newPass').value;
    const confPass = document.getElementById('confPass').value;
    const strengthError = document.getElementById('passStrengthError');
    const matchError = document.getElementById('passMatchError');
    const sameError = document.getElementById('passSameError');
    
    strengthError.style.display = 'none';
    matchError.style.display = 'none';
    if (sameError) sameError.style.display = 'none';

    // التحقق من أن كلمة المرور الجديدة مختلفة عن الحالية
    if (currPass && newPass && currPass === newPass) {
        if (sameError) sameError.style.display = 'block';
        return false;
    }

    // نفس الريجكس المستخدم في التسجيل
    const strongRegex = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;
    if (!strongRegex.test(newPass)) {
        strengthError.style.display = 'block';
        return false;
    }
    
    if (newPass !== confPass) {
        matchError.style.display = 'block';
        return false;
    }
    return true;
}

// 3. دالة إظهار/إخفاء كلمة المرور
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    const path = "../assets/images/";
    input.type = (input.type === "password") ? "text" : "password";
    icon.src = (input.type === "password") ? path + "mdi_eye-off.svg" : path + "mdi_eye-open.svg";
}

// 4. دالة التحقق من اختيار المجال والتخصص
function validateServiceForm() {
    const mainService = document.getElementById('main_service');
    const subService = document.getElementById('sub_service');
    
    if (mainService.value === "" || subService.value === "") {
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه',
            text: 'يرجى اختيار المجال والتخصص معاً.',
        });
        return false;
    }

    // التحقق من نوع التوافر والوقت
    const specific = document.getElementById('acc_specific');
    const always = document.getElementById('acc_always');
    const unavailable = document.getElementById('acc_unavailable');

    if (!specific.checked && !always.checked && !unavailable.checked) {
        Swal.fire({
            icon: 'warning',
            title: 'تنبيه',
            text: 'يرجى تحديد حالة التوافر (وقت محدد، متاح دائماً، أو غير متاح).',
        });
        return false;
    }

    if (specific.checked) {
        const startH = document.querySelector('input[name="start_hh"]').value;
        const startM = document.querySelector('input[name="start_mm"]').value;
        const endH = document.querySelector('input[name="end_hh"]').value;
        const endM = document.querySelector('input[name="end_mm"]').value;

        if (startH === "" || startM === "" || endH === "" || endM === "") {
            Swal.fire({
                icon: 'warning',
                title: 'تنبيه',
                text: 'يرجى تعبئة وقت البدء والانتهاء بشكل صحيح.',
            });
            return false;
        }
    }

    return true;
}

// تشغيل الدوال عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // عرض رسائل النجاح أو الخطأ المنبثقة
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    const error = urlParams.get('error');

    if (msg) {
        Swal.fire({
            icon: 'success',
            title: 'تم بنجاح!',
            text: msg,
        });
    }

    if (error) {
        Swal.fire({
            icon: 'error',
            title: 'حدث خطأ',
            text: error,
        });
    }

    filterSubServices();     // أولاً: ضبط القوائم المنسدلة
    initFormsMonitoring();   // ثانياً: بدء مراقبة التغييرات
});

function toggleAccountTime() {
    const isSpecific = document.getElementById('acc_specific').checked;
    const timeBox = document.getElementById('accTimeBox');
    timeBox.style.display = isSpecific ? 'block' : 'none';
}

// التمرير التلقائي لقسم الخدمات عند الطلب
if (new URLSearchParams(window.location.search).get('focus') === 'service') {
    const serviceSection = document.getElementById('serviceFormSection');
    if (serviceSection) {
        setTimeout(() => {
            serviceSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // وميض بسيط للفت الانتباه
            serviceSection.querySelector('.info-card').style.boxShadow = "0 0 20px rgba(102, 191, 38, 0.3)";
            setTimeout(() => { serviceSection.querySelector('.info-card').style.boxShadow = ""; }, 2000);
        }, 500);
    }
}

function confirmDeleteAccount() {
    Swal.fire({
        title: 'حذف الحساب',
        text: 'هل أنت متأكد من رغبتك في حذف حسابك نهائياً؟ لا يمكن التراجع عن هذا الإجراء وسيتم فقدان بياناتك.',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#f1f3f5',
        confirmButtonText: 'نعم، احذف حسابي',
        cancelButtonText: 'تراجع',
        customClass: { cancelButton: 'text-dark fw-bold' }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteAccountForm').submit();
        }
    });
}

function confirmLogoutAll() {
    Swal.fire({
        title: 'تسجيل الخروج من الكل',
        text: 'سيتم تسجيل خروج حسابك من جميع المتصفحات والأجهزة الأخرى. هل أنت متأكد؟',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#f1f3f5',
        confirmButtonText: 'نعم، خروج',
        cancelButtonText: 'تراجع',
        customClass: { cancelButton: 'text-dark fw-bold' }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('logoutAllForm').submit();
        }
    });
}

function copyReferralLink() {
    var copyText = document.getElementById("referralLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999); // لدعم أجهزة الموبايل
    navigator.clipboard.writeText(copyText.value).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'تم النسخ!',
            text: 'تم نسخ رابط الدعوة، شاركه مع أصدقائك لتربح نقاط مجانية!',
            timer: 2500,
            showConfirmButton: false
        });
    });
}

function shareReferralLink() {
    const link = document.getElementById("referralLink").value;
    if (navigator.share) {
        navigator.share({
            title: 'انضم إلى منصة إثراء',
            text: 'سجل عبر الرابط الخاص بي لنتبادل الخدمات والمنافع مجاناً عبر منصة إثراء!',
            url: link
        }).catch(console.error);
    } else {
        copyReferralLink(); // في حال كان المتصفح قديماً، ينسخ الرابط كبديل
    }
}
</script>
</body>
</html>