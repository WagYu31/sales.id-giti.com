<?php
/**
 * WIDGET TRACKER REALTIME KOMPETISI BONUS SULTAN LOEWIX - RP 4.000.000,-
 * Kategori A: Customer Baru Terbanyak (Rp 2.000.000,-)
 * Kategori B: Reaktivasi Customer Lama Belanja Kembali Terbanyak (Rp 2.000.000,-)
 */

// 1. Fetch Top Sales Kategori A: Customer Baru Terbanyak (Bulan Ini)
$sql_kat_a = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT c.id) AS total_customer_baru,
        COUNT(DISTINCT fu.id) AS total_fu_baru
    FROM sales s
    JOIN customers c ON c.sales_id = s.id AND c.deleted_at IS NULL
    LEFT JOIN follow_ups fu ON fu.customer_id = c.id AND fu.deleted_at IS NULL
    WHERE c.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
      AND (s.role = 'sales' OR s.role = 'superadmin')
    GROUP BY s.id, s.nama_lengkap
    HAVING total_customer_baru > 0
    ORDER BY total_customer_baru DESC, total_fu_baru DESC
    LIMIT 5
";
$res_kat_a = $conn->query($sql_kat_a);
$list_kat_a = [];
if ($res_kat_a) {
    while ($r = $res_kat_a->fetch_assoc()) {
        $list_kat_a[] = $r;
    }
}

// 2. Fetch Top Sales Kategori B: Reaktivasi Customer Lama Belanja Kembali (Bulan Ini)
$sql_kat_b = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT fu.customer_id) AS total_customer_reaktivasi,
        COUNT(fu.id) AS total_transaksi_reaktivasi
    FROM sales s
    JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL
    JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE fu.tgl_follow_up >= DATE_FORMAT(NOW(), '%Y-%m-01')
      AND fu.no_inv IS NOT NULL AND fu.no_inv != ''
      AND c.created_at < DATE_FORMAT(NOW(), '%Y-%m-01')
      AND (s.role = 'sales' OR s.role = 'superadmin')
    GROUP BY s.id, s.nama_lengkap
    HAVING total_customer_reaktivasi > 0
    ORDER BY total_customer_reaktivasi DESC, total_transaksi_reaktivasi DESC
    LIMIT 5
";
$res_kat_b = $conn->query($sql_kat_b);
$list_kat_b = [];
if ($res_kat_b) {
    while ($r = $res_kat_b->fetch_assoc()) {
        $list_kat_b[] = $r;
    }
}

$top1_a = $list_kat_a[0] ?? null;
$max_val_a = $top1_a ? max(1, (int)$top1_a['total_customer_baru']) : 1;

$top1_b = $list_kat_b[0] ?? null;
$max_val_b = $top1_b ? max(1, (int)$top1_b['total_customer_reaktivasi']) : 1;
?>

<!-- 3D SULTAN BONUS COMPETITION TRACKER WIDGET -->
<style>
@keyframes pulseGoldGlow {
    0%, 100% { box-shadow: 0 0 20px rgba(245, 158, 11, 0.35), inset 0 0 15px rgba(254, 240, 138, 0.4); }
    50% { box-shadow: 0 0 35px rgba(245, 158, 11, 0.65), inset 0 0 25px rgba(254, 240, 138, 0.7); }
}

@keyframes floatCashBadge {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-4px) rotate(3deg); }
}

.bonus-competition-card {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    border-radius: 26px;
    padding: 32px 36px;
    margin-bottom: 34px;
    position: relative;
    border: 2px solid #F59E0B !important;
    animation: pulseGoldGlow 4s infinite ease-in-out;
    color: #FFFFFF;
    overflow: hidden;
}

.bonus-competition-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, #F59E0B 0%, #10B981 50%, #3B82F6 100%);
}

.bonus-kat-box {
    background: rgba(255, 255, 255, 0.05);
    border: 1.5px solid rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(12px);
    border-radius: 20px;
    padding: 24px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.3s ease;
}

.bonus-kat-box:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-4px);
    border-color: rgba(245, 158, 11, 0.5);
}

.cash-prize-badge {
    background: linear-gradient(135deg, #10B981 0%, #047857 100%);
    color: #FFFFFF;
    font-size: 13px;
    font-weight: 800;
    padding: 6px 16px;
    border-radius: 20px;
    border: 1px solid #34D399;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
    animation: floatCashBadge 3s infinite ease-in-out;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.rank-row-item {
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 14px;
    padding: 12px 16px;
    margin-bottom: 10px;
    transition: all 0.2s ease;
}

.rank-row-item:hover {
    background: rgba(30, 41, 59, 0.9);
    border-color: rgba(56, 189, 248, 0.4);
}

.rank-pill {
    width: 28px; height: 28px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
    flex-shrink: 0;
}
.rank-pill.gold { background: linear-gradient(135deg, #F59E0B, #D97706); color: #FFF; }
.rank-pill.silver { background: linear-gradient(135deg, #94A3B8, #64748B); color: #FFF; }
.rank-pill.bronze { background: linear-gradient(135deg, #D97706, #B45309); color: #FFF; }
.rank-pill.normal { background: rgba(255, 255, 255, 0.1); color: #94A3B8; }
</style>

<div class="bonus-competition-card">
    <!-- Header Banner -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div style="font-size: 38px; filter: drop-shadow(0 4px 10px rgba(245, 158, 11, 0.5));">
                🎁
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-1" style="font-size: 11px; letter-spacing: 0.5px;">PROGRAM BONUS SALES SULTAN</span>
                    <span class="badge bg-emerald bg-opacity-20 text-success border border-success rounded-pill px-2.5 py-0.5" style="font-size: 11px;">TOTAL RP 4.000.000,-</span>
                </div>
                <h4 class="mb-0 fw-bold text-white mt-1" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.4px;">
                    Kompetisi Sales Loewix Bulan Ini 🔥
                </h4>
            </div>
        </div>
        <div class="text-end">
            <div class="cash-prize-badge">
                💵 BONUS RP 2.000.000,- / KATEGORI
            </div>
        </div>
    </div>

    <!-- 2 Main Categories Grid -->
    <div class="row g-4 align-items-stretch">
        <!-- KATEGORI A: Customer Baru Terbanyak -->
        <div class="col-lg-6 col-12">
            <div class="bonus-kat-box" style="border-top: 4px solid #10B981 !important;">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-white mb-0 d-flex align-items-center gap-2" style="font-size: 16px;">
                            🚀 KATEGORI A: Customer Baru Terbanyak
                        </h6>
                        <span class="badge bg-success bg-opacity-20 text-success border border-success rounded-pill px-2.5 py-1" style="font-size: 11.5px; font-weight: 800;">
                            💰 BONUS RP 2.000.000,-
                        </span>
                    </div>
                    <p class="text-white-50 mb-3" style="font-size: 12.5px;">Perolehan akumulasi pencarian & akuisisi Customer Baru terbanyak bulan ini.</p>

                    <!-- Realtime Leaderboard List Category A -->
                    <div class="rank-list-holder">
                        <?php if (!empty($list_kat_a)): ?>
                            <?php foreach ($list_kat_a as $i => $item): ?>
                                <?php
                                $rClass = 'normal';
                                $rIcon = '#' . ($i + 1);
                                if ($i === 0) { $rClass = 'gold'; $rIcon = '🥇'; }
                                elseif ($i === 1) { $rClass = 'silver'; $rIcon = '🥈'; }
                                elseif ($i === 2) { $rClass = 'bronze'; $rIcon = '🥉'; }
                                $pct = round(($item['total_customer_baru'] / $max_val_a) * 100);
                                ?>
                                <div class="rank-row-item">
                                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="rank-pill <?php echo $rClass; ?>"><?php echo $rIcon; ?></div>
                                            <span class="fw-bold text-white" style="font-size: 14px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                                <?php echo htmlspecialchars($item['nama_sales']); ?>
                                            </span>
                                        </div>
                                        <span class="fw-bold text-success font-monospace" style="font-size: 15px;">
                                            <?php echo number_format($item['total_customer_baru'], 0, ',', '.'); ?> <small class="text-white-50 fs-7">Cust Baru</small>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 5px; background: rgba(255,255,255,0.1); border-radius: 10px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo max(8, $pct); ?>%; border-radius: 10px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-white-50 bg-dark bg-opacity-40 rounded-3" style="font-size: 13px;">
                                🚀 Belum ada data customer baru bulan ini. Ayo jadi yang pertama mendapatkan Customer Baru!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- KATEGORI B: Reaktivasi Customer Lama Belanja Kembali Terbanyak -->
        <div class="col-lg-6 col-12">
            <div class="bonus-kat-box" style="border-top: 4px solid #F59E0B !important;">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-white mb-0 d-flex align-items-center gap-2" style="font-size: 16px;">
                            🔥 KATEGORI B: Reaktivasi Customer Lama
                        </h6>
                        <span class="badge bg-warning bg-opacity-20 text-warning border border-warning rounded-pill px-2.5 py-1" style="font-size: 11.5px; font-weight: 800;">
                            💰 BONUS RP 2.000.000,-
                        </span>
                    </div>
                    <p class="text-white-50 mb-3" style="font-size: 12.5px;">Follow Up & Membangunkan Customer Lama untuk Repeat Order / Belanja Kembali terbanyak.</p>

                    <!-- Realtime Leaderboard List Category B -->
                    <div class="rank-list-holder">
                        <?php if (!empty($list_kat_b)): ?>
                            <?php foreach ($list_kat_b as $i => $item): ?>
                                <?php
                                $rClass = 'normal';
                                $rIcon = '#' . ($i + 1);
                                if ($i === 0) { $rClass = 'gold'; $rIcon = '🥇'; }
                                elseif ($i === 1) { $rClass = 'silver'; $rIcon = '🥈'; }
                                elseif ($i === 2) { $rClass = 'bronze'; $rIcon = '🥉'; }
                                $pct = round(($item['total_customer_reaktivasi'] / $max_val_b) * 100);
                                ?>
                                <div class="rank-row-item">
                                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                                        <div class="d-flex align-items-center gap-2.5">
                                            <div class="rank-pill <?php echo $rClass; ?>"><?php echo $rIcon; ?></div>
                                            <span class="fw-bold text-white" style="font-size: 14px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                                <?php echo htmlspecialchars($item['nama_sales']); ?>
                                            </span>
                                        </div>
                                        <span class="fw-bold text-warning font-monospace" style="font-size: 15px;">
                                            <?php echo number_format($item['total_customer_reaktivasi'], 0, ',', '.'); ?> <small class="text-white-50 fs-7">Cust Belanja</small>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 5px; background: rgba(255,255,255,0.1); border-radius: 10px;">
                                        <div class="progress-bar bg-warning" style="width: <?php echo max(8, $pct); ?>%; border-radius: 10px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-white-50 bg-dark bg-opacity-40 rounded-3" style="font-size: 13px;">
                                🔥 Belum ada data reaktivasi customer lama bulan ini. Ayo Follow Up customer lama kamu sekarang!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
