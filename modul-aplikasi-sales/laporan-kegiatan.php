<?php
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
  <?php include "head.php"; ?>
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
    @media (max-width: 767px) {
        .modal-lg {
            width: 95vw !important;
        }
    }
  </style>
</head>

<body class="bg-light">
  <?php include "cek-menu.php"; ?>

  <main class="main-content">
    <?php
    include "nav-top.php";
    $todayDate = formatTanggal('dd MMMM yyyy');
    ?>

    <div class="container-fluid p-0">
      <div class="row mb-4 mt-0">
        <?php include "laporan-db.php"; ?>
      </div>
      <?php include "footer.php"; ?>
    </div>

  </main>
  <?php include "js-include.php"; ?>
</body>
</html>