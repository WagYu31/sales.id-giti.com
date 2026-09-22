<div class="container">
    <?php
    // Tentukan jumlah data per halaman
    $limit = 10;

    // Tentukan halaman saat ini
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;

    if (isset($_GET["id_sales"])) {
        $sales = $_GET["id_sales"];

        // Hitung offset (mulai dari data ke berapa)
        $offset = ($page - 1) * $limit;

        // Kueri untuk mendapatkan data kegiatan
        $sql = "SELECT v.*, s.nama AS nama_sales, c.nama AS nama_customer
                FROM visits v
                LEFT JOIN sales s ON v.id_sales = s.id_sales
                LEFT JOIN cust c ON v.id_cust = c.id_cust
                WHERE FIND_IN_SET('$sales', v.id_sales) > 0
                ORDER BY tgl_visits DESC
                LIMIT $limit OFFSET $offset";
        $result = mysqli_query($conn, $sql);

        // Hitung total baris data
        $sql_total = "SELECT COUNT(*) AS total FROM visits WHERE FIND_IN_SET('$sales', id_sales)";
        $result_total = mysqli_query($conn, $sql_total);
        $row_total = mysqli_fetch_assoc($result_total);
        $total_pages = ceil($row_total['total'] / $limit);
    } else {
        echo "ID Teknisi tidak valid.";
        exit(); // Keluar dari script jika ID Teknisi tidak valid
    }
    ?>

    <div class="card p-4 mt-4">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <?php
                    // Mendapatkan nama teknisi
                    $cekNm = "SELECT nama FROM sales WHERE id_sales = $sales";
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
                            <th scope="col">Customer</th>
                            <th scope="col">Tanggal Request</th>
                            <th scope="col">Tanggal Mulai</th>
                            <th scope="col">Tanggal Selesai</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . $no . "</td>";
                            echo "<td><a href='customer-detail.php?id_cust=" . $row["id_cust"] . "'>" . $row["nama_customer"] . "</a></td>";
                            echo "<td>" . ($row["tgl_visits"] ? date("d M Y", strtotime($row["tgl_visits"])) : "-") . "</td>";
                            echo "<td>" . ($row["tgl_mulai"] ? date("d M Y", strtotime($row["tgl_mulai"])) : "-") . "</td>";
                            echo "<td>" . ($row["tgl_selesai"] ? date("d M Y", strtotime($row["tgl_selesai"])) : "-") . "</td>";

                            // Menentukan teks status
                            $status = $row["status"];
                            $statusText = "";
                            switch ($status) {
                                case "Pending":
                                    $statusText = "Dijadwalkan";
                                    break;
                                case "On Process":
                                    $statusText = "Dalam Proses";
                                    break;
                                case "Clear":
                                    $statusText = "Selesai";
                                    break;
                                case "Pause":
                                    $statusText = "Lanjut Nanti";
                                    break;
                                default:
                                    $statusText = $status;
                                    break;
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

    <!-- Tambahkan navigasi pagination -->
    <nav aria-label="Page navigation example">
        <ul class="pagination justify-content-center mt-3">
            <!-- Tombol Previous -->
            <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                <a class="page-link" href="<?php if ($page <= 1) echo '#'; else echo "?page=" . ($page - 1) . "&id_teknisi=" . $teknisi; ?>"><i class="material-icons opacity-10">arrow_back_ios</i></a>
            </li>
            <!-- Tampilkan halaman -->
            <?php
            // Batasi jumlah nomor halaman yang ditampilkan
            $max_pages = min(5, $total_pages); // Misalnya, tampilkan maksimal 5 nomor halaman
            $start_page = max(1, $page - floor($max_pages / 2));
            $end_page = min($total_pages, $start_page + $max_pages - 1);

            for ($i = $start_page; $i <= $end_page; $i++) :
            ?>
                <li class="page-item <?php if ($page == $i) echo 'active'; ?>">
                    <a class="page-link text-white bg-gradient-info border-info" href="?page=<?php echo $i . "&id_teknisi=" . $teknisi; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <!-- Tombol Next -->
            <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                <a class="page-link" href="<?php if ($page >= $total_pages) echo '#'; else echo "?page=" . ($page + 1) . "&id_teknisi=" . $teknisi; ?>"><i class="material-icons opacity-10">arrow_forward_ios</i></a>
            </li>
        </ul>
    </nav>
</div>
