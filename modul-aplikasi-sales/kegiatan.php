<?php
/**
 * kegiatan.php - Jadwal & Task Kunjungan Sales (Web Management)
 */
include_once __DIR__ . "/conn.php";
include_once __DIR__ . "/session.php";
include_once __DIR__ . "/get-user-data.php";

$pageNow = "Jadwal Kunjungan";
$currentPage = "Today";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Jadwal Kunjungan Sales App — Loewix Sales</title>
  <?php include __DIR__ . "/head.php"; ?>
  <style>
    ul#data-tek li:nth-child(odd) { background-color: white; }
    ul#data-tek li:nth-child(even) { background-color: #f8fafc; border-radius: 0; }
    .stat-card-premium {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.03);
        border: 1px solid #e2e8f0;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .stat-card-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }
    .stat-label-premium {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 4px;
    }
    .stat-count-premium {
        font-size: 24px;
        font-weight: 800;
        font-family: 'Outfit', sans-serif;
        color: #0f172a;
        margin: 0;
    }
    .stat-icon-premium {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
  </style>
</head>

<body class="bg-light">
  <?php include __DIR__ . "/cek-menu.php"; ?>

  <main class="main-content">
    <?php include __DIR__ . "/nav-top.php"; ?>

    <div class="container-fluid p-0">
      <!-- Quick Action: Buat Jadwal Baru -->
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <h4 class="fw-bold mb-1" style="font-family:'Outfit',sans-serif;">Jadwal Kunjungan Sales (Mobile App)</h4>
          <p class="text-muted small mb-0">Kelola dan pantau seluruh jadwal penugasan sales ke toko & dealer mitra.</p>
        </div>
        <a href="kegiatan-baru.php" class="btn btn-primary d-inline-flex align-items-center gap-2 rounded-pill px-3 py-2 fw-semibold shadow-sm">
          <i class="bi bi-plus-circle-fill"></i>
          <span>Buat Jadwal Baru</span>
        </a>
      </div>

      <div class="row mb-4">
        <?php include __DIR__ . "/kegiatan-db.php"; ?>
      </div>

      <?php include __DIR__ . "/footer.php"; ?>
    </div>
  </main>

  <?php include __DIR__ . "/js-include.php"; ?>

  <script>
    // Handler View Kegiatan
    $(document).on("click", ".view-btn", function() {
        var id = $(this).data("id");
        if (id) {
            window.location.href = "detail_kegiatan.php?id=" + id;
        }
    });

    // Handler Reschedule Admin
    $(document).on("click", ".reschedule-btn", function() {
        var id = $(this).data("id");
        Swal.fire({
            title: 'Jadwalkan Ulang (Reschedule)',
            html: `
                <div class="text-start mb-3">
                    <label class="form-label small fw-bold text-muted">Tanggal & Jam Baru</label>
                    <input type="datetime-local" id="swal_new_jadwal" class="form-control" />
                </div>
                <div class="text-start">
                    <label class="form-label small fw-bold text-muted">Alasan Reschedule</label>
                    <textarea id="swal_reason" class="form-control" rows="2" placeholder="Contoh: Toko tutup / jadwal dimundurkan"></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Simpan Reschedule',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2563EB',
            preConfirm: () => {
                const newJadwal = document.getElementById('swal_new_jadwal').value;
                const reason = document.getElementById('swal_reason').value;
                if (!newJadwal) {
                    Swal.showValidationMessage('Silakan pilih tanggal dan jam baru!');
                }
                return { newJadwal: newJadwal, reason: reason };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'proses_reschedule_admin.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        kegiatan_id: id,
                        new_jadwal: result.value.newJadwal,
                        reason: result.value.reason
                    },
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire('Berhasil', res.message || 'Jadwal berhasil diperbarui!', 'success').then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                        }
                    },
                    error: function(xhr, status, err) {
                        Swal.fire('Error', 'Gagal memproses request: ' + err, 'error');
                    }
                });
            }
        });
    });

    // Handler Hapus / Batalkan Kegiatan
    $(document).on("click", ".hapus-btn", function() {
        var id = $(this).data("id");
        Swal.fire({
            title: 'Hapus Kegiatan?',
            text: 'Jadwal kunjungan ini akan dihapus dari aplikasi sales.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#64748B',
            confirmButtonText: 'Ya, Hapus'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'proses-hapus-kunjungan.php',
                    type: 'POST',
                    data: { id: id },
                    success: function() {
                        Swal.fire('Terhapus', 'Kegiatan berhasil dihapus.', 'success').then(() => {
                            window.location.reload();
                        });
                    }
                });
            }
        });
    });
  </script>
</body>
</html>
