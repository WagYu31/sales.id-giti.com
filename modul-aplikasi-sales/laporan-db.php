<?php

        if (isset($_GET['cariBulanTahun']) && !empty($_GET['cariBulanTahun'])) {
            $current_date = $_GET['cariBulanTahun'];
        } else {
            $current_date = date("Y-m"); // Today's date
        }
?>
<div class="col-lg-12">
    <div class="card h-100 py-3">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 col-md-6 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-2 lead font-weight-bold text-uppercase">Laporan Target Tercapai Teknisi</h6>
                </div>
                <div class="col-12 col-md-6 d-flex align-items-center justify-content-center flex-row">
                    <form method="GET" action="" class="col-12 col-md-12 d-flex align-items-center justify-content-center flex-row">
                        <input type="month" class="form-control border p-2 bg-outline-info w-70" name="cariBulanTahun" value="<?php echo $current_date;?>">
                        <button class="btn bg-gradient-info w-30 mt-3 ms-2">Cari</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        $tomorrow_date = date("Y-m-d", strtotime("+1 day")); // Tomorrow's date
        $current_time = date("H:i:s"); // Current time

        $sql = "SELECT k.*, t.id_teknisi, t.nama
        FROM kegiatan k
        JOIN teknisi t ON t.id_teknisi = k.id_teknisi
        WHERE k.status = 'Clear' AND DATE_FORMAT(k.tgl_inv, '%Y-%m') = '$current_date'
        GROUP BY k.id_teknisi";
        $result = mysqli_query($conn, $sql);

        ?>
        <div class="card-body pb-0 p-0">
            <div class="col-12">
                <p class="text-dark ms-4">
                Bulan 
                <?php
                    $bt = formatTanggal('MMMM yyyy', $current_date);
                    echo $bt;
                ?>
                </p>
            </div>
            <ul class="list-group m-0 mt-4 col-12" id="data-tek">

                    <li class="list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-0 mb-2 border-radius-lg d-md-block d-block">
                        <div class="row px-4">

                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2">
                                    Teknisi
                                </h6>
                            </div>
                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2 text-center">
                                    Jumlah Kegiatan Selesai
                                </h6>
                            </div>
                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2 text-center">
                                    Jumlah Invoice
                                </h6>
                            </div>
                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2 text-center">
                                    Total Target Tercapai
                                </h6>
                            </div>

                            <div class="col-12 col-md-10 mb-2 mb-md-0 text-left">


                            </div>

                        </div>
                    </li>

                <?php
                while ($row = mysqli_fetch_assoc($result)) {
                    $idT = $row['id_teknisi'];
                    $namaT = $row['nama'];
                    $bonus = $row['bonus'];
                    $totalBonus += $bonus;
                    $totalBonusFormatted = "Rp " . number_format($totalBonus, 0, ',', '.');

                ?>
                    <li class="list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-0 mb-2 border-radius-lg d-md-block d-block">
                        <div class="row px-4">

                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2">
                                    <?php
                                    echo $namaT;
                                    ?>
                                </h6>
                            </div>
                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2 text-center">
                                    <?php
                                    // Query untuk menghitung jumlah data
                                    $sqlK = "SELECT COUNT(*) AS total_data FROM kegiatan 
                                            WHERE id_teknisi = $idT AND status = 'Clear' 
                                            AND DATE_FORMAT(tgl_selesai, '%Y-%m') = '$current_date'";
                                    
                                    // Eksekusi query
                                    $resultK = mysqli_query($conn, $sqlK);
                                    
                                    // Periksa apakah query berhasil dieksekusi
                                    if ($resultK) {
                                        // Ambil hasil query
                                        $rowK = mysqli_fetch_assoc($resultK); // Perbaikan: gunakan $resultK
                                        // Total data
                                        $totalData = $rowK['total_data'];
                                        echo $totalData;
                                    } else {
                                        echo "Error: " . mysqli_error($conn);
                                    }
                                    
                                    ?>
                                </h6>
                            </div>
                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2 text-center">
                                    <?php
                                    // Query untuk menghitung jumlah data
                                    $sqlInv = "SELECT COUNT(*) AS total_data FROM kegiatan 
                                            WHERE id_teknisi = $idT AND invoice IS NOT NULL
                                            AND DATE_FORMAT(tgl_inv, '%Y-%m') = '$current_date'";
                                    
                                    // Eksekusi query
                                    $resultInv = mysqli_query($conn, $sqlInv);
                                    
                                    // Periksa apakah query berhasil dieksekusi
                                    if ($resultInv) {
                                        // Ambil hasil query
                                        $rowInv = mysqli_fetch_assoc($resultInv); // Perbaikan: gunakan $resultK
                                        // Total data
                                        $totalData = $rowInv['total_data'];
                                        echo $totalData;
                                    } else {
                                        echo "Error: " . mysqli_error($conn);
                                    }
                                    
                                    ?>
                                </h6>
                            </div>
                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2 text-center">
                                    <?php
                                    echo $totalBonusFormatted;
                                    ?>
                                </h6>
                            </div>

                            <div class="col-12 col-md-10 mb-2 mb-md-0 text-left">


                            </div>

                        </div>
                    </li>
                <?php
                }
                ?>
                <li class="list-group-item border-0 d-flex flex-column justify-content-center align-items-center ps-0 mb-2 border-radius-lg d-md-block d-block">
                        <div class="row px-4">
                            
                            <div class="col-6 col-md-9 mb-md-0">
                            </div>
                            <div class="col-6 col-md-3 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm p-2 text-center">
                                    <?php
                                        $query = "SELECT SUM(bonus) AS total_bonus FROM kegiatan WHERE DATE_FORMAT(tgl_inv, '%Y-%m') = '$current_date'";
                                        $result = mysqli_query($conn, $query);
                                        
                                        if ($result) {
                                            $row = mysqli_fetch_assoc($result);
                                            $total_bonus = $row['total_bonus'];
                                            echo "Total Target Tercapai: Rp " . number_format($total_bonus, 0, ',', '.');
                                        } else {
                                            echo "Error: " . mysqli_error($conn);
                                        }

                                        
                                    ?>
                                </h6>
                            </div>
                        </div>
                    
                </li>
            </ul>
        </div>
    </div>
</div>