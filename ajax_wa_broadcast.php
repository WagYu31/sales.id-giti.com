<?php
/**
 * ajax_wa_broadcast.php
 * Backend AJAX untuk Eksekusi WhatsApp Broadcast CRM & Leads (Loewix Sales Workspace)
 * Mendukung:
 *  - OpenWA Self-Hosted (rmyndharis/OpenWA) dengan 8-Lapis Proteksi Anti-Ban & Anti-Logout
 *  - Manajemen Multi-Session & Scan QR OpenWA
 *  - Mode Asisten 1-Klik (WhatsApp Resmi)
 *  - Legacy Fonnte Gateway
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/openwa_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Verifikasi sesi login sales / superadmin
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi login telah berakhir. Silakan login kembali.'
    ]);
    exit();
}

ensureOpenWATables($conn);

$action        = trim($_POST['action'] ?? ($_GET['action'] ?? ''));
$isSuperAdmin  = (strtolower(trim($_SESSION['role'] ?? '')) === 'superadmin');
$activeSalesId = (int)($_SESSION['user_id'] ?? 0);

// Helper: Ambil Token Fonnte (Legacy)
function getGatewayCredentials($conn) {
    $tokenGateway = '';
    $configFile = __DIR__ . '/includes/wa_gateway_config.json';
    if (file_exists($configFile)) {
        $cfg = json_decode(@file_get_contents($configFile), true);
        if (!empty($cfg['fonnte_token'])) {
            $tokenGateway = trim($cfg['fonnte_token']);
        }
    }
    if (empty($tokenGateway)) {
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key IN ('fonnte_token', 'wa_gateway_token') AND setting_value IS NOT NULL AND setting_value != '' LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $tokenGateway = trim($row['setting_value']);
        }
    }
    return $tokenGateway;
}

// =============================================================================
// A. OPENWA HEALTH, MULTI-SESSION & ANTI-BAN MANAGEMENT
// =============================================================================

// 1. Cek Kesehatan OpenWA + Ringkasan Sesi & Kuota
if ($action === 'openwa_health') {
    $config = getOpenWAConfig($conn);
    $health = openwaRequest($config, 'GET', 'health', null, 8);
    $sync   = openwaSyncAndListSessions($conn, $config);

    $readyCount      = 0;
    $healthySenders  = 0;
    $totalRemaining  = 0;
    $totalDailyLimit = 0;
    $totalSentToday  = 0;

    foreach ($sync['sessions'] as $s) {
        if ($s['status'] === 'ready') $readyCount++;
        if ($s['can_send']) {
            $healthySenders++;
            $totalRemaining += $s['remaining_today'];
        }
        $totalDailyLimit += $s['daily_limit'];
        $totalSentToday  += $s['sent_today'];
    }

    $hourNow = (int)date('G');
    $isWorkHours = ($hourNow >= 8 && $hourNow < 20);

    echo json_encode([
        'success'           => true,
        'online'            => $health['ok'],
        'base_url'          => $config['base_url'],
        'has_api_key'       => !empty($config['api_key']),
        'version'           => is_array($health['data']) ? ($health['data']['version'] ?? null) : null,
        'auth_valid'        => is_array($health['data']) && !empty($health['data']['version']),
        'error'             => $health['ok'] ? '' : $health['error'],
        'total_sessions'    => count($sync['sessions']),
        'ready_sessions'    => $readyCount,
        'healthy_senders'   => $healthySenders,
        'total_sent_today'  => $totalSentToday,
        'total_daily_limit' => $totalDailyLimit,
        'total_remaining'   => $totalRemaining,
        'dedup_days'        => $config['dedup_days'],
        'is_work_hours'     => $isWorkHours,
        'current_time_wib'  => date('H:i') . ' WIB',
        'sessions'          => $sync['sessions'],
    ]);
    exit();
}

// 2. Daftar Sesi OpenWA + Statistik Anti-Ban Hari Ini
if ($action === 'openwa_list_sessions') {
    $config = getOpenWAConfig($conn);
    $sync   = openwaSyncAndListSessions($conn, $config);

    $stats = ['sent' => 0, 'skipped_not_wa' => 0, 'skipped_recent' => 0, 'failed' => 0];
    $resStats = @$conn->query("
        SELECT status, COUNT(*) AS cnt
        FROM wa_broadcast_logs
        WHERE provider = 'openwa' AND DATE(created_at) = CURDATE()
        GROUP BY status
    ");
    if ($resStats) {
        while ($st = $resStats->fetch_assoc()) {
            if (isset($stats[$st['status']])) $stats[$st['status']] = (int)$st['cnt'];
        }
    }

    echo json_encode([
        'success'       => true,
        'openwa_online' => $sync['openwa_online'],
        'error'         => $sync['error'],
        'sessions'      => $sync['sessions'],
        'today_stats'   => $stats,
    ]);
    exit();
}

// 3. Buat Sesi Baru di OpenWA (Superadmin Only)
if ($action === 'openwa_create_session') {
    if (!$isSuperAdmin) {
        echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang dapat membuat sesi OpenWA.']);
        exit();
    }

    $label    = trim($_POST['label'] ?? '');
    $rawPhone = trim($_POST['phone_number'] ?? '');
    $salesId  = (int)($_POST['sales_id'] ?? 0);
    $mode     = in_array($_POST['mode'] ?? '', ['warmup', 'normal', 'boost'], true) ? $_POST['mode'] : 'warmup';
    $limit    = (int)($_POST['daily_limit'] ?? 0);
    if ($limit <= 0) $limit = openwaModeDefaultLimit($mode);
    $limit    = max(5, min(150, $limit));

    $normPhone = normalizeIndonesianPhone($rawPhone);
    if ($label === '') {
        $label = $normPhone !== '' ? ('Sales ' . $normPhone) : ('Sales WA ' . date('His'));
    }

    $slugName = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', strtolower($label));
    $slugName = trim($slugName, '-');
    if ($slugName === '') $slugName = 'sales-' . time();

    $config = getOpenWAConfig($conn);
    $createRes = openwaRequest($config, 'POST', 'sessions', ['name' => $slugName], 15);
    if (!$createRes['ok']) {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat sesi di OpenWA: ' . $createRes['error']]);
        exit();
    }

    $created = is_array($createRes['data']) ? $createRes['data'] : [];
    if (isset($created['data']) && is_array($created['data'])) $created = $created['data'];
    $sessionId = $created['id'] ?? ($created['sessionId'] ?? '');
    if ($sessionId === '') {
        echo json_encode(['success' => false, 'message' => 'OpenWA tidak mengembalikan ID sesi yang valid.']);
        exit();
    }

    // Langsung start sesi agar QR code segera siap
    @openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/start', null, 15);

    $salesIdParam = $salesId > 0 ? $salesId : null;
    $statusInit = 'initializing';
    $stmt = $conn->prepare("
        INSERT INTO wa_openwa_sessions (session_id, session_name, label, phone_number, sales_id, mode, daily_limit, is_active, last_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE label = VALUES(label), phone_number = VALUES(phone_number), sales_id = VALUES(sales_id), mode = VALUES(mode), daily_limit = VALUES(daily_limit)
    ");
    if ($stmt) {
        $stmt->bind_param("ssssisis", $sessionId, $slugName, $label, $normPhone, $salesIdParam, $mode, $limit, $statusInit);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'success'    => true,
        'session_id' => $sessionId,
        'message'    => "Sesi '{$label}' berhasil dibuat di OpenWA! Klik 'Scan QR' untuk menautkan WhatsApp.",
    ]);
    exit();
}

// 4. Setup Cepat 4 Nomor Sales Loewix (Superadmin Only)
if ($action === 'openwa_quick_setup_sales') {
    if (!$isSuperAdmin) {
        echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang dapat menjalankan setup cepat.']);
        exit();
    }

    $config = getOpenWAConfig($conn);
    $sync   = openwaSyncAndListSessions($conn, $config);
    if (!$sync['openwa_online']) {
        echo json_encode(['success' => false, 'message' => 'Server OpenWA belum online: ' . $sync['error']]);
        exit();
    }

    $defaultNumbers = [
        ['label' => 'Sales 1 (0877-0019-1818)', 'name' => 'loewix-sales-1-1818', 'phone' => '6287700191818'],
        ['label' => 'Sales 2 (0819-9699-1818)', 'name' => 'loewix-sales-2-1818', 'phone' => '6281996991818'],
        ['label' => 'Sales 3 (0819-5899-1818)', 'name' => 'loewix-sales-3-1818', 'phone' => '6281958991818'],
        ['label' => 'Sales 4 (0819-5299-1818)', 'name' => 'loewix-sales-4-1818', 'phone' => '6281952991818'],
    ];

    $existingPhones = [];
    $existingNames  = [];
    foreach ($sync['sessions'] as $ex) {
        if (!empty($ex['phone_number'])) $existingPhones[] = normalizeIndonesianPhone($ex['phone_number']);
        if (!empty($ex['session_name'])) $existingNames[]  = strtolower($ex['session_name']);
    }

    $createdCount = 0;
    $skippedCount = 0;
    $errors       = [];

    foreach ($defaultNumbers as $item) {
        if (in_array($item['phone'], $existingPhones, true) || in_array(strtolower($item['name']), $existingNames, true)) {
            $skippedCount++;
            continue;
        }

        $res = openwaRequest($config, 'POST', 'sessions', ['name' => $item['name']], 15);
        if (!$res['ok']) {
            $errors[] = $item['label'] . ': ' . $res['error'];
            continue;
        }
        $d = is_array($res['data']) ? $res['data'] : [];
        if (isset($d['data']) && is_array($d['data'])) $d = $d['data'];
        $sid = $d['id'] ?? ($d['sessionId'] ?? '');
        if ($sid === '') continue;

        @openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sid) . '/start', null, 12);

        $stmt = $conn->prepare("
            INSERT INTO wa_openwa_sessions (session_id, session_name, label, phone_number, mode, daily_limit, is_active, last_status)
            VALUES (?, ?, ?, ?, 'warmup', 20, 1, 'initializing')
            ON DUPLICATE KEY UPDATE label = VALUES(label), phone_number = VALUES(phone_number)
        ");
        if ($stmt) {
            $stmt->bind_param("ssss", $sid, $item['name'], $item['label'], $item['phone']);
            $stmt->execute();
            $stmt->close();
        }
        $createdCount++;
    }

    echo json_encode([
        'success' => true,
        'created' => $createdCount,
        'skipped' => $skippedCount,
        'errors'  => $errors,
        'message' => "Setup selesai: {$createdCount} sesi baru dibuat, {$skippedCount} sudah terdaftar. Silakan Scan QR satu per satu!",
    ]);
    exit();
}

// 5. Ambil QR Code Live untuk Scan (Superadmin Only)
if ($action === 'openwa_get_qr') {
    if (!$isSuperAdmin) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit();
    }

    $sessionId = trim($_POST['session_id'] ?? ($_GET['session_id'] ?? ''));
    if ($sessionId === '') {
        echo json_encode(['success' => false, 'message' => 'Session ID kosong.']);
        exit();
    }

    $config = getOpenWAConfig($conn);
    $infoRes = openwaRequest($config, 'GET', 'sessions/' . rawurlencode($sessionId), null, 12);
    if (!$infoRes['ok']) {
        echo json_encode(['success' => false, 'message' => 'Gagal membaca status sesi: ' . $infoRes['error']]);
        exit();
    }

    $info = is_array($infoRes['data']) ? $infoRes['data'] : [];
    if (isset($info['data']) && is_array($info['data'])) $info = $info['data'];
    $status = strtolower(trim((string)($info['status'] ?? 'unknown')));

    // Jika sesi belum start, otomatis start agar QR muncul
    if (in_array($status, ['created', 'stopped', 'disconnected', 'failed'], true)) {
        @openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/start', null, 12);
        usleep(600000);
        $status = 'initializing';
    }

    // Jika sudah ready, update DB & informasikan sukses
    if ($status === 'ready') {
        $phone = normalizeIndonesianPhone($info['phone'] ?? '');
        $stmt = $conn->prepare("UPDATE wa_openwa_sessions SET last_status = 'ready', phone_number = IF(? != '', ?, phone_number), consecutive_fails = 0, paused_until = NULL WHERE session_id = ?");
        if ($stmt) {
            $stmt->bind_param("sss", $phone, $phone, $sessionId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode([
            'success'   => true,
            'status'    => 'ready',
            'phone'     => $phone,
            'push_name' => $info['pushName'] ?? '',
            'qr'        => null,
        ]);
        exit();
    }

    // Ambil QR Code
    $qrRes = openwaRequest($config, 'GET', 'sessions/' . rawurlencode($sessionId) . '/qr', null, 12);
    $qrData = null;
    if ($qrRes['ok']) {
        if (is_array($qrRes['data'])) {
            $qd = isset($qrRes['data']['data']) && is_array($qrRes['data']['data']) ? $qrRes['data']['data'] : $qrRes['data'];
            $qrData = $qd['qr'] ?? ($qd['qrCode'] ?? ($qd['image'] ?? null));
            if (!empty($qd['status'])) $status = strtolower((string)$qd['status']);
        } elseif (is_string($qrRes['data']) && trim($qrRes['data']) !== '') {
            $qrData = trim($qrRes['data']);
        }
    }

    echo json_encode([
        'success' => true,
        'status'  => $status,
        'qr'      => $qrData,
        'message' => $qrData ? 'Scan QR Code ini di WhatsApp HP -> Perangkat Tertaut.' : 'Sedang menyiapkan QR Code, tunggu beberapa detik...',
    ]);
    exit();
}

// 6. Aksi Sesi OpenWA: start / stop / delete / reset_breaker / update_config (Superadmin Only)
if ($action === 'openwa_session_action') {
    if (!$isSuperAdmin) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit();
    }

    $sessionId = trim($_POST['session_id'] ?? '');
    $op        = trim($_POST['op'] ?? '');
    if ($sessionId === '' || $op === '') {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
        exit();
    }

    $config = getOpenWAConfig($conn);

    if ($op === 'start') {
        $res = openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/start', null, 15);
        echo json_encode(['success' => $res['ok'], 'message' => $res['ok'] ? 'Sesi sedang dijalankan...' : $res['error']]);
        exit();
    }

    if ($op === 'stop') {
        $res = openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/stop', null, 15);
        echo json_encode(['success' => $res['ok'], 'message' => $res['ok'] ? 'Sesi dihentikan.' : $res['error']]);
        exit();
    }

    if ($op === 'delete') {
        @openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/stop', null, 10);
        @openwaRequest($config, 'DELETE', 'sessions/' . rawurlencode($sessionId), null, 12);
        $stmt = $conn->prepare("DELETE FROM wa_openwa_sessions WHERE session_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $sessionId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Sesi berhasil dihapus dari OpenWA & CRM.']);
        exit();
    }

    if ($op === 'reset_breaker') {
        openwaRecordSuccess($conn, $sessionId);
        echo json_encode(['success' => true, 'message' => 'Circuit Breaker berhasil direset. Sesi siap digunakan kembali.']);
        exit();
    }

    if ($op === 'update_config') {
        $label    = trim($_POST['label'] ?? '');
        $phone    = normalizeIndonesianPhone($_POST['phone_number'] ?? '');
        $salesId  = (int)($_POST['sales_id'] ?? 0);
        $mode     = in_array($_POST['mode'] ?? '', ['warmup', 'normal', 'boost'], true) ? $_POST['mode'] : 'warmup';
        $limit    = (int)($_POST['daily_limit'] ?? 0);
        if ($limit <= 0) $limit = openwaModeDefaultLimit($mode);
        $limit    = max(5, min(150, $limit));
        $isActive = isset($_POST['is_active']) ? ((int)$_POST['is_active'] ? 1 : 0) : 1;
        $salesParam = $salesId > 0 ? $salesId : null;

        $stmt = $conn->prepare("
            UPDATE wa_openwa_sessions
            SET label = IF(? != '', ?, label),
                phone_number = IF(? != '', ?, phone_number),
                sales_id = ?,
                mode = ?,
                daily_limit = ?,
                is_active = ?
            WHERE session_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("ssssisiis", $label, $label, $phone, $phone, $salesParam, $mode, $limit, $isActive, $sessionId);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(['success' => true, 'message' => 'Pengaturan anti-ban sesi berhasil disimpan!']);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Operasi tidak dikenali.']);
    exit();
}

// 7. Tes Kirim 1 Pesan via Sesi OpenWA (Superadmin Only)
if ($action === 'openwa_test_send') {
    if (!$isSuperAdmin) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
        exit();
    }

    $sessionId = trim($_POST['session_id'] ?? '');
    $rawPhone  = trim($_POST['phone'] ?? '');
    $cleanPhone = normalizeIndonesianPhone($rawPhone);

    if ($sessionId === '' || strlen($cleanPhone) < 9) {
        echo json_encode(['success' => false, 'message' => 'Pilih sesi dan masukkan nomor tujuan yang valid.']);
        exit();
    }

    $config = getOpenWAConfig($conn);
    $check  = openwaCheckNumberRegistered($config, $sessionId, $cleanPhone);
    if ($check['checked'] && !$check['exists']) {
        echo json_encode(['success' => false, 'message' => "Nomor {$cleanPhone} tidak terdaftar di WhatsApp."]);
        exit();
    }

    $chatId = $check['chatId'];
    $testMsg = "✅ *TES KONEKSI OPENWA ANTI-BAN*\n\nHalo Admin Loewix! Sesi OpenWA pada sistem *sales.id-giti.com* telah berhasil terhubung dengan engine Baileys + Simulasi Mengetik.\nWaktu: " . date('d/m/Y H:i:s') . " WIB";
    openwaSimulateHumanTyping($config, $sessionId, $chatId, strlen($testMsg));

    $sendRes = openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/messages/send-text', [
        'chatId' => $chatId,
        'text'   => $testMsg,
    ], 25);

    if ($sendRes['ok']) {
        echo json_encode(['success' => true, 'message' => "Pesan tes berhasil terkirim ke {$cleanPhone}!"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal kirim tes: ' . $sendRes['error']]);
    }
    exit();
}

// =============================================================================
// B. ENGINE KIRIM BROADCAST OPENWA 8-LAPIS ANTI-BAN (SINGLE QUEUE ITEM)
// =============================================================================
if ($action === 'send_single_openwa') {
    $rawPhone         = trim($_POST['phone'] ?? '');
    $templateMessage  = trim($_POST['message'] ?? '');
    $customerId       = (int)($_POST['customer_id'] ?? 0);
    $customerName     = trim($_POST['customer_name'] ?? 'Toko');
    $customerPic      = trim($_POST['customer_pic'] ?? 'Bpk/Ibu');
    $customerCity     = trim($_POST['customer_city'] ?? '-');
    $customerSales    = trim($_POST['customer_sales'] ?? 'Sales Loewix');
    $sessionMode      = trim($_POST['session_mode'] ?? 'auto_rotate');
    $logFu            = (int)($_POST['log_fu'] ?? 1);
    $precheckWa       = (int)($_POST['precheck_wa'] ?? 1);
    $skipRecent       = (int)($_POST['skip_recent'] ?? 1);
    $simulateTyping   = (int)($_POST['simulate_typing'] ?? 1);
    $enableSpintax    = (int)($_POST['enable_spintax'] ?? 1);
    $enforceWorkHours = (int)($_POST['enforce_work_hours'] ?? 0);

    $config = getOpenWAConfig($conn);

    // Layer 8: Guard Jam Operasional (08:00 - 19:59 WIB)
    if ($enforceWorkHours === 1) {
        $hourNow = (int)date('G');
        if ($hourNow < 8 || $hourNow >= 20) {
            echo json_encode([
                'success'    => false,
                'stop_queue' => true,
                'reason'     => 'outside_work_hours',
                'message'    => 'Di luar jam operasional aman (08:00–20:00 WIB). Hapus centang "Kunci Jam Kerja" jika Anda ingin tetap mengirim/tes sekarang.',
            ]);
            exit();
        }
    }

    $cleanPhone = normalizeIndonesianPhone($rawPhone);
    if (strlen($cleanPhone) < 9) {
        echo json_encode(['success' => false, 'message' => "Nomor telepon ({$rawPhone}) tidak valid."]);
        exit();
    }
    if ($templateMessage === '') {
        echo json_encode(['success' => false, 'message' => 'Pesan broadcast tidak boleh kosong.']);
        exit();
    }

    // Layer 7: Deduplikasi Anti-Spam Ganda (auto-skip jika baru saja di-BC dalam N hari)
    if ($skipRecent === 1 && $config['dedup_days'] > 0) {
        $recent = openwaWasRecentlySent($conn, $cleanPhone, $customerId, $config['dedup_days']);
        if ($recent) {
            openwaWriteBroadcastLog($conn, [
                'sales_id'        => $activeSalesId,
                'customer_id'     => $customerId,
                'customer_name'   => $customerName,
                'phone'           => $cleanPhone,
                'provider'        => 'openwa',
                'status'          => 'skipped_recent',
                'error_detail'    => 'Sudah pernah dikirimi pada ' . $recent['created_at'],
            ]);
            echo json_encode([
                'success'     => true,
                'skipped'     => true,
                'skip_reason' => 'recent',
                'message'     => "Dilewati (sudah di-broadcast pada " . date('d/m H:i', strtotime($recent['created_at'])) . " — proteksi anti-spam {$config['dedup_days']} hari)",
            ]);
            exit();
        }
    }

    // Layer 1 & 5: Pilih Sesi Sehat (Rotasi 4 Nomor + Daily Cap + Circuit Breaker + Restriction Check)
    $picked = openwaSelectHealthySession($conn, $config, $sessionMode, $activeSalesId);
    if (!$picked['ok']) {
        echo json_encode([
            'success'    => false,
            'stop_queue' => true,
            'reason'     => $picked['reason'],
            'message'    => $picked['message'],
        ]);
        exit();
    }

    $session      = $picked['session'];
    $sessionId    = $session['session_id'];
    $sessionLabel = $session['label'];

    // Layer 2: Pre-Flight Check Nomor Aktif di WhatsApp (hindari kirim ke nomor mati)
    $chatId = $cleanPhone . '@c.us';
    if ($precheckWa === 1) {
        $chk = openwaCheckNumberRegistered($config, $sessionId, $cleanPhone);
        $chatId = $chk['chatId'];
        if ($chk['checked'] && !$chk['exists']) {
            openwaWriteBroadcastLog($conn, [
                'session_id'      => $sessionId,
                'session_label'   => $sessionLabel,
                'sales_id'        => $activeSalesId,
                'customer_id'     => $customerId,
                'customer_name'   => $customerName,
                'phone'           => $cleanPhone,
                'provider'        => 'openwa',
                'status'          => 'skipped_not_wa',
                'error_detail'    => 'Nomor tidak terdaftar di WhatsApp',
            ]);
            echo json_encode([
                'success'       => true,
                'skipped'       => true,
                'skip_reason'   => 'not_wa',
                'session_label' => $sessionLabel,
                'message'       => "Dilewati otomatis — nomor {$cleanPhone} tidak terdaftar di WhatsApp (aman dari penalti spam)",
            ]);
            exit();
        }
    }

    // Layer 4: Render Spintax + Variabel + Anti-Duplicate Fingerprint
    $finalMessage = renderAntiBanMessage($templateMessage, [
        'nama_toko'  => $customerName,
        'nama_pic'   => $customerPic,
        'kota'       => $customerCity,
        'nama_sales' => $customerSales,
    ], $enableSpintax === 1);
    $msgHash = hash('sha256', $finalMessage);

    // Layer 3: Simulasi Mengetik Manusiawi sebelum kirim
    $typingSec = 0;
    if ($simulateTyping === 1) {
        $typingSec = openwaSimulateHumanTyping($config, $sessionId, $chatId, mb_strlen($finalMessage));
    }

    // Kirim via OpenWA REST API
    $sendRes = openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/messages/send-text', [
        'chatId' => $chatId,
        'text'   => $finalMessage,
    ], 30);

    if ($sendRes['ok']) {
        openwaRecordSuccess($conn, $sessionId);

        openwaWriteBroadcastLog($conn, [
            'session_id'      => $sessionId,
            'session_label'   => $sessionLabel,
            'sales_id'        => $activeSalesId,
            'customer_id'     => $customerId,
            'customer_name'   => $customerName,
            'phone'           => $cleanPhone,
            'message_hash'    => $msgHash,
            'message_preview' => preg_replace('/[\x{200B}-\x{200D}]/u', '', $finalMessage),
            'provider'        => 'openwa',
            'status'          => 'sent',
        ]);

        // Catat otomatis ke Riwayat Follow-Up CRM
        if ($logFu === 1 && $customerId > 0) {
            $tglFu = date('Y-m-d H:i:s');
            $respon = 'Broadcast WA (OpenWA)';
            $cleanDisplayMsg = preg_replace('/[\x{200B}-\x{200D}]/u', '', $finalMessage);
            $keterangan = "Broadcast OpenWA via {$sessionLabel} ke {$cleanPhone}:\n" . $cleanDisplayMsg;

            $stmtFu = $conn->prepare("INSERT INTO follow_ups (customer_id, sales_id, tgl_follow_up, respon, keterangan) VALUES (?, ?, ?, ?, ?)");
            if ($stmtFu) {
                $stmtFu->bind_param("iisss", $customerId, $activeSalesId, $tglFu, $respon, $keterangan);
                $stmtFu->execute();
                $stmtFu->close();
            }
        }

        $newSentToday  = $session['sent_today'] + 1;
        $newRemaining  = max(0, $session['daily_limit'] - $newSentToday);
        $msgId = is_array($sendRes['data']) ? ($sendRes['data']['messageId'] ?? ($sendRes['data']['id'] ?? '')) : '';

        echo json_encode([
            'success'         => true,
            'skipped'         => false,
            'phone'           => $cleanPhone,
            'customer_name'   => $customerName,
            'session_id'      => $sessionId,
            'session_label'   => $sessionLabel,
            'session_phone'   => $session['phone_number'],
            'sent_today'      => $newSentToday,
            'daily_limit'     => $session['daily_limit'],
            'remaining_today' => $newRemaining,
            'typing_sec'      => $typingSec,
            'message_id'      => $msgId,
        ]);
    } else {
        // Layer 6: Catat Kegagalan & Aktifkan Circuit Breaker jika perlu
        $errMsg = $sendRes['error'] ?: 'Gagal mengirim melalui OpenWA';
        $breaker = openwaRecordFailure($conn, $sessionId, $errMsg);

        openwaWriteBroadcastLog($conn, [
            'session_id'      => $sessionId,
            'session_label'   => $sessionLabel,
            'sales_id'        => $activeSalesId,
            'customer_id'     => $customerId,
            'customer_name'   => $customerName,
            'phone'           => $cleanPhone,
            'message_hash'    => $msgHash,
            'message_preview' => preg_replace('/[\x{200B}-\x{200D}]/u', '', $finalMessage),
            'provider'        => 'openwa',
            'status'          => 'failed',
            'error_detail'    => $errMsg,
        ]);

        // Cek apakah masih ada sesi sehat lain jika mode auto_rotate
        $shouldStop = false;
        if ($breaker['tripped'] && $sessionMode !== 'auto_rotate') {
            $shouldStop = true;
        }

        echo json_encode([
            'success'        => false,
            'skipped'        => false,
            'circuit_broken' => $breaker['tripped'],
            'stop_queue'     => $shouldStop,
            'session_label'  => $sessionLabel,
            'message'        => $breaker['tripped']
                ? "CIRCUIT BREAKER AKTIF pada {$sessionLabel} (istirahat 30 menit demi keamanan akun): {$errMsg}"
                : $errMsg,
        ]);
    }
    exit();
}

// =============================================================================
// C. LEGACY & SHARED ENDPOINTS (FONNTE, FILTER TARGETS, ASISTEN 1-KLIK)
// =============================================================================

// 1. CHECK TOKEN STATUS (Fonnte)
if ($action === 'check_token') {
    $token = getGatewayCredentials($conn);
    echo json_encode([
        'success' => true,
        'has_token' => !empty($token),
        'token_preview' => !empty($token) ? substr($token, 0, 6) . '...' : ''
    ]);
    exit();
}

// 2. SEND SINGLE BROADCAST ITEM (Fonnte Legacy)
if ($action === 'send_single') {
    $rawPhone    = trim($_POST['phone'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    $customerId  = (int)($_POST['customer_id'] ?? 0);
    $customerName= trim($_POST['customer_name'] ?? '');
    $logFu       = (int)($_POST['log_fu'] ?? 1);

    if (empty($rawPhone)) {
        echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp tujuan kosong.']);
        exit();
    }
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Pesan broadcast tidak boleh kosong.']);
        exit();
    }

    $cleanPhone = normalizeIndonesianPhone($rawPhone);
    if (strlen($cleanPhone) < 9) {
        echo json_encode(['success' => false, 'message' => "Nomor telepon ({$rawPhone}) tidak valid."]);
        exit();
    }

    $token = getGatewayCredentials($conn);
    if (empty($token)) {
        echo json_encode([
            'success' => false,
            'message' => 'Token API Fonnte belum dipasang di sistem. Silakan isi token di menu Pengaturan WA Gateway terlebih dahulu.'
        ]);
        exit();
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'target' => $cleanPhone,
            'message' => $message,
            'countryCode' => '62'
        ],
        CURLOPT_HTTPHEADER => [
            "Authorization: {$token}"
        ],
    ]);

    $response = curl_exec($curl);
    $curlErr  = curl_error($curl);
    curl_close($curl);

    if ($curlErr) {
        echo json_encode(['success' => false, 'message' => 'Gagal koneksi ke Fonnte: ' . $curlErr]);
        exit();
    }

    $respData = json_decode($response, true);
    $isSuccess = isset($respData['status']) && ($respData['status'] === true || $respData['status'] === 1 || $respData['status'] === 'true');

    if ($isSuccess) {
        if ($logFu === 1 && $customerId > 0) {
            $tglFu = date('Y-m-d H:i:s');
            $respon = 'Broadcast WhatsApp';
            $keterangan = "Pesan Broadcast Terkirim ke {$cleanPhone}:\n" . $message;

            $stmtFu = $conn->prepare("INSERT INTO follow_ups (customer_id, sales_id, tgl_follow_up, respon, keterangan) VALUES (?, ?, ?, ?, ?)");
            if ($stmtFu) {
                $stmtFu->bind_param("iisss", $customerId, $activeSalesId, $tglFu, $respon, $keterangan);
                $stmtFu->execute();
                $stmtFu->close();
            }
        }

        echo json_encode([
            'success' => true,
            'phone' => $cleanPhone,
            'customer_name' => $customerName,
            'fonnte_status' => $respData['status'] ?? true,
            'detail' => $respData['detail'] ?? 'Terkirim'
        ]);
    } else {
        $errorReason = $respData['reason'] ?? ($respData['message'] ?? 'Ditolak oleh WhatsApp / Fonnte');
        echo json_encode([
            'success' => false,
            'phone' => $cleanPhone,
            'customer_name' => $customerName,
            'message' => $errorReason
        ]);
    }
    exit();
}

// 3. GET FILTER TARGETS (Mengambil semua target customer sesuai filter aktif)
if ($action === 'get_filter_targets') {
    $filter_kota = trim($_POST['filter_kota'] ?? '');
    $filter_kategori = trim($_POST['filter_kategori'] ?? '');
    $filter_sales = intval($_POST['filter_sales'] ?? 0);
    $filter_fu = trim($_POST['filter_fu'] ?? '');
    $search_keyword = trim($_POST['search'] ?? '');
    $max_limit = isset($_POST['max_limit']) ? max(1, min(500, (int)$_POST['max_limit'])) : 100;

    $sql_where_conditions = ["c.deleted_at IS NULL"];
    $params = [];
    $types = '';

    $active_sales_filter = 0;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'sales') {
        $active_sales_filter = (int)$_SESSION['user_id'];
        $sql_where_conditions[] = "c.sales_id = ?";
        $params[] = $active_sales_filter;
        $types .= 'i';
    } elseif ($filter_sales > 0) {
        $active_sales_filter = $filter_sales;
        $sql_where_conditions[] = "c.sales_id = ?";
        $params[] = $active_sales_filter;
        $types .= 'i';
    }

    if (!empty($filter_kota)) {
        $sql_where_conditions[] = "c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND kota LIKE ?)";
        $params[] = "%" . $filter_kota . "%";
        $types .= 's';
    }

    if (!empty($filter_kategori)) {
        $sql_where_conditions[] = "c.kategori = ?";
        $params[] = $filter_kategori;
        $types .= 's';
    }

    if ($filter_fu === 'sudah') {
        if ($active_sales_filter > 0) {
            $sql_where_conditions[] = "c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = ? AND deleted_at IS NULL)";
            $params[] = $active_sales_filter;
            $types .= 'i';
        } else {
            $sql_where_conditions[] = "c.id IN (SELECT DISTINCT customer_id FROM follow_ups WHERE deleted_at IS NULL)";
        }
    } elseif ($filter_fu === 'belum') {
        if ($active_sales_filter > 0) {
            $sql_where_conditions[] = "c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE sales_id = ? AND deleted_at IS NULL)";
            $params[] = $active_sales_filter;
            $types .= 'i';
        } else {
            $sql_where_conditions[] = "c.id NOT IN (SELECT DISTINCT customer_id FROM follow_ups WHERE deleted_at IS NULL)";
        }
    }

    if (!empty($search_keyword)) {
        $clean_kw = trim(preg_replace('/[\-_,]+/u', ' ', $search_keyword));
        $words = array_values(array_filter(explode(' ', $clean_kw), function($w) {
            return mb_strlen(trim($w)) >= 2;
        }));

        if (count($words) > 1) {
            $word_conditions = [];
            foreach ($words as $word) {
                $word_like = '%' . $word . '%';
                $word_conditions[] = "(
                    c.nama_toko LIKE ? 
                    OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?))
                    OR c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND (alamat LIKE ? OR kota LIKE ?))
                )";
                array_push($params, $word_like, $word_like, $word_like, $word_like, $word_like);
                $types .= 'sssss';
            }
            $sql_where_conditions[] = "(" . implode(' AND ', $word_conditions) . ")";
        } else {
            $like_kw = '%' . $search_keyword . '%';
            $sql_where_conditions[] = "(
                c.nama_toko LIKE ? 
                OR c.id IN (SELECT customer_id FROM customer_pics WHERE deleted_at IS NULL AND (nama_pic LIKE ? OR tlp_pic LIKE ?))
                OR c.id IN (SELECT customer_id FROM customer_addresses WHERE deleted_at IS NULL AND (alamat LIKE ? OR kota LIKE ?))
            )";
            array_push($params, $like_kw, $like_kw, $like_kw, $like_kw, $like_kw);
            $types .= 'sssss';
        }
    }

    $sql_where_conditions[] = "c.id IN (SELECT DISTINCT customer_id FROM customer_pics WHERE deleted_at IS NULL AND tlp_pic IS NOT NULL AND TRIM(tlp_pic) != '')";

    $where_clause = "WHERE " . implode(' AND ', $sql_where_conditions);

    $sql = "
        SELECT 
            c.id, c.nama_toko, c.kategori,
            COALESCE(s.nama_lengkap, 'Sales Loewix') AS nama_sales,
            (SELECT GROUP_CONCAT(DISTINCT cp.nama_pic ORDER BY cp.id SEPARATOR '||') FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL) AS all_pics,
            (SELECT GROUP_CONCAT(DISTINCT cp.tlp_pic ORDER BY cp.id SEPARATOR '||') FROM customer_pics cp WHERE cp.customer_id = c.id AND cp.deleted_at IS NULL) AS all_phones,
            (SELECT GROUP_CONCAT(DISTINCT ca.kota ORDER BY ca.id SEPARATOR ', ') FROM customer_addresses ca WHERE ca.customer_id = c.id AND ca.deleted_at IS NULL) AS all_cities
        FROM 
            customers c
        LEFT JOIN 
            sales s ON c.sales_id = s.id
        {$where_clause}
        ORDER BY 
            c.id DESC
        LIMIT ?
    ";

    $params[] = $max_limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $targets = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pics = !empty($row['all_pics']) ? explode('||', $row['all_pics']) : [];
            $phones = !empty($row['all_phones']) ? explode('||', $row['all_phones']) : [];

            $validPhone = '';
            $validPic = '';
            foreach ($phones as $idx => $ph) {
                $cleaned = preg_replace('/[^0-9]/', '', $ph);
                if (strlen($cleaned) >= 9) {
                    $validPhone = $ph;
                    $validPic = $pics[$idx] ?? '';
                    break;
                }
            }

            if (!empty($validPhone)) {
                $targets[] = [
                    'id' => (int)$row['id'],
                    'nama_toko' => trim($row['nama_toko']),
                    'pic' => trim($validPic),
                    'phone' => trim($validPhone),
                    'city' => trim($row['all_cities'] ?? '-'),
                    'sales' => trim($row['nama_sales'] ?? 'Sales Loewix')
                ];
            }
        }
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'total' => count($targets),
        'targets' => $targets
    ]);
    exit();
}

// 4. LOG CLICK FOLLOW-UP (Untuk Mode Asisten 1-Klik / Direct WhatsApp)
if ($action === 'log_click_followup') {
    $customerId  = (int)($_POST['customer_id'] ?? 0);
    $rawPhone    = trim($_POST['phone'] ?? '');
    $message     = trim($_POST['message'] ?? '');
    $customerName= trim($_POST['customer_name'] ?? '');

    if ($customerId > 0) {
        $tglFu = date('Y-m-d H:i:s');
        $respon = 'Broadcast WhatsApp (1-Klik)';
        $keterangan = "Pesan Broadcast Terkirim ke {$rawPhone} ({$customerName}):\n" . $message;

        $stmtFu = $conn->prepare("INSERT INTO follow_ups (customer_id, sales_id, tgl_follow_up, respon, keterangan) VALUES (?, ?, ?, ?, ?)");
        if ($stmtFu) {
            $stmtFu->bind_param("iisss", $customerId, $activeSalesId, $tglFu, $respon, $keterangan);
            $stmtFu->execute();
            $stmtFu->close();
        }
    }
    echo json_encode(['success' => true, 'message' => 'Follow up tercatat']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
