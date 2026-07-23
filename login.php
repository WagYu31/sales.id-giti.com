<?php
// login.php
require_once 'includes/db.php';
$error = '';

// Jika pengguna sudah login, arahkan ke index.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Proses form jika metode adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Siapkan statement untuk mengambil data sales berdasarkan email
    $stmt = $conn->prepare("SELECT id, nama_lengkap, password, role FROM sales WHERE email = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Cek jika pengguna ditemukan
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verifikasi password yang diinput dengan hash di database
        if (password_verify($password, $user['password'])) {
            // Jika password cocok, buat session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
    
            // Arahkan pengguna berdasarkan peran (role)
            if ($_SESSION['role'] === 'adminsales') {
                header("Location: promosi_management.php");
            } else {
                header("Location: customer_management.php");
            }
            exit();
        }
    }
    
    // Jika email tidak ditemukan atau password tidak cocok, tampilkan error
    $error = "Email atau password salah!";
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Loewix Sales System</title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-page: #F0F4FA;
            --bg-card-left: #F5F8FC;
            --primary-blue: #2B78C5;
            --primary-blue-dark: #1A56C6;
            --primary-blue-light: #EBF3FC;
            --accent-cyan: #0284C7;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            margin: 0;
            background-image: 
                radial-gradient(at 0% 0%, rgba(43, 120, 197, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(2, 132, 199, 0.08) 0px, transparent 50%);
        }

        .login-card {
            width: 100%;
            max-width: 1120px;
            background: #FFFFFF;
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.09), 0 0 1px 1px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            display: flex;
            flex-direction: row;
        }

        /* LEFT HERO PANEL */
        .hero-panel {
            width: 52%;
            background-color: var(--bg-card-left);
            background-image: radial-gradient(#CBD5E1 1px, transparent 1px);
            background-size: 24px 24px;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid rgba(226, 232, 240, 0.8);
            position: relative;
        }

        .badge-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .pill-badge {
            background-color: #E0F2FE;
            color: #0369A1;
            font-size: 11px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 9999px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .pill-badge-sub {
            background-color: #F0F9FF;
            color: #0284C7;
            border: 1px solid #BAE6FD;
        }

        .dot-blue {
            width: 8px;
            height: 8px;
            background-color: #0284C7;
            border-radius: 50%;
            display: inline-block;
        }

        .hero-title {
            font-size: 2.35rem;
            font-weight: 800;
            line-height: 1.25;
            color: #0F172A;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .hero-title .highlight {
            color: var(--primary-blue);
            background: linear-gradient(135deg, #2B78C5 0%, #0284C7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 14px;
            line-height: 1.65;
            color: var(--text-muted);
            margin-bottom: 28px;
            max-width: 480px;
        }

        .feature-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 32px;
        }

        .chip {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* DASHBOARD PREVIEW WIDGET */
        .preview-widget {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.04);
            margin-bottom: 24px;
        }

        .widget-header {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #94A3B8;
            margin-bottom: 16px;
        }

        .dot-control {
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }

        .widget-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .stat-box {
            background: #F8FAFC;
            border: 1px solid #F1F5F9;
            border-radius: 14px;
            padding: 12px 14px;
        }

        .stat-label {
            font-size: 10px;
            font-weight: 700;
            color: #94A3B8;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .stat-val {
            font-size: 20px;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .badge-inc {
            font-size: 11px;
            font-weight: 700;
            color: #16A34A;
        }

        .badge-online {
            font-size: 11px;
            font-weight: 700;
            color: #0284C7;
        }

        .chart-mini {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 36px;
            padding-top: 8px;
        }

        .bar {
            flex: 1;
            background: #E2E8F0;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .bar.active {
            background: linear-gradient(180deg, #38BDF8 0%, #2B78C5 100%);
        }

        .hero-footer {
            font-size: 12px;
            color: #94A3B8;
            font-weight: 500;
        }

        /* RIGHT FORM PANEL */
        .form-panel {
            width: 48%;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-container {
            margin-bottom: 24px;
        }

        .logo-img {
            max-height: 60px;
            width: auto;
            object-fit: contain;
        }

        .welcome-text {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 4px;
        }

        .form-title {
            font-size: 1.65rem;
            font-weight: 800;
            color: #0F172A;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .form-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-bottom: 28px;
        }

        .input-group-custom {
            margin-bottom: 20px;
        }

        .input-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748B;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }

        .custom-input {
            width: 100%;
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 500;
            color: #0F172A;
            outline: none;
            transition: all 0.2s ease;
        }

        .custom-input:focus {
            background-color: #FFFFFF;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(43, 120, 197, 0.15);
        }

        .password-wrapper {
            position: relative;
        }

        .btn-eye {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-eye:hover {
            color: #2B78C5;
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 13.5px;
            color: #475569;
        }

        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-blue);
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #2B78C5 0%, #1A56C6 100%);
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            padding: 15px;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 20px -5px rgba(43, 120, 197, 0.4);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px -5px rgba(43, 120, 197, 0.5);
            background: linear-gradient(135deg, #3284d7 0%, #1A56C6 100%);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 24px 0;
            color: #CBD5E1;
            font-size: 12px;
            font-weight: 600;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #E2E8F0;
        }

        .divider span {
            padding: 0 12px;
            color: #94A3B8;
        }

        .btn-secondary-signup {
            width: 100%;
            background-color: #FFFFFF;
            border: 1px solid #E2E8F0;
            color: #334155;
            font-size: 14px;
            font-weight: 700;
            padding: 14px;
            border-radius: 14px;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-secondary-signup:hover {
            background-color: #F8FAFC;
            border-color: #CBD5E1;
            color: #0F172A;
        }

        .footer-security {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: #94A3B8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 992px) {
            .login-card {
                flex-direction: column;
                max-width: 520px;
            }
            .hero-panel, .form-panel {
                width: 100%;
            }
            .hero-panel {
                padding: 36px 28px;
            }
            .form-panel {
                padding: 36px 28px;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <!-- LEFT HERO PANEL -->
        <div class="hero-panel">
            <div>
                <div class="badge-group">
                    <span class="pill-badge"><span class="dot-blue"></span> LOEWIX SALES • PLATFORM PROSPEK</span>
                    <span class="pill-badge pill-badge-sub">MANAJEMEN PENJUALAN</span>
                </div>

                <h1 class="hero-title">
                    Pusat Kendali<br><span class="highlight">Prospek & Sales</span><br>dalam Satu Tempat.
                </h1>

                <p class="hero-subtitle">
                    Platform resmi tim Loewix Sales untuk mencatat calon customer, kelola laporan prospek, serta memantau performa penjualan secara real-time.
                </p>

                <div class="feature-chips">
                    <span class="chip">🎯 Laporan Prospek</span>
                    <span class="chip">📊 Target Penjualan</span>
                    <span class="chip">🤝 Follow Up Customer</span>
                    <span class="chip">📢 Promosi & Broadcast</span>
                </div>

                <!-- PREVIEW DASHBOARD WIDGET -->
                <div class="preview-widget">
                    <div class="widget-header">
                        <span class="dot-control" style="background:#EF4444;"></span>
                        <span class="dot-control" style="background:#F59E0B;"></span>
                        <span class="dot-control" style="background:#10B981;"></span>
                        <span style="margin-left: 6px;">Loewix Sales System - Overview</span>
                    </div>

                    <div class="widget-stats">
                        <div class="stat-box">
                            <div class="stat-label">TOTAL PROSPEK</div>
                            <div class="stat-val">1.28k <span class="badge-inc">↑ +15%</span></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">DEAL BULAN INI</div>
                            <div class="stat-val">42 <span class="badge-online">• Active</span></div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">PERFORMA SALES</div>
                            <div class="stat-val">94% <span class="badge-inc">↑ +6%</span></div>
                        </div>
                    </div>

                    <div style="font-size: 10px; font-weight: 700; color: #94A3B8; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 6px;">AKTIVITAS MINGGU INI</div>
                    <div class="chart-mini">
                        <div class="bar" style="height: 40%;"></div>
                        <div class="bar" style="height: 65%;"></div>
                        <div class="bar active" style="height: 100%;"></div>
                        <div class="bar active" style="height: 85%;"></div>
                        <div class="bar" style="height: 50%;"></div>
                        <div class="bar active" style="height: 90%;"></div>
                        <div class="bar" style="height: 35%;"></div>
                    </div>
                </div>
            </div>

            <div class="hero-footer">
                © 2026 Loewix Sales. All rights reserved.
            </div>
        </div>

        <!-- RIGHT FORM PANEL -->
        <div class="form-panel">
            <div class="logo-container">
                <img src="assets/images/loewix_sales_logo.png" alt="Loewix Sales" class="logo-img">
            </div>

            <div class="welcome-text">Selamat datang kembali, Team Sales! 👋</div>
            <h2 class="form-title">Masuk ke Portal Sales</h2>
            <p class="form-subtitle">Masukkan email & password untuk mengelola laporan prospek</p>

            <?php if($error): ?>
                <div class="alert alert-danger border-0 rounded-4 mb-4 py-3 px-3" style="background-color: #FEF2F2; color: #991B1B; font-size: 13.5px; font-weight: 600;">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="input-group-custom">
                    <label for="email" class="input-label">EMAIL ADDRESS</label>
                    <input type="email" class="custom-input" id="email" name="email" placeholder="wahyuwutomo31@gmail.com" required autocomplete="email">
                </div>

                <div class="input-group-custom">
                    <label for="password" class="input-label">PASSWORD</label>
                    <div class="password-wrapper">
                        <input type="password" class="custom-input" id="password" name="password" placeholder="••••••••••••" required autocomplete="current-password">
                        <button type="button" class="btn-eye" id="togglePassword" aria-label="Toggle password visibility">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="remember-row">
                    <label class="d-flex align-items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" id="remember" checked>
                        <span>Ingat saya selama 30 hari</span>
                    </label>
                </div>

                <button type="submit" class="btn-submit">
                    Masuk ke Dashboard Sales <span style="font-size: 18px;">→</span>
                </button>
            </form>

            <div class="divider">
                <span>ATAU</span>
            </div>

            <a href="signup.php" class="btn-secondary-signup">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                Buat Akun Sales Baru
            </a>

            <div class="footer-security">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Dilindungi sistem keamanan Loewix Sales
            </div>
        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon style
            this.style.opacity = type === 'text' ? '1' : '0.6';
            this.style.color = type === 'text' ? '#2B78C5' : '#94A3B8';
        });
    </script>
</body>
</html>