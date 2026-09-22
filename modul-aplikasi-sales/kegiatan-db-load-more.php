<div class="col-lg-12 mt-4 mb-0">
    <div class="row">
        <div class="col-12">
            <button id="toggleLoadMore" type="button" class="btn bg-gradient-info font-weight-bold" style="font-size:16px;">Kegiatan Yang Akan Datang</button>
        </div>
    </div>
</div>
<div class="col-lg-12 mt-n3 mb-4" id="loadMoreX" style="display: block;">
<div class="card h-100 py-3" style="border-top-left-radius:0;">
        <?php

        $current_date = date("Y-m-d"); // Today's date
        $tomorrow_date = date("Y-m-d", strtotime("+1 day")); // Tomorrow's date
        $current_time = date("H:i:s"); // Current time

        $sql = "SELECT v.*, s.nama AS nama_sales, c.nama AS nama_customer, c.no_wa AS cust_nomor
                FROM visits v
                LEFT JOIN sales s ON s.id_sales = v.id_sales
                LEFT JOIN cust c ON v.id_cust = c.id_cust
                WHERE v.status != 'clear'
                GROUP BY v.kode_transaksi";

        $result = mysqli_query($conn, $sql);

        ?>
        <div class="card-body pb-0 p-0">
            <ul class="list-group m-0 mt-2 col-12" id="data-tek">

                <li class="list-group-item border-0 d-flex flex-column justify-content-between ps-0 mb-2 border-radius-lg d-none d-md-block">
                    <div class="row px-4">
                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Kegiatan</h6>
                            <span class="text-xs">/ Status</span>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Tanggal Visit</h6>
                            <span class="text-xs">/ Jam</span>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Customer</h6>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Sales</h6>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Keterangan</h6>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0  text-start text-md-center">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Aksi</h6>
                        </div>
                    </div>
                </li>
                <?php
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $kodeTransaksi = $row['kode_transaksi'];
                        $status = $row['status'];
                        switch ($status) {
                            case 'dijadwalkan':
                                $status = 'Dijadwalkan';
                                break;
                            case 'clear':
                                $status = 'Selesai';
                                break;
                            case 'on process':
                                $status = 'Dalam Proses';
                                break;
                        }
                        $tgl_visits = $row['tgl_visits'];
                        $id_cust = $row['id_cust'];
                        $id_sales = $row['id_sales'];
                        $nama_cust = $row['nama_customer'];
                        $cust_nomor = $row['cust_nomor'];
                        $ket_visits = $row['keterangan_visits'];

                        $isMobile = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $_SERVER['HTTP_USER_AGENT']);
                    
                        // Include the appropriate menu file based on device size
                        if ($isMobile) {
                            include "kegiatan-db-load-more-mobile.php";
                        } else {
                            include "kegiatan-db-load-more-dekstop.php";
                        }

                    }
                } else {
                    echo "<div class='ms-4 text-sm'>Tidak ada kegiatan untuk Hari Ini</div>";
                }

                ?>

            </ul>
        </div>
    </div>
</div>