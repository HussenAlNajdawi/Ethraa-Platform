<?php $base_url = "http://localhost/ithraa/"; // تأكد أن هذا المسار يناسب جهازك ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(isset($page_title) ? $page_title : 'إثراء', ENT_QUOTES, 'UTF-8'); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/user_home.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/user_home.css'); ?>">
    <?php if (isset($page_css)) echo $page_css; ?>
    
    <!-- تضمين مكتبة SweetAlert2 لضمان عملها في جميع صفحات المستخدم -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">

    <!-- PWA Meta Tags (تطبيق الويب) -->
    <meta name="theme-color" content="#021C7B">
    <link rel="manifest" href="/Ethraa/manifest.json">
    
    <style>
        /* شريط التحميل العلوي */
        #top-progress-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 3px;
            background-color: #66BF26;
            z-index: 100000;
            transform-origin: left;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        /* إصلاح اختفاء النص التوضيحي (Tooltip) خلف الهيدر */
        .tooltip {
            z-index: 100000 !important;
        }
    </style>
    
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/Ethraa/sw.js');
            });
        }
    </script>

    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <!-- سكربت الوضع الليلي ليعمل في جميع صفحات المستخدم -->
    <script src="../assets/js/dark_mode.js" defer></script>

    <!-- سكربت تأكيد تسجيل الخروج لجميع صفحات المستخدم -->
    <script>
    function confirmLogout(e, url) {
        e.preventDefault();
        Swal.fire({
            title: 'تسجيل الخروج',
            text: 'هل أنت متأكد من رغبتك في تسجيل الخروج من حسابك؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#f1f3f5',
            confirmButtonText: 'نعم، خروج',
            cancelButtonText: 'تراجع',
            customClass: { cancelButton: 'text-dark fw-bold' }
        }).then((result) => {
            if (result.isConfirmed) window.location.href = url;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // حماية أزرار الإرسال (Submit) من النقر المزدوج مع إظهار تأثير التحميل
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                // التحقق من وجود الزر
                if (submitBtn && !submitBtn.classList.contains('no-loader')) {
                    const originalWidth = submitBtn.offsetWidth;
                    submitBtn.style.minWidth = originalWidth + 'px'; // الحفاظ على حجم الزر لئلا ينكمش
                    submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> جاري...';
                    submitBtn.style.opacity = '0.8';
                    submitBtn.style.cursor = 'not-allowed';
                    setTimeout(() => { submitBtn.disabled = true; }, 50); // إيقاف الزر بعد السماح بتنفيذ الإرسال
                }
            });
        });
    });
    </script>
</head>
<body>
    
    <!-- شريط تقدم التمرير -->
    <div class="scroll-progress-container">
        <div class="scroll-progress-bar" id="myProgressBar"></div>
    </div>

    <!-- زر الدعم الفني العائم -->
    <a href="https://wa.me/96277799900" target="_blank" class="support-float-btn" title="تواصل مع الدعم الفني">
        <i class="fa-brands fa-whatsapp"></i>
    </a>

    <!-- زر الصعود للأعلى -->
    <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="scroll-top-btn" id="scrollTopBtn" title="العودة للأعلى">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <script>
        // إظهار وإخفاء زر الصعود للأعلى عند التمرير
        window.addEventListener('scroll', function() {
            var btn = document.getElementById('scrollTopBtn');
            if (window.scrollY > 800) {
                btn.classList.add('show');
            } else {
                btn.classList.remove('show');
            }
            
            // حساب تقدم التمرير للشريط العلوي
            var winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            var height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            var scrolled = (winScroll / height) * 100;
            var bar = document.getElementById("myProgressBar");
            if (bar) bar.style.width = scrolled + "%";
        });
    </script>