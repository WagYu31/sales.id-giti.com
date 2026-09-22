<div class="container">
    <div class="card p-4">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-n3 lead font-weight-bold text-uppercase">Tambah Sales</h6>
                </div>
            </div>
        </div>
        <div class="card-body pb-0 p-0">
            <!-- Form input data teknisi -->
            <form class="row g-3" method="POST">
                <div class="col-md-4">
                    <label for="nik" class="form-label">NIP</label>
                    <input type="text" class="form-control border p-2" id="nip" name="nip" placeholder="NIP">
                </div>
                <div class="col-md-4">
                    <label for="nama" class="form-label">Nama</label>
                    <input type="text" class="form-control border p-2" id="nama" name="nama" placeholder="Nama">
                </div>
                <div class="col-md-4">
                    <label for="no_wa" class="form-label">Nomor WhatsApp</label>
                    <div class="col-12 col-md-11 d-flex flex-row">
                        <span class="text-start p-2 bg-gradient-info text-white w-14" style="border-radius:6px; border-top-right-radius:0; border-bottom-right-radius:0;">+62</span>
                        <input type="text" class="form-control border p-2 w-86" style="border-radius:6px; border-top-left-radius:0; border-bottom-left-radius:0;" id="no_tlp" name="no_tlp" placeholder="Nomor WhatsApp">
                    </div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn bg-gradient-info"><i class="bx bx-plus nav_icon"></i> Tambah</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-4 mt-4">
        <div class="card-header pb-0 p-3">
            <div class="row">
                <div class="col-12 d-flex align-items-center">
                    <h6 class="mb-0 mx-1 ms-n3 lead font-weight-bold text-uppercase">Data Sales</h6>
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
                            <th scope="col">NIP</th>
                            <th scope="col">Nama</th>
                            <th scope="col">Nomor Telepon</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM sales";
                        $result = mysqli_query($conn, $sql);

                        $no = 1;
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $nip = $row["nip"];
                                echo "<tr>";
                                echo "<td>" . $no . "</td>";
                                echo "<td>" . $row["nip"] . "</td>";
                                echo "<td>" . $row['nama'] . "</td>";

                                $nomorHandphone = $row['no_wa'];

                                // Cek apakah nomor handphone dimulai dengan angka 0
                                if (substr($nomorHandphone, 0, 1) === '0') {
                                    // Ganti angka 0 dengan 62
                                    $nomorHandphone = '62' . substr($nomorHandphone, 1);
                                }

                                echo "<td><a href='https://api.whatsapp.com/send?phone=$nomorHandphone' target='_blank'>";
                                echo $row['no_wa'];
                                echo "</a></td>";
                                // Tombol Delete
                                ?>
                                <td><button class='btn btn-danger' onclick='deleteSales("<?php echo $nip; ?>")'><i class='far fa-trash-alt'></i></button></td>
                                <?php
                                echo "</tr>";
                                $no++;
                            }
                        } else {
                            echo "<tr><td colspan='4'>Tidak ada data teknisi.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>