<?php
include "../conn.php";
include "../session.php";
include "../get-user-data.php";
$pageNow = "Data Customer";
    // Tangkap data dari form
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
      $nik = $_POST["nik"];
      $nama = $_POST["nama"];
      $no_wa = $_POST["no_wa"];
      $jbtn = "Teknisi";
      
      // Hilangkan karakter selain angka dari nomor telepon
      $no_tlp = preg_replace("/[^0-9]/", "", $no_wa);
  
      // Lakukan validasi data (jika diperlukan)
  
      // Ubah format nomor telepon
      if (substr($no_tlp, 0, 1) == "0") {
          // Jika angka pertama adalah 0, biarkan seperti itu
      } elseif (substr($no_tlp, 0, 2) == "62") {
          // Jika angka pertama adalah 62, ganti dengan 0
          $no_tlp = "0" . substr($no_tlp, 2);
      } elseif (substr($no_tlp, 0, 3) == "+62") {
          // Jika angka pertama adalah +62, ganti dengan 0
          $no_tlp = "0" . substr($no_tlp, 3);
      } elseif (substr($no_tlp, 0, 5) == "+6262") {
          // Jika angka pertama adalah +6262, ganti dengan 0
          $no_tlp = "0" . substr($no_tlp, 5);
      } elseif (substr($no_tlp, 0, 4) == "6262") {
          // Jika angka pertama adalah 6162, ganti dengan 0
          $no_tlp = "0" . substr($no_tlp, 4);
      } elseif (substr($no_tlp, 0, 6) == "+62+62") {
          // Jika angka pertama adalah +62+62, ganti dengan 0
          $no_tlp = "0" . substr($no_tlp, 6);
      } else {
          // Jika angka pertama bukan 0, 62, atau +62, tambahkan 0 di depannya
          $no_tlp = "0" . $no_tlp;
      }

      // Lakukan validasi data (jika diperlukan)

      // Masukkan data ke dalam database
      $sql = "INSERT INTO teknisi (nik, nama, no_wa) VALUES ('$nik', '$nama', '$no_tlp')";

      if (mysqli_query($conn, $sql)) {
          $id_teknisi = mysqli_insert_id($conn);

          // Masukkan data ke dalam database
          $sqll = "INSERT INTO loewix (nik, nama, no_tlp, jabatan, id_teknisi) VALUES ('$nik', '$nama', '$no_tlp', '$jbtn', $id_teknisi)";
  
          if (mysqli_query($conn, $sqll)) {
              // Redirect atau refresh halaman
              echo '<script>window.location.href = "teknisi-db.php";</script>';
              exit();
          } else {
              echo "Error: " . $sqll . "<br>" . mysqli_error($conn);
          }
          exit();
      } else {
          echo "Error: " . $sql . "<br>" . mysqli_error($conn);
      }
      
  }
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
        #toggleLoadMore {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
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

      <div class="row mb-4 mt-4">

        <?php
            include "customer-db-detail.php";
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
    function deleteTek(nik) {
        if (confirm("Apakah Anda yakin ingin menghapus data sales ini?")) {
            // Konfirmasi penghapusan

            // Buat objek XMLHttpRequest
            var xhr = new XMLHttpRequest();

            var url = "hapus_teknisi.php";

            // Buat data yang akan dikirimkan dalam permintaan POST
            var data = new FormData();
            data.append("nik", nik); // Mengirim ID sales yang akan dihapus

            // Atur jenis permintaan dan URL
            xhr.open("POST", url, true);

            // Tangani perubahan status permintaan
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    // Tangani respons dari server
                    var response = xhr.responseText;
                    if (response === "sukses") {
                        // Data sales berhasil dihapus
                        location.reload(); // Muat ulang halaman untuk memperbarui tampilan
                    } else {
                        // Terjadi kesalahan
                        alert("Gagal menghapus data sales.");
                    }
                }
            };

            // Kirim permintaan dengan data yang sudah disiapkan
            xhr.send(data);
        }
    }
</script>

</body>

</html>