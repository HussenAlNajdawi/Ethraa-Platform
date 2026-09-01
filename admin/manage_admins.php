<?php
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// حماية الصفحة: يسمح فقط للمدير العام أو من لديه صلاحية إدارة المشرفين
if (!isSuperAdmin() && !hasAdminPermission('manage_admins')) {
    header('Location: dashboard.php?error=access_denied');
    exit();
}

$current_admin_id = (int)$_SESSION['admin_id'];

// تعريف قائمة الصلاحيات المتاحة
$available_permissions = [
    'manage_users'         => ['title' => 'إدارة المستخدمين', 'desc' => 'عرض، تعديل، حظر وفك حظر حسابات المستخدمين', 'icon' => 'fa-users', 'color' => 'primary'],
    'manage_services'      => ['title' => 'إدارة الخدمات والمجالات', 'desc' => 'إضافة وتعديل وحذف المجالات والمهن', 'icon' => 'fa-briefcase', 'color' => 'success'],
    'manage_notifications' => ['title' => 'إرسال الإشعارات', 'desc' => 'إرسال إشعارات وتنبيهات فردية وجماعية', 'icon' => 'fa-bell', 'color' => 'info'],
    'manage_reports'       => ['title' => 'إدارة البلاغات', 'desc' => 'مراجعة البلاغات واتخاذ الإجراءات التأديبية', 'icon' => 'fa-triangle-exclamation', 'color' => 'danger'],
    'manage_appeals'       => ['title' => 'إدارة الاعتراضات والطعون', 'desc' => 'النظر في طعون المستخدمين المحظورين', 'icon' => 'fa-gavel', 'color' => 'warning'],
    'view_logs'            => ['title' => 'سجل النشاطات (Audit Logs)', 'desc' => 'الاطلاع على كافة تحركات المشرفين بالنظام', 'icon' => 'fa-list-check', 'color' => 'secondary'],
    'manage_settings'      => ['title' => 'إعدادات المنصة وخادم البريد', 'desc' => 'التحكم بالفوتر، وضع الصيانة، وإعدادات SMTP', 'icon' => 'fa-gear', 'color' => 'dark'],
    'manage_admins'        => ['title' => 'إدارة المشرفين والصلاحيات', 'desc' => 'إضافة وتعديل صلاحيات المشرفين الآخرين', 'icon' => 'fa-user-shield', 'color' => 'purple']
];

$msg = '';
$error = '';

// --- معالجة الطلبات ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("رمز حماية غير صالح.");
    }

    $action = $_POST['action'] ?? '';

    // 1. إضافة مشرف جديد
    if ($action === 'add_admin') {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] === 'super_admin' && isSuperAdmin()) ? 'super_admin' : 'sub_admin';
        $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_keys($_POST['permissions']) : [];

        if (empty($username) || empty($full_name) || empty($password)) {
            $error = 'يرجى ملء جميع الحقول الإلزامية.';
        } elseif (strlen($password) < 8) {
            $error = 'كلمة المرور يجب أن لا تقل عن 8 خانات.';
        } else {
            // التحقق من عدم تكرار اسم المستخدم
            $chk = $conn->prepare("SELECT admin_id FROM admins WHERE username = ?");
            $chk->bind_param("s", $username);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'اسم المستخدم هذا مستخدم مسبقاً، يرجى اختيار اسم آخر.';
                $chk->close();
            } else {
                $chk->close();
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $perms_json = ($role === 'super_admin') ? json_encode(['all']) : json_encode($perms);
                $status = 'active';

                $stmt = $conn->prepare("INSERT INTO admins (username, full_name, email, password, role, permissions, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("sssssss", $username, $full_name, $email, $hashed, $role, $perms_json, $status);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    logAdminAction($conn, $current_admin_id, 'ADD_ADMIN', "قام بإنشاء حساب مشرف جديد: $username ($full_name) بدور: $role");
                    $msg = "تمت إضافة المشرف بنجاح وتعيين الصلاحيات المحددة.";
                } else {
                    $error = "حدث خطأ أثناء إضافة المشرف.";
                }
            }
        }
    }

    // 2. تعديل الصلاحيات والدور
    elseif ($action === 'edit_permissions') {
        $target_id = (int)($_POST['target_admin_id'] ?? 0);
        $role = ($_POST['role'] === 'super_admin' && isSuperAdmin()) ? 'super_admin' : 'sub_admin';
        $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_keys($_POST['permissions']) : [];
        $perms_json = ($role === 'super_admin') ? json_encode(['all']) : json_encode($perms);

        // حماية المشرف الرئيسي من تخفيض رتبته
        if ($target_id === 1 && $role !== 'super_admin') {
            $error = 'لا يمكن تخفيض صلاحيات المدير العام الرئيسي للنظام.';
        } elseif ($target_id > 0) {
            $stmt = $conn->prepare("UPDATE admins SET role = ?, permissions = ?, session_version = session_version + 1 WHERE admin_id = ?");
            $stmt->bind_param("ssi", $role, $perms_json, $target_id);
            if ($stmt->execute()) {
                $stmt->close();
                logAdminAction($conn, $current_admin_id, 'UPDATE_ADMIN_PERMS', "قام بتعديل صلاحيات المشرف رقم: $target_id");
                $msg = "تم تحديث صلاحيات المشرف بنجاح.";
            } else {
                $error = "حدث خطأ أثناء تحديث الصلاحيات.";
            }
        }
    }

    // 3. تغيير كلمة مرور المشرف
    elseif ($action === 'change_admin_password') {
        $target_id = (int)($_POST['target_admin_id'] ?? 0);
        $new_pass = $_POST['new_password'] ?? '';

        if (strlen($new_pass) < 8) {
            $error = 'كلمة المرور يجب أن لا تقل عن 8 خانات.';
        } elseif ($target_id > 0) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ?, remember_token = NULL, session_version = session_version + 1 WHERE admin_id = ?");
            $stmt->bind_param("si", $hashed, $target_id);
            if ($stmt->execute()) {
                $stmt->close();
                logAdminAction($conn, $current_admin_id, 'CHANGE_ADMIN_PASS', "قام بتغيير كلمة مرور المشرف رقم: $target_id");
                $msg = "تم تغيير كلمة مرور المشرف بنجاح وتسجيل خروجه من الأجهزة الأخرى.";
            } else {
                $error = "حدث خطأ أثناء تغيير كلمة المرور.";
            }
        }
    }

    // 4. تغيير الحالة (تفعيل / تعطيل)
    elseif ($action === 'toggle_status') {
        $target_id = (int)($_POST['target_admin_id'] ?? 0);
        $new_status = ($_POST['new_status'] === 'active') ? 'active' : 'inactive';

        if ($target_id === 1 || $target_id === $current_admin_id) {
            $error = 'لا يمكنك تعطيل حسابك الشخصي أو حساب المدير الرئيسي.';
        } elseif ($target_id > 0) {
            $stmt = $conn->prepare("UPDATE admins SET status = ?, session_version = session_version + 1 WHERE admin_id = ?");
            $stmt->bind_param("si", $new_status, $target_id);
            if ($stmt->execute()) {
                $stmt->close();
                $status_txt = ($new_status === 'active') ? 'تفعيل' : 'تعطيل';
                logAdminAction($conn, $current_admin_id, 'TOGGLE_ADMIN_STATUS', "قام بـ $status_txt حساب المشرف رقم: $target_id");
                $msg = "تم $status_txt حساب المشرف بنجاح.";
            } else {
                $error = "حدث خطأ أثناء تعديل حالة الحساب.";
            }
        }
    }

    // 5. حذف المشرف
    elseif ($action === 'delete_admin') {
        $target_id = (int)($_POST['target_admin_id'] ?? 0);

        if ($target_id === 1 || $target_id === $current_admin_id) {
            $error = 'لا يمكنك حذف حساب المدير الرئيسي أو حسابك الحالي.';
        } elseif ($target_id > 0) {
            $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
            $stmt->bind_param("i", $target_id);
            if ($stmt->execute()) {
                $stmt->close();
                logAdminAction($conn, $current_admin_id, 'DELETE_ADMIN', "قام بحذف المشرف رقم: $target_id");
                $msg = "تم حذف المشرف نهائياً بنجاح.";
            } else {
                $error = "حدث خطأ أثناء حذف المشرف.";
            }
        }
    }
}

// جلب قائمة المشرفين
$admins_list = [];
$res = $conn->query("SELECT * FROM admins ORDER BY admin_id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $admins_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إدارة المشرفين والصلاحيات - إثراء</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dark_mode.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
    <style>
        .perm-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.78rem;
            padding: 4px 8px;
            border-radius: 6px;
            margin: 2px;
            font-weight: 600;
        }
        .perm-checkbox-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            transition: all 0.2s ease;
            background: #fff;
            height: 100%;
        }
        .perm-checkbox-card:hover {
            border-color: #021C7B;
            background: #f8fafc;
        }
        html.dark-mode .perm-checkbox-card {
            background: #242424;
            border-color: #404040;
        }
        html.dark-mode .perm-checkbox-card:hover {
            background: #2d2d2d;
            border-color: #66BF26;
        }
        .table-responsive {
            overflow: visible !important;
        }
        @media (max-width: 991.98px) {
            .table-responsive {
                overflow-x: auto !important;
                overflow-y: visible !important;
            }
        }
        html.dark-mode #viewPermsModalList .bg-light {
            background: #2a2a2a !important;
            border-color: #444 !important;
        }
        html.dark-mode #viewPermsModalList .text-dark {
            color: #f1f1f1 !important;
        }
        .dropdown-menu {
            z-index: 1060 !important;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <?php include 'sidebar.php'; ?>
        <div id="page-content-wrapper">
            <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-align-left primary-text fs-4 me-3" id="menu-toggle" style="cursor: pointer;"></i>
                    <h2 class="fs-2 m-0">إدارة المشرفين والصلاحيات</h2>
                </div>
            </nav>

            <div class="container-fluid px-4">
                <?php if (!empty($msg)): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            safeSwal({
                                icon: 'success',
                                title: 'تم بنجاح',
                                text: <?php echo json_encode($msg); ?>,
                                confirmButtonText: 'حسناً',
                                confirmButtonColor: '#021C7B'
                            });
                        });
                    </script>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            safeSwal({
                                icon: 'error',
                                title: 'خطأ',
                                text: <?php echo json_encode($error); ?>,
                                confirmButtonText: 'حسناً',
                                confirmButtonColor: '#021C7B'
                            });
                        });
                    </script>
                <?php endif; ?>

                <!-- بطاقة توضيحية -->
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 15px;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="admin-shield-icon-circle shadow-sm">
                                <i class="fa-solid fa-shield-halved fs-4"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold mb-1 text-primary">نظام التحكم في الوصول القائم على الأدوار (RBAC)</h5>
                                <p class="text-muted small mb-0">يمكنك هنا إنشاء حسابات للمشرفين الإضافيين، وتخصيص الصلاحيات المحددة لكل مشرف بدقة لحماية بيانات المنصة.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- جدول المشرفين -->
                <div class="card shadow-sm border-0 mb-4" style="border-radius: 15px;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                            <h4 class="m-0 text-primary fw-bold"><i class="fa-solid fa-users-gear me-2"></i> قائمة المشرفين الحالية</h4>
                            <button class="btn btn-admin-primary px-4 py-2 fw-bold shadow-sm" style="border-radius: 10px;" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                <i class="fa-solid fa-user-plus me-2"></i> إضافة مشرف جديد
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle text-center mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>اسم المشرف</th>
                                        <th>اسم المستخدم</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>الدور</th>
                                        <th style="min-width: 250px;">الصلاحيات الممنوحة</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins_list as $adm): 
                                        $adm_perms = json_decode($adm['permissions'] ?? '[]', true) ?: [];
                                        $is_super = ($adm['role'] === 'super_admin');
                                    ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo $adm['admin_id']; ?></td>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($adm['full_name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($adm['username']); ?></code></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($adm['email'] ?: '—'); ?></td>
                                            <td>
                                                <?php if ($is_super): ?>
                                                    <span class="status-pill main">مدير عام (Super Admin)</span>
                                                <?php else: ?>
                                                    <span class="status-pill sub">مشرف مخصص</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_super || in_array('all', $adm_perms)): ?>
                                                    <span class="status-pill main">وصول كامل (كافة الصلاحيات)</span>
                                                <?php elseif (empty($adm_perms)): ?>
                                                    <span class="status-pill secondary">لا توجد صلاحيات</span>
                                                <?php else: ?>
                                                    <button class="status-pill sub shadow-sm" style="cursor: pointer; border: 1px solid rgba(102, 191, 38, 0.4);" type="button" onclick="showPermissionsModal('<?php echo htmlspecialchars($adm['full_name'], ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($adm_perms), ENT_QUOTES); ?>)">
                                                        صلاحيات محددة (<?php echo count($adm_perms); ?>)
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($adm['status'] === 'active'): ?>
                                                    <span class="status-pill active">نشط</span>
                                                <?php else: ?>
                                                    <span class="status-pill blocked">معطل</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                                        خيارات
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 10px; z-index: 1050;">
                                                        <!-- تعديل الصلاحيات -->
                                                        <li>
                                                            <button class="dropdown-item py-2" onclick="openEditPermsModal(<?php echo htmlspecialchars(json_encode($adm)); ?>)">
                                                                تعديل الصلاحيات
                                                            </button>
                                                        </li>
                                                        <!-- تغيير كلمة المرور -->
                                                        <li>
                                                            <button class="dropdown-item py-2" onclick="openChangePassModal(<?php echo $adm['admin_id']; ?>, '<?php echo htmlspecialchars($adm['username']); ?>')">
                                                                تغيير كلمة المرور
                                                            </button>
                                                        </li>
                                                        
                                                        <?php if ($adm['admin_id'] != 1 && $adm['admin_id'] != $current_admin_id): ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <!-- تعطيل / تفعيل -->
                                                            <li>
                                                                <form method="POST" class="d-inline" onsubmit="confirmToggleAdmin(event, this, '<?php echo ($adm['status'] === 'active') ? 'تعطيل' : 'تفعيل'; ?>');">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                    <input type="hidden" name="action" value="toggle_status">
                                                                    <input type="hidden" name="target_admin_id" value="<?php echo $adm['admin_id']; ?>">
                                                                    <input type="hidden" name="new_status" value="<?php echo ($adm['status'] === 'active') ? 'inactive' : 'active'; ?>">
                                                                    <button type="submit" class="dropdown-item py-2 text-<?php echo ($adm['status'] === 'active') ? 'danger' : 'success'; ?>">
                                                                        <?php echo ($adm['status'] === 'active') ? 'تعطيل الحساب' : 'تفعيل الحساب'; ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <!-- حذف المشرف -->
                                                            <li>
                                                                <form method="POST" class="d-inline" onsubmit="confirmDeleteAdmin(event, this);">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                    <input type="hidden" name="action" value="delete_admin">
                                                                    <input type="hidden" name="target_admin_id" value="<?php echo $adm['admin_id']; ?>">
                                                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                                                        حذف المشرف
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal إضافة مشرف جديد -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-user-plus me-2"></i> إضافة مشرف جديد وتحديد صلاحياته</h5>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add_admin">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">الاسم الكامل <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" placeholder="مثال: أحمد خالد" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">اسم المستخدم (لتسجيل الدخول) <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" dir="ltr" style="text-align: right;" placeholder="admin_ahmed" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">البريد الإلكتروني</label>
                                <input type="email" name="email" class="form-control" dir="ltr" style="text-align: right;" placeholder="ahmed@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">كلمة المرور <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="لا تقل عن 8 خانات" minlength="8" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">نوع الدور</label>
                                <select name="role" id="addAdminRoleSelect" class="form-select" onchange="togglePermsSection('add')">
                                    <option value="sub_admin" selected>مشرف مخصص (صلاحيات محددة)</option>
                                    <?php if (isSuperAdmin()): ?>
                                        <option value="super_admin">مدير عام (Super Admin - وصول كامل لكافة الأقسام)</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <!-- قسم الصلاحيات -->
                        <div id="addPermsSection">
                            <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
                                <h6 class="fw-bold text-dark m-0"><i class="fa-solid fa-key text-warning me-2"></i> تحديد الصلاحيات الممنوحة:</h6>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPerms('add', true)">تحديد الكل</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllPerms('add', false)">إلغاء الكل</button>
                                </div>
                            </div>

                            <div class="row g-3">
                                <?php foreach ($available_permissions as $key => $p): ?>
                                    <div class="col-md-6">
                                        <div class="perm-checkbox-card">
                                            <div class="form-check">
                                                <input class="form-check-input add-perm-check" type="checkbox" name="permissions[<?php echo $key; ?>]" id="perm_add_<?php echo $key; ?>">
                                                <label class="form-check-label fw-bold" for="perm_add_<?php echo $key; ?>">
                                                    <i class="fa-solid <?php echo $p['icon']; ?> text-<?php echo $p['color']; ?> me-1"></i>
                                                    <?php echo $p['title']; ?>
                                                </label>
                                                <div class="text-muted small mt-1" style="font-size: 0.8rem;"><?php echo $p['desc']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-admin-primary px-4 fw-bold" style="border-radius: 8px;">حفظ وإضافة المشرف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تعديل الصلاحيات -->
    <div class="modal fade" id="editPermsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-sliders me-2"></i> تعديل صلاحيات المشرف: <span id="editAdminNameTitle" class="text-dark"></span></h5>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="edit_permissions">
                        <input type="hidden" name="target_admin_id" id="editAdminIdInput">

                        <div class="mb-3">
                            <label class="form-label fw-bold">نوع الدور</label>
                            <select name="role" id="editAdminRoleSelect" class="form-select" onchange="togglePermsSection('edit')">
                                <option value="sub_admin">مشرف مخصص (صلاحيات محددة)</option>
                                <?php if (isSuperAdmin()): ?>
                                    <option value="super_admin">مدير عام (Super Admin - وصول كامل لكافة الأقسام)</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- قسم الصلاحيات -->
                        <div id="editPermsSection">
                            <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
                                <h6 class="fw-bold text-dark m-0"><i class="fa-solid fa-key text-warning me-2"></i> الصلاحيات الممنوحة:</h6>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllPerms('edit', true)">تحديد الكل</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllPerms('edit', false)">إلغاء الكل</button>
                                </div>
                            </div>

                            <div class="row g-3">
                                <?php foreach ($available_permissions as $key => $p): ?>
                                    <div class="col-md-6">
                                        <div class="perm-checkbox-card">
                                            <div class="form-check">
                                                <input class="form-check-input edit-perm-check" type="checkbox" name="permissions[<?php echo $key; ?>]" id="perm_edit_<?php echo $key; ?>">
                                                <label class="form-check-label fw-bold" for="perm_edit_<?php echo $key; ?>">
                                                    <i class="fa-solid <?php echo $p['icon']; ?> text-<?php echo $p['color']; ?> me-1"></i>
                                                    <?php echo $p['title']; ?>
                                                </label>
                                                <div class="text-muted small mt-1" style="font-size: 0.8rem;"><?php echo $p['desc']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-admin-primary px-4 fw-bold" style="border-radius: 8px;">حفظ الصلاحيات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal تغيير كلمة المرور للمشرف -->
    <div class="modal fade" id="changePassModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-warning"><i class="fa-solid fa-key me-2"></i> تغيير كلمة المرور</h5>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="change_admin_password">
                        <input type="hidden" name="target_admin_id" id="passAdminIdInput">

                        <p class="text-muted small mb-3">تغيير كلمة المرور للمشرف: <strong id="passAdminUsername" class="text-dark"></strong></p>

                        <div class="mb-3">
                            <label class="form-label fw-bold">كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" placeholder="8 خانات على الأقل" minlength="8" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning px-4 fw-bold">تحديث كلمة المرور</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal عرض الصلاحيات الممنوحة -->
    <div class="modal fade" id="viewPermissionsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-success" id="viewPermsModalTitle">الصلاحيات الممنوحة</h5>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">قائمة الصلاحيات المتاحة والمفعلة لهذا المشرف:</p>
                    <div id="viewPermsModalList" class="d-flex flex-column gap-2">
                        <!-- يتم تعبئتها ديناميكياً عبر JS -->
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary px-4 w-100 fw-bold" style="border-radius: 10px;" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        var el = document.getElementById("wrapper");
        var toggleButton = document.getElementById("menu-toggle");
        if (toggleButton && el) {
            toggleButton.onclick = function () { el.classList.toggle("toggled"); };
        }

        const availablePermsMap = <?php echo json_encode($available_permissions); ?>;

        function showPermissionsModal(adminName, permsList) {
            document.getElementById('viewPermsModalTitle').textContent = 'صلاحيات المشرف: ' + adminName;
            const listContainer = document.getElementById('viewPermsModalList');
            listContainer.innerHTML = '';

            if (!permsList || permsList.length === 0) {
                listContainer.innerHTML = '<div class="alert alert-light text-center text-muted mb-0">لا توجد صلاحيات ممنوحة لهذا المشرف.</div>';
            } else {
                permsList.forEach(key => {
                    if (availablePermsMap[key]) {
                        const item = document.createElement('div');
                        item.className = 'p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between';
                        item.innerHTML = `
                            <div>
                                <div class="fw-bold text-dark mb-1">• ${availablePermsMap[key].title}</div>
                                <div class="text-muted small">${availablePermsMap[key].desc}</div>
                            </div>
                            <span class="status-pill active" style="min-width: unset; padding: 4px 14px; font-size: 0.8rem;">مفعلة</span>
                        `;
                        listContainer.appendChild(item);
                    }
                });
            }

            new bootstrap.Modal(document.getElementById('viewPermissionsModal')).show();
        }

        function togglePermsSection(mode) {
            const select = document.getElementById(mode + 'AdminRoleSelect');
            const section = document.getElementById(mode + 'PermsSection');
            if (select.value === 'super_admin') {
                section.style.display = 'none';
            } else {
                section.style.display = 'block';
            }
        }

        function selectAllPerms(mode, check) {
            const checks = document.querySelectorAll('.' + mode + '-perm-check');
            checks.forEach(c => c.checked = check);
        }

        function openEditPermsModal(adm) {
            document.getElementById('editAdminIdInput').value = adm.admin_id;
            document.getElementById('editAdminNameTitle').textContent = adm.full_name + ' (' + adm.username + ')';
            
            const roleSelect = document.getElementById('editAdminRoleSelect');
            roleSelect.value = adm.role;

            let perms = [];
            try {
                perms = JSON.parse(adm.permissions) || [];
            } catch(e) {}

            // تعيين الـ Checkboxes
            const checks = document.querySelectorAll('.edit-perm-check');
            checks.forEach(c => {
                const key = c.name.match(/permissions\[(.*?)\]/)[1];
                c.checked = perms.includes(key) || perms.includes('all');
            });

            togglePermsSection('edit');
            new bootstrap.Modal(document.getElementById('editPermsModal')).show();
        }

        function openChangePassModal(id, username) {
            document.getElementById('passAdminIdInput').value = id;
            document.getElementById('passAdminUsername').textContent = username;
            new bootstrap.Modal(document.getElementById('changePassModal')).show();
        }

        function confirmToggleAdmin(e, form, actionText) {
            e.preventDefault();
            Swal.fire({
                title: 'تأكيد العملية',
                text: 'هل أنت متأكد من ' + actionText + ' حساب هذا المشرف؟',
                icon: 'warning',
                showCancelButton: true,
                showCloseButton: false,
                confirmButtonColor: '#021C7B',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، تأكيد',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function confirmDeleteAdmin(e, form) {
            e.preventDefault();
            Swal.fire({
                title: 'حذف المشرف نهائياً',
                text: 'تحذير: هل أنت متأكد من رغبتك بحذف حساب هذا المشرف نهائياً من النظام؟',
                icon: 'error',
                showCancelButton: true,
                showCloseButton: false,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>
