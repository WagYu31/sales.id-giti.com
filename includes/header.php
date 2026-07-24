<?php
require_once 'auth.php'; // Cek apakah user sudah login
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> — Loewix Sales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
<style>
    /* ============ GLOBAL OVERRIDES ============ */
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #F8FAFC;
        color: #1E293B;
        -webkit-font-smoothing: antialiased;
    }

    table tr td { font-size: 0.85em; }
    .input-group-text { cursor: pointer; }

    /* ============ PREMIUM NAVBAR ============ */
    .navbar-loewix {
        background: #FFFFFF;
        border-bottom: 1px solid #E2E8F0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        padding: 0 24px;
        height: 64px;
    }

    .navbar-loewix .navbar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 800;
        font-size: 16px;
        color: #0F172A;
        letter-spacing: -0.3px;
        text-decoration: none;
        padding: 0;
    }

    .navbar-loewix .navbar-brand img {
        height: 32px;
        width: auto;
    }

    .navbar-loewix .navbar-brand .brand-divider {
        width: 1px;
        height: 24px;
        background: #E2E8F0;
        margin: 0 4px;
    }

    .navbar-loewix .navbar-brand .brand-sub {
        font-weight: 600;
        font-size: 13px;
        color: #64748B;
    }

    .navbar-loewix .navbar-nav .nav-link {
        color: #64748B;
        font-weight: 500;
        font-size: 13.5px;
        padding: 20px 14px;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .navbar-loewix .navbar-nav .nav-link:hover {
        color: #1E40AF;
    }

    .navbar-loewix .navbar-nav .nav-link.active {
        color: #1E40AF;
        font-weight: 700;
        border-bottom-color: #1E40AF;
    }

    .navbar-loewix .dropdown-menu {
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        padding: 8px;
        margin-top: 4px;
    }

    .navbar-loewix .dropdown-item {
        font-size: 13.5px;
        font-weight: 500;
        padding: 8px 14px;
        border-radius: 8px;
        color: #475569;
        transition: all 0.15s ease;
    }

    .navbar-loewix .dropdown-item:hover {
        background: #F1F5F9;
        color: #1E293B;
    }

    .navbar-loewix .dropdown-item i {
        margin-right: 8px;
        font-size: 14px;
    }

    .navbar-loewix .dropdown-item.text-danger:hover {
        background: #FEF2F2;
        color: #DC2626;
    }

    /* User avatar badge */
    .user-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px 6px 6px;
        border-radius: 100px;
        background: #F1F5F9;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        transition: all 0.2s ease;
        text-decoration: none;
        border: none;
    }

    .user-badge:hover {
        background: #E2E8F0;
    }

    .user-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1E40AF, #3B82F6);
        color: #FFFFFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
    }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-loewix sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="customer_management.php">
        <img src="assets/images/loewix_sales_logo.png" alt="Loewix Sales">
    </a>
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
                    <ul class="dropdown-menu" aria-labelledby="navbarSalesTools">
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
          <a class="nav-link dropdown-toggle user-badge" href="#" role="button" data-bs-toggle="dropdown">
            <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['nama_lengkap'], 0, 2)); ?></span>
            <?php echo htmlspecialchars($_SESSION['nama_lengkap']); ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="bi bi-key"></i> Ganti Password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
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
        const href = link.getAttribute('href');
        if (!href) return;
        const linkPage = href.split("/").pop();
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