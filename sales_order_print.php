<?php
/**
 * sales_order_print.php
 * Format Cetak Resmi Dokumen Pesanan Penjualan (Sales Order / Proforma Invoice)
 * Mengadopsi format resmi Accurate Online (Sesuai Dokumen PDF Referensi)
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

// Format Tanggal Bahasa Indonesia (contoh: 01 Okt 2026)
$bulanIndo = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];
$tglTime = strtotime($order['so_date'] ?? 'now');
$tglFormatted = date('d', $tglTime) . ' ' . ($bulanIndo[(int)date('n', $tglTime)] ?? date('M', $tglTime)) . ' ' . date('Y', $tglTime);

// Fungsi Terbilang Rupiah
function penyebut($nilai) {
    $nilai = abs($nilai);
    $huruf = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
    $temp = "";
    if ($nilai < 12) {
        $temp = " ". $huruf[$nilai];
    } else if ($nilai < 20) {
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

$defaultBrand = $_GET['brand'] ?? 'GRAVITTI';
$defaultTitle = $_GET['title'] ?? 'PROFORMA INVOICE';

// Barcode Resmi QRIS BCA (GRAVITTI TECHNOLOGY) sesuai dokumen PDF referensi
$qrisImgPath = __DIR__ . '/assets/images/qris_bca.png';
$qrisBase64 = file_exists($qrisImgPath) ? base64_encode(file_get_contents($qrisImgPath)) : '';
$qrisSrc = !empty($qrisBase64) ? ('data:image/png;base64,' . $qrisBase64) : 'assets/images/qris_bca.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($defaultTitle); ?> - <?php echo htmlspecialchars(!empty($order['so_number']) ? $order['so_number'] : ('ID #' . $order['id'])); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #F1F5F9;
            color: #000000;
            margin: 0;
            padding: 24px 0 40px 0;
            font-size: 11px;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Action Toolbar (Hidden When Printing) */
        .print-toolbar {
            max-width: 820px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0F172A;
            padding: 10px 18px;
            border-radius: 10px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.25);
            color: #FFFFFF;
            gap: 12px;
        }

        .toolbar-left, .toolbar-center, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-tb {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            border: 1px solid transparent;
        }

        .btn-tb-outline {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            color: #F8FAFC;
        }

        .btn-tb-outline:hover {
            background: rgba(255, 255, 255, 0.18);
            color: #FFFFFF;
        }

        .btn-tb-primary {
            background: #2563EB;
            color: #FFFFFF;
            border-color: #1D4ED8;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.4);
        }

        .btn-tb-primary:hover {
            background: #1D4ED8;
            color: #FFFFFF;
        }

        .tb-control-group {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.06);
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .tb-control-group label {
            font-size: 11px;
            font-weight: 600;
            color: #94A3B8;
            margin: 0;
            white-space: nowrap;
        }

        .tb-select {
            background: #1E293B;
            color: #F8FAFC;
            border: 1px solid #475569;
            border-radius: 4px;
            padding: 2.5px 8px;
            font-size: 11.5px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }

        .tb-select:focus {
            border-color: #38BDF8;
        }

        /* Accurate Sheet Paper (A4 Proportion) */
        .invoice-paper {
            width: 820px;
            min-height: 1080px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 36px 44px 50px 44px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
            position: relative;
            box-sizing: border-box;
            border: 1px solid #E2E8F0;
        }

        /* Accurate Header Layout */
        .accurate-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .header-col {
            width: 47%;
        }

        .company-logo-text {
            font-size: 24px;
            font-weight: 800;
            color: #000000;
            letter-spacing: 0.5px;
            line-height: 1.1;
            text-transform: uppercase;
            min-height: 28px;
            display: flex;
            align-items: flex-end;
        }

        .doc-main-title {
            font-size: 20px;
            font-weight: 800;
            color: #000000;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
            min-height: 28px;
            display: flex;
            align-items: flex-end;
        }

        .header-line {
            border-bottom: 2px solid #000000;
            width: 100%;
            margin: 5px 0 7px 0;
        }

        .header-label {
            font-size: 11px;
            font-weight: 600;
            color: #000000;
            margin-bottom: 3px;
            min-height: 16px;
        }

        /* Shaded Card (Accurate Style for Customer & Metadata) */
        .meta-card {
            background: #E5E7EB;
            border: 1px solid #CBD5E1;
            padding: 7px 12px;
            font-size: 11px;
            min-height: 82px;
        }

        .customer-name-heading {
            font-size: 12.5px;
            font-weight: 800;
            color: #000000;
            text-transform: uppercase;
            margin-bottom: 3px;
            line-height: 1.2;
        }

        .customer-address-block {
            font-size: 11px;
            line-height: 1.35;
            color: #111827;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
            font-size: 11px;
        }

        .meta-table td.col-lbl {
            width: 82px;
            color: #000000;
        }

        .meta-table td.col-sep {
            width: 12px;
            text-align: center;
            color: #000000;
        }

        .meta-table td.col-val {
            font-weight: 700;
            color: #000000;
        }

        /* Accurate Items Table (Solid Navy Blue Header) */
        .table-accurate {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 11px;
        }

        .table-accurate thead th {
            background: #123B61 !important;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 11px;
            padding: 6px 8px;
            border: none;
            letter-spacing: 0.2px;
        }

        .table-accurate tbody td {
            padding: 6px 8px;
            font-size: 11px;
            border-bottom: 1px solid #E5E7EB;
            vertical-align: top;
            color: #000000;
        }

        .table-accurate tbody tr:last-child td {
            border-bottom: 1.5px solid #000000;
        }

        /* Bottom Section: Notes & QRIS vs Totals & Signature */
        .bottom-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 12px;
        }

        .bottom-left {
            width: 52%;
        }

        .keterangan-header {
            border-top: 1.5px solid #000000;
            border-bottom: 1.5px solid #000000;
            width: 230px;
            padding: 2.5px 0;
            font-size: 11px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 6px;
        }

        .keterangan-body {
            font-size: 11px;
            color: #111827;
            line-height: 1.4;
            min-height: 20px;
        }

        .dashed-divider {
            border-top: 1.5px dashed #9CA3AF;
            width: 230px;
            margin: 10px 0 8px 0;
        }

        .qris-label {
            font-size: 11px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 6px;
        }

        .qris-img-container {
            display: inline-block;
            margin-left: 20px;
        }

        .qris-accurate-img {
            width: 120px;
            height: auto;
            display: block;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        .bottom-right {
            width: 40%;
        }

        /* Accurate Totals Table */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            background: #E5E7EB;
            border: 1px solid #CBD5E1;
            font-size: 11px;
        }

        .totals-table td {
            padding: 4px 8px;
            font-size: 11px;
            color: #000000;
        }

        .totals-table tr.row-grand-total td {
            background: #123B61 !important;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 11.5px;
            padding: 5.5px 8px;
            border-top: 1px solid #123B61;
        }

        /* Signature Section (Accurate Style) */
        .signature-area {
            margin-top: 22px;
            display: flex;
            justify-content: flex-end;
        }

        .signature-box {
            width: 180px;
            text-align: center;
        }

        .sig-title {
            font-size: 11px;
            font-weight: 600;
            color: #000000;
            text-align: center;
        }

        .sig-line {
            border-bottom: 1.5px solid #000000;
            width: 140px;
            margin: 44px auto 4px auto;
        }

        .sig-name {
            font-size: 11.5px;
            font-weight: 700;
            color: #000000;
            text-align: center;
            line-height: 1.2;
        }

        .page-footer-num {
            position: absolute;
            bottom: 20px;
            right: 44px;
            font-size: 10px;
            font-style: italic;
            color: #000000;
        }

        /* Print Media Styles */
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 14mm 12mm 14mm;
            }
            body {
                background: #FFFFFF !important;
                padding: 0 !important;
            }
            .print-toolbar {
                display: none !important;
            }
            .invoice-paper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
            }
            .table-accurate thead th {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .meta-card, .totals-table, .totals-table tr.row-grand-total td {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-footer-num {
                position: fixed;
                bottom: 8mm;
                right: 14mm;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Toolbar (Hidden When Printed) -->
    <div class="print-toolbar">
        <div class="toolbar-left">
            <a href="sales_orders.php" class="btn-tb btn-tb-outline">
                <i class="bi bi-arrow-left"></i> <span>Daftar SO</span>
            </a>
            <a href="sales_order_form.php?id=<?php echo $order['id']; ?>" class="btn-tb btn-tb-outline">
                <i class="bi bi-pencil"></i> <span>Edit Pesanan</span>
            </a>
        </div>

        <div class="toolbar-center">
            <!-- Kop / Brand Selector -->
            <div class="tb-control-group">
                <label for="selectBrand">Kop:</label>
                <select id="selectBrand" class="tb-select">
                    <option value="GRAVITTI" <?php echo ($defaultBrand === 'GRAVITTI') ? 'selected' : ''; ?>>GRAVITTI (Sesuai PDF)</option>
                    <option value="LOEWIX" <?php echo ($defaultBrand === 'LOEWIX') ? 'selected' : ''; ?>>LOEWIX</option>
                    <option value="LOEWIX CCTV" <?php echo ($defaultBrand === 'LOEWIX CCTV') ? 'selected' : ''; ?>>LOEWIX CCTV</option>
                    <option value="PT. GITI CCTV INDONESIA" <?php echo ($defaultBrand === 'PT. GITI CCTV INDONESIA') ? 'selected' : ''; ?>>PT. GITI CCTV INDONESIA</option>
                </select>
            </div>

            <!-- Document Title Selector -->
            <div class="tb-control-group">
                <label for="selectDocTitle">Judul:</label>
                <select id="selectDocTitle" class="tb-select">
                    <option value="PROFORMA INVOICE" <?php echo ($defaultTitle === 'PROFORMA INVOICE') ? 'selected' : ''; ?>>PROFORMA INVOICE (Sesuai PDF)</option>
                    <option value="SALES ORDER" <?php echo ($defaultTitle === 'SALES ORDER') ? 'selected' : ''; ?>>SALES ORDER</option>
                    <option value="PESANAN PENJUALAN" <?php echo ($defaultTitle === 'PESANAN PENJUALAN') ? 'selected' : ''; ?>>PESANAN PENJUALAN</option>
                </select>
            </div>
        </div>

        <div class="toolbar-right">
            <button onclick="window.print()" class="btn-tb btn-tb-primary">
                <i class="bi bi-printer-fill"></i> <span>Cetak Dokumen (PDF)</span>
            </button>
        </div>
    </div>

    <!-- Main Printable Sheet -->
    <div class="invoice-paper">
        
        <!-- Header (Accurate Style) -->
        <div class="accurate-header">
            <!-- Left: Brand / Logo & Customer Address -->
            <div class="header-col">
                <div class="company-logo-text" id="brandLogoText"><?php echo htmlspecialchars($defaultBrand); ?></div>
                <div class="header-line"></div>

                <div class="header-label">Kepada</div>
                <div class="meta-card">
                    <div class="customer-name-heading"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                    <div class="customer-address-block">
                        <?php if (!empty($order['customer_address'])): ?>
                            <?php echo nl2br(htmlspecialchars($order['customer_address'])); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                        <?php if (!empty($order['customer_pic']) || !empty($order['customer_phone'])): ?>
                            <div style="margin-top: 3px; font-size: 10.5px; color: #374151;">
                                <?php if (!empty($order['customer_pic'])): ?>PIC: <strong><?php echo htmlspecialchars($order['customer_pic']); ?></strong><?php endif; ?>
                                <?php if (!empty($order['customer_phone'])): ?> &bull; Telp: <strong><?php echo htmlspecialchars($order['customer_phone']); ?></strong><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Document Title & Meta Box -->
            <div class="header-col">
                <div class="doc-main-title" id="docMainTitle"><?php echo htmlspecialchars($defaultTitle); ?></div>
                <div class="header-line"></div>

                <div class="header-label">&nbsp;</div>
                <div class="meta-card">
                    <table class="meta-table">
                        <tr>
                            <td class="col-lbl">Nomor</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars(!empty($order['so_number']) ? $order['so_number'] : '(Menunggu No. SO)'); ?></td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Tanggal</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo $tglFormatted; ?></td>
                        </tr>
                        <tr>
                            <td class="col-lbl">Pembayaran</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($order['payment_terms'] ?: 'C.O.D'); ?></td>
                        </tr>
                        <?php if (!empty($order['po_number'])): ?>
                        <tr>
                            <td class="col-lbl">No. PO</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($order['po_number']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Table of Items (Navy Blue Header Bar) -->
        <table class="table-accurate">
            <thead>
                <tr>
                    <th style="width: 13%; text-align: left;">Kode Barang</th>
                    <th style="width: 45%; text-align: left;">Nama Barang</th>
                    <th style="width: 7%; text-align: right;">Qty</th>
                    <th style="width: 13%; text-align: right;">@Harga</th>
                    <th style="width: 8%; text-align: right;">Diskon</th>
                    <th style="width: 14%; text-align: right;">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    // Bersihkan deskripsi jika merupakan string metadata internal (Kode / Satuan)
                    $itemDesc = $item['item_description'] ?? '';
                    if (strpos($itemDesc, 'Kode: ') === 0 && strpos($itemDesc, 'Satuan: ') !== false) {
                        $itemDesc = '';
                    }
                ?>
                    <tr>
                        <td style="text-align: left;">
                            <?php echo htmlspecialchars($item['item_code'] ?: '-'); ?>
                        </td>
                        <td style="text-align: left;">
                            <div style="<?php echo (strpos($item['item_name'], '--') === 0) ? 'padding-left:8px; font-weight:500;' : 'font-weight:600;'; ?>">
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </div>
                            <?php if (!empty($itemDesc) && $itemDesc !== $item['item_name']): ?>
                                <div style="font-size: 10px; color: #4B5563; margin-top: 1px;">
                                    <?php echo htmlspecialchars($itemDesc); ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: 600;">
                            <?php echo (int)$item['qty']; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php echo number_format($item['unit_price'], 0, ',', '.'); ?>
                        </td>
                        <td style="text-align: right;">
                            <?php echo number_format($item['discount_item'], 0, ',', '.'); ?>
                        </td>
                        <td style="text-align: right; font-weight: 600;">
                            <?php echo number_format($item['total_price'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Bottom Section: Notes & QRIS (Left) vs Summary & Signature (Right) -->
        <div class="bottom-section">
            
            <!-- Left Side: Keterangan & QRIS -->
            <div class="bottom-left">
                <div class="keterangan-header">Keterangan</div>
                <div class="keterangan-body">
                    <?php if (!empty($order['special_notes'])): ?>
                        <?php echo nl2br(htmlspecialchars($order['special_notes'])); ?>
                    <?php endif; ?>
                    <div style="font-size:10.5px; color:#374151; <?php echo !empty($order['special_notes']) ? 'margin-top:4px;' : ''; ?>">
                        <strong>Terbilang:</strong> <em><?php echo terbilang($order['grand_total']); ?></em>
                    </div>
                </div>

                <div class="dashed-divider"></div>

                <div class="qris-label">Pembayaran melalui QRIS :</div>
                <div class="qris-img-container">
                    <img src="<?php echo $qrisSrc; ?>" 
                         alt="QRIS BCA - GRAVITTI TECHNOLOGY" 
                         class="qris-accurate-img">
                </div>
            </div>

            <!-- Right Side: Totals & Signature -->
            <div class="bottom-right">
                <table class="totals-table">
                    <tr>
                        <td style="width: 50%;">Sub Total</td>
                        <td style="width: 50%; text-align: right; font-weight: 600;">
                            <?php echo number_format($order['subtotal'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Diskon</td>
                        <td style="text-align: right; font-weight: 600;">
                            <?php echo number_format($order['discount_amount'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                    <?php if (!empty($order['is_taxable'])): ?>
                    <tr>
                        <td>PPN (11%)</td>
                        <td style="text-align: right; font-weight: 600;">
                            <?php echo number_format($order['tax_amount'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td>Biaya Lain-lain</td>
                        <td style="text-align: right; font-weight: 600;">
                            0
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="row-grand-total">
                        <td>Total</td>
                        <td style="text-align: right;">
                            <?php echo number_format($order['grand_total'], 0, ',', '.'); ?>
                        </td>
                    </tr>
                </table>

                <!-- Signature Section (Accurate Style) -->
                <div class="signature-area">
                    <div class="signature-box">
                        <div class="sig-title">Bagian Penjualan,</div>
                        <div class="sig-line"></div>
                        <div class="sig-name">
                            <?php echo htmlspecialchars($order['sales_name'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Accurate Page Number (Footer Bottom Right) -->
        <div class="page-footer-num">Halaman 1 dari 1</div>

    </div>

    <!-- Script to Handle Live Switcher & LocalStorage Memory -->
    <script>
        const brandSelect = document.getElementById('selectBrand');
        const docTitleSelect = document.getElementById('selectDocTitle');
        const brandText = document.getElementById('brandLogoText');
        const docTitleText = document.getElementById('docMainTitle');

        // Restore saved preference if any
        const savedBrand = localStorage.getItem('so_print_brand');
        if (savedBrand && brandSelect) {
            brandSelect.value = savedBrand;
            brandText.innerText = savedBrand;
        }

        const savedTitle = localStorage.getItem('so_print_title');
        if (savedTitle && docTitleSelect) {
            docTitleSelect.value = savedTitle;
            docTitleText.innerText = savedTitle;
            document.title = savedTitle + " - <?php echo htmlspecialchars(!empty($order['so_number']) ? $order['so_number'] : ('ID #' . $order['id'])); ?>";
        }

        // Event listeners
        brandSelect.addEventListener('change', function() {
            const val = this.value;
            brandText.innerText = val;
            localStorage.setItem('so_print_brand', val);
        });

        docTitleSelect.addEventListener('change', function() {
            const val = this.value;
            docTitleText.innerText = val;
            document.title = val + " - <?php echo htmlspecialchars(!empty($order['so_number']) ? $order['so_number'] : ('ID #' . $order['id'])); ?>";
            localStorage.setItem('so_print_title', val);
        });
    </script>
</body>
</html>
