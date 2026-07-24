<?php
$page_title = 'Penugasan Sales Cepat';
require_once 'includes/db.php';
require_once 'includes/header.php';

if ($_SESSION['role'] !== 'superadmin') {
    die("Akses ditolak. Halaman ini hanya untuk Superadmin.");
}

$sales_list = [];
$result_sales = $conn->query("SELECT id, nama_lengkap FROM sales WHERE role = 'sales' AND deleted_at IS NULL ORDER BY nama_lengkap ASC");
if($result_sales) {
    while($row = $result_sales->fetch_assoc()){
        $sales_list[] = $row;
    }
}

$customers = [];
$sql = "
    SELECT 
        c.id, 
        c.nama_toko,
        c.kategori,
        ca.alamat,
        ca.kota,
        cp.nama_pic,
        cp.tlp_pic,
        s.nama_lengkap as sales_penanggung_jawab
    FROM customers c
    LEFT JOIN sales s ON c.sales_id = s.id
    LEFT JOIN (SELECT customer_id, MIN(alamat) as alamat, MIN(kota) as kota FROM customer_addresses WHERE deleted_at IS NULL GROUP BY customer_id) ca ON c.id = ca.customer_id
    LEFT JOIN (SELECT customer_id, MIN(nama_pic) as nama_pic, MIN(tlp_pic) as tlp_pic FROM customer_pics WHERE deleted_at IS NULL GROUP BY customer_id) cp ON c.id = cp.customer_id
    WHERE (c.deal IS NULL OR c.deal != 'Y') AND c.deleted_at IS NULL
    ORDER BY c.nama_toko ASC
";
$result_cust = $conn->query($sql);
if($result_cust) {
    while($row = $result_cust->fetch_assoc()){
        $customers[] = $row;
    }
}
?>

<style>
.assign-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.assign-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.assign-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.assign-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.action-bar-box {
    background: linear-gradient(135deg, #F8FAFC 0%, #EFF6FF 100%);
    border: 1.5px solid #DBEAFE;
    border-radius: 16px;
    padding: 18px 24px;
    margin-bottom: 20px;
}

.sales-badge-assigned {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 12px;
}

.sales-badge-unassigned {
    background: #FEF2F2;
    color: #E11D48;
    border: 1px solid #FECDD3;
    padding: 4px 10px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
    font-style: italic;
}
</style>

<!-- Hero Banner -->
<div class="assign-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative" style="z-index:2;">
        <div style="flex: 1; min-width: 280px;">
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Penugasan Sales Cepat</span>
            </div>
            <h1 class="assign-hero-title">Penugasan Sales Cepat 👥</h1>
            <p class="assign-hero-subtitle">Tugaskan atau alihkan tanggung jawab customer ke tim sales secara massal dengan cepat.</p>
        </div>
        <div class="flex-shrink-0">
            <div class="rounded-4 p-3 px-4 border text-center shadow-sm" style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-color: rgba(255, 255, 255, 0.25) !important; min-width: 150px;">
                <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color: #DBEAFE; font-weight:700;">Customer Tersedia</div>
                <div style="font-size:26px; font-weight:800; color: #FFFFFF; font-family:'Plus Jakarta Sans', sans-serif; text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?php echo number_format(count($customers)); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Main Assignment Card -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-people-fill"></i> Kelola Penugasan Customer</h5>
    </div>
    <div class="card-body">
        
        <!-- Action Bar -->
        <div class="action-bar-box">
            <div class="row g-3 align-items-center">
                <div class="col-md-auto">
                    <span class="fw-bold text-dark" style="font-size:13.5px;"><i class="bi bi-check2-all text-primary me-1"></i> Aksi Massal:</span>
                </div>
                <div class="col-md-4 col-sm-6">
                    <select id="sales-select-action" class="form-select">
                        <option value="">-- Pilih Sales Tujuan --</option>
                        <?php foreach($sales_list as $sales): ?>
                            <option value="<?php echo $sales['id']; ?>" data-sales-name="<?php echo htmlspecialchars($sales['nama_lengkap']); ?>"><?php echo htmlspecialchars($sales['nama_lengkap']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-auto col-sm-6 d-flex gap-2">
                    <button id="btn-apply-assignment" class="btn btn-primary">
                        <i class="bi bi-person-check-fill"></i> Terapkan Penugasan
                    </button>
                    <button id="btn-unassign-selected" class="btn btn-secondary text-danger">
                        <i class="bi bi-person-x-fill"></i> Lepas Tugas
                    </button>
                </div>
            </div>
        </div>

        <!-- Search Input -->
        <div class="mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Cari customer, kategori, kota, PIC, atau nama sales...">
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive" style="min-height: 550px; max-height: calc(100vh - 260px); overflow-y: auto;">
            <table class="table table-hover align-middle" id="assignmentTable">
                <thead class="table-dark-header">
                    <tr>
                        <th style="width: 4%; text-align:center;"><input class="form-check-input" type="checkbox" id="selectAll"></th>
                        <th style="width: 22%;">Nama Customer</th>
                        <th style="width: 15%;">Nama PIC</th>
                        <th style="width: 13%;">Telepon PIC</th>
                        <th style="width: 10%;">Kategori</th>
                        <th style="width: 18%;">Alamat</th>
                        <th style="width: 10%;">Kota</th>
                        <th style="width: 18%;">Sales Penanggung Jawab</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($customers as $cust): ?>
                    <tr data-customer-id="<?php echo $cust['id']; ?>">
                        <td class="text-center"><input class="form-check-input customer-checkbox" type="checkbox"></td>
                        <td>
                            <div class="fw-bold text-dark" style="font-family:'Plus Jakarta Sans', sans-serif; font-size:13.5px;">
                                <i class="bi bi-shop text-primary me-1"></i><?php echo htmlspecialchars($cust['nama_toko']); ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $pic_name = trim($cust['nama_pic'] ?? '');
                            $show_pic = ($pic_name !== '' && strtolower($pic_name) !== 'unknown' && strtolower($pic_name) !== strtolower(trim($cust['nama_toko'])));
                            if ($show_pic): 
                            ?>
                                <span class="small fw-semibold text-dark"><i class="bi bi-person-fill text-muted me-1"></i><?php echo htmlspecialchars($pic_name); ?></span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $phone_number = trim($cust['tlp_pic'] ?? '');
                            if (!empty($phone_number) && $phone_number !== '-'):
                                $cleaned_tel = preg_replace('/[^0-9]/', '', $phone_number);
                                $wa_number = (substr($cleaned_tel, 0, 1) === '0') ? '62' . substr($cleaned_tel, 1) : $cleaned_tel;
                            ?>
                                <a href="https://wa.me/<?php echo $wa_number; ?>" target="_blank" class="badge text-success border text-decoration-none shadow-2sm" style="background:#F0FDF4; color:#15803D !important; border-color:#86EFAC !important; border-radius:20px; padding:4px 10px; font-weight:700; font-size:11.5px; display:inline-flex; align-items:center; gap:3px;">
                                    <i class="bi bi-whatsapp"></i> <?php echo htmlspecialchars($phone_number); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $kat = strtoupper(trim($cust['kategori'] ?? 'UMUM'));
                            $kat_bg = '#F1F5F9'; $kat_fg = '#475569'; $kat_bd = '#CBD5E1';
                            if ($kat === 'INSTALLER') { $kat_bg = '#EEF2FF'; $kat_fg = '#4338CA'; $kat_bd = '#C7D2FE'; }
                            elseif ($kat === 'DEALER') { $kat_bg = '#ECFEFF'; $kat_fg = '#0891B2'; $kat_bd = '#A5F3FC'; }
                            elseif ($kat === 'USER') { $kat_bg = '#FEF3C7'; $kat_fg = '#D97706'; $kat_bd = '#FDE68A'; }
                            ?>
                            <span class="badge fw-bold" style="background:<?php echo $kat_bg; ?>; color:<?php echo $kat_fg; ?>; border:1px solid <?php echo $kat_bd; ?>; border-radius:20px; padding:4px 10px; font-size:11px;"><?php echo htmlspecialchars($kat); ?></span>
                        </td>
                        <td class="text-wrap small text-muted" style="max-width:220px; line-height:1.4;">
                            <?php echo htmlspecialchars($cust['alamat'] ?? '-'); ?>
                        </td>
                        <td>
                            <?php 
                            $city = trim($cust['kota'] ?? '');
                            if (!empty($city) && $city !== '-'):
                            ?>
                                <span class="badge fw-bold" style="background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE; border-radius:20px; padding:4px 10px; font-size:11px;">📍 <?php echo htmlspecialchars($city); ?></span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="sales-name-cell">
                            <?php if ($cust['sales_penanggung_jawab']): ?>
                                <div class="d-flex align-items-center gap-1.5" style="white-space:nowrap;">
                                    <div class="sales-avatar-badge-small flex-shrink-0">
                                        <?php echo strtoupper(substr($cust['sales_penanggung_jawab'], 0, 1)); ?>
                                    </div>
                                    <span class="fw-bold text-dark" style="font-size:12.5px;"><?php echo htmlspecialchars($cust['sales_penanggung_jawab']); ?></span>
                                </div>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark fw-bold" style="border-radius:20px; padding:4px 10px; font-size:11px;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Belum ada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="notification-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999; width: 350px;"></div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    
    function showNotification(message, isSuccess = true) {
        const a_class = isSuccess ? 'alert-success' : 'alert-danger';
        const notif = $(`<div class="alert ${a_class} alert-dismissible fade show shadow-lg rounded-3" role="alert">
                             ${message}
                             <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                           </div>`);
        $('#notification-container').append(notif);
        setTimeout(() => notif.alert('close'), 3000);
    }

    $('#searchInput').on('keyup', function() {
        $('#selectAll').prop('checked', false);
        const filter = $(this).val().toLowerCase();
        $('#assignmentTable tbody tr').each(function() {
            const rowText = $(this).text().toLowerCase();
            $(this).toggle(rowText.includes(filter));
        });
    });

    $('#selectAll').on('change', function() {
        $('#assignmentTable tbody tr:visible .customer-checkbox').prop('checked', this.checked);
    });

    function performAction(action, salesId = null) {
        let customerIds = [];
        $('#assignmentTable tbody .customer-checkbox:checked').each(function() {
            customerIds.push($(this).closest('tr').data('customer-id'));
        });

        if (customerIds.length === 0) {
            alert('Silakan pilih minimal satu customer.');
            return;
        }

        $.ajax({
            url: 'assignment_process.php',
            type: 'POST',
            data: { 
                action: action, 
                customer_ids: customerIds, 
                sales_id: salesId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification(response.message || 'Aksi berhasil diterapkan.');
                    
                    let salesName = $('#sales-select-action').find('option:selected').text();
                    customerIds.forEach(function(id) {
                        const newName = (action === 'assign_customers') 
                            ? `<span class="sales-badge-assigned"><i class="bi bi-person-fill me-1"></i> ${salesName}</span>` 
                            : '<span class="sales-badge-unassigned"><i class="bi bi-exclamation-circle me-1"></i> Belum ada</span>';
                        $('tr[data-customer-id="' + id + '"]').find('.sales-name-cell').html(newName);
                    });
                    
                    $('.customer-checkbox, #selectAll').prop('checked', false);
                } else {
                    showNotification(response.message || 'Terjadi kesalahan.', false);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                showNotification('Error: Tidak dapat terhubung ke server.', false);
            }
        });
    }

    $('#btn-apply-assignment').on('click', function() {
        let salesId = $('#sales-select-action').val();
        if (!salesId) {
            alert('Silakan pilih sales yang akan ditugaskan.');
            return;
        }
        performAction('assign_customers', salesId);
    });
    
    $('#btn-unassign-selected').on('click', function() {
        if (confirm('Anda yakin ingin melepas tugas dari customer yang dipilih?')) {
            performAction('unassign_customers');
        }
    });

});
</script>

<?php require_once 'includes/footer.php'; ?>