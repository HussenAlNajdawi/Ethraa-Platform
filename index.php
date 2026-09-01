<?php include 'includes/public_header.php'; ?>

<link rel="stylesheet" href="assets/css/home.css?v=<?php echo filemtime(__DIR__ . '/assets/css/home.css'); ?>">

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0 order-lg-1 hero-text-box">
                    <h1 class="hero-title">
                        حوّل خبرتك إلى كل ما تحتاجه، <br>
                        دائرة<span class="highlight-green"> الإثراء</span> تبدأ بك.
                    </h1>
                    <p class="hero-subtitle">
                        مرحباً بك في إثراء، المنصة الأردنية الرائدة التي تُحوّل خدماتك ومهاراتك المتخصصة إلى قوة تبادل حقيقية.
                    </p>
                </div>
                
                <div class="col-lg-6 text-center order-lg-2">
                    <img src="assets/images/home.png" class="img-fluid light-hero-img" style="max-height: 500px;" alt="دائرة الخدمات">
                    <img src="assets/images/dark home photo.png" class="img-fluid dark-hero-img" style="max-height: 500px; display: none;" alt="دائرة الخدمات">
                </div>
            </div>
        </div>
    </section>

    <section class="categories-section">
        <div class="container">
            <h3 class="section-title reveal-on-scroll">المجالات التي يمكنك المساهمة بها</h3>
            
            <div class="d-flex justify-content-center flex-wrap gap-4 mt-4">
                
                <div class="category-item reveal-on-scroll">
                    <div class="cat-icon"><img src="assets/images/maintenance.svg" alt="مهني"></div>
                    <div class="cat-text">مهني</div>
                </div>
                <div class="category-item reveal-on-scroll">
                    <div class="cat-icon"><img src="assets/images/pc.svg" alt="تقني"></div>
                    <div class="cat-text">تقني</div>
                </div>
                 <div class="category-item reveal-on-scroll">
                    <div class="cat-icon"><img src="assets/images/law-icon.svg" alt="قانون"></div>
                    <div class="cat-text">قانون</div>
                </div>
                 <div class="category-item reveal-on-scroll">
                    <div class="cat-icon"><img src="assets/images/community.svg" alt="مجتمعي"></div>
                    <div class="cat-text">مجتمعي</div>
                </div>
                 <div class="category-item reveal-on-scroll">
                    <div class="cat-icon"><img src="assets/images/book.svg" alt="تعليم"></div>
                    <div class="cat-text">تعليم</div>
                </div>
                 <div class="category-item reveal-on-scroll">
                    <div class="cat-icon"><img src="assets/images/health-icon.svg" alt="صحة"></div>
                    <div class="cat-text">صحة</div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container reveal-scale">
            <h3>انضم إلى دائرة الإثراء، شارك خبرتك، واحصل على خدمات بلا قيود مالية.</h3>
            <p class="fs-5 mt-3 opacity-75">ابدأ رحلتك اليوم برصيد 3 نقاط مجانية في محفظتك</p>
            <a href="user/register.php" class="cta-btn">إنشاء حساب</a>
        </div>
    </section>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const revealElements = document.querySelectorAll(".reveal-on-scroll, .reveal-from-right, .reveal-from-left, .reveal-scale");

    if ("IntersectionObserver" in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12,
            rootMargin: "0px 0px -40px 0px"
        });

        revealElements.forEach(el => revealObserver.observe(el));
    } else {
        revealElements.forEach(el => el.classList.add("is-visible"));
    }
});
</script>

<?php include 'includes/public_footer.php'; ?>