<?php
session_start();
require_once '../config/db_connect.php';

// إنشاء CSRF Token إذا لم يكن موجوداً
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// استرجاع البيانات القديمة لتعبئة الحقول عند الخطأ
$oldData = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
?>
<!-- التقاط كود الدعوة إن وجد -->
<?php $ref = isset($_GET['ref']) ? intval($_GET['ref']) : (isset($oldData['ref']) ? $oldData['ref'] : ''); ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/register.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/register.css'); ?>">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
    <style>
        .field-error-msg {
            color: #dc3545;
            font-size: 0.84rem;
            font-weight: 600;
            margin-top: -12px;
            margin-bottom: 14px;
            margin-right: 8px;
            text-align: right;
            display: none;
            transition: all 0.2s ease-in-out;
        }
        .field-error-msg i {
            margin-left: 4px;
        }
        .dark-mode .field-error-msg {
            color: #ff6b6b;
        }
    </style>
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <!-- سكربت الوضع الليلي -->
    <script src="../assets/js/dark_mode.js" defer></script>
</head>
<body>

    <div class="register-card">
        <a href="../index.php"><img src="../assets/images/close-green.svg" class="close-btn" alt="إغلاق"></a>

        <div class="form-section">
            <h3 class="page-title">إنشاء حساب</h3>
            <form id="registerForm" action="../php/register_process.php" method="POST" onsubmit="return validateRegisterForm()">
                <!-- حماية من CSRF وإرسال كود الدعوة -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="referrer_id" value="<?php echo htmlspecialchars($ref); ?>">
                
                <div class="custom-input-group">
                    <input type="text" name="first_name" id="firstName" class="form-control custom-input" placeholder="الاسم الأول" 
                           value="<?php echo htmlspecialchars($oldData['first_name'] ?? ''); ?>" required>
                </div>
                <div id="firstNameError" class="field-error-msg"><i class="fa-solid fa-circle-exclamation"></i> يرجى إدخال الاسم الأول.</div>

                <div class="custom-input-group">
                    <input type="text" name="last_name" id="lastName" class="form-control custom-input" placeholder="الاسم الأخير" 
                           value="<?php echo htmlspecialchars($oldData['last_name'] ?? ''); ?>" required>
                </div>
                <div id="lastNameError" class="field-error-msg"><i class="fa-solid fa-circle-exclamation"></i> يرجى إدخال الاسم الأخير.</div>

                <div class="custom-input-group">
                    <input type="text" name="birth_date" id="dob" class="form-control custom-input <?php if(isset($_GET['error']) && ($_GET['error'] == 'underage' || $_GET['error'] == 'invalid_age')) echo 'input-error'; ?>" placeholder="تاريخ الميلاد" 
                           onfocus="this.type='date'; if(this.showPicker) this.showPicker();" 
                           onblur="if(!this.value) this.type='text';" 
                           value="<?php echo htmlspecialchars($oldData['birth_date'] ?? ''); ?>" required>
                    <img src="../assets/images/calender.svg" class="input-icon" alt="date" onclick="let d = document.getElementById('dob'); d.type='date'; d.focus(); if(d.showPicker) d.showPicker();">
                </div>
                <div id="ageError" class="field-error-msg" style="<?php echo (isset($_GET['error']) && ($_GET['error'] == 'underage' || $_GET['error'] == 'invalid_age')) ? 'display:block;' : 'display:none;'; ?>"><i class="fa-solid fa-circle-exclamation"></i> عذراً، يجب أن يكون عمرك 18 عاماً أو أكثر لإنشاء حساب.</div>

                <div class="custom-input-group" style="direction: ltr;">
                    <input type="tel" name="phone" id="phone" 
                           class="form-control custom-input <?php if(isset($_GET['error']) && ($_GET['error'] == 'exists' || $_GET['error'] == 'invalid_phone')) echo 'input-error'; ?>" 
                           placeholder="79xxxxxxx" 
                           maxlength="9"
                           value="<?php echo htmlspecialchars($oldData['phone'] ?? ''); ?>"
                           style="text-align: right;" 
                           required>
                    <span style="position: absolute; left: 15px; top: 15px; color: #555; font-size: 14px;">+962</span>
                </div>
                <div id="phoneError" class="field-error-msg" style="<?php echo (isset($_GET['error']) && ($_GET['error'] == 'exists' || $_GET['error'] == 'invalid_phone')) ? 'display:block;' : 'display:none;'; ?>">
                    <i class="fa-solid fa-circle-exclamation"></i> 
                    <span id="phoneErrorText">
                        <?php 
                        if(isset($_GET['error']) && $_GET['error'] == 'exists') echo 'عذراً، رقم الهاتف هذا مسجل مسبقاً.';
                        elseif(isset($_GET['error']) && $_GET['error'] == 'invalid_phone') echo 'رقم الهاتف غير صحيح! يجب أن يبدأ بـ 79 أو 78 أو 77 ويتكون من 9 أرقام.';
                        ?>
                    </span>
                </div>
                
                <div class="custom-input-group">
                    <input type="password" name="password" id="pass1" class="form-control custom-input <?php if(isset($_GET['error']) && $_GET['error'] == 'weak_password') echo 'input-error'; ?>" placeholder="كلمة السر" required>
                    <img src="../assets/images/mdi_eye-off.svg" id="eye1" class="input-icon" onclick="togglePassword('pass1', 'eye1')" alt="show">
                </div>
                <div id="passError" class="field-error-msg" style="<?php echo (isset($_GET['error']) && $_GET['error'] == 'weak_password') ? 'display:block;' : 'display:none;'; ?>"><i class="fa-solid fa-circle-exclamation"></i> <span id="passErrorText">كلمة السر ضعيفة! يجب ألا تقل عن 8 خانات وتحتوي على حروف وأرقام.</span></div>

                <div class="custom-input-group">
                    <input type="password" name="confirm_password" id="pass2" class="form-control custom-input" placeholder="تأكيد كلمة السر" required>
                    <img src="../assets/images/mdi_eye-off.svg" id="eye2" class="input-icon" onclick="togglePassword('pass2', 'eye2')" alt="show">
                </div>
                <div id="confirmError" class="field-error-msg"><i class="fa-solid fa-circle-exclamation"></i> كلمات السر غير متطابقة!</div>
                
                <button type="submit" class="btn btn-register-green mt-3">انشاء حساب</button>
                
                <div class="text-center mt-3">
                    <span class="text-muted">هل لديك حساب بالفعل؟ </span>
                    <a href="login.php" class="fw-bold text-dark text-decoration-none">تسجيل الدخول</a>
                </div>
            </form>
        </div>

        <div class="green-sidebar d-none d-lg-flex">
            <h1 class="brand-title">إثراء</h1>
            <p class="brand-subtitle">مكانك المثالي لتبادل الخدمات</p>
        </div>
    </div>

    <?php 
    if(isset($_SESSION['form_data'])) {
        unset($_SESSION['form_data']);
    }
    ?>

    <script>
        // عرض الرسائل المنبثقة عند تحميل الصفحة
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('error')) {
                let errorText = '';
                let errorTitle = 'خطأ في التسجيل';
                switch(urlParams.get('error')) {
                    case 'underage':
                    case 'invalid_age':
                        errorText = 'عذراً، يجب أن يكون عمرك 18 عاماً أو أكثر لإنشاء حساب في المنصة.';
                        break;
                    case 'weak_password':
                        errorText = 'كلمة السر ضعيفة! يجب أن لا تقل عن 8 خانات وتحتوي على حروف وأرقام.';
                        break;
                    case 'invalid_phone':
                        errorText = 'رقم الهاتف غير صحيح! يجب أن يبدأ بـ 79 أو 78 أو 77 ويتكون من 9 أرقام.';
                        break;
                    case 'exists':
                        errorText = 'عذراً، رقم الهاتف هذا مسجل مسبقاً.';
                        break;
                }

                if(errorText) {
                    Swal.fire({
                        icon: 'error',
                        title: errorTitle,
                        text: errorText,
                    });
                }
            }
        });

        // تعريف العناصر
        const firstNameInput = document.getElementById('firstName');
        const lastNameInput = document.getElementById('lastName');
        const dobInput = document.getElementById('dob');
        const ageErrorDiv = document.getElementById('ageError');
        const phoneInput = document.getElementById('phone');
        const phoneErrorDiv = document.getElementById('phoneError');
        const phoneErrorText = document.getElementById('phoneErrorText');
        const passInput = document.getElementById('pass1');
        const passErrorDiv = document.getElementById('passError');
        const passErrorText = document.getElementById('passErrorText');
        const confirmInput = document.getElementById('pass2');
        const confirmErrorDiv = document.getElementById('confirmError');
        const firstNameError = document.getElementById('firstNameError');
        const lastNameError = document.getElementById('lastNameError');

        // تحديد الحد الأقصى لتاريخ الميلاد (قبل 18 سنة من تاريخ اليوم)
        const today = new Date();
        const maxYear = today.getFullYear() - 18;
        const maxMonth = String(today.getMonth() + 1).padStart(2, '0');
        const maxDay = String(today.getDate()).padStart(2, '0');
        const maxDateStr = `${maxYear}-${maxMonth}-${maxDay}`;
        if (dobInput) {
            dobInput.setAttribute('max', maxDateStr);
            dobInput.addEventListener('change', checkAgeValidation);
            dobInput.addEventListener('input', checkAgeValidation);
        }

        // مراقبة الاسم الأول والأخير
        if (firstNameInput) {
            firstNameInput.addEventListener('input', function() {
                if (this.value.trim() !== "") {
                    firstNameError.style.display = 'none';
                    validateField(this, true);
                }
            });
        }
        if (lastNameInput) {
            lastNameInput.addEventListener('input', function() {
                if (this.value.trim() !== "") {
                    lastNameError.style.display = 'none';
                    validateField(this, true);
                }
            });
        }

        function calculateAge(birthDateString) {
            if (!birthDateString) return -1;
            const bDate = new Date(birthDateString);
            if (isNaN(bDate.getTime())) return -1;
            const now = new Date();
            let age = now.getFullYear() - bDate.getFullYear();
            const m = now.getMonth() - bDate.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < bDate.getDate())) {
                age--;
            }
            return age;
        }

        function checkAgeValidation() {
            if (!dobInput.value) {
                ageErrorDiv.style.display = 'none';
                dobInput.classList.remove('input-error', 'input-success');
                return false;
            }
            const age = calculateAge(dobInput.value);
            const isValid = (age >= 18);
            if (isValid) {
                ageErrorDiv.style.display = 'none';
                validateField(dobInput, true);
            } else {
                ageErrorDiv.style.display = 'block';
                validateField(dobInput, false);
            }
            return isValid;
        }
        
        // 1. التحقق الفوري للرقم
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, ''); // أرقام فقط
            const jordanRegex = /^(77|78|79)[0-9]{7}$/;
            const isValid = jordanRegex.test(this.value);
            validateField(this, isValid);

            if (this.value.length === 0) {
                phoneErrorDiv.style.display = 'none';
            } else if (!isValid) {
                phoneErrorText.innerText = 'رقم الهاتف غير صحيح! يجب أن يبدأ بـ 79 أو 78 أو 77 ويتكون من 9 أرقام.';
                phoneErrorDiv.style.display = 'block';
            } else {
                phoneErrorDiv.style.display = 'none';
            }
        });

        // 2. التحقق الفوري لكلمة السر
        passInput.addEventListener('input', function() {
            const strongRegex = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;
            const isValid = strongRegex.test(this.value);
            validateField(this, isValid);

            if (this.value.length === 0) {
                passErrorDiv.style.display = 'none';
            } else if (!isValid) {
                passErrorText.innerText = 'كلمة السر ضعيفة! يجب ألا تقل عن 8 خانات وتحتوي على حروف وأرقام.';
                passErrorDiv.style.display = 'block';
            } else {
                passErrorDiv.style.display = 'none';
            }

            checkMatch(); // افحص التطابق فوراً
        });

        // 3. التحقق من التطابق
        confirmInput.addEventListener('input', checkMatch);

        function checkMatch() {
            if (confirmInput.value === "") {
                confirmInput.classList.remove('input-error', 'input-success');
                confirmErrorDiv.style.display = 'none';
                return false;
            }
            const isMatch = (passInput.value === confirmInput.value);
            validateField(confirmInput, isMatch);
            if (isMatch) {
                confirmErrorDiv.style.display = 'none';
            } else {
                confirmErrorDiv.style.display = 'block';
            }
            return isMatch;
        }

        // دالة مساعدة لتلوين الحقول
        function validateField(field, isValid) {
            if (isValid) {
                field.classList.remove('input-error');
                field.classList.add('input-success');
            } else {
                field.classList.remove('input-success');
                field.classList.add('input-error');
            }
        }

        // 4. دالة التحقق النهائية عند الضغط على زر "إنشاء حساب"
        function validateRegisterForm() {
            let hasError = false;

            // التحقق من الاسم الأول
            if (!firstNameInput.value.trim()) {
                firstNameError.style.display = 'block';
                validateField(firstNameInput, false);
                hasError = true;
            }

            // التحقق من الاسم الأخير
            if (!lastNameInput.value.trim()) {
                lastNameError.style.display = 'block';
                validateField(lastNameInput, false);
                hasError = true;
            }

            // التحقق من تاريخ الميلاد والعمر 18+
            if (!dobInput.value) {
                ageErrorDiv.innerText = 'يرجى إدخال تاريخ ميلادك للمتابعة.';
                ageErrorDiv.style.display = 'block';
                validateField(dobInput, false);
                hasError = true;
            } else {
                const age = calculateAge(dobInput.value);
                if (age < 18) {
                    ageErrorDiv.innerText = 'عذراً، يجب أن يكون عمرك 18 عاماً أو أكثر لإنشاء حساب في المنصة.';
                    ageErrorDiv.style.display = 'block';
                    validateField(dobInput, false);
                    hasError = true;
                }
            }

            // التحقق من رقم الهاتف
            const jordanRegex = /^(77|78|79)[0-9]{7}$/;
            if (!jordanRegex.test(phoneInput.value)) {
                phoneErrorText.innerText = 'يرجى إدخال رقم هاتف أردني صحيح (يبدأ بـ 79 أو 78 أو 77 ويتكون من 9 أرقام).';
                phoneErrorDiv.style.display = 'block';
                validateField(phoneInput, false);
                hasError = true;
            }

            // التحقق من كلمة السر
            const strongRegex = /^(?=.*[A-Za-z])(?=.*\d).{8,}$/;
            if (!strongRegex.test(passInput.value)) {
                passErrorText.innerText = 'كلمة السر ضعيفة! يجب ألا تقل عن 8 خانات وتحتوي على حروف وأرقام.';
                passErrorDiv.style.display = 'block';
                validateField(passInput, false);
                hasError = true;
            }

            // التحقق من تطابق كلمة السر
            if (passInput.value !== confirmInput.value) {
                confirmErrorDiv.style.display = 'block';
                validateField(confirmInput, false);
                hasError = true;
            }

            if (hasError) {
                Swal.fire({
                    icon: 'warning',
                    title: 'يرجى تصحيح البيانات',
                    text: 'يرجى مراجعة الحقول المحددة باللون الأحمر وتصحيحها للمتابعة.',
                });
                return false;
            }

            return true; 
        }
        
        // كود العين
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            const path = "../assets/images/"; 

            if (input.type === "password") {
                input.type = "text";
                icon.src = path + "mdi_eye-open.svg"; 
            } else {
                input.type = "password";
                icon.src = path + "mdi_eye-off.svg"; 
            }
        }
    </script>
</body>
</html>