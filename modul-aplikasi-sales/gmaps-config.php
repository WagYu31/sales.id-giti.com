<?php
/**
 * Google Maps Scraping — Konfigurasi & Rate Limiter
 * 
 * Menyimpan API Key, batas request, dan fungsi rate limiting.
 * Counter disimpan di file JSON untuk persistensi lintas request.
 */

// ══════════════════════════════════════════════════
// KONFIGURASI — UBAH DI SINI
// ══════════════════════════════════════════════════
// Foursquare V2 API (GRATIS - 100.000 request/bulan, tanpa kartu kredit)
define('FSQ_CLIENT_ID',     'MFHK512CLBUAUI4EEDTONWBH3TU1PALWBXGYQM325Y5L412W');
define('FSQ_CLIENT_SECRET', 'B3UFRKWQGUJ51AHGIZL0ASVLP2A3KFHNXO4OGRISMU1YCJB1');

// Batas penggunaan — proteksi internal
define('GMAPS_DAILY_LIMIT',   50);    // Max request per hari
define('GMAPS_MONTHLY_LIMIT', 1000);  // Max request per bulan

// File counter
define('GMAPS_USAGE_FILE', __DIR__ . '/gmaps_usage.json');

// ══════════════════════════════════════════════════
// FUNGSI RATE LIMITER (DATABASE + FILE FALLBACK)
// ══════════════════════════════════════════════════

/**
 * Ensure DB table for usage tracking exists
 */
function gmaps_ensure_table() {
    global $conn;
    if (!$conn) return;
    $sql = "CREATE TABLE IF NOT EXISTS `gmaps_usage_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `req_date` DATE NOT NULL,
        `req_month` VARCHAR(7) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (`req_date`),
        INDEX (`req_month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    @$conn->query($sql);
}

/**
 * Baca data usage dari DB (dengan JSON fallback)
 */
function gmaps_read_usage() {
    global $conn;
    date_default_timezone_set('Asia/Jakarta');
    $today = date('Y-m-d');
    $month = date('Y-m');

    gmaps_ensure_table();

    if ($conn) {
        $daily = 0;
        $monthly = 0;
        $total = 0;

        $resD = @$conn->query("SELECT COUNT(*) as cnt FROM gmaps_usage_log WHERE req_date = '$today'");
        if ($resD && $rowD = $resD->fetch_assoc()) $daily = intval($rowD['cnt']);

        $resM = @$conn->query("SELECT COUNT(*) as cnt FROM gmaps_usage_log WHERE req_month = '$month'");
        if ($resM && $rowM = $resM->fetch_assoc()) $monthly = intval($rowM['cnt']);

        $resT = @$conn->query("SELECT COUNT(*) as cnt FROM gmaps_usage_log");
        if ($resT && $rowT = $resT->fetch_assoc()) $total = intval($rowT['cnt']);

        return [
            'daily'   => [$today => $daily],
            'monthly' => [$month => $monthly],
            'total'   => $total
        ];
    }

    if (!file_exists(GMAPS_USAGE_FILE)) {
        return ['daily' => [], 'monthly' => [], 'total' => 0];
    }
    $data = json_decode(@file_get_contents(GMAPS_USAGE_FILE), true);
    return $data ?: ['daily' => [], 'monthly' => [], 'total' => 0];
}

/**
 * Simpan data usage
 */
function gmaps_write_usage($data) {
    @file_put_contents(GMAPS_USAGE_FILE, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Cek apakah masih bisa request (belum melebihi limit)
 */
function gmaps_check_limit() {
    $usage = gmaps_read_usage();
    $today = date('Y-m-d');
    $month = date('Y-m');

    $dailyUsed   = $usage['daily'][$today] ?? 0;
    $monthlyUsed = $usage['monthly'][$month] ?? 0;

    if ($dailyUsed >= GMAPS_DAILY_LIMIT) {
        return [
            'allowed'      => false,
            'reason'       => 'Batas harian tercapai (' . GMAPS_DAILY_LIMIT . ' request/hari). Coba lagi besok.',
            'daily_used'   => $dailyUsed,
            'daily_limit'  => GMAPS_DAILY_LIMIT,
            'monthly_used' => $monthlyUsed,
            'monthly_limit'=> GMAPS_MONTHLY_LIMIT,
        ];
    }

    if ($monthlyUsed >= GMAPS_MONTHLY_LIMIT) {
        return [
            'allowed'      => false,
            'reason'       => 'Batas bulanan tercapai (' . GMAPS_MONTHLY_LIMIT . ' request/bulan). Tunggu bulan depan atau hubungi developer.',
            'daily_used'   => $dailyUsed,
            'daily_limit'  => GMAPS_DAILY_LIMIT,
            'monthly_used' => $monthlyUsed,
            'monthly_limit'=> GMAPS_MONTHLY_LIMIT,
        ];
    }

    return [
        'allowed'      => true,
        'reason'       => 'OK',
        'daily_used'   => $dailyUsed,
        'daily_limit'  => GMAPS_DAILY_LIMIT,
        'monthly_used' => $monthlyUsed,
        'monthly_limit'=> GMAPS_MONTHLY_LIMIT,
    ];
}

/**
 * Tambah 1 ke counter setelah request berhasil
 */
function gmaps_increment_usage() {
    global $conn;
    date_default_timezone_set('Asia/Jakarta');
    $today = date('Y-m-d');
    $month = date('Y-m');

    gmaps_ensure_table();
    if ($conn) {
        @$conn->query("INSERT INTO gmaps_usage_log (req_date, req_month) VALUES ('$today', '$month')");
    }

    $usage = gmaps_read_usage();
    gmaps_write_usage($usage);
}

/**
 * Get current usage stats (untuk ditampilkan di frontend)
 */
function gmaps_get_stats() {
    $usage = gmaps_read_usage();
    date_default_timezone_set('Asia/Jakarta');
    $today = date('Y-m-d');
    $month = date('Y-m');

    $dUsed = $usage['daily'][$today] ?? 0;
    $mUsed = $usage['monthly'][$month] ?? 0;

    return [
        'daily_used'        => $dUsed,
        'daily_limit'       => GMAPS_DAILY_LIMIT,
        'daily_remaining'   => max(0, GMAPS_DAILY_LIMIT - $dUsed),
        'monthly_used'      => $mUsed,
        'monthly_limit'     => GMAPS_MONTHLY_LIMIT,
        'monthly_remaining' => max(0, GMAPS_MONTHLY_LIMIT - $mUsed),
        'total_all_time'    => $usage['total'] ?? 0,
    ];
}
?>
