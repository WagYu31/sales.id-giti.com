<?php
// customer_bulk_upload_process_sales.php
require '../vendor/autoload.php';
require_once 'includes/db.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sales' || !isset($_SESSION['user_id'])) {
    die("Akses ditolak.");
}

if (isset($_FILES['customer_file']) && $_FILES['customer_file']['error'] == 0) {
    
    $file_tmp_path = $_FILES['customer_file']['tmp_name'];
    
    $imported_count = 0;
    $skipped_rows = [];
    $uploader_sales_id = $_SESSION['user_id']; // Langsung ambil ID sales dari sesi

    try {
        $spreadsheet = IOFactory::load($file_tmp_path);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        $conn->begin_transaction();
        
        array_shift($rows); // Lewati baris header

        foreach ($rows as $index => $row) {
            $row_num = $index + 2;

            // Template untuk sales tidak punya kolom sales_name
            $nama_toko = trim($row[0] ?? '');
            $kategori = trim($row[1] ?? '');
            $nama_pic = trim($row[2] ?? 'unknown');
            $tlp_pic = trim($row[3] ?? '');
            $alamat = trim($row[4] ?? '');
            $kota = trim($row[5] ?? '');
            $provinsi = trim($row[6] ?? '');

            if (empty($nama_toko)) {
                continue;
            }
            
            if (empty($nama_pic) || empty($tlp_pic) || empty($alamat)) {
                $skipped_rows[] = "Baris {$row_num} ({$nama_toko}) dilewati: Data PIC, telepon, atau alamat tidak lengkap.";
                continue;
            }

            // Proses validasi nomor telepon yang lebih baik
            $cleaned_tlp = preg_replace('/[^0-9]/', '', $tlp_pic);
            if (substr($cleaned_tlp, 0, 2) === '62') {
                $processed_tlp = '0' . substr($cleaned_tlp, 2);
            } elseif (substr($cleaned_tlp, 0, 1) !== '0') {
                $processed_tlp = '0' . $cleaned_tlp;
            } else {
                $processed_tlp = $cleaned_tlp;
            }

            if (empty($processed_tlp)) {
                $skipped_rows[] = "Baris {$row_num} ({$nama_toko}) dilewati: Format nomor telepon tidak valid.";
                continue;
            }

            $stmt_check_phone = $conn->prepare("SELECT id FROM customer_pics WHERE tlp_pic = ? AND deleted_at IS NULL");
            $stmt_check_phone->bind_param("s", $processed_tlp);
            $stmt_check_phone->execute();
            if ($stmt_check_phone->get_result()->num_rows > 0) {
                $skipped_rows[] = "Baris {$row_num} ({$nama_toko}) dilewati: Nomor telepon '{$tlp_pic}' sudah terdaftar.";
                $stmt_check_phone->close();
                continue;
            }
            $stmt_check_phone->close();
            
            // Proses Insert dengan sales_id dari sesi
            $stmt_customer = $conn->prepare("INSERT INTO customers (sales_id, tgl_input, nama_toko, kategori) VALUES (?, ?, ?, ?)");
            $tgl_input = date('Y-m-d');
            $stmt_customer->bind_param("isss", $uploader_sales_id, $tgl_input, $nama_toko, $kategori);
            $stmt_customer->execute();
            $customer_id = $conn->insert_id;
            $stmt_customer->close();

            $stmt_pic = $conn->prepare("INSERT INTO customer_pics (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");
            $stmt_pic->bind_param("iss", $customer_id, $nama_pic, $processed_tlp);
            $stmt_pic->execute();
            $stmt_pic->close();

            if (!empty($alamat)) {
                $stmt_address = $conn->prepare("INSERT INTO customer_addresses (customer_id, alamat, kota, provinsi) VALUES (?, ?, ?, ?)");
                $stmt_address->bind_param("isss", $customer_id, $alamat, $kota, $provinsi);
                $stmt_address->execute();
                $stmt_address->close();
            }
            
            $imported_count++;
        }

        $conn->commit();

        $_SESSION['upload_status'] = 'success';
        $_SESSION['flash_message'] = "Proses Selesai: {$imported_count} data customer berhasil diimpor.";
        
        if (!empty($skipped_rows)) {
            $skipped_message = "<strong>Data berikut dilewati dan tidak diimpor:</strong><br><ul class=\"text-start\">";
            foreach ($skipped_rows as $msg) {
                $skipped_message .= "<li>" . htmlspecialchars($msg) . "</li>";
            }
            $skipped_message .= "</ul>";
            $_SESSION['flash_message_info'] = $skipped_message;
        }

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['upload_status'] = 'error';
        $_SESSION['flash_message_error'] = "Gagal mengimpor: " . $e->getMessage();
    }
} else {
    $_SESSION['upload_status'] = 'error';
    $_SESSION['flash_message_error'] = "Gagal mengunggah file. Silakan coba lagi.";
}

header("Location: customer_io_sales.php");
exit();