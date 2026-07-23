<?php
$page_title = 'Customer Management';
require_once 'includes/db.php';
require_once 'includes/header.php';

// Pastikan hanya superadmin yang bisa akses
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>

<style>
    /* Menambahkan efek hover yang halus pada kartu menu */
    .card-link {
        text-decoration: none;
        color: inherit;
    }
    .card.card-hover {
        transition: all 0.2s ease-in-out;
    }
    .card.card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15)!important;
    }
    .card-icon {
        width: 60px;
        height: 60px;
        font-size: 1.75rem;
    }
</style>

<div class="p-5 mb-4 bg-light rounded-3 shadow-sm">
    <div class="container-fluid py-3">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0 me-4">
                <i class="bi bi-people-fill text-primary" style="font-size: 3.5rem;"></i>
            </div>
            <div class="flex-grow-1">
                <h1 class="display-5 fw-bold">Pusat Manajemen Customer</h1>
                <p class="fs-5 text-muted">Gunakan menu di bawah untuk mengelola data customer Anda secara efisien.</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-4">
        <a href="index.php" class="card-link" target="_blank">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-4">
                            <div class="bg-danger bg-opacity-10 text-danger rounded-3 d-flex align-items-center justify-content-center card-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold">Daftar Customer</h5>
                            <p class="card-text text-muted mb-0">Lihat detail seluruh customer Anda.</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    
    <div class="col-lg-4">
        <a href="kandidat_report_view.php" class="card-link" target="_blank">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-4">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center card-icon">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold">Potensial Customer</h5>
                            <p class="card-text text-muted mb-0">Lihat daftar customer yang memiliki potensial.</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-lg-4">
        <a href="customer_add.php" class="card-link" target="_blank">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-4">
                            <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 d-flex align-items-center justify-content-center card-icon">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold">Tambah Customer</h5>
                            <p class="card-text text-muted mb-0">Input dengan lengkap data customer.</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-lg-4">
        <a href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin') ? 'customer_io.php' : 'customer_io_sales.php'; ?>" class="card-link" target="_blank">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-4">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center card-icon">
                                <i class="bi bi-cloud-upload-fill"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold">Unggah Data Customer</h5>
                            <p class="card-text text-muted mb-0">Impor data customer dalam jumlah besar dari file Excel (XLSX).</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg-4">
        <a href="customer_export.php" class="card-link" target="_blank">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-4">
                            <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center card-icon">
                                <i class="bi bi-cloud-download-fill"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold">Unduh Data Customer</h5>
                            <p class="card-text text-muted mb-0">Ekspor semua data customer yang ada ke dalam format file Excel.</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg-4">
        <a href="customer_maintenance.php" class="card-link" target="_blank">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-4">
                            <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center card-icon">
                                <i class="bi bi-clipboard-check-fill"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold">Kualitas Data Customer</h5>
                            <p class="card-text text-muted mb-0">Periksa dan perbaiki data customer yang duplikat atau salah format.</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg-4">
        <a href="invoice_followup_report.php" class="card-link" target="_blank">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-4">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center card-icon">
                                <i class="bi bi-receipt"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold">Laporan Invoice FU</h5>
                            <p class="card-text text-muted mb-0">Lihat riwayat follow up yang menghasilkan invoice dan perlu tindak lanjut.</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-lg-4">
        <a href="sales_assistant.html" target="_blank" class="card-link" target="_blank">
            <div class="card h-100 shadow-sm card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-4">
                            <div class="bg-dark bg-opacity-10 text-dark rounded-3 d-flex align-items-center justify-content-center card-icon">
                                <i class="bi bi-robot"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title fw-bold">Asisten Loewix (Uji Coba)</h5>
                            <p class="card-text text-muted mb-0">Silakan ketik pertanyaan dari customer Anda di bawah. AI ini akan berikan 3 opsi jawaban untuk membantu Anda menjawab pertanyaan customer.</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>