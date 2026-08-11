<?php
/**
 * GRAFIK RANKING & LEADERBOARD SALES WIDGET - ATHLETICS RUNNING TRACK / SIRKUIT LARI EDITION
 * PERIODE: MULAI 1 AGUSTUS 2026 (SEMUA DARI 0)
 * METRIK UTAMA: TOTAL NOMINAL OMSET INVOICE (RP) DENGAN TARGET RP 200 JUTA
 */

// Parse month filter (Default: 3 Bulan - Agt-Okt 2026)
$selected_bulan = trim($_GET['periode_bulan'] ?? '8-10');

if ($selected_bulan === '8') {
    $start_periode_ranking = '2026-08-01 00:00:00';
    $end_periode_ranking = '2026-08-31 23:59:59';
    $label_periode_ranking = 'Agt 2026';
    $full_label_ranking = 'Bulan 8 (Agustus 2026)';
} else if ($selected_bulan === '9') {
    $start_periode_ranking = '2026-09-01 00:00:00';
    $end_periode_ranking = '2026-09-30 23:59:59';
    $label_periode_ranking = 'Sep 2026';
    $full_label_ranking = 'Bulan 9 (September 2026)';
} else if ($selected_bulan === '10') {
    $start_periode_ranking = '2026-10-01 00:00:00';
    $end_periode_ranking = '2026-10-31 23:59:59';
    $label_periode_ranking = 'Okt 2026';
    $full_label_ranking = 'Bulan 10 (Oktober 2026)';
} else {
    $selected_bulan = '8-10';
    $start_periode_ranking = '2026-08-01 00:00:00';
    $end_periode_ranking = '2026-10-31 23:59:59';
    $label_periode_ranking = '3 Bulan (Agt-Okt)';
    $full_label_ranking = 'Periode 3 Bulan (Agt - Okt 2026)';
}

$target_omset_finish = 200000000; // Rp 200.000.000,-

// Fetch Ranking Sales Data
$sql_ranking_all = "
    SELECT 
        s.id AS sales_id,
        s.nama_lengkap AS nama_sales,
        COALESCE(SUM(CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}' AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.nominal_invoice ELSE 0 END), 0) AS total_omset_invoice,
        COUNT(CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}' THEN fu.id ELSE NULL END) AS total_fu,
        COUNT(DISTINCT CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}' THEN fu.customer_id ELSE NULL END) AS total_customer_fu,
        COUNT(CASE WHEN fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}' AND fu.no_inv IS NOT NULL AND fu.no_inv != '' THEN fu.id ELSE NULL END) AS total_inv_count
    FROM sales s
    LEFT JOIN follow_ups fu ON fu.sales_id = s.id AND fu.deleted_at IS NULL AND fu.tgl_follow_up >= '{$start_periode_ranking}' AND fu.tgl_follow_up <= '{$end_periode_ranking}'
    LEFT JOIN customers c ON fu.customer_id = c.id AND c.deleted_at IS NULL
    WHERE s.role = 'sales' OR s.role = 'superadmin' OR fu.id IS NOT NULL
    GROUP BY s.id, s.nama_lengkap
    ORDER BY total_omset_invoice DESC, total_inv_count DESC, total_fu DESC
";

$res_ranking = $conn->query($sql_ranking_all);
$ranking_data = [];
if ($res_ranking) {
    while ($row = $res_ranking->fetch_assoc()) {
        $ranking_data[] = $row;
    }
}

$chart_labels = [];
$chart_omset_invoice = [];
$chart_total_fu = [];
$chart_customer_fu = [];
$chart_inv_count = [];

foreach ($ranking_data as $rd) {
    $chart_labels[] = $rd['nama_sales'];
    $chart_omset_invoice[] = (float)$rd['total_omset_invoice'];
    $chart_total_fu[] = (int)$rd['total_fu'];
    $chart_customer_fu[] = (int)$rd['total_customer_fu'];
    $chart_inv_count[] = (int)$rd['total_inv_count'];
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

/* === NAILONG 3D SPRITE SHEET ANIMATION === */
/* Sprite sheet: 1024x1024, 3 columns x 2 rows = 6 frames */
/* Each frame: ~341 x 512 px. Display at 40x60px */
.nailong-sprite {
    width: 40px;
    height: 60px;
    background-image: url('assets/nailong_spritesheet.png?v=<?= time() ?>');
    background-size: 120px 120px; /* 40*3=120, 60*2=120 */
    background-repeat: no-repeat;
    background-position: 0 0;
    animation: nailongSpriteRun 0.5s steps(1) infinite;
    display: block;
    filter: drop-shadow(0 3px 6px rgba(0,0,0,0.25));
}

@keyframes nailongSpriteRun {
    0%      { background-position: 0px 0px; }      /* Frame 1 */
    16.66%  { background-position: -40px 0px; }     /* Frame 2 */
    33.33%  { background-position: -80px 0px; }     /* Frame 3 */
    50%     { background-position: 0px -60px; }     /* Frame 4 */
    66.66%  { background-position: -40px -60px; }   /* Frame 5 */
    83.33%  { background-position: -80px -60px; }   /* Frame 6 */
    100%    { background-position: 0px 0px; }       /* Loop back */
}

/* Static single image fallback for victory */
.nailong-3d-gambar2 {
    width: 52px;
    height: auto;
    max-height: 58px;
    object-fit: contain;
    display: block;
}

/* === RUNNING WRAPPER === */
.nailong-run-wrapper {
    position: relative;
    display: inline-block;
}

.nailong-run-bounce {
    animation: nailongRunBounce 0.5s infinite ease-in-out;
}

@keyframes nailongRunBounce {
    0%   { transform: translateY(0px) rotate(-2deg); }
    25%  { transform: translateY(-5px) rotate(0deg); }
    50%  { transform: translateY(-2px) rotate(2deg); }
    75%  { transform: translateY(-5px) rotate(0deg); }
    100% { transform: translateY(0px) rotate(-2deg); }
}

/* === GROUND SHADOW that pulses with bounce === */
.nailong-ground-shadow {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
    width: 36px;
    height: 8px;
    background: radial-gradient(ellipse at center, rgba(0,0,0,0.35) 0%, transparent 70%);
    border-radius: 50%;
    animation: nailongShadowPulse 0.35s infinite ease-in-out;
}

@keyframes nailongShadowPulse {
    0%   { transform: translateX(-50%) scaleX(1) scaleY(1); opacity: 0.5; }
    30%  { transform: translateX(-50%) scaleX(0.7) scaleY(0.6); opacity: 0.3; }
    65%  { transform: translateX(-50%) scaleX(1.15) scaleY(1.1); opacity: 0.6; }
    100% { transform: translateX(-50%) scaleX(1) scaleY(1); opacity: 0.5; }
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
                    <span class="badge bg-warning text-dark fw-bold rounded-pill px-3 py-1" style="font-size: 11px; font-weight: 800;">🏁 Sirkuit Balap Nailong Merah Putih 🇮🇩</span>
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

    <!-- Filter Buttons Bar -->
    <div class="d-flex align-items-center gap-2 flex-wrap mb-4">
        <button type="button" class="metric-btn active" id="btnMetricOmset" onclick="switchChartMetric('omset')">
            💵 Invoice Sales (Rp)
        </button>
        <button type="button" class="metric-btn" id="btnMetricInvCount" onclick="switchChartMetric('inv_count')" style="border: 1.5px solid #3B82F6;">
            🧾 Invoice Terbanyak <span class="badge bg-danger text-white rounded-pill px-2 py-0.5 ms-1" style="font-size: 10px;">Hadiah 2 Juta 💰</span>
        </button>
        <button type="button" class="metric-btn" id="btnMetricTotal" onclick="switchChartMetric('total')">
            ⚡ Total Activity FU
        </button>
        <button type="button" class="metric-btn" id="btnMetricCustomer" onclick="switchChartMetric('customer')">
            👥 Customer di-FU
        </button>
        <button type="button" class="btn-full-leaderboard ms-1" data-bs-toggle="modal" data-bs-target="#fullLeaderboardModal">
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

                    <div class="track-bg-overlay"></div>
                    <div class="finish-line-banner"></div>
                    <div class="finish-badge-top">FINISH 🏁 🇮🇩</div>

                    <div style="position: relative; width: 100%; min-height: 320px;" id="chartCanvasContainer">
                        <canvas id="salesRankingChart"></canvas>
                        <!-- Interactive Animated CCTV Mascot Runners Overlay (Nailong 奶龙 Edition) -->
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
let currentMetric = 'omset';
let currentTopLimit = 10;
let currentMonthPeriode = '<?= $selected_bulan ?>';

let salesChartLabels = <?php echo json_encode($chart_labels); ?>;
let salesChartOmsetInvoice = <?php echo json_encode($chart_omset_invoice); ?>;
let salesChartTotalFU = <?php echo json_encode($chart_total_fu); ?>;
let salesChartCustomerFU = <?php echo json_encode($chart_customer_fu); ?>;
let salesChartInvCount = <?php echo json_encode($chart_inv_count); ?>;

let top1Data = <?php echo json_encode($top1); ?>;
let top2Data = <?php echo json_encode($top2); ?>;
let top3Data = <?php echo json_encode($top3); ?>;

document.addEventListener("DOMContentLoaded", function() {
    renderScaledChart();
});

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

            const badgeFullLabel = document.getElementById('badgeFullLabelRanking');
            if (badgeFullLabel) badgeFullLabel.innerText = data.full_label_ranking;

            salesChartLabels = data.chart_labels || [];
            salesChartOmsetInvoice = data.chart_omset_invoice || [];
            salesChartTotalFU = data.chart_total_fu || [];
            salesChartCustomerFU = data.chart_customer_fu || [];
            salesChartInvCount = data.chart_inv_count || [];

            updatePodiumCardsDOM(data.top1, data.top2, data.top3, data.label_periode_ranking);
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
        const progressEl = document.getElementById(prefix + 'Progress');
        const actEl = document.getElementById(prefix + 'Activity');
        const custEl = document.getElementById(prefix + 'Cust');
        const lblEl = document.getElementById(prefix + 'Lbl');

        if (lblEl) lblEl.innerText = `Omset Invoice (${labelPeriode})`;

        if (data) {
            if (nameEl) nameEl.innerText = data.nama_sales;
            const omset = parseFloat(data.total_omset_invoice) || 0;
            const pct = Math.min(100, Math.round((omset / 200000000) * 100 * 10) / 10);
            if (progressEl) progressEl.style.width = Math.max(5, pct) + '%';
            if (actEl) actEl.innerText = `⚡ ${data.total_fu} Activity`;
            if (custEl) custEl.innerText = `👥 ${data.total_customer_fu} Customer di-FU`;
        } else {
            if (nameEl) nameEl.innerText = '-';
            if (progressEl) progressEl.style.width = '5%';
            if (actEl) actEl.innerText = '⚡ 0 Activity';
            if (custEl) custEl.innerText = '👥 0 Customer di-FU';
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
        const custFu = new Intl.NumberFormat('id-ID').format(s.total_customer_fu);

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
            <td class="text-end px-3 font-monospace fw-bold text-success" style="font-size: 14px;">
                ${omsetFormatted}
            </td>
            <td class="text-end px-3 font-monospace fw-bold text-primary" style="font-size: 14px;">
                ${invCount} Inv
            </td>
            <td class="text-end px-3 font-monospace fw-bold text-dark" style="font-size: 14px;">
                ${totalFu}
            </td>
            <td class="text-end px-3 font-monospace fw-bold text-secondary" style="font-size: 14px;">
                ${custFu}
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
    if (currentMetric === 'omset') return { values: salesChartOmsetInvoice, label: "Omset Invoice (Rp)" };
    if (currentMetric === 'inv_count') return { values: salesChartInvCount, label: "Jumlah Invoice" };
    if (currentMetric === 'total') return { values: salesChartTotalFU, label: "Total Activity FU" };
    return { values: salesChartCustomerFU, label: "Customer di-FU" };
}

function formatRupiahDisplay(num) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
}

function renderScaledChart() {
    const { values, label } = getActiveDatasetValues();
    
    let limit = currentTopLimit === 'all' ? values.length : parseInt(currentTopLimit);
    let slicedLabels = salesChartLabels.slice(0, limit);
    let slicedValues = values.slice(0, limit);

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

    // Compute scale max - Fixed Targets for each metric so finish line represents real goals
    const maxVal = Math.max(...slicedValues, 1);
    let scaleMax = 50;
    let stepSizeVal = 10;

    if (currentMetric === 'omset') {
        // Fixed scale to Target 200 Juta (or higher if a sales exceeds 200M)
        scaleMax = maxVal > 200000000 ? Math.ceil(maxVal / 50000000) * 50000000 : 200000000;
        stepSizeVal = 50000000; // 0, 50 Jt, 100 Jt, 150 Jt, 200 Jt
    } else if (currentMetric === 'inv_count') {
        // Target finish line for Invoice Terbanyak is 50 Invoice
        scaleMax = maxVal > 50 ? Math.ceil(maxVal / 10) * 10 : 50;
        stepSizeVal = 10;
    } else if (currentMetric === 'total') {
        // Target finish line for Activity FU is 100 Activity
        scaleMax = maxVal > 100 ? Math.ceil(maxVal / 20) * 20 : 100;
        stepSizeVal = 20;
    } else if (currentMetric === 'customer') {
        // Target finish line for Customer FU is 50 Customer
        scaleMax = maxVal > 50 ? Math.ceil(maxVal / 10) * 10 : 50;
        stepSizeVal = 10;
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
                    top: 55,
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

    // Check if we need to initialize or rebuild the runner DOM elements
    const needRebuild = holder.children.length !== meta.data.length;
    if (needRebuild) {
        holder.innerHTML = '';
    }

    meta.data.forEach((bar, index) => {
        const value = chart.data.datasets[0].data[index];
        const labelName = chart.data.labels[index];
        const barY = bar.y;
        const barX = bar.x;

        // Calculate runner left position (at the leading tip of the growing bar)
        let leftPos = Math.min(barX - 18, xAxis.right - 45);
        if (value === 0) {
            leftPos = xAxis.left + 5 + (index * 4); // Stagger zero runners
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
                // STATE 2: NAILONG VICTORY 🏆 SELEBRASI NGANGKAT PIALA (Target 200 Juta Finish!)
                runnerDiv.innerHTML = `
                    <div class="nailong-run-wrapper">
                        ${medalBadge ? `<span class="nailong-rank-tag" style="top: -24px; left: 50%; transform: translateX(-50%); font-size: 14px;">${medalBadge} 🏆</span>` : '<span class="nailong-rank-tag" style="top: -24px; left: 50%; transform: translateX(-50%); font-size: 14px;">🏆</span>'}
                        <div class="nailong-victory-float" style="position: relative;">
                            <div class="nailong-sparkles">
                                <div class="sparkle-dot"></div>
                                <div class="sparkle-dot"></div>
                                <div class="sparkle-dot"></div>
                                <div class="sparkle-dot"></div>
                            </div>
                            <img src="assets/nailong_gambar2_3d.png?v=<?= time() ?>" class="nailong-3d-gambar2" alt="Nailong Victory 🏆" />
                            <div class="nailong-trophy-icon">🏆</div>
                            <div class="nailong-money-icon">💰</div>
                            <div class="nailong-headband"></div>
                        </div>
                        <div class="nailong-ground-shadow"></div>
                    </div>
                `;
            } else {
                // STATE 1: NAILONG LARI DI TEMPAT 🏃 WITH SPRITE SHEET ANIMATION + BENDERA 🇮🇩
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
    
    document.getElementById('btnMetricOmset').classList.remove('active');
    if (document.getElementById('btnMetricInvCount')) document.getElementById('btnMetricInvCount').classList.remove('active');
    document.getElementById('btnMetricTotal').classList.remove('active');
    document.getElementById('btnMetricCustomer').classList.remove('active');

    let titleText = '🏁 Sirkuit Lari Sales (Omset Invoice Rp - Target 200 Juta)';
    let metricLblText = 'Omset Invoice (<?= $label_periode_ranking ?>)';

    if (metricType === 'omset') {
        document.getElementById('btnMetricOmset').classList.add('active');
        titleText = '🏁 Sirkuit Lari Sales (Omset Invoice Rp - Target 200 Juta)';
        metricLblText = 'Omset Invoice (<?= $label_periode_ranking ?>)';
    } else if (metricType === 'inv_count') {
        if (document.getElementById('btnMetricInvCount')) document.getElementById('btnMetricInvCount').classList.add('active');
        titleText = '🧾 Sirkuit Lari Sales (HADIAH UTAMA 2 JT INVOICE TERBANYAK - Target 50 Inv)';
        metricLblText = 'Total Invoice (<?= $label_periode_ranking ?>)';
    } else if (metricType === 'total') {
        document.getElementById('btnMetricTotal').classList.add('active');
        titleText = '⚡ Sirkuit Lari Sales (Total Activity Follow Up)';
        metricLblText = 'Total Activity FU';
    } else if (metricType === 'customer') {
        document.getElementById('btnMetricCustomer').classList.add('active');
        titleText = '👥 Sirkuit Lari Sales (Jumlah Customer di-FU)';
        metricLblText = 'Customer di-FU';
    }

    document.getElementById('chartTitle').innerHTML = titleText;
    document.querySelectorAll('.metric-lbl').forEach(el => el.innerText = metricLblText);

    updatePodiumCards(metricType);
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
</script>
