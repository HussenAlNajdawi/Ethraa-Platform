/* assets/js/main.js */

// 1. دالة إظهار وإخفاء كلمة السر
function togglePassword(inputId, iconId) {
    var input = document.getElementById(inputId);
    var icon = document.getElementById(iconId);
    if (input && icon) {
        if (input.type === "password") {
            input.type = "text";
            icon.src = "assets/images/mdi_eye-open.svg";
        } else {
            input.type = "password";
            icon.src = "assets/images/mdi_eye-off.svg";
        }
    }
}

// 2. دالة مساعدة: التحقق من الرقم الأردني
function checkJordanianPhone(val) {
    var isError = false;
    if (val.length > 10) isError = true;
    if (val.length > 0) {
        var firstChar = val.charAt(0);
        if (firstChar !== '0' && firstChar !== '7') {
            isError = true;
        } else {
            if (firstChar === '0') {
                if (val.length >= 2 && val.charAt(1) !== '7') isError = true;
                if (val.length >= 3 && !['9','8','7'].includes(val.charAt(2))) isError = true;
            } else if (firstChar === '7') {
                if (val.length >= 2 && !['9','8','7'].includes(val.charAt(1))) isError = true;
            }
        }
    }
    return isError;
}

// 3. دالة مساعدة: التحقق من قوة كلمة السر
function checkPasswordStrength(val) {
    // الشروط: حرف كبير + رمز خاص + طول أكبر من 8 (أي 9 فأكثر)
    var strongPassRegex = /^(?=.*[A-Z])(?=.*[@$!%*?&#^()_+\-=\[\]{};':"\\|,.<>\/?]).{9,}$/;
    return strongPassRegex.test(val);
}

// 4. مراقبة الحقول (Event Listeners) عند تحميل الصفحة
document.addEventListener("DOMContentLoaded", function() {
    
    // --- أ. مراقبة حقل الهاتف (تسجيل الدخول + إنشاء حساب) ---
    var phoneInputs = ['phone', 'phoneLogin'];
    phoneInputs.forEach(function(id) {
        var input = document.getElementById(id);
        var errorDivId = (id === 'phone') ? 'phoneError' : 'loginPhoneError';
        var errorDiv = document.getElementById(errorDivId);

        if (input) {
            input.addEventListener('input', function() {
                var isError = checkJordanianPhone(this.value);
                if (isError) {
                    this.style.borderColor = "red";
                    this.style.backgroundColor = "#fff0f0";
                    if (errorDiv) errorDiv.style.display = 'block';
                } else {
                    this.style.borderColor = (id === 'phone') ? "#e1e1e1" : "#707070";
                    this.style.backgroundColor = "white";
                    if (errorDiv) errorDiv.style.display = 'none';
                }
            });
        }
    });

    // --- ب. مراقبة حقل كلمة السر (القوة) ---
    var passInput = document.getElementById('pass1');
    var passError = document.getElementById('passError');

    if (passInput) {
        passInput.addEventListener('input', function() {
            var val = this.value;
            // إذا كان فارغاً نرجع للوضع الطبيعي
            if (val.length === 0) {
                this.style.borderColor = "#e1e1e1";
                this.style.backgroundColor = "white";
                if (passError) passError.style.display = 'none';
                return;
            }

            var isValid = checkPasswordStrength(val);
            if (!isValid) {
                this.style.borderColor = "red";
                this.style.backgroundColor = "#fff0f0";
                if (passError) passError.style.display = 'block';
            } else {
                this.style.borderColor = "#6ABD45"; // أخضر
                this.style.backgroundColor = "white";
                if (passError) passError.style.display = 'none';
            }
            
            // إضافة: إذا عدل كلمة السر الأولى، نتحقق فوراً من الثانية إذا كانت مكتوبة
            var pass2Input = document.getElementById('pass2');
            if(pass2Input && pass2Input.value.length > 0) {
                // استدعاء حدث الإدخال يدوياً للحقل الثاني ليعيد فحص نفسه
                pass2Input.dispatchEvent(new Event('input'));
            }
        });
    }

    // --- ج. مراقبة حقل تأكيد كلمة السر (التطابق) ---
    var pass2Input = document.getElementById('pass2');
    var matchError = document.getElementById('matchError');

    if (pass2Input && passInput) {
        pass2Input.addEventListener('input', function() {
            var val1 = passInput.value;
            var val2 = this.value;

            // إذا كان الحقل فارغاً
            if (val2.length === 0) {
                this.style.borderColor = "#e1e1e1";
                this.style.backgroundColor = "white";
                if (matchError) matchError.style.display = 'none';
                return;
            }

            // التحقق من التطابق
            if (val1 !== val2) {
                this.style.borderColor = "red";
                this.style.backgroundColor = "#fff0f0";
                if (matchError) matchError.style.display = 'block';
            } else {
                this.style.borderColor = "#6ABD45"; // أخضر عند التطابق
                this.style.backgroundColor = "white";
                if (matchError) matchError.style.display = 'none';
            }
        });
    }
});

// 5. التحقق عند الضغط على زر "إنشاء حساب" (Submit)
function validateRegisterForm() {
    var isValid = true;
    var dobInput = document.getElementById('dob');
    var phoneInput = document.getElementById('phone');
    var passInput = document.getElementById('pass1');
    var pass2Input = document.getElementById('pass2'); }