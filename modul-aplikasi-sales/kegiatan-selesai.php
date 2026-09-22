<?php
include "../conn.php";
include "../session.php";
include "../get-user-data.php";
$pageNow = "Dashboard";
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
    ul#data-tek li:nth-child(odd) {
      background-color: white;
    }

    ul#data-tek li:nth-child(even) {
      background-color: #efefef;
      border-radius: 0;
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

      <?php include 'top-point.php'; ?>

      <div class="row mb-4 mt-4">

        <?php
        include "kegiatan-db.php";
        ?>

      </div>
      <?php
      include "footer.php";
      ?>
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

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  
  <script>
    
    $(".view-btn").click(function() {
        var kegiatanId = $(this).data("id");
        window.location.href = "view-kegiatan.php?kode_transaksi=" + kodeTransaksi;
    });
    $(".edit-btn").click(function() {
        var kodeTransaksi = $(this).data("id");
        window.location.href = "edit_kegiatan.php?kode_transaksi=" + kodeTransaksi;
    });
</script>

  <script>
    $(".jadwalkan-btn").click(function() {
      var kegiatanId = $(this).data("id");
      $("#jadwalkanForm")[0].reset();
      $("#jadwalkanForm").attr("data-id", kegiatanId);
      $("#jadwalkanModal").modal("show");

      var tglRequest = $(this).data("tgl-request");
      var tanggalInput = document.getElementById("tanggal");
      var jamInput = document.getElementById("jam");

      if (tglRequest) {
        var tglWaktu = tglRequest.split(" ");
        if (tglWaktu.length === 2) {
          var tanggal = tglWaktu[0];
          var waktu = tglWaktu[1];
          tanggalInput.value = tanggal;
          jamInput.value = waktu;

          handleDateChange();
        }
      }
    });


    $("#submitJadwalkan").click(function() {
      var kegiatanId = $("#jadwalkanForm").data("id");
      var tanggal = $("#tanggal").val();
      var jam = $("#jam").val();
      var selectedTechnicians = $(".teknisi-checkbox:checked").map(function() {
        return this.value;
      }).get();

      $.ajax({
        url: "proses_jadwalkan.php",
        type: "POST",
        data: {
          kegiatanId: kegiatanId,
          teknisi: selectedTechnicians,
          tanggal: tanggal,
          jam: jam
        },
        success: function(response) {
          if (response === "success") {
            $("#jadwalkanModal").modal("hide");
            alert("Berhasil");
            window.location.reload();
          } else {
            alert("Gagal menjadwalkan kegiatan.");
          }
        },
        error: function() {
          alert("Terjadi kesalahan saat menghubungi server.");
        }
      });
    });


    $(".hapus-btn").click(function() {
      var kegiatanId = $(this).data("id");
      var nama = $(this).data("nama");
      var kode = $(this).data("kode");
      if (confirm("Apakah Anda yakin ingin menghapus kegiatan ini?")) {
        $.ajax({
          url: "proses_hapus_kegiatan.php",
          type: "POST",
          data: {
            kegiatanId: kegiatanId,
            nama: nama,
            kode: kode
          },
          success: function(response) {
            if (response === "success") {
              alert("Kegiatan berhasil dihapus.");
              window.location.reload();
            } else {
              alert("Gagal menghapus kegiatan.");
            }
          },
          error: function() {
            alert("Terjadi kesalahan saat menghubungi server.");
          }
        });
      }
    });


$(".delete-btn").click(function() {
  var nama = $(this).data("nama");
  var kode = $(this).data("kode");
  if (confirm("Apakah Anda yakin ingin menghapus kegiatan ini?")) {
    $.ajax({
      url: "delete-kegiatan.php",
      type: "POST",
      data: {
        nama: nama,
        kode: kode
      },
      success: function(response) {
        if (response === "success") {
          alert("Kegiatan berhasil dihapus.");
          window.location.reload();
        } else {
          alert("Gagal menghapus kegiatan.");
        }
      },
      error: function() {
        alert("Terjadi kesalahan saat menghubungi server.");
      }
    });
  }
});
  </script>
  <script>
    function handleDateChange() {
      var selectedDate = document.getElementById("tanggal").value;
      var selectedTime = document.getElementById("jam").value;

      var checkboxes = document.querySelectorAll(".teknisi-checkbox");

      checkboxes.forEach(function(checkbox) {
        checkbox.disabled = false;
      });

      var xhr = new XMLHttpRequest();
      xhr.open("GET", "get-kegiatan-teknisi.php?tanggal=" + selectedDate, true);

      xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
          var kegiatanData = JSON.parse(xhr.responseText);

          checkboxes.forEach(function(checkbox) {
            var id_teknisi = checkbox.value;
            var teknisiData = kegiatanData.find(function(data) {
              return data.id_teknisi == id_teknisi;
            });

            if (teknisiData) {
              checkbox.disabled = false;
              // Format ulang teks label
              var formattedText = " ( ";
              if (teknisiData.jenis) {
                formattedText += teknisiData.jenis + " ";
              }
              formattedText += ` jam ${teknisiData.tgl_request.substring(11, 16)}`;
              if (teknisiData.status == "Pending") {
                formattedText += " - Dijadwalkan";
              } else if (teknisiData.status == "On Process") {
                formattedText += " - Dalam proses";
              }
              formattedText += ")";

              checkbox.nextElementSibling.textContent = checkbox.nextElementSibling.textContent.replace(/\(.*\)/, "") + formattedText;
            } else {
              checkbox.nextElementSibling.textContent = checkbox.nextElementSibling.textContent.replace(/\(.*\)/, "");
            }
          });
        }
      };

      xhr.send();
    }

    document.getElementById("tanggal").addEventListener("change", handleDateChange);

    window.addEventListener("load", handleDateChange);
  </script>


  <script>
    const jamInput = document.getElementById("jam");

    jamInput.addEventListener("input", function() {
      const selectedTime = new Date(`2000-01-01T${jamInput.value}`);
      const minTime = new Date(`2000-01-01T07:00`);
      const maxTime = new Date(`2000-01-01T20:00`);

      if (selectedTime < minTime || selectedTime > maxTime) {
        alert("Jam harus berada dalam rentang antara jam 07:00 pagi sampai jam 20:00 malam.");
        jamInput.value = ""; 
      }
    });
  </script>

</body>

</html>