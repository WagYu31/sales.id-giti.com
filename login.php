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
    <title>Login - Loewix Sales</title>
    <meta name="description" content="Login ke dashboard Loewix Sales - Platform manajemen tim sales terpadu">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: #0B1120;
            overflow: hidden;
            position: relative;
        }

        /* ===== ANIMATED BACKGROUND ===== */
        .bg-layer {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: 
                radial-gradient(ellipse 80% 60% at 20% 80%, rgba(56, 189, 248, 0.08) 0%, transparent 60%),
                radial-gradient(ellipse 70% 50% at 80% 20%, rgba(168, 85, 247, 0.07) 0%, transparent 55%),
                radial-gradient(ellipse 90% 70% at 50% 50%, rgba(14, 165, 233, 0.04) 0%, transparent 70%),
                linear-gradient(180deg, #0B1120 0%, #0F172A 40%, #131C2E 100%);
        }

        /* Animated grid */
        .grid-overlay {
            position: fixed;
            inset: 0;
            z-index: 1;
            background-image: 
                linear-gradient(rgba(148, 163, 184, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridShift 20s linear infinite;
        }

        @keyframes gridShift {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
        }

        /* Floating orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 1;
            animation: float 8s ease-in-out infinite;
        }

        .orb-1 {
            width: 400px; height: 400px;
            background: rgba(56, 189, 248, 0.06);
            top: -10%; left: -5%;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 350px; height: 350px;
            background: rgba(168, 85, 247, 0.05);
            bottom: -15%; right: -5%;
            animation-delay: -3s;
        }

        .orb-3 {
            width: 250px; height: 250px;
            background: rgba(245, 158, 11, 0.04);
            top: 40%; right: 20%;
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.05); }
            66% { transform: translate(-20px, 15px) scale(0.95); }
        }

        /* ===== MAIN LAYOUT ===== */
        .login-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-container {
            width: 100%;
            max-width: 480px;
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== LOGO SECTION ===== */
        .logo-section {
            text-align: center;
            margin-bottom: 36px;
            animation: fadeIn 1s ease 0.3s forwards;
            opacity: 0;
        }

        @keyframes fadeIn {
            to { opacity: 1; }
        }

        .logo-section img {
            height: 56px;
            width: auto;
            object-fit: contain;
            filter: brightness(1.1) drop-shadow(0 0 20px rgba(56, 189, 248, 0.15));
            margin-bottom: 12px;
        }

        .logo-tagline {
            font-size: 13px;
            font-weight: 500;
            color: rgba(148, 163, 184, 0.7);
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        /* ===== GLASS CARD ===== */
        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(40px) saturate(150%);
            -webkit-backdrop-filter: blur(40px) saturate(150%);
            border: 1px solid rgba(148, 163, 184, 0.08);
            border-radius: 24px;
            padding: 44px 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 0 0 1px rgba(255, 255, 255, 0.03) inset,
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                0 0 80px -20px rgba(56, 189, 248, 0.06);
        }

        /* Top glow line */
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.4), rgba(168, 85, 247, 0.3), transparent);
        }

        .card-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .card-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: #F1F5F9;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .card-header p {
            font-size: 14px;
            color: #64748B;
            font-weight: 400;
            line-height: 1.5;
        }

        /* ===== ERROR ALERT ===== */
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 14px;
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            font-weight: 600;
            color: #FCA5A5;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        /* ===== FORM FIELDS ===== */
        .form-group {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #94A3B8;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #475569;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .form-input {
            width: 100%;
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(71, 85, 105, 0.3);
            border-radius: 14px;
            padding: 15px 16px 15px 48px;
            font-size: 14.5px;
            font-weight: 500;
            color: #F1F5F9;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-input::placeholder {
            color: #475569;
            font-weight: 400;
        }

        .form-input:focus {
            background: rgba(30, 41, 59, 0.8);
            border-color: rgba(56, 189, 248, 0.4);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.08), 0 0 20px -5px rgba(56, 189, 248, 0.15);
        }

        .form-input:focus + .input-icon,
        .form-input:focus ~ .input-icon {
            color: #38BDF8;
        }

        .btn-eye {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #475569;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-eye:hover {
            color: #94A3B8;
            background: rgba(148, 163, 184, 0.1);
        }

        /* ===== REMEMBER + FORGOT ===== */
        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 13.5px;
            color: #94A3B8;
            font-weight: 500;
        }

        .custom-checkbox {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border: 1.5px solid rgba(71, 85, 105, 0.5);
            border-radius: 6px;
            background: rgba(30, 41, 59, 0.5);
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .custom-checkbox:checked {
            background: linear-gradient(135deg, #0EA5E9, #38BDF8);
            border-color: #38BDF8;
        }

        .custom-checkbox:checked::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 2px;
            width: 5px;
            height: 9px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .forgot-link {
            font-size: 13px;
            color: #38BDF8;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #7DD3FC;
        }

        /* ===== SUBMIT BUTTON ===== */
        .btn-login {
            width: 100%;
            position: relative;
            padding: 16px;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            color: #FFFFFF;
            cursor: pointer;
            overflow: hidden;
            background: linear-gradient(135deg, #0EA5E9 0%, #38BDF8 50%, #0EA5E9 100%);
            background-size: 200% auto;
            box-shadow: 0 8px 24px -4px rgba(14, 165, 233, 0.35), 0 0 0 1px rgba(56, 189, 248, 0.15) inset;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }

        .btn-login:hover {
            background-position: right center;
            box-shadow: 0 12px 32px -4px rgba(14, 165, 233, 0.45), 0 0 0 1px rgba(56, 189, 248, 0.2) inset;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0px) scale(0.99);
        }

        /* Shimmer effect on button */
        .btn-login::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            50%, 100% { left: 100%; }
        }

        /* ===== DIVIDER ===== */
        .divider-row {
            display: flex;
            align-items: center;
            margin: 28px 0;
        }

        .divider-row::before, .divider-row::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(71, 85, 105, 0.3);
        }

        .divider-row span {
            padding: 0 16px;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* ===== SECONDARY BUTTON ===== */
        .btn-signup {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 15px;
            border-radius: 14px;
            border: 1px solid rgba(71, 85, 105, 0.3);
            background: rgba(30, 41, 59, 0.3);
            color: #CBD5E1;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-signup:hover {
            background: rgba(30, 41, 59, 0.6);
            border-color: rgba(100, 116, 139, 0.4);
            color: #F1F5F9;
        }

        /* ===== FOOTER ===== */
        .card-footer {
            text-align: center;
            margin-top: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            color: #475569;
            font-weight: 500;
        }

        /* ===== BOTTOM TEXT ===== */
        .bottom-text {
            text-align: center;
            margin-top: 28px;
            font-size: 12px;
            color: rgba(100, 116, 139, 0.5);
            font-weight: 400;
            animation: fadeIn 1s ease 0.6s forwards;
            opacity: 0;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 540px) {
            .glass-card {
                padding: 32px 24px;
                border-radius: 20px;
            }
            .card-header h1 {
                font-size: 22px;
            }
            .login-container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Animated Background -->
    <div class="bg-layer"></div>
    <div class="grid-overlay"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Main Content -->
    <div class="login-wrapper">
        <div class="login-container">

            <!-- Logo -->
            <div class="logo-section">
                <img src="assets/images/loewix_sales_logo.png" alt="Loewix Sales">
                <div class="logo-tagline">Sales Management Platform</div>
            </div>

            <!-- Glass Card -->
            <div class="glass-card">
                <div class="card-header">
                    <h1>Selamat Datang 👋</h1>
                    <p>Masukkan kredensial Anda untuk mengakses dashboard</p>
                </div>

                <?php if($error): ?>
                    <div class="alert-error">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" autocomplete="on">
                    <!-- Email -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-wrapper">
                            <input type="email" class="form-input" id="email" name="email" placeholder="nama@loewix.com" required autocomplete="email">
                            <div class="input-icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <input type="password" class="form-input" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                            <div class="input-icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <button type="button" class="btn-eye" id="togglePassword" aria-label="Toggle password">
                                <svg id="eyeIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Options Row -->
                    <div class="options-row">
                        <label class="remember-label">
                            <input type="checkbox" class="custom-checkbox" name="remember" checked>
                            Ingat saya
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-login" id="btnLogin">
                        <span>Masuk ke Dashboard</span>
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </button>
                </form>

                <!-- Divider -->
                <div class="divider-row">
                    <span>atau</span>
                </div>

                <!-- Signup Link -->
                <a href="signup.php" class="btn-signup">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Buat Akun Baru
                </a>

                <!-- Security Footer -->
                <div class="card-footer">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Dilindungi enkripsi end-to-end
                </div>
            </div>

            <!-- Bottom -->
            <div class="bottom-text">
                © 2026 Loewix Sales. All rights reserved.
            </div>

        </div>
    </div>

    <script>
        // Toggle password visibility
        const toggle = document.getElementById('togglePassword');
        const pwInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        toggle.addEventListener('click', () => {
            const isPassword = pwInput.type === 'password';
            pwInput.type = isPassword ? 'text' : 'password';
            
            if (isPassword) {
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
                toggle.style.color = '#38BDF8';
            } else {
                eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
                toggle.style.color = '#475569';
            }
        });

        // Add subtle parallax to orbs on mouse move
        document.addEventListener('mousemove', (e) => {
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;
            document.querySelectorAll('.orb').forEach((orb, i) => {
                const speed = (i + 1) * 0.5;
                orb.style.transform = `translate(${x * speed}px, ${y * speed}px)`;
            });
        });
    </script>
</body>
</html>