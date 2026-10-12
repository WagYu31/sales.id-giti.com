<?php
/**
 * wa_support_settings.php
 * Halaman Manajemen Pesan Bantuan Masuk & Pengaturan WhatsApp Gateway (Superadmin Only)
 * Desain UI Premium inspired by 21st.dev & tasteskill.dev
 * Proteksi Keamanan Sandi Superadmin: Wagyu31
 */

$page_title = 'Pusat Pesan Bantuan WhatsApp';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (strtolower(trim($_SESSION['role'] ?? '')) !== 'superadmin') {
    $_SESSION['flash_message_error'] = "Akses ditolak. Halaman ini hanya untuk Super Admin.";
    header("Location: index.php");
    exit();
}

define('GATEWAY_AUTH_KEY', 'Wagyu31');

// Pastikan tabel support_messages sudah ada
$conn->query("
    CREATE TABLE IF NOT EXISTS `support_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `nama_pengirim` VARCHAR(255) NOT NULL,
        `role` VARCHAR(50) NOT NULL,
        `halaman` VARCHAR(255) NULL,
        `target_no` VARCHAR(50) DEFAULT '085771593522',
        `pesan` TEXT NOT NULL,
        `status_kirim` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        `gateway_response` TEXT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$msg_success = '';
$msg_error = '';

// Proses Simpan Pengaturan (Wajib sandi Wagyu31)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $auth_pass = trim($_POST['admin_auth_pass'] ?? '');
    if ($auth_pass !== GATEWAY_AUTH_KEY) {
        $msg_error = "Akses Ditolak: Sandi otorisasi ('Wagyu31') salah atau belum dimasukkan untuk Simpan Konfigurasi!";
    } else {
        $wa_target = trim($_POST['wa_support_number'] ?? '085771593522');
        $fonnte_token = trim($_POST['fonnte_token'] ?? '');

        // 1. Simpan ke JSON file (terproteksi di .gitignore, tidak pernah terpush)
        $configFile = __DIR__ . '/includes/wa_gateway_config.json';
        $configData = [
            'wa_support_number' => $wa_target,
            'fonnte_token' => $fonnte_token,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        @file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));

        // 2. Pastikan kolom setting_value di DB support TEXT panjang
        @$conn->query("ALTER TABLE settings MODIFY COLUMN setting_value TEXT NULL");

        // 3. Simpan ke database settings
        @$conn->query("DELETE FROM settings WHERE setting_key IN ('wa_support_number', 'fonnte_token')");

        $stmt1 = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('wa_support_number', ?)");
        if ($stmt1) {
            $stmt1->bind_param("s", $wa_target);
            $stmt1->execute();
            $stmt1->close();
        }

        $stmt2 = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('fonnte_token', ?)");
        if ($stmt2) {
            $stmt2->bind_param("s", $fonnte_token);
            $stmt2->execute();
            $stmt2->close();
        }

        $msg_success = "Pengaturan WhatsApp Gateway & Token Fonnte berhasil diperbarui!";
    }
}

// Proses Tes Kirim WA (Wajib sandi Wagyu31)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_send_wa'])) {
    $auth_pass = trim($_POST['admin_auth_pass'] ?? '');
    if ($auth_pass !== GATEWAY_AUTH_KEY) {
        $msg_error = "Akses Ditolak: Sandi otorisasi ('Wagyu31') salah untuk melakukan tes pengiriman WhatsApp!";
    } else {
        $test_target = trim($_POST['test_target'] ?? '085771593522');
        $test_token = trim($_POST['test_token'] ?? '');

        if (empty($test_token)) {
            $msg_error = "Token Fonnte belum diisi. Masukkan token terlebih dahulu untuk melakukan tes pengiriman!";
        } else {
            $testMsg = "*TES KONEKSI WHATSAPP GATEWAY*\n\nHalo Admin Loewix! Ini adalah pesan tes dari sistem *sales.id-giti.com*.\nWhatsApp Gateway Fonnte Anda telah *BERHASIL TERHUBUNG* dan siap menerima laporan kendala sales!";

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => [
                    'target' => $test_target,
                    'message' => $testMsg,
                    'countryCode' => '62'
                ],
                CURLOPT_HTTPHEADER => [
                    "Authorization: {$test_token}"
                ],
            ]);

            $response = curl_exec($curl);
            $curlErr = curl_error($curl);
            curl_close($curl);

            if ($curlErr) {
                $msg_error = "Gagal terhubung ke server Fonnte: " . $curlErr;
            } else {
                $respJson = json_decode($response, true);
                if (isset($respJson['status']) && $respJson['status'] == true) {
                    $msg_success = "✅ SUKSES! Pesan tes WhatsApp telah berhasil terkirim ke nomor " . htmlspecialchars($test_target) . "!";
                } else {
                    $errDetail = $respJson['reason'] ?? ($respJson['message'] ?? $response);
                    $msg_error = "Gateway Fonnte merespon error: " . htmlspecialchars($errDetail) . ". Pastikan device di Fonnte sudah berstatus 'Connect' (Scan QR).";
                }
            }
        }
    }
}

// Ambil Pengaturan Saat Ini
$current_wa = '085771593522';
$current_token = '';

// Baca dari JSON file terlebih dahulu
$configFile = __DIR__ . '/includes/wa_gateway_config.json';
if (file_exists($configFile)) {
    $cfg = json_decode(@file_get_contents($configFile), true);
    if (!empty($cfg['wa_support_number'])) $current_wa = trim($cfg['wa_support_number']);
    if (!empty($cfg['fonnte_token'])) $current_token = trim($cfg['fonnte_token']);
}

// Fallback ke database jika di JSON belum ada
if (empty($current_token)) {
    $resSettings = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('wa_support_number', 'fonnte_token')");
    if ($resSettings) {
        while ($r = $resSettings->fetch_assoc()) {
            if ($r['setting_key'] === 'wa_support_number' && !empty($r['setting_value'])) $current_wa = $r['setting_value'];
            if ($r['setting_key'] === 'fonnte_token' && !empty($r['setting_value'])) $current_token = $r['setting_value'];
        }
    }
}

// Ambil Daftar Pesan Masuk
$resMessages = $conn->query("SELECT * FROM support_messages ORDER BY id DESC LIMIT 100");
$messages = $resMessages ? $resMessages->fetch_all(MYSQLI_ASSOC) : [];

$totalMessages = count($messages);
$sentMessages = 0;
foreach ($messages as $m) {
    if ($m['status_kirim'] === 'sent') $sentMessages++;
}
$savedMessages = $totalMessages - $sentMessages;

require_once 'includes/header.php';
?>

<style>
/* ==========================================================================
   21st.dev & tasteskill.dev Design System Styles
   ========================================================================== */
:root {
    --brand-primary: #2563eb;
    --brand-emerald: #10b981;
    --brand-emerald-dark: #059669;
    --surface-card: #ffffff;
    --surface-muted: #f8fafc;
    --border-subtle: rgba(226, 232, 240, 0.85);
    --border-highlight: rgba(37, 99, 235, 0.2);
    --shadow-premium: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 12px 24px -4px rgba(0, 0, 0, 0.04);
    --shadow-float: 0 20px 30px -10px rgba(0, 0, 0, 0.08);
}

.page-header-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid var(--border-subtle);
    border-radius: 20px;
    padding: 24px 28px;
    box-shadow: var(--shadow-premium);
    position: relative;
    overflow: hidden;
}

.page-header-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 260px;
    height: 100%;
    background: radial-gradient(circle at top right, rgba(37, 99, 235, 0.06), transparent 70%);
    pointer-events: none;
}

/* Stat KPI Mini Cards */
.kpi-card {
    background: #ffffff;
    border: 1px solid var(--border-subtle);
    border-radius: 16px;
    padding: 16px 20px;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-premium);
    border-color: rgba(37, 99, 235, 0.3);
}

/* Glass & Card Design */
.premium-card {
    background: #ffffff;
    border: 1px solid var(--border-subtle);
    border-radius: 22px;
    box-shadow: var(--shadow-premium);
    transition: all 0.25s ease;
    position: relative;
}

.card-title-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

/* Pulse Badge & Dots */
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.02em;
    transition: all 0.2s ease;
}
.pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    position: relative;
}
.pulse-dot.emerald {
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulseEmerald 2s infinite;
}
@keyframes pulseEmerald {
    0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.pulse-dot.amber {
    background: #f59e0b;
    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
    animation: pulseAmber 2s infinite;
}
@keyframes pulseAmber {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
    70% { box-shadow: 0 0 0 7px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

/* Security Locked Banner */
.security-banner {
    border-radius: 14px;
    padding: 12px 16px;
    transition: all 0.3s ease;
}
.security-banner.locked {
    background: linear-gradient(135deg, rgba(254, 242, 242, 0.9) 0%, rgba(255, 241, 242, 0.95) 100%);
    border: 1px solid rgba(244, 63, 94, 0.25);
    color: #9f1239;
}
.security-banner.unlocked {
    background: linear-gradient(135deg, rgba(240, 253, 244, 0.9) 0%, rgba(236, 253, 245, 0.95) 100%);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #065f46;
}

/* Form Controls with 21st.dev Style */
.form-label-custom {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.custom-input-group {
    border-radius: 12px;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background: #ffffff;
}

.custom-input-group .form-control {
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    font-size: 13.5px;
    padding: 11px 14px;
    transition: all 0.2s ease;
    font-weight: 500;
}

.custom-input-group .form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    background: #ffffff;
}

/* Locked Input State */
.custom-input-group.is-locked .form-control {
    background: #f8fafc !important;
    color: #64748b !important;
    cursor: pointer;
    border-color: #e2e8f0;
}
.custom-input-group.is-locked .form-control:hover {
    border-color: #cbd5e1;
    background: #f1f5f9 !important;
}

.custom-input-group .input-group-text {
    border: 1.5px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 12px;
    padding: 0 14px;
    color: #64748b;
}

/* Action Buttons */
.btn-save-glow {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    padding: 11px 20px;
    font-weight: 600;
    font-size: 13.5px;
    letter-spacing: 0.01em;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
    transition: all 0.2s ease;
}
.btn-save-glow:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
    transform: translateY(-1px);
    color: #ffffff;
}

.btn-test-wa {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.12) 100%);
    color: #047857;
    border: 1.5px solid rgba(16, 185, 129, 0.35);
    border-radius: 12px;
    padding: 10px 18px;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s ease;
}
.btn-test-wa:hover {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    transform: translateY(-1px);
}

/* 21st.dev Style Password Verification Modal */
.modal-glass {
    backdrop-filter: blur(14px);
    background: rgba(15, 23, 42, 0.65);
}
.modal-glass-card {
    background: #ffffff;
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
    overflow: hidden;
}

.shield-avatar {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border: 1.5px solid rgba(37, 99, 235, 0.2);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #2563eb;
    font-size: 26px;
    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.12);
}

/* Shake Animation on Wrong Password */
@keyframes shakeError {
    0%, 100% { transform: translateX(0); }
    20%, 60% { transform: translateX(-8px); }
    40%, 80% { transform: translateX(8px); }
}
.shake-it {
    animation: shakeError 0.4s ease-in-out;
}

/* Table Design */
.modern-table thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 14px 16px;
    border-bottom: 1.5px solid #e2e8f0;
}
.modern-table tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.modern-table tbody tr:hover td {
    background: #f8fafc;
}
</style>

<div class="container-fluid px-3 px-md-4 py-3">

    <!-- Top Header -->
    <div class="page-header-card mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <div class="card-title-icon bg-success-subtle text-success">
                    <i class="bi bi-whatsapp"></i>
                </div>
                <h4 class="fw-bold mb-0 text-dark">Pusat Pesan Bantuan &amp; WhatsApp Gateway</h4>
            </div>
            <p class="text-muted small mb-0 ms-1">
                Kelola integrasi WhatsApp Gateway &amp; monitoring laporan kendala sales secara real-time.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="https://wa.me/<?= preg_replace('/^0/', '62', $current_wa) ?>" target="_blank" class="btn btn-outline-success rounded-pill px-3 py-2 fw-bold btn-sm d-inline-flex align-items-center gap-2 shadow-sm bg-white">
                <i class="bi bi-whatsapp fs-6 text-success"></i> Chat Target (<?= htmlspecialchars($current_wa) ?>)
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($msg_success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4 border-0 d-flex align-items-center" role="alert" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0 !important;">
            <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
            <div class="fw-semibold small"><?= htmlspecialchars($msg_success) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($msg_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4 border-0 d-flex align-items-center" role="alert" style="background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3 !important;">
            <i class="bi bi-shield-x-fill fs-5 me-2 text-danger"></i>
            <div class="fw-semibold small"><?= htmlspecialchars($msg_error) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- ===================================================================
             CARD KONFIGURASI WA GATEWAY (GAMBAR 3) - TERPROTEKSI SANDI Wagyu31
             =================================================================== -->
        <div class="col-lg-4 col-12">
            <div class="premium-card p-4 h-100" id="cardGatewayConfig">
                
                <!-- Card Header with Lock / Unlock Status -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="card-title-icon bg-primary-subtle text-primary">
                            <i class="bi bi-sliders2-vertical"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Konfigurasi WA Gateway</h6>
                            <span class="text-muted" style="font-size: 11px;">Pengaturan jalur notifikasi bot</span>
                        </div>
                    </div>

                    <div id="badgeGatewayStatus">
                        <?php if (!empty($current_token)): ?>
                            <span class="status-pill" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                                <span class="pulse-dot emerald"></span> Aktif
                            </span>
                        <?php else: ?>
                            <span class="status-pill" style="background: #fffbeb; color: #92400e; border: 1px solid #fde68a;">
                                <span class="pulse-dot amber"></span> Belum Ada Token
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Security Access Banner (Wagyu31 Protection) -->
                <div id="securityBanner" class="security-banner locked mb-3 d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shield-lock-fill fs-5" id="securityBannerIcon"></i>
                        <div>
                            <div class="fw-bold" style="font-size: 12px;" id="securityBannerTitle">Konfigurasi Terproteksi</div>
                            <div style="font-size: 11px; opacity: 0.85;" id="securityBannerSub">Perlu sandi <code>Wagyu31</code> untuk akses &amp; tes.</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-dark rounded-pill px-2.5 py-1 text-nowrap fw-bold" style="font-size: 11px;" id="btnToggleAuth" onclick="handleAuthToggle()">
                        <i class="bi bi-key-fill me-1"></i> Buka Akses
                    </button>
                </div>

                <!-- Info Box Token -->
                <?php if (!empty($current_token)): ?>
                    <div class="p-2.5 rounded-3 mb-3 small d-flex align-items-center gap-2.5" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                        <i class="bi bi-shield-check text-success fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 12px;">Token Terpasang &amp; Aman</div>
                            <div class="text-muted" style="font-size: 11px;">Pesan kendala akan diteruskan otomatis ke WA HP tujuan.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-2.5 rounded-3 mb-3 small d-flex align-items-start gap-2" style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e;">
                        <i class="bi bi-info-circle-fill text-warning fs-6 mt-0.5"></i>
                        <div style="font-size: 11px;">
                            <strong>Token Fonnte belum tersimpan.</strong> Buka akses lalu tempelkan token dari Fonnte agar pesan diteruskan langsung ke HP.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Konfigurasi (Wajib Sandi Wagyu31) -->
                <form method="POST" action="wa_support_settings.php" id="formSaveGateway" onsubmit="return handleFormSaveSubmit(event)">
                    <input type="hidden" name="save_settings" value="1">
                    <input type="hidden" name="admin_auth_pass" id="saveAdminAuthPass" value="">

                    <!-- Poin 1: Ubah Nomor WA -->
                    <div class="mb-3">
                        <label class="form-label-custom">
                            <span>1. Nomor WA Tujuan</span>
                            <span class="badge bg-light text-secondary border fw-normal" id="labelLockPhone" style="font-size: 10px;">
                                <i class="bi bi-lock-fill text-danger me-0.5"></i> Terkunci
                            </span>
                        </label>
                        <div class="input-group custom-input-group is-locked" id="groupPhone">
                            <span class="input-group-text"><i class="bi bi-telephone text-success"></i></span>
                            <input type="text" name="wa_support_number" id="inputWaNumber" class="form-control" 
                                   value="<?= htmlspecialchars($current_wa) ?>" required readonly 
                                   placeholder="085771593522" 
                                   onclick="handleInputAccess('edit_phone', 'mengubah Nomor WhatsApp Tujuan')">
                            <button class="btn btn-outline-secondary" type="button" id="btnLockPhone" 
                                    onclick="handleInputAccess('edit_phone', 'mengubah Nomor WhatsApp Tujuan')">
                                <i class="bi bi-lock-fill text-danger" id="iconLockPhone"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">Semua laporan kendala dari bot web akan diteruskan ke nomor ini.</small>
                    </div>

                    <!-- Poin 2: Ubah Token API Fonnte & Klik Icon Mata -->
                    <div class="mb-3">
                        <label class="form-label-custom">
                            <span>2. Token API Fonnte</span>
                            <span class="badge bg-light text-secondary border fw-normal" id="labelLockToken" style="font-size: 10px;">
                                <i class="bi bi-lock-fill text-danger me-0.5"></i> Terkunci
                            </span>
                        </label>
                        <div class="input-group custom-input-group is-locked" id="groupToken">
                            <span class="input-group-text"><i class="bi bi-key text-warning"></i></span>
                            <input type="password" name="fonnte_token" id="fonnteTokenInput" class="form-control font-monospace" 
                                   value="<?= htmlspecialchars($current_token) ?>" readonly
                                   placeholder="Tempel token di sini (contoh: a1b2c3d...)" 
                                   onclick="handleInputAccess('edit_token', 'mengedit Token API Fonnte')">
                            <button class="btn btn-outline-secondary" type="button" id="btnToggleTokenEye" 
                                    onclick="handleTokenEyeClick()" title="Lihat / Sembunyikan Token (Perlu Sandi Wagyu31)">
                                <i class="bi bi-eye-slash-fill text-muted" id="iconTokenEye"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                            Buka <a href="https://fonnte.com" target="_blank" class="fw-bold text-primary text-decoration-none">fonnte.com</a> > klik tombol <strong>Token</strong> > salin kodenya &amp; tempel di atas.
                        </small>
                    </div>

                    <!-- Poin 3: Simpan Konfigurasi -->
                    <button type="submit" class="btn btn-save-glow w-100 d-inline-flex align-items-center justify-content-center gap-2 mb-3" id="btnSubmitSave">
                        <i class="bi bi-lock-fill text-white-50" id="iconLockSave"></i>
                        <span>Simpan Konfigurasi</span>
                    </button>
                </form>

                <!-- Poin 4: Tes Kirim Pesan WA (Wajib Sandi Wagyu31) -->
                <?php if (!empty($current_token)): ?>
                    <hr class="my-3" style="border-color: #e2e8f0;">
                    <form method="POST" action="wa_support_settings.php" id="formTestSendWa" onsubmit="return handleFormTestSubmit(event)">
                        <input type="hidden" name="test_send_wa" value="1">
                        <input type="hidden" name="test_target" value="<?= htmlspecialchars($current_wa) ?>">
                        <input type="hidden" name="test_token" value="<?= htmlspecialchars($current_token) ?>">
                        <input type="hidden" name="admin_auth_pass" id="testAdminAuthPass" value="">

                        <button type="submit" class="btn btn-test-wa w-100 d-inline-flex align-items-center justify-content-center gap-2" id="btnSubmitTest">
                            <i class="bi bi-lock-fill" id="iconLockTest"></i>
                            <span>Tes Kirim Pesan WA Sekarang</span>
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        </div>

        <!-- ===================================================================
             CARD RIWAYAT PESAN KENDALA MASUK (TABEL DATA KENDALA)
             =================================================================== -->
        <div class="col-lg-8 col-12">
            <div class="premium-card p-4 h-100">
                
                <!-- Quick KPI Summary Strip -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4 col-12">
                        <div class="kpi-card d-flex align-items-center gap-3">
                            <div class="card-title-icon bg-primary-subtle text-primary">
                                <i class="bi bi-inbox-fill"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Total Pesan Masuk</div>
                                <h4 class="fw-bold text-dark mb-0"><?= number_format($totalMessages) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="kpi-card d-flex align-items-center gap-3">
                            <div class="card-title-icon bg-success-subtle text-success">
                                <i class="bi bi-send-check-fill"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Terkirim ke WhatsApp</div>
                                <h4 class="fw-bold text-dark mb-0"><?= number_format($sentMessages) ?></h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-12">
                        <div class="kpi-card d-flex align-items-center gap-3">
                            <div class="card-title-icon bg-secondary-subtle text-secondary">
                                <i class="bi bi-database-check"></i>
                            </div>
                            <div>
                                <div class="text-muted small fw-semibold">Tersimpan di Server</div>
                                <h4 class="fw-bold text-dark mb-0"><?= number_format($savedMessages) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Header with Search Bar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="card-title-icon bg-success-subtle text-success">
                            <i class="bi bi-chat-left-text-fill"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-0">Riwayat Pesan Kendala Masuk</h6>
                            <span class="text-muted" style="font-size: 11px;">Semua laporan kendala sales dari bot floating web</span>
                        </div>
                    </div>

                    <!-- Search Input -->
                    <div style="min-width: 240px;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="tableFilterInput" class="form-control border-start-0" placeholder="Cari pengirim, halaman, pesan..." onkeyup="filterMessagesTable()">
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-chat-square-dots fs-1 text-slate-300 d-block mb-3"></i>
                        <h6 class="fw-bold text-dark mb-1">Belum Ada Pesan Masuk</h6>
                        <p class="small text-muted mb-0">Ketika tim sales mengirim pesan dari widget bot, pesannya akan tercatat rapi di sini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive rounded-3 border" style="max-height: 520px; overflow-y: auto;">
                        <table class="table modern-table align-middle mb-0" id="messagesTable">
                            <thead class="sticky-top">
                                <tr>
                                    <th style="width: 130px;">Waktu</th>
                                    <th style="width: 160px;">Pengirim</th>
                                    <th style="width: 150px;">Halaman</th>
                                    <th>Pesan Kendala</th>
                                    <th style="width: 120px;" class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $m): ?>
                                    <tr class="message-row">
                                        <td class="text-nowrap text-secondary small">
                                            <div class="fw-semibold text-dark" style="font-size: 12px;"><?= date('d/m/Y', strtotime($m['created_at'])) ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($m['created_at'])) ?> WIB</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark text-truncate" style="max-width: 150px;"><?= htmlspecialchars($m['nama_pengirim']) ?></div>
                                            <span class="badge rounded-pill bg-light text-dark border px-2 py-0.5" style="font-size: 10px;"><?= htmlspecialchars($m['role']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary font-monospace px-2 py-1 rounded-2 text-truncate d-inline-block" style="font-size: 11px; max-width: 140px;" title="<?= htmlspecialchars($m['halaman'] ?: '-') ?>">
                                                <?= htmlspecialchars($m['halaman'] ?: '-') ?>
                                            </span>
                                        </td>
                                        <td style="max-width: 320px;">
                                            <div class="text-wrap text-break small text-dark message-content" style="line-height: 1.45;">
                                                <?= nl2br(htmlspecialchars($m['pesan'])) ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($m['status_kirim'] === 'sent'): ?>
                                                <span class="status-pill" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                                                    <i class="bi bi-check-all text-success"></i> Terkirim WA
                                                </span>
                                            <?php else: ?>
                                                <span class="status-pill" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;">
                                                    <i class="bi bi-check-circle text-primary"></i> Tersimpan
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- =======================================================================
     MODAL OTORISASI KEAMANAN SUPERADMIN (Sandi: Wagyu31) - 21st.dev Style
     ======================================================================= -->
<div class="modal fade modal-glass" id="authSecurityModal" tabindex="-1" aria-labelledby="authSecurityModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content modal-glass-card p-2" id="modalCardContainer">
            
            <div class="modal-body p-4 text-center">
                <!-- Icon Shield -->
                <div class="shield-avatar mb-3" id="shieldAvatar">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>

                <h5 class="fw-bold text-dark mb-1" id="authModalTitle">Verifikasi Otorisasi Superadmin</h5>
                <p class="text-muted small mb-3" id="authModalDesc">
                    Akses konfigurasi ini dikunci. Masukkan sandi keamanan untuk melanjutkan.
                </p>

                <!-- Action Context Pill -->
                <div class="badge rounded-pill bg-light text-primary border px-3 py-1.5 mb-3 small d-inline-flex align-items-center gap-1.5" id="authTargetActionBadge">
                    <i class="bi bi-arrow-right-circle-fill"></i> <span id="authActionText">Otorisasi Akses</span>
                </div>

                <!-- Input Password Form -->
                <form id="authVerifyForm" onsubmit="handleAuthSubmit(event)">
                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold text-dark mb-1">Sandi Otorisasi:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-key-fill text-warning"></i></span>
                            <input type="password" id="authPasswordInput" class="form-control" placeholder="Masukkan sandi..." autocomplete="current-password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleModalPasswordEye()">
                                <i class="bi bi-eye-fill" id="modalEyeIcon"></i>
                            </button>
                        </div>
                        <div id="authErrorMessage" class="text-danger small mt-1.5 fw-semibold d-none">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Sandi salah! Silakan coba lagi.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light rounded-3 w-50 py-2 fw-semibold text-secondary" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-primary rounded-3 w-50 py-2 fw-bold shadow-sm" id="btnVerifySubmit">
                            <i class="bi bi-unlock-fill me-1"></i> Buka Akses
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
/**
 * Logika Keamanan Client-Side Superadmin (Sandi: Wagyu31)
 * Memproteksi 4 poin:
 * 1. Ubah nomor WA
 * 2. Ubah Token API Fonnte atau klik icon mata
 * 3. Simpan Konfigurasi
 * 4. Tes Kirim Pesan WA
 */
const REQUIRED_AUTH_KEY = "Wagyu31";
let isGatewayUnlocked = false;
let pendingAuthAction = null; // 'edit_phone', 'edit_token', 'toggle_token_eye', 'submit_save', 'submit_test', 'general'
let authModalInstance = null;

document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('authSecurityModal');
    if (modalEl) {
        authModalInstance = new bootstrap.Modal(modalEl);
    }
});

// Trigger Modal Otorisasi
function requestAccess(actionType, actionDescription) {
    if (isGatewayUnlocked) {
        executeAction(actionType);
        return;
    }

    pendingAuthAction = actionType;
    document.getElementById('authActionText').textContent = actionDescription || 'Otorisasi Akses';
    document.getElementById('authPasswordInput').value = '';
    document.getElementById('authErrorMessage').classList.add('d-none');
    document.getElementById('modalCardContainer').classList.remove('shake-it');

    if (authModalInstance) {
        authModalInstance.show();
        setTimeout(() => {
            const inp = document.getElementById('authPasswordInput');
            if (inp) inp.focus();
        }, 300);
    }
}

// Buka Akses Tombol Header Banner
function handleAuthToggle() {
    if (isGatewayUnlocked) {
        // Kunci Kembali
        lockGateway();
    } else {
        requestAccess('general', 'Membuka Akses Konfigurasi WA Gateway');
    }
}

// Handler Klik Input yang Terkunci (Poin 1 & Poin 2)
function handleInputAccess(type, desc) {
    if (!isGatewayUnlocked) {
        requestAccess(type, desc);
    }
}

// Handler Klik Icon Mata Token (Poin 2)
function handleTokenEyeClick() {
    if (!isGatewayUnlocked) {
        requestAccess('toggle_token_eye', 'Melihat Token API Fonnte');
    } else {
        // Toggle view
        const inp = document.getElementById('fonnteTokenInput');
        const icon = document.getElementById('iconTokenEye');
        if (inp.type === 'password') {
            inp.type = 'text';
            icon.className = 'bi bi-eye-fill text-primary';
        } else {
            inp.type = 'password';
            icon.className = 'bi bi-eye-slash-fill text-muted';
        }
    }
}

// Handler Submit Form Simpan (Poin 3)
function handleFormSaveSubmit(e) {
    if (!isGatewayUnlocked) {
        e.preventDefault();
        requestAccess('submit_save', 'Menyimpan Konfigurasi Gateway');
        return false;
    }
    document.getElementById('saveAdminAuthPass').value = REQUIRED_AUTH_KEY;
    return true;
}

// Handler Submit Form Tes Kirim (Poin 4)
function handleFormTestSubmit(e) {
    if (!isGatewayUnlocked) {
        e.preventDefault();
        requestAccess('submit_test', 'Melakukan Tes Kirim WhatsApp');
        return false;
    }
    document.getElementById('testAdminAuthPass').value = REQUIRED_AUTH_KEY;
    return true;
}

// Verifikasi Sandi di Modal
function handleAuthSubmit(e) {
    e.preventDefault();
    const enteredPass = document.getElementById('authPasswordInput').value.trim();

    if (enteredPass === REQUIRED_AUTH_KEY) {
        // SUKSES
        document.getElementById('authErrorMessage').classList.add('d-none');
        if (authModalInstance) authModalInstance.hide();
        
        unlockGateway();
        executeAction(pendingAuthAction);
        pendingAuthAction = null;
    } else {
        // SALAH
        const errMsg = document.getElementById('authErrorMessage');
        errMsg.classList.remove('d-none');
        const card = document.getElementById('modalCardContainer');
        card.classList.remove('shake-it');
        void card.offsetWidth; // trigger reflow
        card.classList.add('shake-it');
        document.getElementById('authPasswordInput').focus();
    }
}

// Buka Kunci Panel (Unlock)
function unlockGateway() {
    isGatewayUnlocked = true;

    // Update Banner
    const banner = document.getElementById('securityBanner');
    banner.className = 'security-banner unlocked mb-3 d-flex align-items-center justify-content-between gap-2';
    document.getElementById('securityBannerIcon').className = 'bi bi-shield-check-fill fs-5 text-success';
    document.getElementById('securityBannerTitle').textContent = 'Akses Terbuka (Superadmin)';
    document.getElementById('securityBannerSub').textContent = 'Anda memiliki izin penuh mengubah & menguji konfigurasi.';
    
    const btnToggle = document.getElementById('btnToggleAuth');
    btnToggle.className = 'btn btn-sm btn-outline-success rounded-pill px-2.5 py-1 text-nowrap fw-bold';
    btnToggle.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Kunci Kembali';

    // Buka Poin 1 (Nomor WA)
    const inputPhone = document.getElementById('inputWaNumber');
    inputPhone.removeAttribute('readonly');
    document.getElementById('groupPhone').classList.remove('is-locked');
    document.getElementById('labelLockPhone').className = 'badge bg-success-subtle text-success border border-success-subtle fw-normal';
    document.getElementById('labelLockPhone').innerHTML = '<i class="bi bi-unlock-fill me-0.5"></i> Terbuka';
    document.getElementById('iconLockPhone').className = 'bi bi-pencil-fill text-primary';

    // Buka Poin 2 (Token Fonnte)
    const inputToken = document.getElementById('fonnteTokenInput');
    inputToken.removeAttribute('readonly');
    document.getElementById('groupToken').classList.remove('is-locked');
    document.getElementById('labelLockToken').className = 'badge bg-success-subtle text-success border border-success-subtle fw-normal';
    document.getElementById('labelLockToken').innerHTML = '<i class="bi bi-unlock-fill me-0.5"></i> Terbuka';
    document.getElementById('iconTokenEye').className = 'bi bi-eye-slash text-muted';

    // Buka Poin 3 (Simpan)
    const iconSave = document.getElementById('iconLockSave');
    if (iconSave) iconSave.className = 'bi bi-check2-circle text-white';
    document.getElementById('saveAdminAuthPass').value = REQUIRED_AUTH_KEY;

    // Buka Poin 4 (Tes Kirim)
    const iconTest = document.getElementById('iconLockTest');
    if (iconTest) iconTest.className = 'bi bi-whatsapp';
    const testAuthInp = document.getElementById('testAdminAuthPass');
    if (testAuthInp) testAuthInp.value = REQUIRED_AUTH_KEY;
}

// Kunci Kembali Panel (Lock)
function lockGateway() {
    isGatewayUnlocked = false;

    // Update Banner
    const banner = document.getElementById('securityBanner');
    banner.className = 'security-banner locked mb-3 d-flex align-items-center justify-content-between gap-2';
    document.getElementById('securityBannerIcon').className = 'bi bi-shield-lock-fill fs-5 text-danger';
    document.getElementById('securityBannerTitle').textContent = 'Konfigurasi Terproteksi';
    document.getElementById('securityBannerSub').innerHTML = 'Perlu sandi <code>Wagyu31</code> untuk akses &amp; tes.';

    const btnToggle = document.getElementById('btnToggleAuth');
    btnToggle.className = 'btn btn-sm btn-dark rounded-pill px-2.5 py-1 text-nowrap fw-bold';
    btnToggle.innerHTML = '<i class="bi bi-key-fill me-1"></i> Buka Akses';

    // Kunci Poin 1 (Nomor WA)
    const inputPhone = document.getElementById('inputWaNumber');
    inputPhone.setAttribute('readonly', 'true');
    document.getElementById('groupPhone').classList.add('is-locked');
    document.getElementById('labelLockPhone').className = 'badge bg-light text-secondary border fw-normal';
    document.getElementById('labelLockPhone').innerHTML = '<i class="bi bi-lock-fill text-danger me-0.5"></i> Terkunci';
    document.getElementById('iconLockPhone').className = 'bi bi-lock-fill text-danger';

    // Kunci Poin 2 (Token Fonnte)
    const inputToken = document.getElementById('fonnteTokenInput');
    inputToken.setAttribute('readonly', 'true');
    inputToken.type = 'password';
    document.getElementById('groupToken').classList.add('is-locked');
    document.getElementById('labelLockToken').className = 'badge bg-light text-secondary border fw-normal';
    document.getElementById('labelLockToken').innerHTML = '<i class="bi bi-lock-fill text-danger me-0.5"></i> Terkunci';
    document.getElementById('iconTokenEye').className = 'bi bi-eye-slash-fill text-muted';

    // Kunci Poin 3 (Simpan)
    const iconSave = document.getElementById('iconLockSave');
    if (iconSave) iconSave.className = 'bi bi-lock-fill text-white-50';
    document.getElementById('saveAdminAuthPass').value = '';

    // Kunci Poin 4 (Tes Kirim)
    const iconTest = document.getElementById('iconLockTest');
    if (iconTest) iconTest.className = 'bi bi-lock-fill';
    const testAuthInp = document.getElementById('testAdminAuthPass');
    if (testAuthInp) testAuthInp.value = '';
}

// Eksekusi Aksi yang Tertunda Setelah Sukses Auth
function executeAction(actionType) {
    if (!actionType) return;

    if (actionType === 'edit_phone') {
        const phoneInp = document.getElementById('inputWaNumber');
        phoneInp.focus();
        phoneInp.select();
    } else if (actionType === 'edit_token') {
        const tokenInp = document.getElementById('fonnteTokenInput');
        tokenInp.focus();
        tokenInp.select();
    } else if (actionType === 'toggle_token_eye') {
        const inp = document.getElementById('fonnteTokenInput');
        const icon = document.getElementById('iconTokenEye');
        inp.type = 'text'; // Tampilkan setelah diautentikasi
        icon.className = 'bi bi-eye-fill text-primary';
    } else if (actionType === 'submit_save') {
        document.getElementById('saveAdminAuthPass').value = REQUIRED_AUTH_KEY;
        document.getElementById('formSaveGateway').submit();
    } else if (actionType === 'submit_test') {
        document.getElementById('testAdminAuthPass').value = REQUIRED_AUTH_KEY;
        document.getElementById('formTestSendWa').submit();
    }
}

// Toggle Eye di dalam Modal
function toggleModalPasswordEye() {
    const inp = document.getElementById('authPasswordInput');
    const icon = document.getElementById('modalEyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}

// Filter Pencarian Pesan Kendala
function filterMessagesTable() {
    const query = document.getElementById('tableFilterInput').value.toLowerCase();
    const rows = document.querySelectorAll('.message-row');

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
