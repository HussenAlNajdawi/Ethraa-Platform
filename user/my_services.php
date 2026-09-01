<?php 
session_start();
require_once '../config/db_connect.php'; 

// مصفوفة الخدمات
// ملاحظة: تأكد أن صورك بصيغة .png، إذا كانت .jpg عدلها في الكود أدناه
// تم التعديل لجلب الخدمات من قاعدة البيانات لتعكس تغييرات الأدمن
$services = [];
$sql_services = "SELECT * FROM services WHERE parent_id IS NULL ORDER BY service_id ASC";
$res_services = $conn->query($sql_services);

// خريطة الصور (ربط الـ ID بالصورة)
$images_map = [
    1 => 'تعليم.png',
    2 => 'صحة.png',
    3 => 'قانون.png',
    4 => 'تقنية.png',
    5 => 'مجتمعية.png',
    6 => 'مهنية.png'
];

while ($row = $res_services->fetch_assoc()) {
    $id = $row['service_id'];
    // استخدام الصورة المحددة أو صورة افتراضية للخدمات الجديدة
    if (!empty($row['image'])) {
        $img = $row['image'];
    } else {
        $img = isset($images_map[$id]) ? $images_map[$id] : 'مجتمعية.png'; 
    }
    
    $services[] = [
        'id' => $id,
        'name' => $row['name'],
        'img' => $img,
        'link' => 'services_list.php?main_id=' . $id
    ];
}
$page_title = 'الخدمات الرئيسية - إثراء';
$page_css = '<link rel="stylesheet" href="../assets/css/services.css?v=' . filemtime(__DIR__ . '/../assets/css/services.css') . '">';
include '../includes/user_header.php'; 
?>


<?php include '../includes/user_navbar.php'; ?>

<div class="full-page-container">
    <div class="container">
        
        <div class="row justify-content-center g-4">
            <?php foreach ($services as $service): ?>
            <div class="col-6 col-sm-6 col-md-6 col-lg-4 d-flex justify-content-center">
                <a href="<?php echo htmlspecialchars($service['link'], ENT_QUOTES, 'UTF-8'); ?>" class="service-card">
                    
                    <div class="white-box">
                        <img src="../assets/images/<?php echo htmlspecialchars($service['img'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    
                    <h3 class="service-title"><?php echo htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>