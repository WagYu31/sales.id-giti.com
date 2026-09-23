<?php
/**
 * role-menu.php - Bridge Forwarder to Root Role Menu Access
 */
require_once __DIR__ . "/conn.php";
require_once __DIR__ . "/session.php";

$userRole = strtolower(trim($_SESSION['role'] ?? ''));

if ($userRole !== 'superadmin') {
    header("Location: kegiatan.php?error=unauthorized");
    exit();
}

header("Location: ../role_menu_access.php");
exit();
