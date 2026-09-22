<?php
include "../conn.php";
include "../session.php";
include "../get-user-data.php";
$pageNow = "Target Tercapai";
$currentPage = "Today";
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
<script>
  var timer; // Variable untuk menyimpan timer
  var hasInteraction = false; // Menyimpan status interaksi pengguna
  var waitingForInteraction = false; // Menyimpan status menunggu interaksi

  // Fungsi untuk memperbarui halaman secara otomatis
  function reloadPage() {
    location.reload();
  }

  // Fungsi untuk memulai timer untuk refresh otomatis
  function startTimer() {
    timer = setTimeout(function() {
      // Jika masih menunggu interaksi, mulai refresh otomatis setelah jeda 5 detik
      if (waitingForInteraction) {
        reloadPage();
      } else {
        waitingForInteraction = true; // Set status menunggu interaksi menjadi true
        timer = setTimeout(reloadPage, 5000); // Refresh otomatis setelah jeda 5 detik
      }
    }, 10000); // Refresh otomatis setelah 10 detik
  }

  // Fungsi untuk mereset timer jika ada interaksi pengguna
  function resetTimer() {
    clearTimeout(timer); // Hentikan timer
    waitingForInteraction = false; // Set status menunggu interaksi menjadi false
    hasInteraction = true; // Set status interaksi menjadi true
    startTimer(); // Mulai ulang timer
  }

  // Mulai timer saat halaman dimuat
  startTimer();

  // Tambahkan event listener untuk interaksi pengguna (Desktop & Mobile Touch)
  document.addEventListener('mousemove', resetTimer);
  document.addEventListener('keydown', resetTimer);
  document.addEventListener('scroll', resetTimer);
  document.addEventListener('touchstart', resetTimer, { passive: true });
  document.addEventListener('touchmove', resetTimer, { passive: true });
  document.addEventListener('click', resetTimer, { passive: true });
</script>



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

      <div class="row mb-4 mt-0">

        <?php
        include "laporan-db.php";
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
  
</body>

</html>