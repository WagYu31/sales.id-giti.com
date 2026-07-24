<?php
$page_title = "Kalkulator Marketplace";
require_once 'includes/db.php';

// --- AJAX HANDLER (BACKGROUND PROCESS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    ob_clean(); 
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'add_fee') {
        $platform = $_POST['new_platform'];
        if (isset($_POST['platform_input_type']) && $_POST['platform_input_type'] === 'new' && !empty($_POST['new_platform_text'])) {
            $platform = $_POST['new_platform_text'];
        }
        
        $label = $_POST['new_label'] ?? '';
        $type = $_POST['new_type'] ?? 'percent';
        $val = (float)($_POST['new_value'] ?? 0);
        $is_tax = isset($_POST['new_is_tax']) ? 1 : 0;

        if (!empty($platform) && !empty($label)) {
            $stmt = $conn->prepare("INSERT INTO marketplace_fees (platform, fee_label, fee_type, fee_value, is_tax_on_affiliate) VALUES (?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssdi", $platform, $label, $type, $val, $is_tax);
                if ($stmt->execute()) {
                    $new_id = $stmt->insert_id;
                    echo json_encode([
                        'status' => 'success', 
                        'data' => [
                            'id' => $new_id,
                            'platform' => $platform,
                            'fee_label' => $label,
                            'fee_type' => $type,
                            'fee_value' => $val,
                            'is_tax_on_affiliate' => $is_tax
                        ]
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan ke database: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database prepare error']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Nama Platform dan Label Biaya wajib diisi']);
        }
        exit;
    }
}

// --- NORMAL POST HANDLER (Update & Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update' && isset($_POST['fees'])) {
        foreach ($_POST['fees'] as $id => $data) {
            $id = (int)$id;
            $val = (float)$data['value'];
            $is_tax = isset($data['is_tax_on_affiliate']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE marketplace_fees SET fee_value = ?, is_tax_on_affiliate = ? WHERE id = ?");
            $stmt->bind_param("dii", $val, $is_tax, $id);
            $stmt->execute();
            $stmt->close();
        }
    } 
    elseif ($_POST['action'] === 'delete' && isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM marketplace_fees WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    echo "<script>window.location.href = window.location.href;</script>";
    exit;
}

require_once 'includes/header.php';

$fees = [];
$last_update = "";
$platform_options = [];

$result = $conn->query("SELECT * FROM marketplace_fees ORDER BY platform ASC, id ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fees[$row['platform']][] = $row;
        if (!in_array($row['platform'], $platform_options)) {
            $platform_options[] = $row['platform'];
        }
        if ($row['updated_at'] > $last_update) {
            $last_update = $row['updated_at'];
        }
    }
}
$json_fees = json_encode($fees);
?>

<style>
.net-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E3A5F 50%, #2563EB 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: #FFFFFF;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px -10px rgba(37, 99, 235, 0.4);
}

.net-hero::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 250px; height: 250px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.net-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    letter-spacing: -0.5px;
}

.net-hero-subtitle {
    font-size: 14px;
    color: rgba(226, 232, 240, 0.85);
    margin: 0;
    max-width: 600px;
}

.input-nominal { 
    font-weight: 700; 
    color: #0F172A; 
    font-size: 16px;
}

.table-fees td { vertical-align: middle; }

.net-received-card {
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    border-radius: 16px;
    padding: 24px;
    color: #FFF;
    box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);
}
</style>

<!-- Hero Header -->
<div class="net-hero">
    <div class="d-flex flex-wrap justify-content-between align-items-center position-relative" style="z-index:2;">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px; color:rgba(147,197,253,0.9); font-weight:600;">
                <a href="customer_management.php" style="color:inherit; text-decoration:none;">Dashboard</a>
                <span>›</span>
                <span>Kalkulator Net Sales</span>
            </div>
            <h1 class="net-hero-title">Kalkulator Net Sales Marketplace 🛒</h1>
            <p class="net-hero-subtitle">Hitung estimasi hasil penjualan bersih marketplace (Shopee, Tokopedia, TikTok Ads, dll) setelah dipotong komisi & biaya admin.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <button class="btn btn-outline-light border-2 fw-bold" data-bs-toggle="modal" data-bs-target="#settingsModal">
                <i class="bi bi-gear-fill me-1"></i> Pengaturan Biaya
            </button>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Input Card -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Input Transaksi</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="platformSelect" class="form-label">Pilih Platform Marketplace</label>
                    <select class="form-select form-select-lg" id="platformSelect">
                        <option value="" selected disabled>-- Pilih Platform --</option>
                        <?php foreach ($platform_options as $p): ?>
                            <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="sellingPrice" class="form-label">Harga Jual Barang (Rp)</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white text-primary fw-bold">Rp</span>
                        <input type="text" class="form-control input-nominal" id="sellingPrice" placeholder="0" onkeyup="formatRupiah(this)">
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="voucherCost" class="form-label small">Voucher Diskon (Penjual)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted small">Rp</span>
                            <input type="text" class="form-control input-nominal" id="voucherCost" placeholder="0" onkeyup="formatRupiah(this)">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="cashbackCost" class="form-label small">Biaya Cashback (Penjual)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted small">Rp</span>
                            <input type="text" class="form-control input-nominal" id="cashbackCost" placeholder="0" onkeyup="formatRupiah(this)">
                        </div>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button class="btn btn-primary btn-lg shadow-sm" onclick="calculate()">
                        <i class="bi bi-calculator-fill me-1"></i> Hitung Estimasi Bersih
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Calculation Results Card -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> Rincian Perhitungan</h5>
            </div>
            <div class="card-body">
                <div id="emptyState" class="text-center py-5 text-muted">
                    <i class="bi bi-arrow-left-circle-fill display-3 text-primary opacity-25"></i>
                    <h5 class="mt-3 text-dark fw-bold">Belum Ada Perhitungan</h5>
                    <p class="small text-muted mb-0">Pilih platform dan masukkan harga jual di panel sebelah kiri.</p>
                </div>

                <div id="resultState" style="display: none;">
                    <div class="alert alert-primary py-2 px-3 mb-3 d-flex justify-content-between align-items-center small rounded-3">
                        <span>Harga Produk: <strong id="displayPrice">Rp 0</strong></span>
                        <span><i class="bi bi-arrow-right"></i></span>
                        <span>Dasar Hitung (Setelah Voucher): <strong id="displayBasePrice">Rp 0</strong></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-dark-header">
                                <tr>
                                    <th>Komponen Biaya</th>
                                    <th class="text-center">Rumus</th>
                                    <th class="text-end">Nominal</th>
                                </tr>
                            </thead>
                            <tbody id="manualDeductionBody"></tbody>
                            <tbody id="feesTableBody" class="border-top-0"></tbody>
                            <tfoot class="table-light fw-bold border-top">
                                <tr>
                                    <td colspan="2">Total Potongan</td>
                                    <td class="text-end text-danger fw-bold" id="totalDeduction" style="font-size:16px;">Rp 0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="net-received-card mt-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase mb-1 opacity-85 fw-bold" style="letter-spacing:0.5px; font-size:13px;">Estimasi Diterima Bersih</h6>
                                <small class="opacity-75">Net Revenue Marketplace</small>
                            </div>
                            <h2 class="fw-extrabold m-0" style="font-family:'Plus Jakarta Sans', sans-serif; font-size:32px;" id="netAmount">Rp 0</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Settings -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:20px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:#0F172A; color:#FFF;">
                <h5 class="modal-title fw-bold"><i class="bi bi-gear-fill me-2"></i>Pengaturan Master Biaya Marketplace</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-bold"><i class="bi bi-list-stars text-primary me-2"></i>Daftar Biaya Aktif</span>
                                <small class="text-muted">Update: <?php echo $last_update ? date('d M Y', strtotime($last_update)) : '-'; ?></small>
                            </div>
                            <div class="card-body p-0">
                                <form method="POST" id="updateForm">
                                    <input type="hidden" name="action" value="update">
                                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                                        <table class="table table-hover align-middle mb-0 text-nowrap table-fees" id="feesTable">
                                            <thead class="table-dark-header sticky-top">
                                                <tr>
                                                    <th>Platform</th>
                                                    <th>Label Biaya</th>
                                                    <th style="width: 140px;">Nilai</th>
                                                    <th style="width: 100px;">Satuan</th>
                                                    <th class="text-center">Opsi</th>
                                                    <th class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($fees as $platform => $items): ?>
                                                    <?php foreach ($items as $item): ?>
                                                        <tr id="row-<?php echo $item['id']; ?>">
                                                            <td class="small fw-bold text-dark"><?php echo htmlspecialchars($platform); ?></td>
                                                            <td><?php echo htmlspecialchars($item['fee_label']); ?></td>
                                                            <td>
                                                                <input type="number" step="0.01" name="fees[<?php echo $item['id']; ?>][value]" class="form-control form-control-sm text-end" value="<?php echo $item['fee_value']; ?>" required>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-secondary"><?php echo $item['fee_type'] == 'percent' ? 'Persen (%)' : 'Rupiah'; ?></span>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if($item['fee_type'] == 'percent'): ?>
                                                                    <div class="form-check d-inline-block" title="Centang jika ini adalah Pajak yang dihitung dari nilai Affiliate Fee">
                                                                        <input class="form-check-input" type="checkbox" name="fees[<?php echo $item['id']; ?>][is_tax_on_affiliate]" value="1" <?php if($item['is_tax_on_affiliate']) echo 'checked'; ?>>
                                                                        <label class="form-check-label small text-muted">Tax Aff</label>
                                                                    </div>
                                                                <?php else: ?>
                                                                    -
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFee(<?php echo $item['id']; ?>)"><i class="bi bi-trash-fill"></i></button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="p-3 border-top text-end bg-white">
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill"></i> Simpan Perubahan Nilai</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Biaya Baru</div>
                            <div class="card-body">
                                <form id="addFeeForm">
                                    <input type="hidden" name="ajax_action" value="add_fee">
                                    <div class="mb-3">
                                        <label class="form-label">Platform</label>
                                        <select class="form-select mb-2" name="new_platform" id="newPlatformSelect" onchange="togglePlatformInput()">
                                            <?php foreach ($platform_options as $p): ?>
                                                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
                                            <?php endforeach; ?>
                                            <option value="new_entry">+ Buat Platform Baru</option>
                                        </select>
                                        <input type="text" name="new_platform_text" id="newPlatformText" class="form-control d-none" placeholder="Nama Platform Baru...">
                                        <input type="hidden" name="platform_input_type" id="platformInputType" value="existing">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nama Biaya</label>
                                        <input type="text" name="new_label" class="form-control" placeholder="Contoh: Admin Fee" required>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label">Tipe</label>
                                            <select name="new_type" class="form-select" id="newTypeSelect">
                                                <option value="percent">Persentase (%)</option>
                                                <option value="fixed">Tetap (Rp)</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Nilai</label>
                                            <input type="number" step="0.01" name="new_value" class="form-control" required placeholder="0">
                                        </div>
                                    </div>
                                    <div class="mb-3 form-check" id="newTaxCheckDiv">
                                        <input type="checkbox" class="form-check-input" name="new_is_tax" id="newIsTax">
                                        <label class="form-check-label small" for="newIsTax">Potongan ini adalah Pajak dari Affiliate?</label>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary" id="btnAddSubmit">
                                            <span id="btnAddText"><i class="bi bi-plus-circle-fill"></i> Tambahkan</span>
                                            <span id="btnAddSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                    </div>
                                    <div id="addMsg" class="mt-2"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="deleteIdInput">
</form>

<?php require_once 'includes/footer.php'; ?>

<script>
    let feesData = <?php echo $json_fees; ?>;

    // --- FORM HANDLING: ADD FEE ---
    document.getElementById('addFeeForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btnText = document.getElementById('btnAddText');
        const btnSpinner = document.getElementById('btnAddSpinner');
        const btnSubmit = document.getElementById('btnAddSubmit');
        const msgDiv = document.getElementById('addMsg');
        
        btnText.classList.add('d-none');
        btnSpinner.classList.remove('d-none');
        btnSubmit.disabled = true;
        msgDiv.innerHTML = '';

        const formData = new FormData(this);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) 
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Server Error (Not JSON): ' + text.substring(0, 100));
            }
        })
        .then(res => {
            if (res.status === 'success') {
                const data = res.data;
                
                // 1. Update Global Data Variable
                if (!feesData[data.platform]) {
                    feesData[data.platform] = [];
                    addOptionToSelect('platformSelect', data.platform);
                    addOptionToSelect('newPlatformSelect', data.platform);
                }
                feesData[data.platform].push(data);

                // 2. Add Row to Table
                addFeeToTable(data);

                // 3. Reset Form
                document.getElementById('addFeeForm').reset();
                togglePlatformInput(); 
                
                // 4. Success Message
                msgDiv.innerHTML = '<div class="alert alert-success py-1 small">Berhasil ditambahkan!</div>';
                setTimeout(() => { msgDiv.innerHTML = ''; }, 3000);

            } else {
                msgDiv.innerHTML = '<div class="alert alert-danger py-1 small">'+res.message+'</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            msgDiv.innerHTML = '<div class="alert alert-danger py-1 small">Terjadi kesalahan: ' + error.message + '</div>';
        })
        .finally(() => {
            btnText.classList.remove('d-none');
            btnSpinner.classList.add('d-none');
            btnSubmit.disabled = false;
        });
    });

    function addOptionToSelect(selectId, value) {
        const select = document.getElementById(selectId);
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value === value) return;
        }
        
        const option = document.createElement('option');
        option.value = value;
        option.text = value;
        
        if (selectId === 'newPlatformSelect') {
            const lastOption = select.options[select.options.length - 1];
            select.add(option, lastOption);
        } else {
            select.add(option);
        }
    }

    function addFeeToTable(item) {
        const tbody = document.querySelector('#feesTable tbody');
        const tr = document.createElement('tr');
        tr.id = 'row-' + item.id;
        
        const typeLabel = item.fee_type === 'percent' ? 'Persen (%)' : 'Rupiah';
        const taxChecked = item.is_tax_on_affiliate == 1 ? 'checked' : '';
        const taxInput = item.fee_type === 'percent' 
            ? `<div class="form-check d-inline-block"><input class="form-check-input" type="checkbox" name="fees[${item.id}][is_tax_on_affiliate]" value="1" ${taxChecked}><label class="form-check-label small text-muted">Tax Aff</label></div>`
            : '-';

        tr.innerHTML = `
            <td class="small fw-bold text-muted">${item.platform}</td>
            <td>${item.fee_label}</td>
            <td><input type="number" step="0.01" name="fees[${item.id}][value]" class="form-control form-control-sm text-end" value="${item.fee_value}" required></td>
            <td><span class="badge bg-secondary">${typeLabel}</span></td>
            <td class="text-center">${taxInput}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFee(${item.id})"><i class="bi bi-trash-fill"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
        
        const tableContainer = document.querySelector('.table-responsive');
        if(tableContainer) tableContainer.scrollTop = tableContainer.scrollHeight;
    }

    function formatNumber(n) {
        return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function formatRupiah(element) {
        let val = element.value;
        element.value = formatNumber(val);
    }

    function parseNominal(str) {
        if(!str) return 0;
        return parseFloat(str.replace(/\./g, '')) || 0;
    }

    function formatCurrency(number) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(number);
    }

    function togglePlatformInput() {
        const select = document.getElementById('newPlatformSelect');
        const textInput = document.getElementById('newPlatformText');
        const typeInput = document.getElementById('platformInputType');
        
        if (select.value === 'new_entry') {
            textInput.classList.remove('d-none');
            textInput.required = true;
            typeInput.value = 'new';
        } else {
            textInput.classList.add('d-none');
            textInput.value = ''; 
            textInput.required = false;
            typeInput.value = 'existing';
        }
    }

    function deleteFee(id) {
        if(confirm('Apakah Anda yakin ingin menghapus biaya ini dari sistem?')) {
            document.getElementById('deleteIdInput').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    function calculate() {
        const platform = document.getElementById('platformSelect').value;
        const priceStr = document.getElementById('sellingPrice').value;
        const voucherStr = document.getElementById('voucherCost').value;
        const cashbackStr = document.getElementById('cashbackCost').value;

        const price = parseNominal(priceStr);
        const voucher = parseNominal(voucherStr);
        const cashback = parseNominal(cashbackStr);

        const resultDiv = document.getElementById('resultState');
        const emptyDiv = document.getElementById('emptyState');
        const tbodyFees = document.getElementById('feesTableBody');
        const tbodyManual = document.getElementById('manualDeductionBody');
        const totalDedEl = document.getElementById('totalDeduction');
        const netEl = document.getElementById('netAmount');
        const displayPriceEl = document.getElementById('displayPrice');
        const displayBasePriceEl = document.getElementById('displayBasePrice');

        if (!platform || price <= 0) {
            alert("Mohon pilih platform dan masukkan harga jual yang valid.");
            return;
        }

        let calculationBase = price - voucher; 
        
        displayPriceEl.innerText = formatCurrency(price);
        displayBasePriceEl.innerText = formatCurrency(calculationBase);

        let totalDeduction = 0;
        let htmlFees = '';
        let htmlManual = '';
        
        const platformFees = feesData[platform] || [];
        let affiliateFeeAmount = 0;

        if (voucher > 0) {
            totalDeduction += voucher;
            htmlManual += `<tr>
                <td class="text-primary fw-bold">Voucher Diskon</td>
                <td class="text-center text-muted small">Input Manual</td>
                <td class="text-end text-danger">-${formatCurrency(voucher)}</td>
            </tr>`;
        }

        if (cashback > 0) {
            totalDeduction += cashback;
            htmlManual += `<tr>
                <td class="text-primary fw-bold">Biaya Cashback</td>
                <td class="text-center text-muted small">Input Manual</td>
                <td class="text-end text-danger">-${formatCurrency(cashback)}</td>
            </tr>`;
        }

        platformFees.forEach(fee => {
            let nominal = 0;
            let formula = '';
            let feeVal = parseFloat(fee.fee_value);

            if (fee.fee_type === 'fixed') {
                nominal = feeVal;
                formula = formatCurrency(feeVal);
            } else {
                if (fee.is_tax_on_affiliate == 1) {
                    nominal = affiliateFeeAmount * (feeVal / 100);
                    formula = `${feeVal}% dari Affiliate Fee`;
                } else {
                    nominal = calculationBase * (feeVal / 100);
                    formula = `${feeVal}% x Rp ${formatNumber(calculationBase.toString())}`;
                }
            }

            if (fee.fee_label.toLowerCase().includes('affiliate') && fee.fee_label.toLowerCase().includes('komisi') && fee.is_tax_on_affiliate == 0) {
                affiliateFeeAmount = nominal;
            }

            totalDeduction += nominal;

            htmlFees += `<tr>
                <td>${fee.fee_label}</td>
                <td class="text-center text-muted small">${formula}</td>
                <td class="text-end text-danger">-${formatCurrency(nominal)}</td>
            </tr>`;
        });

        const netAmount = price - totalDeduction;

        tbodyFees.innerHTML = htmlFees;
        tbodyManual.innerHTML = htmlManual;
        totalDedEl.innerHTML = `-${formatCurrency(totalDeduction)}`;
        netEl.innerHTML = formatCurrency(netAmount);

        emptyDiv.style.display = 'none';
        resultDiv.style.display = 'block';
    }
</script>