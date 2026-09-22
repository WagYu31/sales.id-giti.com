<div class="container">

    <?php
    // Tentukan jumlah data per halaman
    $limit = 10;

    // Tentukan halaman saat ini
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

    if (isset($_GET["id_cust"])) {
        $customer = $_GET["id_cust"];

        // Hitung offset (mulai dari data ke berapa)
        $offset = ($page - 1) * $limit;

$sql = "SELECT c.id_cust, c.nama AS nama_cust, c.no_wa, c.alamat, v.id_sales, v.kode_transaksi, v.status, v.tgl_visits, v.tgl_mulai, v.tgl_selesai
        FROM cust c
        LEFT JOIN visits v ON c.id_cust = v.id_cust
        WHERE c.id_cust = $customer
        GROUP BY c.id_cust, c.nama, c.no_wa, c.alamat, v.kode_transaksi
        ORDER BY v.kode_transaksi ASC, v.tgl_visits ASC
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);


        // Hitung total baris data
        $sql_total = "SELECT COUNT(*) AS total FROM visits WHERE id_cust = $customer";
        $result_total = mysqli_query($conn, $sql_total);
        $row_total = mysqli_fetch_assoc($result_total);
        $total_pages = ceil($row_total['total'] / $limit);
    } else {
        echo "ID Customer tidak valid.";
        exit(); // Keluar dari script jika ID Customer tidak valid
    }
    ?>



    <div class="card p-4 mt-0">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <?php
                    $cekNm = "SELECT nama FROM cust WHERE id_cust = $customer";
                    $resNm = mysqli_query($conn, $cekNm);
                    $rowNm = mysqli_fetch_assoc($resNm);
                    ?>
                    <h6 class="mb-0 mx-1 ms-0 lead font-weight-bold text-uppercase">Data Kegiatan <?php echo $rowNm['nama']; ?></h6>
                </div>
            </div>
        </div>
        <div class="card-body pb-0 p-0">
            <!-- Tabel data teknisi -->
            <div class="table-responsive mt-3">
                <table class="table text-center">
                    <thead class="text-dark">
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Kode Transaksi</th>
                            <th scope="col">Sales</th>
                            <th scope="col">Tanggal Visits</th>
                            <th scope="col">Tanggal Mulai</th>
                            <th scope="col">Tanggal Selesai</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            $id_teknisi = $row["id_sales"];
                            $idCust = $row["id_cust"];
                            $kodeTransaksi = $row["kode_transaksi"];
                            echo "<tr>";
                            echo "<td>" . $no . "</td>";
                            echo "<td><a href='view-kegiatan.php?kode_transaksi=" . $kodeTransaksi . "'>" . $kodeTransaksi . "</a></td>";
                            $sqlTk = "SELECT v.*, s.id_sales, s.nama AS nama_sales 
                            FROM visits v
                            INNER JOIN sales s ON s.id_sales = v.id_sales
                            WHERE id_cust = $idCust AND kode_transaksi = '$kodeTransaksi'";
                            $resTk = mysqli_query($conn, $sqlTk);
                            echo "<td>";
                            while($rowTk = mysqli_fetch_assoc($resTk)){
                                $idTek = $rowTk['id_sales'];
                                echo "<a href='sales-detail.php?id_sales=" . $idTek . "'>" . $rowTk['nama_sales'] . "</a><br>";
                            }
                            echo "</td>";
                            echo "<td>" . ($row["tgl_visits"] ? date("d M Y", strtotime($row["tgl_visits"])) : "-") . "</td>";
                            echo "<td>" . ($row["tgl_mulai"] ? date("d M Y", strtotime($row["tgl_mulai"])) : "-") . "</td>";
                            echo "<td>" . ($row["tgl_selesai"] ? date("d M Y", strtotime($row["tgl_selesai"])) : "-") . "</td>";
                            $status = $row["status"];
                            $statusText = "";

                            if ($status == "dijadwalkan") {
                                $statusText = "Dijadwalkan";
                            } elseif ($status == "on process") {
                                $statusText = "Dalam Proses";
                            } elseif ($status == "clear") {
                                $statusText = "Selesai";
                            } elseif ($status == "Pause") {
                                $statusText = "Lanjut Nanti";
                            } else {
                                // Jika status tidak sesuai dengan yang diharapkan, biarkan saja nilai aslinya
                                $statusText = $status;
                            }

                            echo "<td>" . $statusText . "</td>";

                            echo "</tr>";
                            $no++;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<nav aria-label="Page navigation example">
    <ul class="pagination justify-content-center mt-3">
        <!-- Tombol Previous -->
        <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="<?php if ($page <= 1) echo '#'; else echo "?page=" . ($page - 1) . "&id_cust=" . $customer; ?>"><i class="material-icons opacity-10">arrow_back_ios</i></a>
        </li>
        <!-- Tampilkan halaman -->
        <?php
        // Batasi jumlah angka halaman yang ditampilkan
        $max_page_number = 4;
        $start_page = max(1, $page - floor($max_page_number / 2));
        $end_page = min($total_pages, $start_page + $max_page_number - 1);

        // Atur ulang $start_page jika $end_page tidak mencakup $max_page_number
        $start_page = max(1, $end_page - $max_page_number + 1);

        for ($i = $start_page; $i <= $end_page; $i++) :
        ?>
            <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                <a class="page-link text-white bg-gradient-info border-info" href="?page=<?php echo $i . "&id_cust=" . $customer; ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>
        <!-- Tombol Next -->
        <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
            <a class="page-link" href="<?php if ($page >= $total_pages) echo '#'; else echo "?page=" . ($page + 1) . "&id_cust=" . $customer; ?>"><i class="material-icons opacity-10">arrow_forward_ios</i></a>
        </li>
    </ul>
</nav>



</div>