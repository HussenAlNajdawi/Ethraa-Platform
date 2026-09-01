<?php
require_once '../config/db_connect.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$current_user_id = $_SESSION['user_id'];

// جلب قائمة الأشخاص الذين تم التعامل معهم سابقاً (أصدقاء)
$friends_ids = [];
$friend_sql = "SELECT provider_id FROM requests WHERE requester_id = ? AND status = 'completed'
               UNION
               SELECT requester_id FROM requests WHERE provider_id = ? AND status = 'completed'";
if ($stmt_f = $conn->prepare($friend_sql)) {
    $stmt_f->bind_param("ii", $current_user_id, $current_user_id);
    $stmt_f->execute();
    $res_f = $stmt_f->get_result();
    while($f_row = $res_f->fetch_row()) {
        $friends_ids[] = $f_row[0];
    }
    $stmt_f->close();
}

// جلب قائمة الاشتراكات في التنبيهات
$subscribed_providers = [];
$sub_sql = "SELECT provider_id FROM availability_subscriptions WHERE requester_id = ?";
if ($stmt_sub = $conn->prepare($sub_sql)) {
    $stmt_sub->bind_param("i", $current_user_id);
    $stmt_sub->execute();
    $res_sub = $stmt_sub->get_result();
    while ($sub_row = $res_sub->fetch_assoc()) {
        $subscribed_providers[] = $sub_row['provider_id'];
    }
    $stmt_sub->close();
}

// التحقق مما إذا كان لدى المستخدم أي طلب نشط حالياً (يمنع طلب جديد حتى انتهاء الحالي)
$has_any_active_request = false;
$active_sql = "SELECT request_id FROM requests WHERE requester_id = ? AND (status = 'pending' OR (status = 'accepted' AND requester_confirmed = 0)) LIMIT 1";
if ($stmt_a = $conn->prepare($active_sql)) {
    $stmt_a->bind_param("i", $current_user_id);
    $stmt_a->execute();
    $stmt_a->store_result();
    if ($stmt_a->num_rows > 0) {
        $has_any_active_request = true;
    }
    $stmt_a->close();
}

// استقبال البيانات
$main_service_id = $_GET['main_id'] ?? ''; 
$search_query    = $_GET['search'] ?? '';
$sub_id          = $_GET['sub_id'] ?? '';
$governorate     = $_GET['governorate'] ?? ''; 
$available_now   = $_GET['available_now'] ?? ''; 
$sort_option     = $_GET['sort'] ?? 'highest_rating'; 

// جلب الخدمات الفرعية
$sub_services_sql = "SELECT * FROM services WHERE parent_id = ?";
$sub_stmt = $conn->prepare($sub_services_sql);
$sub_stmt->bind_param("i", $main_service_id);
$sub_stmt->execute();
$sub_services_result = $sub_stmt->get_result();

// استعلام البحث
$sql = "SELECT 
            u.user_id, u.service_id, u.first_name, u.last_name, u.phone, u.email, u.governorate, u.hide_phone,
            u.free_time_start, u.free_time_end, u.availability_type,
            s.name as sub_service_name, p.name as main_service_name,
            (SELECT AVG(rating) FROM reviews WHERE provider_id = u.user_id) as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE provider_id = u.user_id) as rating_count
        FROM users u
        JOIN services s ON u.service_id = s.service_id 
        LEFT JOIN services p ON s.parent_id = p.service_id 
        WHERE u.user_id != ? AND u.service_id IS NOT NULL"; 

$types = "i";
$params = [$current_user_id];
$highlight_id = $_GET['highlight'] ?? '';

if (!empty($main_service_id)) { $sql .= " AND s.parent_id = ?"; $types .= "i"; $params[] = $main_service_id; }
if (!empty($search_query)) { $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ?)"; $types .= "ss"; $params[] = "%$search_query%"; $params[] = "%$search_query%"; }
if (!empty($sub_id)) { $sql .= " AND u.service_id = ?"; $types .= "i"; $params[] = $sub_id; }
if (!empty($governorate)) { $sql .= " AND u.governorate = ?"; $types .= "s"; $params[] = $governorate; }
if (!empty($available_now)) { 
    $sql .= " AND (u.availability_type = 'always' OR (u.availability_type = 'specific' AND CURTIME() BETWEEN u.free_time_start AND u.free_time_end))"; 
}

// ترتيب النتائج
switch ($sort_option) {
    case 'lowest_rating':
        $sql .= " ORDER BY avg_rating ASC";
        break;
    case 'most_rated':
        $sql .= " ORDER BY rating_count DESC";
        break;
    case 'highest_rating':
    default:
        $sql .= " ORDER BY avg_rating DESC";
        break;
}

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$page_title = 'الخدمات - إثراء';
$page_css = '
    <link rel="stylesheet" href="../assets/css/services_list.css?v=' . filemtime(__DIR__ . '/../assets/css/services_list.css') . '">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
';
include '../includes/user_header.php'; 
?>
<?php include '../includes/user_navbar.php'; ?>

<div class="container mt-4 mb-5">
    
    <div class="top-bar">
        <form action="services_list.php" method="GET" class="search-filter-container" id="servicesSearchForm" onsubmit="return false;">
            <input type="hidden" name="main_id" value="<?php echo htmlspecialchars($main_service_id); ?>">

            <div class="custom-search-box">
                <img src="../assets/images/search.svg" class="search-icon-img" alt="بحث">
                <input type="text" name="search" id="serviceSearchInput" placeholder="ابحث عن اسم مقدم الخدمة..." 
                       value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off">
            </div>

            <button type="button" class="filter-toggle-btn" onclick="toggleFilters()" title="تصفية">
                <img src="../assets/images/filter.svg" alt="فلتر">
            </button>

            <div id="filterOptions" class="filter-options-box" style="display: none;">
                <div class="select-wrapper">
                    <select name="sub_id" class="styled-select" onchange="performLiveServicesSearch()">
                        <option value="">كافة التخصصات</option>
                        <?php 
                        $sub_services_result->data_seek(0); 
                        while ($sub = $sub_services_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $sub['service_id']; ?>" 
                                <?php if($sub_id == $sub['service_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($sub['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="select-wrapper">
                    <select name="governorate" class="styled-select" onchange="performLiveServicesSearch()">
                        <option value="">كافة المحافظات</option>
                        <option value="عمان" <?php if($governorate == 'عمان') echo 'selected'; ?>>العاصمة عمان</option>
                        <option value="الزرقاء" <?php if($governorate == 'الزرقاء') echo 'selected'; ?>>الزرقاء</option>
                        <option value="البلقاء" <?php if($governorate == 'البلقاء') echo 'selected'; ?>>البلقاء</option>
                        <option value="مادبا" <?php if($governorate == 'مادبا') echo 'selected'; ?>>مادبا</option>
                        <option value="إربد" <?php if($governorate == 'إربد') echo 'selected'; ?>>إربد</option>
                        <option value="المفرق" <?php if($governorate == 'المفرق') echo 'selected'; ?>>المفرق</option>
                        <option value="جرش" <?php if($governorate == 'جرش') echo 'selected'; ?>>جرش</option>
                        <option value="عجلون" <?php if($governorate == 'عجلون') echo 'selected'; ?>>عجلون</option>
                        <option value="الكرك" <?php if($governorate == 'الكرك') echo 'selected'; ?>>الكرك</option>
                        <option value="الطفيلة" <?php if($governorate == 'الطفيلة') echo 'selected'; ?>>الطفيلة</option>
                        <option value="معان" <?php if($governorate == 'معان') echo 'selected'; ?>>معان</option>
                        <option value="العقبة" <?php if($governorate == 'العقبة') echo 'selected'; ?>>العقبة</option>
                    </select>
                </div>

                <div class="select-wrapper">
                    <select name="sort" class="styled-select" onchange="performLiveServicesSearch()">
                        <option value="highest_rating" <?php if($sort_option == 'highest_rating') echo 'selected'; ?>>الأعلى تقييماً</option>
                        <option value="most_rated" <?php if($sort_option == 'most_rated') echo 'selected'; ?>>الأكثر تقييماً</option>
                        <option value="lowest_rating" <?php if($sort_option == 'lowest_rating') echo 'selected'; ?>>الأقل تقييماً</option>
                    </select>
                </div>

                <!-- فلتر متاح الآن -->
                <label class="available-now-box <?php if($available_now == '1') echo 'active'; ?>" for="availableNowCheck">
                    <span>متاح الآن</span>
                    <div class="form-check form-switch m-0 d-flex align-items-center">
                        <input class="form-check-input" type="checkbox" name="available_now" value="1" id="availableNowCheck" onchange="performLiveServicesSearch()" <?php if($available_now == '1') echo 'checked'; ?>>
                    </div>
                </label>
            </div>
        </form>

        <a href="my_services.php" class="back-btn">
            <img src="../assets/images/arrow-back.svg" alt="رجوع">
        </a>
    </div>

    <div class="row g-4 justify-content-center" id="servicesCardsContainer">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php 
                    if ($row['user_id'] == $current_user_id) continue;
                    $is_friend = in_array($row['user_id'], $friends_ids);
                    $is_subscribed = in_array($row['user_id'], $subscribed_providers);
                ?>
                <?php 
                // التحقق من التوفر (الحالة + الوقت)
                $is_unavailable = false;
                if ($row['availability_type'] == 'unavailable') {
                    $is_unavailable = true;
                }
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    
                    <div class="service-card <?php echo ($highlight_id == $row['user_id']) ? 'highlighted-card' : ''; ?>" id="card-<?php echo $row['user_id']; ?>">
                        <div class="card-inner">
                            
                            <div class="card-front">
                                <button class="share-card-btn" data-provider-name="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES, 'UTF-8'); ?>" data-service-name="<?php echo htmlspecialchars($row['sub_service_name'], ENT_QUOTES, 'UTF-8'); ?>" onclick="shareProvider(this.getAttribute('data-provider-name'), this.getAttribute('data-service-name'))" title="مشاركة الحساب"><i class="fa-solid fa-share-nodes"></i></button>
                                <div>
                                    <h4 class="provider-name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></h4>
                                    <hr class="card-divider">
                                    
                                    <!-- التقييم -->
                                    <div class="rating-stars mb-2">
                                        <?php 
                                        $rating = round($row['avg_rating'] ?? 0);
                                        $review_count = $row['rating_count'] ?? 0;
                                        for($i = 1; $i <= 5; $i++) {
                                            // النجوم الفعالة تأخذ اللون من CSS، وغير الفعالة نجعلها رمادية
                                            $style = ($i <= $rating) ? '' : 'style="color: #e4e5e9;"';
                                            echo '<i class="fa-solid fa-star" '.$style.'></i>';
                                        }
                                        ?>
                                        <span class="text-muted small fw-bold ms-1" style="font-size: 0.8rem;">(<?php echo $review_count; ?> تقييم)</span>
                                    </div>

                                    <p class="mb-1 fw-bold text-primary"><?php echo htmlspecialchars($row['sub_service_name']); ?></p>
                                    <p class="mb-1 text-muted" style="font-size: 0.9rem;">
                                        <i class="fa-solid fa-location-dot ms-1"></i> <?php echo htmlspecialchars($row['governorate']); ?>
                                    </p>

                                    <?php if($row['availability_type'] == 'always'): ?>
                                        <div class="time-box" style="background-color: #e8f5e9; color: #2e7d32; border-color: #c8e6c9;">
                                            <i class="fa-solid fa-check-circle text-success"></i> متاح دائماً
                                        </div>
                                    <?php elseif($row['availability_type'] == 'unavailable'): ?>
                                        <div class="time-box" style="background-color: #ffebee; color: #c62828; border-color: #ffcdd2;">
                                            <i class="fa-solid fa-ban text-danger"></i> غير متاح حالياً
                                        </div>
                                    <?php else: ?>
                                    <div class="time-box">
                                        <i class="fa-regular fa-clock"></i>
                                        <span style="font-size: 0.9rem;">من</span>
                                        <span dir="ltr" style="font-weight: 500;"><?php echo date('h:i A', strtotime($row['free_time_start'])); ?></span>
                                        <span style="font-size: 0.9rem;">إلى</span>
                                        <span dir="ltr" style="font-weight: 500;"><?php echo date('h:i A', strtotime($row['free_time_end'])); ?></span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="contact-info">
                                        <?php if(!$row['hide_phone']): ?>
                                            <p class="copy-clickable" onclick="copyToClipboard('<?php echo htmlspecialchars($row['phone']); ?>', this)" title="اضغط للنسخ السريع"><i class="fa-solid fa-phone"></i> <span><?php echo htmlspecialchars($row['phone']); ?></span></p>
                                        <?php endif; ?>
                                        <?php if(!empty($row['email'])): ?>
                                            <p class="copy-clickable" onclick="copyToClipboard('<?php echo htmlspecialchars($row['email']); ?>', this)" title="اضغط للنسخ السريع"><i class="fa-solid fa-envelope"></i> <span><?php echo htmlspecialchars($row['email']); ?></span></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($is_unavailable): ?>
                                    <?php if ($is_subscribed): ?>
                                        <form action="../php/manage_notifications.php" method="POST" class="m-0 w-100" style="margin-top: auto;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="unsubscribe_availability">
                                            <input type="hidden" name="provider_id" value="<?php echo $row['user_id']; ?>">
                                            <input type="hidden" name="main_id" value="<?php echo htmlspecialchars($main_service_id); ?>">
                                            <button type="submit" class="btn btn-secondary w-100 my-0 btn-subscribed" style="padding: 10px 0;" title="اضغط لإلغاء التنبيه">
                                                <span class="default-txt"><i class="fa-solid fa-bell-slash"></i> تم تفعيل التنبيه</span>
                                                <span class="hover-txt" style="display:none;"><i class="fa-solid fa-circle-xmark"></i> إلغاء التنبيه؟</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="../php/manage_notifications.php" method="POST" class="m-0 w-100" style="margin-top: auto;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="subscribe_availability">
                                            <input type="hidden" name="provider_id" value="<?php echo $row['user_id']; ?>">
                                            <input type="hidden" name="main_id" value="<?php echo htmlspecialchars($main_service_id); ?>">
                                            <button type="submit" class="btn btn-book w-100 mb-0"><i class="fa-regular fa-bell"></i> أعلمني عند التوفر</button>
                                        </form>
                                    <?php endif; ?>
                                <?php elseif ($has_any_active_request): ?>
                                    <button type="button" class="btn btn-secondary w-100 mb-0" disabled style="padding: 10px 0; cursor: not-allowed; margin-top: auto;">
                                        لديك طلب نشط حالياً
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-book" onclick="flipCard(<?php echo $row['user_id']; ?>)">
                                        احجز الآن
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="card-back">
                                <h5 class="fw-bold text-primary mb-3">تفاصيل الحجز</h5>
                                
                                <form action="../php/process_booking.php" method="POST" style="height: 100%; display: flex; flex-direction: column;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="provider_id" value="<?php echo $row['user_id']; ?>">
                                    <input type="hidden" name="service_id" value="<?php echo $row['service_id']; ?>">
                                    <input type="hidden" name="main_id" value="<?php echo htmlspecialchars($main_service_id); ?>">
                                    
                                    <textarea name="details" class="form-control mb-3" placeholder="اشرح المشكلة أو الخدمة المطلوبة..." required></textarea>
                                    
                                    <div style="margin-top: auto;">
                                        <button type="submit" name="confirm_booking" class="btn btn-success w-100 mb-2">
                                            تأكيد الحجز
                                        </button>
                                        
                                        <button type="button" class="btn btn-secondary w-100" onclick="flipCard(<?php echo $row['user_id']; ?>)">
                                            إلغاء
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5"><h4 class="text-muted">لا يوجد مقدمي خدمات مطابقين للبحث.</h4></div>
        <?php endif; ?>
    </div>

    <!-- قسم إخلاء المسؤولية ومعلومات الخدمة -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="bg-light p-4 shadow-sm border" style="border-radius: 15px; border-color: #e1e1e1;">
                <div class="d-flex align-items-start">
                    <i class="fa-solid fa-circle-info fs-3 ms-3" style="color: #104496; margin-top: 5px;"></i>
                    <div>
                        <h5 class="fw-bold mb-3" style="color: #104496;">معلومات هامة قبل الحجز:</h5>
                        <ul class="mb-0 text-muted" style="line-height: 1.8; padding-right: 20px; font-size: 0.95rem;">
                            <li><strong>تكلفة الخدمة:</strong> جميع الخدمات المعروضة في المنصة تكلف <strong>نقطة واحدة (1)</strong> تُخصم من رصيدك عند الحجز.</li>
                            <li><strong>آلية التقديم:</strong> تُقدم الخدمات بشكل وجاهي أو عن بُعد، وذلك بناءً على الاتفاق المسبق بينك وبين مقدم الخدمة.</li>
                            <li><strong>إخلاء مسؤولية:</strong> تُخلي منصة "إثراء" مسؤوليتها القانونية في حال كان مقدم الخدمة غير مؤهل أو غير صادق. ومع ذلك، نرجو منك استخدام زر <strong>(إبلاغ)</strong> فوراً لاتخاذ الإجراءات الإدارية الصارمة بحقه.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
.highlighted-card {
    border-radius: 20px !important;
    animation: bluePulse 0.65s 6 alternate ease-in-out forwards;
    z-index: 10;
}
@keyframes bluePulse {
    0% { 
        box-shadow: 0 0 5px rgba(0, 26, 117, 0.0);
        transform: scale(1);
    }
    100% { 
        box-shadow: 0 0 30px rgba(0, 26, 117, 1);
        transform: scale(1.03);
    }
}
</style>
<script>
    let serviceSearchTimer = null;
    function performLiveServicesSearch() {
        clearTimeout(serviceSearchTimer);
        serviceSearchTimer = setTimeout(() => {
            const form = document.getElementById('servicesSearchForm');
            if (!form) return;
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);

            window.history.replaceState({}, '', 'services_list.php?' + params.toString());

            fetch('services_list.php?' + params.toString())
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.getElementById('servicesCardsContainer');
                    if (newContainer) {
                        document.getElementById('servicesCardsContainer').innerHTML = newContainer.innerHTML;
                    }
                })
                .catch(err => console.error('Services live search error:', err));
        }, 200);
    }

    document.addEventListener('DOMContentLoaded', function() {
        var highlighted = document.querySelector('.highlighted-card');
        if (highlighted) {
            highlighted.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        const searchInput = document.getElementById('serviceSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', performLiveServicesSearch);
        }
    });

    // 1. كود إظهار الفلتر
    function toggleFilters() {
        var box = document.getElementById('filterOptions');
        box.style.display = (box.style.display === 'none') ? 'flex' : 'none';
        if(box.style.display === 'flex') box.style.animation = 'fadeIn 0.3s';
    }
    
    // إبقاء الفلاتر مفتوحة عند الاختيار
    <?php if(!empty($sub_id) || !empty($governorate) || !empty($available_now)): ?>
        document.getElementById('filterOptions').style.display = 'flex';
    <?php endif; ?>
    
    // 2. كود قلب البطاقة (كما هو)
    function flipCard(id) {
        var card = document.getElementById('card-' + id);
        if (card) card.classList.toggle('flipped');
    }

    // 3. 🔥 الحل لمشكلة النافبار (الجديد) 🔥
    document.addEventListener("DOMContentLoaded", function() {
        var navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(function(link) {
            // نبحث عن الرابط الذي يحتوي على كلمة "الخدمات" ونفعله
            if (link.textContent.trim().includes('الخدمات')) {
                link.classList.add('active');
            }
        });

        // 4. معالجة الرسائل المنبثقة (SweetAlert)
        const urlParams = new URLSearchParams(window.location.search);

        // رسائل خطأ الحجز
        if (urlParams.has('booking_error')) {
            let errorTitle = 'خطأ في الحجز';
            let errorText = '';
            switch(urlParams.get('booking_error')) {
                case 'self_booking':
                    errorText = 'عذراً، لا يمكنك حجز خدمة لنفسك.';
                    break;
                case 'active_request':
                    errorText = 'لديك طلب نشط بالفعل. لا يمكنك طلب خدمة جديدة حتى اكتمال أو إلغاء الطلب الحالي.';
                    break;
                case 'unavailable':
                    errorText = 'عذراً، مقدم الخدمة غير متاح حالياً (خارج أوقات العمل المحددة).';
                    break;
                case 'provider_unavailable':
                    errorText = 'عذراً، مقدم الخدمة هذا غير متاح حالياً.';
                    break;
                case 'no_points':
                    const points = urlParams.get('points') || 0;
                    errorText = `عذراً، رصيدك الحالي (${points} نقاط) لا يكفي لإتمام العملية.`;
                    break;
                case 'generic_error':
                default:
                    errorText = 'حدث خطأ أثناء معالجة الطلب. يرجى المحاولة لاحقاً.';
                    break;
            }
            Swal.fire({
                icon: 'error',
                title: errorTitle,
                text: errorText,
            });
            // إعادة قلب البطاقة لإظهار الخطأ
            if (window.location.hash) {
                const cardId = window.location.hash.substring(1);
                const card = document.getElementById(cardId);
                if (card && !card.classList.contains('flipped')) {
                    setTimeout(() => {
                        card.classList.add('flipped');
                    }, 300);
                }
            }
        }

        // رسالة نجاح الاشتراك في التنبيه
        if (urlParams.has('subscribe_success')) {
            Swal.fire({
                icon: 'success',
                title: 'تم تفعيل التنبيه',
                text: 'سيصلك إشعار عندما يصبح مقدم الخدمة متاحاً.',
            });
        }

        // رسالة نجاح إلغاء الاشتراك
        if (urlParams.has('unsubscribe_success')) {
            Swal.fire({
                icon: 'success',
                title: 'تم إلغاء التنبيه',
                text: 'تم إلغاء اشتراكك في تنبيهات هذا المستخدم.',
            });
        }
    });

    // 5. ميزة مشاركة بطاقة مقدم الخدمة
    function shareProvider(providerName, serviceName) {
        if (navigator.share) {
            navigator.share({
                title: 'خدمة مميزة على منصة إثراء',
                text: `وجدت مقدم خدمة ممتاز باسم (${providerName}) في مجال (${serviceName}) على منصة إثراء، تحقق منه الآن!`,
                url: window.location.href // الرابط الحالي
            }).catch(console.error);
        } else {
            Swal.fire('تنبيه', 'متصفحك لا يدعم ميزة المشاركة السريعة.', 'info');
        }
    }

    // 6. النسخ السريع لمعلومات التواصل
    function copyToClipboard(text, element) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHTML = element.innerHTML;
            element.innerHTML = '<i class="fa-solid fa-check text-success"></i> <span class="text-success fw-bold">تم النسخ</span>';
            setTimeout(() => {
                element.innerHTML = originalHTML;
            }, 1500);
        }).catch(err => console.error('فشل النسخ', err));
    }
</script>
</body>
</html>