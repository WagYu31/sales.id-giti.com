<?php
include "conn.php";
include "session.php";
include "get-user-data.php";
$pageNow = "Sales";
$currentPage = "Today";

date_default_timezone_set('Asia/Jakarta');

// Soft Delete
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $conn->query("UPDATE sales SET deleted_at = NOW() WHERE id = '$id'");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Update Sales
$successMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $id = $_POST['update_id'];
    $nama = $_POST['edit_nama'];
    $nik = $_POST['edit_nik'];
    $telp = preg_replace('/\D/', '', $_POST['edit_telp']);
    $editPassword = $_POST['edit_password'] ?? '';
    $id_wilayah = intval($_POST['edit_id_wilayah'] ?? 0);

    if (substr($telp, 0, 1) === '0') {
        $telp = '62' . substr($telp, 1);
    } elseif (substr($telp, 0, 1) === '8') {
        $telp = '62' . $telp;
    } elseif (!str_starts_with($telp, '62')) {
        $telp = '62' . $telp;
    }

    if (!empty($editPassword)) {
        $hashedPassword = password_hash($editPassword, PASSWORD_DEFAULT);
        
        // Update sales table
        $stmt = $conn->prepare("UPDATE sales SET nama = ?, nik = ?, telp = ?, password = ?, id_wilayah = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssssii", $nama, $nik, $telp, $hashedPassword, $id_wilayah, $id);
        $stmt->execute();
        $stmt->close();

        // Update user_sales table (sync password, nama, username)
        $stmtUser = $conn->prepare("UPDATE user_sales SET nama = ?, username = ?, password = ?, updated_at = NOW() WHERE sales_id = ?");
        $stmtUser->bind_param("sssi", $nama, $nik, $hashedPassword, $id);
        $stmtUser->execute();
        $stmtUser->close();
    } else {
        // Update sales table
        $stmt = $conn->prepare("UPDATE sales SET nama = ?, nik = ?, telp = ?, id_wilayah = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssii", $nama, $nik, $telp, $id_wilayah, $id);
        $stmt->execute();
        $stmt->close();

        // Update user_sales table (sync nama and username)
        $stmtUser = $conn->prepare("UPDATE user_sales SET nama = ?, username = ?, updated_at = NOW() WHERE sales_id = ?");
        $stmtUser->bind_param("ssi", $nama, $nik, $id);
        $stmtUser->execute();
        $stmtUser->close();
    }
    
    $successMsg = "Profil Sales berhasil diperbarui!";
}

// Handle insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_id'])) {
    $nama = $_POST['nama'];
    $nik = $_POST['nik'];
    $rawTelp = $_POST['telp'];
    $password = $_POST['password'] ?? '';
    $id_wilayah = intval($_POST['id_wilayah'] ?? 0);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $telp = preg_replace('/\D/', '', $rawTelp);

    if (substr($telp, 0, 1) === '0') {
        $telp = '62' . substr($telp, 1);
    } elseif (substr($telp, 0, 1) === '8') {
        $telp = '62' . $telp;
    } elseif (!str_starts_with($telp, '62')) {
        $telp = '62' . $telp;
    }

    $stmt = $conn->prepare("INSERT INTO sales (nik, nama, telp, password, id_wilayah, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssssi", $nik, $nama, $telp, $hashedPassword, $id_wilayah);
    $stmt->execute();
    $newSalesId = $stmt->insert_id;
    $stmt->close();

    // Juga buat akun login di user_sales agar bisa masuk dari app mobile
    $stmtUser = $conn->prepare("INSERT INTO user_sales (sales_id, username, nama, password, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmtUser->bind_param("isss", $newSalesId, $nik, $nama, $hashedPassword);
    $stmtUser->execute();
    $stmtUser->close();

    $successMsg = "Sales baru berhasil ditambahkan!";
}

// Ambil data sales beserta wilayah
$salesData = mysqli_query($conn, "
    SELECT s.*, w.nama AS nama_wilayah 
    FROM sales s 
    LEFT JOIN wilayah w ON s.id_wilayah = w.id 
    WHERE s.deleted_at IS NULL 
    ORDER BY s.id DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <?php include "head.php"; ?>
  <style>
    /* ── Premium Styling ── */
    .card-premium {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.02);
      margin-bottom: 24px;
    }
    
    .section-header-premium {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 24px;
      background: #1e293b;
      color: #fff;
    }
    
    .section-header-premium h6 {
      margin: 0;
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      display: flex;
      align-items: center;
    }
    
    .card-body-premium {
      padding: 28px 24px;
    }

    /* ── Form inputs ── */
    .form-group-premium {
      margin-bottom: 20px;
    }
    
    .form-label-premium {
      display: block;
      font-size: 12px;
      font-weight: 700;
      color: #334155;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    
    .input-premium {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 10px 16px !important;
      font-size: 14px;
      color: #1e293b;
      background-color: #fff;
      transition: all 0.2s ease;
    }
    
    .input-premium:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
      outline: none;
    }

    /* ── Avatars in Table ── */
    .avatar-initials-table {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      color: #fff;
      font-size: 12px; font-weight: 700;
      display: inline-flex; align-items: center; justify-content: center;
      margin-right: 12px;
      vertical-align: middle;
      box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }
    
    .sales-identity-cell {
      display: flex;
      align-items: center;
      text-align: left;
    }

    /* ── Table custom styling ── */
    .premium-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .premium-table th {
      background: #f8fafc;
      border-bottom: 2px solid #e2e8f0;
      color: #64748b;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 14px 16px;
      text-align: left;
    }
    
    .premium-table td {
      padding: 14px 16px;
      border-bottom: 1px solid #f1f5f9;
      color: #334155;
      font-size: 13px;
      vertical-align: middle;
    }
    
    .premium-table tr:hover td {
      background-color: #f8fafc;
    }
    
    .nik-badge {
      font-family: monospace;
      background: #f1f5f9;
      color: #475569;
      padding: 4px 8px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 12px;
    }
    
    .wa-link {
      font-size: 13px; color: #10b981;
      text-decoration: none; display: inline-flex;
      align-items: center; gap: 4px; font-weight: 600;
    }
    
    .wa-link:hover { text-decoration: underline; }

    /* ── Action Buttons ── */
    .btn-act {
      width: 32px; height: 32px; padding: 0; display: inline-flex;
      align-items: center; justify-content: center; border-radius: 8px;
      border: 1px solid transparent; transition: all 0.2s; cursor: pointer; text-decoration: none;
    }
    .btn-act:hover { transform: scale(1.08); }
    .btn-act .material-symbols-outlined { font-size: 16px; }
    .btn-act-edit { background: #fffbeb; color: #d97706; border-color: #fef3c7; }
    .btn-act-edit:hover { background: #d97706; color: #fff; }
    .btn-act-delete { background: #fef2f2; color: #dc2626; border-color: #fee2e2; margin-left: 6px; }
    .btn-act-delete:hover { background: #dc2626; color: #fff; }

    .btn-submit-premium {
      background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
      color: #fff !important;
      border: none;
      border-radius: 10px;
      padding: 10px 24px;
      font-size: 13px; font-weight: 700;
      display: inline-flex; align-items: center; gap: 6px;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
      transition: all 0.22s ease;
      cursor: pointer;
    }
    
    .btn-submit-premium:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    }
    
    .btn-submit-premium:active {
      transform: translateY(0);
    }
    
    .btn-submit-premium .material-symbols-outlined {
      font-size: 16px;
    }

    /* ── Modal Premium Styling ── */
    .modal-content-premium {
      border-radius: 16px;
      border: none;
      overflow: hidden;
      box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }
    
    .modal-header-premium {
      background: #1e293b;
      color: #fff;
      padding: 20px 24px;
      border-bottom: none;
    }
    
    .modal-title-premium {
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .modal-body-premium {
      padding: 28px 24px;
      background: #fff;
    }
    
    .modal-footer-premium {
      background: #f8fafc;
      padding: 16px 24px;
      border-top: 1px solid #f1f5f9;
      display: flex;
      justify-content: flex-end;
      gap: 8px;
    }

    input[type="checkbox"] {
      -webkit-appearance: checkbox;
      -moz-appearance: checkbox;
      appearance: checkbox;
    }
    
    <?php include "css/floating-menu2.css"; ?>
  
    /* ── Mobile Responsive Enhancements ── */
    @media (max-width: 991.98px) {
      .card-body-premium {
        padding: 20px 16px !important;
      }
      .btn-act {
        min-width: 38px !important;
        min-height: 38px !important;
        border-radius: 10px !important;
        margin: 2px !important;
        touch-action: manipulation !important;
      }
      .btn-submit-premium {
        width: 100% !important;
        justify-content: center !important;
        min-height: 48px !important;
        touch-action: manipulation !important;
      }
      .premium-table th, .premium-table td {
        padding: 12px 10px !important;
      }
    }

  </style>
</head>
<body class="g-sidenav-show bg-gray-200">
<?php include "cek-menu.php"; ?>
<main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg ">
  <?php
    include "nav-top.php";
    $todayDate = formatTanggal('dd MMMM yyyy');
  ?>
  <div class="container-fluid py-4">
    
    <!-- Success Alert -->
    <?php if (!empty($successMsg)): ?>
      <div class="alert alert-success text-white font-weight-bold mb-4" style="background: #10b981; border: none; border-radius: 10px; padding: 14px 20px;">
        <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">check_circle</span>
        <?php echo $successMsg; ?>
      </div>
    <?php endif; ?>

    <!-- Card Tambah Sales -->
    <div class="card-premium">
      <div class="section-header-premium">
        <h6>
          <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">person_add</span>
          Tambah Sales Baru
        </h6>
      </div>
      <div class="card-body-premium">
        <form method="POST">
          <div class="row">
            <div class="col-md-3 form-group-premium">
              <label class="form-label-premium">Nama Sales</label>
              <input type="text" name="nama" class="input-premium" placeholder="Masukkan nama lengkap sales..." required>
            </div>
            <div class="col-md-2 form-group-premium">
              <label class="form-label-premium">NIK / Username</label>
              <input type="text" name="nik" class="input-premium" placeholder="Masukkan NIK unik..." required>
            </div>
            <div class="col-md-2 form-group-premium">
              <label class="form-label-premium">No. Telepon (WhatsApp)</label>
              <input type="text" name="telp" class="input-premium" placeholder="Contoh: 08123456789" required>
            </div>
            <div class="col-md-2 form-group-premium">
              <label class="form-label-premium">Wilayah Operasional</label>
              <select name="id_wilayah" class="input-premium" required>
                <option value="">-- Pilih Wilayah --</option>
                <?php 
                $wQuery = mysqli_query($conn, "SELECT * FROM wilayah WHERE deleted_at IS NULL ORDER BY nama ASC");
                while ($w = mysqli_fetch_assoc($wQuery)) {
                    echo "<option value='{$w['id']}'>" . htmlspecialchars($w['nama']) . "</option>";
                }
                ?>
              </select>
            </div>
            <div class="col-md-3 form-group-premium">
              <label class="form-label-premium">Kata Sandi (Password Awal)</label>
              <input type="password" name="password" class="input-premium" placeholder="Sandi masuk aplikasi mobile..." required>
            </div>
          </div>
          <div class="mt-3">
            <button type="submit" class="btn-submit-premium">
              <span class="material-symbols-outlined">save</span>
              Simpan Data Sales
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Card Daftar Sales -->
    <div class="card-premium">
      <div class="section-header-premium">
        <h6>
          <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">group</span>
          Daftar Sales Aktif
        </h6>
        <span class="badge bg-light text-dark font-weight-bold" style="font-size: 11px;"><?= mysqli_num_rows($salesData); ?> Sales Terdaftar</span>
      </div>
      
      <div class="table-responsive">
        <table class="premium-table">
          <thead>
            <tr>
              <th style="width: 70px; text-align: center;">No</th>
              <th style="width: 140px;">NIK / Username</th>
              <th>Nama Lengkap</th>
              <th style="width: 160px;">Wilayah</th>
              <th style="width: 200px;">No. Telepon</th>
              <th style="width: 120px; text-align: center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $no = 1; while ($row = mysqli_fetch_assoc($salesData)): ?>
            <tr>
              <td style="text-align: center; font-weight: 600; color: #64748b;"><?= $no++; ?></td>
              <td><span class="nik-badge"><?= htmlspecialchars($row['nik']); ?></span></td>
              <td>
                <div class="sales-identity-cell">
                  <div class="avatar-initials-table">
                    <?php 
                      $words = explode(' ', $row['nama']);
                      echo strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                    ?>
                  </div>
                  <span style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['nama']); ?></span>
                </div>
              </td>
              <td>
                <span class="badge bg-gradient-dark text-capitalize" style="font-size: 10px; font-weight: 600;">
                  <?= htmlspecialchars($row['nama_wilayah'] ?? 'Tanpa Wilayah'); ?>
                </span>
              </td>
              <td>
                <?php if (!empty($row['telp'])): ?>
                <a href="https://wa.me/<?= htmlspecialchars($row['telp']); ?>" target="_blank" class="wa-link">
                  <i class="fab fa-whatsapp" style="font-size: 16px;"></i> 
                  <?= htmlspecialchars(preg_replace('/^62/', '0', $row['telp'])); ?>
                </a>
                <?php else: ?>
                <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td style="text-align: center;">
                <button type="button" class="btn-act btn-act-edit editBtn" 
                  data-id="<?= $row['id']; ?>" 
                  data-nama="<?= htmlspecialchars($row['nama']); ?>" 
                  data-nik="<?= htmlspecialchars($row['nik']); ?>" 
                  data-telp="<?= htmlspecialchars($row['telp']); ?>" 
                  data-id-wilayah="<?= $row['id_wilayah']; ?>"
                  data-bs-toggle="modal" data-bs-target="#editModal" title="Ubah Data & Sandi">
                  <span class="material-symbols-outlined">edit</span>
                </button>
                <a href="?delete_id=<?= $row['id']; ?>" class="btn-act btn-act-delete" onclick="return confirm('Yakin ingin menghapus data sales ini?')" title="Hapus Sales">
                  <span class="material-symbols-outlined">delete</span>
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
            <?php if (mysqli_num_rows($salesData) == 0): ?>
              <tr>
                <td colspan="6" class="text-center text-muted" style="padding: 40px;">Belum ada data sales terdaftar.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content modal-content-premium">
          <div class="modal-header modal-header-premium">
            <h5 class="modal-title modal-title-premium">
              <span class="material-symbols-outlined">manage_accounts</span>
              Ubah Data &amp; Sandi Sales
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body modal-body-premium row">
            <input type="hidden" name="update_id" id="edit_id">
            
            <div class="col-md-6 form-group-premium">
              <label class="form-label-premium">Nama Lengkap</label>
              <input type="text" name="edit_nama" id="edit_nama" class="input-premium" required>
            </div>
            
            <div class="col-md-3 form-group-premium">
              <label class="form-label-premium">NIK / Username</label>
              <input type="text" name="edit_nik" id="edit_nik" class="input-premium" required>
            </div>
            
            <div class="col-md-3 form-group-premium">
              <label class="form-label-premium">No. Telepon</label>
              <input type="text" name="edit_telp" id="edit_telp" class="input-premium" required>
            </div>

            <div class="col-md-6 form-group-premium mt-2">
              <label class="form-label-premium">Wilayah Operasional</label>
              <select name="edit_id_wilayah" id="edit_id_wilayah" class="input-premium" required>
                <option value="">-- Pilih Wilayah --</option>
                <?php 
                $wQuery2 = mysqli_query($conn, "SELECT * FROM wilayah WHERE deleted_at IS NULL ORDER BY nama ASC");
                while ($w2 = mysqli_fetch_assoc($wQuery2)) {
                    echo "<option value='{$w2['id']}'>" . htmlspecialchars($w2['nama']) . "</option>";
                }
                ?>
              </select>
            </div>
            
            <div class="col-md-6 form-group-premium mt-2">
              <label class="form-label-premium">Kata Sandi Baru (Ubah Sandi)</label>
              <input type="password" name="edit_password" class="input-premium" placeholder="Masukkan password baru jika ingin mengubah, atau kosongkan saja...">
              <small class="text-muted" style="font-size: 11px; margin-top: 4px; display: block;">
                *Biarkan kosong jika tidak ada perubahan kata sandi untuk akun sales ini.
              </small>
            </div>
          </div>
          <div class="modal-footer modal-footer-premium">
            <button type="submit" class="btn-submit-premium">
              <span class="material-symbols-outlined">save</span>
              Simpan Perubahan
            </button>
            <button type="button" class="btn bg-gradient-secondary font-weight-bold" data-bs-dismiss="modal" style="border-radius: 10px; padding: 10px 20px; font-size: 13px;">Batal</button>
          </div>
        </form>
      </div>
    </div>

    <?php include "floating-menu.php"; ?>
    <?php include "footer.php"; ?>
  </div>
</main>

<?php include "js-include.php"; ?>
<script>
  document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('edit_id').value = btn.dataset.id;
      document.getElementById('edit_nama').value = btn.dataset.nama;
      document.getElementById('edit_nik').value = btn.dataset.nik;
      document.getElementById('edit_telp').value = btn.dataset.telp;
      document.getElementById('edit_id_wilayah').value = btn.dataset.idWilayah || "";
    });
  });
</script>
</body>
</html>
