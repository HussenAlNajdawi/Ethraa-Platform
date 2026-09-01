<?php
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }
requireAdminPermission('manage_users');

// معالجة الإجراءات السريعة
if (isset($_GET['action']) && isset($_GET['id'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die("طلب غير صالح.");
    }
    
    $id = intval($_GET['id']);
    $action_taken = '';
    if ($_GET['action'] == 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $action_taken = 'حذف حساب مستخدم';
    } elseif ($_GET['action'] == 'ban') {
        $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $action_taken = 'حظر نهائي لمستخدم';
    } elseif ($_GET['action'] == 'activate') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        
        $stmt2 = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND type = 'warning'");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $stmt2->close();
        $action_taken = 'تفعيل حساب مستخدم / رفع الحظر';
    }
    
    if ($action_taken) {
        logAdminAction($conn, $_SESSION['admin_id'], 'MANAGE_USER', "$action_taken (رقم المستخدم: $id)");
    }
    header("Location: users.php");
    exit();
}

// استقبال نص البحث
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest'; // القيمة الافتراضية
$status_filter = $_GET['status'] ?? ''; // فلتر الحالة

// تطبيق الحظر التلقائي لمن لديهم 3 إنذارات أو أكثر
$conn->query("UPDATE users u SET status = 'banned' WHERE (SELECT COUNT(*) FROM notifications n WHERE n.user_id = u.user_id AND n.type = 'warning') >= 3 AND status = 'active'");

// بناء الاستعلام مع دعم البحث
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM notifications n WHERE n.user_id = u.user_id AND n.type = 'warning') as warnings_count 
        FROM users u WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $s = '%' . $search . '%';
    $sql .= " AND (u.user_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)";
    $types .= "ssss";
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
}

if (!empty($status_filter)) {
    $sql .= " AND u.status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

// منطق الترتيب
switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY u.user_id ASC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY u.first_name ASC, u.last_name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY u.first_name DESC, u.last_name DESC";
        break;
    default: // newest
        $sql .= " ORDER BY u.user_id DESC";
        break;
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة المستخدمين - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/dark_mode.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/admin.css'); ?>">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/sweetalert_custom.css">
    <!-- منع الوميض الأبيض (FOUC) -->
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <style>
        /* تنسيق زر تصدير الإكسيل */
        .btn-export-excel {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #2e7d32;
        }
        .btn-export-excel:hover {
            background-color: #2e7d32;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
        }
        
        /* تأثير النقر السريع للنسخ في الإدارة */
        .copy-clickable {
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 6px;
            padding: 4px 8px;
        }
        .copy-clickable:hover {
            background-color: rgba(2, 28, 123, 0.08);
            color: var(--main-text-color) !important;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle"></i>
                    <h2 class="fs-2 m-0">إدارة المستخدمين</h2>
                </div>
            </nav>
            <div class="container-fluid px-4">
                
                <!-- شريط البحث والعنوان -->
                <div class="d-flex justify-content-start align-items-center mt-4 mb-3">
                    <form method="GET" action="users.php" class="m-0 d-flex gap-3" id="searchForm" onsubmit="return false;">
                        <div class="admin-search-box">
                            <img src="../assets/images/search.svg" class="admin-search-icon" alt="search">
                            <input type="text" name="search" id="searchInput" placeholder="بحث بالاسم، الهاتف أو الـ ID..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                            <span id="clearSearch" class="clear-search-btn" style="display: none;">&times;</span>
                        </div>
                        
                        <select name="status" id="statusFilter" class="form-select" style="width: 150px; border-radius: 12px; border: 1px solid #e1e1e1;">
                            <option value="">كل الحالات</option>
                            <option value="active" <?php if($status_filter == 'active') echo 'selected'; ?>>نشط فقط</option>
                            <option value="banned" <?php if($status_filter == 'banned') echo 'selected'; ?>>محظور فقط</option>
                        </select>

                        <select name="sort" id="sortFilter" class="form-select" style="width: 200px; border-radius: 12px; border: 1px solid #e1e1e1;">
                            <option value="newest" <?php if($sort == 'newest') echo 'selected'; ?>>الأحدث (ID تنازلي)</option>
                            <option value="oldest" <?php if($sort == 'oldest') echo 'selected'; ?>>الأقدم (ID تصاعدي)</option>
                            <option value="name_asc" <?php if($sort == 'name_asc') echo 'selected'; ?>>الاسم (أ - ي)</option>
                            <option value="name_desc" <?php if($sort == 'name_desc') echo 'selected'; ?>>الاسم (ي - أ)</option>
                        </select>
                        
                        <button type="button" class="btn btn-export-excel ms-auto d-flex align-items-center" onclick="exportTableToCSV('المستخدمين_إثراء.csv')" style="border-radius: 12px; font-weight: bold; transition: all 0.3s ease;">
                            <i class="fas fa-file-excel me-2"></i> تصدير لإكسيل
                        </button>
                    </form>
                </div>

                <div class="row">
                    <div class="col">
                        <div class="table-responsive bg-white rounded shadow-sm" id="usersTableContainer">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>الاسم</th>
                                        <th>الهاتف</th>
                                        <th>الإنذارات</th>
                                        <th>الحالة</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($users->num_rows > 0): ?>
                                        <?php while($row = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="copy-clickable fw-bold text-primary" onclick="copyAdminData('<?php echo $row['user_id']; ?>', this)" title="نسخ الـ ID"><?php echo $row['user_id']; ?></span>
                                            </td>
                                            <td>
                                                <a href="user_details.php?id=<?php echo $row['user_id']; ?>" class="text-decoration-none fw-bold text-primary" title="عرض تفاصيل المستخدم">
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                </a>
                                            </td>
                                            <td dir="ltr">
                                                <span class="copy-clickable" onclick="copyAdminData('<?php echo htmlspecialchars($row['phone']); ?>', this)" title="نسخ الرقم"><?php echo htmlspecialchars($row['phone']); ?></span>
                                            </td>
                                            <td>
                                                <?php if($row['warnings_count'] > 0): ?>
                                                    <span class="badge bg-danger rounded-pill"><?php echo $row['warnings_count']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($row['status'] == 'active'): ?>
                                                    <div class="status-pill active">
                                                        نشط
                                                    </div>
                                                <?php else: ?>
                                                    <div class="status-pill blocked">
                                                        محظور
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center justify-content-center gap-3">
                                                    <div class="status-toggle-wrapper m-0">
                                                        <img src="../assets/images/active.svg" class="status-icon" alt="active" style="width: 22px;">
                                                        <?php if($row['status'] == 'active'): ?>
                                                        <a href="?action=ban&id=<?php echo $row['user_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="status-btn active-btn" title="حظر المستخدم" onclick="confirmBan(event, this.href)"></a>
                                                        <?php else: ?>
                                                        <a href="?action=activate&id=<?php echo $row['user_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="status-btn blocked-btn" title="تفعيل المستخدم" onclick="confirmUnban(event, this.href)"></a>
                                                        <?php endif; ?>
                                                        <img src="../assets/images/block.svg" class="status-icon" alt="block" style="width: 22px;">
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted fw-bold">لا يوجد مستخدمين مطابقين للبحث.</td>
                                        </tr>
                                    <?php endif; ?>
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

        function confirmBan(e, url) {
            e.preventDefault();
            document.getElementById('confirmBtn').href = url;
            var myModal = new bootstrap.Modal(document.getElementById('confirmModal'));
            myModal.show();
        }

        function confirmUnban(e, url) {
            e.preventDefault();
            document.getElementById('confirmUnbanBtn').href = url;
            var myModal = new bootstrap.Modal(document.getElementById('confirmUnbanModal'));
            myModal.show();
        }

        // كود البحث اللحظي المباشر أثناء الكتابة
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.getElementById('clearSearch');
        const statusFilter = document.getElementById('statusFilter');
        const sortFilter = document.getElementById('sortFilter');

        if (searchInput.value.trim() !== "") clearBtn.style.display = "block";

        let liveSearchTimer = null;
        function performLiveSearch() {
            clearTimeout(liveSearchTimer);
            liveSearchTimer = setTimeout(() => {
                const searchVal = searchInput.value.trim();
                const statusVal = statusFilter.value;
                const sortVal = sortFilter.value;

                const params = new URLSearchParams({
                    search: searchVal,
                    status: statusVal,
                    sort: sortVal
                });

                window.history.replaceState({}, '', 'users.php?' + params.toString());

                fetch('users.php?' + params.toString())
                    .then(res => res.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContainer = doc.getElementById('usersTableContainer');
                        if (newContainer) {
                            document.getElementById('usersTableContainer').innerHTML = newContainer.innerHTML;
                        }
                    })
                    .catch(err => console.error('Live search error:', err));
            }, 200);
        }

        searchInput.addEventListener('input', function() {
            clearBtn.style.display = this.value.trim() !== "" ? "block" : "none";
            performLiveSearch();
        });

        clearBtn.addEventListener('click', function() {
            searchInput.value = "";
            clearBtn.style.display = "none";
            searchInput.focus();
            performLiveSearch();
        });

        statusFilter.addEventListener('change', performLiveSearch);
        sortFilter.addEventListener('change', performLiveSearch);

        // دالة تصدير الجدول إلى ملف إكسيل/CSV
        function exportTableToCSV(filename) {
            var csv = [];
            var rows = document.querySelectorAll("table tr");
            
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll("td, th");
                
                // نتجاوز العمود الأخير (الإجراءات)
                for (var j = 0; j < cols.length - 1; j++) 
                    row.push('"' + cols[j].innerText.replace(/"/g, '""').trim() + '"');
                
                csv.push(row.join(","));
            }

            // إضافة BOM لدعم اللغة العربية في إكسيل
            var csvFile = new Blob(["\uFEFF" + csv.join("\n")], {type: "text/csv;charset=utf-8;"});
            var downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.click();
        }

        // تفعيل التلميحات (Tooltips)
        document.addEventListener("DOMContentLoaded", function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // دالة النسخ السريع للأدمن
        function copyAdminData(text, element) {
            navigator.clipboard.writeText(text).then(() => {
                const originalHTML = element.innerHTML;
                element.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
                setTimeout(() => {
                    element.innerHTML = originalHTML;
                }, 1000);
            }).catch(err => console.error('فشل النسخ', err));
        }
    </script>

    <!-- Modal تأكيد الحظر -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
          <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
            <div style="width: 80px; height: 80px; background-color: #fff5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; box-shadow: 0 5px 15px rgba(220, 53, 69, 0.1);">
                <i class="fa-solid fa-user-slash" style="color: #dc3545; font-size: 36px;"></i>
            </div>
            <h4 class="modal-title fw-bold text-danger" style="font-family: 'Cairo', sans-serif;">تأكيد الحظر</h4>
          </div>
          <div class="modal-body text-center pt-2">
            <p class="text-muted fw-bold mb-4" style="font-size: 1.1rem;">
                هل أنت متأكد من رغبتك في حظر هذا المستخدم؟<br>
                <span class="small text-secondary" style="font-weight: normal;">لن يتمكن المستخدم من تسجيل الدخول بعد ذلك.</span>
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-light fw-bold px-4 py-2" style="border-radius: 12px; background-color: #f1f3f5; min-width: 120px;" data-bs-dismiss="modal">تراجع</button>
                <a href="#" id="confirmBtn" class="btn btn-danger fw-bold px-4 py-2" style="border-radius: 12px; min-width: 120px; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);">نعم، احظر</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal تأكيد فك الحظر -->
    <div class="modal fade" id="confirmUnbanModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 25px; border: none; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
          <div class="modal-header border-0 justify-content-center flex-column align-items-center pb-0">
            <div style="width: 80px; height: 80px; background-color: #e8f5e9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; box-shadow: 0 5px 15px rgba(46, 125, 50, 0.1);">
                <i class="fa-solid fa-user-check" style="color: #2e7d32; font-size: 36px;"></i>
            </div>
            <h4 class="modal-title fw-bold text-success" style="font-family: 'Cairo', sans-serif;">فك الحظر</h4>
          </div>
          <div class="modal-body text-center pt-2">
            <p class="text-muted fw-bold mb-4" style="font-size: 1.1rem;">
                هل أنت متأكد من رغبتك في تفعيل هذا المستخدم؟<br>
                <span class="small text-secondary" style="font-weight: normal;">سيتم تصفير عداد الإنذارات تلقائياً عند التفعيل.</span>
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-light fw-bold px-4 py-2" style="border-radius: 12px; background-color: #f1f3f5; min-width: 120px;" data-bs-dismiss="modal">تراجع</button>
                <a href="#" id="confirmUnbanBtn" class="btn btn-success fw-bold px-4 py-2" style="border-radius: 12px; min-width: 120px; box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);">نعم، تفعيل</a>
            </div>
          </div>
        </div>
      </div>
    </div>
</body>
</html>