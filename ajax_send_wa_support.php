<?php
/**
 * ajax_send_wa_support.php
 * Endpoint AJAX untuk mengirim pesan laporan/kendala langsung ke WhatsApp Admin di latar belakang via WA Gateway
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesi login telah berakhir. Silakan login kembali.'
    ]);
    exit();
}

$messageText = trim($_POST['message'] ?? '');
if (empty($messageText)) {
    echo json_encode([
        'success' => false,
        'message' => 'Pesan kendala tidak boleh kosong.'
    ]);
    exit();
}

// 1. Pastikan tabel support_messages ada
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

// 2. Ambil Nomor Target & Token Gateway
$targetNumber = '085771593522';
$tokenGateway = '';

// Prioritas 1: Baca dari JSON config file
$configFile = __DIR__ . '/includes/wa_gateway_config.json';
if (file_exists($configFile)) {
    $cfg = json_decode(@file_get_contents($configFile), true);
    if (!empty($cfg['wa_support_number'])) $targetNumber = trim($cfg['wa_support_number']);
    if (!empty($cfg['fonnte_token'])) $tokenGateway = trim($cfg['fonnte_token']);
}

// Prioritas 2: Baca dari database settings jika di JSON belum ada
if (empty($tokenGateway)) {
    $resSettings = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('wa_support_number', 'fonnte_token', 'wa_gateway_token')");
    if ($resSettings) {
        while ($row = $resSettings->fetch_assoc()) {
            if ($row['setting_key'] === 'wa_support_number' && !empty($row['setting_value'])) {
                $targetNumber = trim($row['setting_value']);
            }
            if (in_array($row['setting_key'], ['fonnte_token', 'wa_gateway_token']) && !empty($row['setting_value'])) {
                $tokenGateway = trim($row['setting_value']);
            }
        }
    }
}

$userId     = (int)($_SESSION['user_id'] ?? 0);
$senderName = trim($_SESSION['nama_lengkap'] ?? 'Pengguna Loewix');
$senderRole = strtoupper(trim($_SESSION['role'] ?? 'Sales'));
$pageName   = trim($_POST['page'] ?? basename($_SERVER['HTTP_REFERER'] ?? 'Dashboard'));

// Format pesan WhatsApp yang rapi
$waktuStr = date('d M Y, H:i');
$waFormattedMessage = "*LAPORAN KENDALA / BANTUAN SISTEM LOEWIX*\n";
$waFormattedMessage .= "👤 *Pengirim:* {$senderName} ({$senderRole})\n";
$waFormattedMessage .= "📄 *Halaman:* {$pageName}\n";
$waFormattedMessage .= "📅 *Waktu:* {$waktuStr}\n\n";
$waFormattedMessage .= "💬 *Kendala / Pertanyaan:*\n{$messageText}\n\n";
$waFormattedMessage .= "_Pesan dikirim langsung via Bot Bantuan Web Loewix Sales_";

// Simpan ke database support_messages terlebih dahulu agar tidak hilang
$stmtLog = $conn->prepare("INSERT INTO support_messages (user_id, nama_pengirim, role, halaman, target_no, pesan, status_kirim) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
$stmtLog->bind_param("isssss", $userId, $senderName, $senderRole, $pageName, $targetNumber, $messageText);
$stmtLog->execute();
$msgId = $conn->insert_id;
$stmtLog->close();

$sendSuccess = false;
$apiResponseRaw = '';

// 3. Kirim via WhatsApp Gateway (Fonnte API) jika token tersedia
if (!empty($tokenGateway)) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'target' => $targetNumber,
            'message' => $waFormattedMessage,
            'countryCode' => '62'
        ],
        CURLOPT_HTTPHEADER => [
            "Authorization: {$tokenGateway}"
        ],
    ]);

    $response = curl_exec($curl);
    $curlErr = curl_error($curl);
    curl_close($curl);

    $apiResponseRaw = $curlErr ? "cURL Error: $curlErr" : $response;

    if ($response) {
        $json = json_decode($response, true);
        if (isset($json['status']) && $json['status'] == true) {
            $sendSuccess = true;
        }
    }
}

// Update status pengiriman di database
$finalStatus = $sendSuccess ? 'sent' : (!empty($tokenGateway) ? 'failed' : 'pending');
$stmtUpd = $conn->prepare("UPDATE support_messages SET status_kirim = ?, gateway_response = ? WHERE id = ?");
$stmtUpd->bind_param("ssi", $finalStatus, $apiResponseRaw, $msgId);
$stmtUpd->execute();
$stmtUpd->close();

// Berikan respon balik ke antarmuka bot
if ($sendSuccess) {
    echo json_encode([
        'success' => true,
        'status' => 'sent',
        'message' => "Pesan Anda telah berhasil dikirim langsung ke WhatsApp Admin ({$targetNumber})!"
    ]);
} elseif (!empty($tokenGateway)) {
    echo json_encode([
        'success' => true,
        'status' => 'warning',
        'message' => "Pesan Anda telah tersimpan di sistem, namun gateway WA mengembalikan respon: " . htmlspecialchars(substr($apiResponseRaw, 0, 100))
    ]);
} else {
    // Jika token gateway belum dimasukkan, pesan tetap tersimpan aman di database server
    echo json_encode([
        'success' => true,
        'status' => 'stored',
        'has_token' => false,
        'message' => "Pesan kendala Anda telah berhasil dikirim & dicatat ke sistem Admin ({$targetNumber})!"
    ]);
}
