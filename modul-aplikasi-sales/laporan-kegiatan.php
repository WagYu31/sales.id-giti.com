<?php
include "../conn.php";
include "../session.php";
include "../get-user-data.php";
$pageNow = "Laporan";
$currentPage = "Today";
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <?php
  include "head.php";
  ?>
  <style>
    ul#data-tek li:nth-child(odd), ul#data-rincian li:nth-child(odd) {
      background-color: white;
    }

    ul#data-tek li:nth-child(even), ul#data-rincian li:nth-child(even) {
      background-color: #efefef;
      border-radius: 0;
    }
        #toggleLoadMore {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }
    .modal-lg {
        width: 60vw !important;
    }
    /* CSS untuk mengatur lebar modal menjadi 100vw pada perangkat mobile */
    @media (max-width: 767px) {
        .modal-lg {
            width: 95vw !important;
        }
    }

  </style>
</head>

<body class="g-sidenav-show  bg-gray-200">
  <?php
  include "cek-menu.php";
  ?>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
    <!-- Navbar -->
    <?php
    include "nav-top.php";
    $todayDate = formatTanggal('dd MMMM yyyy');
    ?>
    <!-- End Navbar -->
    <div class="container-fluid py-4">

      <div class="row mb-4 mt-0">

        <?php
        include "laporan-db-kegiatan.php";
        ?>

      </div>
      <?php
      include "footer.php";
      ?>
    </div>


<!-- Modal untuk memasukkan bonus -->
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


<!-- Modal untuk memasukkan invoice -->
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


<!-- Modal untuk memasukkan invoice -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modalDetail">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">Riwayat Waktu Pengerjaan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="detailForm" data-kode="">
                <div class="modal-body">
                    <div class="w-100 mt-2">
                        <div id="dataDetailTek" style="width: 100%;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-gradient-danger" data-bs-dismiss="modal">Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>


  </main>
  <?php
  include "js-include.php";
  ?>
  <script>
    var win = navigator.platform.indexOf('Win') > -1;
    if (win && document.querySelector('#sidenav-scrollbar')) {
      var options = {
        damping: '0.5'
      }
      Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
    }
  </script>
  
  <script>
$(document).ready(function() {
    // Fungsi untuk menangani klik pada tombol bonus
    $('.bonus-btn').click(function() {
        var tekId = $(this).data("id");
        var kodeTran = $(this).data("kode");
        // Reset form dan atur nilai data-id
        $("#bonusForm")[0].reset();
        $("#bonusForm").attr("data-id", tekId);
        $("#bonusForm").attr("data-kode", kodeTran);
        $("#bonusModal").modal("show");
    });

    // Fungsi untuk menangani klik pada tombol submit
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
                    alert("Berhasil memperbarui bonus dan/atau denda kegiatan.");
                    window.location.reload();
                } else {
                    alert("Gagal memperbarui bonus dan/atau denda kegiatan.");
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
        $("#invForm").attr("data-kode", kodeTran); // Set nilai data-kode
        $("#invModal").modal("show");
    });
    
    // Fungsi untuk menangani klik pada tombol submit
    $("#submitInv").click(function() {
        var invoice = $("#invInput").val();
        var kodeTran = $("#invForm").data("kode"); // Ambil nilai kode transaksi dari data-kode
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
    
    
    $(".replay-button").click(function() {
      var kegiatanId = $(this).data("id");
      if (confirm("Apakah Anda yakin ingin mengulang memberi bonus dan denda kegiatan ini?")) {
        $.ajax({
          url: "proses_replay.php",
          type: "POST",
          data: {
            kegiatanId: kegiatanId
          },
          success: function(response) {
            if (response === "success") {
              alert("Kegiatan berhasil direset.");
              window.location.reload();
            } else {
              alert("Gagal mereset data.");
            }
          },
          error: function() {
            alert("Terjadi kesalahan saat menghubungi server.");
          }
        });
      }
    });
    
    
        $(".detailBtn").click(function(){
            var id_teknisi = $(this).data('id'); // Ambil id_teknisi dari data-id
            var kode_transaksi = $(this).data('kode'); // Ambil kode transaksi dari data-kode
            
            // Kirim permintaan AJAX untuk mendapatkan data berdasarkan id_teknisi dan kode transaksi
            $.ajax({
                url: 'get-data-rincian-pekerjaan.php', // Ganti dengan URL skrip PHP yang mengambil data dari database
                type: 'POST',
                data: {id_teknisi: id_teknisi, kode_transaksi: kode_transaksi},
                success: function(response) {
                    // Isi div #dataTek dengan data yang diterima dari server
                    $("#dataDetailTek").html(response);
                },
                error: function(xhr, status, error) {
                    // Tangani kesalahan jika ada
                    console.error(xhr.responseText);
                }
            });
        });
});
</script>


  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  
</body>

</html>