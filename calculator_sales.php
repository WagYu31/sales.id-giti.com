<?php
$page_title = 'Kalkulator Sales';
require_once 'includes/db.php';
require_once 'includes/header.php';

$settings_query = $conn->query("SELECT * FROM settings");
$config = [];
while ($row = $settings_query->fetch_assoc()) { $config[$row['setting_key']] = $row['setting_value']; }
$products_query = $conn->query("SELECT * FROM product_prices ORDER BY type ASC");
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-dark text-white py-3">
                <h4 class="mb-0 text-center"><i class="bi bi-calculator"></i> Kalkulator Sales</h4>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Pilih Produk (Database)</label>
                        <select id="calc_product" class="form-select form-select-lg">
                            <option value="0" data-msrp="0">-- Pilih Produk --</option>
                            <?php while ($p = $products_query->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>" data-msrp="<?php echo $p['msrp']; ?>">
                                    <?php echo $p['type']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-bold text-primary">Harga Dasar (MSRP)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="manual_price" class="form-control fw-bold" value="0">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Level Customer</label>
                        <select id="calc_level" class="form-select">
                            <option value="user">User (Tanpa Diskon)</option>
                            <option value="dealer">Dealer</option>
                            <option value="master">Master Dealer</option>
                        </select>
                    </div>

                    <div id="div_disc_dealer" class="col-md-4 d-none">
                        <label class="form-label fw-bold">Diskon Dealer (%)</label>
                        <div class="input-group">
                            <input type="number" id="disc_dealer_pct" class="form-control" value="<?php echo $config['dealer_discount']; ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div id="div_disc_master" class="col-md-4 d-none">
                        <label class="form-label fw-bold">Diskon Master (%)</label>
                        <div class="input-group">
                            <input type="number" id="disc_master_pct" class="form-control" value="<?php echo $config['master_dealer_discount']; ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Quantity</label>
                        <input type="number" id="calc_qty" class="form-control" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Ongkir (Rp)</label>
                        <input type="number" id="calc_ongkir" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Diskon Tambahan</label>
                        <div class="input-group">
                            <input type="number" id="calc_extra" class="form-control" value="0">
                            <select id="extra_type" class="form-select" style="max-width: 70px;">
                                <option value="rp">Rp</option>
                                <option value="pct">%</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-4 bg-light rounded-3 border">
                    <div class="row mb-2"><div class="col-7">Harga Satuan (Setelah Level):</div><div class="col-5 text-end fw-bold" id="res_unit_price">Rp 0</div></div>
                    <div class="row mb-2 text-muted"><div class="col-7">Subtotal Produk:</div><div class="col-5 text-end" id="res_subtotal">Rp 0</div></div>
                    <div class="row mb-2 text-success"><div class="col-7">Ongkir (+):</div><div class="col-5 text-end" id="res_ongkir">Rp 0</div></div>
                    <div class="row mb-3 text-danger"><div class="col-7">Diskon Tambahan (-):</div><div class="col-5 text-end" id="res_extra_label">Rp 0</div></div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="fw-bold mb-0 text-dark">TOTAL AKHIR</h3>
                        <h2 class="fw-bold mb-0 text-primary" id="res_total">Rp 0</h2>
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