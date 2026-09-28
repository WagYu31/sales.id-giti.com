<?php
/**
 * sales_order_print.php
 * Format Cetak Resmi Dokumen Pesanan Penjualan (Sales Order)
 * Siap cetak A4 / PDF dengan kop resmi PT. Giti / Loewix
 */

require_once 'includes/db.php';
require_once 'includes/sales_order_helper.php';
ensureSalesOrderTables($conn);

$soId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($soId <= 0) {
    die("ID Pesanan tidak valid.");
}

$stmt = $conn->prepare("SELECT * FROM sales_orders WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $soId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Pesanan Penjualan tidak ditemukan atau sudah dihapus.");
}

// Fetch Items
$stmtItems = $conn->prepare("SELECT * FROM sales_order_items WHERE sales_order_id = ? ORDER BY id ASC");
$stmtItems->bind_param("i", $soId);
$stmtItems->execute();
$itemsRes = $stmtItems->get_result();
$items = [];
while ($row = $itemsRes->fetch_assoc()) {
    $items[] = $row;
}
$stmtItems->close();

// Fungsi Konversi Angka ke Terbilang Bahasa Indonesia
function penyebut($nilai) {
    $nilai = abs($nilai);
    $huruf = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    $temp = "";
    if ($nilai < 12) {
        $temp = " ". $huruf[$nilai];
    } else if ($nilai <20) {
        $temp = penyebut($nilai - 10). " Belas";
    } else if ($nilai < 100) {
        $temp = penyebut($nilai/10)." Puluh". penyebut($nilai % 10);
    } else if ($nilai < 200) {
        $temp = " Seratus" . penyebut($nilai - 100);
    } else if ($nilai < 1000) {
        $temp = penyebut($nilai/100) . " Ratus" . penyebut($nilai % 100);
    } else if ($nilai < 2000) {
        $temp = " Seribu" . penyebut($nilai - 1000);
    } else if ($nilai < 1000000) {
        $temp = penyebut($nilai/1000) . " Ribu" . penyebut($nilai % 1000);
    } else if ($nilai < 1000000000) {
        $temp = penyebut($nilai/1000000) . " Juta" . penyebut($nilai % 1000000);
    } else if ($nilai < 1000000000000) {
        $temp = penyebut($nilai/1000000000) . " Milyar" . penyebut(fmod($nilai,1000000000));
    } else if ($nilai < 1000000000000000) {
        $temp = penyebut($nilai/1000000000000) . " Trilyun" . penyebut(fmod($nilai,1000000000000));
    }     
    return $temp;
}

function terbilang($nilai) {
    if ($nilai < 0) {
        $hasil = "Minus ". trim(penyebut($nilai));
    } else {
        $hasil = trim(penyebut($nilai));
    }     
    return $hasil . " Rupiah";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order - <?php echo htmlspecialchars($order['so_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #F1F5F9;
            color: #0F172A;
            margin: 0;
            padding: 20px 0;
        }

        .print-toolbar {
            max-width: 820px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .invoice-paper {
            max-width: 820px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 40px 48px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            box-sizing: border-box;
        }

        .company-title {
            font-size: 22px;
            font-weight: 800;
            color: #0F172A;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .company-subtitle {
            font-size: 11.5px;
            color: #64748B;
            line-height: 1.4;
        }

        .doc-title-badge {
            text-align: right;
        }

        .doc-name {
            font-size: 24px;
            font-weight: 800;
            color: #1D4ED8;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }

        .doc-sub {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .info-grid {
            margin-top: 24px;
            border-top: 2px solid #E2E8F0;
            border-bottom: 2px solid #E2E8F0;
            padding: 16px 0;
        }

        .meta-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }

        .meta-val {
            font-size: 13px;
            font-weight: 700;
            color: #0F172A;
        }

        .meta-val.code {
            font-family: 'JetBrains Mono', monospace;
            color: #1D4ED8;
            font-size: 14px;
        }

        /* Invoice Table */
        .table-invoice {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        .table-invoice thead th {
            background: #F8FAFC;
            color: #334155;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 10px 10px;
            border-top: 1.5px solid #CBD5E1;
            border-bottom: 1.5px solid #CBD5E1;
        }

        .table-invoice tbody td {
            padding: 10px 10px;
            font-size: 12.5px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: top;
        }

        .table-invoice tbody tr:last-child td {
            border-bottom: 1.5px solid #CBD5E1;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .summary-box {
            margin-top: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12.5px;
        }

        .grand-total-row {
            border-top: 2px solid #0F172A;
            padding-top: 8px;
            margin-top: 6px;
            font-size: 16px;
            font-weight: 800;
            color: #0F172A;
        }

        .terbilang-box {
            background: #F8FAFC;
            border-left: 3px solid #1D4ED8;
            padding: 10px 14px;
            font-size: 12px;
            font-style: italic;
            color: #334155;
            border-radius: 0 6px 6px 0;
            margin-top: 12px;
        }

        /* Signatures */
        .signature-grid {
            margin-top: 36px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            text-align: center;
        }

        .sign-box {
            border-top: 1px solid #CBD5E1;
            padding-top: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-top: 65px;
        }

        .sign-title {
            font-size: 11.5px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
        }

        @media print {
            body {
                background: #FFFFFF;
                padding: 0;
            }
            .print-toolbar {
                display: none !important;
            }
            .invoice-paper {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Print Action Bar (Hidden on print) -->
    <div class="print-toolbar">
        <div class="d-flex align-items-center gap-2">
            <a href="sales_orders.php" class="btn btn-outline-secondary btn-sm fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Daftar SO
            </a>
            <a href="sales_order_form.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary btn-sm fw-bold">
                <i class="bi bi-pencil me-1"></i> Edit Pesanan
            </a>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm">
                <i class="bi bi-printer me-1"></i> Cetak Dokumen (Print / PDF)
            </button>
        </div>
    </div>

    <!-- Main Printable Invoice Paper -->
    <div class="invoice-paper">
        
        <!-- Header: Logo & Company vs Document Title -->
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div style="background:#1D4ED8; color:#fff; width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:18px;">
                        L
                    </div>
                    <span class="company-title">LOEWIX CCTV</span>
                </div>
                <div class="company-subtitle">
                    <strong>PT. GITI CCTV INDONESIA</strong><br>
                    Official Distributor of Loewix Surveillance &amp; Security Systems<br>
                    Web: https://sales.id-giti.com &bull; Telp / WA: 0812-3456-7890
                </div>
            </div>
            <div class="doc-title-badge">
                <div class="doc-name">SALES ORDER</div>
                <div class="doc-sub">PESANAN PENJUALAN</div>
                <div class="meta-val code mt-1"><?php echo htmlspecialchars($order['so_number']); ?></div>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="info-grid">
            <div class="row g-3">
                <div class="col-6">
                    <div class="meta-label">Pemesan / Customer (Toko)</div>
                    <div class="meta-val" style="font-size:15px; color:#1D4ED8;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                    <div style="font-size:12px; color:#475569; margin-top:2px;">
                        <?php if (!empty($order['customer_address'])): ?>
                            <?php echo nl2br(htmlspecialchars($order['customer_address'])); ?><br>
                        <?php endif; ?>
                        <strong>PIC:</strong> <?php echo htmlspecialchars($order['customer_pic'] ?: '-'); ?> &bull; 
                        <strong>Telp:</strong> <?php echo htmlspecialchars($order['customer_phone'] ?: '-'); ?>
                    </div>
                </div>

                <div class="col-6">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="meta-label">Tanggal Pesanan</div>
                            <div class="meta-val"><?php echo date('d F Y', strtotime($order['so_date'])); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">Syarat Pembayaran</div>
                            <div class="meta-val"><?php echo htmlspecialchars($order['payment_terms']); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">Sales Representative</div>
                            <div class="meta-val"><?php echo htmlspecialchars($order['sales_name'] ?: '-'); ?></div>
                        </div>
                        <div class="col-6">
                            <div class="meta-label">No. PO Customer</div>
                            <div class="meta-val"><?php echo htmlspecialchars($order['po_number'] ?: '-'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Optional Shipping Info -->
        <?php if (!empty($order['shipping_method']) || !empty($order['shipping_date'])): ?>
            <div class="d-flex justify-content-between p-2 mt-2 bg-light rounded" style="font-size:11.5px; color:#475569;">
                <div>
                    <strong>Pengiriman:</strong> <?php echo htmlspecialchars($order['shipping_method'] ?: 'Ambil Sendiri / Standar'); ?>
                </div>
                <div>
                    <strong>Tgl Kirim:</strong> <?php echo !empty($order['shipping_date']) ? date('d/m/Y', strtotime($order['shipping_date'])) : '-'; ?>
                </div>
                <div>
                    <strong>Cabang / Gudang:</strong> <?php echo htmlspecialchars($order['branch'] ?: 'Kantor Pusat'); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Items Table -->
        <table class="table-invoice">
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">No.</th>
                    <th style="width: 38%;">Nama Barang &amp; Deskripsi</th>
                    <th style="width: 15%;">Kode #</th>
                    <th style="width: 8%; text-align: center;">Qty</th>
                    <th style="width: 8%; text-align: center;">Satuan</th>
                    <th style="width: 15%; text-align: right;">Harga (Rp)</th>
                    <th style="width: 16%; text-align: right;">Total (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($items as $item): 
                ?>
                    <tr>
                        <td style="text-align: center; color: #64748B;"><?php echo $no++; ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <?php if (!empty($item['item_description']) && $item['item_description'] !== $item['item_name']): ?>
                                <div style="font-size: 11px; color: #64748B;"><?php echo htmlspecialchars($item['item_description']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="font-mono" style="font-size: 11.5px; color: #475569;">
                            <?php echo htmlspecialchars($item['item_code']); ?>
                        </td>
                        <td style="text-align: center; font-weight: 700;">
                            <?php echo (int)$item['qty']; ?>
                        </td>
                        <td style="text-align: center; color: #64748B; font-size: 11.5px;">
                            <?php echo htmlspecialchars($item['unit'] ?: 'PCS'); ?>
                        </td>
                        <td style="text-align: right;" class="font-mono">
                            <?php echo number_format($item['unit_price'], 0, ',', '.'); ?>
                            <?php if ($item['discount_item'] > 0): ?>
                                <div style="font-size: 10px; color: #EF4444;">-<?php echo number_format($item['discount_item'], 0, ',', '.'); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: 700;" class="font-mono text-dark">
                            <?php echo number_format($item['total_price'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary & Terbilang -->
        <div class="row summary-box">
            <div class="col-7">
                <div class="terbilang-box">
                    <strong>Terbilang:</strong><br>
                    <?php echo terbilang($order['grand_total']); ?>
                </div>

                <?php if (!empty($order['special_notes'])): ?>
                    <div class="mt-3 p-2 border rounded" style="font-size:11.5px; background:#FAFAFA;">
                        <strong>Catatan Khusus:</strong><br>
                        <?php echo nl2br(htmlspecialchars($order['special_notes'])); ?>
                    </div>
                <?php endif; ?>

                <div class="mt-3" style="font-size:11px; color:#64748B;">
                    <strong>Rekening Pembayaran:</strong><br>
                    Bank BCA: <strong>123-456-7890</strong> a/n PT. GITI CCTV INDONESIA<br>
                    Bank Mandiri: <strong>987-654-3210</strong> a/n PT. GITI CCTV INDONESIA
                </div>
            </div>

            <div class="col-5">
                <div class="summary-row">
                    <span class="text-muted">Sub Total:</span>
                    <span class="font-mono fw-bold">Rp <?php echo number_format($order['subtotal'], 0, ',', '.'); ?></span>
                </div>

                <?php if ($order['discount_amount'] > 0): ?>
                    <div class="summary-row text-danger">
                        <span>Diskon (<?php echo $order['discount_type'] === 'percent' ? $order['discount_val'].'%' : 'Potongan'; ?>):</span>
                        <span class="font-mono fw-bold">- Rp <?php echo number_format($order['discount_amount'], 0, ',', '.'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($order['is_taxable']): ?>
                    <div class="summary-row">
                        <span class="text-muted">PPN (11%):</span>
                        <span class="font-mono fw-bold">Rp <?php echo number_format($order['tax_amount'], 0, ',', '.'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="summary-row grand-total-row">
                    <span>GRAND TOTAL:</span>
                    <span class="font-mono text-primary">Rp <?php echo number_format($order['grand_total'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <!-- Signatures Grid -->
        <div class="signature-grid">
            <div>
                <div class="sign-title">Dibuat Oleh (Sales)</div>
                <div class="sign-box"><?php echo htmlspecialchars($order['sales_name'] ?: 'Staff Sales'); ?></div>
            </div>
            <div>
                <div class="sign-title">Disetujui Oleh</div>
                <div class="sign-box">Finance / Manager</div>
            </div>
            <div>
                <div class="sign-title">Customer / Pemesan</div>
                <div class="sign-box"><?php echo htmlspecialchars($order['customer_pic'] ?: $order['customer_name']); ?></div>
            </div>
        </div>

    </div>

</body>
</html>
