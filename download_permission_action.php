<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi login berakhir. Silakan login kembali.']);
    exit;
}

// Pastikan tabel download_requests ada
$conn->query("
CREATE TABLE IF NOT EXISTS download_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sales_id INT NOT NULL,
    sales_name VARCHAR(255) NOT NULL,
    customer_ids TEXT NOT NULL,
    jumlah_data INT NOT NULL,
    alasan TEXT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    approved_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$action = $_POST['action'] ?? '';

// 1. Sales Mengirim Permintaan Izin Unduh
if ($action === 'request_download') {
    $sales_id = $_SESSION['user_id'];
    $sales_name = $_SESSION['nama_lengkap'] ?? 'Sales';
    $customer_ids_raw = $_POST['customer_ids'] ?? '';
    $alasan = trim($_POST['alasan'] ?? 'Untuk follow up customer');

    if (empty($customer_ids_raw)) {
        echo json_encode(['success' => false, 'message' => 'Pilih setidaknya satu customer untuk diunduh.']);
        exit;
    }

    $ids_array = array_filter(explode(',', $customer_ids_raw));
    $jumlah_data = count($ids_array);

    if ($jumlah_data === 0) {
        echo json_encode(['success' => false, 'message' => 'Data customer tidak valid.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO download_requests (sales_id, sales_name, customer_ids, jumlah_data, alasan, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param('issis', $sales_id, $sales_name, $customer_ids_raw, $jumlah_data, $alasan);

    if ($stmt->execute()) {
        // Reset cache notifikasi
        unset($_SESSION['notif_cache_time']);
        echo json_encode([
            'success' => true, 
            'message' => "Permintaan izin unduh {$jumlah_data} data customer berhasil dikirim ke Superadmin! Harap tunggu persetujuan."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengirim permintaan: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// 3. Sales Mengirim Permintaan Izin Export Bonus Sales Detail
if ($action === 'request_bonus_export') {
    $sales_id = $_SESSION['user_id'];
    $sales_name = $_SESSION['nama_lengkap'] ?? 'Sales';
    $target_sales_id = intval($_POST['target_sales_id'] ?? 0);
    $target_periode = trim($_POST['target_periode'] ?? '8-10');
    $alasan = trim($_POST['alasan'] ?? 'Untuk laporan rincian program 3 bulan');
    $jumlah_data = intval($_POST['jumlah_data'] ?? 0);

    $stmt = $conn->prepare("INSERT INTO download_requests (sales_id, sales_name, customer_ids, jumlah_data, alasan, status, request_type, target_sales_id, target_periode) VALUES (?, ?, '', ?, ?, 'Pending', 'bonus_sales_detail', ?, ?)");
    $stmt->bind_param('isisis', $sales_id, $sales_name, $jumlah_data, $alasan, $target_sales_id, $target_periode);

    if ($stmt->execute()) {
        unset($_SESSION['notif_cache_time']);
        echo json_encode([
            'success' => true, 
            'message' => "Permintaan izin export Excel berhasil dikirim ke Superadmin! Harap tunggu persetujuan."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengirim permintaan: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// 4. Verifikasi Password / PIN Super Admin di Tempat (On-the-spot Authorization)
if ($action === 'verify_superadmin_password') {
    $password = trim($_POST['password'] ?? '');
    $target_sales_id = intval($_POST['target_sales_id'] ?? 0);
    $target_periode = trim($_POST['target_periode'] ?? '8-10');

    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password Super Admin wajib diisi.']);
        exit;
    }

    // Cari akun superadmin yang cocok
    $res_sa = $conn->query("SELECT id, password FROM sales WHERE role = 'superadmin' AND deleted_at IS NULL");
    $match = false;

    if ($res_sa) {
        while ($sa = $res_sa->fetch_assoc()) {
            if (password_verify($password, $sa['password']) || $password === $sa['password']) {
                $match = true;
                break;
            }
        }
    }

    if ($match) {
        $_SESSION['temp_export_auth_' . $target_sales_id] = true;
        echo json_encode([
            'success' => true,
            'message' => 'Otorisasi Super Admin Berhasil! Mengunduh file Excel...',
            'export_url' => "export_bonus_sales_excel.php?sales_id={$target_sales_id}&periode_bulan=" . urlencode($target_periode)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Password Super Admin salah atau tidak valid.']);
    }
    exit;
}

// 5. Superadmin Menyetujui atau Menolak Permintaan Unduh
if (in_array($action, ['approve_request', 'reject_request'])) {
    if ($_SESSION['role'] !== 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Hanya Superadmin yang memiliki wewenang ini.']);
        exit;
    }

    $request_id = intval($_POST['request_id'] ?? 0);
    $new_status = ($action === 'approve_request') ? 'Approved' : 'Rejected';
    $admin_id = $_SESSION['user_id'];

    if ($request_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID permintaan tidak valid.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE download_requests SET status = ?, approved_by = ? WHERE id = ?");
    $stmt->bind_param('sii', $new_status, $admin_id, $request_id);

    if ($stmt->execute()) {
        $msg = ($new_status === 'Approved') ? "Permintaan izin unduh berhasil DISETUJUI!" : "Permintaan izin unduh DITOLAK.";
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memproses permintaan: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
