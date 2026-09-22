                        <li class="list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-0 mb-2 border-radius-lg d-md-block">
                            <div class="row px-4">

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <span class="text-xs">Status</span>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $status; ?></h6>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <span class="text-xs">Tanggal / Jam</span>
                                </div>

                                <?php

                                // Format dan tampilkan tanggal dan waktu
                                $formattedTime = date("H:i", strtotime($tgl_visits));
                                $formattedDate = date("d-m-Y", strtotime($tgl_visits));
                                $tanggal_sekarang = date("d-m-Y");
                                if ($formattedDate > $tanggal_sekarang) {
                                ?>
                                    <div class="col-6 col-md-2 mb-2 mb-md-0">
                                        <h6 class="mb-1 font-weight-bold text-sm" style="color:blue;"><?php echo $formattedDate; ?> / <?php echo $formattedTime; ?></h6>
                                    </div>
                                <?php
                                } else {
                                ?>
                                    <div class="col-6 col-md-2 mb-2 mb-md-0">
                                        <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $formattedDate; ?> / <?php echo $formattedTime; ?></h6>
                                    </div>
                                <?php
                                }

                                $nomorHandphone = $cust_nomor;

                                // Cek apakah nomor handphone dimulai dengan angka 0
                                if (substr($nomorHandphone, 0, 1) === '0') {
                                    // Ganti angka 0 dengan 62
                                    $nomorHandphone = '62' . substr($nomorHandphone, 1);
                                }

                                ?>

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <span class="text-xs">Customer</span>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                                    <h6 class="text-dark font-weight-bold text-sm"><?php echo $nama_cust; ?></h6>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <span class="text-xs">No Telepon</span>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                                    <h6 class="text-dark font-weight-bold text-sm"><a href="https://api.whatsapp.com/send?phone=<?php echo $nomorHandphone; ?>" target="_blank"><?php echo $cust_nomor; ?></a></h6>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <span class="text-xs">Sales</span>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-md-center justify-content-start align-items-center d-flex">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm">
                                        <?php
                                        $sqlGetTek = "SELECT v.*, s.nama, s.id_sales FROM visits v
                                        JOIN sales s ON s.id_sales = v.id_sales
                                    WHERE v.kode_transaksi = '$kodeTransaksi'";
                                        $queryGetTek = mysqli_query($conn, $sqlGetTek);
                                        while ($rowGetTek = mysqli_fetch_assoc($queryGetTek)) {
                                            echo "<span class='text-dark'>" . $rowGetTek['nama'] . "</span><br>";
                                        }
                                        ?>
                                    </h6>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <span class="text-xs">Keterangan Visit</span>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-start justify-content-md-center align-items-center d-flex">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $ket_visits; ?></h6>
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                </div>

                                <div class="col-6 col-md-2 mb-2 mb-md-0 d-flex justify-content-md-center justify-content-start align-items-center pt-1">
                                    <!-- <button class="btn btn-info view-btn w-25 p-2 text-center me-1" data-id="<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">visibility</i></button> -->
                                    <a class="btn btn-info view-btn w-25 p-2 text-center me-1" href="view-kegiatan.php?kode_transaksi=<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">visibility</i></a>
                                    <button class="btn btn-warning edit-btn w-25 p-2 text-center me-1" data-id="<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">autorenew</i></button>
                                    <button class="btn btn-danger delete-btn w-25 p-2 text-center" data-kode="<?php echo $kodeTransaksi; ?>" data-nama="<?php echo $nmUser; ?>"><i class="far fa-trash-alt"></i></button>
                                </div>


                            </div>
                        </li>