<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- 1. جلب إحصائيات التقييم العامة ---
$sql_stats = "SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM reviews WHERE provider_id = ?";
$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$res_stats = $stmt_stats->get_result();
$row_stats = $res_stats->fetch_assoc();

$my_rating = $row_stats['avg_rating'] ? round($row_stats['avg_rating'], 1) : 0;
$my_rating_count = $row_stats['count'];
$stmt_stats->close();

// تحديد خيار الفرز
$sort_option = $_GET['sort'] ?? 'newest';
$order_clause = "ORDER BY r.created_at DESC";

switch ($sort_option) {
    case 'highest':
        $order_clause = "ORDER BY r.rating DESC, r.created_at DESC";
        break;
    case 'lowest':
        $order_clause = "ORDER BY r.rating ASC, r.created_at DESC";
        break;
    case 'newest':
    default:
        $order_clause = "ORDER BY r.created_at DESC";
        break;
}

// --- 2. جلب جميع التقييمات مع أسماء المقيّمين ---
$sql_reviews = "SELECT r.*, u.first_name, u.last_name 
                FROM reviews r
                JOIN users u ON r.reviewer_id = u.user_id
                WHERE r.provider_id = ?
                $order_clause";
$stmt_reviews = $conn->prepare($sql_reviews);
$stmt_reviews->bind_param("i", $user_id);
$stmt_reviews->execute();
$reviews = $stmt_reviews->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_reviews->close();

$page_title = 'تقييماتي - إثراء';
$page_css = '
    <link rel="stylesheet" href="../assets/css/account_style.css?v=' . filemtime(__DIR__ . '/../assets/css/account_style.css') . '">
    <link rel="stylesheet" href="../assets/css/user_reviews.css?v=' . filemtime(__DIR__ . '/../assets/css/user_reviews.css') . '">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
';
include '../includes/user_header.php';
?>

<?php include '../includes/user_navbar.php'; ?>

<div class="container">
    <div class="account-container">
        
        <a href="my_account.php" class="back-icon" title="العودة لحسابي">
            <img src="../assets/images/arrow-back-blue.svg" width="35">
        </a>

        <h1 class="page-title">تقييماتي والتعليقات</h1>

        <!-- بطاقة ملخص التقييمات -->
        <div class="summary-card">
            <div class="summary-rating-value"><?php echo $my_rating; ?></div>
            <div class="summary-stars text-warning">
                <?php 
                for($i=1; $i<=5; $i++) {
                    echo ($i <= round($my_rating)) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                }
                ?>
            </div>
            <div class="summary-count">بناءً على <?php echo $my_rating_count; ?> تقييم</div>
        </div>

        <!-- خيارات الفرز -->
        <div class="d-flex justify-content-between align-items-center mb-3 px-2">
            <h5 class="fw-bold m-0 text-secondary" style="font-size: 1.1rem;">أحدث التعليقات</h5>
            <form method="GET" action="user_reviews.php" class="d-flex align-items-center">
                <label for="sort" class="ms-2 text-muted fw-bold small">ترتيب حسب:</label>
                <select name="sort" id="sort" class="form-select form-select-sm border-0 bg-white shadow-sm" style="width: auto; border-radius: 10px; cursor: pointer;" onchange="this.form.submit()">
                    <option value="newest" <?php if($sort_option == 'newest') echo 'selected'; ?>>الأحدث أولاً</option>
                    <option value="highest" <?php if($sort_option == 'highest') echo 'selected'; ?>>الأعلى تقييماً</option>
                    <option value="lowest" <?php if($sort_option == 'lowest') echo 'selected'; ?>>الأقل تقييماً</option>
                </select>
            </form>
        </div>

        <!-- قائمة التعليقات -->
        <div class="reviews-list">
            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="reviewer-info">
                            <div class="reviewer-icon">
                                <i class="fa-solid fa-user fa-lg"></i>
                            </div>
                            <div class="reviewer-name">
                                <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                            </div>
                            <div class="review-date">
                                <?php echo date('Y/m/d', strtotime($review['created_at'])); ?>
                            </div>
                        </div>
                        <div class="review-content">
                            <div class="review-stars text-warning">
                                <?php 
                                for($i=1; $i<=5; $i++) {
                                    echo ($i <= $review['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                }
                                ?>
                            </div>
                            <?php if (!empty($review['comment'])): ?>
                                <p class="review-comment">
                                    <i class="fa-solid fa-quote-right"></i>
                                    <?php echo htmlspecialchars($review['comment']); ?>
                                </p>
                            <?php else: ?>
                                <p class="review-comment text-muted">
                                    <em>(لم يتم تقديم تعليق)</em>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fa-regular fa-star-half-stroke fa-4x text-muted mb-3" style="opacity: 0.2;"></i>
                    <h5 class="text-muted">لم تحصل على أي تقييمات بعد</h5>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>