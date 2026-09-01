<?php
require_once '../config/db_connect.php';
require_once 'admin_functions.php';

if (!isset($_SESSION['admin_id'])) { header('Location: admin_login.php'); exit(); }
requireAdminPermission('view_logs');

$sql = "SELECT l.*, a.full_name, a.username FROM admin_logs l LEFT JOIN admins a ON l.admin_id = a.admin_id ORDER BY l.created_at DESC LIMIT 500";
$logs = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>سجل نشاطات الإدارة - إثراء</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <link rel='stylesheet' href='../assets/css/style.css'>
    <link rel='stylesheet' href='../assets/css/dark_mode.css'>
    <link rel='stylesheet' href='../assets/css/admin.css'>
    <script>if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark-mode');</script>
</head>
<body>
    <div class='d-flex' id='wrapper'>
        <?php include 'sidebar.php'; ?>
        <div id='page-content-wrapper'>
            <nav class='navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4'>
                <div class='d-flex align-items-center'>
                    <i class='fas fa-align-left primary-text fs-4 me-3' id='menu-toggle'></i>
                    <h2 class='fs-2 m-0'>سجل النشاطات (Audit Logs)</h2>
                </div>
            </nav>
            <div class='container-fluid px-4'>
                <div class='card shadow-sm border-0' style='border-radius: 15px;'>
                    <div class='card-body p-4'>
                        <div class='table-responsive'>
                            <table class='table table-hover align-middle'>
                                <thead class='table-light'>
                                    <tr>
                                        <th>#</th>
                                        <th>المشرف</th>
                                        <th>نوع الإجراء</th>
                                        <th>الوصف التفصيلي</th>
                                        <th>عنوان IP</th>
                                        <th>التاريخ والوقت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($logs && $logs->num_rows > 0): ?>
                                        <?php while($row = $logs->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $row['log_id']; ?></td>
                                                <td class='fw-bold text-primary'><?php echo htmlspecialchars($row['full_name'] ? ($row['full_name'] . ' (@' . $row['username'] . ')') : 'مدير عام'); ?></td>
                                                <td><span class='status-pill secondary' style='min-width: unset; padding: 4px 14px; font-size: 0.82rem;'><?php echo htmlspecialchars($row['action_type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                                <td><small class='text-muted' dir='ltr'><?php echo htmlspecialchars($row['ip_address']); ?></small></td>
                                                <td dir='ltr' class='text-end'><small><?php echo date('Y-m-d h:i A', strtotime($row['created_at'])); ?></small></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan='6' class='text-center py-4 text-muted'>لا توجد سجلات بعد.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
    <script>
        var el = document.getElementById('wrapper');
        var toggleButton = document.getElementById('menu-toggle');
        toggleButton.onclick = function () { el.classList.toggle('toggled'); };
    </script>
</body>
</html>
