<?php
/**
 * laporan-kegiatan.php - Laporan Kunjungan & Visit Sales (Loewix Sales)
 */
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";

$pageNow = "Laporan Visit (GPS)";
$currentPage = "Today";
$role = $_SESSION['role'] ?? 'sales';
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Laporan Visit (GPS) — Loewix Sales</title>
  <?php include __DIR__ . "/head.php"; ?>
  
  <style>
    .modal-lg {
        max-width: 800px;
    }
    @media (max-width: 767px) {
        .modal-lg {
            max-width: 95vw !important;
            margin: 10px auto;
        }
    }
  </style>
</head>

<body class="bg-light">
  <?php include __DIR__ . "/cek-menu.php"; ?>

  <main class="main-content">
    <?php
    include __DIR__ . "/nav-top.php";
    $todayDate = formatTanggal('dd MMMM yyyy');
    ?>

    <div class="container-fluid px-2 px-sm-3 px-md-4 py-2">
      <div class="row mb-4 mt-0">
        <?php include __DIR__ . "/laporan-db-cust.php"; ?>
      </div>
      <?php include __DIR__ . "/footer.php"; ?>
    </div>

    <!-- Modal untuk Bonus -->
    <div class="modal fade" id="bonusModal" tabindex="-1" aria-labelledby="bonusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bonusModalLabel">Bonus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="bonusForm">
                    <div class="modal-body">
                        <div class="input-group d-flex flex-row justify-content-start align-items-start">
                            <label class="col-12">Nomor Invoice</label>
                            <input type="text" name="invoice" id="invoiceInput" class="form-control border p-2" placeholder="Masukkan nomor Invoice">
                        </div>
                        <div class="input-group d-flex flex-row justify-content-start align-items-start mt-3">
                            <label class="col-12">Bonus</label>
                            <span class="text-center w-10 p-2 bg-gradient-info text-white border-end-0" style="border-radius: 7px 0 0 7px;">Rp</span>
                            <input type="number" name="bonus" id="bonusInput" class="form-control border p-2" placeholder="Masukkan nominal bonus">
                        </div>
                        <div class="input-group d-flex flex-row justify-content-start align-items-start mt-3">
                            <label class="col-12">Denda</label>
                            <span class="text-center w-10 p-2 bg-gradient-info text-white border-end-0" style="border-radius: 7px 0 0 7px;">Rp</span>
                            <input type="number" id="dendaInput" name="denda" class="form-control border p-2 text-start w-70" placeholder="Masukkan nominal denda">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn bg-gradient-danger" data-bs-dismiss="modal">Tutup</button>
                        <button type="button" class="btn bg-gradient-info" id="submitBonus">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk Invoice -->
    <div class="modal fade" id="invModal" tabindex="-1" aria-labelledby="invModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="invModalLabel">Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="invForm" data-kode="">
                    <div class="modal-body">
                        <div class="input-group d-flex flex-row justify-content-start align-items-start mt-3">
                            <label class="col-12">Nomor Invoice</label>
                            <input type="text" name="inv" id="invInput" class="form-control border p-2" placeholder="Masukkan nomor Invoice" value="">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn bg-gradient-danger" data-bs-dismiss="modal">Tutup</button>
                        <button type="button" class="btn bg-gradient-info" id="submitInv">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Riwayat Waktu / Detail (Sesuai Gambar 2) -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modalDetail">
            <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header border-bottom py-3 px-4" style="background: #ffffff;">
                    <h5 class="modal-title font-weight-bold text-dark fs-6" id="detailModalLabel">Riwayat Waktu Pengerjaan</h5>
                    <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 p-md-4">
                    <div class="w-100">
                        <div id="dataDetailTek" style="width: 100%;"></div>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 px-4 bg-light">
                    <button type="button" class="btn btn-danger px-4 py-2 text-uppercase fw-bold" style="border-radius: 8px; font-size: 13px;" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

  </main>

  <?php include __DIR__ . "/js-include.php"; ?>

  <script>
    $(document).ready(function() {
        $('.bonus-btn').click(function() {
            var tekId = $(this).data("id");
            var kodeTran = $(this).data("kode");
            $("#bonusForm")[0].reset();
            $("#bonusForm").attr("data-id", tekId);
            $("#bonusForm").attr("data-kode", kodeTran);
            $("#bonusModal").modal("show");
        });

        $("#submitBonus").click(function() {
            var tekId = $("#bonusForm").data("id");
            var kodeTran = $("#bonusForm").data("kode");
            var bonus = $("#bonusInput").val();
            var denda = $("#dendaInput").val();
            var invoice = $("#invoiceInput").val();

            $.ajax({
                url: "proses_update_bonus.php",
                type: "POST",
                data: {
                    tekId: tekId,
                    kodeTran: kodeTran,
                    bonus: bonus,
                    denda: denda,
                    invoice: invoice
                },
                success: function(response) {
                    if (response.trim() === "success") {
                        $("#bonusModal").modal("hide");
                        alert("Berhasil memperbarui bonus/denda kunjungan.");
                        window.location.reload();
                    } else {
                        alert("Gagal memperbarui data.");
                    }
                },
                error: function() {
                    alert("Terjadi kesalahan saat menghubungi server.");
                }
            });
        });
        
        $('.inv-btn').click(function() {
            var kodeTran = $(this).data("kode");
            var invData = $(this).data("invdata");
            $("#invInput").val(invData);
            $("#invForm").attr("data-kode", kodeTran);
            $("#invModal").modal("show");
        });
        
        $("#submitInv").click(function() {
            var invoice = $("#invInput").val();
            var kodeTran = $("#invForm").data("kode");
            $.ajax({
                url: "proses_update_inv.php",
                type: "POST",
                data: {
                    kodeTran: kodeTran,
                    invoice: invoice
                },
                success: function(response) {
                    if (response.trim() === "success") {
                        $("#invModal").modal("hide");
                        alert("Berhasil memperbarui Nomor Invoice.");
                        window.location.reload();
                    } else {
                        alert("Gagal memperbarui Nomor Invoice.");
                    }
                },
                error: function() {
                    alert("Terjadi kesalahan saat menghubungi server.");
                }
            });
        });

        $(document).on('click', '.detailBtn', function(){
            var id_sales = $(this).data('id');
            var kode_transaksi = $(this).data('kode');
            
            $("#dataDetailTek").html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><div class="small text-muted mt-2">Memuat rincian kunjungan...</div></div>');
            
            $.ajax({
                url: 'get-data-rincian-pekerjaan.php',
                type: 'POST',
                data: {id_sales: id_sales, kode_transaksi: kode_transaksi},
                success: function(response) {
                    $("#dataDetailTek").html(response);
                },
                error: function(xhr, status, error) {
                    $("#dataDetailTek").html('<div class="alert alert-danger p-3">Gagal memuat rincian data kunjungan: ' + error + '</div>');
                }
            });
        });
    });
  </script>
</body>
</html>