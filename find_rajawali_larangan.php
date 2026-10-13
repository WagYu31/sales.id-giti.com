<?php
/**
 * find_rajawali_larangan.php
 * Script pencarian detail data toko Rajawali & Larangan di seluruh database
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================================\n";
echo "   PENCARIAN DATA TOKO: RAJAWALI / LARANGAN DI SELURUH DATABASE   \n";
echo "=================================================================\n\n";

// 1. Cari di tabel customers (nama_toko)
echo "--- 1. Tabel CUSTOMERS (nama_toko) ---\n";
$q1 = $conn->query("
    SELECT c.id, c.nama_toko, c.kategori, c.sales_id, s.nama_lengkap as nama_sales, c.deleted_at
    FROM customers c
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE (LOWER(c.nama_toko) LIKE '%rajawali%' OR LOWER(c.nama_toko) LIKE '%larangan%')
");
if ($q1 && $q1->num_rows > 0) {
    while ($r = $q1->fetch_assoc()) {
        $del = $r['deleted_at'] ? " [TERHAPUS: {$r['deleted_at']}]" : "";
        echo "ID: #{$r['id']} | Toko: '{$r['nama_toko']}' | Kategori: {$r['kategori']} | Sales: {$r['nama_sales']} (ID: {$r['sales_id']}){$del}\n";
        
        // Ambil alamatnya
        $qa = $conn->query("SELECT alamat, kota, provinsi FROM customer_addresses WHERE customer_id = {$r['id']} LIMIT 1");
        if ($qa && $rowA = $qa->fetch_assoc()) {
            echo "   -> Alamat: {$rowA['alamat']} | Kota: {$rowA['kota']}\n";
        }
        
        // Ambil PIC
        $qp = $conn->query("SELECT nama_pic, tlp_pic FROM customer_pics WHERE customer_id = {$r['id']} LIMIT 1");
        if ($qp && $rowP = $qp->fetch_assoc()) {
            echo "   -> PIC: {$rowP['nama_pic']} ({$rowP['tlp_pic']})\n";
        }
        echo "\n";
    }
} else {
    echo "Tidak ditemukan nama toko dengan kata 'rajawali' atau 'larangan'.\n\n";
}

// 2. Cari di tabel customer_addresses (alamat / kota)
echo "--- 2. Tabel CUSTOMER_ADDRESSES (alamat / kota mengandung 'larangan') ---\n";
$q2 = $conn->query("
    SELECT ca.id as addr_id, ca.customer_id, ca.alamat, ca.kota, ca.provinsi, c.nama_toko, c.sales_id, s.nama_lengkap as nama_sales
    FROM customer_addresses ca
    JOIN customers c ON ca.customer_id = c.id
    LEFT JOIN sales s ON c.sales_id = s.id
    WHERE (LOWER(ca.alamat) LIKE '%larangan%' OR LOWER(ca.kota) LIKE '%larangan%')
");
if ($q2 && $q2->num_rows > 0) {
    while ($r = $q2->fetch_assoc()) {
        echo "Customer ID: #{$r['customer_id']} | Toko: '{$r['nama_toko']}' | Sales: {$r['nama_sales']} (ID: {$r['sales_id']})\n";
        echo "   -> Alamat: {$r['alamat']}\n";
        echo "   -> Kota: {$r['kota']} | Provinsi: {$r['provinsi']}\n\n";
    }
} else {
    echo "Tidak ada alamat yang mengandung kata 'larangan'.\n\n";
}

// 3. Cari di tabel sales_customer (aplikasi mobile / canvas)
echo "--- 3. Tabel SALES_CUSTOMER (Mobile / Canvas) ---\n";
$q3 = $conn->query("
    SELECT id, kode_customer, nama, kategori, alamat, kota, telp_pribadi
    FROM sales_customer
    WHERE (LOWER(nama) LIKE '%rajawali%' OR LOWER(nama) LIKE '%larangan%' OR LOWER(alamat) LIKE '%larangan%')
");
if ($q3 && $q3->num_rows > 0) {
    while ($r = $q3->fetch_assoc()) {
        echo "ID: #{$r['id']} | Kode: {$r['kode_customer']} | Nama: '{$r['nama']}' | Kategori: {$r['kategori']}\n";
        echo "   -> Alamat: {$r['alamat']} | Kota: {$r['kota']} | Telp: {$r['telp_pribadi']}\n\n";
    }
} else {
    echo "Tidak ada data sales_customer dengan kata tersebut.\n\n";
}

echo "Selesai.\n";
