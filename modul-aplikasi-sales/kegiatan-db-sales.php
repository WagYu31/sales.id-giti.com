<div class="col-lg-12">
    <div class="card h-100 py-3">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-2 lead font-weight-bold text-uppercase">Status Kegiatan</h6>
                </div>
            </div>
        </div>
        <?php
        
        $sqlSel = "SELECT * FROM loewix WHERE nik = $nikSesi";
        $resSel = mysqli_query($conn, $sqlSel);
        $rowSel = mysqli_fetch_assoc($resSel);
        $namaUser = $rowSel['nama'];
        
        $current_date = date("Y-m-d"); // Today's date
        $tomorrow_date = date("Y-m-d", strtotime("+1 day")); // Tomorrow's date
        $current_time = date("H:i:s"); // Current time
        $nmUser = "Aulia Ella Miranda";
        $sql = "SELECT k.*, t.nama AS nama_teknisi, c.nama AS nama_customer, c.nomor_tlp AS cust_nomor
            FROM kegiatan k
            LEFT JOIN teknisi t ON k.id_teknisi = t.id_teknisi
            LEFT JOIN customer c ON k.id_cust = c.id_cust
            WHERE k.req_by = '$namaUser' AND k.status != 'N'
            GROUP BY k.kode_transaksi
            ORDER BY k.tgl_request DESC";

        $result = mysqli_query($conn, $sql);

        ?>
        <div class="card-body pb-0 p-0">
            <ul class="list-group m-0 mt-4 col-12" id="data-tek">

                <li class="list-group-item border-0 d-flex flex-column justify-content-between ps-0 mb-2 border-radius-lg d-md-block">
                    <div class="row px-4">
                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Status</h6>
                            <span class="text-xs">/ Kegiatan</span>
                        </div>

                        <div class="col-6 col-md-2 mb-2 mb-md-0">
                            <h6 class="mb-1 text-dark font-weight-bold text-sm">Tanggal Request</h6>
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
                    while ($row = mysqli_fetch_assoc($result)) {
                        $kodeTransaksi = $row['kode_transaksi'];

                        if (!isset($groupedData[$kodeTransaksi])) {
                            $groupedData[$kodeTransaksi] = [
                                'tgl_request' => [],
                                'tgl_reschedule' => [],
                                'tgl_mulai' => [],
                                'customer' => [],
                                'cust_nomor' => [],
                                'teknisi' => [],
                                'jenis' => [],
                                'keterangan' => [],
                                'req_by' => [],
                                'status' => [],
                                'id_kegiatan' => []
                            ];
                        }

                        $groupedData[$kodeTransaksi]['tgl_request'][] = $row['tgl_request'];
                        $groupedData[$kodeTransaksi]['tgl_reschedule'][] = $row['tgl_reschedule'];
                        $groupedData[$kodeTransaksi]['tgl_mulai'][] = $row['tgl_mulai'];
                        $groupedData[$kodeTransaksi]['customer'][] = $row['nama_customer'];
                        $groupedData[$kodeTransaksi]['cust_nomor'][] = $row['cust_nomor'];
                        $teknisiIds = explode(',', $row['id_teknisi']);
                        $teknisiNames = [];
                        foreach ($teknisiIds as $teknisiId) {
                            $teknisiQuery = "SELECT nama FROM teknisi WHERE id_teknisi = " . intval($teknisiId);
                            $teknisiResult = mysqli_query($conn, $teknisiQuery);
                            if ($teknisiRow = mysqli_fetch_assoc($teknisiResult)) {
                                $teknisiNames[] = $teknisiRow['nama'];
                            }
                        }
                        $groupedData[$kodeTransaksi]['teknisi'][] = implode("<br>", $teknisiNames);
                        $groupedData[$kodeTransaksi]['jenis'][] = $row['jenis'];
                        $groupedData[$kodeTransaksi]['keterangan'][] = $row['keterangan'];
                        $groupedData[$kodeTransaksi]['req_by'][] = $row['req_by'];
                        $groupedData[$kodeTransaksi]['status'][] = $row['status'];
                        $groupedData[$kodeTransaksi]['id_kegiatan'][] = $row['id_kegiatan'];
                    }
                } else {
                    echo "<div class='ms-4 text-sm'>Tidak ada kegiatan untuk Hari Ini</div>";
                }

                $no = 1;

                foreach ($groupedData as $kodeTransaksi => $data) {

                ?>

                    <li class="list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-0 mb-2 border-radius-lg d-md-block">
                        <div class="row px-4">
                            <?php
                            $updatedStatus = '';

                            foreach ($data['status'] as $status) {
                                if ($status != 'Clear') {
                                    $updatedStatus = $status;

                                    switch ($updatedStatus) {
                                        case 'Waiting':
                                            $updatedStatus = 'Belum Dijadwalkan';
                                            break;
                                        case 'Pending':
                                            $updatedStatus = 'Dijadwalkan';
                                            break;
                                        case 'On Process':
                                            $updatedStatus = 'Diproses';
                                            break;
                                        case 'Pause':
                                            $updatedStatus = 'Lanjut Nanti';
                                            break;
                                        case 'Reschedule':
                                            $updatedStatus = 'Dijadwalkan Ulang';
                                            break;
                                        case 'Reschedule2':
                                            $updatedStatus = 'Dijadwalkan Ulang';
                                            break;
                                        default:
                                            $updatedStatus = $data['status'][0];
                                    }
                                    break;
                                }
                            }

                            if (empty($updatedStatus)) {
                                $updatedStatus = 'Selesai';
                            }



                            ?>

                            <div class="col-6 col-md-2 mb-2 mb-md-0">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $updatedStatus; ?></h6>
                                <span class="text-xs"><?php echo $data['jenis'][0]; ?></span>
                            </div>

                            <?php


                            $smallestIndex = 0; // Initialize with the first index
                            $smallestDateTime = strtotime($data["tgl_mulai"][0]);

                            foreach ($data["tgl_mulai"] as $index => $datetime) {
                                $currentDateTime = strtotime($datetime);

                                if ($currentDateTime < $smallestDateTime) {
                                    $smallestDateTime = $currentDateTime;
                                    $smallestIndex = $index;
                                }
                            }

                            $selectedDatetime = $data["tgl_mulai"][$smallestIndex];
                            $rescheduleDates = $groupedData[$kodeTransaksi]['tgl_reschedule'];

                            $rescheduleNotNull = null;
                            foreach ($rescheduleDates as $date) {
                                if ($date !== null) {
                                    $rescheduleNotNull = $date;
                                    break;
                                }
                            }

                            if ($rescheduleNotNull !== null) {
                                $datetime = $rescheduleNotNull;
                            } else {
                                $datetime = $data["tgl_request"][0];
                            }
                            
                            $datetime = $data["tgl_request"][0];

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
                            

                            $nomorHandphone = $data['cust_nomor'][0];

                            // Cek apakah nomor handphone dimulai dengan angka 0
                            if (substr($nomorHandphone, 0, 1) === '0') {
                                // Ganti angka 0 dengan 62
                                $nomorHandphone = '62' . substr($nomorHandphone, 1);
                            }

                            ?>

                            <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center">
                                <h6 class="text-dark font-weight-bold text-sm"><?php echo $data['customer'][0]; ?></h6>
                                <span class="text-xs text-uppercase"><a href="https://api.whatsapp.com/send?phone=<?php echo $nomorHandphone; ?>" target="_blank"><?php echo $data['cust_nomor'][0]; ?></a></span>
                            </div>

                            <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-md-center justify-content-start align-items-center d-flex">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm">
                                    <?php
                                    foreach ($data['teknisi'] as $teknisiName) {
                                        $teknisiQuery = "SELECT t.id_teknisi, t.log_link, t.no_wa, k.status
                                        FROM teknisi t 
                                        INNER JOIN kegiatan k ON t.id_teknisi = k.id_teknisi
                                        WHERE t.nama = '" . mysqli_real_escape_string($conn, $teknisiName) . "'";
                                        $teknisiResult = mysqli_query($conn, $teknisiQuery);

                                        $status = '';

                                        if ($teknisiResult && mysqli_num_rows($teknisiResult) > 0) {
                                            $teknisiRow = mysqli_fetch_assoc($teknisiResult);
                                            $teknisiId = $teknisiRow['id_teknisi'];
                                            $logLink = $teknisiRow['log_link'];
                                            $noWa = $teknisiRow['no_wa'];
                                            $noWa = '62' . substr($noWa, 1);
                                            while ($teknisiRow = mysqli_fetch_assoc($teknisiResult)) {
                                                if ($teknisiRow['status'] != 'Clear') {
                                                    $status = $teknisiRow['status'];
                                                    break; // Keluar dari loop saat status ditemukan
                                                }
                                            }
                                            $pesan = urlencode("Jangan lupa selesaikan kegiatanmu $teknisiName. Cek disini $logLink");
                                            $pesan = str_replace('%2F', '/', $pesan); // Replace encoded slashes with normal slashes
                                            $textColor = $status != 'Clear' ? 'black' : 'red';
                                            $link = "teknisi_detail.php?id_teknisi=" . $teknisiId;

                                            echo "<span style='color: $textColor;'><a href='#'>$teknisiName</a></span><br>";
                                        }
                                    }
                                    ?>
                                </h6>
                            </div>


                            <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-center align-items-center d-flex">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $data['req_by'][0]; ?></h6>
                            </div>

                            <div class="col-6 col-md-2 mb-2 mb-md-0 d-flex justify-content-md-center justify-content-start align-items-center pt-1">
                                <!-- <button class="btn btn-info view-btn w-25 p-2 text-center me-1" data-id="<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">visibility</i></button> -->
                                <a class="btn btn-info view-btn w-25 p-2 text-center me-1" href="view-kegiatan.php?kode_transaksi=<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">visibility</i></a>
                                <!-- <button class="btn btn-warning edit-btn w-25 p-2 text-center me-1" data-id="<?php echo $kodeTransaksi; ?>"><i class="material-icons opacity-10">autorenew</i></button> -->
                                <!-- <button class="btn btn-danger delete-btn w-25 p-2 text-center" data-kode="<?php echo $kodeTransaksi; ?>" data-nama="<?php echo $nmUser; ?>"><i class="far fa-trash-alt"></i></button> -->
                            </div>


                        </div>
                    </li>

                <?php
                    $no++;
                }
                ?>

            </ul>
        </div>
    </div>
</div>