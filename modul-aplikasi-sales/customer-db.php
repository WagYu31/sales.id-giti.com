        <?php

            // Tangani pencarian
            if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
                $keyword = $_GET['keyword'];
                $sql = "SELECT c.id_cust, c.nama, c.no_wa, c.alamat, c.kota, COUNT(v.id_visits) AS total_visits
                        FROM cust c
                        LEFT JOIN visits v ON c.id_cust = v.id_cust
                        WHERE c.nama LIKE '%$keyword%' OR c.no_wa LIKE '%$keyword%' OR c.alamat LIKE '%$keyword%' OR c.kota LIKE '%$keyword%'
                        GROUP BY c.id_cust, c.nama, c.no_wa, c.alamat, .c.kota
                        ORDER BY total_visits DESC";
            } else {
                // Jika tidak ada kata kunci pencarian, tampilkan semua data
                $sql = "SELECT c.id_cust, c.nama, c.no_wa, c.alamat, c.kota, COUNT(v.id_visits) AS total_visits
                        FROM cust c
                        LEFT JOIN visits v ON c.id_cust = v.id_cust
                        GROUP BY c.id_cust, c.nama, c.no_wa, c.alamat, c.kota
                        ORDER BY total_visits DESC";
            }

            $result = mysqli_query($conn, $sql);
        ?>
<div class="container">
    <div class="card p-4">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-md-n3 ms-0 lead font-weight-bold text-uppercase">Data Customer</h6>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12 col-md-6 d-flex flex-row justify-content-start align-items-center">
                    <a href="tambah-customer.php" class="btn bg-gradient-info me-2"><i class='fas fa-plus me-2'></i>Tambah</a>
                    <a href="customer.php" class="btn bg-gradient-info">Refresh</a>
                </div>
                <div class="col-12 col-md-6">
                    <form method="GET" action="">
                        <div class="mb-3 d-flex flex-row justify-content-start align-items-center">
                            <input type="text" class="form-control border w-60 w-md-80" style="border-radius: 7px; border-bottom-right-radius:0; border-top-right-radius:0; padding:7.5px;" placeholder="Cari berdasarkan nama, nomor telepon, atau alamat" name="keyword" value="<?php echo $keyword;?>">
                            <button class="btn bg-gradient-primary w-40 w-md-20" style="border-radius: 7px; border-bottom-left-radius:0; border-top-left-radius:0; margin-top:15.5px;" type="submit">Cari</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body pb-0 p-0 text-center mt-3">
            <!-- Tampilan data teknisi -->
            <?php
            $no = 1;
            if (mysqli_num_rows($result) > 0) {
            ?>
                <div class="row border-bottom font-weight-bold d-none d-md-flex flex-row justify-content-start justify-content-md-center align-items-center">
                    <div class="w-5 d-none d-md-flex">No</div>
                    
                    <div class="w-15 d-none d-md-flex">Nama</div>
                    <div class="col-6 d-flex d-md-none">Nama</div>
                    
                    <div class="w-15 d-none d-md-flex text-start">Jumlah Permintaan</div>
                    <div class="col-6 d-flex d-md-none">No WhatsApp</div>
                    
                    <div class="w-15 d-none d-md-flex">No WhatsApp</div>
                    <div class="col-6 d-flex d-md-none text-start">Jumlah Permintaan</div>
                    
                    <div class="w-40 d-none d-md-flex">Alamat</div>
                    <div class="col-6 d-flex d-md-none">Alamat</div>
                    
                    <div class="w-10 d-none d-md-flex">Aksi</div>
                    <div class="col-6 d-flex d-md-none"></div>
                    <div class="col-6 d-flex d-md-none">Aksi</div>
                </div>
                <?php
                while ($row = mysqli_fetch_assoc($result)) {
                ?>
                    <div class="row border-bottom py-3" style="font-size:14px;">
                        <div class="w-5 d-none d-md-flex"><?= $no ?></div>
                        
                        <div class="w-15 text-start d-none d-md-flex">
                            <?php $id_cust = $row['id_cust'];
                            echo "<a href='customer-detail.php?id_cust=" . $id_cust . "'> " . $row['nama'] . "</a>";
                            ?>
                        </div>
                        <div class="col-6 d-flex d-md-none text-start">
                            <?php $id_cust = $row['id_cust'];
                            echo "<a href='customer-detail.php?id_cust=" . $id_cust . "'> " . $row['nama'] . "</a>";
                            ?>
                        </div>
                        
                        <div class="w-15 d-none d-md-flex justify-content-center">
                            <?php
                            $id_cust = $row["id_cust"];
                            $queryTotalKegiatan = "SELECT COUNT(*) AS total_visits FROM visits WHERE id_cust = $id_cust GROUP BY kode_transaksi";
                            $resultTotalKegiatan = mysqli_query($conn, $queryTotalKegiatan);
                            if(mysqli_num_rows($resultTotalKegiatan) > 0) {
                                $totalKegiatan = mysqli_fetch_assoc($resultTotalKegiatan)["total_visits"];
                                echo $totalKegiatan;
                            } else {
                                $totalKegiatan = 0;
                                echo $totalKegiatan;
                            }
                            ?>
                        </div>
                        <div class="col-6 d-flex d-md-none">
                            <a href="https://api.whatsapp.com/send?phone=<?= $row['no_wa'] ?>" target="_blank"><?= $row['no_wa'] ?></a>
                        </div>
                        
                        <div class="w-15 d-none d-md-flex">
                            <a href="https://api.whatsapp.com/send?phone=<?= $row['no_wa'] ?>" target="_blank"><?= $row['no_wa'] ?></a>
                        </div>
                        <div class="col-6 d-flex d-md-none">
                            <?php
                            $id_cust = $row["id_cust"];
                            $queryTotalKegiatan = "SELECT COUNT(*) AS total_visits FROM visits WHERE id_cust = $id_cust GROUP BY kode_transaksi";
                            $resultTotalKegiatan = mysqli_query($conn, $queryTotalKegiatan);
                            if(mysqli_num_rows($resultTotalKegiatan) > 0) {
                                $totalKegiatan = mysqli_fetch_assoc($resultTotalKegiatan)["total_visits"];
                                echo $totalKegiatan . " Permintaan";
                            } else {
                                $totalKegiatan = 0;
                                echo $totalKegiatan . " Permintaan";
                            }
                            ?></div>
                        
                       <?php
                            $alamatCS = $row['alamat'];
                            ${"makeLinksClickable$no"} = function ($text) {
                                $pattern = '/(http|https|ftp):\/\/[^\s]+/i';
                                $replacement = '<a href="$0" target="_blank">$0</a>';
                                $text = preg_replace($pattern, $replacement, $text);
                        
                                return $text;
                            };
                            $linkClick = ${"makeLinksClickable$no"};
                            $CL = $linkClick($alamatCS);
                        ?>
                        <div class="w-40 text-start d-none d-md-flex flex-column wrap-text"><?= $CL; ?></div>
                        <div class="col-6 d-flex d-md-none flex-column text-start wrap-text"><?= $CL; ?></div>

                        <div class="w-10 d-none d-md-flex">
                            <a href='edit-customer.php?id=<?= $row["id_cust"] ?>' class='btn btn-warning btn-sm me-2 ' title='Edit' style='height: 33px;'><i class='far fa-edit' style='font-size: 12px;'></i></a>
                            <a href='delete.php?id=<?= $row["id_cust"] ?>' class='btn btn-danger btn-sm' title='Delete' style='height: 33px;'><i class='far fa-trash-alt' style='font-size: 12px;'></i></a>
                        </div>
                        <div class="col-6 d-flex d-md-none"></div>
                        <div class="col-6 d-flex d-md-none mt-1">
                            <a href='edit-customer.php?id=<?= $row["id_cust"] ?>' class='btn btn-warning btn-sm mr-2' title='Edit'><i class='far fa-edit' style='font-size: 12px;'></i></a>
                            <a href='delete.php?id=<?= $row["id_cust"] ?>' class='btn btn-danger btn-sm ms-2' title='Delete'><i class='far fa-trash-alt' style='font-size: 12px;'></i></a>
                        </div>
                    </div>
            <?php
                    $no++;
                }
            } else {
                echo "<div class='row'><div class='col'><p>Tidak ada data customer.</p></div></div>";
            }
            ?>
        </div>
    </div>
</div>