<?php
// followup_view.php
$page_title = "Detail Follow Up";
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fungsi helper untuk ikon file (tidak berubah)
function get_file_icon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return 'bi-file-earmark-pdf-fill text-danger';
        case 'doc': case 'docx': return 'bi-file-earmark-word-fill text-primary';
        case 'xls': case 'xlsx': return 'bi-file-earmark-excel-fill text-success';
        case 'jpg': case 'jpeg': case 'png': case 'gif': return 'bi-file-earmark-image-fill text-info';
        case 'mp4': case 'webm': return 'bi-file-earmark-play-fill text-warning';
        default: return 'bi-file-earmark-fill';
    }
}

// Cek ID Customer
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    header("Location: index.php");
    exit();
}
$customer_id = (int)$_GET['customer_id'];

// 1. Ambil Data utama customer
$stmt_cust = $conn->prepare("SELECT c.nama_toko, c.tgl_input, c.foto_toko, c.sales_id, s.nama_lengkap as nama_sales FROM customers c LEFT JOIN sales s ON c.sales_id = s.id WHERE c.id = ? AND c.deleted_at IS NULL");
$stmt_cust->bind_param("i", $customer_id);
$stmt_cust->execute();
$customer_result = $stmt_cust->get_result();
if ($customer_result->num_rows == 0) {
    die("Customer tidak ditemukan atau telah dihapus.");
}
$customer = $customer_result->fetch_assoc();
$page_title = "Detail: " . htmlspecialchars($customer['nama_toko']);

// PERUBAHAN: Mengambil data PIC dan teleponnya dari tabel `customer_pics`
$customer_pics = $conn->query("SELECT nama_pic, tlp_pic FROM customer_pics WHERE customer_id = $customer_id AND deleted_at IS NULL ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// PERUBAHAN: Pengambilan data dari `customer_phones` dihapus.

// 4. Data Addresses (tidak berubah)
$customer_addresses = $conn->query("SELECT alamat, kota, provinsi, link_google_map FROM customer_addresses WHERE customer_id = $customer_id AND deleted_at IS NULL ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Ambil data follow-up (logika ini tidak berubah)
$stmt_fu = $conn->prepare("SELECT fu.*, s.nama_lengkap as nama_sales_fu FROM follow_ups fu JOIN sales s ON fu.sales_id = s.id WHERE fu.customer_id = ? AND fu.deleted_at IS NULL ORDER BY fu.tgl_follow_up DESC");
$stmt_fu->bind_param("i", $customer_id);
$stmt_fu->execute();
$followups_result = $stmt_fu->get_result();
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-person-badge"></i> Detail Customer</h5>
        <a href="index.php" class="btn btn-sm btn-secondary">Kembali ke Dashboard</a>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 text-center">
                <img src="assets/uploads/<?php echo !empty($customer['foto_toko']) ? htmlspecialchars($customer['foto_toko']) : 'default_toko.png'; ?>" 
                     class="img-fluid rounded border" 
                     alt="Foto Toko"
                     style="max-height: 150px; object-fit: cover;">
            </div>
            <div class="col-md-9">
                <h3 class="text-capitalize"><?php echo htmlspecialchars($customer['nama_toko']); ?></h3>
                <p class="text-muted">
                    Sales Penanggung Jawab: <strong><?php echo htmlspecialchars($customer['nama_sales'] ?? 'N/A'); ?></strong> | 
                    Tanggal Input: <strong><?php echo date('d M Y', strtotime($customer['tgl_input'])); ?></strong>
                </p>
                <hr>
                <div class="row">
                    <div class="col-lg-6">
                        <h6><i class="bi bi-people-fill text-primary"></i> PIC & Kontak:</h6>
                        <ul class="list-unstyled">
                            <?php if(!empty($customer_pics)): foreach ($customer_pics as $pic): ?>
                                <li>
                                    <?php echo htmlspecialchars($pic['nama_pic']); ?>
                                    <?php if(!empty($pic['tlp_pic'])): 
                                        $cleaned_tel = preg_replace('/[^0-9]/', '', $pic['tlp_pic']);
                                        $wa_number = (substr($cleaned_tel, 0, 1) === '0') ? '62' . substr($cleaned_tel, 1) : $cleaned_tel;
                                    ?>
                                    (<a href="https://wa.me/<?php echo $wa_number; ?>" target="_blank"><?php echo htmlspecialchars($pic['tlp_pic']); ?></a>)
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; else: echo "<li>-</li>"; endif; ?>
                        </ul>
                    </div>
                    <div class="col-lg-6 text-capitalize">
                        <h6><i class="bi bi-geo-alt-fill text-danger"></i> Alamat:</h6>
                        <?php if(!empty($customer_addresses)): foreach ($customer_addresses as $address): ?>
                            <address class="mb-2">
                                <?php echo nl2br(htmlspecialchars($address['alamat'] ?? '')); ?><br>
                                <strong class="text-capitalize"><?php echo htmlspecialchars($address['kota'] ?? ''); ?> - <?php echo htmlspecialchars($address['provinsi'] ?? ''); ?></strong><br>
                                <?php if (!empty($address['link_google_map'])): ?>
                                    <a href="<?php echo htmlspecialchars($address['link_google_map'] ?? ''); ?>" target="_blank" class="btn btn-sm btn-outline-danger mt-1">Lihat Peta</a>
                                <?php endif; ?>
                            </address>
                        <?php endforeach; else: echo "-"; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-card-list"></i> Riwayat Follow Up</h5>
        <?php if ($_SESSION['role'] == 'superadmin' || $_SESSION['user_id'] == $customer['sales_id']): ?>
            <a href="followup_add.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-success"><i class="bi bi-plus-circle"></i> Tambah Follow Up</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Sales</th>
                        <th>Respon</th>
                        <th>Keterangan/Media</th>
                        <th>Invoice</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($followups_result->num_rows > 0): ?>
                        <?php while($fu = $followups_result->fetch_assoc()): ?>
                            <tr id="followup-row-<?php echo $fu['id']; ?>">
                                <td class="text-nowrap"><?php echo date('d M Y, H:i', strtotime($fu['tgl_follow_up'])); ?></td>
                                <td class="text-nowrap"><?php echo htmlspecialchars($fu['nama_sales_fu']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($fu['respon'])); ?></td>
                                <td>
                                    <?php echo nl2br(htmlspecialchars($fu['keterangan'])); ?>
                                    <?php for ($i = 1; $i <= 3; $i++): 
                                        $media_file = $fu['media'.$i];
                                        if ($media_file): 
                                            $file_path = "assets/uploads/" . htmlspecialchars($media_file);
                                            $icon_class = get_file_icon($media_file);
                                    ?>
                                        <a href="#" class="d-block mb-1 text-decoration-none" data-bs-toggle="modal" data-bs-target="#mediaModal" data-file-url="<?php echo $file_path; ?>" data-file-name="<?php echo htmlspecialchars($media_file); ?>">
                                            <i class="bi <?php echo $icon_class; ?>"></i> <?php echo htmlspecialchars(substr($media_file, 14)); ?>
                                        </a>
                                    <?php 
                                        endif;
                                    endfor; ?>
                                </td>
                                <td>
                                    <?php echo nl2br(htmlspecialchars($fu['no_inv'])); ?>
                                </td>
                                <td>
                                    <?php if ($_SESSION['role'] == 'superadmin' || (isset($fu['sales_id']) && $_SESSION['user_id'] == $fu['sales_id'])): ?>
                                        <button class="btn btn-sm btn-outline-danger delete-followup-btn" data-followup-id="<?php echo $fu['id']; ?>" title="Hapus Follow Up">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center p-4">Belum ada riwayat follow-up untuk customer ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="mediaModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mediaModalLabel">Tampilan Media</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="mediaModalBody"></div>
    </div>
  </div>
</div>

<?php 
$stmt_cust->close();
$stmt_fu->close();
require_once 'includes/footer.php'; 
?>

<script>
// Javascript di file ini tidak berubah
document.addEventListener('DOMContentLoaded', function () {
    const mediaModal = document.getElementById('mediaModal');
    const modalTitle = document.getElementById('mediaModalLabel');
    const modalBody = document.getElementById('mediaModalBody');
    const tableBody = document.querySelector('tbody');

    mediaModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const fileUrl = button.getAttribute('data-file-url');
        const fileName = button.getAttribute('data-file-name');
        const fileExtension = fileName.split('.').pop().toLowerCase();
        modalTitle.textContent = fileName;
        modalBody.innerHTML = '';
        if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) {
            modalBody.innerHTML = `<img src="${fileUrl}" class="img-fluid w-100" alt="${fileName}">`;
        } else if (fileExtension === 'pdf') {
            modalBody.innerHTML = `<iframe src="${fileUrl}" style="width:100%; height:75vh;" frameborder="0"></iframe>`;
        } else if (['mp4', 'webm'].includes(fileExtension)) {
            modalBody.innerHTML = `<video controls autoplay class="w-100"><source src="${fileUrl}" type="video/${fileExtension}"></video>`;
        } else {
            modalBody.innerHTML = `<div class="text-center p-5"><p>Pratinjau tidak tersedia.</p><a href="${fileUrl}" class="btn btn-primary" download><i class="bi bi-download"></i> Download ${fileName}</a></div>`;
        }
    });
    mediaModal.addEventListener('hidden.bs.modal', () => { modalBody.innerHTML = ''; });

    tableBody.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.delete-followup-btn');
        if (deleteButton) {
            if (confirm('Anda yakin ingin menghapus catatan follow-up ini?')) {
                const followupId = deleteButton.dataset.followupId;
                fetch('followup_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 'followup_id': followupId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById('followup-row-' + followupId);
                        row.style.transition = 'opacity 0.5s ease-out';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 500);
                    } else {
                        alert('Gagal menghapus: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Terjadi kesalahan jaringan.');
                    console.error('Error:', error);
                });
            }
        }
    });
});
</script>