<div class="col-lg-12">
    <div class="card h-100 py-3">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-2 lead font-weight-bold text-uppercase">Diselesaikan</h6>
                </div>
            </div>
        </div>
        <?php
        $current_date = date("Y-m-d"); // Today's date
        $tomorrow_date = date("Y-m-d", strtotime("+1 day")); // Tomorrow's date
        $current_time = date("H:i:s"); // Current time

        $sql = "SELECT k.*, t.nama AS nama_teknisi, c.nama AS nama_customer, c.nomor_tlp AS cust_nomor
        FROM kegiatan k
        LEFT JOIN teknisi t ON k.id_teknisi = t.id_teknisi
        LEFT JOIN customer c ON k.id_cust = c.id_cust
        WHERE k.status = 'Clear'
        GROUP BY k.kode_transaksi
        ORDER BY k.tgl_selesai DESC";

        $result = mysqli_query($conn, $sql);
        ?>
        <div class="card-body pb-0 p-0">
            <ul class="list-group m-0 mt-4 col-12" id="data-tek">

                <li class="list-group-item border-0 d-flex flex-column justify-content-between ps-0 mb-2 border-radius-lg d-md-block d-block">
                    <div class="row px-4">
                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Status</h6>
                            <span class="text-xs">/ Kegiatan</span>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Tanggal</h6>
                            <span class="text-xs">/ Jam</span>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Customer</h6>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Teknisi</h6>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Permintaan Dari</h6>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0  text-start text-md-center">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Aksi</h6>
                        </div>
                    </div>
                </li>
                <?php
                $groupedData = [];

                if (mysqli_num_rows($result) > 0) {
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result)) {
                        $kodeTransaksi = $row['kode_transaksi'];

                ?>
                        <li class="list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-0 mb-2 border-radius-lg d-md-block d-block">
                            <div class="row px-4">

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm">Selesai</h6>
                                    <span class="text-xs"><?php echo $row['jenis']; ?></span>
                                </div>

                                <?php


                                $datetime = $row["tgl_selesai"];

                                // Format dan tampilkan tanggal dan waktu
                                $formattedTime = date("H:i", strtotime($datetime));
                                $formattedDate = date("d-m-Y", strtotime($datetime));
                                $tanggal_sekarang = date("d-m-Y");
                                ?>
                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $formattedDate; ?></h6>
                                    <span class="text-xs text-uppercase"><?php echo $formattedTime; ?></span>
                                </div>
                                <?php


                                $nomorHandphone = $row['cust_nomor'];

                                // Cek apakah nomor handphone dimulai dengan angka 0
                                if (substr($nomorHandphone, 0, 1) === '0') {
                                    // Ganti angka 0 dengan 62
                                    $nomorHandphone = '62' . substr($nomorHandphone, 1);
                                }

                                ?>

                                <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                                    <h6 class="text-dark font-weight-bold text-sm"><?php echo $row['nama_customer']; ?></h6>
                                    <span class="text-xs text-uppercase"><a href="https://api.whatsapp.com/send?phone=<?php echo $nomorHandphone; ?>" target="_blank"><?php echo $row['cust_nomor']; ?></a></span>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-center align-items-center d-flex">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm">
                                        <?php
                                        // Gunakan prepared statement untuk mencegah serangan SQL Injection
                                        $selAllTek = "SELECT kegiatan.*, teknisi.nama FROM kegiatan JOIN teknisi ON teknisi.id_teknisi = kegiatan.id_teknisi WHERE kode_transaksi = ? AND status != 'N' AND status = 'Clear'";
                                        $stmt = mysqli_prepare($conn, $selAllTek);
                                        mysqli_stmt_bind_param($stmt, "s", $kodeTransaksi);
                                        mysqli_stmt_execute($stmt);
                                        $resAllTek = mysqli_stmt_get_result($stmt);
                                
                                        while($rowAllTek = mysqli_fetch_assoc($resAllTek)){
                                            echo $rowAllTek['nama'] . "<br>";
                                        }
                                        ?>
                                    </h6>
                                </div>


                                <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-center align-items-center d-flex">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $row['req_by']; ?></h6>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0 d-flex justify-content-center align-items-center pt-1">
                                    <!-- <button class="btn btn-info view-btn w-25 p-2 text-center me-1" data-id="<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">visibility</i></button> -->
                                    <a class="btn btn-info view-btn w-25 p-2 text-center me-1" href="view-kegiatan.php?kode_transaksi=<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">visibility</i></a>
                                    <!--<button class="btn btn-warning edit-btn w-25 p-2 text-center me-1" data-id="<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">autorenew</i></button>-->
                                    <button class="btn btn-danger delete-btn w-25 p-2 text-center" data-kode="<?php echo $kodeTransaksi; ?>" data-nama="<?php echo $nmUser; ?>"><i class="far fa-trash-alt"></i></button>
                                </div>


                            </div>
                        </li>


                <?php



                        $no++;
                    }
                } else {
                    echo "<div class='ms-4 text-sm'>Tidak ada kegiatan untuk Hari Ini</div>";
                }




                ?>

            </ul>
        </div>
    </div>
</div>