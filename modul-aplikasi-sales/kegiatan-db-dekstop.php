<li class="list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-0 mb-2 border-radius-lg d-md-block">
    <div class="row px-4">
        <?php
        // STATUS KEGIATAN
        $statusKegiatan = strtolower($data['status'][0]);
        switch ($statusKegiatan) {
            case 'dijadwalkan':
                $updatedStatus = 'Dijadwalkan';
                break;
            case 'berjalan':
                $updatedStatus = 'Dalam Proses';
                break;
            case 'selesai':
                $updatedStatus = 'Selesai';
                break;
            case 'dibatalkan':
                $updatedStatus = 'Dibatalkan';
                break;
            default:
                $updatedStatus = ucfirst($statusKegiatan);
        }

        if ($pageNow != "Task") {
        ?>
            <div class="col-6 col-md-1 mb-2 mb-md-0 mt-1">
                <h6 class="mb-n1 text-dark font-weight-bold text-sm">
                    <?php echo ucwords(strtolower($data['kegiatan'][0])); ?>
                </h6>
                <span class="text-xs"><?php echo $updatedStatus; ?></span>
            </div>
        <?php } ?>

        <?php
        // TANGGAL & JAM
        $datetime = $data['jadwal'][0];
        $formattedTime = date("H:i", strtotime($datetime));
        $formattedDate = date("d/m/Y", strtotime($datetime));
        ?>

        <?php if ($pageNow != 'Task') { ?>
            <div class="col-6 col-md-1 mb-2 mb-md-0 text-center d-flex justify-content-center align-items-center">
                <h6 class="mb-1 font-weight-bold text-sm"><?php echo $formattedTime; ?></h6>
            </div>
        <?php } else { ?>
            <div class="col-6 col-md-1 mb-2 mb-md-0 text-center d-flex justify-content-center align-items-center flex-column">
                <h6 class="mb-1 font-weight-bold text-sm"><?php echo $formattedDate; ?></h6>
                <span class="mb-1 text-xs"><?php echo $formattedTime; ?></span>
            </div>
        <?php } ?>

        <?php
        // NOMOR WHATSAPP CUSTOMER
        $nomorHandphone = $data['cust_nomor'][0];
        if (substr($nomorHandphone, 0, 1) === '0') {
            $nomorHandphone = '62' . substr($nomorHandphone, 1);
        }
        ?>

        <!-- NAMA & NOMOR CUSTOMER -->
        <div class="col-6 col-md-2 mb-2 mt-n2 mb-md-0 text-center text-md-left d-flex justify-content-center align-items-center flex-column">
            <h6 class="text-dark font-weight-bold mb-0 text-sm"><?php echo $data['customer'][0]; ?></h6>
            <span class="text-xs text-uppercase"><a href="https://api.whatsapp.com/send?phone=<?php echo $nomorHandphone; ?>" target="_blank"><?php echo $data['cust_nomor'][0]; ?></a></span>
            <?php if (!empty($kodeTransaksi)): ?>
              <span style="font-size:9px; font-family:monospace; color:#3b82f6; font-weight:700; margin-top:2px;"><?php echo $kodeTransaksi; ?></span>
            <?php endif; ?>
        </div>

        <?php
        // TAMPILKAN SALES + STATUS
        $kodeTransaksi = $data['kode'][0]; // pastikan ini sudah diset
        $sqlGetKegiatanId = "SELECT id FROM kegiatan_sales WHERE kode = '$kodeTransaksi' ORDER BY id DESC LIMIT 1";
        $resultKegiatan = mysqli_query($conn, $sqlGetKegiatanId);
        $kegiatanRow = mysqli_fetch_assoc($resultKegiatan);
        $kegiatanId = $kegiatanRow['id'];

        $sqlGetSales = "SELECT s.nama AS nama_sales, ts.id_sales 
                        FROM team_kegiatan_sales ts 
                        LEFT JOIN sales s ON ts.id_sales = s.id
                        WHERE ts.id_kegiatan_sales = '$kegiatanId' AND ts.deleted_at IS NULL";
        $resultSales = mysqli_query($conn, $sqlGetSales);
        ?>

        <div class="<?php echo ($pageNow != "Task") ? 'col-6 col-md-3' : 'col-6 col-md-4'; ?> mb-2 mb-md-0">
            <?php while ($rowSales = mysqli_fetch_assoc($resultSales)) {
                $salesId = $rowSales['sales_id'];

                $sqlVisitStatus = "SELECT status FROM pelaksanaan_sales   
                                   WHERE kegiatan_id = '$kegiatanId' 
                                   AND sales_id = '$salesId' 
                                   ORDER BY id DESC LIMIT 1";

                $resultVisit = mysqli_query($conn, $sqlVisitStatus);
                $statusVisit = "Dijadwalkan";

                if ($visit = mysqli_fetch_assoc($resultVisit)) {
                    switch (strtolower($visit['status'])) {
                        case 'berjalan':
                            $statusVisit = "Berjalan";
                            break;
                        case 'selesai':
                            $statusVisit = "Selesai";
                            break;
                    }
                }
            ?>
                <div class="d-flex align-items-start px-1 mb-1 mt-1 justify-content-center">
                    <div class="col-6 px-0 text-start">
                        <h6 class="mb-0 text-dark font-weight-bold text-sm"><?php echo $rowSales['nama_sales']; ?></h6>
                    </div>
                    <div class="text-white rounded-pill p-1 px-2 text-center bg-<?php echo ($statusVisit == 'Selesai') ? 'success' : (($statusVisit == 'Berjalan') ? 'info' : 'secondary'); ?>" style="font-size:11px;">
                        <?php echo $statusVisit; ?>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- ALAMAT CUSTOMER -->
        <div class="<?php echo ($pageNow != 'Task') ? 'col-6 col-md-2' : 'col-6 col-md-3'; ?> px-0 text-start text-md-left d-flex justify-content-center align-items-center">
            <span class="text-xs"><?php echo $data['alamat'][0]; ?></span>
        </div>

        <!-- REQUEST DARI -->
        <div class="col-6 col-md-1 mb-2 mb-md-0 d-flex justify-content-between align-items-center text-center">
            <div class="text-left ms-3">
                <h6 class="mb-1 text-primary px-2 py-1 rounded-pill btn btn-outline-primary font-weight-bold text-xs">
                    <?php echo getInitials($data['request'][0]); ?>
                </h6>
            </div>
            <div class="text-right ms-4">
                <?php
                $createdAt = $data['created_at'][0];
                ?>
                <h6 class="mb-0 font-weight-bold" style="font-size:12px;"><?php echo date("d/M", strtotime($createdAt)); ?></h6>
                <span class="text-xs text-uppercase"><?php echo date("H:i", strtotime($createdAt)); ?></span>
            </div>
        </div>

        <!-- AKSI -->
        <?php if ($pageNow != 'Task') { ?>
            <div class="col-6 p-0 col-md-2 mb-2 mb-md-0 d-flex justify-content-md-end justify-content-start align-items-center pt-1">
                <a class="btn btn-info view-btn w-15 p-1 text-center me-1" href="view-kegiatan-sales.php?kode_transaksi=<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10" style="font-size:12px;">visibility</i></a>
                <button class="btn btn-warning edit-btn w-15 p-1 text-center me-1" data-id="<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10" style="font-size:12px;">autorenew</i></button>
                <a class="btn btn-danger delete-btn w-15 p-1 text-center" href="delete-kegiatan-sales.php?kode=<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10" style="font-size:12px;">delete</i></a>
            </div>
        <?php } else { ?>
            <div class="col-6 p-0 col-md-1 mb-2 mb-md-0 d-flex justify-content-md-end justify-content-start align-items-center pt-1">
                <a class="btn btn-info view-btn w-30 p-1 text-center me-1" href="view-kegiatan-sales.php?kode_transaksi=<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10" style="font-size:12px;">visibility</i></a>
            </div>
        <?php } ?>
    </div>
</li>
