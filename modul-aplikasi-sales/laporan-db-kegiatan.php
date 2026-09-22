<?php

if (isset($_GET['cariTgl']) && !empty($_GET['cariTgl'])) {
    $current_date = $_GET['cariTgl'];
} else {
    $current_date = date("Y-m-d"); // Today's date
}
?>
<div class="col-lg-12">
    <div class="card h-100 py-3">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 col-md-6 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-2 lead font-weight-bold text-uppercase">Laporan Kegiatan</h6>
                </div>
                <div class="col-12 col-md-6 d-flex align-items-center justify-content-center flex-row">
                    <!--<form method="GET" action="" class="col-12 col-md-12 d-flex align-items-center justify-content-center flex-row">-->
                    <!--    <input type="date" class="form-control border p-2 bg-outline-info w-70" name="cariTgl" value="<?php echo $current_date; ?>">-->
                    <!--    <button class="btn bg-gradient-info w-30 mt-3 ms-2">Cari</button>-->
                    <!--</form>-->
                </div>
            </div>
        </div>
        <?php
        $tomorrow_date = date("Y-m-d", strtotime("+1 day")); // Tomorrow's date
        $current_time = date("H:i:s"); // Current time

        // Kueri SQL untuk memilih data dengan tanggal request, tanggal mulai, atau tanggal selesai sama dengan hari ini dan status tidak sama dengan 'N'
        $sql = "SELECT kegiatan.*, customer.nama AS nama_cust, customer.id_cust
                FROM kegiatan
                INNER JOIN customer ON kegiatan.id_cust = customer.id_cust
                WHERE 
                kegiatan.status != 'N'
                AND kegiatan.id_teknisi IS NOT NULL
                GROUP BY kegiatan.kode_transaksi
                ORDER BY kegiatan.tgl_request DESC";

        $result = mysqli_query($conn, $sql);

        ?>
        <div class="card-body pb-0 p-0">
            <?php
            
            $tanggal = date("d", strtotime($current_date));
            $tahun = date("Y", strtotime($current_date));
            // Konversi format tanggal dari Y-m-d menjadi d - M - Y
            $formatted_date = date("d - M - Y", strtotime($current_date));
            
            // Mendapatkan nama hari dalam bahasa Indonesia
            $day_in_indonesian = formatTanggal('EEEE', $current_date);
            
            // Mendapatkan nama bulan dalam bahasa Indonesia
            $month_in_indonesian = formatTanggal('MMMM', $current_date);
            
            ?>
            <!--<p class="ms-4 mt-2 text-dark font-weight-bold"><?php echo $day_in_indonesian . ", " . $tanggal . " " . $month_in_indonesian . " " . $tahun; ?></p>-->

            <ul class="list-group m-0 mt-2 col-12" id="data-tek">


                <?php
                $groupedData = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $idC = $row['id_cust'];
                    $namaC = $row['nama_cust'];
                    $kodeTransaksi = $row['kode_transaksi'];
                    $invoice = $row['invoice'];
                ?>
                    <li class="list-group-item border-0 d-flex flex-column justify-content-start align-items-start justify-content-md-between align-items-md-center ps-0 mb-2 border-radius-lg d-md-block d-block">
                        <div class="row px-4">

                            <div class="col-12 col-md-12 mb-md-0 bg-gradient-dark d-flex flex-column justify-content-center align-items-start">
                                <h6 class="mb-1 text-white font-weight-bold text-sm p-2">
                                    <?php
                                    if($invoice != NULL){
                                        echo $namaC . " | <span style='color:yellow;'> No. Invoice : " . $invoice . "<button style='background:none; border:none;' class='inv-btn' data-bs-toggle='modal' data-bs-target='#invModal' data-invdata='$invoice' data-kode='$kodeTransaksi'><i class='material-icons opacity-10 text-xs ms-2 text-danger bg-white' style='border-radius:50%;padding:1px;padding-left:2px; padding-right:2px;'>replay</i></button></span>";
                                    }
                                    else{
                                        echo $namaC;
                                    }
                                    
                                    ?>
                                </h6>
                            </div>

                            <div class="col-12 col-md-10 mb-2 mb-md-0 text-left">


                                <?php
                                $sqlLapTek = "SELECT kegiatan.*, teknisi.nama AS nama_teknisi, teknisi.id_teknisi
                                FROM kegiatan 
                                JOIN teknisi ON teknisi.id_teknisi = kegiatan.id_teknisi
                                WHERE id_cust = $idC AND kode_transaksi = '$kodeTransaksi' AND status != 'N'";
                                // AND (DATE(tgl_request) = '$current_date' OR DATE(tgl_mulai) = '$current_date' OR DATE(tgl_selesai) = '$current_date')";
                                $resLapTek = mysqli_query($conn, $sqlLapTek);
                                if (mysqli_num_rows($resLapTek) > 0) {

                                ?>
                    <li class="list-group-item border-0 d-flex flex-column justify-content-between ps-0 mb-2 border-radius-lg d-md-block d-block">
                        <div class="row px-4">
                            <div class="col-6 w-md-10 mb-2 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm">Status</h6>
                                <span class="text-xs">/ Kegiatan</span>
                            </div>

                            <div class="col-6 w-md-15 mb-2 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm">Teknisi</h6>
                            </div>

                            <div class="col-6 w-md-15 mb-2 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm">Request</h6>
                                <span class="text-xs">Tanggal / Jam</span>
                            </div>

                            <div class="col-6 w-md-15 mb-2 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm"> Mulai</h6>
                                <span class="text-xs">Tanggal / Jam</span>
                            </div>

                            <div class="col-6 w-md-15 mb-2 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm">Selesai</h6>
                                <span class="text-xs">Tanggal / Jam</span>
                            </div>

                            <div class="col-6 w-md-10 mb-2 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm">Rincian Waktu</h6>
                            </div>

                            <div class="col-6 w-md-15 mb-2 mb-md-0 text-start text-md-center">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm text-start text-md-center">Bonus / Denda</h6>
                            </div>



                        </div>
                    </li>
                    <?php

                                    while ($rowLT = mysqli_fetch_assoc($resLapTek)) {
                                        
                                        $idT = $rowLT["id_teknisi"];
                                        $ketFinish = $rowLT['ket_finish'];
                                        $sqlMR = "SELECT *
                                                FROM kegiatan 
                                                WHERE id_teknisi = '$idT' AND kode_transaksi = '$kodeTransaksi' ORDER BY id_kegiatan ASC LIMIT 1";
                                                $resMR = mysqli_query($conn, $sqlMR);
                                                $rowMR = mysqli_fetch_assoc($resMR);
                                                $ket_finish = $rowMR["ket_finish"];
                                
                                        $tanggal_sekarang = date("d-m-Y");
                                        $datetime = $rowMR["tgl_request"];
                                        $formattedDate = ($datetime && $datetime != '0000-00-00 00:00:00') ? date("d-m-Y", strtotime($datetime)) : '-';
                                        $formattedTime = ($datetime && $datetime != '0000-00-00 00:00:00') ? date("H:i", strtotime($datetime)) : '-';

                                        $tglMulai = $rowMR["tgl_mulai"];
                                        $formattedDateMulai = ($tglMulai && $tglMulai != '0000-00-00 00:00:00') ? date("d-m-Y", strtotime($tglMulai)) : '-';
                                        $formattedTimeMulai = ($tglMulai && $tglMulai != '0000-00-00 00:00:00') ? date("H:i", strtotime($tglMulai)) : '-';

                                        $tglSelesai = $rowLT["tgl_selesai"];
                                        
                                        $sqlS = "SELECT tgl_selesai
                                                 FROM kegiatan 
                                                 WHERE id_teknisi = '$idT' AND kode_transaksi = '$kodeTransaksi' 
                                                 ORDER BY id_kegiatan DESC 
                                                 LIMIT 1";
                                        $resS = mysqli_query($conn, $sqlS);
                                        
                                        // Cek apakah ada hasil query
                                        if ($resS) {
                                            // Ambil data dari hasil query
                                            $rowS = mysqli_fetch_assoc($resS);
                                            $tglSelesaiTerakhir = $rowS['tgl_selesai'];
                                        
                                            // Jika tgl_selesai terakhir NULL atau '0000-00-00 00:00:00', ambil data sebelumnya
                                            if ($tglSelesaiTerakhir == NULL || $tglSelesaiTerakhir == '0000-00-00 00:00:00') {
                                                $sqlSBefore = "SELECT tgl_selesai
                                                               FROM kegiatan 
                                                               WHERE id_teknisi = '$idT' AND kode_transaksi = '$kodeTransaksi' AND status = 'Clear'
                                                               ORDER BY id_kegiatan DESC 
                                                               LIMIT 1, 1";
                                                $resSBefore = mysqli_query($conn, $sqlSBefore);
                                                
                                                // Cek apakah ada hasil query
                                                if ($resSBefore) {
                                                    // Ambil data tgl_selesai sebelumnya
                                                    $rowSBefore = mysqli_fetch_assoc($resSBefore);
                                                    $tglSelesaiTerakhir = $rowSBefore['tgl_selesai'];
                                                } else {
                                                    // Handle jika query sebelumnya tidak mengembalikan hasil
                                                    echo "Error: " . mysqli_error($conn);
                                                }
                                            }
                                        } else {
                                            // Handle jika query tidak mengembalikan hasil
                                            echo "Error: " . mysqli_error($conn);
                                        }

                                        
                                        $formattedDateSls = ($tglSelesaiTerakhir && $tglSelesaiTerakhir != '0000-00-00 00:00:00') ? date("d-m-Y", strtotime($tglSelesaiTerakhir)) : '-';
                                        $formattedTimeSls = ($tglSelesaiTerakhir && $tglSelesaiTerakhir != '0000-00-00 00:00:00') ? date("H:i", strtotime($tglSelesaiTerakhir)) : '-';

                    ?>
                        <li class="list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-0 mb-2 border-radius-lg d-md-block d-block">
                            <div class="row px-4">

                                <div class="col-6 w-md-10 mb-2 mb-md-0">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm">
                                        <?php
                                        // Mengubah nilai status menjadi teks yang diinginkan
                                        $status = $rowLT['status'];
                                        switch ($status) {
                                            case 'N':
                                            case 'Pause':
                                                echo 'Lanjut Nanti';
                                                break;
                                            case 'Pending':
                                                echo 'Dijadwalkan';
                                                break;
                                            case 'On Process':
                                                echo 'Diproses';
                                                break;
                                            case 'Clear':
                                                echo 'Selesai';
                                                break;
                                            default:
                                                echo $status; // Jika status tidak sesuai dengan kondisi di atas, biarkan nilainya tetap
                                        }
                                        ?>
                                    </h6>
                                    <span class="text-xs"><a href="view-kegiatan.php?kode_transaksi=<?php echo $rowLT['kode_transaksi']; ?>&id_teknisi=<?php echo $idT; ?>"><?php echo $rowLT['kode_transaksi']; ?></a></span>
                                </div>

                                <div class="col-6 w-md-15 mb-2 mb-md-0 text-left">
                                    <h6 class="text-dark font-weight-bold text-sm"><?php echo $rowLT['nama_teknisi']; ?></h6>
                                </div>


                                <div class="col-6 w-md-15 mb-2 mb-md-0">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $formattedDate; ?></h6>
                                    <span class="text-xs text-uppercase"><?php echo $formattedTime; ?></span>
                                </div>
                                <div class="col-6 w-md-15 mb-2 mb-md-0">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $formattedDateMulai; ?></h6>
                                    <span class="text-xs text-uppercase"><?php echo $formattedTimeMulai; ?></span>
                                </div>
                                <div class="col-6 w-md-15 mb-2 mb-md-0">
                                    <?php
                                        if($ketFinish == "Diselesaikan oleh Admin"){
                                            ?>
                                                <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $ketFinish; ?></h6>
                                            <?php
                                        }else{
                                            ?>
                                                <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $formattedDateSls; ?></h6>
                                                <span class="text-xs"><?php echo $formattedTimeSls; ?></span>
                                            <?php
                                        }
                                    ?>
                                </div>
                                <?php
                                        // Menghitung selisih waktu antara tgl_request dan tgl_mulai jika tgl_mulai tidak NULL dan bukan '0000-00-00 00:00:00'
                                        if ($rowLT["tgl_mulai"] && $rowLT["tgl_mulai"] != '0000-00-00 00:00:00') {
                                            if ($rowLT["tgl_request"] < $rowLT["tgl_mulai"]) {
                                                $datetime_request = strtotime($rowLT["tgl_request"]);
                                                $datetime_mulai = strtotime($rowLT["tgl_mulai"]);
                                                $telat_in_seconds = $datetime_mulai - $datetime_request;

                                                // Mengonversi selisih waktu menjadi jam, menit, dan detik
                                                $telat_hours = floor($telat_in_seconds / 3600);
                                                $telat_minutes = floor(($telat_in_seconds % 3600) / 60);
                                                $telat_seconds = $telat_in_seconds % 60;

                                                // Format selisih waktu ke dalam string "x jam, y menit, z detik"
                                                $telat_formatted = '';
                                                if ($telat_hours > 0) {
                                                    $telat_formatted .= $telat_hours . ' jam, ';
                                                }
                                                if ($telat_minutes > 0) {
                                                    $telat_formatted .= $telat_minutes . ' menit, ';
                                                }
                                                $telat_formatted .= $telat_seconds . ' detik';
                                            } else {
                                                $telat_formatted = '0';
                                            }
                                        } else {
                                            $telat_formatted = '-';
                                        }
                                ?>

                                <div class="col-6 w-md-5 mb-2 mb-md-0">
                                    <!--<h6 class="mb-1 text-danger font-weight-bold text-sm"><?php echo $telat_formatted; ?></h6>-->
                                    <button class="btn bg-gradient-info text-white detailBtn" data-bs-toggle="modal" data-bs-target="#detailModal" data-id="<?php echo $idT; ?>" data-kode="<?php echo $kodeTransaksi; ?>">Lihat</button>
                                    <!-- <span class="text-xs text-uppercase"><?php echo $telat_formatted; ?></span> -->
                                </div>

                                <?php if ($rowLT['status'] == 'Clear') : ?>
                                    <?php if ($rowLT['bonus'] == NULL && $rowLT['denda'] == NULL) : ?>
                                        <div class="col-6 w-md-25 mb-2 mb-md-0 d-flex flex-row justify-content-start justify-content-md-center align-items-center text-center">
                                            <button class="btn bg-gradient-success me-2 p-2 bonus-btn" data-bs-toggle="modal" data-bs-target="#bonusModal" data-id="<?php echo $idT; ?>" data-kode="<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">add</i> Tambah</button>
                                        </div>
                                    <?php else : ?>
                                        <div class="col-6 w-md-15 mb-2 mb-md-0 d-flex flex-column text-end">
                                            <h6 class="mb-1 text-dark font-weight-bold text-sm text-end">Rp <?php echo number_format($rowLT['bonus'], 0, ',', '.'); ?></h6>
                                            <span class="text-xs text-uppercase">Bonus</span>
                                        </div>
                                        <div class="col-6 w-md-5 mb-2 mb-md-0 d-flex flex-column text-start">
                                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Rp <?php echo number_format($rowLT['denda'], 0, ',', '.'); ?></h6>
                                            <span class="text-xs text-uppercase">Denda</span>
                                        </div>
                                        <div class="col-6 w-md-5 mb-2 mb-md-0 d-flex flex-row justify-content-center align-items-center text-center">
                                            <button class="replay-button btn bg-gradient-warning px-1 py-2" data-id="<?php echo $rowLT['id_kegiatan'] ?>"><i class="material-icons opacity-10">replay</i></button>
                                        </div>

                                    <?php endif; ?>
                                <?php else : ?>
                                    <div class="col-6 w-md-25 mb-2 mb-md-0 d-flex flex-row justify-content-start justify-content-md-center align-items-center text-start text-md-center">
                                        <span class="text-xs text-uppercase text-danger">Kegiatan Belum Selesai</span>
                                    </div>
                                <?php endif; ?>


                            </div>
                        </li>
            <?php
                                    }
                                } else {
                                    echo "<li class='list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-4 mb-2 border-radius-lg d-md-block d-block'>Tidak ada kegiatan.</li>";
                                }
                            }

            ?>
        </div>

    </div>
    </li>

    </ul>
</div>
</div>
</div>