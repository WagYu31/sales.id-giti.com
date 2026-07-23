<?php
require_once 'auth.php'; // Cek apakah user sudah login
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - Laporan Prospek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
<style>
    table tr td {
        font-size: 0.85em;
    }
    .input-group-text {
        cursor: pointer;
    }
    .navbar {
        border-bottom: 1px solid #dee2e6;
    }
    .navbar-brand {
        font-weight: 600;
        color: #212529;
    }
    .navbar-nav .nav-link {
        color: #6c757d;
        font-weight: 500;
        padding-left: 0.8rem;
        padding-right: 0.8rem;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease-in-out;
    }
    .navbar-nav .nav-link:hover {
        color: #0d6efd;
    }
    .navbar-nav .nav-link.active {
        color: #0d6efd;
        font-weight: 700;
        border-bottom: 2px solid #0d6efd;
    }
    .dropdown-menu {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,.1);
    }
    .dropdown-menu { border: none; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    a.nav-link{
        font-size: 14px;
    }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><i class="bi bi-graph-up-arrow"></i> ProspekApp</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'adminsales'): ?>
            <li class="nav-item">
                <a class="nav-link" href="promosi_management.php">Promosi</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="sales_ads.php">Laporan Ads</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="ads_report.php">Report Saldo & Ads</a>
            </li>
        <?php else: ?>
            <!--<li class="nav-item">-->
            <!--    <a class="nav-link" href="index.php">Dashboard Customer</a>-->
            <!--</li>-->
            <li class="nav-item">
                <a class="nav-link" href="customer_management.php">Customer Management</a>
            </li>
            <?php if ($_SESSION['role'] == 'superadmin'): ?>
            <li class="nav-item">
              <a class="nav-link" href="followup_report.php">Follow Up Report</a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
              <a class="nav-link" href="sales_management.php">Sales Performance</a>
            </li>
            <?php if ($_SESSION['role'] == 'superadmin'): ?>
            <li class="nav-item">
              <a class="nav-link" href="sales_assignment.php">Sales Management</a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="sales_qa.php">Forum Q&A Sales</a>
            </li>
             <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarSalesTools" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Sales Tools
                    </a>
                    <ul class="dropdown-menu border-0 shadow-sm" aria-labelledby="navbarSalesTools">
                        <li><a class="dropdown-item" href="broadcast_schedule.php"><i class="bi bi-megaphone"></i> Broadcast</a></li>
                        <li><a class="dropdown-item" href="promosi_management.php"><i class="bi bi-tags"></i> Promosi</a></li>
                        <li><a class="dropdown-item" href="price_list.php"><i class="bi bi-ui-checks"></i> Price List</a></li>
                        <li><a class="dropdown-item" href="calculator_sales.php"><i class="bi bi-calculator"></i> Kalkulator Sales</a></li>
                        <li><a class="dropdown-item" href="online_tools.php"><i class="bi bi-123"></i> Kalkulator Online</a></li>
                        
                        <?php if (in_array($_SESSION['role'], ['superadmin', 'adminsales'])): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="sales_ads.php"><i class="bi bi-cart-check"></i> Laporan Ads Online</a></li>
                            <li><a class="dropdown-item" href="ads_report.php"><i class="bi bi-bar-chart-line"></i> Report Saldo & Ads</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="bi bi-key me-2"></i> Ganti Password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container-fluid px-5 mt-4">

<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePasswordModalLabel"><i class="bi bi-key-fill"></i> Ganti Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="changePasswordForm">
            <div id="passwordChangeAlert" class="alert d-none" role="alert"></div>
            <div class="mb-3">
                <label for="old_password" class="form-label">Password Lama</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="old_password" name="old_password" required>
                    <span class="input-group-text toggle-password"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">Password Baru</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                    <span class="input-group-text toggle-password"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <span class="input-group-text toggle-password"><i class="bi bi-eye-slash"></i></span>
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" form="changePasswordForm" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const currentPage = window.location.pathname.split("/").pop();
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');

    navLinks.forEach(link => {
        const linkPage = link.getAttribute('href').split("/").pop();
        if (!link.classList.contains('dropdown-toggle')) {
            link.classList.remove('active');
        }
        if (linkPage === currentPage) {
            link.classList.add('active');
        }
    });

    document.querySelectorAll('.toggle-password').forEach(item => {
        item.addEventListener('click', function (e) {
            const passwordInput = this.previousElementSibling;
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        });
    });

    const passwordForm = document.getElementById('changePasswordForm');
    if (passwordForm) {
        const alertBox = document.getElementById('passwordChangeAlert');
        const modalEl = document.getElementById('changePasswordModal');
        const modal = new bootstrap.Modal(modalEl);

        passwordForm.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            alertBox.className = 'alert d-none';

            if (formData.get('new_password') !== formData.get('confirm_password')) {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Konfirmasi password baru tidak cocok.';
                return;
            }

            fetch('change_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertBox.className = 'alert alert-success';
                    alertBox.textContent = data.message;
                    passwordForm.reset();
                    setTimeout(() => {
                        modal.hide();
                        alertBox.className = 'alert d-none';
                    }, 2000);
                } else {
                    alertBox.className = 'alert alert-danger';
                    alertBox.textContent = data.message;
                }
            })
            .catch(error => {
                alertBox.className = 'alert alert-danger';
                alertBox.textContent = 'Terjadi kesalahan jaringan.';
                console.error('Error:', error);
            });
        });
    }
});
</script>