<?php
/**
 * Google Maps Scraping — Import ke Database Customer
 * 
 * Import data toko terpilih dari hasil scraping ke tabel sales_customer.
 * Auto-generate kode CUST-XXX, cek duplikat, dan bulk import.
 * 
 * Endpoint: POST import-gmaps-customer.php
 * Body: { places: [ { name, address, phone, ... }, ... ] }
 */

header('Content-Type: application/json');
require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => true, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$places = $input['places'] ?? [];

if (empty($places)) {
    echo json_encode(['error' => true, 'message' => 'Tidak ada data toko yang dipilih']);
    exit;
}

$imported = 0;
$skipped  = 0;
$errors   = [];

foreach ($places as $place) {
    $nama    = trim($place['name'] ?? '');
    $alamat  = trim($place['address'] ?? '');
    $telp    = trim($place['phone'] ?? '');
    $kota    = trim($place['city'] ?? '');
    $lat     = $place['lat'] ?? '';
    $lng     = $place['lng'] ?? '';
    $website = trim($place['website'] ?? '');
    $placeId = trim($place['place_id'] ?? '');

    if (empty($nama)) {
        $errors[] = 'Data tanpa nama — dilewati';
        $skipped++;
        continue;
    }

    // ─── Cek Duplikat (nama + kota) ─────────────────
    $checkStmt = $conn->prepare("SELECT id FROM sales_customer WHERE nama = ? AND kota = ? LIMIT 1");
    $checkStmt->bind_param("ss", $nama, $kota);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $skipped++;
        $checkStmt->close();
        continue;
    }
    $checkStmt->close();

    // ─── Generate Kode Customer ─────────────────────
    $lastKode = mysqli_query($conn, "SELECT kode_customer FROM sales_customer WHERE kode_customer IS NOT NULL ORDER BY id DESC LIMIT 1");
    $lastRow = mysqli_fetch_assoc($lastKode);
    $nextNum = 1;
    if ($lastRow && preg_match('/CUST-(\d+)/', $lastRow['kode_customer'], $m)) {
        $nextNum = intval($m[1]) + 1;
    }
    $kodeCustomer = 'CUST-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

    // ─── Kategori default ───────────────────────────
    $kategori = 'Toko CCTV';

    // ─── Simpan email sebagai website (optional) ────
    $email = '';

    // ─── Insert ─────────────────────────────────────
    $stmt = $conn->prepare(
        "INSERT INTO sales_customer (kode_customer, kategori, nama, telp_pribadi, email, alamat, kota, lat, lon, alamat_lokasi, created_at, updated_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );

    // Alamat lokasi = formatted address dari Google
    $alamatLokasi = $alamat;

    $stmt->bind_param("ssssssssss",
        $kodeCustomer,
        $kategori,
        $nama,
        $telp,
        $email,
        $alamat,
        $kota,
        $lat,
        $lng,
        $alamatLokasi
    );

    if ($stmt->execute()) {
        $imported++;
    } else {
        $errors[] = 'Gagal import "' . $nama . '": ' . $stmt->error;
    }
    $stmt->close();
}

echo json_encode([
    'error'    => false,
    'message'  => "Berhasil import $imported toko" . ($skipped > 0 ? ", $skipped dilewati (duplikat/tanpa nama)" : ""),
    'imported' => $imported,
    'skipped'  => $skipped,
    'errors'   => $errors,
]);
?>
