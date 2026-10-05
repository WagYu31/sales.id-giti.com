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
            background: #E2E8F0;
            color: #000000;
            margin: 0;
            padding: 20px 0;
            font-size: 11px;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Action Bar (Hidden when printing) */
        .print-toolbar {
            max-width: 820px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0F172A;
            padding: 10px 16px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            color: #FFFFFF;
        }

        /* Accurate Sheet Paper (A4 Proportion) */
        .invoice-paper {
            max-width: 820px;
            min-height: 1060px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 38px 46px 60px 46px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            box-sizing: border-box;
        }

        /* Accurate Header Layout */
        .accurate-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .header-left {
            width: 50%;
        }

        .company-logo-text {
            font-size: 26px;
            font-weight: 800;
            color: #000000;
            letter-spacing: 0.5px;
            line-height: 1.1;
            text-transform: uppercase;
        }

        .header-line-left {
            border-bottom: 2px solid #000000;
            width: 250px;
            margin: 5px 0 8px 0;
        }

        .kepada-label {
            font-size: 11px;
            color: #000000;
            margin-bottom: 2px;
        }

        .customer-name-heading {
            font-size: 13px;
            font-weight: 800;
            color: #000000;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .customer-address-block {
            font-size: 11px;
            line-height: 1.4;
            color: #000000;
            max-width: 340px;
        }

        .header-right {
            width: 44%;
        }

        .doc-main-title {
            font-size: 22px;
            font-weight: 800;
            color: #000000;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .header-line-right {
            border-bottom: 2px solid #000000;
            width: 100%;
            margin: 5px 0 8px 0;
        }

        /* Meta Box Grey Shaded (Accurate Style) */
        .meta-card {
            background: #E5E7EB;
            border: 1px solid #D1D5DB;
            padding: 6px 12px;
            font-size: 11px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .meta-table td {
            padding: 2.5px 0;
            vertical-align: top;
            font-size: 11px;
        }

        .meta-table td.col-lbl {
            width: 90px;
            color: #000000;
        }

        .meta-table td.col-sep {
            width: 14px;
            text-align: center;
            color: #000000;
        }

        .meta-table td.col-val {
            font-weight: 600;
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
            background: #0A2540 !important;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 11px;
            padding: 6px 8px;
            border: none;
            letter-spacing: 0.2px;
        }

        .table-accurate tbody td {
            padding: 5.5px 8px;
            font-size: 11px;
            border-bottom: 1px solid #E5E7EB;
            vertical-align: top;
            color: #000000;
        }

        .table-accurate tbody tr:last-child td {
            border-bottom: 1.5px solid #000000;
        }

        /* Bottom Section: Notes & QRIS vs Totals */
        .bottom-section {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
        }

        .bottom-left {
            width: 54%;
        }

        .keterangan-title {
            font-size: 11px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 2px;
        }

        .keterangan-line {
            border-bottom: 1.5px solid #000000;
            width: 240px;
            margin-bottom: 6px;
        }

        .keterangan-body {
            font-size: 11px;
            color: #000000;
            line-height: 1.4;
            min-height: 24px;
        }

        .dashed-divider {
            border-top: 1.5px dashed #9CA3AF;
            width: 240px;
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
            margin-top: 2px;
        }

        .qris-accurate-img {
            width: 122px;
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
            border: 1px solid #D1D5DB;
            font-size: 11px;
        }

        .totals-table td {
            padding: 4px 8px;
            font-size: 11px;
            color: #000000;
        }

        .totals-table tr.row-grand-total td {
            background: #0A2540 !important;
            color: #FFFFFF !important;
            font-weight: 700;
            font-size: 11.5px;
            padding: 6px 8px;
            border-top: 1px solid #0A2540;
        }

        /* Signature Area */
        .signature-area {
            margin-top: 24px;
            text-align: right;
        }

        .signature-box {
            display: inline-block;
            width: 180px;
            text-align: center;
            font-size: 11.5px;
        }

        .signature-line {
            border-bottom: 1.5px solid #000000;
            width: 100%;
            margin: 55px auto 4px auto;
        }

        .page-footer-num {
            position: absolute;
            bottom: 20px;
            right: 46px;
            font-size: 10px;
            font-style: italic;
            color: #000000;
        }

        /* Print Media Styles */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 14mm 10mm 14mm;
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
                padding: 0 !important;
                margin: 0 !important;
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
        <div class="d-flex align-items-center gap-2">
            <a href="sales_orders.php" class="btn btn-outline-light btn-sm fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Daftar SO
            </a>
            <a href="sales_order_form.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-light btn-sm fw-bold">
                <i class="bi bi-pencil me-1"></i> Edit Pesanan
            </a>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Kop / Brand Selector -->
            <div class="d-flex align-items-center gap-1">
                <span style="font-size:11px; color:#94A3B8;">Kop:</span>
                <select id="selectBrand" class="form-select form-select-sm" style="width:auto; font-size:11.5px; font-weight:600; background:#1E293B; color:#fff; border-color:#475569;">
                    <option value="GRAVITTI" <?php echo ($defaultBrand === 'GRAVITTI') ? 'selected' : ''; ?>>GRAVITTI (Sesuai PDF)</option>
                    <option value="LOEWIX" <?php echo ($defaultBrand === 'LOEWIX') ? 'selected' : ''; ?>>LOEWIX</option>
                    <option value="LOEWIX CCTV" <?php echo ($defaultBrand === 'LOEWIX CCTV') ? 'selected' : ''; ?>>LOEWIX CCTV</option>
                    <option value="PT. GITI CCTV INDONESIA" <?php echo ($defaultBrand === 'PT. GITI CCTV INDONESIA') ? 'selected' : ''; ?>>PT. GITI CCTV INDONESIA</option>
                </select>
            </div>

            <!-- Document Title Selector -->
            <div class="d-flex align-items-center gap-1">
                <span style="font-size:11px; color:#94A3B8;">Judul:</span>
                <select id="selectDocTitle" class="form-select form-select-sm" style="width:auto; font-size:11.5px; font-weight:600; background:#1E293B; color:#fff; border-color:#475569;">
                    <option value="PROFORMA INVOICE" <?php echo ($defaultTitle === 'PROFORMA INVOICE') ? 'selected' : ''; ?>>PROFORMA INVOICE (Sesuai PDF)</option>
                    <option value="SALES ORDER" <?php echo ($defaultTitle === 'SALES ORDER') ? 'selected' : ''; ?>>SALES ORDER</option>
                    <option value="PESANAN PENJUALAN" <?php echo ($defaultTitle === 'PESANAN PENJUALAN') ? 'selected' : ''; ?>>PESANAN PENJUALAN</option>
                </select>
            </div>

            <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold px-3">
                <i class="bi bi-printer me-1"></i> Cetak Dokumen (PDF)
            </button>
        </div>
    </div>

    <!-- Main Printable Sheet -->
    <div class="invoice-paper">
        
        <!-- Header (Accurate Style) -->
        <div class="accurate-header">
            <!-- Left: Brand / Logo & Customer Address -->
            <div class="header-left">
                <div class="company-logo-text" id="brandLogoText"><?php echo htmlspecialchars($defaultBrand); ?></div>
                <div class="header-line-left"></div>

                <div class="kepada-label">Kepada</div>
                <div class="customer-name-heading"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                <div class="customer-address-block">
                    <?php if (!empty($order['customer_address'])): ?>
                        <?php echo nl2br(htmlspecialchars($order['customer_address'])); ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                    <?php if (!empty($order['customer_pic']) || !empty($order['customer_phone'])): ?>
                        <div style="margin-top:2px;">
                            <?php if (!empty($order['customer_pic'])): ?>PIC: <?php echo htmlspecialchars($order['customer_pic']); ?><?php endif; ?>
                            <?php if (!empty($order['customer_phone'])): ?> &bull; Telp: <?php echo htmlspecialchars($order['customer_phone']); ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Document Title & Meta Box -->
            <div class="header-right">
                <div class="doc-main-title" id="docMainTitle"><?php echo htmlspecialchars($defaultTitle); ?></div>
                <div class="header-line-right"></div>

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
                        <?php if (!empty($order['sales_name'])): ?>
                        <tr>
                            <td class="col-lbl">Sales</td>
                            <td class="col-sep">:</td>
                            <td class="col-val"><?php echo htmlspecialchars($order['sales_name']); ?></td>
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
                    <th style="width: 14%; text-align: left;">Kode Barang</th>
                    <th style="width: 44%; text-align: left;">Nama Barang</th>
                    <th style="width: 7%; text-align: right;">Qty</th>
                    <th style="width: 15%; text-align: right;">@Harga</th>
                    <th style="width: 7%; text-align: right;">Diskon</th>
                    <th style="width: 13%; text-align: right;">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td style="text-align: left;">
                            <?php echo htmlspecialchars($item['item_code'] ?: '-'); ?>
                        </td>
                        <td style="text-align: left;">
                            <div style="<?php echo (strpos($item['item_name'], '--') === 0) ? 'padding-left:8px; font-weight:500;' : 'font-weight:600;'; ?>">
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </div>
                            <?php if (!empty($item['item_description']) && $item['item_description'] !== $item['item_name']): ?>
                                <div style="font-size: 10px; color: #4B5563; margin-top: 1px;">
                                    <?php echo htmlspecialchars($item['item_description']); ?>
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
                <div class="keterangan-title">Keterangan</div>
                <div class="keterangan-line"></div>
                <div class="keterangan-body">
                    <?php if (!empty($order['special_notes'])): ?>
                        <?php echo nl2br(htmlspecialchars($order['special_notes'])); ?>
                    <?php else: ?>
                        <i>-</i>
                    <?php endif; ?>
                    <div style="font-size:10.5px; color:#4B5563; margin-top:4px;">
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
                        <div style="font-weight: 600;">Bagian Penjualan,</div>
                        <div class="signature-line"></div>
                        <div style="text-align: center; font-size: 11.5px; font-weight: 600;">
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
