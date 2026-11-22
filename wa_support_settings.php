<?php
/**
 * wa_support_settings.php
 * Halaman Manajemen Pesan Bantuan Masuk & Pengaturan WhatsApp Gateway (Superadmin Only)
 * Desain UI Premium inspired by 21st.dev & tasteskill.dev
 * Proteksi Keamanan Sandi Konfigurasi Gateway (Server-Side)
 */

$page_title = 'Pusat Pesan Bantuan & OpenWA Gateway';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/openwa_helper.php';

if (strtolower(trim($_SESSION['role'] ?? '')) !== 'superadmin') {
    $_SESSION['flash_message_error'] = "Akses ditolak. Halaman ini hanya untuk Super Admin.";
    header("Location: index.php");
    exit();
}

ensureOpenWATables($conn);
define('GATEWAY_AUTH_KEY', '312226');

// Endpoint AJAX Verifikasi / Kunci Ulang Sandi Konfigurasi Gateway (Server-Side)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gateway_auth_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $authAction = trim($_POST['gateway_auth_action']);
    if ($authAction === 'unlock') {
        $submittedPass = trim((string)($_POST['auth_password'] ?? ''));
        if (hash_equals(GATEWAY_AUTH_KEY, $submittedPass)) {
            $_SESSION['wa_gateway_config_unlocked'] = true;
            echo json_encode(['success' => true, 'message' => 'Akses konfigurasi berhasil dibuka!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sandi salah! Silakan coba lagi.']);
        }
        exit();
    }
    if ($authAction === 'lock') {
        unset($_SESSION['wa_gateway_config_unlocked']);
        echo json_encode(['success' => true, 'message' => 'Konfigurasi berhasil dikunci kembali.']);
        exit();
    }
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
    exit();
}

$is_gateway_unlocked = !empty($_SESSION['wa_gateway_config_unlocked']);

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

// Proses Simpan Pengaturan OpenWA Self-Hosted (Wajib Terbuka dengan Sandi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_openwa_settings'])) {
    if (!$is_gateway_unlocked) {
        $msg_error = "Akses terkunci! Silakan buka kunci konfigurasi terlebih dahulu menggunakan sandi keamanan.";
    } else {
        saveOpenWAConfig($conn, [
            'base_url'        => $_POST['openwa_base_url'] ?? 'http://127.0.0.1:2785',
            'api_key'         => $_POST['openwa_api_key'] ?? '',
            'dedup_days'      => $_POST['openwa_dedup_days'] ?? 7,
            'work_hours_only' => isset($_POST['openwa_work_hours_only']) ? 1 : 0,
        ]);
        $msg_success = "Konfigurasi OpenWA Self-Hosted & Anti-Ban berhasil disimpan!";
    }
}

// Proses Simpan Pengaturan Fonnte (Wajib Terbuka dengan Sandi) — merge agar konfigurasi OpenWA tidak tertimpa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!$is_gateway_unlocked) {
        $msg_error = "Akses terkunci! Silakan buka kunci konfigurasi terlebih dahulu menggunakan sandi keamanan.";
    } else {
        $wa_target = trim($_POST['wa_support_number'] ?? '085771593522');
        $fonnte_token = trim($_POST['fonnte_token'] ?? '');

        // 1. Simpan ke JSON file (merge dengan data lama agar openwa_* tetap aman)
        $configFile = __DIR__ . '/includes/wa_gateway_config.json';
        $configData = file_exists($configFile) ? json_decode(@file_get_contents($configFile), true) : [];
        if (!is_array($configData)) $configData = [];
        $configData['wa_support_number'] = $wa_target;
        $configData['fonnte_token']      = $fonnte_token;
        $configData['updated_at']        = date('Y-m-d H:i:s');
        @file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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

// Proses Tes Kirim WA Fonnte (Wajib Terbuka dengan Sandi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_send_wa'])) {
    if (!$is_gateway_unlocked) {
        $msg_error = "Akses terkunci! Silakan buka kunci konfigurasi terlebih dahulu menggunakan sandi keamanan.";
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

// Ambil Konfigurasi OpenWA & Daftar Sales untuk Mapping Nomor
$openwa_cfg = getOpenWAConfig($conn);
$resSalesList = $conn->query("SELECT id, nama_lengkap, role FROM sales WHERE deleted_at IS NULL ORDER BY nama_lengkap ASC");
$salesList = $resSalesList ? $resSalesList->fetch_all(MYSQLI_ASSOC) : [];

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

/* ==========================================================================
   Premium OpenWA QR Scanner Modal UI
   ========================================================================== */
.qr-modal-content {
    border: 0;
    border-radius: 26px !important;
    overflow: hidden;
    box-shadow: 0 28px 60px -15px rgba(15, 23, 42, 0.38), 0 0 0 1px rgba(255, 255, 255, 0.14);
}
.qr-modal-hero {
    background: radial-gradient(circle at top right, rgba(16, 185, 129, 0.28), transparent 55%),
                linear-gradient(135deg, #0b1324 0%, #0f172a 55%, #064e3b 100%);
    padding: 20px 24px;
    color: #ffffff;
    position: relative;
}
.qr-hero-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
    flex-shrink: 0;
}
.qr-stage-outer {
    position: relative;
    width: 280px;
    height: 280px;
    margin: 0 auto;
    padding: 14px;
    border-radius: 24px;
    background: #ffffff;
    box-shadow: 0 14px 36px -8px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(16, 185, 129, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
}
.qr-stage-outer.is-ready-scan {
    box-shadow: 0 16px 40px -8px rgba(16, 185, 129, 0.22), 0 0 0 1.5px rgba(16, 185, 129, 0.35);
}
/* Viewfinder Corner Brackets */
.qr-corner {
    position: absolute;
    width: 24px;
    height: 24px;
    border-color: #10b981;
    border-style: solid;
    pointer-events: none;
}
.qr-corner.tl { top: 8px; left: 8px; border-width: 3px 0 0 3px; border-radius: 10px 0 0 0; }
.qr-corner.tr { top: 8px; right: 8px; border-width: 3px 3px 0 0; border-radius: 0 10px 0 0; }
.qr-corner.bl { bottom: 8px; left: 8px; border-width: 0 0 3px 3px; border-radius: 0 0 0 10px; }
.qr-corner.br { bottom: 8px; right: 8px; border-width: 0 3px 3px 0; border-radius: 0 0 10px 0; }

.qr-img-fade {
    animation: qrFadeIn 0.28s ease-out;
}
@keyframes qrFadeIn {
    from { opacity: 0.25; transform: scale(0.97); }
    to   { opacity: 1; transform: scale(1); }
}
.qr-steps-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 14px 16px;
    text-align: left;
}
.qr-step-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 12px;
    color: #334155;
    line-height: 1.45;
}
.qr-step-item + .qr-step-item {
    margin-top: 9px;
}
.qr-step-num {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
    font-size: 11px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
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

    <!-- =======================================================================
         OPENWA SELF-HOSTED MULTI-SESSION & 8-LAYER ANTI-BAN COMMAND CENTER
         ======================================================================= -->
    <div class="premium-card p-4 mb-4" style="border-top: 4px solid #10b981;">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-2.5">
                <div class="card-title-icon" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff;">
                    <i class="bi bi-shield-fill-check"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h5 class="fw-bold text-dark mb-0">OpenWA Self-Hosted Gateway — 8 Lapis Anti-Ban</h5>
                        <span class="status-pill" id="openwaLiveBadge" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">
                            <span class="spinner-border spinner-border-sm" style="width:10px;height:10px;"></span> Mengecek server...
                        </span>
                    </div>
                    <span class="text-muted small">Multi-Nomor Sales (Rotasi Otomatis), Pre-Check WA, Typing Manusiawi, Spintax, Daily Cap &amp; Circuit Breaker.</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold" onclick="loadOpenwaDashboard()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh Live
                </button>
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="quickSetup4Sales()">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Setup Cepat 4 Nomor Sales
                </button>
                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="openAddSessionModal()">
                    <i class="bi bi-plus-circle-fill me-1"></i> Tambah Nomor WA
                </button>
            </div>
        </div>

        <div class="row g-4">
            <!-- Kiri: Koneksi Server OpenWA & Guardrail Global (Terkunci Sandi Keamanan) -->
            <div class="col-lg-4 col-12">
                <div class="p-3.5 rounded-4 h-100" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div class="d-flex justify-content-between align-items-center mb-2.5">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-1.5">
                            <i class="bi bi-hdd-network-fill text-success"></i> Koneksi Server OpenWA (Docker)
                        </h6>
                        <?php if ($is_gateway_unlocked): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2.5 py-0.5 fw-bold" style="font-size: 10.5px;" onclick="lockGateway()">
                                <i class="bi bi-lock-fill me-0.5"></i> Kunci
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-danger btn-sm rounded-pill px-2.5 py-0.5 fw-bold shadow-sm" style="font-size: 10.5px;" onclick="requireGatewayUnlock('Buka Konfigurasi OpenWA')">
                                <i class="bi bi-unlock-fill me-0.5"></i> Buka Kunci
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (!$is_gateway_unlocked): ?>
                        <div class="security-banner locked mb-3 py-2 px-2.5 d-flex align-items-center gap-2" style="cursor:pointer;" onclick="requireGatewayUnlock('Buka Konfigurasi OpenWA')">
                            <i class="bi bi-shield-lock-fill fs-5 text-danger flex-shrink-0"></i>
                            <div style="line-height: 1.3;">
                                <div class="fw-bold" style="font-size: 11.5px;">Konfigurasi Terkunci Sandi</div>
                                <div style="font-size: 10.5px; opacity: 0.9;">Klik <strong>Buka Kunci</strong> untuk melihat API Key &amp; mengubah pengaturan.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="wa_support_settings.php" id="formSaveOpenwa" onsubmit="return guardLockedSubmit(event, 'Simpan Konfigurasi OpenWA')">
                        <input type="hidden" name="save_openwa_settings" value="1">
                        <div class="mb-2.5">
                            <label class="form-label-custom">
                                <span>URL API OpenWA</span>
                                <?php if (!$is_gateway_unlocked): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-normal" style="font-size: 9.5px;"><i class="bi bi-lock-fill"></i> Terkunci</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" name="openwa_base_url" class="form-control form-control-sm font-monospace <?= !$is_gateway_unlocked ? 'bg-light text-muted' : '' ?>"
                                   value="<?= htmlspecialchars($openwa_cfg['base_url']) ?>"
                                   placeholder="http://127.0.0.1:2785"
                                   <?= !$is_gateway_unlocked ? 'readonly onclick="requireGatewayUnlock(\'Ubah URL API OpenWA\')" style="cursor:pointer;"' : 'required' ?>>
                            <small class="text-muted" style="font-size:10.5px;">Default lokal Docker: <code>http://127.0.0.1:2785</code></small>
                        </div>
                        <div class="mb-2.5">
                            <label class="form-label-custom">
                                <span>API Key OpenWA (X-API-Key)</span>
                                <?php if (!$is_gateway_unlocked): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-normal" style="font-size: 9.5px;"><i class="bi bi-lock-fill"></i> Terkunci</span>
                                <?php endif; ?>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="password" name="openwa_api_key" id="openwaApiKeyInput"
                                       class="form-control font-monospace <?= !$is_gateway_unlocked ? 'bg-light text-muted' : '' ?>"
                                       value="<?= $is_gateway_unlocked ? htmlspecialchars($openwa_cfg['api_key']) : (!empty($openwa_cfg['api_key']) ? '••••••••••••••••••••••••' : '') ?>"
                                       placeholder="owa_k1_..."
                                       <?= !$is_gateway_unlocked ? 'readonly onclick="requireGatewayUnlock(\'Lihat / Ubah API Key OpenWA\')" style="cursor:pointer;"' : '' ?>>
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleOpenwaKeyEye()" title="Lihat/Sembunyikan">
                                    <i class="bi bi-<?= $is_gateway_unlocked ? 'eye-slash-fill' : 'lock-fill text-danger' ?>" id="iconOpenwaKeyEye"></i>
                                </button>
                            </div>
                            <small class="text-muted" style="font-size:10.5px;">Otomatis terisi jika menjalankan <code>install_openwa.sh</code></small>
                        </div>
                        <div class="row g-2 mb-2.5">
                            <div class="col-6">
                                <label class="form-label-custom"><span>Anti-Spam Ganda</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="openwa_dedup_days" class="form-control fw-bold <?= !$is_gateway_unlocked ? 'bg-light text-muted' : '' ?>" min="0" max="90"
                                           value="<?= (int)$openwa_cfg['dedup_days'] ?>"
                                           <?= !$is_gateway_unlocked ? 'readonly onclick="requireGatewayUnlock(\'Ubah Anti-Spam Ganda\')" style="cursor:pointer;"' : '' ?>>
                                    <span class="input-group-text">Hari</span>
                                </div>
                            </div>
                            <div class="col-6 d-flex align-items-end">
                                <div class="form-check mb-1" <?= !$is_gateway_unlocked ? 'onclick="requireGatewayUnlock(\'Ubah Jam Kerja\'); return false;" style="cursor:pointer;"' : '' ?>>
                                    <input class="form-check-input" type="checkbox" name="openwa_work_hours_only" id="chkWorkHours" value="1"
                                           <?= !empty($openwa_cfg['work_hours_only']) ? 'checked' : '' ?>
                                           <?= !$is_gateway_unlocked ? 'onclick="return false;"' : '' ?>>
                                    <label class="form-check-label small fw-bold text-dark" for="chkWorkHours" style="font-size:11px;">Jam Kerja 08–20</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn <?= $is_gateway_unlocked ? 'btn-success' : 'btn-secondary' ?> btn-sm w-100 rounded-3 fw-bold py-2 shadow-sm">
                            <i class="bi bi-<?= $is_gateway_unlocked ? 'save2-fill' : 'lock-fill' ?> me-1"></i>
                            <?= $is_gateway_unlocked ? 'Simpan Konfigurasi OpenWA' : 'Terkunci — Masukkan Sandi untuk Ubah' ?>
                        </button>
                    </form>

                    <!-- Panduan Ringkas Install Server -->
                    <div class="mt-3 p-2.5 rounded-3 bg-white border" style="font-size: 11px;">
                        <div class="fw-bold text-dark mb-1"><i class="bi bi-terminal-fill text-primary me-1"></i> Pasang OpenWA di Server (1 Perintah):</div>
                        <?php if ($is_gateway_unlocked): ?>
                            <code class="d-block p-1.5 rounded bg-dark text-light user-select-all" style="font-size: 10px; word-break: break-all;">sudo bash /www/wwwroot/sales.id-giti.com/openwa-docker/install_openwa.sh</code>
                        <?php else: ?>
                            <div class="p-1.5 rounded bg-light text-muted text-center border" style="font-size: 10.5px; cursor:pointer;" onclick="requireGatewayUnlock('Lihat Perintah Server OpenWA')">
                                <i class="bi bi-lock-fill text-danger me-1"></i> Perintah server disembunyikan (Klik untuk buka sandi)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Kanan: Statistik Anti-Ban Hari Ini + Daftar Multi-Session Sales -->
            <div class="col-lg-8 col-12">
                <!-- Strip Statistik Anti-Ban Hari Ini -->
                <div class="row g-2.5 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="kpi-card py-2.5 px-3">
                            <div class="text-muted fw-semibold" style="font-size:11px;">Terkirim Hari Ini</div>
                            <div class="fw-bold text-success fs-5" id="statOpenwaSent">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card py-2.5 px-3">
                            <div class="text-muted fw-semibold" style="font-size:11px;">Skip Bukan WA</div>
                            <div class="fw-bold text-warning fs-5" id="statOpenwaNotWa">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card py-2.5 px-3">
                            <div class="text-muted fw-semibold" style="font-size:11px;">Skip Anti-Duplikat</div>
                            <div class="fw-bold text-primary fs-5" id="statOpenwaRecent">0</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="kpi-card py-2.5 px-3">
                            <div class="text-muted fw-semibold" style="font-size:11px;">Gagal / Breaker</div>
                            <div class="fw-bold text-danger fs-5" id="statOpenwaFailed">0</div>
                        </div>
                    </div>
                </div>

                <!-- Alert Offline jika Docker belum menyala -->
                <div id="openwaOfflineNotice" class="alert alert-warning rounded-4 small py-2.5 px-3 mb-3 d-none">
                    <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Server OpenWA Belum Terhubung</div>
                    <div id="openwaOfflineDetail">Pastikan container Docker <code>openwa-api</code> sudah dijalankan di server VPS Anda.</div>
                </div>

                <!-- Tabel Multi-Session Nomor WA Sales -->
                <div class="table-responsive rounded-3 border">
                    <table class="table modern-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nomor &amp; Sales PIC</th>
                                <th>Status Koneksi</th>
                                <th style="min-width:170px;">Kuota Aman Harian</th>
                                <th class="text-end" style="min-width:190px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="openwaSessionsTbody">
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted small">
                                    <span class="spinner-border spinner-border-sm me-1"></span> Memuat daftar nomor WhatsApp OpenWA...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- ===================================================================
             CARD KONFIGURASI WA GATEWAY (FONNTE) - TERPROTEKSI SANDI KEAMANAN
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

                <!-- Security Access Banner (Terkunci dengan Sandi Keamanan) -->
                <?php if ($is_gateway_unlocked): ?>
                    <div id="securityBanner" class="security-banner unlocked mb-3 d-flex align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check-fill fs-5 text-success" id="securityBannerIcon"></i>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 12px;" id="securityBannerTitle">Akses Terbuka</div>
                                <div class="text-muted" style="font-size: 11px;" id="securityBannerSub">Sandi terverifikasi. Klik Kunci jika sudah selesai mengubah.</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-2.5 py-1 fw-bold" style="font-size: 11px;" onclick="lockGateway()">
                            <i class="bi bi-lock-fill me-1"></i> Kunci
                        </button>
                    </div>
                <?php else: ?>
                    <div id="securityBanner" class="security-banner locked mb-3 d-flex align-items-center justify-content-between gap-2" style="cursor:pointer;" onclick="requireGatewayUnlock('Buka Konfigurasi WA Gateway')">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock-fill fs-5 text-danger" id="securityBannerIcon"></i>
                            <div>
                                <div class="fw-bold" style="font-size: 12px;" id="securityBannerTitle">Konfigurasi Terkunci</div>
                                <div style="font-size: 11px; opacity: 0.9;" id="securityBannerSub">Masukkan sandi keamanan untuk melihat atau mengubah token.</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-danger btn-sm rounded-pill px-2.5 py-1 fw-bold shadow-sm flex-shrink-0" style="font-size: 11px;" onclick="event.stopPropagation(); requireGatewayUnlock('Buka Konfigurasi WA Gateway');">
                            <i class="bi bi-unlock-fill me-1"></i> Buka
                        </button>
                    </div>
                <?php endif; ?>

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
                            <strong>Token Fonnte belum tersimpan.</strong> Silakan tempelkan token dari Fonnte agar pesan diteruskan langsung ke HP.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Konfigurasi (Terproteksi Sandi) -->
                <form method="POST" action="wa_support_settings.php" id="formSaveGateway" onsubmit="return guardLockedSubmit(event, 'Simpan Konfigurasi WA Gateway')">
                    <input type="hidden" name="save_settings" value="1">

                    <!-- Poin 1: Ubah Nomor WA -->
                    <div class="mb-3">
                        <label class="form-label-custom">
                            <span>1. Nomor WA Tujuan</span>
                            <?php if ($is_gateway_unlocked): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-normal" id="labelLockPhone" style="font-size: 10px;">
                                    <i class="bi bi-unlock-fill me-0.5"></i> Terbuka
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-normal" id="labelLockPhone" style="font-size: 10px;">
                                    <i class="bi bi-lock-fill me-0.5"></i> Terkunci
                                </span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group custom-input-group <?= !$is_gateway_unlocked ? 'is-locked' : '' ?>" id="groupPhone">
                            <span class="input-group-text"><i class="bi bi-telephone text-success"></i></span>
                            <input type="text" name="wa_support_number" id="inputWaNumber" class="form-control" 
                                   value="<?= htmlspecialchars($current_wa) ?>" required 
                                   placeholder="085771593522"
                                   <?= !$is_gateway_unlocked ? 'readonly onclick="requireGatewayUnlock(\'Ubah Nomor WA Tujuan\')"' : '' ?>>
                            <span class="input-group-text bg-white" <?= !$is_gateway_unlocked ? 'onclick="requireGatewayUnlock(\'Ubah Nomor WA Tujuan\')"' : '' ?>>
                                <i class="bi bi-<?= $is_gateway_unlocked ? 'pencil-fill text-primary' : 'lock-fill text-danger' ?>" style="font-size: 12px;"></i>
                            </span>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">Semua laporan kendala dari bot web akan diteruskan ke nomor ini.</small>
                    </div>

                    <!-- Poin 2: Ubah Token API Fonnte & Klik Icon Mata -->
                    <div class="mb-3">
                        <label class="form-label-custom">
                            <span>2. Token API Fonnte</span>
                            <?php if ($is_gateway_unlocked): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-normal" id="labelLockToken" style="font-size: 10px;">
                                    <i class="bi bi-unlock-fill me-0.5"></i> Terbuka
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-normal" id="labelLockToken" style="font-size: 10px;">
                                    <i class="bi bi-lock-fill me-0.5"></i> Terkunci
                                </span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group custom-input-group <?= !$is_gateway_unlocked ? 'is-locked' : '' ?>" id="groupToken">
                            <span class="input-group-text"><i class="bi bi-key text-warning"></i></span>
                            <input type="password" name="fonnte_token" id="fonnteTokenInput" class="form-control font-monospace" 
                                   value="<?= $is_gateway_unlocked ? htmlspecialchars($current_token) : (!empty($current_token) ? '••••••••••••••••••••' : '') ?>"
                                   placeholder="Tempel token di sini (contoh: a1b2c3d...)"
                                   <?= !$is_gateway_unlocked ? 'readonly onclick="requireGatewayUnlock(\'Lihat / Ubah Token Fonnte\')"' : '' ?>>
                            <button class="btn btn-outline-secondary" type="button" id="btnToggleTokenEye" 
                                    onclick="handleTokenEyeClick()" title="Lihat / Sembunyikan Token">
                                <i class="bi bi-<?= $is_gateway_unlocked ? 'eye-slash-fill text-muted' : 'lock-fill text-danger' ?>" id="iconTokenEye"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 11px;">
                            Buka <a href="https://fonnte.com" target="_blank" class="fw-bold text-primary text-decoration-none">fonnte.com</a> > klik tombol <strong>Token</strong> > salin kodenya &amp; tempel di atas.
                        </small>
                    </div>

                    <!-- Poin 3: Simpan Konfigurasi -->
                    <button type="submit" class="btn <?= $is_gateway_unlocked ? 'btn-save-glow' : 'btn-secondary' ?> w-100 d-inline-flex align-items-center justify-content-center gap-2 mb-3 rounded-3 py-2.5 fw-bold" id="btnSubmitSave">
                        <i class="bi bi-<?= $is_gateway_unlocked ? 'check2-circle' : 'lock-fill' ?> text-white"></i>
                        <span><?= $is_gateway_unlocked ? 'Simpan Konfigurasi' : 'Terkunci — Buka Sandi untuk Ubah' ?></span>
                    </button>
                </form>

                <!-- Poin 4: Tes Kirim Pesan WA -->
                <?php if (!empty($current_token)): ?>
                    <hr class="my-3" style="border-color: #e2e8f0;">
                    <form method="POST" action="wa_support_settings.php" id="formTestSendWa" onsubmit="return guardLockedSubmit(event, 'Tes Kirim Pesan WA Fonnte')">
                        <input type="hidden" name="test_send_wa" value="1">
                        <input type="hidden" name="test_target" value="<?= htmlspecialchars($current_wa) ?>">
                        <?php if ($is_gateway_unlocked): ?>
                            <input type="hidden" name="test_token" value="<?= htmlspecialchars($current_token) ?>">
                        <?php endif; ?>

                        <button type="submit" class="btn btn-test-wa w-100 d-inline-flex align-items-center justify-content-center gap-2" id="btnSubmitTest">
                            <i class="bi bi-<?= $is_gateway_unlocked ? 'whatsapp' : 'lock-fill' ?>"></i>
                            <span><?= $is_gateway_unlocked ? 'Tes Kirim Pesan WA Sekarang' : 'Terkunci — Buka Sandi untuk Tes WA' ?></span>
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
     MODAL OTORISASI KEAMANAN KONFIGURASI GATEWAY - 21st.dev Style
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
 * Proteksi Sandi Konfigurasi OpenWA & WA Gateway (Terverifikasi Server-Side PHP)
 */
const isGatewayUnlocked = <?= $is_gateway_unlocked ? 'true' : 'false' ?>;

function requireGatewayUnlock(actionLabel) {
    const modalEl = document.getElementById('authSecurityModal');
    if (!modalEl) return;
    const actionText = document.getElementById('authActionText');
    const errBox     = document.getElementById('authErrorMessage');
    const passInput  = document.getElementById('authPasswordInput');

    if (actionText) actionText.textContent = actionLabel || 'Buka Konfigurasi Gateway';
    if (errBox) errBox.classList.add('d-none');
    if (passInput) {
        passInput.value = '';
        passInput.type = 'password';
    }
    const eyeIcon = document.getElementById('modalEyeIcon');
    if (eyeIcon) eyeIcon.className = 'bi bi-eye-fill';

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
    setTimeout(() => passInput && passInput.focus(), 250);
}

function guardLockedSubmit(event, actionLabel) {
    if (!isGatewayUnlocked) {
        if (event) event.preventDefault();
        requireGatewayUnlock(actionLabel);
        return false;
    }
    return true;
}

function toggleModalPasswordEye() {
    const inp = document.getElementById('authPasswordInput');
    const icon = document.getElementById('modalEyeIcon');
    if (!inp || !icon) return;
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash-fill text-primary';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}

async function handleAuthSubmit(event) {
    event.preventDefault();
    const passInput = document.getElementById('authPasswordInput');
    const errBox    = document.getElementById('authErrorMessage');
    const card      = document.getElementById('modalCardContainer');
    const btn       = document.getElementById('btnVerifySubmit');
    const password  = passInput ? passInput.value.trim() : '';

    if (!password) return;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memeriksa...';
    }

    try {
        const fd = new URLSearchParams({
            gateway_auth_action: 'unlock',
            auth_password: password
        });
        const resp = await fetch('wa_support_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: fd.toString()
        });
        const data = await resp.json();

        if (data.success) {
            window.location.reload();
            return;
        }

        if (errBox) {
            errBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (data.message || 'Sandi salah! Silakan coba lagi.');
            errBox.classList.remove('d-none');
        }
        if (card) {
            card.classList.remove('shake-it');
            void card.offsetWidth;
            card.classList.add('shake-it');
        }
        if (passInput) {
            passInput.value = '';
            passInput.focus();
        }
    } catch (e) {
        if (errBox) {
            errBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Terjadi gangguan jaringan saat verifikasi.';
            errBox.classList.remove('d-none');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-unlock-fill me-1"></i> Buka Akses';
        }
    }
}

async function lockGateway() {
    try {
        const fd = new URLSearchParams({ gateway_auth_action: 'lock' });
        await fetch('wa_support_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: fd.toString()
        });
    } finally {
        window.location.reload();
    }
}

// Toggle Lihat/Sembunyikan Token API Fonnte
function handleTokenEyeClick() {
    if (!isGatewayUnlocked) {
        requireGatewayUnlock('Lihat Token API Fonnte');
        return;
    }
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

<!-- Modal 1: Live Scan QR Code OpenWA (Premium Zero-Flicker Scanner UI) -->
<div class="modal fade modal-glass" id="modalOpenwaQr" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 470px;">
        <div class="modal-content qr-modal-content">
            <!-- Hero Header -->
            <div class="qr-modal-hero d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-3 overflow-hidden">
                    <div class="qr-hero-icon">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                    <div class="overflow-hidden">
                        <h6 class="fw-bold mb-0.5 text-white" style="font-size: 15.5px;">Tautkan WhatsApp Sales</h6>
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <span class="badge rounded-pill px-2.5 py-0.5 fw-semibold" style="background: rgba(255,255,255,0.12); color: #e2e8f0; font-size: 11px;" id="qrModalSessionLabel">
                                Memuat sesi...
                            </span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white flex-shrink-0" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4 text-center bg-white">
                <!-- Live Status Pill -->
                <div class="mb-3">
                    <span class="status-pill" id="qrStatusBadge" style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe;">
                        <span class="spinner-border spinner-border-sm" style="width:10px;height:10px;"></span> Menyiapkan sesi...
                    </span>
                </div>

                <!-- Crisp White Viewfinder QR Stage -->
                <div class="qr-stage-outer mb-3" id="qrStageOuter">
                    <span class="qr-corner tl"></span>
                    <span class="qr-corner tr"></span>
                    <span class="qr-corner bl"></span>
                    <span class="qr-corner br"></span>
                    <div id="qrImageWrapper" class="d-flex align-items-center justify-content-center w-100 h-100">
                        <div class="text-muted small">
                            <div class="spinner-border text-success mb-2" role="status"></div>
                            <div class="fw-semibold">Meminta QR Code...</div>
                        </div>
                    </div>
                </div>

                <!-- Info Masa Aktif QR (Jelas & Tidak Bikin Panik) -->
                <div class="d-inline-flex align-items-center gap-1.5 px-3 py-1 rounded-pill mb-3" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; font-size: 11px; font-weight: 600;">
                    <i class="bi bi-clock-history"></i>
                    <span id="qrValidityText">QR aktif ~20–40 detik &amp; otomatis diperbarui jika kedaluwarsa</span>
                </div>

                <!-- Panduan 3 Langkah Visual -->
                <div class="qr-steps-box">
                    <div class="qr-step-item">
                        <span class="qr-step-num">1</span>
                        <div>Buka aplikasi <strong>WhatsApp</strong> di HP sales yang sesuai nomor di atas.</div>
                    </div>
                    <div class="qr-step-item">
                        <span class="qr-step-num">2</span>
                        <div>Ketuk menu <strong>&#8942;</strong> (Android) atau <strong>Pengaturan</strong> (iPhone) &rarr; pilih <strong>Perangkat Tertaut</strong>.</div>
                    </div>
                    <div class="qr-step-item">
                        <span class="qr-step-num">3</span>
                        <div>Ketuk <strong>Tautkan Perangkat</strong> lalu arahkan kamera ke QR Code (tanpa perlu klik apa pun, otomatis terhubung).</div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-top py-2.5 px-4 bg-light justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-1.5 text-muted" style="font-size: 11.5px;">
                    <span class="pulse-dot emerald" style="width: 7px; height: 7px;"></span>
                    <span id="qrFooterLiveText">Mendeteksi scan otomatis...</span>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold" onclick="forceRefreshOpenwaQr()" title="Muat ulang QR Code sekarang">
                        <i class="bi bi-arrow-clockwise me-0.5"></i> Refresh QR
                    </button>
                    <button type="button" class="btn btn-dark btn-sm rounded-pill px-3.5 fw-bold" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Tambah Sesi Nomor WA Baru -->
<div class="modal fade" id="modalAddOpenwaSession" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0F172A, #1E293B);">
                <h6 class="modal-title fw-bold mb-0"><i class="bi bi-plus-circle-fill text-success me-1.5"></i> Tambah Nomor WA Sales (OpenWA)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddOpenwaSession" onsubmit="submitAddOpenwaSession(event)">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama / Label Sesi</label>
                        <input type="text" id="addSessLabel" class="form-control" placeholder="Contoh: Sales Edi (0877-0019-1818)" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nomor WhatsApp Sales</label>
                        <input type="text" id="addSessPhone" class="form-control" placeholder="087700191818 / +62 877-0019-1818" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Mapping ke Akun Sales CRM (Opsional)</label>
                        <select id="addSessSalesId" class="form-select">
                            <option value="0">-- Pool Rotasi Bersama (Semua Sales) --</option>
                            <?php foreach ($salesList as $sl): ?>
                                <option value="<?= (int)$sl['id'] ?>"><?= htmlspecialchars($sl['nama_lengkap']) ?> (<?= htmlspecialchars($sl['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="form-label small fw-bold">Mode Keamanan Anti-Ban</label>
                            <select id="addSessMode" class="form-select" onchange="syncLimitByMode('addSessMode','addSessLimit')">
                                <option value="warmup" selected>🛡️ Warmup (20 pesan/hari — Paling Aman)</option>
                                <option value="normal">⚖️ Normal (45 pesan/hari — Nomor Lama)</option>
                                <option value="boost">⚡ Boost (75 pesan/hari — Nomor Terlatih)</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold">Limit Harian</label>
                            <input type="number" id="addSessLimit" class="form-control fw-bold" min="5" max="150" value="20" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-light rounded-pill px-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" id="btnSubmitAddSess">
                        <i class="bi bi-check2-circle me-1"></i> Buat Sesi &amp; Siapkan QR
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 3: Edit Konfigurasi Anti-Ban Sesi -->
<div class="modal fade" id="modalEditOpenwaSession" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #0F172A, #1E293B);">
                <h6 class="modal-title fw-bold mb-0"><i class="bi bi-sliders text-info me-1.5"></i> Pengaturan Kuota &amp; Anti-Ban Sesi</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditOpenwaSession" onsubmit="submitEditOpenwaSession(event)">
                <input type="hidden" id="editSessId">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Label Sesi</label>
                        <input type="text" id="editSessLabel" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nomor WhatsApp</label>
                        <input type="text" id="editSessPhone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Akun Sales PIC</label>
                        <select id="editSessSalesId" class="form-select">
                            <option value="0">-- Pool Rotasi Bersama (Semua Sales) --</option>
                            <?php foreach ($salesList as $sl): ?>
                                <option value="<?= (int)$sl['id'] ?>"><?= htmlspecialchars($sl['nama_lengkap']) ?> (<?= htmlspecialchars($sl['role']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small fw-bold">Mode Keamanan</label>
                            <select id="editSessMode" class="form-select" onchange="syncLimitByMode('editSessMode','editSessLimit')">
                                <option value="warmup">🛡️ Warmup (20/hari)</option>
                                <option value="normal">⚖️ Normal (45/hari)</option>
                                <option value="boost">⚡ Boost (75/hari)</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold">Limit Harian</label>
                            <input type="number" id="editSessLimit" class="form-control fw-bold" min="5" max="150" required>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="editSessActive" checked>
                        <label class="form-check-label small fw-bold" for="editSessActive">Aktifkan nomor ini dalam rotasi broadcast</label>
                    </div>
                </div>
                <div class="modal-footer border-top px-4 py-3">
                    <button type="button" class="btn btn-light rounded-pill px-3 fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let openwaSessionsCache = [];
let openwaQrPollTimer = null;
let openwaQrActiveSessionId = '';

function toggleOpenwaKeyEye() {
    if (!isGatewayUnlocked) {
        requireGatewayUnlock('Lihat API Key OpenWA');
        return;
    }
    const inp = document.getElementById('openwaApiKeyInput');
    const ic  = document.getElementById('iconOpenwaKeyEye');
    if (inp.type === 'password') {
        inp.type = 'text';
        ic.className = 'bi bi-eye-fill text-primary';
    } else {
        inp.type = 'password';
        ic.className = 'bi bi-eye-slash-fill';
    }
}

function syncLimitByMode(selectId, limitId) {
    const mode = document.getElementById(selectId)?.value;
    const limEl = document.getElementById(limitId);
    if (!limEl) return;
    if (mode === 'warmup') limEl.value = 20;
    else if (mode === 'normal') limEl.value = 45;
    else if (mode === 'boost') limEl.value = 75;
}

function escapeOpenwaHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

async function loadOpenwaDashboard() {
    const badge = document.getElementById('openwaLiveBadge');
    const notice = document.getElementById('openwaOfflineNotice');
    const noticeDetail = document.getElementById('openwaOfflineDetail');
    const tbody = document.getElementById('openwaSessionsTbody');

    try {
        const [hResp, lResp] = await Promise.all([
            fetch('ajax_wa_broadcast.php?action=openwa_health').then(r => r.json()),
            fetch('ajax_wa_broadcast.php?action=openwa_list_sessions').then(r => r.json())
        ]);

        if (hResp.online) {
            const verTxt = hResp.version ? ('v' + hResp.version) : 'Key Belum Valid';
            badge.style.background = hResp.auth_valid ? '#ecfdf5' : '#fffbeb';
            badge.style.color      = hResp.auth_valid ? '#065f46' : '#92400e';
            badge.style.border     = hResp.auth_valid ? '1px solid #a7f3d0' : '1px solid #fde68a';
            badge.innerHTML        = `<span class="pulse-dot ${hResp.auth_valid ? 'emerald' : 'amber'}"></span> Online (${escapeOpenwaHtml(verTxt)}) • ${hResp.ready_sessions}/${hResp.total_sessions} Ready`;
            notice.classList.add('d-none');
        } else {
            badge.style.background = '#fff1f2';
            badge.style.color      = '#9f1239';
            badge.style.border     = '1px solid #fecdd3';
            badge.innerHTML        = '<i class="bi bi-x-circle-fill"></i> Offline';
            notice.classList.remove('d-none');
            noticeDetail.textContent = hResp.error || 'Server OpenWA belum berjalan di port 2785.';
        }

        if (lResp.today_stats) {
            document.getElementById('statOpenwaSent').textContent   = lResp.today_stats.sent || 0;
            document.getElementById('statOpenwaNotWa').textContent  = lResp.today_stats.skipped_not_wa || 0;
            document.getElementById('statOpenwaRecent').textContent = lResp.today_stats.skipped_recent || 0;
            document.getElementById('statOpenwaFailed').textContent = lResp.today_stats.failed || 0;
        }

        openwaSessionsCache = lResp.sessions || [];
        renderOpenwaSessionsTable(openwaSessionsCache);
    } catch (e) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3 small">Gagal memuat status OpenWA.</td></tr>';
    }
}

function renderOpenwaSessionsTable(sessions) {
    const tbody = document.getElementById('openwaSessionsTbody');
    if (!tbody) return;

    if (!sessions.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-4 text-muted small">
                    <i class="bi bi-phone-vibrate fs-3 d-block mb-1 text-secondary"></i>
                    Belum ada sesi nomor WA di OpenWA. Klik <strong>"Setup Cepat 4 Nomor Sales"</strong> di kanan atas!
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = sessions.map(s => {
        const pct = Math.min(100, Math.round((s.sent_today / Math.max(1, s.daily_limit)) * 100));
        const barColor = pct >= 90 ? 'bg-danger' : (pct >= 65 ? 'bg-warning' : 'bg-success');

        let statusHtml = '';
        if (s.status === 'ready') {
            statusHtml = `<span class="status-pill" style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;"><span class="pulse-dot emerald"></span> READY</span>`;
        } else if (s.status === 'qr_ready') {
            statusHtml = `<span class="status-pill" style="background:#fffbeb;color:#92400e;border:1px solid #fde68a;"><i class="bi bi-qr-code"></i> SIAP SCAN QR</span>`;
        } else if (s.status === 'initializing' || s.status === 'authenticating') {
            statusHtml = `<span class="status-pill" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;"><span class="spinner-border spinner-border-sm" style="width:9px;height:9px;"></span> ${escapeOpenwaHtml(s.status)}</span>`;
        } else {
            statusHtml = `<span class="status-pill" style="background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;">${escapeOpenwaHtml(s.status.toUpperCase())}</span>`;
        }

        let extraWarn = '';
        if (s.has_restriction) {
            extraWarn += `<div class="text-danger fw-bold mt-1" style="font-size:10.5px;"><i class="bi bi-shield-exclamation"></i> Pembatasan WA terdeteksi!</div>`;
        }
        if (s.is_paused) {
            const mins = Math.ceil((s.paused_remaining_sec || 60) / 60);
            extraWarn += `<div class="text-warning-emphasis fw-bold mt-1" style="font-size:10.5px;">
                <i class="bi bi-pause-circle-fill"></i> Circuit Breaker (${mins} mnt)
                <a href="javascript:void(0)" class="text-primary ms-1" onclick="doOpenwaSessionAction('${escapeOpenwaHtml(s.session_id)}','reset_breaker')">Reset</a>
            </div>`;
        }
        if (!s.is_active) {
            extraWarn += `<div class="text-muted mt-1" style="font-size:10.5px;"><i class="bi bi-slash-circle"></i> Nonaktif di rotasi</div>`;
        }

        const modeBadge = s.mode === 'warmup'
            ? '<span class="badge bg-info-subtle text-info-emphasis border">Warmup</span>'
            : (s.mode === 'normal' ? '<span class="badge bg-success-subtle text-success border">Normal</span>' : '<span class="badge bg-warning-subtle text-warning-emphasis border">Boost</span>');

        const scanOrTestBtn = s.status === 'ready'
            ? `<button type="button" class="btn btn-outline-success btn-sm rounded-pill px-2.5 fw-bold" style="font-size:11px;" onclick="testOpenwaSend('${escapeOpenwaHtml(s.session_id)}','${escapeOpenwaHtml(s.label)}')" title="Tes Kirim 1 Pesan"><i class="bi bi-send-check"></i> Tes</button>`
            : `<button type="button" class="btn btn-success btn-sm rounded-pill px-2.5 fw-bold" style="font-size:11px;" onclick="openOpenwaQrModal('${escapeOpenwaHtml(s.session_id)}','${escapeOpenwaHtml(s.label)}')"><i class="bi bi-qr-code-scan"></i> Scan QR</button>`;

        return `
            <tr>
                <td>
                    <div class="fw-bold text-dark" style="font-size:12.5px;">${escapeOpenwaHtml(s.label)}</div>
                    <div class="text-muted" style="font-size:11px;">
                        <i class="bi bi-whatsapp text-success"></i> ${escapeOpenwaHtml(s.phone_number || 'Belum terdeteksi')}
                        &bull; <i class="bi bi-person-badge text-primary"></i> ${escapeOpenwaHtml(s.sales_name)}
                    </div>
                </td>
                <td>
                    ${statusHtml}
                    ${extraWarn}
                </td>
                <td>
                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:11px;">
                        <span>${modeBadge}</span>
                        <span class="fw-bold text-dark">${s.sent_today} / ${s.daily_limit}</span>
                    </div>
                    <div class="progress" style="height:6px; border-radius:10px;">
                        <div class="progress-bar ${barColor}" style="width:${pct}%"></div>
                    </div>
                </td>
                <td class="text-end text-nowrap">
                    <div class="d-inline-flex gap-1 align-items-center">
                        ${scanOrTestBtn}
                        <button type="button" class="btn btn-light border btn-sm rounded-circle" style="width:28px;height:28px;padding:0;" onclick="openEditSessionModal('${escapeOpenwaHtml(s.session_id)}')" title="Atur Kuota & Sales"><i class="bi bi-sliders"></i></button>
                        ${s.status === 'ready'
                            ? `<button type="button" class="btn btn-light border btn-sm rounded-circle text-warning" style="width:28px;height:28px;padding:0;" onclick="doOpenwaSessionAction('${escapeOpenwaHtml(s.session_id)}','stop')" title="Hentikan Sesi"><i class="bi bi-stop-fill"></i></button>`
                            : `<button type="button" class="btn btn-light border btn-sm rounded-circle text-primary" style="width:28px;height:28px;padding:0;" onclick="doOpenwaSessionAction('${escapeOpenwaHtml(s.session_id)}','start')" title="Jalankan Sesi"><i class="bi bi-play-fill"></i></button>`
                        }
                        <button type="button" class="btn btn-light border btn-sm rounded-circle text-danger" style="width:28px;height:28px;padding:0;" onclick="confirmDeleteSession('${escapeOpenwaHtml(s.session_id)}','${escapeOpenwaHtml(s.label)}')" title="Hapus Sesi"><i class="bi bi-trash-fill"></i></button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

async function quickSetup4Sales() {
    const res = await Swal.fire({
        title: 'Setup Cepat 4 Nomor Sales Loewix?',
        html: 'Akan membuat 4 sesi OpenWA dalam <b>Mode Warmup Aman (20/hari)</b>:<br><small class="text-muted">0877-0019-1818 &bull; 0819-9699-1818 &bull; 0819-5899-1818 &bull; 0819-5299-1818</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Buat 4 Sesi',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10b981'
    });
    if (!res.isConfirmed) return;

    Swal.fire({ title: 'Membuat 4 sesi di OpenWA...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const fd = new URLSearchParams({ action: 'openwa_quick_setup_sales' });
    const data = await fetch('ajax_wa_broadcast.php', { method: 'POST', body: fd }).then(r => r.json());
    Swal.fire({ icon: data.success ? 'success' : 'error', title: data.success ? 'Berhasil!' : 'Gagal', text: data.message });
    loadOpenwaDashboard();
}

function openAddSessionModal() {
    document.getElementById('formAddOpenwaSession').reset();
    document.getElementById('addSessLimit').value = 20;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAddOpenwaSession')).show();
}

async function submitAddOpenwaSession(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitAddSess');
    btn.disabled = true;
    const fd = new URLSearchParams({
        action: 'openwa_create_session',
        label: document.getElementById('addSessLabel').value,
        phone_number: document.getElementById('addSessPhone').value,
        sales_id: document.getElementById('addSessSalesId').value,
        mode: document.getElementById('addSessMode').value,
        daily_limit: document.getElementById('addSessLimit').value
    });
    try {
        const data = await fetch('ajax_wa_broadcast.php', { method: 'POST', body: fd }).then(r => r.json());
        bootstrap.Modal.getInstance(document.getElementById('modalAddOpenwaSession'))?.hide();
        await loadOpenwaDashboard();
        if (data.success && data.session_id) {
            openOpenwaQrModal(data.session_id, document.getElementById('addSessLabel').value);
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
        }
    } finally {
        btn.disabled = false;
    }
}

function openEditSessionModal(sessionId) {
    const s = openwaSessionsCache.find(x => x.session_id === sessionId);
    if (!s) return;
    document.getElementById('editSessId').value = s.session_id;
    document.getElementById('editSessLabel').value = s.label;
    document.getElementById('editSessPhone').value = s.phone_number || '';
    document.getElementById('editSessSalesId').value = s.sales_id || 0;
    document.getElementById('editSessMode').value = s.mode || 'warmup';
    document.getElementById('editSessLimit').value = s.daily_limit || 20;
    document.getElementById('editSessActive').checked = !!s.is_active;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditOpenwaSession')).show();
}

async function submitEditOpenwaSession(e) {
    e.preventDefault();
    const fd = new URLSearchParams({
        action: 'openwa_session_action',
        op: 'update_config',
        session_id: document.getElementById('editSessId').value,
        label: document.getElementById('editSessLabel').value,
        phone_number: document.getElementById('editSessPhone').value,
        sales_id: document.getElementById('editSessSalesId').value,
        mode: document.getElementById('editSessMode').value,
        daily_limit: document.getElementById('editSessLimit').value,
        is_active: document.getElementById('editSessActive').checked ? '1' : '0'
    });
    const data = await fetch('ajax_wa_broadcast.php', { method: 'POST', body: fd }).then(r => r.json());
    bootstrap.Modal.getInstance(document.getElementById('modalEditOpenwaSession'))?.hide();
    Swal.fire({ icon: data.success ? 'success' : 'error', title: data.success ? 'Tersimpan' : 'Gagal', text: data.message, timer: 1600, showConfirmButton: false });
    loadOpenwaDashboard();
}

async function doOpenwaSessionAction(sessionId, op) {
    const fd = new URLSearchParams({ action: 'openwa_session_action', session_id: sessionId, op: op });
    const data = await fetch('ajax_wa_broadcast.php', { method: 'POST', body: fd }).then(r => r.json());
    if (!data.success) {
        Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
    }
    loadOpenwaDashboard();
}

async function confirmDeleteSession(sessionId, label) {
    const r = await Swal.fire({
        title: 'Hapus Sesi?',
        text: `Sesi "${label}" akan dihapus dari OpenWA & CRM.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    });
    if (r.isConfirmed) {
        await doOpenwaSessionAction(sessionId, 'delete');
    }
}

async function testOpenwaSend(sessionId, label) {
    const { value: phone } = await Swal.fire({
        title: `Tes Kirim via ${label}`,
        input: 'text',
        inputLabel: 'Masukkan Nomor WA Tujuan Tes (misal nomor HP Anda):',
        inputValue: '<?= htmlspecialchars($current_wa) ?>',
        showCancelButton: true,
        confirmButtonText: 'Kirim Tes',
        confirmButtonColor: '#10b981'
    });
    if (!phone) return;
    Swal.fire({ title: 'Mengirim tes (simulasi mengetik)...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const fd = new URLSearchParams({ action: 'openwa_test_send', session_id: sessionId, phone: phone });
    const data = await fetch('ajax_wa_broadcast.php', { method: 'POST', body: fd }).then(r => r.json());
    Swal.fire({ icon: data.success ? 'success' : 'error', title: data.success ? 'Terkirim!' : 'Gagal', text: data.message });
}

let openwaLastQrValue = '';

function setQrStatusPill(type, htmlContent) {
    const badge = document.getElementById('qrStatusBadge');
    if (!badge) return;
    const styles = {
        ready:  { bg: '#ecfdf5', color: '#065f46', border: '1px solid #a7f3d0' },
        qr:     { bg: '#ecfdf5', color: '#047857', border: '1px solid #6ee7b7' },
        auth:   { bg: '#eff6ff', color: '#1d4ed8', border: '1px solid #93c5fd' },
        wait:   { bg: '#fffbeb', color: '#92400e', border: '1px solid #fde68a' },
        error:  { bg: '#fff1f2', color: '#9f1239', border: '1px solid #fecdd3' }
    };
    const st = styles[type] || styles.wait;
    badge.style.background = st.bg;
    badge.style.color      = st.color;
    badge.style.border     = st.border;
    badge.innerHTML        = htmlContent;
}

function openOpenwaQrModal(sessionId, label) {
    openwaQrActiveSessionId = sessionId;
    openwaLastQrValue = '';
    document.getElementById('qrModalSessionLabel').textContent = label;
    document.getElementById('qrStageOuter')?.classList.remove('is-ready-scan');
    document.getElementById('qrFooterLiveText').textContent = 'Mendeteksi scan otomatis...';
    setQrStatusPill('wait', '<span class="spinner-border spinner-border-sm" style="width:10px;height:10px;"></span> Menyiapkan QR Code...');
    document.getElementById('qrImageWrapper').innerHTML = '<div class="text-muted small"><div class="spinner-border text-success mb-2"></div><div class="fw-semibold">Meminta QR Code...</div></div>';

    const modalEl = document.getElementById('modalOpenwaQr');
    bootstrap.Modal.getOrCreateInstance(modalEl).show();

    pollOpenwaQr();
    if (openwaQrPollTimer) clearInterval(openwaQrPollTimer);
    // Polling cepat di background hanya untuk mendeteksi status scan — QR tidak digambar ulang kecuali benar-benar berubah dari WhatsApp!
    openwaQrPollTimer = setInterval(pollOpenwaQr, 2500);

    modalEl.addEventListener('hidden.bs.modal', () => {
        if (openwaQrPollTimer) {
            clearInterval(openwaQrPollTimer);
            openwaQrPollTimer = null;
        }
        openwaLastQrValue = '';
        loadOpenwaDashboard();
    }, { once: true });
}

function forceRefreshOpenwaQr() {
    openwaLastQrValue = '';
    document.getElementById('qrImageWrapper').innerHTML = '<div class="text-muted small"><div class="spinner-border text-success mb-2"></div><div class="fw-semibold">Memuat ulang QR...</div></div>';
    pollOpenwaQr();
}

async function pollOpenwaQr() {
    if (!openwaQrActiveSessionId) return;
    try {
        const data = await fetch('ajax_wa_broadcast.php?action=openwa_get_qr&session_id=' + encodeURIComponent(openwaQrActiveSessionId)).then(r => r.json());
        const wrap  = document.getElementById('qrImageWrapper');
        const stage = document.getElementById('qrStageOuter');
        const foot  = document.getElementById('qrFooterLiveText');

        if (!data.success) {
            setQrStatusPill('error', '<i class="bi bi-exclamation-triangle-fill"></i> Gagal Memuat');
            wrap.innerHTML = `<div class="p-3 text-danger small fw-semibold">${escapeOpenwaHtml(data.message)}</div>`;
            return;
        }

        const st = (data.status || 'waiting').toLowerCase();

        if (st === 'ready') {
            if (openwaQrPollTimer) {
                clearInterval(openwaQrPollTimer);
                openwaQrPollTimer = null;
            }
            stage?.classList.add('is-ready-scan');
            setQrStatusPill('ready', '<i class="bi bi-check-circle-fill"></i> TERHUBUNG (READY)');
            if (foot) foot.textContent = 'Perangkat berhasil ditautkan!';
            wrap.innerHTML = `
                <div class="text-success p-3 qr-img-fade">
                    <div class="rounded-circle bg-success-subtle d-inline-flex align-items-center justify-content-center mb-2" style="width:76px;height:76px;">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:46px;"></i>
                    </div>
                    <div class="fw-bold text-dark fs-6">WhatsApp Terhubung!</div>
                    <div class="small text-muted mt-0.5">${escapeOpenwaHtml(data.phone || '')}</div>
                </div>`;
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalOpenwaQr'))?.hide();
                loadOpenwaDashboard();
            }, 1500);
            return;
        }

        if (st === 'authenticating') {
            openwaLastQrValue = '';
            setQrStatusPill('auth', '<span class="spinner-border spinner-border-sm" style="width:10px;height:10px;"></span> QR Terbaca — Menghubungkan...');
            if (foot) foot.textContent = 'Sinkronisasi akun WhatsApp...';
            wrap.innerHTML = `
                <div class="text-primary p-3 qr-img-fade">
                    <div class="spinner-border text-primary mb-2" style="width:42px;height:42px;"></div>
                    <div class="fw-bold text-dark small">QR Berhasil Discan!</div>
                    <div class="text-muted" style="font-size:11.5px;">Mohon tunggu beberapa detik...</div>
                </div>`;
            return;
        }

        if (data.qr) {
            const qrStr = String(data.qr);
            stage?.classList.add('is-ready-scan');
            setQrStatusPill('qr', '<span class="pulse-dot emerald"></span> SIAP SCAN QR');
            if (foot) foot.textContent = 'Menunggu scan dari kamera HP...';

            // ZERO-FLICKER: Hanya render ulang gambar jika token QR benar-benar berubah dari server WhatsApp (~20-40 dtk sekali)!
            if (qrStr !== openwaLastQrValue) {
                openwaLastQrValue = qrStr;
                const imgSrc = qrStr.startsWith('data:image')
                    ? qrStr
                    : ('https://api.qrserver.com/v1/create-qr-code/?size=244x244&margin=2&data=' + encodeURIComponent(qrStr));
                wrap.innerHTML = `<img src="${imgSrc}" alt="QR WhatsApp" class="qr-img-fade" style="width:244px;height:244px;object-fit:contain;border-radius:10px;">`;
            }
        } else {
            setQrStatusPill('wait', '<span class="spinner-border spinner-border-sm" style="width:10px;height:10px;"></span> ' + escapeOpenwaHtml(st.toUpperCase()));
        }
    } catch (e) {}
}

document.addEventListener('DOMContentLoaded', loadOpenwaDashboard);
</script>

<?php require_once 'includes/footer.php'; ?>
