<?php
session_start();
include "../conn.php";

// Fungsi untuk menghasilkan ID acak dengan 3 digit huruf dan angka
function generateUniqueID()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $idTeam = '';
    for ($i = 0; $i < 3; $i++) {
        $idTeam .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $idTeam;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kegiatanId = $_POST["kegiatanId"];
    $tanggal = $_POST["tanggal"];
    $jam = $_POST["jam"];
    $selectedTechnicians = $_POST["teknisi"];

    // Gabungkan array teknisi menjadi string dengan koma
    $idTeknisi = implode(",", $selectedTechnicians);

    $tgl_request = $tanggal . ' ' . $jam;

    $idTeam = generateUniqueID();

    $firstTechnician = array_shift($selectedTechnicians); 

    $stat = "Pending";

    $updateQuery = "UPDATE kegiatan SET id_teknisi = ?, tgl_request = ?, status = 'Pending', id_team = ? WHERE id_kegiatan = ?";
    if ($stmt = mysqli_prepare($conn, $updateQuery)) {
        mysqli_stmt_bind_param($stmt, "sssi", $firstTechnician, $tgl_request, $idTeam, $kegiatanId);
        if (mysqli_stmt_execute($stmt)) {
            $selectDataQuery = "SELECT id_cust, kode_transaksi, keterangan, jenis, req_by FROM kegiatan WHERE id_kegiatan = $kegiatanId";
            $result_selectDataQuery = mysqli_query($conn, $selectDataQuery);
            $data = mysqli_fetch_assoc($result_selectDataQuery);
            $id_cust = $data["id_cust"];
            $jenis = $data["jenis"];
            $kode_transaksi = $data["kode_transaksi"];
            $keterangan = $data["keterangan"];
            $req_by = $data["req_by"];

            $teamInsertQuery = "INSERT INTO kegiatan (id_team, id_teknisi, tgl_request, status, id_cust, jenis, kode_transaksi, keterangan, req_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?,?)";
            if ($teamStmt = mysqli_prepare($conn, $teamInsertQuery)) {
                foreach ($selectedTechnicians as $idTeknisi) {
                    mysqli_stmt_bind_param($teamStmt, "sississss", $idTeam, $idTeknisi, $tgl_request, $stat, $id_cust, $jenis, $kode_transaksi, $keterangan, $req_by);
                    mysqli_stmt_execute($teamStmt);
                }
            }
            mysqli_stmt_close($teamStmt);

            echo "success";

            $tgl_now = date("Y-m-d H:i:s");
            $hist = "Menjadwalkan kegiatan $kode_transaksi";
            $tipe = "Jadwal";
            $history = "INSERT INTO history_line (nama, history, tipe, tanggal) VALUES (?, ?, ?, ?)";

            if ($stmtHistory = mysqli_prepare($conn, $history)) {
                mysqli_stmt_bind_param($stmtHistory, "ssss", $req_by, $hist, $tipe, $tgl_now);
                if (mysqli_stmt_execute($stmtHistory)) {
                } else {
                    echo "Terjadi kesalahan dalam menambahkan catatan ke tabel history_line: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmtHistory);
            }

        } else {
            echo "error";
        }
    } else {
        echo "error";
    }

    mysqli_stmt_close($stmt);
} else {
    echo "error";
}

mysqli_close($conn);
