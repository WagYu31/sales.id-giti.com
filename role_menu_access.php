<?php
/**
 * role_menu_access.php
 * Halaman Manajemen Hak Akses & Role Menu (Khusus Super Admin)
 */

$page_title = 'Akses Role Menu (Super Admin)';
require_once 'includes/db.php';
require_once 'includes/role_permission_helper.php';
require_once 'includes/header.php';

// Pastikan user telah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userRole = strtolower(trim($_SESSION['role'] ?? ''));

// Keamanan: Hanya Super Admin yang boleh mengakses halaman ini
if ($userRole !== 'superadmin') {
    echo '
    <div class="container py-5 text-center">
        <div class="card border-0 shadow-sm mx-auto p-4 rounded-4" style="max-width: 500px; background: #fff;">
            <div class="mb-3">
                <span class="material-symbols-outlined text-danger" style="font-size: 64px;">gpp_bad</span>
            </div>
            <h3 class="fw-bold text-danger mb-2">Akses Ditolak!</h3>
            <p class="text-muted mb-4">Halaman <strong>Akses Role Menu</strong> hanya dapat diakses secara eksklusif oleh <strong>Super Admin</strong>.</p>
            <a href="customer_management.php" class="btn btn-primary rounded-3 px-4 py-2 fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>';
    require_once 'includes/footer.php';
    exit();
}

ensureRolePermissionsTable($conn);

$allRoles = getAllSystemRoles($conn);
$allModules = getAllSystemMenus();

// Selected Role from URL or Default 'sales'
$selectedRole = strtolower(trim($_GET['role'] ?? 'sales'));
if (!isset($allRoles[$selectedRole])) {
    $selectedRole = array_key_first($allRoles) ?? 'sales';
}

$currentRoleData = $allRoles[$selectedRole];
$permissions = getRolePermissions($conn, $selectedRole);

// Hitung jumlah user pada setiap role
$userCounts = [];
$qCount = $conn->query("SELECT LOWER(role) as r, COUNT(*) as c FROM sales WHERE deleted_at IS NULL GROUP BY LOWER(role)");
if ($qCount) {
    while ($rc = $qCount->fetch_assoc()) {
        $userCounts[$rc['r']] = (int)$rc['c'];
    }
}

// Hitung statistik menu aktif untuk role ini
$totalMenus = 0;
$activeMenus = 0;
$canCreateCount = 0;
$canEditCount = 0;
$canDeleteCount = 0;
$canExportCount = 0;

foreach ($allModules as $mod) {
    foreach ($mod['menus'] as $m) {
        $totalMenus++;
        $p = $permissions[$m['key']] ?? ['is_accessible'=>0, 'can_create'=>0, 'can_edit'=>0, 'can_delete'=>0, 'can_export'=>0];
        if (!empty($p['is_accessible'])) $activeMenus++;
        if (!empty($p['can_create'])) $canCreateCount++;
        if (!empty($p['can_edit'])) $canEditCount++;
        if (!empty($p['can_delete'])) $canDeleteCount++;
        if (!empty($p['can_export'])) $canExportCount++;
    }
}
?>

<style>
/* ── Hero Banner ── */
.role-hero-banner {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 45%, #B45309 100%);
    border-radius: 20px;
    padding: 30px 36px;
    margin-bottom: 24px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -8px rgba(180, 83, 9, 0.35);
}
.role-hero-banner::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.25) 0%, transparent 70%);
    pointer-events: none;
}
.role-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(245, 158, 11, 0.2);
    color: #FCD34D;
    border: 1px solid rgba(245, 158, 11, 0.4);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.role-hero-title {
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 26px;
    margin-bottom: 6px;
    letter-spacing: -0.02em;
}
.role-hero-sub {
    font-size: 13.5px;
    color: rgba(241, 245, 249, 0.85);
    margin: 0;
    max-width: 680px;
    line-height: 1.5;
}

/* ── Role Navigation Pills ── */
.role-nav-wrapper {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 10px 14px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: none;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}
.role-nav-wrapper::-webkit-scrollbar { display: none; }
.role-nav-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    color: #64748B;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    border: 1.5px solid transparent;
}
.role-nav-item:hover {
    background: #F8FAFC;
    color: #0F172A;
    transform: translateY(-1px);
}
.role-nav-item.active {
    background: var(--role-bg, #EFF6FF);
    color: var(--role-color, #2563EB) !important;
    border-color: var(--role-border, #BFDBFE);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
}
.role-count-pill {
    font-size: 10.5px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 20px;
    background: rgba(0, 0, 0, 0.06);
}
.role-nav-item.active .role-count-pill {
    background: var(--role-color, #2563EB);
    color: #FFFFFF;
}

/* ── Summary Cards ── */
.role-stat-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 16px 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    height: 100%;
}
.role-stat-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748B;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
}
.role-stat-value {
    font-family: 'Outfit', sans-serif;
    font-size: 24px;
    font-weight: 800;
    color: #0F172A;
    margin: 0;
}
.role-stat-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
}

/* ── Section Modules & Tables ── */
.module-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}
.module-card-header {
    padding: 16px 22px;
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.module-card-title {
    font-size: 14px;
    font-weight: 800;
    color: #1E293B;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}
.module-icon-badge {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
}

/* ── Permission Table Rows ── */
.perm-table {
    width: 100%;
    margin-bottom: 0;
}
.perm-table th {
    background: #F8FAFC;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748B;
    letter-spacing: 0.05em;
    padding: 12px 18px;
    border-bottom: 1.5px solid #E2E8F0;
}
.perm-table td {
    padding: 14px 18px;
    vertical-align: middle;
    border-bottom: 1px solid #F1F5F9;
}
.perm-table tr:last-child td {
    border-bottom: none;
}
.perm-table tr:hover {
    background-color: #FAFCFF;
}

.menu-icon-circle {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.menu-label-text {
    font-size: 13.5px;
    font-weight: 700;
    color: #0F172A;
    margin-bottom: 2px;
}
.menu-desc-text {
    font-size: 11.5px;
    color: #64748B;
    margin: 0;
    line-height: 1.4;
}
.menu-url-pill {
    font-size: 10px;
    font-family: monospace;
    background: #F1F5F9;
    color: #475569;
    padding: 1.5px 6px;
    border-radius: 4px;
    border: 1px solid #E2E8F0;
    display: inline-block;
    margin-top: 3px;
}

/* ── Custom iOS-like Switch ── */
.form-switch-modern {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    margin: 0;
}
.form-switch-modern input {
    display: none;
}
.switch-slider {
    position: relative;
    width: 44px;
    height: 24px;
    background-color: #CBD5E1;
    border-radius: 24px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}
.switch-slider::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background-color: #FFFFFF;
    top: 3px;
    left: 3px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.form-switch-modern input:checked + .switch-slider {
    background: linear-gradient(135deg, #10B981, #059669);
}
.form-switch-modern input:checked + .switch-slider::before {
    transform: translateX(20px);
}
.form-switch-modern.switch-primary input:checked + .switch-slider {
    background: linear-gradient(135deg, #3B82F6, #2563EB);
}
.form-switch-modern.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}

/* ── Floating Toast & Floating Bar ── */
.toast-perm {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: #0F172A;
    color: #FFFFFF;
    border-radius: 12px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
    z-index: 9999;
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    border-left: 4px solid #10B981;
}
.toast-perm.show {
    transform: translateY(0);
    opacity: 1;
}

/* Quick Action Buttons */
.btn-action-pill {
    padding: 7px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1.5px solid transparent;
    transition: all 0.2s;
    text-decoration: none;
    cursor: pointer;
}
.btn-action-pill:hover {
    transform: translateY(-1px);
}
</style>

<div class="container-fluid px-3 px-md-4 py-3">

    <!-- ── 1. HERO BANNER ──────────────────────────────────────────────────── -->
    <div class="role-hero-banner">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="role-hero-badge">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Super Admin Area</span>
                </div>
                <h1 class="role-hero-title">
                    Manajemen Hak Akses &amp; Role Menu
                </h1>
                <p class="role-hero-sub">
                    Atur hak akses menu, visibilitas fitur, dan otoritas tindakan (Tambah, Edit, Hapus, Export) untuk setiap kelompok pengguna secara terpusat &amp; aman.
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-light rounded-3 fw-bold px-3 py-2 text-dark shadow-sm d-inline-flex align-items-center gap-2" style="font-size: 13px;" onclick="showRoleUsersModal('<?= $selectedRole; ?>', '<?= htmlspecialchars($currentRoleData['label']); ?>')">
                    <i class="bi bi-people-fill text-primary"></i>
                    <span>Daftar User (<?= $userCounts[$selectedRole] ?? 0; ?>)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ── 2. ROLE TABS SELECTOR ───────────────────────────────────────────── -->
    <div class="role-nav-wrapper">
        <span class="text-uppercase fw-bold text-muted me-2 d-none d-md-inline" style="font-size: 11px; letter-spacing: 0.05em;">
            <i class="bi bi-sliders me-1"></i> Pilih Role:
        </span>
        <?php foreach ($allRoles as $rKey => $rData): 
            $isActive = ($selectedRole === $rKey);
            $uCount = $userCounts[$rKey] ?? 0;
        ?>
        <a href="role_menu_access.php?role=<?= $rKey; ?>" 
           class="role-nav-item <?= $isActive ? 'active' : ''; ?>"
           style="--role-color: <?= $rData['badge_color']; ?>; --role-bg: <?= $rData['badge_bg']; ?>; --role-border: <?= $rData['badge_border']; ?>;">
            <i class="<?= $rData['icon']; ?>" style="color: <?= $rData['badge_color']; ?>;"></i>
            <span><?= htmlspecialchars($rData['label']); ?></span>
            <span class="role-count-pill"><?= $uCount; ?> user</span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── 3. SUMMARY STATS & QUICK ACTIONS ────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <!-- Role Info -->
        <div class="col-12 col-md-4">
            <div class="role-stat-card d-flex align-items-center gap-3">
                <div class="role-stat-icon" style="background: <?= $currentRoleData['badge_bg']; ?>; color: <?= $currentRoleData['badge_color']; ?>; border: 1.5px solid <?= $currentRoleData['badge_border']; ?>;">
                    <i class="<?= $currentRoleData['icon']; ?>"></i>
                </div>
                <div>
                    <div class="role-stat-label">Role Aktif Terpilih</div>
                    <h4 class="role-stat-value" style="color: <?= $currentRoleData['badge_color']; ?>;">
                        <?= htmlspecialchars($currentRoleData['label']); ?>
                    </h4>
                    <span class="badge rounded-pill" style="background: <?= $currentRoleData['badge_bg']; ?>; color: <?= $currentRoleData['badge_color']; ?>; font-size: 10.5px; border: 1px solid <?= $currentRoleData['badge_border']; ?>;">
                        <?= $userCounts[$selectedRole] ?? 0; ?> Pengguna Aktif
                    </span>
                </div>
            </div>
        </div>

        <!-- Menu Access Count -->
        <div class="col-6 col-md-4">
            <div class="role-stat-card d-flex align-items-center gap-3">
                <div class="role-stat-icon" style="background: #ECFDF5; color: #059669; border: 1.5px solid #A7F3D0;">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="role-stat-label">Menu Yang Dapat Diakses</div>
                    <h4 class="role-stat-value text-success">
                        <?= $activeMenus; ?> <span style="font-size: 14px; font-weight: 600; color: #64748B;">/ <?= $totalMenus; ?> Menu</span>
                    </h4>
                    <span class="text-muted" style="font-size: 11px;">
                        <?= round(($activeMenus / max(1, $totalMenus)) * 100); ?>% hak visibilitas
                    </span>
                </div>
            </div>
        </div>

        <!-- Actions Grant / Revoke -->
        <div class="col-6 col-md-4">
            <div class="role-stat-card d-flex flex-column justify-content-center">
                <div class="role-stat-label mb-2">Aksi Cepat Izin</div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn-action-pill" style="background: #ECFDF5; color: #059669; border-color: #A7F3D0;" onclick="grantAllPermissions('<?= $selectedRole; ?>')">
                        <i class="bi bi-check-all"></i> Aktifkan Semua
                    </button>
                    <?php if ($selectedRole !== 'superadmin'): ?>
                    <button type="button" class="btn-action-pill" style="background: #FEF2F2; color: #DC2626; border-color: #FECACA;" onclick="revokeAllPermissions('<?= $selectedRole; ?>')">
                        <i class="bi bi-x-circle"></i> Nonaktifkan
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn-action-pill" style="background: #F1F5F9; color: #475569; border-color: #E2E8F0;" onclick="resetDefaultPermissions('<?= $selectedRole; ?>')">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Default
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 4. SEARCH BAR FILTER ────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4" style="background: #FFFFFF; border: 1px solid #E2E8F0 !important;">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 10px 0 0 10px;">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="searchMenuInput" class="form-control border-start-0" placeholder="Cari nama menu atau fitur..." style="border-radius: 0 10px 10px 0; font-size: 13px;">
                </div>
            </div>
            <div class="col-12 col-md-6 text-md-end">
                <span class="text-muted" style="font-size: 12px;">
                    <i class="bi bi-info-circle text-primary me-1"></i> Perubahan switch otomatis tersimpan secara instan.
                </span>
            </div>
        </div>
    </div>

    <!-- ── 5. PERMISSION MODULES MATRIX ────────────────────────────────────── -->
    <div id="modulesContainer">
        <?php foreach ($allModules as $modKey => $module): ?>
        <div class="module-card module-section-item" data-module="<?= $modKey; ?>">
            
            <div class="module-card-header">
                <h5 class="module-card-title">
                    <span class="module-icon-badge" style="background: <?= $module['color']; ?>18; color: <?= $module['color']; ?>;">
                        <i class="<?= $module['icon']; ?>"></i>
                    </span>
                    <span><?= htmlspecialchars($module['title']); ?></span>
                </h5>
                <span class="badge bg-white text-secondary border fw-bold" style="font-size: 11px;">
                    <?= count($module['menus']); ?> Menu
                </span>
            </div>

            <div class="table-responsive">
                <table class="perm-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Menu &amp; Deskripsi</th>
                            <th class="text-center" style="width: 13%;">Akses Menu</th>
                            <th class="text-center" style="width: 13%;">Tambah (Create)</th>
                            <th class="text-center" style="width: 13%;">Ubah (Edit)</th>
                            <th class="text-center" style="width: 13%;">Hapus (Delete)</th>
                            <th class="text-center" style="width: 13%;">Export (Excel)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($module['menus'] as $menu): 
                            $mKey = $menu['key'];
                            $p = $permissions[$mKey] ?? ['is_accessible'=>1, 'can_create'=>1, 'can_edit'=>1, 'can_delete'=>1, 'can_export'=>1];
                            $isSuper = ($selectedRole === 'superadmin');
                            $hasActions = $menu['has_actions'] ?? ['access', 'create', 'edit', 'delete', 'export'];
                        ?>
                        <tr class="menu-row-item" data-menu-title="<?= strtolower(htmlspecialchars($menu['label'] . ' ' . $menu['description'] . ' ' . $menu['url'])); ?>">
                            
                            <!-- Menu Info -->
                            <td>
                                <div class="d-flex align-items-start gap-2.5">
                                    <div class="menu-icon-circle" style="background: <?= $menu['color']; ?>15; color: <?= $menu['color']; ?>;">
                                        <i class="<?= $menu['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="menu-label-text"><?= htmlspecialchars($menu['label']); ?></div>
                                        <div class="menu-desc-text"><?= htmlspecialchars($menu['description']); ?></div>
                                        <span class="menu-url-pill">
                                            <i class="bi bi-link-45deg"></i> <?= htmlspecialchars($menu['url']); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- 1. Akses Menu Switch -->
                            <td class="text-center">
                                <label class="form-switch-modern switch-primary <?= ($isSuper && $mKey==='role_menu_access') ? 'disabled' : ''; ?>" title="Aktifkan / Nonaktifkan Akses Menu">
                                    <input type="checkbox" 
                                           class="perm-checkbox"
                                           data-role="<?= $selectedRole; ?>" 
                                           data-menu="<?= $mKey; ?>" 
                                           data-field="is_accessible" 
                                           <?= !empty($p['is_accessible']) ? 'checked' : ''; ?>
                                           onchange="handleTogglePermission(this)">
                                    <span class="switch-slider"></span>
                                </label>
                            </td>

                            <!-- 2. Can Create Switch -->
                            <td class="text-center">
                                <?php if (in_array('create', $hasActions)): ?>
                                <label class="form-switch-modern <?= empty($p['is_accessible']) ? 'disabled' : ''; ?>" title="Izin Tambah Data">
                                    <input type="checkbox" 
                                           class="perm-checkbox sub-action-<?= $mKey; ?>"
                                           data-role="<?= $selectedRole; ?>" 
                                           data-menu="<?= $mKey; ?>" 
                                           data-field="can_create" 
                                           <?= !empty($p['can_create']) ? 'checked' : ''; ?>
                                           onchange="handleTogglePermission(this)">
                                    <span class="switch-slider"></span>
                                </label>
                                <?php else: ?>
                                <span class="text-muted" style="font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 3. Can Edit Switch -->
                            <td class="text-center">
                                <?php if (in_array('edit', $hasActions)): ?>
                                <label class="form-switch-modern <?= empty($p['is_accessible']) ? 'disabled' : ''; ?>" title="Izin Edit Data">
                                    <input type="checkbox" 
                                           class="perm-checkbox sub-action-<?= $mKey; ?>"
                                           data-role="<?= $selectedRole; ?>" 
                                           data-menu="<?= $mKey; ?>" 
                                           data-field="can_edit" 
                                           <?= !empty($p['can_edit']) ? 'checked' : ''; ?>
                                           onchange="handleTogglePermission(this)">
                                    <span class="switch-slider"></span>
                                </label>
                                <?php else: ?>
                                <span class="text-muted" style="font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 4. Can Delete Switch -->
                            <td class="text-center">
                                <?php if (in_array('delete', $hasActions)): ?>
                                <label class="form-switch-modern <?= empty($p['is_accessible']) ? 'disabled' : ''; ?>" title="Izin Hapus Data">
                                    <input type="checkbox" 
                                           class="perm-checkbox sub-action-<?= $mKey; ?>"
                                           data-role="<?= $selectedRole; ?>" 
                                           data-menu="<?= $mKey; ?>" 
                                           data-field="can_delete" 
                                           <?= !empty($p['can_delete']) ? 'checked' : ''; ?>
                                           onchange="handleTogglePermission(this)">
                                    <span class="switch-slider"></span>
                                </label>
                                <?php else: ?>
                                <span class="text-muted" style="font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- 5. Can Export Switch -->
                            <td class="text-center">
                                <?php if (in_array('export', $hasActions)): ?>
                                <label class="form-switch-modern <?= empty($p['is_accessible']) ? 'disabled' : ''; ?>" title="Izin Unduh / Export Excel">
                                    <input type="checkbox" 
                                           class="perm-checkbox sub-action-<?= $mKey; ?>"
                                           data-role="<?= $selectedRole; ?>" 
                                           data-menu="<?= $mKey; ?>" 
                                           data-field="can_export" 
                                           <?= !empty($p['can_export']) ? 'checked' : ''; ?>
                                           onchange="handleTogglePermission(this)">
                                    <span class="switch-slider"></span>
                                </label>
                                <?php else: ?>
                                <span class="text-muted" style="font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- ── FLOATING TOAST NOTIFICATION ─────────────────────────────────────────── -->
<div class="toast-perm" id="permToast">
    <i class="bi bi-check-circle-fill text-success fs-5"></i>
    <span id="toastMsg">Pengaturan berhasil disimpan!</span>
</div>

<!-- ── MODAL DAFTAR USER ROLE ──────────────────────────────────────────────── -->
<div class="modal fade" id="roleUsersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div>
                    <h5 class="modal-title fw-bold" id="modalRoleTitle">Daftar Pengguna Role</h5>
                    <p class="text-muted small mb-0">Daftar akun tim sales &amp; admin dengan role ini</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="roleUsersLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="small text-muted mt-2">Memuat daftar pengguna...</p>
                </div>
                <div id="roleUsersContent" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Pengguna</th>
                                    <th>Email</th>
                                    <th>Terdaftar Sejak</th>
                                    <th>Role Saat Ini</th>
                                    <th class="text-center" style="width: 120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="roleUsersTbody">
                                <!-- Loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button type="button" class="btn btn-secondary rounded-3 px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ── SCRIPTS ─────────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Live Search Menu
document.getElementById('searchMenuInput').addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.menu-row-item');
    const modules = document.querySelectorAll('.module-section-item');

    rows.forEach(row => {
        const text = row.getAttribute('data-menu-title') || '';
        if (text.includes(q)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Sembunyikan modul jika semua anaknya tersembunyi
    modules.forEach(mod => {
        const visibleRows = mod.querySelectorAll('.menu-row-item:not([style*="display: none"])');
        mod.style.display = visibleRows.length > 0 ? '' : 'none';
    });
});

// Toast Notifier
let toastTimeout;
function showToast(msg) {
    const toast = document.getElementById('permToast');
    const toastMsg = document.getElementById('toastMsg');
    toastMsg.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toast.classList.remove('show');
    }, 2500);
}

// Handler Toggle Switch Instant AJAX
function handleTogglePermission(el) {
    const role = el.getAttribute('data-role');
    const menuKey = el.getAttribute('data-menu');
    const field = el.getAttribute('data-field');
    const val = el.checked ? 1 : 0;

    // Jika akses menu dinonaktifkan, disable sub actions
    if (field === 'is_accessible') {
        const subSwitches = document.querySelectorAll('.sub-action-' + menuKey);
        subSwitches.forEach(s => {
            const parentLabel = s.closest('.form-switch-modern');
            if (val === 0) {
                s.checked = false;
                if (parentLabel) parentLabel.classList.add('disabled');
            } else {
                if (parentLabel) parentLabel.classList.remove('disabled');
            }
        });
    }

    const formData = new URLSearchParams();
    formData.append('action', 'toggle_permission');
    formData.append('role', role);
    formData.append('menu_key', menuKey);
    formData.append('field', field);
    formData.append('value', val);

    fetch('ajax_role_permission_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Hak akses berhasil diperbarui!');
        } else {
            Swal.fire('Gagal', data.message || 'Terjadi kesalahan.', 'error');
            el.checked = !el.checked; // Revert switch
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
        el.checked = !el.checked;
    });
}

// Grant All Permissions
function grantAllPermissions(role) {
    Swal.fire({
        title: 'Aktifkan Semua Hak Akses?',
        text: `Semua menu dan tindakan untuk role ${role.toUpperCase()} akan diberikan izin penuh.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Ya, Aktifkan Semua',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const formData = new URLSearchParams();
            formData.append('action', 'grant_all');
            formData.append('role', role);

            fetch('ajax_role_permission_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil', data.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            });
        }
    });
}

// Revoke All Permissions
function revokeAllPermissions(role) {
    Swal.fire({
        title: 'Nonaktifkan Semua Hak Akses?',
        text: `Semua menu untuk role ${role.toUpperCase()} akan ditutup dan tidak dapat diakses.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Ya, Nonaktifkan',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const formData = new URLSearchParams();
            formData.append('action', 'revoke_all');
            formData.append('role', role);

            fetch('ajax_role_permission_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil', data.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            });
        }
    });
}

// Reset Default Permissions
function resetDefaultPermissions(role) {
    Swal.fire({
        title: 'Reset ke Pengaturan Default?',
        text: `Hak akses untuk role ${role.toUpperCase()} akan dikembalikan ke pengaturan standar sistem.`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#2563EB',
        cancelButtonColor: '#64748B',
        confirmButtonText: 'Ya, Reset Default',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const formData = new URLSearchParams();
            formData.append('action', 'reset_defaults');
            formData.append('role', role);

            fetch('ajax_role_permission_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil', data.message, 'success').then(() => window.location.reload());
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            });
        }
    });
}

// Show Users Modal
function showRoleUsersModal(role, roleLabel) {
    const modalEl = document.getElementById('roleUsersModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('modalRoleTitle').textContent = `Daftar Pengguna Role: ${roleLabel}`;
    document.getElementById('roleUsersLoading').style.display = 'block';
    document.getElementById('roleUsersContent').style.display = 'none';
    modal.show();

    fetch(`ajax_role_permission_handler.php?action=get_role_users&role=${encodeURIComponent(role)}`)
    .then(res => res.json())
    .then(data => {
        document.getElementById('roleUsersLoading').style.display = 'none';
        document.getElementById('roleUsersContent').style.display = 'block';
        const tbody = document.getElementById('roleUsersTbody');
        tbody.innerHTML = '';

        if (data.success && data.data.length > 0) {
            const currentUserId = data.current_user_id || 0;
            data.data.forEach(u => {
                const isSelf = (parseInt(u.id) === currentUserId);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width:34px;height:34px;font-size:11px;flex-shrink:0;">
                                ${u.nama ? u.nama.substring(0, 2).toUpperCase() : 'US'}
                            </div>
                            <div>
                                <div class="fw-bold text-dark">${u.nama || '-'} ${isSelf ? '<span class="badge bg-primary text-white ms-1" style="font-size:9px;">Akun Anda</span>' : ''}</div>
                                <small class="text-muted font-monospace" style="font-size:10px;">ID: #${u.id}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="text-muted"><i class="bi bi-envelope me-1"></i>${u.email || '-'}</span></td>
                    <td><span class="text-secondary small"><i class="bi bi-calendar-event me-1"></i>${u.created_at || '-'}</span></td>
                    <td><span class="badge bg-light text-dark border px-2.5 py-1.5 fw-bold text-uppercase" style="font-size:10.5px;">${u.role || '-'}</span></td>
                    <td class="text-center">
                        <div class="d-inline-flex gap-1.5 align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-2 px-2 py-1" title="Ubah Role Pengguna" onclick="changeUserRole(${u.id}, '${escapeHtml(u.nama)}', '${u.role}', '${role}', '${escapeHtml(roleLabel)}')">
                                <i class="bi bi-arrow-left-right"></i>
                            </button>
                            ${!isSelf ? `
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-2 px-2 py-1" title="Hapus Akun Pengguna" onclick="deleteUserAccount(${u.id}, '${escapeHtml(u.nama)}', '${role}', '${escapeHtml(roleLabel)}')">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                            ` : `
                            <span class="text-muted small" title="Tidak dapat menghapus akun sendiri"><i class="bi bi-lock-fill"></i></span>
                            `}
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada pengguna yang terdaftar dengan role ini.</td></tr>`;
        }
    })
    .catch((err) => {
        console.error("Error loading users:", err);
        document.getElementById('roleUsersLoading').style.display = 'none';
        document.getElementById('roleUsersContent').style.display = 'block';
        document.getElementById('roleUsersTbody').innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-1"></i> Gagal memuat data pengguna.</td></tr>`;
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Handler Hapus User
function deleteUserAccount(userId, userName, currentRole, roleLabel) {
    Swal.fire({
        title: 'Hapus Akun Pengguna?',
        html: `<p class="text-muted mb-0">Akun <strong>${userName}</strong> akan dihapus dari sistem.<br><small class="text-danger">Tindakan ini akan menonaktifkan login pengguna tersebut.</small></p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#64748B',
        confirmButtonText: '<i class="bi bi-trash3 me-1"></i> Ya, Hapus Akun',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const formData = new URLSearchParams();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);

            fetch('ajax_role_permission_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil', data.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'Gagal memproses permintaan hapus.', 'error');
            });
        }
    });
}

// Handler Ubah Role User
function changeUserRole(userId, userName, currentRole, activeTabRole, roleLabel) {
    const rolesOptions = {
        'superadmin': 'Super Admin (Full Akses)',
        'admin': 'Admin (Operasional)',
        'adminsales': 'Admin Sales (Support Promosi & Ads)',
        'sales': 'Sales (Field Canvas & Mobile App)',
        'manager': 'Sales Manager (Supervisor & Approval)',
        'finance': 'Finance / Kasir (Keuangan & Price List)'
    };

    let optionsHtml = '';
    for (const [key, label] of Object.entries(rolesOptions)) {
        const isSelected = (key.toLowerCase() === currentRole.toLowerCase()) ? 'selected' : '';
        optionsHtml += `<option value="${key}" ${isSelected}>${label}</option>`;
    }

    Swal.fire({
        title: 'Ubah Role Pengguna',
        html: `
            <div class="text-start mb-3">
                <p class="text-muted small mb-2">Pilih role baru untuk akun <strong>${userName}</strong>:</p>
                <select id="swalNewRoleSelect" class="form-select" style="font-size: 13.5px; border-radius: 10px;">
                    ${optionsHtml}
                </select>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563EB',
        cancelButtonColor: '#64748B',
        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Simpan Role Baru',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            const newRole = document.getElementById('swalNewRoleSelect').value;
            if (!newRole) {
                Swal.showValidationMessage('Silakan pilih role baru!');
            }
            return newRole;
        }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.showLoading();
            const formData = new URLSearchParams();
            formData.append('action', 'change_user_role');
            formData.append('user_id', userId);
            formData.append('new_role', result.value);

            fetch('ajax_role_permission_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil', data.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Gagal', data.message, 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'Gagal memproses perubahan role.', 'error');
            });
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
