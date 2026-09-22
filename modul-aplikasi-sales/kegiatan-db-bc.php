<div class="col-lg-12">
    <div class="card h-100 py-3">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <h6 class="mb-0 mx-1">Kegiatan Berjalan</h6>
                </div>
            </div>
        </div>
        <?php

        // Query untuk mengambil data dari tabel kegiatan dan JOIN dengan tabel teknisi dan customer
        $sql = "SELECT k.*, t.nama AS nama_teknisi, c.nama AS nama_customer 
                        FROM kegiatan k
                        LEFT JOIN teknisi t ON k.id_teknisi = t.id_teknisi
                        LEFT JOIN customer c ON k.id_cust = c.id_cust
                        WHERE k.status IN ('Pending', 'On Process', 'Reschedule', 'Reschedule2', 'Pause')
                        AND (
                            DATE(k.tgl_reschedule) >= CURDATE()
                            OR DATE(k.tgl_mulai) >= CURDATE()
                            OR DATE(k.tgl_request) >= CURDATE()
                        )
                        ORDER BY 
                            COALESCE(k.tgl_reschedule, k.tgl_mulai, k.tgl_request), 
                            k.tgl_request";

        $result = mysqli_query($conn, $sql);

        ?>
        <div class="card-body pb-0 p-0">
            <ul class="list-group m-0 mt-4" id="data-tek">

                <li class="list-group-item border-0 d-flex flex-column justify-content-between ps-0 mb-2 border-radius-lg d-md-block d-none">
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
                    while ($row = mysqli_fetch_assoc($result)) {
                        $kodeTransaksi = $row['kode_transaksi'];

                        if (!isset($groupedData[$kodeTransaksi])) {
                            $groupedData[$kodeTransaksi] = [
                                'tgl_request' => [],
                                'tgl_reschedule' => [],
                                'tgl_mulai' => [],
                                'customer' => [],
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
                }

                $no = 1; // Nomor urutan baris

                foreach ($groupedData as $kodeTransaksi => $data) {

                ?>

                    <li class="list-group-item border-0 d-flex flex-column justify-content-between align-items-center ps-0 mb-2 border-radius-lg d-md-block d-none">
                        <div class="row px-4">
                            <?php
                            $updatedStatus = '';
                            switch ($data['status'][0]) {
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
                                case 'Clear':
                                    $updatedStatus = 'Selesai';
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

                            // Format dan tampilkan tanggal dan waktu
                            $formattedTime = date("H:i", strtotime($datetime));
                            $formattedDate = date("d-m-Y", strtotime($datetime));
                            $tanggal_sekarang = date("d-m-Y");
                            if ($formattedDate > $tanggal_sekarang) {
                            ?>
                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <h6 class="mb-1 font-weight-bold text-sm" style="color:blue;"><?php echo $formattedDate; ?></h6>
                                    <span class="text-xs text-uppercase" style="color:blue;"><?php echo $formattedTime; ?></span>
                                </div>
                            <?php
                            } else {
                            ?>
                                <div class="col-6 col-md-2 mb-2 mb-md-0">
                                    <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $formattedDate; ?></h6>
                                    <span class="text-xs text-uppercase"><?php echo $formattedTime; ?></span>
                                </div>
                            <?php
                            }

                            ?>

                            <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-center align-items-center d-flex">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $data['customer'][0]; ?></h6>
                            </div>

                            <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-center align-items-center d-flex">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm">
                                <?php
                                foreach ($data['teknisi'] as $teknisiName) {
                                    $teknisiQuery = "SELECT id_teknisi, log_link, no_wa FROM teknisi WHERE nama = '" . mysqli_real_escape_string($conn, $teknisiName) . "'";
                                    $teknisiResult = mysqli_query($conn, $teknisiQuery);

                                    if ($teknisiRow = mysqli_fetch_assoc($teknisiResult)) {
                                        $teknisiId = $teknisiRow['id_teknisi'];
                                        $logLink = $teknisiRow['log_link'];
                                        $noWa = $teknisiRow['no_wa'];
                                        $noWa = '62' . substr($noWa, 1);
                                        $pesan = urlencode("Jangan lupa selesaikan kegiatanmu $teknisiName. Cek disini $logLink");
                                        $pesan = str_replace('%2F', '/', $pesan); // Replace encoded slashes with normal slashes

                                        $link = "teknisi_detail.php?id_teknisi=" . $teknisiId;
                                        
                                        echo "<a href='$link'>$teknisiName</a><br>";

                                    } else {
                                        echo $teknisiName;
                                    }
                                }
                                ?>
                                </h6>

                            </div>
                            <div class="col-6 col-md-2 mb-2 mb-md-0 text-left text-md-center justify-content-center align-items-center d-flex">
                                <h6 class="mb-1 text-dark font-weight-bold text-sm"><?php echo $data['req_by'][0]; ?></h6>
                            </div>

                            <div class="col-6 col-md-2 mb-2 mb-md-0 d-flex justify-content-center align-items-center pt-1">
                                <button class="btn btn-info view-btn w-25 p-2 text-center me-1" data-id="<?php echo $data['id_kegiatan'][0]; ?>"><i class="material-icons opacity-10">visibility</i></button>
                                <button class="btn btn-warning edit-btn w-25 p-2 text-center me-1" data-id="<?php echo $data['id_kegiatan'][0]; ?>"><i class="material-icons opacity-10">autorenew</i></button>
                                <button class="btn btn-danger delete-btn w-25 p-2 text-center" data-id="<?php echo $kodeTransaksi; ?>"><i class="far fa-trash-alt"></i></button>
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