<?php
/**
 * GRAFIK RANKING & LEADERBOARD SALES WIDGET - ATHLETICS RUNNING TRACK / SIRKUIT LARI EDITION
 * PERIODE: MULAI 1 AGUSTUS 2026 (SEMUA DARI 0)
 * METRIK UTAMA: TOTAL NOMINAL OMSET INVOICE (RP) DENGAN TARGET RP 200 JUTA
 */

if (!isset($conn)) {
    require_once __DIR__ . '/db.php';
}

// Parse month filter (Default: 3 Bulan - Agt-Okt 2026)
$selected_bulan = trim($_GET['periode_bulan'] ?? '8-10');

if ($selected_bulan === '8') {
    $where_fu = "(YEAR(fu.tgl_follow_up) = 2026 AND MONTH(fu.tgl_follow_up) = 8)";
    $where_c = "(YEAR(c.tgl_input) = 2026 AND MONTH(c.tgl_input) = 8)";
    $label_periode_ranking = 'Agt 2026';
    $full_label_ranking = 'Bulan 8 (Agustus 2026)';
} else if ($selected_bulan === '9') {
    $where_fu = "(YEAR(fu.tgl_follow_up) = 2026 AND MONTH(fu.tgl_follow_up) = 9)";
    $where_c = "(YEAR(c.tgl_input) = 2026 AND MONTH(c.tgl_input) = 9)";
    $label_periode_ranking = 'Sep 2026';
    $full_label_ranking = 'Bulan 9 (September 2026)';
} else if ($selected_bulan === '10') {
    $where_fu = "(YEAR(fu.tgl_follow_up) = 2026 AND MONTH(fu.tgl_follow_up) = 10)";
    $where_c = "(YEAR(c.tgl_input) = 2026 AND MONTH(c.tgl_input) = 10)";
    $label_periode_ranking = 'Okt 2026';
    $full_label_ranking = 'Bulan 10 (Oktober 2026)';
} else {
    $selected_bulan = '8-10';
    $where_fu = "(YEAR(fu.tgl_follow_up) = 2026 AND MONTH(fu.tgl_follow_up) IN (8, 9, 10))";
    $where_c = "(YEAR(c.tgl_input) = 2026 AND MONTH(c.tgl_input) IN (8, 9, 10))";
    $label_periode_ranking = '3 Bulan (Agt-Okt)';
    $full_label_ranking = 'Periode 3 Bulan (Agt - Okt 2026)';
}

$target_omset_finish = 200000000; // Rp 200.000.000,-

// Fetch Ranking Sales Data (Primary Focus: Follow Up & Penambahan Customer Baru)
$sql_ranking_all = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COUNT(DISTINCT CASE WHEN {$where_fu} THEN fu.id END) AS total_fu,
        COUNT(DISTINCT CASE WHEN {$where_c} THEN c.id END) AS total_cust_baru,
        COUNT(DISTINCT CASE WHEN {$where_fu} THEN fu.customer_id END) AS total_customer_fu,
        COUNT(DISTINCT CASE WHEN {$where_fu} AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.id END) AS total_inv_count,
        COALESCE(SUM(CASE WHEN {$where_fu} AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.nominal_invoice ELSE 0 END), 0) AS total_omset_invoice
    FROM sales s
    LEFT JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL
    LEFT JOIN customers c ON c.sales_id = s.id AND c.deleted_at IS NULL
    GROUP BY s.id, s.nama_lengkap
    ORDER BY (COUNT(DISTINCT CASE WHEN {$where_fu} THEN fu.id END) + COUNT(DISTINCT CASE WHEN {$where_c} THEN c.id END)) DESC
";

$res_ranking = $conn->query($sql_ranking_all);
$ranking_data = [];
if ($res_ranking) {
    while ($row = $res_ranking->fetch_assoc()) {
        $row['total_omset_invoice'] = (float)$row['total_omset_invoice'];
        $row['total_fu'] = (int)$row['total_fu'];
        $row['total_cust_baru'] = (int)$row['total_cust_baru'];
        $row['total_customer_fu'] = (int)$row['total_customer_fu'];
        $row['total_inv_count'] = (int)$row['total_inv_count'];
        $ranking_data[] = $row;
    }
} else {
    error_log("Sales Ranking Widget SQL Error: " . $conn->error);
}

$chart_labels = [];
$chart_omset_invoice = [];
$chart_total_fu = [];
$chart_cust_baru = [];
$chart_customer_fu = [];
$chart_inv_count = [];

foreach ($ranking_data as $rd) {
    $chart_labels[] = $rd['nama_sales'];
    $chart_omset_invoice[] = $rd['total_omset_invoice'];
    $chart_total_fu[] = $rd['total_fu'];
    $chart_cust_baru[] = $rd['total_cust_baru'];
    $chart_customer_fu[] = $rd['total_customer_fu'];
    $chart_inv_count[] = $rd['total_inv_count'];
}

$top1 = $ranking_data[0] ?? null;
$top2 = $ranking_data[1] ?? null;
$top3 = $ranking_data[2] ?? null;
$total_sales_count = count($ranking_data);
?>

<!-- 3D SPATIAL & ATHLETICS RUNNING TRACK / SIRKUIT LARI EDITION -->
<style>
@keyframes float3DMedal {
    0%, 100% { transform: translateY(0px) rotate(0deg) scale(1); }
    50% { transform: translateY(-5px) rotate(4deg) scale(1.08); }
}

@keyframes pulseGlowGold {
    0%, 100% { box-shadow: 0 10px 25px -5px rgba(245, 158, 11, 0.3); }
    50% { box-shadow: 0 18px 38px -4px rgba(245, 158, 11, 0.55); }
}

@keyframes pulseGlowSilver {
    0%, 100% { box-shadow: 0 10px 25px -5px rgba(148, 163, 184, 0.25); }
    50% { box-shadow: 0 16px 32px -4px rgba(148, 163, 184, 0.45); }
}

@keyframes pulseGlowBronze {
    0%, 100% { box-shadow: 0 10px 25px -5px rgba(217, 119, 6, 0.25); }
    50% { box-shadow: 0 16px 32px -4px rgba(217, 119, 6, 0.45); }
}

@keyframes borderShimmerGold {
    0%, 100% { border-color: #FCD34D; }
    50% { border-color: #F59E0B; }
}

@keyframes popMetricAnim {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); color: #2563EB; }
    100% { transform: scale(1); }
}

/* ATHLETICS RUNNING TRACK / SIRKUIT LARI 17 AGUSTUS KEMERDEKAAN RI 🇮🇩 STYLES */
.chart-scroll-wrapper {
    position: relative;
    max-height: 370px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-top: 28px;
    padding-right: 6px;
    background: linear-gradient(180deg, #881337 0%, #991B1B 35%, #B91C1C 70%, #7F1D1D 100%);
    border-radius: 22px;
    border: 3px solid #DC2626;
    outline: 2px solid #F59E0B;
    box-shadow: inset 0 16px 36px rgba(0, 0, 0, 0.65), 0 16px 36px rgba(220, 38, 38, 0.45);
    perspective: 1000px;
    transform-style: preserve-3d;
}

/* === 3D FLYING BIRDS & SKY LAYER === */
.track-3d-birds-layer {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 90px;
    pointer-events: none;
    z-index: 4;
    overflow: hidden;
}

/* === REALISTIC SVG VECTOR BIRDS IN FLIGHT (7-BIRD FLOCK SQUADRON) === */
.track-3d-birds-layer {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 110px;
    pointer-events: none;
    z-index: 4;
    overflow: hidden;
}

.real-bird-unit {
    position: absolute;
    filter: drop-shadow(0 8px 14px rgba(0,0,0,0.55));
    animation-timing-function: cubic-bezier(0.37, 0, 0.63, 1);
    animation-iteration-count: infinite;
}

/* Varied SVG Wing Morphing Animations */
.svg-real-bird .left-wing {
    transform-origin: 50px 30px;
    animation: wingFlapLeft 0.32s ease-in-out infinite alternate;
}
.svg-real-bird .right-wing {
    transform-origin: 50px 30px;
    animation: wingFlapRight 0.32s ease-in-out infinite alternate;
}

.bird-fast .left-wing { animation-duration: 0.25s; }
.bird-fast .right-wing { animation-duration: 0.25s; }
.bird-slow .left-wing { animation-duration: 0.42s; }
.bird-slow .right-wing { animation-duration: 0.42s; }

@keyframes wingFlapLeft {
    0%   { transform: rotate(0deg) scaleY(1); }
    100% { transform: rotate(44deg) scaleY(0.4); }
}

@keyframes wingFlapRight {
    0%   { transform: rotate(0deg) scaleY(1); }
    100% { transform: rotate(-44deg) scaleY(0.4); }
}

/* 7-Bird Flock Trajectories & Positions */
/* Bird 1: Lead Eagle */
.bird-lead {
    width: 44px; height: 28px;
    top: 15px;
    animation: flightSwoop1 10.5s cubic-bezier(0.37, 0, 0.63, 1) infinite;
    animation-delay: 0s;
}

/* Bird 2: Wingman Top Right */
.bird-v1 {
    width: 36px; height: 22px;
    top: 5px;
    opacity: 0.95;
    animation: flightSwoop2 12s cubic-bezier(0.37, 0, 0.63, 1) infinite;
    animation-delay: 0.8s;
}

/* Bird 3: Wingman Mid Left */
.bird-v2 {
    width: 38px; height: 24px;
    top: 32px;
    opacity: 0.95;
    animation: flightSwoop3 11s cubic-bezier(0.37, 0, 0.63, 1) infinite;
    animation-delay: 1.6s;
}

/* Bird 4: Flock Center High */
.bird-v3 {
    width: 28px; height: 18px;
    top: 10px;
    opacity: 0.85;
    animation: flightSwoop1 13.5s cubic-bezier(0.37, 0, 0.63, 1) infinite;
    animation-delay: 3.2s;
}

/* Bird 5: Flock Mid Swooper */
.bird-v4 {
    width: 40px; height: 25px;
    top: 40px;
    animation: flightSwoop2 10s cubic-bezier(0.37, 0, 0.63, 1) infinite;
    animation-delay: 4.8s;
}

/* Bird 6: Flock Distance 1 */
.bird-v5 {
    width: 22px; height: 14px;
    top: 8px;
    opacity: 0.75;
    animation: flightSwoop3 15s cubic-bezier(0.37, 0, 0.63, 1) infinite;
    animation-delay: 6.4s;
}

/* Bird 7: Flock Distance 2 */
.bird-v6 {
    width: 20px; height: 13px;
    top: 28px;
    opacity: 0.7;
    animation: flightSwoop1 16s cubic-bezier(0.37, 0, 0.63, 1) infinite;
    animation-delay: 8.0s;
}

/* Real Aerodynamic Swooping & Pitching Keyframes */
@keyframes flightSwoop1 {
    0% {
        left: -12%;
        top: 20px;
        transform: rotate(-12deg) scale(0.9);
    }
    25% {
        top: 4px;
        transform: rotate(-18deg) scale(1.1);
    }
    50% {
        top: 38px;
        transform: rotate(10deg) scale(1.2);
    }
    75% {
        top: 10px;
        transform: rotate(-8deg) scale(1.05);
    }
    100% {
        left: 112%;
        top: 22px;
        transform: rotate(6deg) scale(0.9);
    }
}

@keyframes flightSwoop2 {
    0% {
        left: -10%;
        top: 35px;
        transform: rotate(-8deg);
    }
    35% {
        top: 8px;
        transform: rotate(-15deg);
    }
    65% {
        top: 44px;
        transform: rotate(12deg);
    }
    100% {
        left: 110%;
        top: 25px;
        transform: rotate(4deg);
    }
}

@keyframes flightSwoop3 {
    0% {
        left: -10%;
        top: 8px;
        transform: rotate(-6deg);
    }
    40% {
        top: 32px;
        transform: rotate(10deg);
    }
    75% {
        top: 5px;
        transform: rotate(-12deg);
    }
    100% {
        left: 110%;
        top: 14px;
        transform: rotate(2deg);
    }
}

/* === STADIUM 3D TREES LAYER (NEAT SKYLINE HORIZON FOREST) === */
.stadium-trees-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    z-index: 1;
    overflow: hidden;
}

.tree-3d-pine {
    position: absolute;
    background-image: url('assets/tree_3d_pine.png?v=<?= time() ?>');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center bottom;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.4));
    transform-origin: bottom center;
    animation: tree3dSway 4.2s ease-in-out infinite alternate;
}

.tree-3d-oak {
    position: absolute;
    background-image: url('assets/tree_3d_oak.png?v=<?= time() ?>');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center bottom;
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.4));
    transform-origin: bottom center;
    animation: tree3dSway 4.8s ease-in-out infinite alternate;
}

/* Staggered 3D Skyline Positions Across Top Horizon */
.tree-p1 { left: 1.5%; top: 6px; width: 34px; height: 42px; opacity: 0.85; animation-delay: 0s; }
.tree-o2 { left: 5.0%; top: 12px; width: 28px; height: 34px; opacity: 0.72; animation-delay: 0.6s; }
.tree-p3 { left: 8.5%; top: 4px; width: 36px; height: 44px; opacity: 0.90; animation-delay: 1.2s; }
.tree-o4 { left: 12.0%; top: 14px; width: 26px; height: 32px; opacity: 0.68; animation-delay: 1.8s; }

.tree-o7 { right: 26.5%; top: 14px; width: 26px; height: 32px; opacity: 0.68; animation-delay: 0.9s; }
.tree-p8 { right: 22.5%; top: 8px; width: 32px; height: 40px; opacity: 0.80; animation-delay: 1.5s; }
.tree-o9 { right: 18.5%; top: 14px; width: 28px; height: 34px; opacity: 0.70; animation-delay: 0.3s; }
.tree-p10 { right: 14.5%; top: 4px; width: 36px; height: 44px; opacity: 0.90; animation-delay: 0.9s; }

@keyframes tree3dSway {
    0% { transform: rotate(-2deg) scaleX(1); }
    100% { transform: rotate(2.5deg) scaleX(1.03); }
}

/* === STADIUM 3D CENTER BILLBOARD BOARD === */
.stadium-center-billboard {
    position: absolute;
    top: 6px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.billboard-screen-body {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #0F172A 100%);
    border: 1.5px solid #F59E0B;
    border-radius: 10px;
    padding: 3px 10px;
    box-shadow: 0 4px 14px rgba(245, 158, 11, 0.35), 0 0 20px rgba(15, 23, 42, 0.8), inset 0 0 8px rgba(245, 158, 11, 0.2);
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    position: relative;
}

.billboard-led-lights {
    display: flex;
    gap: 2.5px;
}

.led-dot {
    width: 4.5px;
    height: 4.5px;
    border-radius: 50%;
}
.led-dot.red { background: #EF4444; box-shadow: 0 0 5px #EF4444; }
.led-dot.yellow { background: #F59E0B; box-shadow: 0 0 5px #F59E0B; }
.led-dot.green { background: #10B981; box-shadow: 0 0 5px #10B981; }

/* Billboard Animated Rotating Text Slider (Teks Berubah-Rubah Otomatis) */
.billboard-text-slider {
    position: relative;
    height: 18px;
    overflow: hidden;
    min-width: 210px;
}

.billboard-msg {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    white-space: nowrap;
    opacity: 0;
    transform: translateY(14px);
    animation: billboardMsgCycle 12s infinite ease-in-out;
}

.billboard-msg:nth-child(1) { animation-delay: 0s; }
.billboard-msg:nth-child(2) { animation-delay: 4s; }
.billboard-msg:nth-child(3) { animation-delay: 8s; }

@keyframes billboardMsgCycle {
    0% { opacity: 0; transform: translateY(14px); }
    5% { opacity: 1; transform: translateY(0px); }
    29% { opacity: 1; transform: translateY(0px); }
    33% { opacity: 0; transform: translateY(-14px); }
    100% { opacity: 0; transform: translateY(-14px); }
}

.billboard-icon { font-size: 12px; animation: iconPulse 1s infinite alternate; }
@keyframes iconPulse { 0% { transform: scale(1); } 100% { transform: scale(1.25); } }

.billboard-title {
    font-size: 11px;
    font-weight: 900;
    color: #FDE047;
    letter-spacing: 0.5px;
    text-shadow: 0 0 8px rgba(253, 224, 71, 0.6);
}

.billboard-title.alt2 {
    color: #38BDF8;
    text-shadow: 0 0 8px rgba(56, 189, 248, 0.6);
}

.billboard-title.alt3 {
    color: #4ADE80;
    text-shadow: 0 0 8px rgba(74, 222, 128, 0.6);
}

.billboard-flag { font-size: 11px; }

/* Support Legs */
.billboard-leg {
    position: absolute;
    bottom: -8px;
    width: 2.5px;
    height: 9px;
    background: linear-gradient(180deg, #64748B 0%, #334155 100%);
    border-radius: 1px;
}
.billboard-leg.leg-left { left: 16px; }
.billboard-leg.leg-right { right: 16px; }

/* 3D Floating Clouds */
.track-3d-clouds-layer {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 70px;
    pointer-events: none;
    z-index: 2;
    overflow: hidden;
    opacity: 0.3;
}

.cloud-item {
    position: absolute;
    font-size: 32px;
    filter: blur(1px);
    animation: cloudDrift 35s linear infinite;
}

.cloud-item:nth-child(1) { top: 2px; left: -15%; animation-duration: 38s; }
.cloud-item:nth-child(2) { top: 20px; left: -30%; animation-duration: 48s; animation-delay: 12s; }

@keyframes cloudDrift {
    0%   { transform: translateX(0); }
    100% { transform: translateX(120vw); }
}

.chart-scroll-wrapper::-webkit-scrollbar {
    width: 6px;
}
.chart-scroll-wrapper::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.25);
    border-radius: 10px;
}
.chart-scroll-wrapper::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #DC2626, #F59E0B);
    border-radius: 10px;
}

.track-bg-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    pointer-events: none;
    background-image: 
        repeating-linear-gradient(0deg, transparent, transparent 43px, rgba(255, 255, 255, 0.35) 44px),
        linear-gradient(90deg, rgba(255,255,255,0.08) 1px, transparent 1px);
    background-size: 100% 44px, 10% 100%;
    z-index: 1;
}

/* GARIS FINISH MERAH PUTIH 17 AGUSTUS 🇮🇩 */
.finish-line-banner {
    position: absolute;
    right: 12px;
    top: 10px;
    bottom: 10px;
    width: 24px;
    background: repeating-linear-gradient(
        45deg,
        #DC2626,
        #DC2626 8px,
        #FFFFFF 8px,
        #FFFFFF 16px
    );
    border-radius: 4px;
    box-shadow: 0 0 16px rgba(220, 38, 38, 0.9), 0 0 8px rgba(255, 255, 255, 0.9);
    z-index: 3;
    pointer-events: none;
}

.finish-badge-top {
    position: absolute;
    right: 4px;
    top: 6px;
    background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%);
    color: #FFFFFF;
    font-size: 10.5px;
    font-weight: 900;
    padding: 4px 10px;
    border-radius: 14px;
    border: 1.5px solid #FCD34D;
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.6);
    z-index: 4;
}

.cctv-mascot-runner {
    position: absolute;
    z-index: 10;
    cursor: pointer;
    transition: top 0.4s ease;
    filter: drop-shadow(0 4px 10px rgba(0,0,0,0.45));
}

.cctv-mascot-runner:hover {
    transform: scale(1.18);
    filter: drop-shadow(0 8px 18px rgba(0,0,0,0.55)) brightness(1.08);
}

.nailong-rank-tag {
    position: absolute;
    left: 46px;
    top: 8px;
    font-size: 13.5px;
    font-weight: 800;
    white-space: nowrap;
    filter: drop-shadow(0 2px 5px rgba(0,0,0,0.25));
}

/* === LOEWIX CCTV 3D MESH ROBOT RUNNER (HIGH BRIGHTNESS HD) === */
.loewix-runner-3d, .nailong-sprite {
    width: 54px;
    height: 66px;
    background-image: url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAzMAAAQACAYAAADV4gc7AAEAAElEQVR4nOy9CZhdV3Xlv+/4hho1WZZkyTOeMMHgAWyQwIEwBgjEBPpPaBI6OAmQgW6STrr7s93pL52QAb6EhECaJJh0hyGQYOZAACkQsA0YbIwtsGVbsi3Lmmp8053+39rnnPtuPZXmqUpaPyjXqzfee9+r0ll37b22CCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEIIIYQQQgghhBBCCCGEEEII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');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center bottom;
    display: block;
    filter: brightness(1.22) contrast(1.15) drop-shadow(0 6px 14px rgba(0, 0, 0, 0.45));
    transform-origin: center bottom;
    animation: loewixSprintRealistic3D 0.42s ease-in-out infinite;
}

/* 3D Olympic Forward Sprint Physics (Micro Ground Glide 2.5px, Stride Body Lean & Forward Acceleration) */
@keyframes loewixSprintRealistic3D {
    0% {
        transform: translateY(0px) rotate(-12deg) scale(1.03, 0.97);
    }
    50% {
        transform: translateY(-3px) rotate(-14deg) scale(0.97, 1.03);
    }
    100% {
        transform: translateY(0px) rotate(-12deg) scale(1.03, 0.97);
    }
}

/* Static single image fallback for victory */
.nailong-3d-gambar2 {
    width: 52px;
    height: 52px;
    object-fit: contain;
    display: block;
    filter: brightness(1.2) drop-shadow(0 4px 10px rgba(0, 0, 0, 0.4));
}

/* === RUNNING WRAPPER === */
.nailong-run-wrapper {
    position: relative;
    display: inline-block;
}

.nailong-run-bounce {
    display: inline-block;
}

/* === DUST CLOUD particles behind runner === */
.nailong-dust-cloud {
    position: absolute;
    bottom: 0px;
    left: -6px;
    width: 20px;
    height: 14px;
    pointer-events: none;
}

.dust-puff {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    animation: dustPuffAnim 0.5s infinite ease-out;
}

.dust-puff:nth-child(1) {
    width: 6px; height: 6px;
    bottom: 2px; left: 4px;
    animation-delay: 0s;
}
.dust-puff:nth-child(2) {
    width: 8px; height: 7px;
    bottom: 5px; left: 0px;
    animation-delay: 0.12s;
}
.dust-puff:nth-child(3) {
    width: 5px; height: 5px;
    bottom: 0px; left: 8px;
    animation-delay: 0.25s;
}

@keyframes dustPuffAnim {
    0%   { opacity: 0.7; transform: translate(0, 0) scale(0.5); }
    50%  { opacity: 0.4; transform: translate(-8px, -3px) scale(1); }
    100% { opacity: 0; transform: translate(-16px, -6px) scale(1.3); }
}

/* === SPEED LINES behind runner === */
.nailong-speed-lines-container {
    position: absolute;
    left: -14px;
    top: 30%;
    width: 14px;
    height: 16px;
    pointer-events: none;
}

.speed-line {
    position: absolute;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.7) 100%);
    border-radius: 1px;
    animation: speedLineMove 0.4s infinite linear;
}

.speed-line:nth-child(1) {
    width: 10px; top: 0px;
    animation-delay: 0s;
}
.speed-line:nth-child(2) {
    width: 12px; top: 6px;
    animation-delay: 0.13s;
}
.speed-line:nth-child(3) {
    width: 8px; top: 12px;
    animation-delay: 0.26s;
}

@keyframes speedLineMove {
    0%   { opacity: 0.8; transform: translateX(0); width: 10px; }
    50%  { opacity: 0.5; transform: translateX(-6px); width: 14px; }
    100% { opacity: 0; transform: translateX(-14px); width: 4px; }
}

/* === BENDERA MERAH PUTIH 🇮🇩 WAVING === */
.nailong-flag-pole {
    position: absolute;
    top: 8px;
    right: -8px;
    display: flex;
    align-items: flex-start;
    animation: nailongFlagWave 0.5s infinite alternate ease-in-out;
    transform-origin: bottom center;
}

@keyframes nailongFlagWave {
    0%   { transform: rotate(5deg); }
    100% { transform: rotate(20deg); }
}

/* === IKAT KEPALA MERAH PUTIH === */
.nailong-headband {
    position: absolute;
    top: 4px;
    left: 10px;
    right: 10px;
    height: 4.5px;
    background: linear-gradient(180deg, #DC2626 50%, #FFFFFF 50%);
    border-radius: 2px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    transform: rotate(-4deg);
}

/* === VICTORY CELEBRATION ANIMATION === */
.nailong-victory-float {
    animation: nailongVictoryFloat 1.6s infinite ease-in-out;
}

@keyframes nailongVictoryFloat {
    0%   { transform: translateY(0px) scale(1) rotate(0deg); }
    20%  { transform: translateY(-8px) scale(1.06) rotate(2deg); }
    40%  { transform: translateY(-5px) scale(1.03) rotate(-1deg); }
    60%  { transform: translateY(-10px) scale(1.08) rotate(3deg); }
    80%  { transform: translateY(-3px) scale(1.02) rotate(-2deg); }
    100% { transform: translateY(0px) scale(1) rotate(0deg); }
}

.nailong-trophy-icon {
    position: absolute;
    top: -14px;
    right: -12px;
    font-size: 22px;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4));
    animation: trophyBounce 0.7s infinite alternate ease-in-out;
}

@keyframes trophyBounce {
    0%   { transform: translateY(0px) scale(1) rotate(-5deg); }
    100% { transform: translateY(-6px) scale(1.15) rotate(8deg); }
}

.nailong-money-icon {
    position: absolute;
    bottom: -2px;
    left: -10px;
    font-size: 16px;
    filter: drop-shadow(0 2px 5px rgba(0,0,0,0.4));
    animation: moneyBagWiggle 1.2s infinite ease-in-out;
}

@keyframes moneyBagWiggle {
    0%, 100% { transform: rotate(0deg) scale(1); }
    25%  { transform: rotate(-8deg) scale(1.05); }
    75%  { transform: rotate(8deg) scale(1.05); }
}

/* === VICTORY SPARKLE PARTICLES === */
.nailong-sparkles {
    position: absolute;
    top: -5px;
    left: -5px;
    right: -5px;
    bottom: -5px;
    pointer-events: none;
}

.sparkle-dot {
    position: absolute;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    animation: sparkleFloat 1.4s infinite ease-in-out;
}

.sparkle-dot:nth-child(1) {
    background: #FCD34D; top: 0; left: 20%;
    animation-delay: 0s;
}
.sparkle-dot:nth-child(2) {
    background: #DC2626; top: 30%; right: 0;
    animation-delay: 0.3s;
}
.sparkle-dot:nth-child(3) {
    background: #FFFFFF; bottom: 10%; left: 10%;
    animation-delay: 0.6s;
}
.sparkle-dot:nth-child(4) {
    background: #F59E0B; top: 15%; left: 60%;
    animation-delay: 0.9s;
}

@keyframes sparkleFloat {
    0%   { opacity: 0; transform: scale(0) translateY(0); }
    30%  { opacity: 1; transform: scale(1.2) translateY(-4px); }
    70%  { opacity: 0.6; transform: scale(0.8) translateY(-8px); }
    100% { opacity: 0; transform: scale(0) translateY(-12px); }
}

/* === NAILONG JOY SPIN (click reaction) === */
@keyframes nailongJoySpin {
    0%   { transform: rotate(0deg) scale(1); }
    25%  { transform: rotate(15deg) scale(1.15); }
    50%  { transform: rotate(-10deg) scale(1.1); }
    75%  { transform: rotate(360deg) scale(1.2); }
    100% { transform: rotate(360deg) scale(1); }
}

.ranking-widget-card {
    background: #FFFFFF;
    border-radius: 26px;
    padding: 32px 36px;
    margin-bottom: 34px;
    position: relative;
    box-shadow: 0 22px 48px -12px rgba(15, 23, 42, 0.09), 0 4px 14px rgba(15, 23, 42, 0.02);
    border: 1.5px solid rgba(226, 232, 240, 0.95) !important;
    overflow: hidden;
    transform-style: preserve-3d;
    perspective: 1200px;
}

.ranking-widget-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4.5px;
    background: linear-gradient(90deg, #F59E0B 0%, #10B981 50%, #2563EB 100%);
    box-shadow: 0 2px 12px rgba(245, 158, 11, 0.4);
}

.podium-card {
    border-radius: 22px;
    padding: 22px 24px;
    position: relative;
    overflow: hidden;
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1.5px solid #E2E8F0;
    background: linear-gradient(180deg, #FFFFFF 0%, #F8FAFC 100%);
    transform-style: preserve-3d;
    perspective: 800px;
}

.podium-card:hover {
    transform: translateY(-6px) rotateX(3deg) rotateY(-2deg) scale(1.015);
    box-shadow: 0 22px 42px -8px rgba(15, 23, 42, 0.14), 0 8px 18px rgba(15, 23, 42, 0.06);
}

.podium-card.gold {
    animation: borderShimmerGold 3s infinite ease-in-out, pulseGlowGold 4s infinite ease-in-out;
    background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
}

.podium-card.silver {
    animation: pulseGlowSilver 4s infinite ease-in-out;
    background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
    border-color: #CBD5E1 !important;
}

.podium-card.bronze {
    animation: pulseGlowBronze 4s infinite ease-in-out;
    background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
    border-color: #FDBA74 !important;
}

.podium-rank-badge {
    width: 42px; height: 42px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 20px;
    color: #FFFFFF;
    box-shadow: 0 8px 18px rgba(0,0,0,0.18), inset 0 2px 4px rgba(255,255,255,0.4);
    animation: float3DMedal 3.5s ease-in-out infinite;
    flex-shrink: 0;
}

.podium-rank-badge.gold { background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); }
.podium-rank-badge.silver { background: linear-gradient(135deg, #94A3B8 0%, #64748B 100%); }
.podium-rank-badge.bronze { background: linear-gradient(135deg, #D97706 0%, #B45309 100%); }

.metric-btn {
    border: 1px solid #CBD5E1;
    background: #F8FAFC;
    color: #64748B;
    font-weight: 700;
    font-size: 12px;
    padding: 7px 16px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
}

.metric-btn:active {
    transform: scale(0.95) translateY(1px);
}

.metric-btn.active, .metric-btn:hover {
    background: linear-gradient(135deg, #2563EB, #1D4ED8);
    color: #FFFFFF;
    border-color: #2563EB;
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.top-limit-btn {
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 14px;
    border: 1px solid #CBD5E1;
    background: #FFFFFF;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
}

.top-limit-btn:active {
    transform: scale(0.94);
}

.top-limit-btn.active, .top-limit-btn:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
}

.btn-full-leaderboard {
    background: #FFFFFF;
    color: #0F172A;
    border: 1.5px solid #CBD5E1;
    font-weight: 700;
    font-size: 12.5px;
    padding: 8px 20px;
    border-radius: 20px;
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}

.btn-full-leaderboard:active {
    transform: scale(0.96);
}

.btn-full-leaderboard:hover {
    background: #0F172A;
    color: #FFFFFF;
    border-color: #0F172A;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.3);
}

.pop-metric {
    animation: popMetricAnim 0.4s ease-out;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="ranking-widget-card" id="ranking-widget-card">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="podium-rank-badge gold" style="width: 50px; height: 50px; font-size: 26px;">
                🏆
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-danger text-white fw-bold rounded-pill px-3 py-1" style="font-size: 11px; letter-spacing: 0.5px; border: 1px solid #FCD34D;">🇮🇩 EDISI SPESIAL KEMERDEKAAN</span>
                    <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-1" style="font-size: 11px; font-weight: 800;">🏁 Sirkuit Balap Loewix CCTV Robot 🤖 🇮🇩</span>
                </div>
                <h5 class="mb-0 fw-bold text-dark mt-1" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 19.5px; letter-spacing: -0.4px;">
                    PROGRAM PER SEMESTER DALAM 3 BULAN
                </h5>
            </div>
        </div>
        <div class="text-end">
            <div class="cash-prize-badge-3d" style="background: linear-gradient(135deg, #1E3A8A 0%, #2563EB 50%, #1D4ED8 100%); border-color: #93C5FD; box-shadow: 0 10px 25px -4px rgba(37, 99, 235, 0.5);">
                <span>💰</span> HADIAH UTAMA 2 JT INV TERBANYAK
            </div>
        </div>
    </div>

    <!-- Month Filter Bar (3 Bulan: Agt - Okt 2026) - In-Place Dynamic AJAX Filter -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 p-2 bg-light rounded-pill border gap-2 shadow-sm">
        <div class="d-flex align-items-center gap-2 px-3">
            <span class="fw-bold text-dark" style="font-size: 12.5px;">📅 Filter Periode Ranking:</span>
            <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold" id="badgeFullLabelRanking" style="font-size: 11px; border: 1px solid #93C5FD;"><?= $full_label_ranking ?></span>
        </div>
        <div class="d-flex align-items-center gap-1.5 flex-wrap">
            <button type="button" onclick="switchMonthPeriode('8-10', this)" class="btn btn-sm month-filter-btn <?= ($selected_bulan==='8-10')?'active-month btn-primary fw-bold text-white shadow-sm':'btn-outline-secondary' ?> rounded-pill px-3 py-1" style="font-size: 11.5px;">
                🏆 Total 3 Bulan (Agt-Okt)
            </button>
            <button type="button" onclick="switchMonthPeriode('8', this)" class="btn btn-sm month-filter-btn <?= ($selected_bulan==='8')?'active-month btn-danger fw-bold text-white shadow-sm':'btn-outline-secondary' ?> rounded-pill px-3 py-1" style="font-size: 11.5px;">
                📅 Agt (Bulan 8)
            </button>
            <button type="button" onclick="switchMonthPeriode('9', this)" class="btn btn-sm month-filter-btn <?= ($selected_bulan==='9')?'active-month btn-warning fw-bold text-dark shadow-sm':'btn-outline-secondary' ?> rounded-pill px-3 py-1" style="font-size: 11.5px;">
                📅 Sep (Bulan 9)
            </button>
            <button type="button" onclick="switchMonthPeriode('10', this)" class="btn btn-sm month-filter-btn <?= ($selected_bulan==='10')?'active-month btn-success fw-bold text-white shadow-sm':'btn-outline-secondary' ?> rounded-pill px-3 py-1" style="font-size: 11.5px;">
                📅 Okt (Bulan 10)
            </button>
        </div>
    </div>

    <!-- Filter Buttons Bar (Primary Focus: Follow Up & Penambahan Customer Baru) -->
    <div class="d-flex align-items-center gap-2 flex-wrap mb-4">
        <button type="button" class="metric-btn active" id="btnMetricTotal" onclick="switchChartMetric('total')">
            ⚡ Total Activity FU
        </button>
        <button type="button" class="metric-btn" id="btnMetricCustBaru" onclick="switchChartMetric('cust_baru')">
            🚀 Customer Baru Input
        </button>
        <button type="button" class="metric-btn" id="btnMetricCustomer" onclick="switchChartMetric('customer')">
            👥 Customer di-FU
        </button>
        <button type="button" class="metric-btn" id="btnMetricInvCount" onclick="switchChartMetric('inv_count')" style="border: 1.5px solid #3B82F6;">
            🧾 Invoice Terbanyak <span class="badge bg-danger text-white rounded-pill px-2 py-0.5 ms-1" style="font-size: 10px;">Hadiah 2 Juta 💰</span>
        </button>
        <button type="button" class="metric-btn" id="btnMetricOmset" onclick="switchChartMetric('omset')">
            💵 Invoice Sales (Rp)
        </button>
        <button type="button" class="btn-full-leaderboard ms-1" data-bs-toggle="modal" data-bs-target="#fullLeaderboardModal" id="btnFullLeaderboardText">
            📋 Full Leaderboard (<?php echo $total_sales_count; ?> Sales)
        </button>
    </div>

    <div class="row g-4 align-items-stretch">
        <!-- Left Side: Top 3 3D Spatial Podium Cards -->
        <div class="col-lg-5 col-12 d-flex flex-column gap-3">
            <!-- Top 1 Gold -->
            <?php if ($top1): ?>
            <?php $top1_omset = (float)$top1['total_omset_invoice']; ?>
            <div class="podium-card gold" id="podiumCard1">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="podium-rank-badge gold">🥇</div>
                        <div>
                            <span class="badge bg-warning text-dark fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10.5px; letter-spacing: 0.5px;">JUARA 1</span>
                            <h6 class="mb-0 fw-bold text-dark mt-0.5" id="podium1Name" style="font-size: 16.5px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top1['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold text-warning metric-val font-monospace" id="podium1Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;">Rp <?php echo number_format($top1_omset, 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium1Lbl" style="font-size: 11px;">Omset Invoice (<?= $label_periode_ranking ?>)</small>
                    </div>
                </div>
                <?php $pct_top1 = min(100, round(($top1_omset / $target_omset_finish) * 100, 1)); ?>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(245, 158, 11, 0.25);">
                    <div class="progress-bar bg-warning bg-gradient" id="podium1Progress" role="progressbar" style="width: <?php echo max(5, $pct_top1); ?>%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span id="podium1Activity">⚡ <?php echo $top1['total_fu']; ?> Activity</span>
                    <span id="podium1Cust">👥 <?php echo $top1['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 2 Silver -->
            <?php if ($top2): ?>
            <?php $top2_omset = (float)$top2['total_omset_invoice']; ?>
            <div class="podium-card silver" id="podiumCard2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="podium-rank-badge silver">🥈</div>
                        <div>
                            <span class="badge bg-secondary text-white fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10.5px; letter-spacing: 0.5px;">JUARA 2</span>
                            <h6 class="mb-0 fw-bold text-dark mt-0.5" id="podium2Name" style="font-size: 16px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top2['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold text-secondary metric-val font-monospace" id="podium2Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;">Rp <?php echo number_format($top2_omset, 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium2Lbl" style="font-size: 11px;">Omset Invoice (<?= $label_periode_ranking ?>)</small>
                    </div>
                </div>
                <?php $pct2 = min(100, round(($top2_omset / $target_omset_finish) * 100, 1)); ?>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(148, 163, 184, 0.25);">
                    <div class="progress-bar bg-secondary bg-gradient" id="podium2Progress" role="progressbar" style="width: <?php echo max(5, $pct2); ?>%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span id="podium2Activity">⚡ <?php echo $top2['total_fu']; ?> Activity</span>
                    <span id="podium2Cust">👥 <?php echo $top2['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 3 Bronze -->
            <?php if ($top3): ?>
            <?php $top3_omset = (float)$top3['total_omset_invoice']; ?>
            <div class="podium-card bronze" id="podiumCard3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="podium-rank-badge bronze">🥉</div>
                        <div>
                            <span class="badge bg-danger bg-opacity-75 text-white fw-bold rounded-pill px-2.5 py-0.5" style="font-size: 10.5px; letter-spacing: 0.5px;">JUARA 3</span>
                            <h6 class="mb-0 fw-bold text-dark mt-0.5" id="podium3Name" style="font-size: 16px; font-family: 'Plus Jakarta Sans', sans-serif;"><?php echo htmlspecialchars($top3['nama_sales']); ?></h6>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold text-danger metric-val font-monospace" id="podium3Val" style="font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1;">Rp <?php echo number_format($top3_omset, 0, ',', '.'); ?></div>
                        <small class="text-muted fw-semibold metric-lbl" id="podium3Lbl" style="font-size: 11px;">Omset Invoice (<?= $label_periode_ranking ?>)</small>
                    </div>
                </div>
                <?php $pct3 = min(100, round(($top3_omset / $target_omset_finish) * 100, 1)); ?>
                <div class="progress mt-2.5" style="height: 7px; border-radius: 10px; background: rgba(217, 119, 6, 0.25);">
                    <div class="progress-bar bg-warning bg-gradient" id="podium3Progress" role="progressbar" style="width: <?php echo max(5, $pct3); ?>%; border-radius: 10px; transition: width 1.2s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 text-muted" style="font-size: 12px;">
                    <span id="podium3Activity">⚡ <?php echo $top3['total_fu']; ?> Activity</span>
                    <span id="podium3Cust">👥 <?php echo $top3['total_customer_fu']; ?> Customer di-FU</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Side: Athletics Running Track Circuit Background & Chart -->
        <div class="col-lg-7 col-12">
            <div class="p-3.5 bg-light rounded-4 border h-100 d-flex flex-column justify-content-between shadow-sm position-relative">
                <div class="d-flex justify-content-between align-items-center mb-2 px-2 flex-wrap gap-2">
                    <span class="fw-bold text-dark d-flex align-items-center gap-1.5" style="font-size: 14px;" id="chartTitle">🏁 Sirkuit Lari Sales (Omset Invoice Rp - Target 200 Juta)</span>
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="text-muted fw-semibold" style="font-size: 11px;">Tampilkan:</span>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit(5, this)">Top 5</button>
                        <button type="button" class="top-limit-btn active" onclick="setTopLimit(10, this)">Top 10</button>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit(20, this)">Top 20</button>
                        <button type="button" class="top-limit-btn" onclick="setTopLimit('all', this)">Semua (<?php echo $total_sales_count; ?>)</button>
                    </div>
                </div>

                <!-- Athletics Track Circuit Wrapper with Checkered Finish Line -->
                <div class="chart-scroll-wrapper" id="chartScrollWrapper">
                    <!-- 3D Spatial Parallax Sky & Flying Birds Layer -->
                    <div class="track-3d-clouds-layer">
                        <div class="cloud-item">☁️</div>
                        <div class="cloud-item">☁️</div>
                    </div>
                    <div class="track-3d-birds-layer">
                        <!-- Bird 1: Lead Eagle -->
                        <div class="real-bird-unit bird-lead bird-fast">
                            <svg class="svg-real-bird" viewBox="0 0 100 60" width="100%" height="100%">
                                <path class="svg-wing left-wing" d="M 50,30 Q 28,2 2,28 Q 26,22 50,30" fill="#0F172A" />
                                <path class="svg-wing right-wing" d="M 50,30 Q 72,2 98,28 Q 74,22 50,30" fill="#1E293B" />
                                <path d="M 50,30 Q 42,33 34,31 Q 45,25 50,24 Q 55,25 66,31 Q 58,33 50,30" fill="#0F172A" />
                                <polygon points="34,31 20,37 26,31 22,25" fill="#0F172A" />
                            </svg>
                        </div>

                        <!-- Bird 2: Wingman Top Right -->
                        <div class="real-bird-unit bird-v1">
                            <svg class="svg-real-bird" viewBox="0 0 100 60" width="100%" height="100%">
                                <path class="svg-wing left-wing" d="M 50,30 Q 28,2 2,28 Q 26,22 50,30" fill="#1E293B" />
                                <path class="svg-wing right-wing" d="M 50,30 Q 72,2 98,28 Q 74,22 50,30" fill="#334155" />
                                <path d="M 50,30 Q 42,33 34,31 Q 45,25 50,24 Q 55,25 66,31 Q 58,33 50,30" fill="#1E293B" />
                                <polygon points="34,31 20,37 26,31 22,25" fill="#1E293B" />
                            </svg>
                        </div>

                        <!-- Bird 3: Wingman Mid Left -->
                        <div class="real-bird-unit bird-v2 bird-fast">
                            <svg class="svg-real-bird" viewBox="0 0 100 60" width="100%" height="100%">
                                <path class="svg-wing left-wing" d="M 50,30 Q 28,2 2,28 Q 26,22 50,30" fill="#0F172A" />
                                <path class="svg-wing right-wing" d="M 50,30 Q 72,2 98,28 Q 74,22 50,30" fill="#1E293B" />
                                <path d="M 50,30 Q 42,33 34,31 Q 45,25 50,24 Q 55,25 66,31 Q 58,33 50,30" fill="#0F172A" />
                                <polygon points="34,31 20,37 26,31 22,25" fill="#0F172A" />
                            </svg>
                        </div>

                        <!-- Bird 4: Flock Center High -->
                        <div class="real-bird-unit bird-v3 bird-slow">
                            <svg class="svg-real-bird" viewBox="0 0 100 60" width="100%" height="100%">
                                <path class="svg-wing left-wing" d="M 50,30 Q 28,2 2,28 Q 26,22 50,30" fill="#334155" />
                                <path class="svg-wing right-wing" d="M 50,30 Q 72,2 98,28 Q 74,22 50,30" fill="#475569" />
                                <path d="M 50,30 Q 42,33 34,31 Q 45,25 50,24 Q 55,25 66,31 Q 58,33 50,30" fill="#334155" />
                                <polygon points="34,31 20,37 26,31 22,25" fill="#334155" />
                            </svg>
                        </div>

                        <!-- Bird 5: Flock Mid Swooper -->
                        <div class="real-bird-unit bird-v4">
                            <svg class="svg-real-bird" viewBox="0 0 100 60" width="100%" height="100%">
                                <path class="svg-wing left-wing" d="M 50,30 Q 28,2 2,28 Q 26,22 50,30" fill="#1E293B" />
                                <path class="svg-wing right-wing" d="M 50,30 Q 72,2 98,28 Q 74,22 50,30" fill="#334155" />
                                <path d="M 50,30 Q 42,33 34,31 Q 45,25 50,24 Q 55,25 66,31 Q 58,33 50,30" fill="#1E293B" />
                                <polygon points="34,31 20,37 26,31 22,25" fill="#1E293B" />
                            </svg>
                        </div>

                        <!-- Bird 6: Flock Distance 1 -->
                        <div class="real-bird-unit bird-v5 bird-slow">
                            <svg class="svg-real-bird" viewBox="0 0 100 60" width="100%" height="100%">
                                <path class="svg-wing left-wing" d="M 50,30 Q 28,2 2,28 Q 26,22 50,30" fill="#475569" />
                                <path class="svg-wing right-wing" d="M 50,30 Q 72,2 98,28 Q 74,22 50,30" fill="#64748B" />
                                <path d="M 50,30 Q 42,33 34,31 Q 45,25 50,24 Q 55,25 66,31 Q 58,33 50,30" fill="#475569" />
                                <polygon points="34,31 20,37 26,31 22,25" fill="#475569" />
                            </svg>
                        </div>

                        <!-- Bird 7: Flock Distance 2 -->
                        <div class="real-bird-unit bird-v6">
                            <svg class="svg-real-bird" viewBox="0 0 100 60" width="100%" height="100%">
                                <path class="svg-wing left-wing" d="M 50,30 Q 28,2 2,28 Q 26,22 50,30" fill="#475569" />
                                <path class="svg-wing right-wing" d="M 50,30 Q 72,2 98,28 Q 74,22 50,30" fill="#64748B" />
                                <path d="M 50,30 Q 42,33 34,31 Q 45,25 50,24 Q 55,25 66,31 Q 58,33 50,30" fill="#475569" />
                                <polygon points="34,31 20,37 26,31 22,25" fill="#475569" />
                            </svg>
                        </div>
                    </div>

                    <!-- STADIUM 3D TREES & CENTER BILLBOARD LAYER -->
                    <div class="stadium-trees-overlay">
                        <!-- Skyline Left Cluster -->
                        <div class="tree-3d-pine tree-p1"></div>
                        <div class="tree-3d-oak tree-o2"></div>
                        <div class="tree-3d-pine tree-p3"></div>
                        <div class="tree-3d-oak tree-o4"></div>
                        <div class="tree-3d-pine tree-p5"></div>
                        <div class="tree-3d-oak tree-o6"></div>

                        <!-- Center Stadium 3D Billboard Board -->
                        <div class="stadium-center-billboard">
                            <div class="billboard-leg leg-left"></div>
                            <div class="billboard-leg leg-right"></div>
                            <div class="billboard-screen-body">
                                <div class="billboard-led-lights">
                                    <span class="led-dot red"></span>
                                    <span class="led-dot yellow"></span>
                                    <span class="led-dot green"></span>
                                </div>
                                <div class="billboard-text-slider">
                                    <div class="billboard-msg">
                                        <span class="billboard-icon">🔥</span>
                                        <span class="billboard-title">SIRKUIT JUARA LOEWIX</span>
                                        <span class="billboard-flag">🏆 🇮🇩</span>
                                    </div>
                                    <div class="billboard-msg">
                                        <span class="billboard-icon">⚡</span>
                                        <span class="billboard-title alt2">TOP SALES LOEWIX BULAN INI</span>
                                        <span class="billboard-flag">🌟 🥇</span>
                                    </div>
                                    <div class="billboard-msg">
                                        <span class="billboard-icon">🚀</span>
                                        <span class="billboard-title alt3">GASPOL TARGET 200 JUTA!</span>
                                        <span class="billboard-flag">💪 🇮🇩</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Skyline Right Cluster -->
                        <div class="tree-3d-oak tree-o7"></div>
                        <div class="tree-3d-pine tree-p8"></div>
                        <div class="tree-3d-oak tree-o9"></div>
                        <div class="tree-3d-pine tree-p10"></div>
                        <div class="tree-3d-oak tree-o11"></div>
                        <div class="tree-3d-pine tree-p12"></div>
                    </div>

                    <div class="track-bg-overlay"></div>
                    <div class="finish-line-banner"></div>
                    <div class="finish-badge-top">FINISH 🏁 🇮🇩</div>

                    <div style="position: relative; width: 100%; min-height: 320px;" id="chartCanvasContainer">
                        <canvas id="salesRankingChart"></canvas>
                        <!-- Interactive Animated CCTV Mascot Runners Overlay -->
                        <div id="cctvMascotOverlayHolder"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Full Leaderboard -->
<div class="modal fade" id="fullLeaderboardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header bg-gradient text-white" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 24px 24px 0 0; padding: 20px 28px;">
                <div class="d-flex align-items-center gap-2.5">
                    <div style="font-size: 24px;">🏆</div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" style="font-family: 'Plus Jakarta Sans', sans-serif;">Full Leaderboard Sales Representative</h5>
                        <small class="text-white-50">Periode: Mulai 1 Agustus 2026 (Semua Dari 0)</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3 text-secondary" style="font-size: 12px;">PERINGKAT</th>
                                <th class="px-3 py-3 text-secondary" style="font-size: 12px;">SALES REPRESENTATIVE</th>
                                <th class="px-3 py-3 text-secondary text-end" style="font-size: 12px;">OMSET INVOICE (RP)</th>
                                <th class="px-3 py-3 text-secondary text-end" style="font-size: 12px;">JUMLAH INVOICE</th>
                                <th class="px-3 py-3 text-secondary text-end" style="font-size: 12px;">TOTAL ACTIVITY</th>
                                <th class="px-3 py-3 text-secondary text-end" style="font-size: 12px;">CUST DI-FU</th>
                            </tr>
                        </thead>
                        <tbody id="modalLeaderboardTbody">
                            <?php foreach ($ranking_data as $idx => $s): ?>
                                <?php 
                                $rNum = $idx + 1;
                                $badgeCls = 'bg-light text-secondary border';
                                if ($rNum === 1) $badgeCls = 'bg-warning text-dark fw-bold';
                                elseif ($rNum === 2) $badgeCls = 'bg-secondary text-white fw-bold';
                                elseif ($rNum === 3) $badgeCls = 'bg-danger text-white fw-bold';
                                ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="badge <?php echo $badgeCls; ?> rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 12px;">
                                            <?php echo $rNum; ?>
                                        </span>
                                    </td>
                                    <td class="px-3">
                                        <div class="fw-bold text-dark" style="font-size: 14px; font-family: 'Plus Jakarta Sans', sans-serif;">
                                            <?php echo htmlspecialchars($s['nama_sales']); ?>
                                        </div>
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-success" style="font-size: 14px;">
                                        Rp <?php echo number_format((float)$s['total_omset_invoice'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-primary" style="font-size: 14px;">
                                        <?php echo number_format($s['total_inv_count'], 0, ',', '.'); ?> Inv
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-dark" style="font-size: 14px;">
                                        <?php echo number_format($s['total_fu'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end px-3 font-monospace fw-bold text-secondary" style="font-size: 14px;">
                                        <?php echo number_format($s['total_customer_fu'], 0, ',', '.'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2.5 px-4 justify-content-between">
                <span class="text-muted" style="font-size: 12.5px;">Data diperbarui secara realtime dari database</span>
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
let salesChartInstance = null;
let currentMetric = 'total';
let currentTopLimit = 10;
let currentMonthPeriode = '<?= $selected_bulan ?>';
let currentLabelPeriodeRanking = '<?= $label_periode_ranking ?>';
let rankingDataRaw = <?php echo json_encode($ranking_data); ?>;

let salesChartLabels = <?php echo json_encode($chart_labels); ?>;
let salesChartOmsetInvoice = <?php echo json_encode($chart_omset_invoice); ?>;
let salesChartTotalFU = <?php echo json_encode($chart_total_fu); ?>;
let salesChartCustBaru = <?php echo json_encode($chart_cust_baru); ?>;
let salesChartCustomerFU = <?php echo json_encode($chart_customer_fu); ?>;
let salesChartInvCount = <?php echo json_encode($chart_inv_count); ?>;

let top1Data = <?php echo json_encode($top1); ?>;
let top2Data = <?php echo json_encode($top2); ?>;
let top3Data = <?php echo json_encode($top3); ?>;

document.addEventListener("DOMContentLoaded", function() {
    renderScaledChart();
});

function getSortedDatasetByMetric(metricType) {
    let rawList = [...rankingDataRaw];

    if (metricType === 'total') {
        rawList.sort((a, b) => (parseInt(b.total_fu) || 0) - (parseInt(a.total_fu) || 0));
    } else if (metricType === 'cust_baru') {
        rawList.sort((a, b) => (parseInt(b.total_cust_baru) || 0) - (parseInt(a.total_cust_baru) || 0));
    } else if (metricType === 'customer') {
        rawList.sort((a, b) => (parseInt(b.total_customer_fu) || 0) - (parseInt(a.total_customer_fu) || 0));
    } else if (metricType === 'inv_count') {
        rawList.sort((a, b) => (parseInt(b.total_inv_count) || 0) - (parseInt(a.total_inv_count) || 0));
    } else if (metricType === 'omset') {
        rawList.sort((a, b) => (parseFloat(b.total_omset_invoice) || 0) - (parseFloat(a.total_omset_invoice) || 0));
    }

    let labels = [];
    let values = [];

    rawList.forEach(item => {
        labels.push(item.nama_sales);
        if (metricType === 'total') values.push(parseInt(item.total_fu) || 0);
        else if (metricType === 'cust_baru') values.push(parseInt(item.total_cust_baru) || 0);
        else if (metricType === 'customer') values.push(parseInt(item.total_customer_fu) || 0);
        else if (metricType === 'inv_count') values.push(parseInt(item.total_inv_count) || 0);
        else if (metricType === 'omset') values.push(parseFloat(item.total_omset_invoice) || 0);
    });

    return {
        sortedList: rawList,
        labels: labels,
        values: values,
        top1: rawList[0] || null,
        top2: rawList[1] || null,
        top3: rawList[2] || null
    };
}

function switchMonthPeriode(monthVal, btnEl) {
    if (currentMonthPeriode === monthVal && btnEl && btnEl.classList.contains('active-month')) return;
    currentMonthPeriode = monthVal;

    // Reset month filter button styles
    document.querySelectorAll('.month-filter-btn').forEach(btn => {
        btn.className = 'btn btn-sm month-filter-btn btn-outline-secondary rounded-pill px-3 py-1';
        btn.style.fontSize = '11.5px';
    });

    if (btnEl) {
        btnEl.classList.add('active-month');
        if (monthVal === '8-10') btnEl.className = 'btn btn-sm month-filter-btn active-month btn-primary fw-bold text-white shadow-sm rounded-pill px-3 py-1';
        else if (monthVal === '8') btnEl.className = 'btn btn-sm month-filter-btn active-month btn-danger fw-bold text-white shadow-sm rounded-pill px-3 py-1';
        else if (monthVal === '9') btnEl.className = 'btn btn-sm month-filter-btn active-month btn-warning fw-bold text-dark shadow-sm rounded-pill px-3 py-1';
        else if (monthVal === '10') btnEl.className = 'btn btn-sm month-filter-btn active-month btn-success fw-bold text-white shadow-sm rounded-pill px-3 py-1';
    }

    const cardEl = document.getElementById('ranking-widget-card');
    if (cardEl) {
        cardEl.style.transition = 'opacity 0.25s ease';
        cardEl.style.opacity = '0.55';
    }

    fetch('ajax_sales_ranking.php?periode_bulan=' + encodeURIComponent(monthVal))
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            rankingDataRaw = data.ranking_data || [];
            currentLabelPeriodeRanking = data.label_periode_ranking || '3 Bulan (Agt-Okt)';

            salesChartLabels = data.chart_labels || [];
            salesChartOmsetInvoice = data.chart_omset_invoice || [];
            salesChartTotalFU = data.chart_total_fu || [];
            salesChartCustBaru = data.chart_cust_baru || [];
            salesChartCustomerFU = data.chart_customer_fu || [];
            salesChartInvCount = data.chart_inv_count || [];

            const badgeFullLabel = document.getElementById('badgeFullLabelRanking');
            if (badgeFullLabel) badgeFullLabel.innerText = data.full_label_ranking;

            const btnFullLeaderboard = document.getElementById('btnFullLeaderboardText');
            if (btnFullLeaderboard && data.ranking_data) {
                btnFullLeaderboard.innerText = `📋 Full Leaderboard (${data.ranking_data.length} Sales)`;
            }

            switchChartMetric(currentMetric);
            updateModalLeaderboardDOM(data.ranking_data);

            if (cardEl) cardEl.style.opacity = '1';
        })
        .catch(err => {
            console.error('AJAX Month Filter Error:', err);
            if (cardEl) cardEl.style.opacity = '1';
        });
}

function updatePodiumCardsDOM(top1, top2, top3, labelPeriode) {
    top1Data = top1;
    top2Data = top2;
    top3Data = top3;

    function renderSinglePodium(prefix, data) {
        const nameEl = document.getElementById(prefix + 'Name');
        const valEl = document.getElementById(prefix + 'Val');
        const progressEl = document.getElementById(prefix + 'Progress');
        const actEl = document.getElementById(prefix + 'Activity');
        const custEl = document.getElementById(prefix + 'Cust');
        const lblEl = document.getElementById(prefix + 'Lbl');

        let metricLabelText = `Total Activity FU (${labelPeriode})`;
        if (currentMetric === 'total') metricLabelText = `Total Activity FU (${labelPeriode})`;
        else if (currentMetric === 'cust_baru') metricLabelText = `Cust Baru Input (${labelPeriode})`;
        else if (currentMetric === 'customer') metricLabelText = `Customer di-FU (${labelPeriode})`;
        else if (currentMetric === 'inv_count') metricLabelText = `Total Invoice (${labelPeriode})`;
        else if (currentMetric === 'omset') metricLabelText = `Omset Invoice (${labelPeriode})`;

        if (lblEl) lblEl.innerText = metricLabelText;

        if (data) {
            if (nameEl) nameEl.innerText = data.nama_sales;

            let valStr = '';
            let pct = 5;

            if (currentMetric === 'total') {
                const totalFu = parseInt(data.total_fu) || 0;
                valStr = `${new Intl.NumberFormat('id-ID').format(totalFu)} Activity`;
                pct = Math.min(100, Math.round((totalFu / 100) * 100));
            } else if (currentMetric === 'cust_baru') {
                const custBaru = parseInt(data.total_cust_baru) || 0;
                valStr = `${new Intl.NumberFormat('id-ID').format(custBaru)} Cust Baru`;
                pct = Math.min(100, Math.round((custBaru / 20) * 100));
            } else if (currentMetric === 'customer') {
                const custFu = parseInt(data.total_customer_fu) || 0;
                valStr = `${new Intl.NumberFormat('id-ID').format(custFu)} Cust di-FU`;
                pct = Math.min(100, Math.round((custFu / 50) * 100));
            } else if (currentMetric === 'inv_count') {
                const invCount = parseInt(data.total_inv_count) || 0;
                valStr = `${new Intl.NumberFormat('id-ID').format(invCount)} Invoice`;
                pct = Math.min(100, Math.round((invCount / 50) * 100));
            } else {
                const omset = parseFloat(data.total_omset_invoice) || 0;
                valStr = formatRupiahDisplay(omset);
                pct = Math.min(100, Math.round((omset / 200000000) * 100 * 10) / 10);
            }

            if (valEl) valEl.innerText = valStr;
            if (progressEl) progressEl.style.width = Math.max(5, pct) + '%';
            if (actEl) actEl.innerText = `⚡ ${data.total_fu || 0} Activity FU`;
            if (custEl) custEl.innerText = `🚀 ${data.total_cust_baru || 0} Cust Baru`;
        } else {
            if (nameEl) nameEl.innerText = '-';
            if (valEl) valEl.innerText = '0';
            if (progressEl) progressEl.style.width = '5%';
            if (actEl) actEl.innerText = '⚡ 0 Activity FU';
            if (custEl) custEl.innerText = '🚀 0 Cust Baru';
        }
    }

    renderSinglePodium('podium1', top1);
    renderSinglePodium('podium2', top2);
    renderSinglePodium('podium3', top3);
}

function updateModalLeaderboardDOM(rankingData) {
    const tbody = document.getElementById('modalLeaderboardTbody');
    if (!tbody || !rankingData) return;

    let html = '';
    rankingData.forEach((s, idx) => {
        const rNum = idx + 1;
        let badgeCls = 'bg-light text-secondary border';
        if (rNum === 1) badgeCls = 'bg-warning text-dark fw-bold';
        else if (rNum === 2) badgeCls = 'bg-secondary text-white fw-bold';
        else if (rNum === 3) badgeCls = 'bg-danger text-white fw-bold';

        const omsetFormatted = 'Rp ' + new Intl.NumberFormat('id-ID').format(s.total_omset_invoice);
        const invCount = new Intl.NumberFormat('id-ID').format(s.total_inv_count);
        const totalFu = new Intl.NumberFormat('id-ID').format(s.total_fu);
        const custBaru = new Intl.NumberFormat('id-ID').format(s.total_cust_baru || 0);

        html += `
        <tr>
            <td class="px-4 py-3">
                <span class="badge ${badgeCls} rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 12px;">
                    ${rNum}
                </span>
            </td>
            <td class="px-3">
                <div class="fw-bold text-dark" style="font-size: 14px; font-family: 'Plus Jakarta Sans', sans-serif;">
                    ${escapeHtml(s.nama_sales)}
                </div>
            </td>
            <td class="text-end px-3 font-monospace fw-bold text-dark" style="font-size: 14px;">
                ${totalFu} FU
            </td>
            <td class="text-end px-3 font-monospace fw-bold text-success" style="font-size: 14px;">
                ${custBaru} Baru
            </td>
            <td class="text-end px-3 font-monospace fw-bold text-primary" style="font-size: 14px;">
                ${invCount} Inv
            </td>
            <td class="text-end px-3 font-monospace fw-bold text-muted" style="font-size: 14px;">
                ${omsetFormatted}
            </td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function getActiveDatasetValues() {
    if (currentMetric === 'total') return { values: salesChartTotalFU || [], label: "Total Activity FU" };
    if (currentMetric === 'cust_baru') return { values: salesChartCustBaru || [], label: "Customer Baru Input" };
    if (currentMetric === 'customer') return { values: salesChartCustomerFU || [], label: "Customer di-FU" };
    if (currentMetric === 'inv_count') return { values: salesChartInvCount || [], label: "Jumlah Invoice" };
    return { values: salesChartOmsetInvoice || [], label: "Omset Invoice (Rp)" };
}

function formatRupiahDisplay(num) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
}

function renderScaledChart() {
    const { values, label } = getActiveDatasetValues();
    const activeValues = Array.isArray(values) ? values : [];
    
    let limit = currentTopLimit === 'all' ? activeValues.length : parseInt(currentTopLimit);
    let slicedLabels = (salesChartLabels || []).slice(0, limit);
    let slicedValues = activeValues.slice(0, limit);

    // Calculate dynamic height for canvas so bars and top Nailong head/speech bubble never get squished or clipped
    const container = document.getElementById('chartCanvasContainer');
    const barHeightPx = 44;
    const computedHeight = Math.max(320, slicedLabels.length * barHeightPx + 60);
    container.style.height = `${computedHeight}px`;

    const ctx = document.getElementById('salesRankingChart').getContext('2d');
    
    if (salesChartInstance) {
        salesChartInstance.destroy();
    }

    // Clear old Nailong overlay runners
    const holder = document.getElementById('cctvMascotOverlayHolder');
    if (holder) holder.innerHTML = '';

    // 3D Gold & Vibrant Track Bar Gradients
    const gradientGold = ctx.createLinearGradient(0, 0, 400, 0);
    gradientGold.addColorStop(0, '#F59E0B');
    gradientGold.addColorStop(1, '#FCD34D');

    const gradientBlue = ctx.createLinearGradient(0, 0, 400, 0);
    gradientBlue.addColorStop(0, '#3B82F6');
    gradientBlue.addColorStop(1, '#60A5FA');

    const gradientGreen = ctx.createLinearGradient(0, 0, 400, 0);
    gradientGreen.addColorStop(0, '#10B981');
    gradientGreen.addColorStop(1, '#34D399');

    const gradientSilver = ctx.createLinearGradient(0, 0, 400, 0);
    gradientSilver.addColorStop(0, '#94A3B8');
    gradientSilver.addColorStop(1, '#CBD5E1');

    const gradientBronze = ctx.createLinearGradient(0, 0, 400, 0);
    gradientBronze.addColorStop(0, '#F97316');
    gradientBronze.addColorStop(1, '#FB923C');

    // Compute scale max - Dynamic scale with headroom so bars and finish line look great
    const maxVal = Math.max(...slicedValues, 0);
    let scaleMax = 50;
    let stepSizeVal = 10;

    if (currentMetric === 'omset') {
        // Fixed scale to Target 200 Juta (or higher if a sales exceeds 200M)
        scaleMax = maxVal > 200000000 ? Math.ceil(maxVal / 50000000) * 50000000 : 200000000;
        stepSizeVal = 50000000; // 0, 50 Jt, 100 Jt, 150 Jt, 200 Jt
    } else {
        if (maxVal === 0) {
            scaleMax = 50;
            stepSizeVal = 10;
        } else {
            scaleMax = Math.ceil(maxVal * 1.15);
            stepSizeVal = Math.max(1, Math.ceil(scaleMax / 5));
        }
    }

    salesChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: slicedLabels,
            datasets: [{
                label: label,
                data: slicedValues,
                backgroundColor: [
                    gradientGold,
                    gradientSilver,
                    gradientBronze,
                    gradientBlue,
                    gradientGreen
                ],
                borderColor: [
                    '#D97706',
                    '#64748B',
                    '#C2410C',
                    '#1D4ED8',
                    '#047857'
                ],
                borderWidth: 1.5,
                borderRadius: 14,
                borderSkipped: false,
                barThickness: 18
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 68,
                    right: 65,
                    left: 10,
                    bottom: 10
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.95)',
                    titleFont: { family: 'Plus Jakarta Sans', size: 13, weight: 'bold' },
                    bodyFont: { family: 'Plus Jakarta Sans', size: 12.5 },
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            let val = context.raw;
                            if (currentMetric === 'omset') {
                                return ` 💰 Omset Invoice: ${formatRupiahDisplay(val)}`;
                            }
                            return ` ${context.dataset.label}: ${val}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    min: 0,
                    max: scaleMax,
                    stepSize: stepSizeVal,
                    grid: { color: 'rgba(255, 255, 255, 0.15)', lineWidth: 1 },
                    ticks: { 
                        color: '#F8FAFC', 
                        font: { family: 'Plus Jakarta Sans', size: 11.5, weight: '700' },
                        callback: function(val) {
                            if (currentMetric === 'omset') {
                                if (val === 0) return '0';
                                if (val >= 1000000) return (val / 1000000) + ' Jt';
                                return val;
                            }
                            return val;
                        }
                    },
                    border: { display: false }
                },
                y: {
                    grid: { display: false },
                    ticks: {
                        color: '#FFFFFF',
                        font: { family: 'Plus Jakarta Sans', size: 12.5, weight: '700' }
                    },
                    border: { display: false }
                }
            },
            animation: {
                duration: 2800,
                easing: 'linear',
                onProgress: function(animation) {
                    positionMascotRunners(this);
                },
                onComplete: function() {
                    positionMascotRunners(this);
                }
            }
        }
    });
}

function positionMascotRunners(chart) {
    const holder = document.getElementById('cctvMascotOverlayHolder');
    if (!holder) return;

    const meta = chart.getDatasetMeta(0);
    const xAxis = chart.scales.x;

    if (!meta || !meta.data || meta.data.length === 0) return;

    const firstBarY = meta.data[0] ? meta.data[0].y : null;
    if (firstBarY === null || isNaN(firstBarY) || firstBarY === undefined) return;

    const currentDatasetKey = `${currentMetric}_${currentMonthPeriode}_${currentTopLimit}_${meta.data.length}`;
    const prevDatasetKey = holder.getAttribute('data-key');
    const needRebuild = (prevDatasetKey !== currentDatasetKey) || (holder.children.length !== meta.data.length);

    if (needRebuild) {
        holder.innerHTML = '';
        holder.setAttribute('data-key', currentDatasetKey);
    }

    meta.data.forEach((bar, index) => {
        const value = chart.data.datasets[0].data[index];
        const labelName = chart.data.labels[index];
        const barY = bar.y;
        const barX = bar.x;

        let leftPos = Math.min(barX - 18, xAxis.right - 45);
        if (value === 0) {
            leftPos = xAxis.left + 5 + (index * 4);
        }
        const topPos = barY - 32;



        if (!needRebuild && holder.children[index]) {
            // Update position in-place without destroying DOM node (keeps CSS sprite running!)
            const runnerDiv = holder.children[index];
            runnerDiv.style.left = `${leftPos}px`;
            runnerDiv.style.top = `${topPos}px`;
        } else {
            // Create DOM element for runner
            const runnerDiv = document.createElement('div');
            runnerDiv.className = 'cctv-mascot-runner';
            runnerDiv.style.left = `${leftPos}px`;
            runnerDiv.style.top = `${topPos}px`;

            let isFinished = false;
            if (currentMetric === 'omset' && value >= 200000000) isFinished = true;
            else if (currentMetric === 'inv_count' && value >= 50) isFinished = true;
            else if (currentMetric === 'total' && value >= 100) isFinished = true;
            else if (currentMetric === 'customer' && value >= 50) isFinished = true;

            let medalBadge = '';
            if (index === 0) medalBadge = '🥇';
            else if (index === 1) medalBadge = '🥈';
            else if (index === 2) medalBadge = '🥉';

            runnerDiv.onclick = function() {
                triggerNailongJoySpin(runnerDiv, labelName, isFinished);
            };

            if (isFinished) {
                // VICTORY NAILONG 🏆 (FINISHED TARGET WITH CROWN & SPARKLES + SPRITE ANIMATION)
                runnerDiv.innerHTML = `
                    <div class="nailong-run-wrapper">
                        <span class="nailong-rank-tag" style="top: -26px; left: 50%; transform: translateX(-50%); font-size: 14px; background: linear-gradient(135deg, #F59E0B, #B45309); color: #FFF; border: 1.5px solid #FEF08A; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.5);">
                            ${medalBadge ? medalBadge + ' 👑' : '👑 🏆'}
                        </span>
                        <div class="nailong-run-bounce" style="position: relative;">
                            <div class="nailong-sparkles">
                                <div class="sparkle-dot"></div>
                                <div class="sparkle-dot"></div>
                                <div class="sparkle-dot"></div>
                                <div class="sparkle-dot"></div>
                            </div>
                            <div class="nailong-sprite"></div>
                            <div class="nailong-flag-pole">
                                <div style="width: 2px; height: 26px; background: #78350F; border-radius: 1px;"></div>
                                <div style="width: 14px; height: 9px; background: linear-gradient(180deg, #DC2626 50%, #FFFFFF 50%); border-radius: 1px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); border: 0.3px solid #CBD5E1;"></div>
                            </div>
                            <div class="nailong-dust-cloud">
                                <div class="dust-puff"></div>
                                <div class="dust-puff"></div>
                                <div class="dust-puff"></div>
                            </div>
                        </div>
                        <div class="nailong-ground-shadow"></div>
                    </div>
                `;
            } else {
                // RUNNING NAILONG 🏃 WITH SPRITE SHEET ANIMATION + BENDERA 🇮🇩
                runnerDiv.innerHTML = `
                    <div class="nailong-run-wrapper">
                        ${medalBadge ? `<span class="nailong-rank-tag" style="top: -24px; left: 50%; transform: translateX(-50%); font-size: 14px;">${medalBadge}</span>` : ''}
                        <div class="nailong-run-bounce" style="position: relative;">
                            <div class="nailong-speed-lines-container">
                                <div class="speed-line"></div>
                                <div class="speed-line"></div>
                                <div class="speed-line"></div>
                            </div>
                            <div class="nailong-sprite"></div>
                            <div class="nailong-flag-pole">
                                <div style="width: 2px; height: 26px; background: #78350F; border-radius: 1px;"></div>
                                <div style="width: 14px; height: 9px; background: linear-gradient(180deg, #DC2626 50%, #FFFFFF 50%); border-radius: 1px; box-shadow: 0 2px 5px rgba(0,0,0,0.3); border: 0.3px solid #CBD5E1;"></div>
                            </div>
                            <div class="nailong-dust-cloud">
                                <div class="dust-puff"></div>
                                <div class="dust-puff"></div>
                                <div class="dust-puff"></div>
                            </div>
                        </div>
                        <div class="nailong-ground-shadow"></div>
                    </div>
                `;
            }

            holder.appendChild(runnerDiv);
        }
    });
}

function triggerNailongJoySpin(element, salesName, isFinished) {
    element.style.animation = 'none';
    element.offsetHeight; // trigger reflow
    element.style.animation = 'nailongJoySpin 0.7s cubic-bezier(0.34, 1.56, 0.64, 1)';
    
    spawnFlyingPartyProps(element);
    showMotivationSpeechBubble(element, salesName, isFinished);
    playNailongCheerAudio(salesName);
}

function getSalesCallName(salesName) {
    if (!salesName) return 'Sales';
    const lower = salesName.toLowerCase().trim();
    if (lower.includes('edi suprianto') || lower.includes('suprianto') || lower === 'edi') {
        return 'Anto';
    }
    if (lower.includes('efrina panjaitan') || lower.includes('panjaitan') || lower.includes('efrina')) {
        return 'Rina';
    }
    return salesName.trim().split(' ')[0];
}

function playNailongCheerAudio(salesName) {
    const callName = getSalesCallName(salesName);

    // 1. Synthesize 17 Agustus Independence Day Marching Band Fanfare (Web Audio API)
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (AudioCtx) {
            const ctx = new AudioCtx();

            // Notes for 17 Agustus 1945 March:
            // "Tujuh belas agustus tahun empat lima... itulah hari kemerdekaan kita!"
            // G4 (392Hz), E4 (329.63Hz), C4 (261.63Hz), A4 (440Hz), F4 (349.23Hz), C5 (523.25Hz), E5 (659.25Hz)
            const marchMelody = [
                { f: 392.00, d: 0.12, t: 0.00 }, // Tu-
                { f: 392.00, d: 0.12, t: 0.14 }, // -juh
                { f: 329.63, d: 0.15, t: 0.28 }, // be-
                { f: 261.63, d: 0.22, t: 0.45 }, // -las
                { f: 392.00, d: 0.12, t: 0.70 }, // A-
                { f: 392.00, d: 0.12, t: 0.84 }, // -gus-
                { f: 329.63, d: 0.15, t: 0.98 }, // -tus
                { f: 261.63, d: 0.22, t: 1.15 }, // ta-
                { f: 392.00, d: 0.10, t: 1.40 }, // -hun
                { f: 440.00, d: 0.10, t: 1.52 }, // em-
                { f: 392.00, d: 0.10, t: 1.64 }, // -pat
                { f: 329.63, d: 0.10, t: 1.76 }, // li-
                { f: 349.23, d: 0.10, t: 1.88 }, // -ma
                { f: 392.00, d: 0.25, t: 2.00 }, // !!!
                { f: 523.25, d: 0.15, t: 2.30 }, // C5
                { f: 659.25, d: 0.35, t: 2.48 }  // E5 MERDEKA!
            ];

            marchMelody.forEach(n => {
                // Main Brass Trumpet (Sawtooth + Triangle mix)
                const osc1 = ctx.createOscillator();
                const osc2 = ctx.createOscillator();
                const gain = ctx.createGain();

                osc1.type = 'sawtooth';
                osc2.type = 'triangle';
                osc1.frequency.setValueAtTime(n.f, ctx.currentTime + n.t);
                osc2.frequency.setValueAtTime(n.f * 1.005, ctx.currentTime + n.t); // Subtle chorus

                gain.gain.setValueAtTime(0.28, ctx.currentTime + n.t);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + n.t + n.d);

                osc1.connect(gain);
                osc2.connect(gain);
                gain.connect(ctx.destination);

                osc1.start(ctx.currentTime + n.t);
                osc2.start(ctx.currentTime + n.t);
                osc1.stop(ctx.currentTime + n.t + n.d);
                osc2.stop(ctx.currentTime + n.t + n.d);
            });
        }
    } catch (e) {
        console.log("Web Audio API info:", e);
    }

    // 2. Web Speech Synthesis Indonesian Cheer Leader Voice ("Semangat Anto!")
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel(); // Cancel any ongoing speech

        const cheerText = `Semangat ${callName}! Semangat ${callName}! Semangat ${callName}! Merdeka! Ayo ${callName}, pasti Juara Loewix!`;
        const utterance = new SpeechSynthesisUtterance(cheerText);
        utterance.lang = 'id-ID';
        utterance.rate = 1.25;
        utterance.pitch = 1.35;
        utterance.volume = 1.0;

        const voices = window.speechSynthesis.getVoices();
        const idVoice = voices.find(v => v.lang.includes('id') || v.lang.includes('ID'));
        if (idVoice) utterance.voice = idVoice;

        window.speechSynthesis.speak(utterance);
    }
}

function spawnFlyingPartyProps(parentElement) {
    const props = ['🇮🇩', '📹', '💰', '🏆', '💵', '💎', '⭐', '🔥', '💖', '🎉', '⚡', '🇮🇩', '🏆', '💵', '💎', '⭐'];
    const container = document.body;
    const rect = parentElement.getBoundingClientRect();

    for (let i = 0; i < 16; i++) {
        const propEl = document.createElement('div');
        propEl.innerText = props[Math.floor(Math.random() * props.length)];
        
        const startX = rect.left + 20;
        const startY = rect.top + 10;
        
        const angle = Math.random() * Math.PI * 2;
        const distance = 80 + Math.random() * 120;
        const targetX = startX + Math.cos(angle) * distance;
        const targetY = startY + Math.sin(angle) * distance - 40;
        
        propEl.style.cssText = `
            position: fixed;
            left: ${startX}px;
            top: ${startY}px;
            font-size: ${18 + Math.random() * 14}px;
            pointer-events: none;
            z-index: 9999;
            transition: all 0.9s cubic-bezier(0.16, 1, 0.3, 1);
            opacity: 1;
            transform: scale(0.5) rotate(0deg);
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
        `;

        container.appendChild(propEl);

        setTimeout(() => {
            propEl.style.left = `${targetX}px`;
            propEl.style.top = `${targetY}px`;
            propEl.style.opacity = '0';
            propEl.style.transform = `scale(1.4) rotate(${Math.random() * 360 - 180}deg)`;
        }, 20);

        setTimeout(() => propEl.remove(), 950);
    }
}

function showMotivationSpeechBubble(parentElement, salesName, isFinished) {
    const existing = parentElement.querySelector('.nailong-speech-bubble');
    if (existing) existing.remove();

    const callName = getSalesCallName(salesName);

    const bubble = document.createElement('div');
    bubble.className = 'nailong-speech-bubble';
    
    let quotes = [
        `DIRGAHAYU INDONESIA! 🇮🇩 Semangat ${callName}! 🔥💪`,
        `MERDEKA! 🇮🇩 Gas Terus ${callName}! 🚀🏆`,
        `GACOR KEMERDEKAAN! 🇮🇩 ${callName} Mantaap! 💰💵`,
        `SULTAN KEMERDEKAAN! 🇮🇩 ${callName} 👑🌟`,
        `JUARA LOEWIX! 🇮🇩 ${callName} 💎🎉`
    ];

    if (isFinished) {
        quotes = [
            `JUARA 1 FINISH! 🏆 🇮🇩 Omset 200 Juta ${callName}! 🎉`,
            `PUNCAK VICTORY! 👑 🇮🇩 ${callName} Angkat Piala 🏆!`,
            `SULTAN JUARA 1! 🏆 💰 Omset 200M Tuntas ${callName}! 🇮🇩`
        ];
    }
    const randomQuote = quotes[Math.floor(Math.random() * quotes.length)];

    bubble.style.cssText = `
        position: absolute;
        bottom: 52px;
        left: 50%;
        transform: translateX(-50%) scale(0.7);
        background: linear-gradient(135deg, #DC2626, #991B1B);
        color: #FFFFFF;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 11.5px;
        font-weight: 800;
        padding: 7px 14px;
        border-radius: 14px;
        border: 1.5px solid #FCD34D;
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.5);
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        z-index: 100;
    `;
    
    bubble.innerText = randomQuote;
    parentElement.appendChild(bubble);

    setTimeout(() => {
        bubble.style.opacity = '1';
        bubble.style.transform = 'translateX(-50%) scale(1)';
    }, 20);

    setTimeout(() => {
        bubble.style.opacity = '0';
        bubble.style.transform = 'translateX(-50%) scale(0.7)';
        setTimeout(() => bubble.remove(), 300);
    }, 2200);
}

function switchChartMetric(metricType) {
    currentMetric = metricType;
    
    if (document.getElementById('btnMetricOmset')) document.getElementById('btnMetricOmset').classList.remove('active');
    if (document.getElementById('btnMetricInvCount')) document.getElementById('btnMetricInvCount').classList.remove('active');
    if (document.getElementById('btnMetricTotal')) document.getElementById('btnMetricTotal').classList.remove('active');
    if (document.getElementById('btnMetricCustBaru')) document.getElementById('btnMetricCustBaru').classList.remove('active');
    if (document.getElementById('btnMetricCustomer')) document.getElementById('btnMetricCustomer').classList.remove('active');

    let titleText = '⚡ Sirkuit Lari Sales (Total Activity Follow Up)';
    let metricLblText = 'Total Activity FU';

    if (metricType === 'total') {
        if (document.getElementById('btnMetricTotal')) document.getElementById('btnMetricTotal').classList.add('active');
        titleText = '⚡ Sirkuit Lari Sales (Total Activity Follow Up)';
        metricLblText = 'Total Activity FU';
    } else if (metricType === 'cust_baru') {
        if (document.getElementById('btnMetricCustBaru')) document.getElementById('btnMetricCustBaru').classList.add('active');
        titleText = '🚀 Sirkuit Lari Sales (Penambahan Customer Baru Input)';
        metricLblText = 'Customer Baru Input';
    } else if (metricType === 'customer') {
        if (document.getElementById('btnMetricCustomer')) document.getElementById('btnMetricCustomer').classList.add('active');
        titleText = '👥 Sirkuit Lari Sales (Jumlah Customer di-FU)';
        metricLblText = 'Customer di-FU';
    } else if (metricType === 'inv_count') {
        if (document.getElementById('btnMetricInvCount')) document.getElementById('btnMetricInvCount').classList.add('active');
        titleText = `🧾 Sirkuit Lari Sales (HADIAH UTAMA 2 JT INVOICE TERBANYAK - ${currentLabelPeriodeRanking})`;
        metricLblText = `Total Invoice (${currentLabelPeriodeRanking})`;
    } else if (metricType === 'omset') {
        if (document.getElementById('btnMetricOmset')) document.getElementById('btnMetricOmset').classList.add('active');
        titleText = '🏁 Sirkuit Lari Sales (Omset Invoice Rp - Target 200 Juta)';
        metricLblText = `Omset Invoice (${currentLabelPeriodeRanking})`;
    }

    document.getElementById('chartTitle').innerHTML = titleText;
    document.querySelectorAll('.metric-lbl').forEach(el => el.innerText = metricLblText);

    // Dynamic sort by active metric
    const metricData = getSortedDatasetByMetric(metricType);

    salesChartLabels = metricData.labels;
    if (metricType === 'total') salesChartTotalFU = metricData.values;
    else if (metricType === 'cust_baru') salesChartCustBaru = metricData.values;
    else if (metricType === 'customer') salesChartCustomerFU = metricData.values;
    else if (metricType === 'inv_count') salesChartInvCount = metricData.values;
    else if (metricType === 'omset') salesChartOmsetInvoice = metricData.values;

    updatePodiumCardsDOM(metricData.top1, metricData.top2, metricData.top3, currentLabelPeriodeRanking);
    renderScaledChart();
}

function updatePodiumCards(metricType) {
    const val1 = document.getElementById('podium1Val');
    const val2 = document.getElementById('podium2Val');
    const val3 = document.getElementById('podium3Val');

    if (val1 && top1Data) val1.innerText = getFormattedMetricValue(top1Data, metricType);
    if (val2 && top2Data) val2.innerText = getFormattedMetricValue(top2Data, metricType);
    if (val3 && top3Data) val3.innerText = getFormattedMetricValue(top3Data, metricType);

    [val1, val2, val3].forEach(el => {
        if (el) {
            el.classList.remove('pop-metric');
            void el.offsetWidth;
            el.classList.add('pop-metric');
        }
    });
}

function getFormattedMetricValue(data, metricType) {
    if (!data) return '-';
    if (metricType === 'omset') return formatRupiahDisplay(data.total_omset_invoice);
    if (metricType === 'inv_count') return new Intl.NumberFormat('id-ID').format(data.total_inv_count) + ' Inv';
    if (metricType === 'total') return new Intl.NumberFormat('id-ID').format(data.total_fu);
    return new Intl.NumberFormat('id-ID').format(data.total_customer_fu);
}

function setTopLimit(limit, btn) {
    currentTopLimit = limit;
    document.querySelectorAll('.top-limit-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderScaledChart();
}

// Auto-initialize chart and Nailong runners on page load
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(function() {
        switchChartMetric(currentMetric);
    }, 50);
} else {
    document.addEventListener('DOMContentLoaded', function() {
        switchChartMetric(currentMetric);
    });
}
</script>
