<?php
// login.php
require_once 'includes/db.php';
$error = '';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, nama_lengkap, password, role FROM sales WHERE email = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            if ($_SESSION['role'] === 'adminsales') {
                header("Location: promosi_management.php");
            } else {
                header("Location: customer_management.php");
            }
            exit();
        }
    }
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
    <meta name="description" content="Login ke Loewix Sales — platform manajemen tim sales internal.">
    <title>Login — Loewix Sales</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}

        html{height:100%}

        body{
            font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
            height:100%;
            display:flex;
            background:#FFFFFF;
            color:#1A1A2E;
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
        }

        /* ============ LEFT BRANDING PANEL ============ */
        .brand-panel{
            width:50%;
            min-height:100vh;
            background:linear-gradient(160deg,#0B1D3A 0%,#0F2847 35%,#132E52 60%,#0B1D3A 100%);
            position:relative;
            display:flex;
            flex-direction:column;
            justify-content:center;
            align-items:center;
            padding:60px;
            overflow:hidden;
        }

        /* Subtle grid pattern */
        .brand-panel::before{
            content:'';
            position:absolute;
            inset:0;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px,transparent 1px),
                linear-gradient(90deg,rgba(255,255,255,0.02) 1px,transparent 1px);
            background-size:48px 48px;
        }

        /* Accent glow */
        .brand-panel::after{
            content:'';
            position:absolute;
            width:500px;height:500px;
            border-radius:50%;
            background:radial-gradient(circle,rgba(74,144,226,0.12) 0%,transparent 70%);
            bottom:-20%;left:-10%;
            filter:blur(60px);
        }

        .brand-content{
            position:relative;
            z-index:2;
            max-width:420px;
            text-align:center;
        }

        .brand-logo-white{
            height:72px;
            width:auto;
            margin-bottom:40px;
            filter:brightness(0) invert(1);
            opacity:0.95;
        }

        .brand-headline{
            font-size:28px;
            font-weight:800;
            color:#FFFFFF;
            line-height:1.3;
            margin-bottom:16px;
            letter-spacing:-0.5px;
        }

        .brand-headline span{
            background:linear-gradient(135deg,#4A90E2,#67B8F7);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
        }

        .brand-desc{
            font-size:15px;
            line-height:1.7;
            color:rgba(255,255,255,0.55);
            margin-bottom:48px;
        }

        /* Stats row */
        .stats-row{
            display:flex;
            gap:32px;
            justify-content:center;
        }

        .stat-item{
            text-align:center;
        }

        .stat-number{
            font-size:28px;
            font-weight:800;
            color:#FFFFFF;
            letter-spacing:-0.5px;
        }

        .stat-label{
            font-size:11px;
            font-weight:600;
            color:rgba(255,255,255,0.4);
            text-transform:uppercase;
            letter-spacing:1.2px;
            margin-top:4px;
        }

        .stat-divider{
            width:1px;
            background:rgba(255,255,255,0.1);
            align-self:stretch;
        }

        .brand-footer{
            position:absolute;
            bottom:32px;
            left:0;right:0;
            text-align:center;
            font-size:12px;
            color:rgba(255,255,255,0.25);
            z-index:2;
        }

        /* ============ RIGHT FORM PANEL ============ */
        .form-panel{
            width:50%;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:40px;
            background:#FFFFFF;
        }

        .form-container{
            width:100%;
            max-width:400px;
            animation:fadeUp .6s cubic-bezier(.16,1,.3,1) forwards;
            opacity:0;
        }

        @keyframes fadeUp{
            from{opacity:0;transform:translateY(16px)}
            to{opacity:1;transform:translateY(0)}
        }

        /* Logo for form panel */
        .form-logo{
            height:48px;
            width:auto;
            margin-bottom:32px;
        }

        .form-greeting{
            font-size:13px;
            font-weight:600;
            color:#4A90E2;
            text-transform:uppercase;
            letter-spacing:1px;
            margin-bottom:8px;
        }

        .form-title{
            font-size:28px;
            font-weight:800;
            color:#1A1A2E;
            margin-bottom:8px;
            letter-spacing:-0.5px;
        }

        .form-subtitle{
            font-size:14px;
            color:#8C8CA1;
            margin-bottom:36px;
            line-height:1.5;
        }

        /* Alert */
        .alert-error{
            background:#FFF5F5;
            border:1px solid #FED7D7;
            border-radius:12px;
            padding:14px 16px;
            margin-bottom:24px;
            display:flex;align-items:center;gap:10px;
            font-size:13.5px;font-weight:600;
            color:#C53030;
            animation:shake .4s ease;
        }

        @keyframes shake{
            0%,100%{transform:translateX(0)}
            25%{transform:translateX(-5px)}
            75%{transform:translateX(5px)}
        }

        /* Form fields */
        .field{margin-bottom:20px}

        .field-label{
            display:block;
            font-size:13px;
            font-weight:600;
            color:#1A1A2E;
            margin-bottom:8px;
        }

        .input-wrap{position:relative}

        .input-wrap svg.ico{
            position:absolute;
            left:14px;top:50%;
            transform:translateY(-50%);
            color:#B0B0C3;
            pointer-events:none;
            transition:color .2s;
        }

        .field-input{
            width:100%;
            padding:14px 14px 14px 44px;
            border:1.5px solid #E8E8EF;
            border-radius:12px;
            font-size:14px;
            font-weight:500;
            color:#1A1A2E;
            background:#FAFAFC;
            font-family:'Inter',sans-serif;
            outline:none;
            transition:all .25s ease;
        }

        .field-input::placeholder{color:#B0B0C3;font-weight:400}

        .field-input:focus{
            border-color:#4A90E2;
            background:#FFFFFF;
            box-shadow:0 0 0 4px rgba(74,144,226,0.1);
        }

        .field-input:focus ~ svg.ico,
        .field-input:focus + svg.ico{
            color:#4A90E2;
        }

        .btn-toggle{
            position:absolute;right:12px;top:50%;
            transform:translateY(-50%);
            background:none;border:none;
            color:#B0B0C3;cursor:pointer;
            padding:4px;border-radius:6px;
            display:flex;align-items:center;
            transition:all .2s;
        }

        .btn-toggle:hover{color:#6C6C80;background:rgba(0,0,0,0.04)}

        /* Options row */
        .options{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:28px;
        }

        .checkbox-label{
            display:flex;align-items:center;gap:8px;
            font-size:13px;color:#6C6C80;font-weight:500;
            cursor:pointer;
        }

        .checkbox-label input[type="checkbox"]{
            appearance:none;-webkit-appearance:none;
            width:18px;height:18px;
            border:1.5px solid #D0D0DD;
            border-radius:5px;
            background:#FAFAFC;
            cursor:pointer;
            position:relative;
            transition:all .2s;
        }

        .checkbox-label input:checked{
            background:#4A90E2;
            border-color:#4A90E2;
        }

        .checkbox-label input:checked::after{
            content:'';position:absolute;
            left:5px;top:2px;
            width:5px;height:9px;
            border:solid #fff;
            border-width:0 2px 2px 0;
            transform:rotate(45deg);
        }

        /* Submit button */
        .btn-submit{
            width:100%;
            padding:15px;
            border:none;
            border-radius:12px;
            font-size:15px;
            font-weight:700;
            font-family:'Inter',sans-serif;
            color:#FFFFFF;
            cursor:pointer;
            background:#1A1A2E;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            transition:all .25s ease;
            box-shadow:0 1px 3px rgba(0,0,0,0.08);
        }

        .btn-submit:hover{
            background:#2D2D4A;
            box-shadow:0 4px 12px rgba(26,26,46,0.2);
            transform:translateY(-1px);
        }

        .btn-submit:active{
            transform:translateY(0);
        }

        /* Divider */
        .divider{
            display:flex;align-items:center;
            margin:24px 0;
        }

        .divider::before,.divider::after{
            content:'';flex:1;
            height:1px;background:#E8E8EF;
        }

        .divider span{
            padding:0 14px;
            font-size:11px;font-weight:700;
            color:#B0B0C3;
            letter-spacing:1px;
            text-transform:uppercase;
        }

        /* Secondary button */
        .btn-secondary{
            width:100%;
            display:flex;align-items:center;justify-content:center;gap:8px;
            padding:14px;
            border-radius:12px;
            border:1.5px solid #E8E8EF;
            background:#FFFFFF;
            color:#1A1A2E;
            font-size:14px;font-weight:600;
            font-family:'Inter',sans-serif;
            text-decoration:none;
            cursor:pointer;
            transition:all .2s;
        }

        .btn-secondary:hover{
            background:#FAFAFC;
            border-color:#D0D0DD;
        }

        /* Security badge */
        .security-badge{
            margin-top:32px;
            text-align:center;
            display:flex;align-items:center;justify-content:center;gap:6px;
            font-size:12px;color:#B0B0C3;font-weight:500;
        }

        /* ============ RESPONSIVE ============ */
        @media(max-width:900px){
            body{flex-direction:column}
            .brand-panel{
                width:100%;min-height:auto;
                padding:40px 24px;
            }
            .brand-headline{font-size:22px}
            .stats-row{gap:20px}
            .stat-number{font-size:22px}
            .form-panel{
                width:100%;min-height:auto;
                padding:32px 24px;
            }
            .form-container{max-width:100%}
        }

        @media(max-width:480px){
            .brand-panel{padding:32px 20px}
            .form-panel{padding:24px 20px}
            .form-title{font-size:24px}
        }
    </style>
</head>
<body>

    <!-- LEFT: BRANDING PANEL -->
    <div class="brand-panel">
        <div class="brand-content">
            <img src="assets/images/loewix_sales_logo.png?v=<?php echo time(); ?>" alt="Loewix Sales" class="brand-logo-white">

            <h1 class="brand-headline">
                Kelola Tim Sales Anda<br>dalam <span>Satu Platform</span>
            </h1>

            <p class="brand-desc">
                Pantau target, absensi, dan performa tim secara real-time. Semua yang Anda butuhkan untuk mengoptimalkan operasional sales.
            </p>

            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Pengguna Aktif</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <div class="stat-number">98%</div>
                    <div class="stat-label">Uptime</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Support</div>
                </div>
            </div>
        </div>

        <div class="brand-footer">© 2026 Loewix Sales. All rights reserved.</div>
    </div>

    <!-- RIGHT: FORM PANEL -->
    <div class="form-panel">
        <div class="form-container">

            <img src="assets/images/loewix_sales_logo.png?v=<?php echo time(); ?>" alt="Loewix Sales" class="form-logo">

            <div class="form-greeting">Selamat datang kembali</div>
            <h2 class="form-title">Masuk ke akun Anda</h2>
            <p class="form-subtitle">Masukkan email dan password untuk melanjutkan ke dashboard.</p>

            <?php if($error): ?>
                <div class="alert-error">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="on">
                <div class="field">
                    <label for="email" class="field-label">Email</label>
                    <div class="input-wrap">
                        <input type="email" class="field-input" id="email" name="email" placeholder="nama@perusahaan.com" required autocomplete="email">
                        <svg class="ico" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                </div>

                <div class="field">
                    <label for="password" class="field-label">Password</label>
                    <div class="input-wrap">
                        <input type="password" class="field-input" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                        <svg class="ico" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <button type="button" class="btn-toggle" id="togglePw" aria-label="Tampilkan password">
                            <svg id="eyeIco" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" checked>
                        Ingat saya
                    </label>
                </div>

                <button type="submit" class="btn-submit">
                    Masuk ke Dashboard
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </button>
            </form>

            <div class="divider"><span>atau</span></div>

            <a href="signup.php" class="btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Buat Akun Baru
            </a>

            <div class="security-badge">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Dilindungi enkripsi SSL 256-bit
            </div>
        </div>
    </div>

    <script>
        const tog=document.getElementById('togglePw'),
              pw=document.getElementById('password'),
              eye=document.getElementById('eyeIco');
        tog.addEventListener('click',()=>{
            const show=pw.type==='password';
            pw.type=show?'text':'password';
            eye.innerHTML=show
                ?'<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>'
                :'<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
            tog.style.color=show?'#4A90E2':'#B0B0C3';
        });
    </script>
</body>
</html>