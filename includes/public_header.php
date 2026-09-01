<?php 
require_once __DIR__ . '/../config/db_connect.php';
$base_url = "http://localhost/ithraa/"; 
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إثراء - تبادل الخدمات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">

    <!-- PWA Meta Tags (تطبيق الويب) -->
    <meta name="theme-color" content="#021C7B">
    <link rel="manifest" href="/Ethraa/manifest.json">
    
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/Ethraa/sw.js');
            });
        }
    </script>

    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
</head>
<body>

    <!-- شريط تقدم التمرير -->
    <div class="scroll-progress-container">
        <div class="scroll-progress-bar" id="myProgressBar"></div>
    </div>

    <!-- زر الصعود للأعلى -->
    <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="scroll-top-btn" id="scrollTopBtn" title="العودة للأعلى" style="bottom: 25px;">
        <i class="fa-solid fa-arrow-up"></i>
    </button>

    <script>
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

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container d-flex justify-content-between">
            <a class="navbar-brand" href="index.php">
                <img src="assets/images/logo.png" alt="إثراء" height="70">
            </a>

            <a href="user/login.php" class="btn-login-nav">تسجيل الدخول</a>
        </div>
    </nav>