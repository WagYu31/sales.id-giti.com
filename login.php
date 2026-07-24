<?php
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        html{height:100%}
        body{
            font-family:'Inter','Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif;
            height:100%;display:flex;
            background:#FFFFFF;color:#0F172A;
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale;
        }

        /* ============ LEFT HERO ============ */
        .hero{
            width:50%;min-height:100vh;
            background:#001D4A;
            position:relative;display:flex;
            flex-direction:column;justify-content:center;
            padding:60px;overflow:hidden;
        }

        #particleCanvas{
            position:absolute;inset:0;
            width:100%;height:100%;
            z-index:1;
        }

        /* Radial glow overlay */
        .hero::after{
            content:'';position:absolute;inset:0;
            background:
                radial-gradient(ellipse 60% 50% at 50% 60%, rgba(0,82,204,0.2) 0%, transparent 70%),
                radial-gradient(ellipse 40% 30% at 30% 40%, rgba(59,130,246,0.1) 0%, transparent 60%);
            z-index:2;pointer-events:none;
        }

        .hero-content{
            position:relative;z-index:3;
            max-width:480px;
        }

        /* Logo text */
        .hero-logo{
            display:flex;align-items:center;gap:10px;
            margin-bottom:48px;
        }

        .hero-logo-img{
            height:56px;width:auto;
            filter:brightness(0) invert(1);
            opacity:0.95;
        }

        .hero-headline{
            font-size:38px;font-weight:700;
            color:#FFFFFF;line-height:1.25;
            letter-spacing:-0.8px;margin-bottom:16px;
        }

        .hero-headline span{
            font-weight:800;
            background:linear-gradient(135deg,#3B82F6,#06B6D4);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
        }

        .hero-desc{
            font-size:14px;line-height:1.7;
            color:#94A3B8;
            font-weight:400;
            margin-bottom:40px;max-width:380px;
        }

        /* Glassmorphism Stat Cards */
        .stat-row{display:flex;gap:14px;flex-wrap:wrap}

        .stat-glass{
            background:rgba(255,255,255,0.05);
            backdrop-filter:blur(10px);
            -webkit-backdrop-filter:blur(10px);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:16px;
            padding:20px 24px;
            min-width:130px;flex:1;
            text-align:center;
            transition:all 0.3s ease;
        }

        .stat-glass:hover{
            background:rgba(255,255,255,0.08);
            border-color:rgba(255,255,255,0.15);
            transform:translateY(-2px);
        }

        .stat-glass-icon{
            width:40px;height:40px;
            border-radius:10px;
            background:rgba(255,255,255,0.08);
            display:flex;align-items:center;justify-content:center;
            margin:0 auto 12px;
            color:rgba(255,255,255,0.6);font-size:18px;
        }

        .stat-glass-value{
            font-size:24px;font-weight:700;
            color:#FFFFFF;letter-spacing:-0.5px;
            line-height:1;
        }

        .stat-glass-label{
            font-size:10px;font-weight:600;
            color:#94A3B8;
            text-transform:uppercase;
            letter-spacing:1.2px;
            margin-top:6px;
        }

        .hero-footer{
            position:absolute;bottom:28px;left:60px;
            font-size:12px;color:rgba(255,255,255,0.2);
            z-index:3;
        }

        /* ============ RIGHT FORM ============ */
        .form-panel{
            width:50%;min-height:100vh;
            display:flex;align-items:center;justify-content:center;
            padding:40px 60px;
            background:#F8FAFC;
        }

        .form-container{
            width:100%;max-width:440px;
            background:#FFFFFF;
            border-radius:20px;
            padding:40px 36px;
            box-shadow:0 4px 24px rgba(0,0,0,0.06), 0 1px 4px rgba(0,0,0,0.04);
            border:1px solid #F1F5F9;
            animation:fadeUp .6s cubic-bezier(.16,1,.3,1) forwards;
            opacity:0;
        }

        @keyframes fadeUp{
            from{opacity:0;transform:translateY(20px)}
            to{opacity:1;transform:translateY(0)}
        }

        .form-greeting{
            font-size:11px;font-weight:700;
            color:#2563EB;text-transform:uppercase;
            letter-spacing:2px;margin-bottom:6px;
        }

        .form-title{
            font-size:26px;font-weight:800;
            color:#0F172A;margin-bottom:6px;
            letter-spacing:-0.5px;line-height:1.2;
        }

        .form-subtitle{
            font-size:13.5px;color:#64748B;
            font-weight:400;
            margin-bottom:28px;line-height:1.5;
        }

        /* Alert */
        .alert-error{
            background:#FEF2F2;border:1px solid #FECACA;
            border-radius:12px;padding:12px 16px;
            margin-bottom:16px;display:flex;align-items:center;gap:8px;
            font-size:13px;font-weight:600;color:#DC2626;
            animation:shake .4s ease;
        }
        @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}

        /* Fields */
        .field{margin-bottom:16px}
        .field-label{
            display:block;font-size:13px;font-weight:600;
            color:#1E293B;margin-bottom:6px;
        }
        .input-wrap{position:relative}
        .input-wrap .ico{
            position:absolute;left:14px;top:50%;
            transform:translateY(-50%);
            color:#64748B;pointer-events:none;
            transition:color .2s;
        }
        .field-input{
            width:100%;padding:12px 14px 12px 44px;
            border:1.5px solid #CBD5E1;border-radius:10px;
            font-size:14px;font-weight:500;color:#0F172A;
            background:#FFFFFF;font-family:'Inter','Plus Jakarta Sans',sans-serif;
            outline:none;transition:all .2s ease;
        }
        .field-input::placeholder{color:#94A3B8;font-weight:400;font-size:13px}
        .field-input:hover{border-color:#94A3B8}
        .field-input:focus{
            border-color:#2563EB;
            box-shadow:0 0 0 3px rgba(37,99,235,0.1);
            background:#FFFFFF;
        }
        .field-input:focus ~ .ico{color:#2563EB}

        .btn-toggle{
            position:absolute;right:12px;top:50%;
            transform:translateY(-50%);
            background:none;border:none;color:#64748B;
            cursor:pointer;padding:4px;border-radius:6px;
            display:flex;align-items:center;transition:all .2s;
        }
        .btn-toggle:hover{color:#1E293B;background:rgba(0,0,0,0.05)}

        /* Options */
        .options{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
        .checkbox-label{
            display:flex;align-items:center;gap:8px;
            font-size:13px;color:#475569;font-weight:500;cursor:pointer;
        }
        .checkbox-label input[type="checkbox"]{
            appearance:none;-webkit-appearance:none;
            width:18px;height:18px;border:2px solid #CBD5E1;
            border-radius:5px;background:#FFFFFF;cursor:pointer;
            position:relative;transition:all .2s;
        }
        .checkbox-label input[type="checkbox"]:hover{border-color:#94A3B8}
        .checkbox-label input:checked{background:#2563EB;border-color:#2563EB}
        .checkbox-label input:checked::after{
            content:'';position:absolute;left:5px;top:2px;
            width:5px;height:9px;border:solid #fff;
            border-width:0 2px 2px 0;transform:rotate(45deg);
        }

        /* Submit */
        .btn-submit{
            width:100%;padding:14px;border:none;border-radius:10px;
            font-size:15px;font-weight:700;
            font-family:'Inter','Plus Jakarta Sans',sans-serif;
            color:#FFFFFF;cursor:pointer;
            background:linear-gradient(135deg,#0F2847 0%,#1D4ED8 100%);
            display:flex;align-items:center;justify-content:center;gap:8px;
            transition:all .25s ease;
            box-shadow:0 4px 12px rgba(29,78,216,0.3);
        }
        .btn-submit:hover{
            box-shadow:0 8px 24px rgba(29,78,216,0.4);
            transform:translateY(-2px);
        }
        .btn-submit:active{transform:translateY(0)}

        /* Divider */
        .divider{display:flex;align-items:center;margin:18px 0}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:#E2E8F0}
        .divider span{padding:0 14px;font-size:10px;font-weight:700;color:#94A3B8;letter-spacing:1.5px}

        /* Secondary button */
        .btn-secondary{
            width:100%;display:flex;align-items:center;justify-content:center;gap:8px;
            padding:12px;border-radius:10px;border:1.5px solid #E2E8F0;
            background:#FFFFFF;color:#0F172A;font-size:14px;font-weight:600;
            font-family:'Inter','Plus Jakarta Sans',sans-serif;text-decoration:none;
            cursor:pointer;transition:all .2s;
        }
        .btn-secondary:hover{background:#F1F5F9;border-color:#CBD5E1;transform:translateY(-1px)}

        /* Security */
        .security-badge{
            margin-top:24px;text-align:center;
            display:flex;align-items:center;justify-content:center;gap:6px;
            font-size:11px;color:#94A3B8;font-weight:500;
        }

        /* ============ RESPONSIVE ============ */
        @media(max-width:900px){
            body{flex-direction:column}
            .hero{width:100%;min-height:auto;padding:40px 24px}
            .hero-headline{font-size:28px}
            .form-panel{width:100%;min-height:auto;padding:32px 24px}
        }
        @media(max-width:480px){
            .hero{padding:32px 20px}
            .form-panel{padding:24px 20px}
            .hero-headline{font-size:24px}
            .stat-glass{min-width:90px;padding:14px}
            .stat-glass-value{font-size:22px}
        }
    </style>
</head>
<body>

<!-- ===== LEFT HERO ===== -->
<div class="hero">
    <canvas id="particleCanvas"></canvas>

    <div class="hero-content">
        <div class="hero-logo">
            <img src="assets/images/loewix_sales_logo.png?v=<?php echo time(); ?>" alt="Loewix Sales" class="hero-logo-img">
        </div>

        <h1 class="hero-headline">
            Kelola Tim Sales<br>Anda dalam<br><span>Satu Platform</span>
        </h1>

        <p class="hero-desc">
            Kelola data customer, lacak follow-up, dan pantau performa tim sales dari satu dashboard. Solusi lengkap untuk meningkatkan konversi penjualan Anda.
        </p>

        <div class="stat-row">
            <div class="stat-glass">
                <div class="stat-glass-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <div class="stat-glass-value">500+</div>
                <div class="stat-glass-label">Pengguna Aktif</div>
            </div>
            <div class="stat-glass">
                <div class="stat-glass-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="stat-glass-value">98%</div>
                <div class="stat-glass-label">Uptime</div>
            </div>
            <div class="stat-glass">
                <div class="stat-glass-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="stat-glass-value">24/7</div>
                <div class="stat-glass-label">Support</div>
            </div>
        </div>
    </div>

    <div class="hero-footer">&copy; 2026 Loewix Sales. All rights reserved.</div>
</div>

<!-- ===== RIGHT FORM ===== -->
<div class="form-panel">
    <div class="form-container">
        <div class="form-greeting">Selamat Datang Kembali</div>
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
                        <svg id="eyeIco" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
            </div>

            <div class="options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember"> Ingat saya
                </label>
            </div>

            <button type="submit" class="btn-submit">
                Masuk ke Dashboard
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </button>
        </form>

        <div class="divider"><span>ATAU</span></div>

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

<!-- ===== SCRIPTS ===== -->
<script>
// Password toggle
const tog=document.getElementById('togglePw'),
      pw=document.getElementById('password'),
      eye=document.getElementById('eyeIco');
tog.addEventListener('click',()=>{
    const show=pw.type==='password';
    pw.type=show?'text':'password';
    eye.innerHTML=show
        ?'<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'
        :'<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
});

// Globe + Network Animation
(function(){
    const canvas=document.getElementById('particleCanvas');
    const ctx=canvas.getContext('2d');
    let w,h;
    let angle=0;

    function resize(){
        w=canvas.width=canvas.offsetWidth;
        h=canvas.height=canvas.offsetHeight;
    }

    // Globe parameters
    const GLOBE_RADIUS_RATIO=0.28;
    const MERIDIANS=12;
    const PARALLELS=8;

    // Floating particles
    const particles=[];
    const PARTICLE_COUNT=60;

    class Particle{
        constructor(){
            this.x=Math.random()*2000;
            this.y=Math.random()*2000;
            this.vx=(Math.random()-0.5)*0.3;
            this.vy=(Math.random()-0.5)*0.3;
            this.r=Math.random()*1.5+0.5;
            this.alpha=Math.random()*0.3+0.05;
        }
        update(){
            this.x+=this.vx;
            this.y+=this.vy;
            if(this.x<0||this.x>w)this.vx*=-1;
            if(this.y<0||this.y>h)this.vy*=-1;
        }
        draw(){
            ctx.beginPath();
            ctx.arc(this.x,this.y,this.r,0,Math.PI*2);
            ctx.fillStyle=`rgba(100,180,255,${this.alpha})`;
            ctx.fill();
        }
    }

    function initParticles(){
        particles.length=0;
        for(let i=0;i<PARTICLE_COUNT;i++){
            const p=new Particle();
            p.x=Math.random()*w;
            p.y=Math.random()*h;
            particles.push(p);
        }
    }

    function drawGlobe(cx,cy,R,rot){
        ctx.strokeStyle='rgba(80,160,255,0.12)';
        ctx.lineWidth=0.8;

        // Draw parallels (horizontal circles)
        for(let i=1;i<PARALLELS;i++){
            const lat=Math.PI*i/PARALLELS-Math.PI/2;
            const r=Math.cos(lat)*R;
            const y=cy+Math.sin(lat)*R;
            ctx.beginPath();
            ctx.ellipse(cx,y,r,r*0.15,0,0,Math.PI*2);
            ctx.stroke();
        }

        // Draw meridians (vertical arcs)
        for(let i=0;i<MERIDIANS;i++){
            const lon=Math.PI*2*i/MERIDIANS+rot;
            const x1=Math.cos(lon)*R*0.05;
            ctx.beginPath();
            for(let j=0;j<=40;j++){
                const lat=Math.PI*j/40-Math.PI/2;
                const px=cx+Math.cos(lat)*Math.sin(lon)*R;
                const py=cy+Math.sin(lat)*R;
                if(j===0)ctx.moveTo(px,py);
                else ctx.lineTo(px,py);
            }
            ctx.stroke();
        }

        // Outer circle
        ctx.beginPath();
        ctx.arc(cx,cy,R,0,Math.PI*2);
        ctx.strokeStyle='rgba(80,160,255,0.2)';
        ctx.lineWidth=1;
        ctx.stroke();

        // Inner glow
        const grad=ctx.createRadialGradient(cx,cy,R*0.1,cx,cy,R*1.3);
        grad.addColorStop(0,'rgba(0,82,204,0.15)');
        grad.addColorStop(0.5,'rgba(0,82,204,0.05)');
        grad.addColorStop(1,'transparent');
        ctx.fillStyle=grad;
        ctx.beginPath();
        ctx.arc(cx,cy,R*1.3,0,Math.PI*2);
        ctx.fill();

        // Bright dots on globe surface (data points)
        const dots=16;
        for(let i=0;i<dots;i++){
            const lat=Math.random()*Math.PI-Math.PI/2;
            const lon=Math.random()*Math.PI*2+rot*2;
            const px=cx+Math.cos(lat)*Math.sin(lon)*R;
            const py=cy+Math.sin(lat)*R;
            const frontFacing=Math.cos(lat)*Math.cos(lon-rot);
            if(frontFacing>0){
                const alpha=frontFacing*0.6;
                ctx.beginPath();
                ctx.arc(px,py,1.5,0,Math.PI*2);
                ctx.fillStyle=`rgba(120,200,255,${alpha})`;
                ctx.fill();
            }
        }
    }

    // Network rays from globe to edges
    function drawRays(cx,cy,R){
        const rayCount=20;
        for(let i=0;i<rayCount;i++){
            const a=Math.PI*2*i/rayCount+angle*0.2;
            const startR=R*1.05;
            const endR=R*2.5+Math.sin(angle+i)*R*0.5;
            const x1=cx+Math.cos(a)*startR;
            const y1=cy+Math.sin(a)*startR;
            const x2=cx+Math.cos(a)*endR;
            const y2=cy+Math.sin(a)*endR;

            const grad=ctx.createLinearGradient(x1,y1,x2,y2);
            grad.addColorStop(0,'rgba(80,160,255,0.2)');
            grad.addColorStop(1,'rgba(80,160,255,0)');
            ctx.beginPath();
            ctx.moveTo(x1,y1);
            ctx.lineTo(x2,y2);
            ctx.strokeStyle=grad;
            ctx.lineWidth=0.5;
            ctx.stroke();

            // End dot
            ctx.beginPath();
            ctx.arc(x2,y2,1.2,0,Math.PI*2);
            ctx.fillStyle='rgba(100,180,255,0.15)';
            ctx.fill();
        }
    }

    // Particle connections
    function drawConnections(){
        const maxDist=120;
        for(let i=0;i<particles.length;i++){
            for(let j=i+1;j<particles.length;j++){
                const dx=particles[i].x-particles[j].x;
                const dy=particles[i].y-particles[j].y;
                const dist=Math.sqrt(dx*dx+dy*dy);
                if(dist<maxDist){
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x,particles[i].y);
                    ctx.lineTo(particles[j].x,particles[j].y);
                    ctx.strokeStyle=`rgba(80,160,255,${(1-dist/maxDist)*0.08})`;
                    ctx.lineWidth=0.5;
                    ctx.stroke();
                }
            }
        }
    }

    function animate(){
        ctx.clearRect(0,0,w,h);

        const cx=w*0.5;
        const cy=h*0.65;
        const R=Math.min(w,h)*GLOBE_RADIUS_RATIO;

        // Draw rays behind globe
        drawRays(cx,cy,R);

        // Draw globe
        drawGlobe(cx,cy,R,angle);

        // Draw particles and connections
        drawConnections();
        particles.forEach(p=>{p.update();p.draw()});

        angle+=0.003;
        requestAnimationFrame(animate);
    }

    function init(){
        resize();
        initParticles();
        animate();
    }

    window.addEventListener('resize',()=>{resize();initParticles()});
    init();
})();
</script>
</body>
</html>