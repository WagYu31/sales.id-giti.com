<?php
/**
 * ajax_role_permission_handler.php
 * Endpoint AJAX untuk manajemen hak akses role menu (Superadmin Only)
 */

require_once 'includes/db.php';
require_once 'includes/role_permission_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
    exit();
}

$userRole = strtolower(trim($_SESSION['role'] ?? ''));
if ($userRole !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak! Hanya Super Admin yang berhak mengubah hak akses menu.']);
    exit();
}

ensureRolePermissionsTable($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userName = $_SESSION['nama_lengkap'] ?? 'Super Admin';

switch ($action) {
    case 'toggle_permission':
        $roleName  = strtolower(trim($_POST['role'] ?? ''));
        $menuKey   = trim($_POST['menu_key'] ?? '');
        $permField = trim($_POST['field'] ?? 'is_accessible'); // is_accessible, can_create, can_edit, can_delete, can_export
        $val       = (int)($_POST['value'] ?? 0);

        if (empty($roleName) || empty($menuKey)) {
            echo json_encode(['success' => false, 'message' => 'Parameter role atau menu tidak valid.']);
            exit();
        }

        $allowedFields = ['is_accessible', 'can_create', 'can_edit', 'can_delete', 'can_export'];
        if (!in_array($permField, $allowedFields)) {
            echo json_encode(['success' => false, 'message' => 'Field permission tidak dikenali.']);
            exit();
        }

        // Ambil data permission eksisting
        $stmt = $conn->prepare("SELECT * FROM role_menu_permissions WHERE role_name = ? AND menu_key = ?");
        $stmt->bind_param("ss", $roleName, $menuKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $curr = $res->fetch_assoc();
        $stmt->close();

        $is_accessible = $curr['is_accessible'] ?? 1;
        $can_create    = $curr['can_create'] ?? 1;
        $can_edit      = $curr['can_edit'] ?? 1;
        $can_delete    = $curr['can_delete'] ?? 1;
        $can_export    = $curr['can_export'] ?? 1;

        // Update nilai field yang di-toggle
        $$permField = $val;

        // Jika akses menu dinonaktifkan, nonaktifkan juga aksi turunannya
        if ($permField === 'is_accessible' && $val === 0) {
            $can_create = 0;
            $can_edit = 0;
            $can_delete = 0;
            $can_export = 0;
        }

        // Simpan
        $ok = saveRoleMenuPermission($conn, $roleName, $menuKey, $is_accessible, $can_create, $can_edit, $can_delete, $can_export, $userName);

        if ($ok) {
            echo json_encode([
                'success' => true, 
                'message' => 'Hak akses berhasil diperbarui!',
                'data' => [
                    'role' => $roleName,
                    'menu_key' => $menuKey,
                    'is_accessible' => $is_accessible,
                    'can_create' => $can_create,
                    'can_edit' => $can_edit,
                    'can_delete' => $can_delete,
                    'can_export' => $can_export
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data ke database.']);
        }
        break;

    case 'save_all_permissions':
        $roleName = strtolower(trim($_POST['role'] ?? ''));
        $permissionsJson = $_POST['permissions'] ?? '';

        if (empty($roleName) || empty($permissionsJson)) {
            echo json_encode(['success' => false, 'message' => 'Data permission tidak lengkap.']);
            exit();
        }

        $permissions = json_decode($permissionsJson, true);
        if (!is_array($permissions)) {
            echo json_encode(['success' => false, 'message' => 'Format data JSON permission tidak valid.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            foreach ($permissions as $menuKey => $perms) {
                $is_acc = !empty($perms['is_accessible']) ? 1 : 0;
                $c_cre  = !empty($perms['can_create']) ? 1 : 0;
                $c_edt  = !empty($perms['can_edit']) ? 1 : 0;
                $c_del  = !empty($perms['can_delete']) ? 1 : 0;
                $c_exp  = !empty($perms['can_export']) ? 1 : 0;

                saveRoleMenuPermission($conn, $roleName, $menuKey, $is_acc, $c_cre, $c_edt, $c_del, $c_exp, $userName);
            }
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Semua hak akses untuk role ' . strtoupper($roleName) . ' berhasil disimpan!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
        break;

    case 'grant_all':
        $roleName = strtolower(trim($_POST['role'] ?? ''));
        if (empty($roleName)) {
            echo json_encode(['success' => false, 'message' => 'Role tidak ditentukan.']);
            exit();
        }

        $allModules = getAllSystemMenus();
        $conn->begin_transaction();
        try {
            foreach ($allModules as $module) {
                foreach ($module['menus'] as $m) {
                    saveRoleMenuPermission($conn, $roleName, $m['key'], 1, 1, 1, 1, 1, $userName);
                }
            }
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Semua akses menu berhasil diaktifkan untuk role ' . strtoupper($roleName) . '.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal mengaktifkan semua akses: ' . $e->getMessage()]);
        }
        break;

    case 'revoke_all':
        $roleName = strtolower(trim($_POST['role'] ?? ''));
        if (empty($roleName)) {
            echo json_encode(['success' => false, 'message' => 'Role tidak ditentukan.']);
            exit();
        }

        if ($roleName === 'superadmin') {
            echo json_encode(['success' => false, 'message' => 'Tidak diperbolehkan menonaktifkan semua akses untuk role Super Admin!']);
            exit();
        }

        $allModules = getAllSystemMenus();
        $conn->begin_transaction();
        try {
            foreach ($allModules as $module) {
                foreach ($module['menus'] as $m) {
                    saveRoleMenuPermission($conn, $roleName, $m['key'], 0, 0, 0, 0, 0, $userName);
                }
            }
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Semua akses menu telah dinonaktifkan untuk role ' . strtoupper($roleName) . '.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal menonaktifkan akses: ' . $e->getMessage()]);
        }
        break;

    case 'reset_defaults':
        $roleName = strtolower(trim($_POST['role'] ?? ''));
        if (empty($roleName)) {
            echo json_encode(['success' => false, 'message' => 'Role tidak ditentukan.']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM role_menu_permissions WHERE role_name = ?");
        $stmt->bind_param("s", $roleName);
        $stmt->execute();
        $stmt->close();

        // Seed ulang default preset
        $defaults = getRolePermissions($conn, $roleName);
        $conn->begin_transaction();
        try {
            foreach ($defaults as $mk => $p) {
                saveRoleMenuPermission($conn, $roleName, $mk, $p['is_accessible'], $p['can_create'], $p['can_edit'], $p['can_delete'], $p['can_export'], 'System Default');
            }
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Hak akses role ' . strtoupper($roleName) . ' telah di-reset ke pengaturan standar.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Gagal mereset ke default: ' . $e->getMessage()]);
        }
        break;

    case 'get_role_users':
        $roleName = strtolower(trim($_GET['role'] ?? ''));
        if (empty($roleName)) {
            echo json_encode(['success' => false, 'message' => 'Role tidak ditentukan.']);
            exit();
        }

        // Cek kolom yang tersedia di tabel sales
        $hasHp = false;
        $hasFoto = false;
        $chkHp = $conn->query("SHOW COLUMNS FROM sales LIKE 'no_hp'");
        if ($chkHp && $chkHp->num_rows > 0) $hasHp = true;
        $chkFoto = $conn->query("SHOW COLUMNS FROM sales LIKE 'foto'");
        if ($chkFoto && $chkFoto->num_rows > 0) $hasFoto = true;

        $selectFields = "id, nama_lengkap, email, role, created_at";
        if ($hasHp) $selectFields .= ", no_hp";
        if ($hasFoto) $selectFields .= ", foto";

        $stmt = $conn->prepare("SELECT $selectFields FROM sales WHERE (LOWER(role) = ? OR role = ?) AND deleted_at IS NULL ORDER BY nama_lengkap ASC");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database query error: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("ss", $roleName, $roleName);
        $stmt->execute();
        $res = $stmt->get_result();

        $users = [];
        while ($row = $res->fetch_assoc()) {
            $users[] = [
                'id' => $row['id'],
                'nama' => $row['nama_lengkap'] ?? '',
                'email' => $row['email'] ?? '',
                'no_hp' => $row['no_hp'] ?? '-',
                'role' => $row['role'] ?? '',
                'created_at' => !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '-'
            ];
        }
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $users, 'total' => count($users), 'current_user_id' => (int)$_SESSION['user_id']]);
        break;

    case 'delete_user':
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID pengguna tidak valid.']);
            exit();
        }

        if ($userId === (int)$_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif login!']);
            exit();
        }

        // Set customers sales_id to NULL agar data customer tidak hilang
        $stmt_reassign = $conn->prepare("UPDATE customers SET sales_id = NULL WHERE sales_id = ?");
        if ($stmt_reassign) {
            $stmt_reassign->bind_param("i", $userId);
            $stmt_reassign->execute();
            $stmt_reassign->close();
        }

        // Soft delete akun sales
        $stmt_del = $conn->prepare("UPDATE sales SET deleted_at = NOW(), email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()) WHERE id = ?");
        $stmt_del->bind_param("i", $userId);
        if ($stmt_del->execute()) {
            echo json_encode(['success' => true, 'message' => 'Akun pengguna berhasil dihapus.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus pengguna: ' . $conn->error]);
        }
        $stmt_del->close();
        break;

    case 'change_user_role':
        $userId = (int)($_POST['user_id'] ?? 0);
        $newRole = strtolower(trim($_POST['new_role'] ?? ''));

        if ($userId <= 0 || empty($newRole)) {
            echo json_encode(['success' => false, 'message' => 'Data perpindahan role tidak lengkap.']);
            exit();
        }

        $stmt_role = $conn->prepare("UPDATE sales SET role = ? WHERE id = ?");
        $stmt_role->bind_param("si", $newRole, $userId);
        if ($stmt_role->execute()) {
            echo json_encode(['success' => true, 'message' => 'Role pengguna berhasil dipindahkan ke ' . strtoupper($newRole) . '.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui role pengguna: ' . $conn->error]);
        }
        $stmt_role->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
        break;
}
