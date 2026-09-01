<?php
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
requireAdminPermission('manage_services');

// إضافة خدمة جديدة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_service'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $name = $_POST['name'];
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    
    // معالجة رفع الصورة بأمان
    $image_val = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size = 3 * 1024 * 1024; // 3MB

        $tmp_name = $_FILES['image']['tmp_name'];
        $filename = $_FILES['image']['name'];
        $file_size = $_FILES['image']['size'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($file_size <= $max_size && in_array($ext, $allowed_exts)) {
            $mime = mime_content_type($tmp_name);
            $img_info = @getimagesize($tmp_name);

            if (in_array($mime, $allowed_mimes) && $img_info !== false) {
                $new_name = 'srv_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $target_dir = '../assets/images/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                
                if (move_uploaded_file($tmp_name, $target_dir . $new_name)) {
                    $image_val = $new_name;
                }
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO services (name, parent_id, image) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $name, $parent_id, $image_val);
    $stmt->execute();
    $stmt->close();
    header("Location: services.php");
}

// تعديل خدمة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_service'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $id = intval($_POST['service_id']);
    $name = $_POST['name'];
    $stmt = $conn->prepare("UPDATE services SET name = ? WHERE service_id = ?");
    $stmt->bind_param("si", $name, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: services.php");
}

// حذف خدمة
if (isset($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("طلب غير صالح.");
    }
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: services.php");
}

// جلب الخدمات الرئيسية
$main_services_res = $conn->query("SELECT * FROM services WHERE parent_id IS NULL ORDER BY name");
$main_services = [];
while($row = $main_services_res->fetch_assoc()) {
    // جلب الخدمات الفرعية لكل خدمة رئيسية
    $parent_id = $row['service_id'];
    
    // حساب عدد المسجلين في الخدمة الرئيسية (مجموع الفرعية)
    $stmt1 = $conn->prepare("SELECT COUNT(*) as c FROM users u JOIN services s ON u.service_id = s.service_id WHERE s.parent_id = ?");
    $stmt1->bind_param("i", $parent_id);
    $stmt1->execute();
    $count_main = $stmt1->get_result()->fetch_assoc()['c'];
    $stmt1->close();
    $row['users_count'] = $count_main;

    $stmt2 = $conn->prepare("SELECT * FROM services WHERE parent_id = ? ORDER BY name");
    $stmt2->bind_param("i", $parent_id);
    $stmt2->execute();
    $subs_res = $stmt2->get_result();
    $subs = [];
    while($sub = $subs_res->fetch_assoc()) {
        // حساب عدد المسجلين في الخدمة الفرعية
        $sub_id = $sub['service_id'];
        $stmt3 = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE service_id = ?");
        $stmt3->bind_param("i", $sub_id);
        $stmt3->execute();
        $count_sub = $stmt3->get_result()->fetch_assoc()['c'];
        $stmt3->close();
        $sub['users_count'] = $count_sub;
        $subs[] = $sub;
    }
    $main_services[] = ['main' => $row, 'subs' => $subs];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة الخدمات - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">إدارة الخدمات</h2>
                </div>
            </nav>
            <div class="container-fluid px-4">
                
                <!-- نموذج الإضافة -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-3">إضافة خدمة جديدة</h5>
                                <form method="POST" class="d-flex gap-2" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="text" name="name" class="form-control" placeholder="اسم الخدمة" required>
                                    <select name="parent_id" class="form-select">
                                        <option value="">خدمة رئيسية (تصنيف جديد)</option>
                                        <?php foreach($main_services as $item): ?>
                                            <option value="<?php echo $item['main']['service_id']; ?>"><?php echo htmlspecialchars($item['main']['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="file" name="image" class="form-control" accept="image/*" style="width: 250px;">
                                    <button type="submit" name="add_service" class="btn btn-primary text-nowrap" style="background-color: var(--main-bg-color); border-color: var(--main-bg-color);">إضافة</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جدول الخدمات -->
                <div class="row">
                    <div class="col">
                        <div class="table-responsive bg-white rounded shadow-sm">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>اسم الخدمة</th>
                                        <th>التصنيف</th>
                                        <th>المسجلين</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($main_services as $item): ?>
                                        <!-- الخدمة الرئيسية -->
                                        <tr class="table-light">
                                            <td><strong><?php echo $item['main']['service_id']; ?></strong></td>
                                            <td class="fw-bold text-primary position-relative text-center">
                                                <?php if(!empty($item['subs'])): ?>
                                                    <button class="btn btn-sm btn-link text-decoration-none p-0 me-2 toggle-subs" type="button" data-bs-toggle="collapse" data-bs-target="#subs-<?php echo $item['main']['service_id']; ?>" aria-expanded="false">
                                                        <i class="fas fa-chevron-down"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($item['main']['name']); ?>
                                            </td>
                                            <td>
                                                <div class="status-pill main">رئيسي</div>
                                            </td>
                                            <td>
                                                <div class="count-badge main-count"><?php echo $item['main']['users_count']; ?></div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-id="<?php echo (int)$item['main']['service_id']; ?>" data-name="<?php echo htmlspecialchars($item['main']['name'], ENT_QUOTES, 'UTF-8'); ?>" onclick="openEditModal(this.getAttribute('data-id'), this.getAttribute('data-name'))" title="تعديل الخدمة">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                            <a href="?delete=<?php echo $item['main']['service_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-danger" onclick="confirmDelete(event, this.href, 'حذف الخدمة الرئيسية سيؤدي لحذف جميع الخدمات الفرعية التابعة لها! هل أنت متأكد؟')" title="حذف الخدمة">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        
                                        <!-- الخدمات الفرعية -->
                                        <?php if(!empty($item['subs'])): ?>
                                            <tbody class="collapse" id="subs-<?php echo $item['main']['service_id']; ?>">
                                                <?php 
                                                $subs_count = count($item['subs']);
                                                foreach($item['subs'] as $index => $sub): 
                                                    $is_last = ($index === $subs_count - 1);
                                                ?>
                                                    <tr>
                                                        <td><?php echo $sub['service_id']; ?></td>
                                                        <td class="position-relative text-center">
                                                            <div class="tree-line-vertical <?php echo $is_last ? 'last' : ''; ?>"></div>
                                                            <div class="tree-line-horizontal"></div>
                                                            <?php echo htmlspecialchars($sub['name']); ?>
                                                        </td>
                                                        <td>
                                                            <div class="status-pill sub">فرعي</div>
                                                        </td>
                                                        <td>
                                                            <div class="count-badge"><?php echo $sub['users_count']; ?></div>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary" data-id="<?php echo (int)$sub['service_id']; ?>" data-name="<?php echo htmlspecialchars($sub['name'], ENT_QUOTES, 'UTF-8'); ?>" onclick="openEditModal(this.getAttribute('data-id'), this.getAttribute('data-name'))" title="تعديل الخدمة">
                                                                <i class="fas fa-pen"></i>
                                                            </button>
                                                        <a href="?delete=<?php echo $sub['service_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(event, this.href, 'هل أنت متأكد من حذف هذه الخدمة الفرعية؟')" title="حذف الخدمة">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById("menu-toggle").onclick = function () { document.getElementById("wrapper").classList.toggle("toggled"); };

        function openEditModal(id, name) {
            document.getElementById('editServiceId').value = id;
            document.getElementById('editServiceName').value = name;
            var myModal = new bootstrap.Modal(document.getElementById('editServiceModal'));
            myModal.show();
        }

        // تدوير السهم عند الفتح/الإغلاق
        document.querySelectorAll('.toggle-subs').forEach(btn => {
            btn.addEventListener('click', function() {
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-rotate-180');
            });
        });

        function confirmDelete(e, url, message) {
            e.preventDefault();
            document.getElementById('confirmDeleteBtn').href = url;
            document.getElementById('deleteMessage').innerText = message;
            var myModal = new bootstrap.Modal(document.getElementById('deleteServiceModal'));
            myModal.show();
        }

        // تفعيل التلميحات
        document.addEventListener("DOMContentLoaded", function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

    <!-- Modal تعديل الخدمة -->
    <div class="modal fade" id="editServiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
                <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
                    <div style="width: 70px; height: 70px; background-color: #e3f2fd; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                        <i class="fa-solid fa-pen" style="color: #0d6efd; font-size: 30px;"></i>
                    </div>
                    <h4 class="modal-title fw-bold text-primary" style="font-family: 'Cairo', sans-serif;">تعديل الخدمة</h4>
                </div>
                <div class="modal-body pt-3">
                    <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="edit_service" value="1">
                        <input type="hidden" name="service_id" id="editServiceId">
                        <div class="mb-4">
                            <label class="form-label text-muted fw-bold small">اسم الخدمة</label>
                            <input type="text" name="name" id="editServiceName" class="form-control bg-light border-0" required style="border-radius: 12px; padding: 12px;">
                        </div>
                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-light fw-bold px-4 py-2" style="border-radius: 12px; background-color: #f1f3f5; min-width: 120px;" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary fw-bold px-4 py-2" style="border-radius: 12px; min-width: 120px; box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal حذف الخدمة -->
    <div class="modal fade" id="deleteServiceModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
          <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
            <div style="width: 80px; height: 80px; background-color: #fff5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; box-shadow: 0 5px 15px rgba(220, 53, 69, 0.1);">
                <i class="fa-solid fa-trash-can" style="color: #dc3545; font-size: 36px;"></i>
            </div>
            <h4 class="modal-title fw-bold text-danger" style="font-family: 'Cairo', sans-serif;">حذف الخدمة</h4>
          </div>
          <div class="modal-body text-center pt-2">
            <p class="text-muted fw-bold mb-4" style="font-size: 1.1rem;" id="deleteMessage">
                هل أنت متأكد من حذف هذه الخدمة؟
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-light fw-bold px-4 py-2" style="border-radius: 12px; background-color: #f1f3f5; min-width: 120px;" data-bs-dismiss="modal">تراجع</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger fw-bold px-4 py-2" style="border-radius: 12px; min-width: 120px; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);">نعم، احذف</a>
            </div>
          </div>
        </div>
      </div>
    </div>
</body>
</html>