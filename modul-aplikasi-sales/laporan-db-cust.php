
<style>
@media (max-width: 767.98px) {
  .detailBtn, .editVisitBtn, .deleteVisitBtn {
    min-width: 38px !important;
    min-height: 38px !important;
    padding: 8px 12px !important;
    font-size: 14px !important;
    touch-action: manipulation !important;
  }
}
</style>

<?php
// Filter variables
$filterSales = isset($_GET['id_sales']) ? intval($_GET['id_sales']) : 0;
$filterBulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : date("Y-m");
$filterTanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : '';
$filterStatus = isset($_GET['status']) ? trim(strtolower($_GET['status'])) : '';

if (!empty($filterTanggal)) {
    $current_date = $filterTanggal;
} else {
    $current_date = $filterBulan . '-01'; // Define base date for compatibility/formatting
}

// Fetch sales list for dropdown
$resSalesList = mysqli_query($conn, "SELECT id, nama FROM sales WHERE deleted_at IS NULL ORDER BY nama ASC");
$salesOptions = [];
if ($resSalesList) {
    while ($rS = mysqli_fetch_assoc($resSalesList)) {
        $salesOptions[] = $rS;
    }
}
?>
<style>
.premium-hover-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}
.premium-hover-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 36px -4px rgba(148, 163, 184, 0.16), 0 6px 16px -2px rgba(148, 163, 184, 0.08) !important;
}
.activity-row-item:hover {
    background-color: #f8fafc;
}
</style>
<div class="col-lg-12">
    <div class="card h-100 py-3" style="border-radius: 16px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); border: none;">
        <div class="card-header pb-3 p-3 bg-transparent border-bottom">
            <div class="row align-items-center">
                <div class="col-12 col-xl-4 d-flex align-items-center mb-3 mb-xl-0">
                    <h5 class="mb-0 mx-1 ms-2 font-weight-bold text-dark text-uppercase" style="letter-spacing: 0.5px;"><i class="fa-solid fa-clipboard-list text-primary me-2"></i>Laporan Kunjungan Sales</h5>
                </div>
                <div class="col-12 col-xl-8">
                    <form method="GET" action="" class="row g-2 align-items-center justify-content-xl-end">
                        <div class="col-12 col-sm-3">
                            <select name="id_sales" class="form-select border p-2 bg-white text-dark" style="border-radius: 8px;">
                                <option value="0">-- Semua Sales --</option>
                                <?php foreach ($salesOptions as $opt) : ?>
                                    <option value="<?php echo $opt['id']; ?>" <?php echo $filterSales == $opt['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($opt['nama']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-2">
                            <select name="status" class="form-select border p-2 bg-white text-dark" style="border-radius: 8px;" title="Filter Status">
                                <option value="">-- Semua Status --</option>
                                <option value="selesai" <?php echo $filterStatus == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                <option value="berjalan" <?php echo $filterStatus == 'berjalan' ? 'selected' : ''; ?>>Diproses</option>
                                <option value="dijadwalkan" <?php echo $filterStatus == 'dijadwalkan' ? 'selected' : ''; ?>>Dijadwalkan</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-2">
                            <input type="month" class="form-control border p-2 bg-white text-dark" name="bulan" value="<?php echo $filterBulan; ?>" style="border-radius: 8px;" title="Filter Bulan">
                        </div>
                        <div class="col-12 col-sm-2">
                            <input type="date" class="form-control border p-2 bg-white text-dark" name="tanggal" value="<?php echo $filterTanggal; ?>" style="border-radius: 8px;" title="Filter Tanggal">
                        </div>
                        <div class="col-6 col-sm-1 d-grid">
                            <button type="submit" class="btn bg-gradient-primary mb-0 p-2 text-white" style="border-radius: 8px;">Cari</button>
                        </div>
                        <div class="col-6 col-sm-2 d-grid">
                            <button type="button" class="btn bg-gradient-success mb-0 p-2 text-white" style="border-radius: 8px;" data-bs-toggle="modal" data-bs-target="#syncSheetsModal">
                                <i class="fa-solid fa-file-excel me-1"></i>Sync
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        // Build SQL with filters
        $whereClauses = ["ks.deleted_at IS NULL"];
        if ($filterSales > 0) {
            $whereClauses[] = "ks.id IN (SELECT id_kegiatan_sales FROM team_kegiatan_sales WHERE id_sales = $filterSales AND deleted_at IS NULL)";
        }
        if (!empty($filterTanggal)) {
            $whereClauses[] = "DATE(ks.jadwal) = '" . mysqli_real_escape_string($conn, $filterTanggal) . "'";
        } else if (!empty($filterBulan)) {
            $whereClauses[] = "DATE_FORMAT(ks.jadwal, '%Y-%m') = '" . mysqli_real_escape_string($conn, $filterBulan) . "'";
        }
        if (!empty($filterStatus)) {
            if ($filterStatus === 'dijadwalkan') {
                $whereClauses[] = "ks.id NOT IN (SELECT kegiatan_id FROM pelaksanaan_sales WHERE status IN ('selesai', 'berjalan', 'proses'))";
            } else if ($filterStatus === 'berjalan') {
                $whereClauses[] = "ks.id IN (SELECT kegiatan_id FROM pelaksanaan_sales WHERE status IN ('berjalan', 'proses'))";
            } else if ($filterStatus === 'selesai') {
                $whereClauses[] = "ks.id IN (SELECT kegiatan_id FROM pelaksanaan_sales WHERE status = 'selesai')";
            }
        }
        
        $whereSql = implode(" AND ", $whereClauses);

        $sql = "SELECT ks.id, ks.id AS kode_transaksi, ks.jadwal AS tgl_visits, sc.nama AS nama_cust, sc.id AS id_cust
                FROM kegiatan_sales ks
                INNER JOIN sales_customer sc ON ks.id_customer = sc.id
                WHERE $whereSql
                ORDER BY ks.jadwal DESC";

        $result = mysqli_query($conn, $sql);
        ?>
        <div class="card-body p-3">
            <?php

            $tanggal = date("d", strtotime($current_date));
            $tahun = date("Y", strtotime($current_date));
            $formatted_date = date("d - M - Y", strtotime($current_date));
            $day_in_indonesian = formatTanggal('EEEE', $current_date);
            $month_in_indonesian = formatTanggal('MMMM', $current_date);

            ?>
            <div class="d-flex flex-column gap-4 mt-2">

                <?php
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $kegiatanId = $row['id'];
                        $idC = $row['id_cust'];
                        $namaC = $row['nama_cust'];
                        $kodeTransaksi = $row['kode_transaksi'];
                        $tgl_visits = $row['tgl_visits'];
                ?>
                    <!-- Customer Activity Card -->
                    <div class="card mb-4 premium-hover-card" style="border-radius: 16px; border: 1px solid #e2e8f0; border-left: 5px solid #3b82f6 !important; box-shadow: 0 4px 20px -2px rgba(148, 163, 184, 0.05); overflow: hidden; background-color: #ffffff;">
                        
                        <!-- Customer Name Title Header -->
                        <div class="p-3 border-bottom d-flex align-items-center justify-content-between" style="background-color: #ffffff;">
                            <div class="d-flex align-items-center">
                                <span class="d-flex align-items-center justify-content-center me-2.5" style="background-color: #eff6ff; color: #1d4ed8; border-radius: 8px; width: 32px; height: 32px;">
                                    <i class="fa-solid fa-building text-xs"></i>
                                </span>
                                <h6 class="mb-0 text-dark font-weight-bold text-sm" style="letter-spacing: 0.02em;">
                                    <?php echo htmlspecialchars($namaC); ?>
                                </h6>
                            </div>
                        </div>

                        <!-- Inner Activities Table -->
                        <div class="p-3">
                            <?php
                            $sqlLapTek = "SELECT tks.*, s.nama AS nama_sales, tks.id_sales,
                                                 IFNULL(ps.status, 'dijadwalkan') AS status,
                                                 ps.ci_at AS tgl_mulai, ps.co_at AS tgl_selesai,
                                                 ks.id AS kode_transaksi, ks.jadwal AS tgl_visits,
                                                 COALESCE(NULLIF(ps.catatan_visit, ''), ps.keterangan) AS hasil_visits
                                          FROM team_kegiatan_sales tks
                                          JOIN sales s ON tks.id_sales = s.id
                                          JOIN kegiatan_sales ks ON tks.id_kegiatan_sales = ks.id
                                          LEFT JOIN pelaksanaan_sales ps ON ps.kegiatan_id = tks.id_kegiatan_sales AND ps.sales_id = tks.id_sales
                                          WHERE tks.id_kegiatan_sales = '$kegiatanId' AND tks.deleted_at IS NULL";
                            if ($filterSales > 0) {
                                $sqlLapTek .= " AND tks.id_sales = $filterSales";
                            }
                            if (!empty($filterStatus)) {
                                if ($filterStatus === 'dijadwalkan') {
                                    $sqlLapTek .= " AND (ps.status IS NULL OR ps.status = 'dijadwalkan')";
                                } else if ($filterStatus === 'berjalan') {
                                    $sqlLapTek .= " AND ps.status IN ('berjalan', 'proses')";
                                } else if ($filterStatus === 'selesai') {
                                    $sqlLapTek .= " AND ps.status = 'selesai'";
                                }
                            }
                            $resLapTek = mysqli_query($conn, $sqlLapTek);
                            if ($resLapTek && mysqli_num_rows($resLapTek) > 0) {
                            ?>
                                <!-- Header Row for Desktop -->
                                <div class="row px-3 py-2.5 bg-light d-none d-md-flex font-weight-bold text-xxs text-uppercase text-secondary mb-2" style="background-color: #f8fafc; border-radius: 8px; letter-spacing: 0.05em; border: 1px solid #e2e8f0;">
                                    <div class="col-md-2">Status / Kegiatan</div>
                                    <div class="col-md-2">Sales Agent</div>
                                    <div class="col-md-2">Jadwal Kunjungan</div>
                                    <div class="col-md-1">Waktu Mulai</div>
                                    <div class="col-md-1">Waktu Selesai</div>
                                    <div class="col-md-2 text-center">Aksi</div>
                                    <div class="col-md-2">Hasil</div>
                                </div>

                                <?php
                                while ($rowLT = mysqli_fetch_assoc($resLapTek)) {
                                    $idT = $rowLT["id_sales"];
                                    $hslVisits = $rowLT['hasil_visits'] ?? '';
                                    $datetime = $rowLT["tgl_visits"];
                                    $formattedDate = ($datetime && $datetime != '0000-00-00 00:00:00') ? date("d-m-Y", strtotime($datetime)) : '-';
                                    $formattedTime = ($datetime && $datetime != '0000-00-00 00:00:00') ? date("H:i", strtotime($datetime)) : '-';

                                    $tglMulai = $rowLT["tgl_mulai"];
                                    $formattedDateMli = ($tglMulai && $tglMulai != '0000-00-00 00:00:00') ? date("d-m-Y", strtotime($tglMulai)) : '-';
                                    $formattedTimeMli = ($tglMulai && $tglMulai != '0000-00-00 00:00:00') ? date("H:i", strtotime($tglMulai)) : '-';

                                    $tglSelesai = $rowLT["tgl_selesai"];
                                    $formattedDateSls = ($tglSelesai && $tglSelesai != '0000-00-00 00:00:00') ? date("d-m-Y", strtotime($tglSelesai)) : '-';
                                    $formattedTimeSls = ($tglSelesai && $tglSelesai != '0000-00-00 00:00:00') ? date("H:i", strtotime($tglSelesai)) : '-';
                                    $status = strtolower($rowLT['status']);

                                    $badgeStyle = match($status) {
                                        'selesai' => 'background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;',
                                        'berjalan' => 'background-color: #fffbeb; color: #92400e; border: 1px solid #fde68a;',
                                        default => 'background-color: #f8fafc; color: #475569; border: 1px solid #e2e8f0;'
                                    };
                                ?>
                                    <!-- Activity Row -->
                                    <div class="row px-3 py-3 align-items-center mb-2 activity-row-item" style="border-bottom: 1px solid #f1f5f9; border-radius: 8px; transition: all 0.2s ease;">
                                        
                                        <!-- Status / Kegiatan -->
                                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                                            <span class="d-md-none text-xxs text-secondary d-block font-weight-bold">Status</span>
                                            <span class="badge text-xxs font-weight-bold px-2.5 py-1.5" style="<?php echo $badgeStyle; ?> border-radius: 12px; display: inline-block;">
                                                <?php echo ucfirst($status == 'berjalan' ? 'Diproses' : ($status == 'selesai' ? 'Selesai' : 'Dijadwalkan')); ?>
                                            </span>
                                            <div class="text-xs mt-1 font-weight-bold">
                                                <a href="view-kegiatan.php?kode_transaksi=<?php echo $rowLT['kode_transaksi']; ?>&id_sales=<?php echo $idT; ?>" class="text-primary text-xxs font-weight-bold">
                                                    <i class="fa-solid fa-hashtag me-0.5"></i><?php echo $rowLT['kode_transaksi']; ?>
                                                </a>
                                            </div>
                                        </div>

                                        <!-- Sales -->
                                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                                            <span class="d-md-none text-xxs text-secondary d-block font-weight-bold">Sales</span>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-xs me-2 d-flex align-items-center justify-content-center" style="border-radius: 50%; width: 26px; height: 26px; background-color: #f1f5f9; border: 1px solid #e2e8f0;">
                                                    <i class="fa-solid fa-user text-secondary" style="font-size: 10px;"></i>
                                                </div>
                                                <h6 class="text-dark font-weight-bold text-xs mb-0"><?php echo htmlspecialchars($rowLT['nama_sales']); ?></h6>
                                            </div>
                                        </div>

                                        <!-- Visits -->
                                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                                            <span class="d-md-none text-xxs text-secondary d-block font-weight-bold">Visits</span>
                                            <div class="d-flex flex-column">
                                                <span class="text-xs text-dark font-weight-bold"><i class="fa-regular fa-calendar me-1 text-secondary"></i><?php echo $formattedDate; ?></span>
                                                <span class="text-xxs text-secondary font-weight-bold ms-3.5"><?php echo $formattedTime; ?></span>
                                            </div>
                                        </div>

                                        <!-- Mulai -->
                                        <div class="col-6 col-md-1 mb-2 mb-md-0">
                                            <span class="d-md-none text-xxs text-secondary d-block font-weight-bold">Mulai</span>
                                            <div class="d-flex flex-column">
                                                <?php if ($formattedDateMli !== '-') : ?>
                                                    <span class="text-xs text-dark font-weight-bold"><?php echo $formattedDateMli; ?></span>
                                                    <span class="text-xxs text-secondary font-weight-bold ms-0"><?php echo $formattedTimeMli; ?></span>
                                                <?php else : ?>
                                                    <span class="text-xs text-secondary">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Selesai -->
                                        <div class="col-6 col-md-1 mb-2 mb-md-0">
                                            <span class="d-md-none text-xxs text-secondary d-block font-weight-bold">Selesai</span>
                                            <div class="d-flex flex-column">
                                                <?php if ($formattedDateSls !== '-') : ?>
                                                    <span class="text-xs text-dark font-weight-bold"><?php echo $formattedDateSls; ?></span>
                                                    <span class="text-xxs text-secondary font-weight-bold ms-0"><?php echo $formattedTimeSls; ?></span>
                                                <?php else : ?>
                                                    <span class="text-xs text-secondary">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Aksi (Lihat, Edit, Hapus) -->
                                        <div class="col-12 col-md-2 text-md-center mb-2 mb-md-0 d-flex flex-wrap gap-2 justify-content-start justify-content-md-center align-items-center">
                                            <span class="d-md-none text-xxs text-secondary font-weight-bold w-100 mb-1">Aksi:</span>
                                            <button class="btn btn-outline-primary btn-sm mb-0 detailBtn" style="border-radius: 8px; min-width: 38px; min-height: 38px; padding: 6px 10px; display: inline-flex; align-items: center; justify-content: center; touch-action: manipulation;" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="<?php echo $idT; ?>" data-kode="<?php echo $kodeTransaksi; ?>" title="Rincian Kunjungan">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-success btn-sm mb-0 editVisitBtn" style="border-radius: 8px; min-width: 38px; min-height: 38px; padding: 6px 10px; display: inline-flex; align-items: center; justify-content: center; touch-action: manipulation;" data-id="<?php echo $kodeTransaksi; ?>" data-sales="<?php echo $idT; ?>" title="Edit Laporan">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm mb-0 deleteVisitBtn" style="border-radius: 8px; min-width: 38px; min-height: 38px; padding: 6px 10px; display: inline-flex; align-items: center; justify-content: center; touch-action: manipulation;" data-id="<?php echo $kodeTransaksi; ?>" data-sales="<?php echo $idT; ?>" data-status="<?php echo $status; ?>" data-cust="<?php echo htmlspecialchars($namaC); ?>" title="Hapus Laporan">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>

                                        <!-- Hasil Visits -->
                                        <div class="col-12 col-md-2 mb-2 mb-md-0">
                                            <span class="d-md-none text-xxs text-secondary d-block font-weight-bold">Hasil</span>
                                            <?php if ($status == 'selesai') : ?>
                                                <div style="font-size: 12.5px; color: #334155; line-height: 1.5; font-weight: 500; word-break: break-word;">
                                                    <?php echo nl2br(htmlspecialchars($hslVisits)); ?>
                                                </div>
                                            <?php else : ?>
                                                <span class="badge text-xxs font-weight-bold px-2.5 py-1" style="background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 12px; display: inline-block;">Belum Selesai</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php
                                }
                            } else {
                                echo "<div class='text-center text-sm text-secondary py-3'>Tidak ada kegiatan.</div>";
                            }
                            ?>
                        </div>
                    </div>
                <?php
                    }
                } else {
                    echo "<div class='text-center text-sm text-secondary py-4'>Tidak ada data kunjungan.</div>";
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sync Google Sheets -->
<div class="modal fade" id="syncSheetsModal" tabindex="-1" aria-labelledby="syncSheetsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold text-dark" id="syncSheetsModalLabel">
                    <i class="fa-solid fa-file-excel text-success me-2"></i>Sync ke Google Sheets
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="syncSheetsForm">
                <div class="modal-body py-3">
                    <div class="alert alert-info text-white text-xs border-0" style="background: linear-gradient(135deg, #1d4ed8, #3b82f6); border-radius: 12px; line-height: 1.5;">
                        <i class="fa-solid fa-circle-info me-2 text-sm"></i>
                        Pastikan Anda telah membagikan Spreadsheet target sebagai <strong>Editor</strong> ke email service account berikut:<br>
                        <code class="text-white bg-dark px-2 py-1 mt-1 d-inline-block rounded-sm select-all" style="font-family: monospace; font-size: 11px; letter-spacing: 0.2px;">sheets-sync@loewix-sales.iam.gserviceaccount.com</code>
                    </div>

                    <div class="form-group mt-3">
                        <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">ID Spreadsheet atau URL</label>
                        <input type="text" name="spreadsheet_id" id="sheetIdInput" class="form-control border p-2 text-sm" 
                               placeholder="Masukkan ID / Link Google Sheets" 
                               value="19OV073XNHmo7zACGOpYPyEcmodIZmEv4wzFq7Fg_uoU" style="border-radius: 8px;" required>
                    </div>

                    <div class="form-group mt-3">
                        <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Nama Sheet (Tab)</label>
                        <input type="text" name="sheet_name" id="sheetNameInput" class="form-control border p-2 text-sm" 
                               placeholder="Contoh: Sheet1" value="Sheet1" style="border-radius: 8px;" required>
                    </div>

                    <div class="row mt-3">
                        <div class="col-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Bulan</label>
                            <input type="text" id="displayBulanInput" class="form-control border p-2 text-sm bg-light" value="<?php echo date('F Y', strtotime($filterBulan . '-01')); ?>" style="border-radius: 8px;" readonly disabled>
                            <input type="hidden" id="hiddenBulanInput" name="bulan" value="<?php echo $filterBulan; ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Tanggal</label>
                            <input type="text" id="displayTanggalInput" class="form-control border p-2 text-sm bg-light" value="<?php echo !empty($filterTanggal) ? date('d M Y', strtotime($filterTanggal)) : '-'; ?>" style="border-radius: 8px;" readonly disabled>
                            <input type="hidden" id="hiddenTanggalInput" name="tanggal" value="<?php echo $filterTanggal; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Status Kegiatan</label>
                            <?php
                            $selectedStatusName = "Semua Status";
                            if ($filterStatus === 'selesai') $selectedStatusName = "Selesai";
                            elseif ($filterStatus === 'berjalan') $selectedStatusName = "Diproses";
                            elseif ($filterStatus === 'dijadwalkan') $selectedStatusName = "Dijadwalkan";
                            ?>
                            <input type="text" id="displayStatusInput" class="form-control border p-2 text-sm bg-light" value="<?php echo $selectedStatusName; ?>" style="border-radius: 8px;" readonly disabled>
                            <input type="hidden" id="hiddenStatusInput" name="status" value="<?php echo $filterStatus; ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Sales Agent</label>
                            <?php
                            $selectedSalesName = "Semua Sales";
                            if ($filterSales > 0) {
                                foreach ($salesOptions as $opt) {
                                    if ($opt['id'] == $filterSales) {
                                        $selectedSalesName = $opt['nama'];
                                        break;
                                    }
                                }
                            }
                            ?>
                            <input type="text" id="displaySalesInput" class="form-control border p-2 text-sm bg-light" value="<?php echo htmlspecialchars($selectedSalesName); ?>" style="border-radius: 8px;" readonly disabled>
                            <input type="hidden" id="hiddenSalesInput" name="id_sales" value="<?php echo $filterSales; ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-secondary mb-0" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn bg-gradient-success mb-0" id="btnDoSync" style="border-radius: 8px;">
                        <span id="syncSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                        Mulai Sync
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Laporan Kunjungan -->
<div class="modal fade" id="editVisitModal" tabindex="-1" aria-labelledby="editVisitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold text-dark" id="editVisitModalLabel">
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Laporan Kunjungan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editVisitForm">
                <input type="hidden" name="kegiatan_id" id="edit_kegiatan_id">
                <input type="hidden" name="sales_id" id="edit_sales_id">
                <input type="hidden" name="status_kegiatan" id="edit_status_kegiatan">
                
                <div class="modal-body py-3">
                    <div class="row">
                        <!-- Customer & Sales (Readonly) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Customer</label>
                            <input type="text" id="edit_customer_name" class="form-control border p-2 text-sm bg-light" readonly disabled style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Sales Agent</label>
                            <input type="text" id="edit_sales_name" class="form-control border p-2 text-sm bg-light" readonly disabled style="border-radius: 8px;">
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- Jadwal Kunjungan (Editable) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Jadwal Kunjungan</label>
                            <input type="datetime-local" name="jadwal" id="edit_jadwal" class="form-control border p-2 text-sm" style="border-radius: 8px;" required>
                        </div>
                        <!-- Tipe Prospek (Editable for Selesai/Berjalan) -->
                        <div class="col-md-6 mb-3 execution-field">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Tipe Prospek</label>
                            <select name="tipe_prospek" id="edit_tipe_prospek" class="form-select border p-2 text-sm" style="border-radius: 8px;">
                                <option value="Biasa">Biasa</option>
                                <option value="Peluang">Peluang</option>
                                <option value="Rumit">Rumit</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row execution-field">
                        <!-- Clock In (Editable) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Waktu Mulai (Clock In)</label>
                            <input type="datetime-local" name="ci_at" id="edit_ci_at" class="form-control border p-2 text-sm" style="border-radius: 8px;">
                        </div>
                        <!-- Clock Out (Editable) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Waktu Selesai (Clock Out)</label>
                            <input type="datetime-local" name="co_at" id="edit_co_at" class="form-control border p-2 text-sm" style="border-radius: 8px;">
                        </div>
                    </div>

                    <div class="row execution-field">
                        <!-- No Invoice (Editable) -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Nomor Invoice</label>
                            <input type="text" name="no_invoice" id="edit_no_invoice" class="form-control border p-2 text-sm" style="border-radius: 8px;" placeholder="Contoh: INV.12345">
                        </div>
                    </div>
                    
                    <!-- Hasil Kunjungan (Editable) -->
                    <div class="mb-3 execution-field">
                        <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Hasil Kunjungan / Keterangan</label>
                        <textarea name="keterangan" id="edit_keterangan" rows="2" class="form-control border p-2 text-sm" style="border-radius: 8px;" placeholder="Hasil kunjungan..."></textarea>
                    </div>
                    
                    <!-- Catatan Tambahan (Editable) -->
                    <div class="mb-3 execution-field">
                        <label class="form-label text-xs font-weight-bold text-secondary text-uppercase mb-1">Catatan Tambahan</label>
                        <textarea name="catatan_visit" id="edit_catatan_visit" rows="2" class="form-control border p-2 text-sm" style="border-radius: 8px;" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-link text-secondary mb-0" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn bg-gradient-primary mb-0" style="border-radius: 8px;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Make Modal Cards Draggable (Drag & Drop)
    function makeModalDraggable(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const dialog = modal.querySelector('.modal-dialog');
        const header = modal.querySelector('.modal-header');
        
        if (!dialog || !header) return;
        
        header.style.cursor = 'move';
        
        let isDragging = false;
        let startX, startY;
        let modalLeft = 0, modalTop = 0;
        
        modal.addEventListener('show.bs.modal', () => {
            dialog.style.left = '0px';
            dialog.style.top = '0px';
            modalLeft = 0;
            modalTop = 0;
        });
        
        header.addEventListener('mousedown', (e) => {
            if (e.target.closest('.btn-close') || e.target.closest('button')) return;
            
            isDragging = true;
            startX = e.clientX - modalLeft;
            startY = e.clientY - modalTop;
            
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            
            document.body.style.userSelect = 'none';
            dialog.style.transition = 'none';
        });
        
        function onMouseMove(e) {
            if (!isDragging) return;
            
            modalLeft = e.clientX - startX;
            modalTop = e.clientY - startY;
            
            dialog.style.left = modalLeft + 'px';
            dialog.style.top = modalTop + 'px';
        }
        
        function onMouseUp() {
            isDragging = false;
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            
            document.body.style.userSelect = '';
            dialog.style.transition = '';
        }
    }

    // Initialize Draggability
    makeModalDraggable('syncSheetsModal');
    makeModalDraggable('editVisitModal');
    makeModalDraggable('detailModal');

    $("#syncSheetsModal").on('show.bs.modal', function() {
        const topSalesSelect = $('select[name="id_sales"]');
        const topStatusSelect = $('select[name="status"]');
        const topBulanInput = $('input[name="bulan"]');
        const topTanggalInput = $('input[name="tanggal"]');
        
        const selectedSalesId = topSalesSelect.val();
        const selectedSalesName = topSalesSelect.find('option:selected').text().trim();
        const selectedStatus = topStatusSelect.val();
        const selectedStatusName = topStatusSelect.find('option:selected').text().trim();
        const selectedBulan = topBulanInput.val();
        const selectedTanggal = topTanggalInput.val();
        
        let readableMonth = selectedBulan;
        if (selectedBulan) {
            const parts = selectedBulan.split('-');
            if (parts.length === 2) {
                const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                const monthIdx = parseInt(parts[1], 10) - 1;
                if (monthIdx >= 0 && monthIdx < 12) {
                    readableMonth = months[monthIdx] + " " + parts[0];
                }
            }
        }
        
        let readableTanggal = "-";
        if (selectedTanggal) {
            const dateParts = selectedTanggal.split('-');
            if (dateParts.length === 3) {
                const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                const monthIdx = parseInt(dateParts[1], 10) - 1;
                if (monthIdx >= 0 && monthIdx < 12) {
                    readableTanggal = dateParts[2] + " " + months[monthIdx] + " " + dateParts[0];
                }
            }
        }
        
        $("#displaySalesInput").val(selectedSalesName);
        $("#hiddenSalesInput").val(selectedSalesId);
        $("#displayStatusInput").val(selectedStatusName);
        $("#hiddenStatusInput").val(selectedStatus);
        $("#displayBulanInput").val(readableMonth);
        $("#hiddenBulanInput").val(selectedBulan);
        $("#displayTanggalInput").val(readableTanggal);
        $("#hiddenTanggalInput").val(selectedTanggal);
    });

    $("#syncSheetsForm").on('submit', function(e) {
        e.preventDefault();
        
        const btn = $("#btnDoSync");
        const spinner = $("#syncSpinner");
        
        btn.prop('disabled', true);
        spinner.removeClass('d-none');
        
        $.ajax({
            url: "proses-sync-sheets.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
                
                if (response.status === 'success') {
                    alert(response.message);
                    $("#syncSheetsModal").modal('hide');
                } else {
                    alert("Gagal: " + response.message);
                }
            },
            error: function(xhr, status, error) {
                btn.prop('disabled', false);
                spinner.addClass('d-none');
                let errMsg = error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errMsg = xhr.responseJSON.message;
                }
                alert("Terjadi kesalahan koneksi server: " + errMsg);
            }
        });
    });

    // ── EDIT VISIT ACTION ──
    $(".editVisitBtn").click(function() {
        const kegiatanId = $(this).data("id");
        const salesId = $(this).data("sales");
        
        $.ajax({
            url: "get_visit_details.php",
            type: "GET",
            data: { kegiatan_id: kegiatanId, sales_id: salesId },
            dataType: "json",
            success: function(res) {
                if (res.status === 'success') {
                    const d = res.data;
                    $("#edit_kegiatan_id").val(d.kegiatan_id);
                    $("#edit_sales_id").val(d.sales_id);
                    $("#edit_status_kegiatan").val(d.status_kegiatan);
                    $("#edit_customer_name").val(d.nama_cust);
                    $("#edit_sales_name").val(d.nama_sales);
                    $("#edit_jadwal").val(d.jadwal);
                    
                    // Show or hide execution fields depending on status
                    if (d.status_kegiatan === 'selesai' || d.status_kegiatan === 'berjalan' || d.status_kegiatan === 'proses') {
                        $(".execution-field").show();
                        $("#edit_ci_at").val(d.ci_at);
                        $("#edit_co_at").val(d.co_at);
                        $("#edit_tipe_prospek").val(d.tipe_prospek);
                        $("#edit_no_invoice").val(d.no_invoice);
                        $("#edit_keterangan").val(d.keterangan);
                        $("#edit_catatan_visit").val(d.catatan_visit);
                    } else {
                        $(".execution-field").hide();
                    }
                    
                    // Open edit modal
                    const editModal = new bootstrap.Modal(document.getElementById('editVisitModal'));
                    editModal.show();
                } else {
                    alert("Gagal mengambil data rincian: " + res.message);
                }
            },
            error: function() {
                alert("Terjadi kesalahan koneksi server saat memuat rincian.");
            }
        });
    });

    // Submit Edit Form
    $("#editVisitForm").submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: "proses-edit-kunjungan.php",
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(res) {
                if (res.status === 'success') {
                    alert(res.message);
                    location.reload();
                } else {
                    alert("Gagal menyimpan perubahan: " + res.message);
                }
            },
            error: function() {
                alert("Terjadi kesalahan saat menyimpan perubahan.");
            }
        });
    });

    // ── DELETE/RESET VISIT ACTION ──
    $(".deleteVisitBtn").click(function() {
        const kegiatanId = $(this).data("id");
        const salesId = $(this).data("sales");
        const status = $(this).data("status");
        const custName = $(this).data("cust");
        
        let confirmMsg = "";
        if (status === 'selesai' || status === 'berjalan' || status === 'proses') {
            confirmMsg = `Apakah Anda yakin ingin menghapus laporan kunjungan ke "${custName}"?\n\nTindakan ini akan menghapus detail pengerjaan (Clock In/Out, Foto, Catatan) dan mengembalikan status kunjungan menjadi "Dijadwalkan" agar sales bisa melakukan kunjungan ulang.`;
        } else {
            confirmMsg = `Apakah Anda yakin ingin menghapus jadwal kunjungan ke "${custName}" secara permanen?`;
        }
        
        if (confirm(confirmMsg)) {
            $.ajax({
                url: "proses-hapus-kunjungan.php",
                type: "POST",
                data: { kegiatan_id: kegiatanId, sales_id: salesId, status_kegiatan: status },
                dataType: "json",
                success: function(res) {
                    if (res.status === 'success') {
                        alert(res.message);
                        location.reload();
                    } else {
                        alert("Gagal menghapus kunjungan: " + res.message);
                    }
                },
                error: function() {
                    alert("Terjadi kesalahan saat memproses penghapusan.");
                }
            });
        }
    });
});
</script>