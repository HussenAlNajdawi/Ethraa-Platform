<?php
// admin/admin_functions.php

function logAdminAction($conn, $admin_id, $action_type, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $stmt = $conn->prepare('INSERT INTO admin_logs (admin_id, action_type, description, ip_address) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('isss', $admin_id, $action_type, $description, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * فحص هل المشرف الحالي هو المدير العام
 */
function isSuperAdmin() {
    return (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin');
}

/**
 * فحص هل المشرف يملك صلاحية معينة
 */
function hasAdminPermission($permission_key, $conn = null) {
    if (isSuperAdmin()) {
        return true;
    }

    $perms = $_SESSION['admin_permissions'] ?? [];
    if (is_string($perms)) {
        $perms = json_decode($perms, true) ?: [];
    }

    if (!is_array($perms)) {
        $perms = [];
    }

    if (in_array('all', $perms)) {
        return true;
    }

    return in_array($permission_key, $perms);
}

/**
 * حماية الصفحة ومنع المشرف غير المصرح له من دخولها
 */
function requireAdminPermission($permission_key, $conn = null) {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: admin_login.php');
        exit();
    }

    if (!hasAdminPermission($permission_key, $conn)) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}
?>