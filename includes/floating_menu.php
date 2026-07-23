<?php
// File: includes/floating_menu.php

// Tampilkan menu ini jika role adalah superadmin atau sales
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['superadmin', 'sales'])):
?>

<style>
    /* Kontainer utama untuk FAB */
    .fab-container {
        position: fixed;
        left: 40px;
        top: 90%;
        transform: translateY(-50%);
        z-index: 1050;
    }

    /* Tombol utama yang bulat */
    .fab-button {
        width: 60px;
        height: 60px;
        background-color: #0d6efd; /* Warna biru primer Bootstrap */
        border-radius: 50%;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .fab-button:hover {
        background-color: #0b5ed7;
        transform: scale(1.05);
    }
    .fab-button .fab-icon-open {
        display: block;
        transition: transform 0.3s ease;
    }
    .fab-button .fab-icon-close {
        display: none;
        transform: rotate(45deg);
        transition: transform 0.3s ease;
    }

    /* Kontainer untuk menu dropdown */
    .fab-menu {
        list-style: none;
        padding: 0;
        margin: 0 0 15px 5px; /* Margin bawah dari tombol utama */
        position: absolute;
        bottom: 100%; /* Posisi menu di atas tombol */
        left: 0;
        visibility: hidden;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.3s ease;
    }

    /* Tampilkan menu saat container aktif */
    .fab-container.active .fab-menu {
        visibility: visible;
        opacity: 1;
        transform: translateY(0);
    }
    .fab-container.active .fab-button .fab-icon-open {
        transform: rotate(-45deg);
        display: none;
    }
    .fab-container.active .fab-button .fab-icon-close {
        transform: rotate(0);
        display: block;
    }

    /* Setiap item menu */
    .fab-menu li {
        margin-bottom: 10px;
        display: flex;
        align-items: center;
    }

    /* Tautan di dalam menu */
    .fab-menu a {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: #343a40;
        background-color: white;
        padding: 8px 16px;
        border-radius: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.15);
        white-space: nowrap;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    .fab-menu a:hover {
        background-color: #f8f9fa;
        color: #0d6efd;
    }
    .fab-menu a i {
        font-size: 1.2rem;
        margin-right: 10px;
    }
</style>

<div class="fab-container" id="fab-container">
    <ul class="fab-menu">
        <li>
            <a href="index.php">
                <i class="bi bi-people-fill text-danger"></i>
                Daftar Customer
            </a>
        </li>
        
        <li>
            <a href="customer_add.php">
                <i class="bi bi-person-plus-fill text-secondary"></i>
                Tambah Customer
            </a>
        </li>
        
        <li>
            <a href="<?php echo (isset($_SESSION['role']) && $_SESSION['role'] == 'superadmin') ? 'customer_io.php' : 'customer_io_sales.php'; ?>">
                <i class="bi bi-cloud-upload-fill text-primary"></i>
                Unggah Data
            </a>
        </li>

        <li>
            <a href="customer_export.php">
                <i class="bi bi-cloud-download-fill text-success"></i>
                Unduh Data
            </a>
        </li>
        
        <li>
            <a href="customer_maintenance.php">
                <i class="bi bi-clipboard-check-fill text-warning"></i>
                Kualitas Data
            </a>
        </li>
        <li>
            <a href="invoice_followup_report.php">
                <i class="bi bi-receipt text-info"></i>
                Laporan Invoice
            </a>
        </li>
    </ul>

    <div class="fab-button" id="fab-button">
        <i class="bi bi-wrench-adjustable-circle fab-icon-open"></i>
        <i class="bi bi-plus-lg fab-icon-close"></i>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fabContainer = document.getElementById('fab-container');
    const fabButton = document.getElementById('fab-button');

    if (fabButton) {
        fabButton.addEventListener('click', function() {
            fabContainer.classList.toggle('active');
        });

        // Menutup menu jika klik di luar container
        document.addEventListener('click', function(event) {
            if (fabContainer && !fabContainer.contains(event.target)) {
                fabContainer.classList.remove('active');
            }
        });
    }
});
</script>

<?php
endif;
?>