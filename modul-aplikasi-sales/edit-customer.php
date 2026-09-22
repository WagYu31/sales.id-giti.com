<?php
include "../conn.php";
include "../session.php";
include "../get-user-data.php";
$pageNow = "Data Customer";
// Tangkap data dari form
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["id"])) {
    $id_customer = $_GET["id"];
}

// Tangkap data dari form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_customer_get = $_GET['id'];
    $nama = $_POST["nama_pelanggan"];
    $no_tlp = $_POST["no_telepon"];
    $no_wa = $_POST["no_whatsapp"];
    $alamat = $_POST["alamat_pelanggan"];
    $kota = $_POST["kota_pelanggan"];
    $kategori = $_POST["kategori_pelanggan"];
    $email = $_POST["email_pelanggan"];
    $website = $_POST["website"];

    $nama_toko = $_POST["nama_toko"];
    $cp = $_POST["contact_person"];
    $alamat_toko = $_POST["alamat_toko"];
    $kota_toko = $_POST["kota_toko"];

    $ig = $_POST["instagram"];
    $fb = $_POST["facebook"];
    $shopee = $_POST["shopee"];
    $tokped = $_POST["tokopedia"];
    $lazada = $_POST["lazada"];
    $other = $_POST["other"];


    $no_tlp = preg_replace("/[^0-9]/", "", $no_tlp);
    $no_wa = preg_replace("/[^0-9]/", "", $no_wa);
    $cp = preg_replace("/[^0-9]/", "", $cp);

    // Fungsi untuk menyesuaikan format nomor telepon
    function formatPhoneNumber($number)
    {
        if (substr($number, 0, 2) == "08") {
            // Jika 2 angka pertama adalah "08", biarkan seperti itu
            return $number;
        } elseif (substr($number, 0, 2) == "62") {
            // Jika 2 angka pertama adalah "62", ubah menjadi "0"
            return "0" . substr($number, 2);
        } elseif (substr($number, 0, 3) == "+62") {
            // Jika 2 angka pertama adalah "62", ubah menjadi "0"
            return "0" . substr($number, 3);
        } elseif (substr($number, 0, 1) == "8") {
            // Jika angka pertama adalah "8", tambahkan "0" sebelum angka "8"
            return "0" . $number;
        } else {
            // Jika tidak memenuhi kondisi di atas, tambahkan "08" di depannya
            return "08" . $number;
        }
    }

    // Menyesuaikan format nomor telepon, nomor WhatsApp, dan kontak person
    $no_tlp_formatted = formatPhoneNumber($no_tlp);
    $no_wa_formatted = formatPhoneNumber($no_wa);
    $cp_formatted = formatPhoneNumber($cp);


    // Update data dalam database
    $sql = "UPDATE cust SET 
            nama='$nama', 
            no_tlp='$no_tlp_formatted', 
            no_wa='$no_wa_formatted',
            alamat='$alamat',
            kota='$kota',
            kategori='$kategori',
            email='$email',
            website='$website',
            nama_toko='$nama_toko',
            contact_person='$cp_formatted',
            alamat_toko='$alamat_toko',
            kota_toko='$kota_toko',
            ig='$ig',
            fb='$fb',
            shopee='$shopee',
            tokped='$tokped',
            lazada='$lazada',
            other='$other'
        WHERE id_cust=$id_customer_get";


    if (mysqli_query($conn, $sql)) {
        // Redirect atau refresh halaman
        echo '<script>window.location.href = "customer.php";</script>';
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
                include "edit-customer-db.php";
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