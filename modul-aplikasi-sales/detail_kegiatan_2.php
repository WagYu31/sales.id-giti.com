<?php
// Mulai sesi
session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../index.php");
    exit;
}

include "conn.php";

// Mengakses id_user dari sesi
$id_user = $_SESSION["id_user"];
$role = $_SESSION["role"];


include "get-user-data.php";

include "get-number-waiting.php";

if (isset($_GET['id_kegiatan'])) {
    $id_kegiatan = $_GET['id_kegiatan'];
    // Query untuk mengambil data kegiatan berdasarkan id_kegiatan
    $sql = "SELECT k.*, t.nama AS nama_teknisi, c.nama AS nama_customer, c.nomor_tlp AS telepon_cust, c.alamat AS alamat_customer FROM kegiatan k
                LEFT JOIN teknisi t ON k.id_teknisi = t.id_teknisi
                LEFT JOIN customer c ON k.id_cust = c.id_cust
                WHERE k.id_kegiatan = $id_kegiatan";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
    } else {
        // Tampilkan pesan jika data tidak ditemukan
        echo "Data kegiatan tidak ditemukan.";
        exit;
    }
} else {
    // Tampilkan pesan jika parameter id_kegiatan tidak ada
    echo "Parameter id_kegiatan tidak valid.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loewix | Detail Kegiatan</title>
    <link rel="icon" href="img/logo3.png" type="image/png">
    <?php
    include "dep-css.php";
    ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" integrity="sha512-lJgqo6i/vD5+C5x4do9Mn+k6vOOI55FMEidMigv55Pz4Zb0igpm5Fcx4OIkUz0cLGGNAsRMjR1VfIu0yd3n/og==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" href="css/style.css?rev=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="css/foot.css?rev=<?php echo time(); ?>">
    <style>
        h2,
        h3 {
            display: inline-block;
        }

        h3 {
            padding: 5px 10px;
            border-radius: 5px;
            color: #fff;
            margin-right: 10px;
        }

        h3.pending,
        h3.besok,
        h3.reschedule {
            background-color: #fcca03;
        }

        h3.on-process {
            background-color: green;
        }

        h3.clear {
            background-color: blue;
        }

        #map-mulai,
        #map-selesai {
            height: 300px;
            width: 30vw;
            z-index: 0;
        }

        #gambarFinish {
            height: 250px;
        }

        #map-selesai,
        #gambarFinish {
            display: inline-block;
        }

        .container table {
            margin-top: 5vh;
        }

        th {
            text-align: center;
            vertical-align: middle;
            justify-content: center;
        }

        table.tek th {
            width: 25%;
            text-align: left;
        }

        th.mnt {
            background-color: #eee;
            color: #343a40;
        }

        .smp-kanan {
            margin-left: 15px;
        }

        .notif {
            position: absolute;
            top: -5px;
            left: 8px;
            background-color: red;
            color: white;
            font-size: 10px;
            border-radius: 50%;
            padding: 2px 6px;
            vertical-align: middle;
            justify-content: center;
        }

        .kembali {
            float: right;
            margin-right: 15px;
            margin-top: 50px;
        }

        .navbar {
            background-color: white;
            box-shadow: 5px 3px 15px rgba(0, 0, 0, 0.5);
        }

        li.nav-item a i,
        li.nav-item span {
            color: #4723D9;
        }

        ul.menuv li i {
            color: white;
        }

        .menuv li a span {
            width: 65px;
            left: -17%;
        }

        .menuv li a span.tg {
            left: -45%;
        }

        .nama-tek {
            background-color: none;
            margin-bottom: -40px;
            padding-top: 5px;
            padding-bottom: 5px;
            padding: 0;
        }

        .jdl-tek {
            width: 100%;
            color: white;
            justify-content: center;
            text-align: center;
            background-color: #343a40;
            font-size: 18px;
            font-weight: bold;
            padding: 10px;
            /*margin-left:-15px;*/
        }

        .container-btn {
            display: flex;
            width: 100%;
        }

        .btn-nama-tek {
            flex: 1;
            height: 50px;
            border-radius: 0;
            font-weight: bold;
            background-color: none;
            border: none;
        }

        .btn-nama-tek:hover {
            background-color: #333;
            border: 1px solid #333;
        }

        /* Gaya untuk baris "Tanggal Reschedule" dan "Waktu Reschedule" */
        table.three td:nth-child(2),
        table.three td:nth-child(3) {
            background-color: #e6f2ff;
            /* Ganti warna latar belakang sesuai keinginan Anda */
            font-weight: bold;
            /* Tambahkan tebal jika diperlukan */
            color: #000;
            /* Warna teks pada latar belakang yang berbeda */
        }

        table.three td {
            text-align: center;
        }

        /* Gaya untuk baris "Tanggal Reschedule" dan "Waktu Reschedule" */
        table.three td:nth-child(8) {
            text-align: left;
        }

        @media (max-width: 768px) {

            #map-mulai,
            #map-selesai {
                height: 200px;
            }

            #map-selesai,
            #map-mulai {
                width: 100%;
                float: none;
            }

            #gambarFinish {
                height: auto;
                display: block;
                margin-top: 10px;
            }

            #content {
                padding: 0;
            }

            h3 {
                margin-top: 5vh;
                margin-bottom: 0;
                font-size: 18px;
                /*margin-left:15px;*/
            }

            h2 {
                margin-top: 10px;
                font-size: 20px;
            }

            .container table {
                margin-top: 2vh;
            }

            table.two td:first-child,
            table.two th:first-child {
                display: none;
                /* Menyembunyikan setiap td pertama */
            }

            .kembali {
                /*float:left;*/
                margin-right: 0;
                margin-top: 0;
            }

            table.two {
                margin-top: 40px;
            }

            .btn-nama-tek {
                width: auto;
                height: 100px;
            }

            .table-responsive {
                overflow-x: auto;
            }

            /* Atur lebar tabel untuk mode mobile */
            table.table {
                width: 100%;
            }

            .footer {
                margin-bottom: 12vh;
            }
        }
    </style>
</head>

<body id="body-pd">
    <div class="container-fluid">
        <div class="row">
            <?php
            include "header.php";
            ?>
            <div class="l-navbar" id="nav-bar">
                <nav class="nav">
                    <div> <a href="#" class="nav_logo"> <img src="img/logo2.png" width="50px"></img> <span class="nav_logo-name">Loewix</span> </a>
                        <div class="nav_list">
                            <?php
                            if ($role == "Admin") {
                            ?>
                                <a href="index.php" class="nav_link active"> <i class='bx bx-grid-alt nav_icon'></i> <span class="nav_name">Dashboard</span> </a>
                                <a href="kegiatan.php" class="nav_link"> <i class='bx bx-bookmark nav_icon'></i> <span class="nav_name">Kegiatan</span> </a>
                                <a href="waiting_list.php" class="nav_link">
                                    <i class='bx bx-pin nav_icon'></i>
                                    <span class="nav_name">Waiting List</span>
                                    <?php if ($waitingCount > 0) : ?>
                                        <span class="notif"><?php echo $waitingCount; ?></span>
                                    <?php endif; ?>
                                </a>

                                <a href="teknisi.php" class="nav_link"> <i class='bx bx-user-pin nav_icon'></i> <span class="nav_name">Teknisi</span> </a>
                                <a href="data-customer.php" class="nav_link"> <i class='bx bx-user nav_icon'></i> <span class="nav_name">Data Customer</span> </a>
                            <?php
                            } else if ($role == "SA") {
                            ?>
                                <a href="index-sa.php" class="nav_link active"> <i class='bx bx-grid-alt nav_icon'></i> <span class="nav_name">Dashboard</span> </a>
                                <a href="kegiatan.php" class="nav_link"> <i class='bx bx-bookmark nav_icon'></i> <span class="nav_name">Kegiatan</span> </a>
                                <a href="waiting_list.php" class="nav_link">
                                    <i class='bx bx-pin nav_icon'></i>
                                    <span class="nav_name">Waiting List</span>
                                    <?php if ($waitingCount > 0) : ?>
                                        <span class="notif"><?php echo $waitingCount; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="teknisi.php" class="nav_link"> <i class='bx bx-user-pin nav_icon'></i> <span class="nav_name">Teknisi</span> </a>
                                <a href="sales.php" class="nav_link"> <i class='bx bx-user-pin nav_icon'></i> <span class="nav_name">Sales</span> </a>
                                <a href="data-customer.php" class="nav_link"> <i class='bx bxs-group nav_icon'></i> <span class="nav_name">Data Customer</span> </a>
                                <a href="history.php" class="nav_link"> <i class='bx bxs-time nav_icon'></i> <span class="nav_name">Riwayat</span> </a>
                            <?php
                            } else if ($role == "Sales") {
                            ?>
                                <a href="index-sales.php" class="nav_link active"> <i class='bx bx-grid-alt nav_icon'></i> <span class="nav_name">Dashboard</span> </a>
                                <a href="kegiatan.php" class="nav_link"> <i class='bx bx-bookmark nav_icon'></i> <span class="nav_name">Kegiatan</span> </a>
                                <a href="data-customer.php" class="nav_link"> <i class='bx bx-user nav_icon'></i> <span class="nav_name">Data Customer</span> </a>
                            <?php
                            } else {
                            ?>
                                <a href="index-teknisi.php" class="nav_link"> <i class='bx bx-grid-alt nav_icon'></i> <span class="nav_name">Dashboard</span> </a>
                                <a href="profile-tek.php" class="nav_link"> <i class='bx bx-throphy nav_icon'></i> <span class="nav_name">Profile</span> </a>
                                <!--<a href="kegiatan.php" class="nav_link"> <i class='bx bx-bookmark nav_icon'></i> <span class="nav_name">Kegiatan</span> </a>-->
                            <?php
                            }
                            ?>
                            <!-- <a href="#" class="nav_link"> <i class='bx bx-bar-chart-alt-2 nav_icon'></i> <span class="nav_name">Stats</span> </a> -->
                        </div>
                    </div> <a href="logout.php" class="nav_link"> <i class='bx bx-log-out nav_icon'></i> <span class="nav_name">SignOut</span> </a>
                </nav>
            </div>

            <?php
            include "btm-nav.php";
            ?>
            <!-- Konten Utama -->
            <main id="content" class="mx-auto">
                <div class="container">
                    <?php
                    $status = $row['status'];
                    $lok_mulai = $row["lokasi_mulai"];
                    $selesai = $row["tgl_selesai"];
                    $lok_selesai = $row["lokasi_selesai"];
                    $ket = $row["keterangan"];
                    $kode_transaksi = $row["kode_transaksi"];
                    $idTeam = $row["id_team"];

                    // Contoh format datetime dalam variabel $request dan $mulai
                    $requestDatetime = $row['tgl_request'];
                    $mulaiDatetime = $row['tgl_mulai'];

                    // Memisahkan tanggal dari format datetime dalam format "d-m-y"
                    $requestDate = date('d M Y', strtotime($requestDatetime));
                    $mulaiDate = date('d M Y', strtotime($mulaiDatetime));

                    // Memisahkan jam dari format datetime dalam format "H:i"
                    $requestTime = date('H:i', strtotime($requestDatetime));
                    $mulaiTime = date('H:i', strtotime($mulaiDatetime));

                    $statusClass = '';

                    if ($status == 'Pending') {
                        $statusClass = 'pending';
                        $sts = 'Dijadwalkan';
                    } elseif ($status == 'Reschedule') {
                        $statusClass = 'reschedule';
                        $sts = 'Reschedule';
                    } elseif ($status == 'Reschedule2') {
                        $statusClass = 'reschedule';
                        $sts = 'Reschedule';
                    } elseif ($status == 'Pause') {
                        $statusClass = 'besok';
                        $sts = 'Lanjut Besok';
                    } elseif ($status == 'On Process') {
                        $statusClass = 'on-process';
                        $sts = 'Di Proses';
                    } elseif ($status == 'Clear') {
                        $statusClass = 'clear';
                        $sts = 'Selesai';
                    }
                    ?>

                    <h3 class="<?php echo $statusClass; ?>"><?php echo $sts; ?></h3>
                    <h2 class="text-lg">Detail Kegiatan : <?php echo $kode_transaksi; ?></h2>

                    <!-- Tampilkan detail kegiatan di sini -->

                    <!--<div class="container">-->
                    <div class="table-responsive">
                        <table class="table table-bordered tek">
                            <tbody>
                                <tr>
                                    <th colspan="2" class="mnt">DATA CUSTOMER</th>
                                </tr>
                                <tr>
                                    <th>Customer</th>
                                    <td><?php echo $row['nama_customer']; ?></td>
                                </tr>

                                <?php
                                $telepon_cust = $row['telepon_cust'];

                                // Periksa apakah nomor telepon diawali dengan "0"
                                if (substr($telepon_cust, 0, 1) === '0') {
                                    // Jika ya, ganti "0" menjadi "62"
                                    $tlp_cust = '62' . substr($telepon_cust, 1);
                                }
                                ?>

                                <tr>
                                    <th>No Telepon Customer</th>
                                    <td><a href="https://api.whatsapp.com/send?phone=<?php echo $tlp_cust; ?>"><?php echo $telepon_cust; ?></a></td>
                                </tr>
                                <tr>
                                    <th>Alamat Customer</th>
                                    <td><?php echo $row['alamat_customer']; ?></td>
                                </tr>
                                <tr>
                                    <th colspan="2" class="mnt">PERMINTAAN</th>
                                </tr>
                                <tr>
                                    <th>Kegiatan</th>
                                    <td><?php echo $row['jenis']; ?></td>
                                </tr>
                                <tr>
                                    <th>Tanggal</th>

                                    <?php
                                    $requestDatetime = $row['tgl_request'];
                                    // Memisahkan tanggal dari format datetime dalam format "d-m-y"
                                    $requestDate = date('d M Y', strtotime($requestDatetime));
                                    // Memisahkan jam dari format datetime dalam format "H:i"
                                    $requestTime = date('H:i', strtotime($requestDatetime));
                                    $namaHari = date("l", strtotime($requestDatetime));
                                    $namaHariIndonesia = "";
                                    switch ($namaHari) {
                                        case "Monday":
                                            $namaHariIndonesia = "Senin";
                                            break;
                                        case "Tuesday":
                                            $namaHariIndonesia = "Selasa";
                                            break;
                                        case "Wednesday":
                                            $namaHariIndonesia = "Rabu";
                                            break;
                                        case "Thursday":
                                            $namaHariIndonesia = "Kamis";
                                            break;
                                        case "Friday":
                                            $namaHariIndonesia = "Jumat";
                                            break;
                                        case "Saturday":
                                            $namaHariIndonesia = "Sabtu";
                                            break;
                                        case "Sunday":
                                            $namaHariIndonesia = "Minggu";
                                            break;
                                    }

                                    $rescheduleDateTime = $row['tgl_reschedule'];
                                    $rescheduleDate = date('d M Y', strtotime($rescheduleDateTime));
                                    $rescheduleTime = date('H:i', strtotime($rescheduleDateTime));
                                    $rescheduleHari = date("l", strtotime($rescheduleDateTime));
                                    $rescheduleHariIndo = "";
                                    switch ($rescheduleHari) {
                                        case "Monday":
                                            $rescheduleHariIndo = "Senin";
                                            break;
                                        case "Tuesday":
                                            $rescheduleHariIndo = "Selasa";
                                            break;
                                        case "Wednesday":
                                            $rescheduleHariIndo = "Rabu";
                                            break;
                                        case "Thursday":
                                            $rescheduleHariIndo = "Kamis";
                                            break;
                                        case "Friday":
                                            $rescheduleHariIndo = "Jumat";
                                            break;
                                        case "Saturday":
                                            $rescheduleHariIndo = "Sabtu";
                                            break;
                                        case "Sunday":
                                            $rescheduleHariIndo = "Minggu";
                                            break;
                                    }
                                    ?>

                                    <td><?php echo $namaHariIndonesia . ", " . $requestDate; ?></td>
                                </tr>
                                <tr>
                                    <th>Waktu</th>
                                    <td><?php echo date('H:i', strtotime($row['tgl_request'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Keterangan</th>
                                    <td><?php echo $row['keterangan']; ?></td>
                                </tr>


                            </tbody>
                        </table>
                    </div>
                    <!--</div>-->

                    <div class="container nama-tek">
                        <div class="jdl-tek">TEKNISI</div>
                        <?php
                        $selectTeknisi = "SELECT k.id_teknisi, k.kode_transaksi, t.nama AS nama_teknisi, t.id_teknisi, k.id_kegiatan FROM kegiatan k
                                                LEFT JOIN teknisi t ON k.id_teknisi = t.id_teknisi
                                                WHERE k.kode_transaksi = '$kode_transaksi'";
                        $resTek = mysqli_query($conn, $selectTeknisi);

                        echo '<div class="container-btn" style="border:1px solid #eee;">';
                        if (mysqli_num_rows($resTek) > 0) {
                            while ($dataTek = mysqli_fetch_assoc($resTek)) {
                                $id_kegiatan_teknisi = $dataTek['id_kegiatan'];
                                $nama_teknisi = $dataTek['nama_teknisi'];

                                // Cek apakah id_kegiatan_teknisi sama dengan id_kegiatan sesi saat ini
                                $background_color = ($id_kegiatan_teknisi == $_GET['id_kegiatan']) ? 'background-color: #eee; margin:1px;color:#333; border:1px solid #fff' : '';

                                // Buat tombol untuk setiap teknisi dengan warna latar belakang yang sesuai
                                echo '<button class="btn btn-secondary btn-nama-tek" style="' . $background_color . '" onclick="location.href=\'detail_kegiatan_2.php?id_kegiatan=' . $id_kegiatan_teknisi . '\'">' . $nama_teknisi . '</button>';
                            }
                        } else {
                            echo 'Data Teknisi tidak ditemukan.';
                        }
                        echo '</div>';
                        ?>
                    </div>
                    <!--<div class="container">-->
                    <div class="table-responsive">
                        <table class="table table-bordered two">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="background-color:white; color:#333; border:2px solid #eee;"></th>
                                    <th style="background-color:white; color:#333; border:2px solid #eee;">Mulai</th>
                                    <th style="background-color:white; color:#333; border:2px solid #eee;">Selesai</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Tanggal</td>
                                    <td><?php echo $row['tgl_mulai'] ? date('d-m-y', strtotime($row['tgl_mulai'])) : '-'; ?></td>
                                    <td><?php echo $row['tgl_selesai'] ? date('d-m-y', strtotime($row['tgl_selesai'])) : '-'; ?></td>
                                </tr>
                                <tr>
                                    <td>Waktu</td>
                                    <td><?php echo $row['tgl_mulai'] ? date('H:i', strtotime($row['tgl_mulai'])) : '-'; ?></td>
                                    <td><?php echo $row['tgl_selesai'] ? date('H:i', strtotime($row['tgl_selesai'])) : '-'; ?></td>
                                </tr>
                                <tr>
                                    <td>Alamat Lokasi</td>
                                    <td>
                                        <div id="locationAddress"></div>
                                    </td>
                                    <td>
                                        <div id="locationAddress2"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Maps</td>
                                    <td>
                                        <div id="map-mulai"></div>
                                    </td>
                                    <td>
                                        <div id="map-selesai"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Review</td>
                                    <td colspan="2">
                                        <?php if (!empty($row['gambar_finish_1']) && $row['gambar_finish_1'] != '-') : ?>
                                            <img id="gambarFinish" src="uploads/<?php echo $row['gambar_finish_1']; ?>" alt="Gambar Selesai" class="img-fluid">
                                        <?php endif; ?>

                                        <?php if (!empty($row['gambar_finish_2']) && $row['gambar_finish_2'] != '-') : ?>
                                            <img id="gambarFinish" src="uploads/<?php echo $row['gambar_finish_2']; ?>" alt="Gambar Selesai" class="img-fluid">
                                        <?php endif; ?>

                                        <?php if (!empty($row['gambar_finish_3']) && $row['gambar_finish_3'] != '-') : ?>
                                            <img id="gambarFinish" src="uploads/<?php echo $row['gambar_finish_3']; ?>" alt="Gambar Selesai" class="img-fluid">
                                        <?php endif; ?>

                                        <?php if (!empty($row['gambar_finish_4']) && $row['gambar_finish_4'] != '-') : ?>
                                            <img id="gambarFinish" src="uploads/<?php echo $row['gambar_finish_4']; ?>" alt="Gambar Selesai" class="img-fluid">
                                        <?php endif; ?>

                                        <?php if (!empty($row['gambar_finish_5']) && $row['gambar_finish_5'] != '-') : ?>
                                            <img id="gambarFinish" src="uploads/<?php echo $row['gambar_finish_5']; ?>" alt="Gambar Selesai" class="img-fluid">
                                        <?php endif; ?>

                                    </td>
                                </tr>
                                <tr>
                                    <td>Note</td>
                                    <td colspan="2">
                                        <?php echo "<b>Permasalahan : </b>" . $row['ket_finish']; ?><br>
                                        <?php echo "<b>Solusi : </b>" . $row['ket_finish_2']; ?><br>
                                        <?php echo "<b>Keterangan Tambahan : </b>" . $row['ket_finish_3']; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!--</div>-->



                    <!--<div class="container">-->
                    <?php
                    // Memeriksa apakah ada id_kegiatan yang sama dalam tabel reschedule
                    $checkIdKegiatan = "SELECT COUNT(*) as total FROM reschedule WHERE id_kegiatan = $id_kegiatan";
                    $result = mysqli_query($conn, $checkIdKegiatan);
                    $rw = mysqli_fetch_assoc($result);

                    if ($rw['total'] > 0) {
                    ?>
                        <div class="table-responsive">
                            <table class="table table-bordered three">
                                <thead class="thead-dark">
                                    <tr>
                                        <th style="width:3%;" rowspan="2">No</th>
                                        <th style="width:13%;" rowspan="2">Tanggal</th>
                                        <th style="width:10%;" rowspan="2">Waktu</th>
                                        <th style="width:13%;" colspan="2">Mulai</th>
                                        <!--<th>Lokasi Mulai</th>-->
                                        <th style="width:13%;" colspan="2">Selesai</th>
                                        <!--<th>Lokasi Selesai</th>-->
                                        <th style="width:23%;" rowspan="2">Keterangan</th>
                                        <th style="width:5%;" rowspan="2">Status</th>
                                    </tr>
                                    <tr>
                                        <th style="width:13%;">Tanggal</th>
                                        <th style="width:10%;">Waktu</th>
                                        <!--<th>Lokasi Mulai</th>-->
                                        <th style="width:13%;">Tanggal</th>
                                        <th style="width:10%;">Waktu</th>
                                        <!--<th>Lokasi Selesai</th>-->
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Data dari tabel reschedule
                                    echo "<tr>";
                                    echo "<td>" . 1 . "</td>";
                                    echo "<td>" . $requestDate . "</td>";
                                    echo "<td>" . $requestTime . "</td>";
                                    echo "<td>" . $mulaiDate . "</td>";
                                    echo "<td>" . $mulaiTime . "</td>";
                                    // echo "<td id='alamatLokasiMulai'></td>";

                                    $res = "SELECT * FROM reschedule WHERE id_kegiatan = $id_kegiatan";
                                    $resRes = mysqli_query($conn, $res);

                                    $no = 2;

                                    if ($resRes && mysqli_num_rows($resRes) > 0) {
                                        $data = mysqli_fetch_assoc($resRes); // Ambil data pertama
                                        $id_resc = $data["id_resc"];
                                        $tgl_req = $data["tanggal"];
                                        $mli = $data["tgl_mulai"];
                                        $stat = $data["status"];
                                        if ($stat == "Pause" || $stat == "Reschedule" || $stat == "Reschedule2") {
                                            $stat = "Reschedule";
                                        } else if ($stat == "Clear") {
                                            $stat = "Selesai";
                                        }
                                        // $lok_mli = $data["lokasi_mulai"];
                                        $allData = array(); // Inisialisasi array untuk semua data

                                        echo "<td>" . (($data["tgl_selesai"] && $data["tgl_selesai"] != '0000-00-00 00:00:00') ? date('d M Y', strtotime($data["tgl_selesai"])) : '-') . "</td>";
                                        echo "<td>" . (($data["tgl_selesai"] && $data["tgl_selesai"] != '0000-00-00 00:00:00') ? date('H:i', strtotime($data["tgl_selesai"])) : '-') . "</td>";
                                        // echo "<td id='alamatLokasiSelesai'></td>";
                                        echo "<td>" . $data["keterangan"] . "</td>";
                                        echo "<td>" . $stat . "</td>";
                                        echo "</tr>";

                                        while ($data = mysqli_fetch_assoc($resRes)) {
                                            $allData[] = $data; // Tambahkan data ke array
                                        }

                                        // Tampilkan semua data kecuali satu data terakhir
                                        $count = count($allData);
                                        for ($i = 0; $i < $count - 1; $i++) {

                                            $statu = $allData[$i]["status"];
                                            if ($statu == "Pause" || $statu == "Reschedule" || $statu == "Reschedule2") {
                                                $statu = "Reschedule";
                                            } else if ($statu == "Clear") {
                                                $statu = "Selesai";
                                            }
                                            echo "<tr>";
                                            echo "<td>" . $no . "</td>";
                                            echo "<td>" . (($allData[$i]["tanggal"] && $allData[$i]["tanggal"] != '0000-00-00 00:00:00') ? date('d M Y', strtotime($allData[$i]["tanggal"])) : '-') . "</td>";
                                            echo "<td>" . (($allData[$i]["tanggal"] && $allData[$i]["tanggal"] != '0000-00-00 00:00:00') ? date('H:i', strtotime($allData[$i]["tanggal"])) : '-') . "</td>";
                                            echo "<td>" . (($allData[$i]["tgl_mulai"] && $allData[$i]["tgl_mulai"] != '0000-00-00 00:00:00') ? date('d M Y', strtotime($allData[$i]["tgl_mulai"])) : '-') . "</td>";
                                            echo "<td>" . (($allData[$i]["tgl_mulai"] && $allData[$i]["tgl_mulai"] != '0000-00-00 00:00:00') ? date('H:i', strtotime($allData[$i]["tgl_mulai"])) : '-') . "</td>";
                                            echo "<td>" . (($allData[$i]["tgl_selesai"] && $allData[$i]["tgl_selesai"] != '0000-00-00 00:00:00') ? date('d M Y', strtotime($allData[$i]["tgl_selesai"])) : '-') . "</td>";
                                            echo "<td>" . (($allData[$i]["tgl_selesai"] && $allData[$i]["tgl_selesai"] != '0000-00-00 00:00:00') ? date('H:i', strtotime($allData[$i]["tgl_selesai"])) : '-') . "</td>";
                                            // echo "<td id='alamatLokasiSelesai-$i'></td>"; 
                                            echo "<td>" . $allData[$i]["keterangan"] . "</td>";
                                            echo "<td>" . $statu . "</td>";
                                            echo "</tr>";
                                            $no++;
                                        }

                                        // Query untuk mendapatkan data terakhir dari tabel reschedule
                                        $akhir = "SELECT * FROM reschedule WHERE id_kegiatan = $id_kegiatan ORDER BY id_resc DESC LIMIT 1";
                                        $resAkhir = mysqli_query($conn, $akhir);

                                        if ($resAkhir && mysqli_num_rows($resAkhir) > 0) {
                                            $dt = mysqli_fetch_assoc($resAkhir);
                                            echo "<tr>";
                                            echo "<td>" . $no . "</td>";
                                            echo "<td>" . (($dt["tanggal"] && $dt["tanggal"] != '0000-00-00 00:00:00') ? date('d M Y', strtotime($dt["tanggal"])) : '-') . "</td>";
                                            echo "<td>" . (($dt["tanggal"] && $dt["tanggal"] != '0000-00-00 00:00:00') ? date('H:i', strtotime($dt["tanggal"])) : '-') . "</td>";
                                            echo "<td>" . (($dt["tgl_mulai"] && $dt["tgl_mulai"] != '0000-00-00 00:00:00') ? date('d M Y', strtotime($dt["tgl_mulai"])) : '-') . "</td>";
                                            echo "<td>" . (($dt["tgl_mulai"] && $dt["tgl_mulai"] != '0000-00-00 00:00:00') ? date('H:i', strtotime($dt["tgl_mulai"])) : '-') . "</td>";
                                            // echo "<td id='alamatLokasiMulai-Terakhir'></td>";
                                        } else {
                                            // Tambahkan penanganan ketika data terakhir tidak ditemukan
                                            echo "<tr><td colspan='4'>Data terakhir tidak ditemukan</td></tr>";
                                        }
                                    }
                                    echo "<td>" . (($selesai && $selesai != '0000-00-00 00:00:00') ? date('d M Y', strtotime($selesai)) : '-') . "</td>";
                                    echo "<td>" . (($selesai && $selesai != '0000-00-00 00:00:00') ? date('H:i', strtotime($selesai)) : '-') . "</td>";
                                    // echo "<td id='alamatLokasiSelesai-Terakhir'></td>";
                                    echo "<td>" . $ket . "</td>";

                                    if ($status == "Pause" || $status == "Reschedule" || $status == "Reschedule2") {
                                        $status = "Reschedule";
                                    } else if ($status == "Clear") {
                                        $status = "Selesai";
                                    }
                                    echo "<td>" . $status . "</td>";
                                    echo "</tr>";
                                    ?>

                                </tbody>
                            </table>
                        </div>
                    <?php
                    } else {
                        // Tampilkan pesan jika tidak ada id_kegiatan yang sama dalam tabel reschedule
                    }
                    ?>
                    <!--</div>-->

                </div>
            </main>
        </div>
    </div>


    <?php
    include "foot.php";
    include "dep-js.php";
    ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <!-- Sisipkan script JavaScript untuk Google Maps -->
    <script>
        // Mendapatkan nilai lengkap dari kolom lokasi_mulai
        var lokasiMulai = "<?php echo $row['lokasi_mulai']; ?>";

        // Memisahkan koordinat latitude dan longitude
        var koordinatMulai = lokasiMulai.split(',');

        // Konversi string menjadi float
        var latitudeMulai = parseFloat(koordinatMulai[0]);
        var longitudeMulai = parseFloat(koordinatMulai[1]);

        // Inisialisasi peta dan atur koordinat awal untuk lokasi mulai
        var mapMulai = L.map('map-mulai').setView([latitudeMulai, longitudeMulai], 15);

        // Tambahkan layer peta OSM untuk lokasi mulai
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mapMulai);

        // Tambahkan marker pada koordinat lokasi mulai
        L.marker([latitudeMulai, longitudeMulai]).addTo(mapMulai)
            .bindPopup('Lokasi Mulai')
            .openPopup();

        // Fungsi untuk mengambil alamat dari koordinat
        function getAddressFromCoordinates(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    var address = data.display_name;
                    document.getElementById('locationAddress').innerHTML = address;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('locationAddress').innerHTML = "Tidak dapat mengambil alamat. Pastikan GPS Aktif.";
                });
        }

        // Panggil fungsi untuk lokasi mulai
        getAddressFromCoordinates(latitudeMulai, longitudeMulai);
    </script>

    <!-- Sisipkan script JavaScript untuk Google Maps untuk lokasi selesai -->
    <script>
        // Mendapatkan nilai lengkap dari kolom lokasi_selesai
        var lokasiSelesai = "<?php echo $row['lokasi_selesai']; ?>";

        // Memisahkan koordinat latitude dan longitude
        var koordinatSelesai = lokasiSelesai.split(',');

        // Konversi string menjadi float
        var latitudeSelesai = parseFloat(koordinatSelesai[0]);
        var longitudeSelesai = parseFloat(koordinatSelesai[1]);

        // Inisialisasi peta dan atur koordinat awal untuk lokasi selesai
        var mapSelesai = L.map('map-selesai').setView([latitudeSelesai, longitudeSelesai], 15);

        // Tambahkan layer peta OSM untuk lokasi selesai
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mapSelesai);

        // Tambahkan marker pada koordinat lokasi selesai
        L.marker([latitudeSelesai, longitudeSelesai]).addTo(mapSelesai)
            .bindPopup('Lokasi Selesai')
            .openPopup();

        // Fungsi untuk mengambil alamat dari koordinat
        function getAddressFromCoordinates(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    var address = data.display_name;
                    document.getElementById('locationAddress2').innerHTML = address;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('locationAddress2').innerHTML = "Tidak dapat mengambil alamat. Pastikan GPS Aktif.";
                });
        }

        // Panggil fungsi untuk lokasi mulai
        getAddressFromCoordinates(latitudeSelesai, longitudeSelesai);
    </script>

</body>

</html>