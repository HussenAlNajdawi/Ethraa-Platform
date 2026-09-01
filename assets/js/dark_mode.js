/**
 * سكربت تفعيل الوضع الليلي (Dark Mode) لجميع صفحات المنصة
 */

// تطبيق السمة فوراً لمنع الوميض الأبيض (إن أمكن)
if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.classList.add('dark-mode');
}

document.addEventListener('DOMContentLoaded', () => {
    // تزامن الكلاس مع body بعد تحميله (مهم جداً لأن ملف الـ CSS يعتمد على body.dark-mode)
    if (document.documentElement.classList.contains('dark-mode')) {
        document.body.classList.add('dark-mode');
    }

    // تجنب إضافة الزر أكثر من مرة
    if (document.querySelector('.dark-mode-toggle')) return;
    
    // أيقونات بصيغة SVG لضمان ظهورها في كل الصفحات بشكل مستقل
    const sunIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16"><path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/></svg>`;
    const moonIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278z"/></svg>`;

    // إنشاء زر التبديل العائم
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'dark-mode-toggle';
    toggleBtn.title = 'تفعيل/إلغاء الوضع الليلي';
    
    // تحديد الأيقونة المناسبة بناءً على الحالة الحالية للزر العائم
    const isCurrentlyDark = document.body.classList.contains('dark-mode');
    toggleBtn.innerHTML = isCurrentlyDark ? `<i>${sunIcon}</i>` : `<i>${moonIcon}</i>`;
    
    // تحديث أيقونة الزر في الموبايل عند التحميل
    const mobileToggle = document.getElementById('mobileDarkModeToggle');
    if (mobileToggle) {
        mobileToggle.innerHTML = isCurrentlyDark ? `<i class="fa-solid fa-sun text-warning me-2"></i> إيقاف الوضع الليلي` : `<i class="fa-solid fa-moon text-secondary me-2"></i> تفعيل الوضع الليلي`;
    }
    
    document.body.appendChild(toggleBtn);

    // إنشاء الدالة العامة للتبديل (لتتمكن أي أزرار أخرى في الموبايل من استخدامها)
    window.toggleDarkMode = function() {
        document.documentElement.classList.toggle('dark-mode');
        document.body.classList.toggle('dark-mode');
        
        const isDark = document.documentElement.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        
        // تحديث أيقونة الزر العائم
        if (toggleBtn) {
            toggleBtn.innerHTML = isDark ? `<i>${sunIcon}</i>` : `<i>${moonIcon}</i>`;
        }
        
        // تحديث أيقونة الزر في الهيدر (الموبايل) إذا وجد
        const headerToggle = document.getElementById('headerDarkModeToggle');
        if (headerToggle) {
            headerToggle.innerHTML = isDark ? `<i class="fa-solid fa-sun text-warning fs-5"></i>` : `<i class="fa-solid fa-moon fs-5"></i>`;
        }
    };

    // حدث الضغط على الزر العائم للتبديل
    toggleBtn.addEventListener('click', window.toggleDarkMode);
    
    // التحديث المبدئي لأيقونة الهيدر
    const headerToggle = document.getElementById('headerDarkModeToggle');
    if (headerToggle) {
        headerToggle.innerHTML = isCurrentlyDark ? `<i class="fa-solid fa-sun text-warning fs-5"></i>` : `<i class="fa-solid fa-moon text-dark fs-5"></i>`;
    }
});