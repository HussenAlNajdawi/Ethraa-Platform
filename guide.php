<?php include 'includes/public_header.php'; ?>

<!-- إضافة ملفات CSS -->
<link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
<link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/assets/css/dark_mode.css'); ?>">
<link rel="stylesheet" href="assets/css/guide.css?v=<?php echo filemtime(__DIR__ . '/assets/css/guide.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    /* تأثير للأزرار غير المختارة في التبويبات */
    #guideTabs .nav-link:not(.active) {
        transition: all 0.3s ease;
    }
    #guideTabs .nav-link:not(.active):hover {
        background-color: #f8f9fa;
        color: #021C7B;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    /* تأثير لزر "ابدأ الآن" */
    .btn-start-now {
        transition: all 0.3s ease !important;
    }
    .btn-start-now:hover {
        background-color: #4C9F16 !important; /* لون أخضر أغمق قليلاً */
        border-color: #4C9F16 !important;
        transform: scale(1.05) translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 191, 38, 0.4);
    }
</style>

<div class="container guide-container">
    <!-- أزرار التحكم العلوية -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <button onclick="downloadPDF()" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
            <i class="fa-solid fa-file-pdf ms-2"></i> تحميل الدليل (PDF)
        </button>
        <a href="javascript:history.back()" class="guide-back-btn">
            <img src="assets/images/arrow-back.svg" width="45" alt="رجوع">
        </a>
    </div>

    <!-- شعار يظهر فقط في PDF -->
    <div class="pdf-header text-center mb-4" style="display: none;">
        <img src="assets/images/logo.png" alt="إثراء" height="80">
    </div>

    <div class="text-center mb-5">
        <h1 class="guide-title">دليل استخدام منصة إثراء</h1>
        <p class="guide-subtitle">خطوات بسيطة وسهلة لتبدأ رحلتك في تبادل الخدمات</p>
    </div>

    <!-- التبويبات -->
    <ul class="nav nav-pills mb-4 justify-content-center p-0" id="guideTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-4 fw-bold" id="provider-tab" data-bs-toggle="pill" data-bs-target="#provider-content" type="button">
                <i class="fa-solid fa-briefcase ms-2"></i> كيف أضيف خدمتي؟
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-bold" id="booking-tab" data-bs-toggle="pill" data-bs-target="#booking-content" type="button">
                <i class="fa-regular fa-calendar-check ms-2"></i> كيف أحجز خدمة؟
            </button>
        </li>
    </ul>

    <div class="tab-content" id="guideTabContent">
        
        <!-- قسم إضافة الخدمة -->
        <div class="tab-pane fade show active" id="provider-content">
            <div class="guide-card">
                <div class="step-row">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <h5>الذهاب إلى صفحة "حسابي"</h5>
                        <p>اضغط على رابط <strong>"حسابي"</strong> الموجود في الشريط العلوي للموقع.</p>
                        <img src="assets/images/دليل حسابي.png" class="guide-img" alt="الذهاب إلى حسابي">
                    </div>
                </div>

                <div class="step-row">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <h5>تحديد المجال والتخصص</h5>
                        <p>انزل لأسفل الصفحة إلى قسم <strong>"الخدمة التي أقدمها"</strong>. قم باختيار المجال العام (مثلاً: تقنية) ثم اختر التخصص الدقيق (مثلاً: برمجة مواقع).</p>
                        <img src="assets/images/دليل المجال.png" class="guide-img" alt="تحديد التخصص">
                    </div>
                </div>

                <div class="step-row">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <h5>تحديد أوقات التوفر</h5>
                        <p>اختر ما إذا كنت متاحاً في <strong>وقت محدد</strong> (ثم حدد الساعات) أو <strong>متاح دائماً</strong>، ثم اضغط على زر <strong>"حفظ التعديلات"</strong>.</p>
                        <img src="assets/images/دليل الوقت.png" class="guide-img" alt="تحديد الوقت">
                    </div>
                </div>
            </div>
        </div>

        <!-- قسم حجز الخدمة -->
        <div class="tab-pane fade" id="booking-content">
            <div class="guide-card">
                <div class="step-row">
                    <div class="step-num">1</div>
                    <div class="step-content">
                        <h5>البحث عن الخدمة</h5>
                        <p>اذهب إلى صفحة <strong>"الخدمات"</strong> واختر التصنيف الذي تريده، أو استخدم شريط البحث للعثور على مقدم خدمة معين.</p>
                        <img src="assets/images/دليل الخدمات.png" class="guide-img" alt="البحث عن خدمة">
                    </div>
                </div>

                <div class="step-row">
                    <div class="step-num">2</div>
                    <div class="step-content">
                        <h5>اختيار مقدم الخدمة</h5>
                        <p>تصفح الكروت المتاحة. يمكنك رؤية تقييم كل شخص وتخصصه. اضغط على زر <strong>"احجز الآن"</strong> لقلب البطاقة.</p>
                        <img src="assets/images/دليل الحجز.png" class="guide-img" alt="اختيار مقدم الخدمة">
                    </div>
                </div>

                <div class="step-row">
                    <div class="step-num">3</div>
                    <div class="step-content">
                        <h5>تأكيد الحجز</h5>
                        <p>اكتب تفاصيل المشكلة أو الخدمة التي تحتاجها في المربع المخصص، ثم اضغط <strong>"تأكيد الحجز"</strong>. سيصل الطلب فوراً للطرف الآخر.</p>
                        <img src="assets/images/دليل التأكيد.png" class="guide-img" alt="تأكيد الحجز">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="text-center mt-5">
        <a href="user/login.php" class="btn btn-success btn-lg rounded-pill px-5 fw-bold btn-start-now" style="background-color: #66BF26; border-color: #66BF26;">ابدأ الآن</a>
    </div>
</div>

<?php include 'includes/public_footer.php'; ?>

<script>
// كود الظهور التدريجي (Fade In) عند التمرير باستخدام Intersection Observer
document.addEventListener("DOMContentLoaded", function() {
    const steps = document.querySelectorAll('.step-row');
    const cards = document.querySelectorAll('.guide-card');
    
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                if (entry.target.classList.contains('step-row')) {
                    entry.target.classList.add('visible'); // إضافة الكلاس للظهور
                } else if (entry.target.classList.contains('guide-card')) {
                    entry.target.classList.add('visible-line'); // إظهار الخط
                }
                observer.unobserve(entry.target); // إيقاف المراقبة بعد الظهور مرة واحدة
            }
        });
    }, {
        threshold: 0.15 // يبدأ الظهور عندما يظهر 15% من العنصر في الشاشة
    });

    steps.forEach(step => observer.observe(step));
    cards.forEach(card => observer.observe(card));
});

function downloadPDF() {
    // تحديد العنصر المراد تحويله (الحاوية كاملة)
    const element = document.querySelector('.guide-container');
    
    // إخفاء الأزرار مؤقتاً لعدم ظهورها في الـ PDF
    const controls = document.querySelector('.no-print');
    controls.style.display = 'none';

    // إظهار الشعار في PDF
    const pdfHeader = document.querySelector('.pdf-header');
    if (pdfHeader) pdfHeader.style.display = 'block';
    
    // ضمان ظهور جميع الخطوات في الـ PDF (في حال لم يقم المستخدم بالتمرير لها بعد)
    const steps = document.querySelectorAll('.step-row');
    steps.forEach(step => step.classList.add('visible'));
    const cards = document.querySelectorAll('.guide-card');
    cards.forEach(card => card.classList.add('visible-line'));

    // إعدادات ملف PDF
    const opt = {
        margin:       [10, 10, 10, 10], // هوامش الصفحة
        filename:     'دليل_استخدام_إثراء.pdf', // اسم الملف عند التحميل
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, scrollY: 0 }, // دقة عالية
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // إنشاء وتحميل الملف
    html2pdf().set(opt).from(element).save().then(() => {
        // إعادة إظهار الأزرار بعد الانتهاء
        controls.style.display = ''; 
        if (pdfHeader) pdfHeader.style.display = 'none';
    });
}
</script>