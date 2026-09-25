<?php
/**
 * includes/role_permission_helper.php
 * Helper untuk mengelola master menu & hak akses role pengguna
 */

if (!function_exists('ensureRolePermissionsTable')) {
    function ensureRolePermissionsTable($conn) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS `role_menu_permissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `role_name` VARCHAR(50) NOT NULL,
                `menu_key` VARCHAR(100) NOT NULL,
                `is_accessible` TINYINT(1) NOT NULL DEFAULT 1,
                `can_create` TINYINT(1) NOT NULL DEFAULT 1,
                `can_edit` TINYINT(1) NOT NULL DEFAULT 1,
                `can_delete` TINYINT(1) NOT NULL DEFAULT 1,
                `can_export` TINYINT(1) NOT NULL DEFAULT 1,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `updated_by` VARCHAR(100) DEFAULT 'System',
                UNIQUE KEY `uk_role_menu` (`role_name`, `menu_key`),
                INDEX `idx_role` (`role_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}

if (!function_exists('getAllSystemMenus')) {
    function getAllSystemMenus() {
        return [
            'menu_utama' => [
                'title' => 'Menu Utama Web',
                'icon'  => 'bi bi-laptop',
                'color' => '#3b82f6',
                'menus' => [
                    [
                        'key'         => 'dashboard_sales',
                        'label'       => 'Dashboard Utama',
                        'url'         => 'customer_management.php',
                        'icon'        => 'bi bi-grid-1x2-fill',
                        'color'       => '#2563eb',
                        'description' => 'Dashboard utama ringkasan KPI, leaderboard kompetisi sales, dan akses cepat',
                        'has_actions' => ['access', 'create', 'edit', 'delete', 'export']
                    ],
                    [
                        'key'         => 'dashboard_leaderboard',
                        'label'       => 'Database Customer & Forum',
                        'url'         => 'index.php',
                        'icon'        => 'bi bi-people-fill',
                        'color'       => '#10b981',
                        'description' => 'Manajemen database pelanggan, kontak PIC, filter wilayah, dan forum diskusi sales',
                        'has_actions' => ['access', 'export']
                    ],
                    [
                        'key'         => 'sales_work_plan',
                        'label'       => 'Rencana Kerja Sales',
                        'url'         => 'sales_work_plan.php',
                        'icon'        => 'bi bi-calendar-check-fill',
                        'color'       => '#6366f1',
                        'description' => 'Pencatatan target harian, jadwal follow-up, checklist dan verifikasi kunjungan',
                        'has_actions' => ['access', 'create', 'edit', 'delete', 'export']
                    ],
                    [
                        'key'         => 'followup_report',
                        'label'       => 'Follow Up Report',
                        'url'         => 'followup_report.php',
                        'icon'        => 'bi bi-journal-check',
                        'color'       => '#10b981',
                        'description' => 'Rekap riwayat follow up customer, catatan penawaran, dan hasil closing sales',
                        'has_actions' => ['access', 'create', 'edit', 'delete', 'export']
                    ],
                    [
                        'key'         => 'sales_data_web',
                        'label'       => 'Data Sales (Web)',
                        'url'         => 'sales_management.php',
                        'icon'        => 'bi bi-graph-up-arrow',
                        'color'       => '#06b6d4',
                        'description' => 'Statistik dan data penjualan tim sales di web management',
                        'has_actions' => ['access', 'export']
                    ],
                    [
                        'key'         => 'sales_management',
                        'label'       => 'Sales Management / Assignment',
                        'url'         => 'sales_assignment.php',
                        'icon'        => 'bi bi-people-fill',
                        'color'       => '#8b5cf6',
                        'description' => 'Pembagian assignment customer ke sales & rotasi tim',
                        'has_actions' => ['access', 'create', 'edit', 'delete']
                    ],
                ]
            ],
            'aplikasi_sales_canvas' => [
                'title' => 'Aplikasi Sales Canvas (Mobile)',
                'icon'  => 'bi bi-phone',
                'color' => '#0ea5e9',
                'menus' => [
                    [
                        'key'         => 'kegiatan_canvas',
                        'label'       => 'Dashboard Sales Canvas',
                        'url'         => 'modul-aplikasi-sales/kegiatan.php',
                        'icon'        => 'bi bi-grid-1x2-fill',
                        'color'       => '#2563eb',
                        'description' => 'Dashboard jadwal, summary tab kunjungan, dan analitik performa canvas',
                        'has_actions' => ['access', 'create', 'edit', 'delete']
                    ],
                    [
                        'key'         => 'kegiatan_baru',
                        'label'       => 'Tambah Kegiatan Baru',
                        'url'         => 'modul-aplikasi-sales/kegiatan-baru.php',
                        'icon'        => 'bi bi-calendar-plus-fill',
                        'color'       => '#3b82f6',
                        'description' => 'Form penjadwalan kunjungan canvasing ke toko / pelanggan baru',
                        'has_actions' => ['access', 'create']
                    ],
                    [
                        'key'         => 'laporan_kegiatan',
                        'label'       => 'Laporan Visit (GPS)',
                        'url'         => 'modul-aplikasi-sales/laporan-kegiatan.php',
                        'icon'        => 'bi bi-geo-alt-fill',
                        'color'       => '#06b6d4',
                        'description' => 'Laporan presensi kunjungan sales, clock in/out, foto lokasi, dan GPS tracking',
                        'has_actions' => ['access', 'export']
                    ],
                    [
                        'key'         => 'tiptok',
                        'label'       => 'TIP TOK (Konsinyasi)',
                        'url'         => 'modul-aplikasi-sales/tiptok.php',
                        'icon'        => 'bi bi-box-seam-fill',
                        'color'       => '#f59e0b',
                        'description' => 'Manajemen titip produk konsinyasi ke toko, stok opname, dan retur',
                        'has_actions' => ['access', 'create', 'edit', 'delete', 'export']
                    ],
                    [
                        'key'         => 'customer_dealer',
                        'label'       => 'Customer Toko / Dealer',
                        'url'         => 'modul-aplikasi-sales/customer.php',
                        'icon'        => 'bi bi-shop-window',
                        'color'       => '#10b981',
                        'description' => 'Master data toko, dealer, dan data prospek canvasing sales',
                        'has_actions' => ['access', 'create', 'edit', 'delete', 'export']
                    ],
                    [
                        'key'         => 'sales_app_accounts',
                        'label'       => 'Akun & Sales App',
                        'url'         => 'modul-aplikasi-sales/sales.php',
                        'icon'        => 'bi bi-person-badge-fill',
                        'color'       => '#06b6d4',
                        'description' => 'Daftar user aplikasi sales lapangan, status aktif, dan profil mobile',
                        'has_actions' => ['access', 'create', 'edit', 'delete']
                    ],
                    [
                        'key'         => 'scraping_gmaps',
                        'label'       => 'Scraper Leads Maps',
                        'url'         => 'modul-aplikasi-sales/scraping-gmaps.php',
                        'icon'        => 'bi bi-crosshair2',
                        'color'       => '#ef4444',
                        'description' => 'Alat pencarian leads calon customer toko CCTV otomatis via Google Maps',
                        'has_actions' => ['access', 'create', 'export']
                    ],
                ]
            ],
            'tools_operasional' => [
                'title' => 'Tools & Operasional',
                'icon'  => 'bi bi-tools',
                'color' => '#8b5cf6',
                'menus' => [
                    [
                        'key'         => 'announcements',
                        'label'       => 'Pengumuman',
                        'url'         => 'announcements.php',
                        'icon'        => 'bi bi-megaphone-fill',
                        'color'       => '#f59e0b',
                        'description' => 'Publikasi info internal perusahaan dan promo terbaru ke dashboard tim',
                        'has_actions' => ['access', 'create', 'edit', 'delete']
                    ],
                    [
                        'key'         => 'broadcast',
                        'label'       => 'Broadcast Schedule',
                        'url'         => 'broadcast_schedule.php',
                        'icon'        => 'bi bi-megaphone',
                        'color'       => '#10b981',
                        'description' => 'Jadwal dan pengiriman pesan blast WhatsApp ke customer',
                        'has_actions' => ['access', 'create', 'edit', 'delete']
                    ],
                    [
                        'key'         => 'promosi',
                        'label'       => 'Promosi Management',
                        'url'         => 'promosi_management.php',
                        'icon'        => 'bi bi-tags-fill',
                        'color'       => '#ec4899',
                        'description' => 'Katalog program promosi, banner diskon, dan reward penjualan',
                        'has_actions' => ['access', 'create', 'edit', 'delete']
                    ],
                    [
                        'key'         => 'price_list',
                        'label'       => 'Price List',
                        'url'         => 'price_list.php',
                        'icon'        => 'bi bi-ui-checks',
                        'color'       => '#3b82f6',
                        'description' => 'Daftar harga resmi produk Loewix untuk penawaran customer',
                        'has_actions' => ['access', 'create', 'edit', 'delete', 'export']
                    ],
                    [
                        'key'         => 'calculator_sales',
                        'label'       => 'Kalkulator Sales',
                        'url'         => 'calculator_sales.php',
                        'icon'        => 'bi bi-calculator',
                        'color'       => '#6366f1',
                        'description' => 'Kalkulasi harga modal, margin laba, dan diskon paket customer',
                        'has_actions' => ['access']
                    ],
                    [
                        'key'         => 'online_tools',
                        'label'       => 'Kalkulator Online',
                        'url'         => 'online_tools.php',
                        'icon'        => 'bi bi-123',
                        'color'       => '#0ea5e9',
                        'description' => 'Tools kalkulasi online untuk perhitungan teknis CCTV',
                        'has_actions' => ['access']
                    ],
                    [
                        'key'         => 'ads_report',
                        'label'       => 'Laporan Ads & Saldo',
                        'url'         => 'ads_report.php',
                        'icon'        => 'bi bi-bar-chart-line-fill',
                        'color'       => '#14b8a6',
                        'description' => 'Laporan saldo iklan, performa ads berbayar, dan leads masuk',
                        'has_actions' => ['access', 'create', 'export']
                    ],
                ]
            ],
            'super_admin_exclusive' => [
                'title' => 'Akses Khusus Super Admin',
                'icon'  => 'bi bi-shield-lock-fill',
                'color' => '#d97706',
                'menus' => [
                    [
                        'key'         => 'role_menu_access',
                        'label'       => 'Akses Role Menu',
                        'url'         => 'role_menu_access.php',
                        'icon'        => 'bi bi-shield-lock-fill',
                        'color'       => '#d97706',
                        'description' => 'Pengaturan hak akses dan izin menu per role (Super Admin Only)',
                        'has_actions' => ['access', 'edit']
                    ],
                    [
                        'key'         => 'export_excel_all',
                        'label'       => 'Izin Ekspor Data Sensitif (Excel)',
                        'url'         => 'customer_export.php',
                        'icon'        => 'bi bi-file-earmark-spreadsheet-fill',
                        'color'       => '#10b981',
                        'description' => 'Otorisasi dan persetujuan download data pelanggan seluruh Indonesia',
                        'has_actions' => ['access', 'export']
                    ],
                ]
            ]
        ];
    }
}

if (!function_exists('getAllSystemRoles')) {
    function getAllSystemRoles($conn = null) {
        $roles = [
            'superadmin' => [
                'key'         => 'superadmin',
                'label'       => 'Super Admin',
                'badge_color' => '#dc2626',
                'badge_bg'    => '#fef2f2',
                'badge_border'=> '#fca5a5',
                'icon'        => 'bi bi-shield-shaded',
                'description' => 'Akses mutlak & penuh ke seluruh sistem, konfigurasi role, dan persetujuan data.'
            ],
            'admin' => [
                'key'         => 'admin',
                'label'       => 'Admin',
                'badge_color' => '#2563eb',
                'badge_bg'    => '#eff6ff',
                'badge_border'=> '#bfdbfe',
                'icon'        => 'bi bi-person-gear',
                'description' => 'Administrator operasional, pengelolaan data pelanggan, sales, dan laporan harian.'
            ],
            'adminsales' => [
                'key'         => 'adminsales',
                'label'       => 'Admin Sales',
                'badge_color' => '#7c3aed',
                'badge_bg'    => '#f5f3ff',
                'badge_border'=> '#ddd6fe',
                'icon'        => 'bi bi-headset',
                'description' => 'Admin pendukung sales, pengelolaan promosi, laporan ads, dan rencana kerja sales.'
            ],
            'sales' => [
                'key'         => 'sales',
                'label'       => 'Sales (Field / Canvas)',
                'badge_color' => '#059669',
                'badge_bg'    => '#ecfdf5',
                'badge_border'=> '#a7f3d0',
                'icon'        => 'bi bi-person-badge',
                'description' => 'Tim sales lapangan, input kegiatan canvas, follow up toko, dan absensi GPS.'
            ],
            'manager' => [
                'key'         => 'manager',
                'label'       => 'Sales Manager',
                'badge_color' => '#d97706',
                'badge_bg'    => '#fffbeb',
                'badge_border'=> '#fde68a',
                'icon'        => 'bi bi-briefcase-fill',
                'description' => 'Manager supervisor, review performa leaderboard, verifikasi target kerja, dan monitoring tim.'
            ],
            'finance' => [
                'key'         => 'finance',
                'label'       => 'Finance / Kasir',
                'badge_color' => '#0891b2',
                'badge_bg'    => '#ecfeff',
                'badge_border'=> '#a5f3fc',
                'icon'        => 'bi bi-cash-stack',
                'description' => 'Bagian keuangan, akses price list, kalkulator sales, dan omset closing.'
            ]
        ];

        // Tambah role kustom jika ada di tabel sales
        if ($conn) {
            $q = $conn->query("SELECT DISTINCT role FROM sales WHERE role IS NOT NULL AND role != '' AND deleted_at IS NULL");
            if ($q) {
                while ($r = $q->fetch_assoc()) {
                    $rk = strtolower(trim($r['role']));
                    if (!isset($roles[$rk]) && !empty($rk)) {
                        $roles[$rk] = [
                            'key'         => $rk,
                            'label'       => ucwords($r['role']),
                            'badge_color' => '#475569',
                            'badge_bg'    => '#f1f5f9',
                            'badge_border'=> '#cbd5e1',
                            'icon'        => 'bi bi-person',
                            'description' => 'Role kustom dalam sistem.'
                        ];
                    }
                }
            }
        }

        return $roles;
    }
}

if (!function_exists('getRolePermissions')) {
    function getRolePermissions($conn, $roleName) {
        ensureRolePermissionsTable($conn);
        $roleName = strtolower(trim($roleName));
        
        $sql = "SELECT menu_key, is_accessible, can_create, can_edit, can_delete, can_export FROM role_menu_permissions WHERE role_name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $roleName);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $perms = [];
        while ($row = $res->fetch_assoc()) {
            $perms[$row['menu_key']] = [
                'is_accessible' => (int)$row['is_accessible'],
                'can_create'    => (int)$row['can_create'],
                'can_edit'      => (int)$row['can_edit'],
                'can_delete'    => (int)$row['can_delete'],
                'can_export'    => (int)$row['can_export'],
            ];
        }
        $stmt->close();

        // Jika belum ada row di DB untuk role ini, beri default preset
        $allModules = getAllSystemMenus();
        foreach ($allModules as $module) {
            foreach ($module['menus'] as $m) {
                $mk = $m['key'];
                if (!isset($perms[$mk])) {
                    if ($roleName === 'superadmin') {
                        $perms[$mk] = ['is_accessible'=>1, 'can_create'=>1, 'can_edit'=>1, 'can_delete'=>1, 'can_export'=>1];
                    } elseif ($roleName === 'admin') {
                        $perms[$mk] = ['is_accessible'=>($mk !== 'role_menu_access' ? 1 : 0), 'can_create'=>1, 'can_edit'=>1, 'can_delete'=>1, 'can_export'=>1];
                    } elseif ($roleName === 'adminsales') {
                        $isAcc = in_array($mk, ['promosi', 'ads_report', 'sales_work_plan', 'kegiatan_canvas', 'kegiatan_baru', 'laporan_kegiatan', 'tiptok', 'customer_dealer', 'announcements', 'broadcast', 'price_list', 'calculator_sales', 'online_tools']) ? 1 : 0;
                        $perms[$mk] = ['is_accessible'=>$isAcc, 'can_create'=>$isAcc, 'can_edit'=>$isAcc, 'can_delete'=>0, 'can_export'=>0];
                    } elseif ($roleName === 'sales') {
                        $isAcc = in_array($mk, ['dashboard_sales', 'sales_work_plan', 'kegiatan_canvas', 'kegiatan_baru', 'laporan_kegiatan', 'tiptok', 'customer_dealer', 'announcements', 'price_list', 'calculator_sales', 'online_tools']) ? 1 : 0;
                        $perms[$mk] = ['is_accessible'=>$isAcc, 'can_create'=>$isAcc, 'can_edit'=>$isAcc, 'can_delete'=>0, 'can_export'=>0];
                    } else {
                        $perms[$mk] = ['is_accessible'=>1, 'can_create'=>0, 'can_edit'=>0, 'can_delete'=>0, 'can_export'=>0];
                    }
                }
            }
        }

        return $perms;
    }
}

if (!function_exists('saveRoleMenuPermission')) {
    function saveRoleMenuPermission($conn, $roleName, $menuKey, $isAccessible, $canCreate = 1, $canEdit = 1, $canDelete = 1, $canExport = 1, $updatedBy = 'Super Admin') {
        ensureRolePermissionsTable($conn);
        $roleName = strtolower(trim($roleName));
        $menuKey  = trim($menuKey);

        $sql = "INSERT INTO role_menu_permissions (role_name, menu_key, is_accessible, can_create, can_edit, can_delete, can_export, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    is_accessible = VALUES(is_accessible),
                    can_create    = VALUES(can_create),
                    can_edit      = VALUES(can_edit),
                    can_delete    = VALUES(can_delete),
                    can_export    = VALUES(can_export),
                    updated_by    = VALUES(updated_by)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiiiis", $roleName, $menuKey, $isAccessible, $canCreate, $canEdit, $canDelete, $canExport, $updatedBy);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
