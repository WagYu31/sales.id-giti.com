<?php
/**
 * wa_support_settings.php
 * Halaman Manajemen Pesan Bantuan Masuk & Pengaturan WhatsApp Gateway (Superadmin Only)
 */

$page_title = 'Pusat Pesan Bantuan WhatsApp';
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (strtolower(trim($_SESSION['role'] ?? '')) !== 'superadmin') {
    $_SESSION['flash_message_error'] = "Akses ditolak. Halaman ini hanya untuk Super Admin.";
    header("Location: index.php");
    exit();
}

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

// Proses Simpan Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $wa_target = trim($_POST['wa_support_number'] ?? '085771593522');
    $fonnte_token = trim($_POST['fonnte_token'] ?? '');

    // 1. Simpan ke JSON file (paling aman & persisten tanpa terpengaruh schema database)
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

    $msg_success = "Pengaturan WhatsApp Gateway & Token Fonnte berhasil disimpan!";
}

// Proses Tes Kirim WA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_send_wa'])) {
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

require_once 'includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="bi bi-whatsapp text-success me-2"></i>Pusat Pesan Bantuan &amp; WA Support</h4>
        <p class="text-muted small mb-0">Daftar laporan kendala dari tim sales &amp; konfigurasi WhatsApp Gateway otomatis</p>
    </div>
    <div class="d-flex gap-2">
        <a href="https://wa.me/<?= preg_replace('/^0/', '62', $current_wa) ?>" target="_blank" class="btn btn-outline-success btn-sm rounded-3 px-3 fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm">
            <i class="bi bi-whatsapp"></i> Chat Nomor Target (<?= htmlspecialchars($current_wa) ?>)
        </a>
    </div>
</div>

<?php if ($msg_success): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($msg_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($msg_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Pengaturan Gateway -->
    <div class="col-lg-4 col-12">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark m-0"><i class="bi bi-sliders2 text-primary me-2"></i>Konfigurasi WA Gateway</h6>
                    <?php if (!empty($current_token)): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Belum Ada Token</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($current_token)): ?>
                    <div class="p-2.5 bg-light rounded-3 border mb-3 small d-flex align-items-center gap-2">
                        <i class="bi bi-shield-check text-success fs-5"></i>
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 12px;">Token Fonnte Terpasang</div>
                            <div class="text-muted" style="font-size: 11px;">Pesan kendala akan diteruskan otomatis ke WA HP Anda.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-2.5 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 mb-3 small d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle-fill text-warning fs-6 mt-0.5"></i>
                        <div style="font-size: 11px;" class="text-secondary">
                            <strong>Token Fonnte belum tersimpan di kolom bawah.</strong> Silakan salin (copy) kode token dari Fonnte, lalu tempel (paste) ke kolom token di bawah dan klik Simpan.
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="wa_support_settings.php">
                    <input type="hidden" name="save_settings" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nomor WA Tujuan:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-telephone text-success"></i></span>
                            <input type="text" name="wa_support_number" class="form-control" value="<?= htmlspecialchars($current_wa) ?>" required placeholder="085771593522">
                        </div>
                        <small class="text-muted" style="font-size: 11px;">Semua laporan kendala dari bot web akan diarahkan ke nomor ini.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Token API Fonnte:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-key text-warning"></i></span>
                            <input type="password" name="fonnte_token" id="fonnteTokenInput" class="form-control font-monospace" value="<?= htmlspecialchars($current_token) ?>" placeholder="Tempel token di sini (contoh: a1b2c3d...)">
                            <button class="btn btn-outline-secondary" type="button" onclick="
                                const inp = document.getElementById('fonnteTokenInput');
                                inp.type = (inp.type === 'password') ? 'text' : 'password';
                            "><i class="bi bi-eye"></i></button>
                        </div>
                        <small class="text-muted" style="font-size: 11px;">
                            Buka <a href="https://fonnte.com" target="_blank" class="fw-bold text-primary">fonnte.com</a> > klik tombol <strong>Token</strong> > salin kodenya &amp; tempel di atas.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-bold shadow-sm mb-2">
                        <i class="bi bi-save me-1"></i> Simpan Konfigurasi
                    </button>
                </form>

                <?php if (!empty($current_token)): ?>
                    <hr class="my-3">
                    <form method="POST" action="wa_support_settings.php">
                        <input type="hidden" name="test_send_wa" value="1">
                        <input type="hidden" name="test_target" value="<?= htmlspecialchars($current_wa) ?>">
                        <input type="hidden" name="test_token" value="<?= htmlspecialchars($current_token) ?>">
                        <button type="submit" class="btn btn-outline-success w-100 rounded-3 py-2 fw-bold btn-sm shadow-sm">
                            <i class="bi bi-whatsapp me-1"></i> Tes Kirim Pesan WA Sekarang
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistik Ringkas -->
    <div class="col-lg-8 col-12">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark m-0"><i class="bi bi-chat-left-text-fill text-success me-2"></i>Riwayat Pesan Kendala Masuk (<?= count($messages) ?>)</h6>
            </div>
            <div class="card-body p-4">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 text-slate-300 d-block mb-2"></i>
                        Belum ada pesan kendala yang dikirim dari bot web.
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table class="table table-hover align-middle" style="font-size: 13px;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Waktu</th>
                                    <th>Pengirim</th>
                                    <th>Halaman</th>
                                    <th>Pesan Kendala</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $m): ?>
                                    <tr>
                                        <td class="text-nowrap text-secondary small">
                                            <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($m['nama_pengirim']) ?></div>
                                            <span class="badge bg-light text-dark border" style="font-size: 10px;"><?= htmlspecialchars($m['role']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size: 10.5px;"><?= htmlspecialchars($m['halaman'] ?: '-') ?></span>
                                        </td>
                                        <td style="max-width: 320px;">
                                            <div class="text-wrap text-break"><?= nl2br(htmlspecialchars($m['pesan'])) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($m['status_kirim'] === 'sent'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                                    <i class="bi bi-check-all me-1"></i> Terkirim WA
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
                                                    <i class="bi bi-check-circle me-1"></i> Tersimpan
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

<?php require_once 'includes/footer.php'; ?>
