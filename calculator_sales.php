<?php
$page_title = 'Kalkulator Sales';
require_once 'includes/db.php';
require_once 'includes/header.php';

$settings_query = $conn->query("SELECT * FROM settings");
$config = [];
while ($row = $settings_query->fetch_assoc()) { $config[$row['setting_key']] = $row['setting_value']; }
$products_query = $conn->query("SELECT * FROM product_prices ORDER BY type ASC");
?>

<style>
.calc-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.calc-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.calc-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.calc-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.result-box-v2 {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    border-radius: 18px;
    padding: 28px;
    color: #FFFFFF;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.3);
}
</style>

<!-- Hero Header -->
<div class="calc-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Kalkulator Sales</span>
            </div>
            <h1 class="calc-hero-title">Kalkulator Sales 🧮</h1>
            <p class="calc-hero-subtitle">Hitung estimasi harga produk berdasarkan level customer (Dealer/Master Dealer), ongkir, & diskon tambahan.</p>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-calculator-fill"></i> Simulator Perhitungan Harga</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Pilih Produk (Database)</label>
                        <select id="calc_product" class="form-select form-select-lg">
                            <option value="0" data-msrp="0">-- Pilih Produk --</option>
                            <?php while ($p = $products_query->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>" data-msrp="<?php echo $p['msrp']; ?>">
                                    <?php echo htmlspecialchars($p['type']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label text-primary">Harga Dasar (MSRP)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text fw-bold bg-white">Rp</span>
                            <input type="number" id="manual_price" class="form-control fw-bold" value="0" style="font-size:20px; color:#2563EB;">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Level Customer</label>
                        <select id="calc_level" class="form-select">
                            <option value="user">User (Tanpa Diskon)</option>
                            <option value="dealer">Dealer</option>
                            <option value="master">Master Dealer</option>
                        </select>
                    </div>

                    <div id="div_disc_dealer" class="col-md-4 d-none">
                        <label class="form-label">Diskon Dealer (%)</label>
                        <div class="input-group">
                            <input type="number" id="disc_dealer_pct" class="form-control" value="<?php echo htmlspecialchars($config['dealer_discount'] ?? 0); ?>">
                            <span class="input-group-text bg-white">%</span>
                        </div>
                    </div>

                    <div id="div_disc_master" class="col-md-4 d-none">
                        <label class="form-label">Diskon Master (%)</label>
                        <div class="input-group">
                            <input type="number" id="disc_master_pct" class="form-control" value="<?php echo htmlspecialchars($config['master_dealer_discount'] ?? 0); ?>">
                            <span class="input-group-text bg-white">%</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Quantity</label>
                        <input type="number" id="calc_qty" class="form-control" value="1" min="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ongkir (Rp)</label>
                        <input type="number" id="calc_ongkir" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Diskon Tambahan</label>
                        <div class="input-group">
                            <input type="number" id="calc_extra" class="form-control" value="0">
                            <select id="extra_type" class="form-select" style="max-width: 80px;">
                                <option value="rp">Rp</option>
                                <option value="pct">%</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="result-box-v2 mt-4">
                    <div class="row mb-2" style="font-size:14px; opacity:0.85;">
                        <div class="col-7">Harga Satuan (Setelah Level):</div>
                        <div class="col-5 text-end fw-bold" id="res_unit_price">Rp 0</div>
                    </div>
                    <div class="row mb-2" style="font-size:14px; opacity:0.85;">
                        <div class="col-7">Subtotal Produk:</div>
                        <div class="col-5 text-end fw-bold" id="res_subtotal">Rp 0</div>
                    </div>
                    <div class="row mb-2 text-emerald" style="color:#34D399; font-size:14px;">
                        <div class="col-7">Ongkir (+):</div>
                        <div class="col-5 text-end fw-bold" id="res_ongkir">Rp 0</div>
                    </div>
                    <div class="row mb-3 text-rose" style="color:#FCA5A5; font-size:14px;">
                        <div class="col-7">Diskon Tambahan (-):</div>
                        <div class="col-5 text-end fw-bold" id="res_extra_label">Rp 0</div>
                    </div>
                    <hr style="border-color: rgba(255,255,255,0.2);">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="fw-bold mb-0 text-white" style="font-family:'Plus Jakarta Sans', sans-serif;">TOTAL AKHIR</h4>
                        <h2 class="fw-extrabold mb-0" style="color:#60A5FA; font-family:'Plus Jakarta Sans', sans-serif; font-size:32px;" id="res_total">Rp 0</h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function calculate() {
    const price = parseFloat(document.getElementById('manual_price').value) || 0;
    const level = document.getElementById('calc_level').value;
    const dPct = parseFloat(document.getElementById('disc_dealer_pct').value) || 0;
    const mPct = parseFloat(document.getElementById('disc_master_pct').value) || 0;
    const qty = parseInt(document.getElementById('calc_qty').value) || 0;
    const ongkir = parseFloat(document.getElementById('calc_ongkir').value) || 0;
    
    const extraVal = parseFloat(document.getElementById('calc_extra').value) || 0;
    const extraType = document.getElementById('extra_type').value;

    let unitAfterLevel = price;
    
    document.getElementById('div_disc_dealer').classList.toggle('d-none', level === 'user');
    document.getElementById('div_disc_master').classList.toggle('d-none', level !== 'master');

    if (level === 'dealer') {
        unitAfterLevel = price * (1 - (dPct / 100));
    } else if (level === 'master') {
        let priceAfterDealer = price * (1 - (dPct / 100));
        unitAfterLevel = priceAfterDealer * (1 - (mPct / 100));
    }

    const subtotal = unitAfterLevel * qty;
    
    let extraAmount = 0;
    if (extraType === 'rp') {
        extraAmount = extraVal;
    } else {
        extraAmount = subtotal * (extraVal / 100);
    }

    const total = subtotal + ongkir - extraAmount;

    const fmt = n => "Rp " + new Intl.NumberFormat('id-ID').format(Math.round(n));
    document.getElementById('res_unit_price').innerText = fmt(unitAfterLevel);
    document.getElementById('res_subtotal').innerText = fmt(subtotal);
    document.getElementById('res_ongkir').innerText = fmt(ongkir);
    document.getElementById('res_extra_label').innerText = fmt(extraAmount);
    document.getElementById('res_total').innerText = fmt(total);
}

document.getElementById('calc_product').addEventListener('change', function() {
    document.getElementById('manual_price').value = this.options[this.selectedIndex].getAttribute('data-msrp');
    calculate();
});

document.querySelectorAll('input, select').forEach(el => el.addEventListener('input', calculate));
calculate();
</script>

<?php require_once 'includes/footer.php'; ?>