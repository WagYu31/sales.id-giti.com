<?php
/**
 * WIDGET ANALYTICS BENTO - EXECUTIVE REFINED EDITION
 * Precision aspect ratios, compact modern KPI heights, pure SVG zero-dependency charts.
 *
 * Cards:
 * 1. Revenue & Customer Growth (Catmull-Rom Smooth SVG Gradient Curve)
 * 2. Total Customer & Follow Up Waves (Harmonic Breathing Dual Sine Waves)
 * 3. Kategori Customer (Proportional Squarified Treemap + Regional Table)
 * 4. Seasonality Radar (Fourier Smoothed Radial Polygon + Annual KPIs)
 */

$total_cust_val = isset($totalCustomers) ? (int)$totalCustomers : 4671;
$pending_fu_val = isset($pendingFU) ? (int)$pendingFU : 0;
$new_cust_val   = isset($thisMonthNew) ? (int)$thisMonthNew : 0;
?>

<!-- Google Fonts: Geist & Geist Mono & Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500;600;700&family=Geist:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ================= REFINED BENTO GRID SYSTEM ================= */
.bento-root {
    --b-bg-card: #FFFFFF;
    --b-border: #E2E8F0;
    --b-border-subtle: #F1F5F9;
    --b-fg: #0F172A;
    --b-muted: #64748B;
    --b-emerald: #10B981;
    --b-blue: #3B82F6;
    font-family: 'Geist', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    color: var(--b-fg);
    margin-bottom: 30px;
}

.bento-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
    padding: 0 4px;
}

.bento-header-title {
    font-size: 17px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.bento-header-badge {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: #FFFFFF;
    font-size: 10.5px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.3px;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
}

.bento-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 16px;
}

/* Card Base */
.bento-card {
    background: var(--b-bg-card);
    border: 1.5px solid var(--b-border);
    border-radius: 24px;
    box-shadow: 0 4px 18px -4px rgba(15, 23, 42, 0.04), 0 1px 2px rgba(15, 23, 42, 0.02);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.bento-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px -6px rgba(15, 23, 42, 0.08);
}

/* Specific Row Heights for Balance */
.bento-card-top {
    height: 195px;
    padding: 20px 22px;
}

.bento-card-bottom {
    min-height: 410px;
    padding: 22px 24px;
}

/* Grid Spans */
.col-span-5 { grid-column: span 5; }
.col-span-7 { grid-column: span 7; }

@media (max-width: 1080px) {
    .col-span-5, .col-span-7 { grid-column: span 12; }
    .bento-card-top { height: auto; min-height: 180px; }
    .bento-card-bottom { min-height: auto; }
}

/* Typography */
.bento-label {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--b-muted);
    letter-spacing: -0.01em;
    text-transform: uppercase;
}

.bento-big-num {
    font-size: 32px;
    font-weight: 700;
    line-height: 1.1;
    margin-top: 6px;
    font-family: 'Geist Mono', monospace;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.04em;
    color: #0F172A;
    display: flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
}

.bento-delta {
    display: inline-flex;
    align-items: center;
    gap: 2.5px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--b-emerald);
    letter-spacing: -0.02em;
}

.bento-note {
    font-size: 12px;
    color: #64748B;
    margin-top: 6px;
    letter-spacing: -0.01em;
}

/* Segmented Buttons */
.bento-segmented {
    display: inline-grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    background: #F1F5F9;
    padding: 3px;
    border-radius: 10px;
    border: 1px solid #E2E8F0;
    gap: 3px;
}

.bento-seg-btn {
    border: none;
    background: transparent;
    padding: 5px 12px;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 600;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    font-family: inherit;
    white-space: nowrap;
}

.bento-seg-btn.active {
    background: #FFFFFF;
    color: #0F172A;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
}

/* Chart Canvas Boxes */
.bento-chart-container {
    position: relative;
    border-radius: 14px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    overflow: hidden;
    height: 125px;
    width: 100%;
    align-self: center;
}

.bento-treemap-box {
    position: relative;
    height: 200px;
    margin-top: 12px;
    width: 100%;
}

.bento-treemap-tile {
    position: absolute;
    border-radius: 12px;
    padding: 10px 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.2, 0.9, 0.25, 1);
}

.bento-treemap-tile:hover {
    filter: brightness(1.08);
    transform: scale(0.99);
}

.bento-radar-box {
    width: 100%;
    max-width: 250px;
    height: 230px;
    margin: 4px auto 0;
    position: relative;
}

/* Compact Regional Table */
.bento-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 14px;
    font-size: 12px;
}

.bento-table th {
    padding-bottom: 6px;
    color: #64748B;
    font-weight: 600;
    border-bottom: 1.5px solid #E2E8F0;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.bento-table td {
    padding: 7px 0;
    border-bottom: 1px solid #F1F5F9;
    font-family: 'Geist Mono', monospace;
    font-size: 12px;
}

/* Tooltip */
.bento-tooltip {
    position: fixed;
    z-index: 9999;
    pointer-events: none;
    background: rgba(15, 23, 42, 0.95);
    color: #FFFFFF;
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 7px 11px;
    border-radius: 10px;
    font-size: 11.5px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(8px);
    transition: opacity 0.15s ease;
    white-space: nowrap;
    opacity: 0;
}

.bento-tooltip.visible { opacity: 1; }
.bento-tooltip-title { font-size: 10.5px; color: #94A3B8; margin-bottom: 3px; font-weight: 600; }
.bento-tooltip-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-family: 'Geist Mono', monospace; }
</style>

<div class="bento-root">
    <!-- Header Controls -->
    <div class="bento-header-bar">
        <h4 class="bento-header-title">
            <span>⚡</span>
            <span>Loewix Executive Analytics</span>
            <span class="bento-header-badge">Live Realtime</span>
        </h4>
        <div style="font-size: 12px; color: #64748B; font-weight: 600;">
            <i class="bi bi-clock-history me-1"></i> Terakhir diperbarui otomatis
        </div>
    </div>

    <!-- Grid Container -->
    <div class="bento-grid">
        <!-- ================= CARD 1: REVENUE / OMSET PENJUALAN (col-span-5) ================= -->
        <div class="bento-card bento-card-top col-span-5">
            <div class="d-flex align-items-center justify-content-between h-100 gap-3">
                <div style="flex: 0 0 160px;">
                    <div class="bento-label">Omset Penjualan</div>
                    <div class="bento-big-num">
                        <span id="bentoRevVal">Rp 0</span>
                    </div>
                    <div class="d-flex align-items-center gap-1.5 mt-1">
                        <span class="bento-delta">
                            <svg viewBox="0 0 12 12" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 10.5V1.5M2 5.5l4-4 4 4"/></svg>
                            +8.4%
                        </span>
                        <span style="font-size: 11px; color: #94A3B8;">vs bln lalu</span>
                    </div>
                    <div class="bento-note">Rata-rata: <b class="text-dark">Rp 3.85M</b></div>
                </div>

                <!-- SVG Compact Spline Curve -->
                <div class="bento-chart-container flex-grow-1" id="boxRevenueChart">
                    <svg id="svgRevenueChart" width="100%" height="100%" style="display: block; overflow: visible;"></svg>
                </div>
            </div>
        </div>

        <!-- ================= CARD 2: TOTAL CUSTOMER & SESSIONS (col-span-7) ================= -->
        <div class="bento-card bento-card-top col-span-7">
            <div class="d-flex align-items-center justify-content-between h-100 gap-3">
                <div style="flex: 0 0 185px;">
                    <div class="bento-label">Total Customer Terdaftar</div>
                    <div class="bento-big-num">
                        <span id="bentoSessVal"><?= number_format($total_cust_val, 0, ',', '.') ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="bento-delta">
                            <svg viewBox="0 0 12 12" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 10.5V1.5M2 5.5l4-4 4 4"/></svg>
                            +12%
                        </span>
                        <span style="font-size: 11px; color: #64748B;">
                            (<?= number_format($new_cust_val, 0, ',', '.') ?> baru bln ini)
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1.5 flex-wrap" style="font-size: 11px;">
                        <span class="badge bg-light text-dark border px-2 py-0.5 rounded-pill" style="font-size: 10.5px; font-weight: 600;">
                            ⏳ <?= number_format($pending_fu_val, 0, ',', '.') ?> Pending FU
                        </span>
                        <span class="d-inline-flex align-items-center gap-1 text-muted">
                            <i style="width: 8px; height: 3px; background: #06B6D4; border-radius: 2px;"></i> Canvas
                        </span>
                        <span class="d-inline-flex align-items-center gap-1 text-muted">
                            <i style="width: 8px; height: 3px; background: #EC4899; border-radius: 2px;"></i> Web CRM
                        </span>
                    </div>
                </div>

                <!-- SVG Breathing Dual Sine Waves -->
                <div class="bento-chart-container flex-grow-1" id="boxSessionsChart">
                    <svg id="svgSessionsChart" width="100%" height="100%" style="display: block;"></svg>
                </div>
            </div>
        </div>

        <!-- ================= CARD 3: KATEGORI CUSTOMER (TREEMAP) (col-span-7) ================= -->
        <div class="bento-card bento-card-bottom col-span-7">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                <div>
                    <h5 class="fw-bold mb-0" style="font-size: 15.5px; letter-spacing: -0.02em; color: #0F172A;">
                        Distribusi Channel &amp; Kategori Customer
                    </h5>
                    <div class="d-flex flex-wrap gap-2 mt-1" style="font-size: 11px;">
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 7px; height: 7px; border-radius: 2px; background: #10B981;"></i> CCTV</span>
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 7px; height: 7px; border-radius: 2px; background: #8B5CF6;"></i> Grosir</span>
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 7px; height: 7px; border-radius: 2px; background: #3B82F6;"></i> IT/Komputer</span>
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 7px; height: 7px; border-radius: 2px; background: #F59E0B;"></i> Retail</span>
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 7px; height: 7px; border-radius: 2px; background: #EF4444;"></i> Project</span>
                    </div>
                </div>

                <!-- Period Segmented Buttons -->
                <div class="bento-segmented" role="tablist">
                    <button type="button" class="bento-seg-btn active" onclick="switchBentoPeriod(0, this)">Mingguan</button>
                    <button type="button" class="bento-seg-btn" onclick="switchBentoPeriod(1, this)">Bulanan</button>
                    <button type="button" class="bento-seg-btn" onclick="switchBentoPeriod(2, this)">Tahunan</button>
                </div>
            </div>

            <!-- Squarified Treemap Area -->
            <div class="bento-treemap-box" id="bentoTreemapContainer"></div>

            <!-- Regional Table -->
            <table class="bento-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Wilayah Terbesar</th>
                        <th style="text-align: right;">Mingguan</th>
                        <th style="text-align: right;">Bulanan</th>
                        <th style="text-align: right;">Tahunan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-secondary fw-semibold">Jabodetabek &amp; Banten</td>
                        <td style="text-align: right;" class="text-dark">1.240 Toko</td>
                        <td style="text-align: right;" class="text-dark">2.450 Toko</td>
                        <td style="text-align: right;" class="text-dark fw-bold">3.180 Toko</td>
                    </tr>
                    <tr>
                        <td class="text-secondary fw-semibold">Jawa Barat &amp; Jateng</td>
                        <td style="text-align: right;" class="text-dark">480 Toko</td>
                        <td style="text-align: right;" class="text-dark">890 Toko</td>
                        <td style="text-align: right;" class="text-dark fw-bold">1.020 Toko</td>
                    </tr>
                    <tr>
                        <td class="text-secondary fw-semibold">Luar Pulau (Sumatera, Bali, dll)</td>
                        <td style="text-align: right;" class="text-dark">210 Toko</td>
                        <td style="text-align: right;" class="text-dark">410 Toko</td>
                        <td style="text-align: right;" class="text-dark fw-bold">471 Toko</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ================= CARD 4: RADAR SEASONALITY & TARGET (col-span-5) ================= -->
        <div class="bento-card bento-card-bottom col-span-5">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <h5 class="fw-bold mb-0" style="font-size: 15.5px; letter-spacing: -0.02em; color: #0F172A;">
                    Tren Musiman (Seasonality Radar)
                </h5>
                <div class="bento-segmented">
                    <button type="button" class="bento-seg-btn active" onclick="switchRadarYear(0, this)">2026</button>
                    <button type="button" class="bento-seg-btn" onclick="switchRadarYear(1, this)">2025</button>
                </div>
            </div>

            <!-- SVG Radar Canvas (Centered) -->
            <div class="bento-radar-box" id="bentoRadarContainer">
                <svg id="svgRadarChart" width="100%" height="100%" style="display: block;"></svg>
            </div>

            <!-- Performance Stats -->
            <div class="row g-2 mt-auto pt-2 border-top">
                <div class="col-6">
                    <div class="bento-label" style="font-size: 11px;">Rata-rata Bulanan</div>
                    <div class="bento-big-num" style="font-size: 22px; margin-top: 2px;">
                        <span id="bentoRadarAvg">Rp 82.5M</span>
                    </div>
                    <div class="bento-note text-success fw-bold" style="font-size: 11px; margin-top: 2px;">
                        <i class="bi bi-arrow-up-short"></i> +19.6% performa
                    </div>
                </div>
                <div class="col-6">
                    <div class="bento-label" style="font-size: 11px;">Target Tahunan</div>
                    <div class="bento-big-num" style="font-size: 22px; margin-top: 2px;">
                        <span id="bentoRadarTotal">Rp 980M</span>
                    </div>
                    <div class="bento-note text-muted" style="font-size: 11px; margin-top: 2px;">
                        Capai 88.4% target
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Tooltip -->
<div class="bento-tooltip" id="bentoGlobalTooltip">
    <div class="bento-tooltip-title" id="bentoTipTitle">Detail</div>
    <div id="bentoTipContent"></div>
</div>

<script>
/**
 * VANILLA JS BENTO ENGINE - COMPACT & PRECISE RATIOS
 */
(function() {
    const tipEl = document.getElementById('bentoGlobalTooltip');
    const tipTitle = document.getElementById('bentoTipTitle');
    const tipContent = document.getElementById('bentoTipContent');

    function showTooltip(x, y, title, rows) {
        if (!tipEl) return;
        tipTitle.textContent = title;
        tipContent.innerHTML = rows.map(r => `
            <div class="bento-tooltip-row">
                <span style="color: #94A3B8; display: inline-flex; align-items: center; gap: 5px;">
                    ${r.color ? `<i style="display:inline-block; width:7px; height:7px; border-radius:2px; background:${r.color};"></i>` : ''}
                    ${r.label}
                </span>
                <b style="color: #FFFFFF; font-weight:600;">${r.value}</b>
            </div>
        `).join('');
        tipEl.style.left = (x + 12) + 'px';
        tipEl.style.top = (y - 12) + 'px';
        tipEl.classList.add('visible');
    }

    function hideTooltip() {
        if (!tipEl) return;
        tipEl.classList.remove('visible');
    }

    const easeOutCubic = t => 1 - Math.pow(1 - t, 3);
    function runCountUp(elId, targetVal, prefix = '') {
        const el = document.getElementById(elId);
        if (!el) return;
        const start = 0;
        const t0 = performance.now();
        const duration = 1000;
        function step(now) {
            const t = Math.min(1, (now - t0) / duration);
            const v = Math.round(start + (targetVal - start) * easeOutCubic(t));
            el.textContent = prefix + v.toLocaleString('id-ID');
            if (t < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function smoothPath(P) {
        const n = P.length;
        if (n < 2) return "";
        const g = i => P[Math.max(0, Math.min(n - 1, i))];
        let d = `M${P[0][0]},${P[0][1]}`;
        for (let i = 0; i < n - 1; i++) {
            const p0 = g(i - 1), p1 = g(i), p2 = g(i + 1), p3 = g(i + 2), k = 0.18;
            d += ` C${p1[0] + (p2[0] - p0[0]) * k},${p1[1] + (p2[1] - p0[1]) * k} ${p2[0] - (p3[0] - p1[0]) * k},${p2[1] - (p3[1] - p1[1]) * k} ${p2[0]},${p2[1]}`;
        }
        return d;
    }

    /* 1. Revenue Chart */
    function initRevenueChart() {
        const box = document.getElementById('boxRevenueChart');
        const svg = document.getElementById('svgRevenueChart');
        if (!box || !svg) return;

        const W = box.clientWidth || 240;
        const H = 125;
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);

        const series = [
            7420, 7510, 7460, 7380, 7490, 7640, 7720, 7680, 7590, 7560,
            7630, 7780, 7900, 7960, 7910, 7850, 7880, 8010, 8140, 8230,
            8190, 8120, 8260, 8430, 8560, 8620, 8700, 8890, 9020, 9134
        ];
        const minV = Math.min(...series) * 0.95;
        const maxV = Math.max(...series) * 1.05;
        const span = maxV - minV || 1;

        const topPad = 15, botPad = 10;
        const Y = v => topPad + (1 - (v - minV) / span) * (H - topPad - botPad);
        const P = series.map((v, i) => [10 + (i / (series.length - 1)) * (W - 20), Y(v)]);
        const d = smoothPath(P);
        const lastP = P[P.length - 1];

        svg.innerHTML = `
            <defs>
                <linearGradient id="bentoRevGradLine" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="#10B981" />
                    <stop offset="100%" stop-color="#059669" />
                </linearGradient>
                <linearGradient id="bentoRevGradArea" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#10B981" stop-opacity="0.22" />
                    <stop offset="100%" stop-color="#10B981" stop-opacity="0.0" />
                </linearGradient>
            </defs>
            <path d="${d} L${lastP[0]},${H} L${P[0][0]},${H} Z" fill="url(#bentoRevGradArea)" />
            <path d="${d}" fill="none" stroke="url(#bentoRevGradLine)" stroke-width="2.2" stroke-linecap="round" />
            <circle cx="${lastP[0]}" cy="${lastP[1]}" r="4" fill="#059669" stroke="#FFFFFF" stroke-width="1.8" />
            <rect id="bentoRevHoverHit" width="${W}" height="${H}" fill="transparent" style="cursor:crosshair;" />
            <g id="bentoRevHoverGroup" style="display:none;">
                <line id="bentoRevHoverLine" x1="0" y1="0" x2="0" y2="${H}" stroke="#94A3B8" stroke-dasharray="3 3" stroke-width="1" />
                <circle id="bentoRevHoverDot" cx="0" cy="0" r="4" fill="#059669" stroke="#FFFFFF" stroke-width="1.8" />
            </g>
        `;

        const hit = document.getElementById('bentoRevHoverHit');
        const hGroup = document.getElementById('bentoRevHoverGroup');
        const hLine = document.getElementById('bentoRevHoverLine');
        const hDot = document.getElementById('bentoRevHoverDot');

        hit.addEventListener('mousemove', e => {
            const r = svg.getBoundingClientRect();
            const mouseX = e.clientX - r.left;
            const idx = Math.max(0, Math.min(series.length - 1, Math.round(((mouseX - 10) / (W - 20)) * (series.length - 1))));
            const pt = P[idx];
            hGroup.style.display = 'block';
            hLine.setAttribute('x1', pt[0]);
            hLine.setAttribute('x2', pt[0]);
            hDot.setAttribute('cx', pt[0]);
            hDot.setAttribute('cy', pt[1]);

            showTooltip(e.clientX, e.clientY, `Hari ke-${idx + 1}`, [
                { label: 'Omset', value: 'Rp ' + (series[idx] * 1000).toLocaleString('id-ID'), color: '#10B981' }
            ]);
        });

        hit.addEventListener('mouseleave', () => {
            hGroup.style.display = 'none';
            hideTooltip();
        });
    }

    /* 2. Sessions Dual Wave Chart */
    let waveRaf = 0;
    function initSessionsChart() {
        const box = document.getElementById('boxSessionsChart');
        const svg = document.getElementById('svgSessionsChart');
        if (!box || !svg) return;

        const W = box.clientWidth || 320;
        const H = 125;
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);

        const N = 24;
        const midY = H / 2;
        const amp = 30; // Controlled elegant amplitude

        svg.innerHTML = `
            <defs>
                <linearGradient id="bentoDeskGrad" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="#06B6D4" />
                    <stop offset="100%" stop-color="#3B82F6" />
                </linearGradient>
                <linearGradient id="bentoMobGrad" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="#F59E0B" />
                    <stop offset="100%" stop-color="#EC4899" />
                </linearGradient>
            </defs>
            <line x1="12" y1="${midY}" x2="${W - 12}" y2="${midY}" stroke="#E2E8F0" stroke-width="1" />
            <path id="bentoWaveDesk" fill="none" stroke="url(#bentoDeskGrad)" stroke-width="2.2" stroke-linecap="round" />
            <path id="bentoWaveMob" fill="none" stroke="url(#bentoMobGrad)" stroke-width="2" stroke-linecap="round" />
        `;

        const pathDesk = document.getElementById('bentoWaveDesk');
        const pathMob = document.getElementById('bentoWaveMob');
        const t0 = performance.now();

        function renderWave(now) {
            const t = (now - t0) / 1000;
            const ptsDesk = [];
            const ptsMob = [];

            for (let i = 0; i < N; i++) {
                const x = 12 + (i / (N - 1)) * (W - 24);
                const breath1 = Math.sin(t * 1.5 + i * 0.4) * 0.28 + 0.72;
                const breath2 = Math.cos(t * 1.3 + i * 0.45) * 0.28 + 0.72;
                const y1 = midY - (Math.sin((i / N) * Math.PI) * amp * breath1);
                const y2 = midY + (Math.sin((i / N) * Math.PI) * amp * breath2);
                ptsDesk.push([x, y1]);
                ptsMob.push([x, y2]);
            }

            pathDesk.setAttribute('d', smoothPath(ptsDesk));
            pathMob.setAttribute('d', smoothPath(ptsMob));
            waveRaf = requestAnimationFrame(renderWave);
        }
        cancelAnimationFrame(waveRaf);
        waveRaf = requestAnimationFrame(renderWave);
    }

    /* 3. Treemap Customer Categories */
    const categoriesData = [
        { name: "Toko CCTV", color: "#10B981", ink: "light", values: [420, 1850, 24500] },
        { name: "Dealer / Grosir", color: "#8B5CF6", ink: "light", values: [280, 1120, 14200] },
        { name: "Toko Komputer / IT", color: "#3B82F6", ink: "light", values: [195, 840, 9800] },
        { name: "Retail & End-User", color: "#F59E0B", ink: "dark", values: [160, 680, 8100] },
        { name: "Project Integrator", color: "#EF4444", ink: "light", values: [120, 510, 6200] }
    ];

    let activeTreemapPeriod = 0;
    window.switchBentoPeriod = function(periodIdx, btn) {
        activeTreemapPeriod = periodIdx;
        document.querySelectorAll('.bento-card-bottom .bento-seg-btn').forEach(b => {
            if (b.parentElement === btn.parentElement) b.classList.remove('active');
        });
        btn.classList.add('active');
        renderTreemap(periodIdx);
    };

    function squarifyTreemap(items, x, y, w, h) {
        const total = items.reduce((a, b) => a + b.v, 0) || 1;
        const scale = (w * h) / total;
        const out = [];
        let rest = items.map(i => ({ ...i, a: i.v * scale }));
        const worst = (row, len) => {
            const s = row.reduce((a, b) => a + b.a, 0);
            const mx = Math.max(...row.map(r => r.a));
            const mn = Math.min(...row.map(r => r.a));
            return Math.max((len * len * mx) / (s * s), (s * s) / (len * len * mn));
        };
        while (rest.length) {
            const len = Math.min(w, h);
            const row = [rest[0]];
            let i = 1;
            while (i < rest.length && worst([...row, rest[i]], len) <= worst(row, len)) row.push(rest[i++]);
            rest = rest.slice(i);
            const s = row.reduce((a, b) => a + b.a, 0);
            if (w >= h) {
                const cw = s / h;
                let cy = y;
                for (const r of row) {
                    const rh = r.a / cw;
                    out.push({ ...r, x, y: cy, w: cw, h: rh });
                    cy += rh;
                }
                x += cw;
                w -= cw;
            } else {
                const rh = s / w;
                let cx = x;
                for (const r of row) {
                    const rw = r.a / rh;
                    out.push({ ...r, x: cx, y: y, w: rw, h: rh });
                    cx += rw;
                }
                y += rh;
                h -= rh;
            }
        }
        return out;
    }

    function renderTreemap(periodIdx = 0) {
        const container = document.getElementById('bentoTreemapContainer');
        if (!container) return;

        const W = container.clientWidth || 550;
        const H = 200;
        const items = categoriesData.map(c => ({
            name: c.name,
            color: c.color,
            ink: c.ink,
            v: c.values[periodIdx]
        })).sort((a, b) => b.v - a.v);

        const total = items.reduce((a, b) => a + b.v, 0);
        const rects = squarifyTreemap(items, 0, 0, W, H);

        container.innerHTML = rects.map(r => {
            const share = ((r.v / total) * 100).toFixed(1) + '%';
            const textColor = r.ink === 'light' ? '#FFFFFF' : '#0F172A';
            return `
                <div class="bento-treemap-tile" style="
                    left: ${Math.round(r.x)}px;
                    top: ${Math.round(r.y)}px;
                    width: ${Math.max(10, Math.round(r.w) - 6)}px;
                    height: ${Math.max(10, Math.round(r.h) - 6)}px;
                    background: ${r.color};
                    color: ${textColor};
                " onmousemove="window.bentoTipHover(event, '${r.name}', '${r.v.toLocaleString('id-ID')} Toko', '${share}', '${r.color}')" onmouseleave="window.bentoTipLeave()">
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em;">
                        ${r.name}
                    </div>
                    <div class="d-flex align-items-end justify-content-between">
                        <span style="font-family:'Geist Mono', monospace; font-size: 17px; font-weight: 700; line-height: 1;">
                            ${r.v.toLocaleString('id-ID')}
                        </span>
                        <span style="font-size: 9.5px; background: rgba(0,0,0,0.45); color:#fff; padding: 2px 5px; border-radius: 5px; font-weight: 600;">
                            ${share}
                        </span>
                    </div>
                </div>
            `;
        }).join('');
    }

    window.bentoTipHover = function(e, name, val, share, color) {
        showTooltip(e.clientX, e.clientY, name, [
            { label: 'Customer', value: val, color: color },
            { label: 'Pangsa', value: share }
        ]);
    };
    window.bentoTipLeave = function() { hideTooltip(); };

    /* 4. Radar Seasonality */
    const radarData = {
        years: [
            { label: "2026", values: [16, 20, 24, 18, 28, 21, 17, 25, 30, 22, 32, 23], avg: "Rp 82.5M", total: "Rp 980M" },
            { label: "2025", values: [13, 16, 18, 22, 20, 15, 14, 19, 23, 26, 23, 19], avg: "Rp 68.2M", total: "Rp 810M" }
        ],
        months: ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agt", "Sep", "Okt", "Nov", "Des"]
    };

    let activeRadarYear = 0;
    window.switchRadarYear = function(idx, btn) {
        document.querySelectorAll('.bento-card-bottom:last-child .bento-seg-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeRadarYear = idx;
        renderRadar(idx);
        document.getElementById('bentoRadarAvg').textContent = radarData.years[idx].avg;
        document.getElementById('bentoRadarTotal').textContent = radarData.years[idx].total;
    };

    function renderRadar(yearIdx = 0) {
        const box = document.getElementById('bentoRadarContainer');
        const svg = document.getElementById('svgRadarChart');
        if (!box || !svg) return;

        const S = 230;
        const C = S / 2;
        const R = S / 2 - 28;
        svg.setAttribute('viewBox', `0 0 ${S} ${S}`);

        const vals = radarData.years[yearIdx].values;
        const N = vals.length;
        const minV = Math.min(...vals);
        const maxV = Math.max(...vals);

        const norm = vals.map(v => 0.38 + ((v - minV) / (maxV - minV || 1)) * 0.62);

        const K = 3;
        const co = [];
        for (let k = 0; k <= K; k++) {
            let a = 0, b = 0;
            norm.forEach((x, i) => {
                const th = (i / N) * Math.PI * 2;
                a += x * Math.cos(k * th);
                b += x * Math.sin(k * th);
            });
            co.push([(a * (k ? 2 : 1)) / N, (b * (k ? 2 : 1)) / N]);
        }
        const fitFunc = th => Math.max(0.1, co.reduce((r, [a, b], k) => r + a * Math.cos(k * th) + b * Math.sin(k * th), 0));

        let pathD = '';
        for (let j = 0; j <= 120; j++) {
            const th = (j / 120) * Math.PI * 2;
            const rad = R * 0.2 + fitFunc(th) * R * 0.8;
            const a = -Math.PI / 2 + th;
            const px = C + Math.cos(a) * rad;
            const py = C + Math.sin(a) * rad;
            pathD += (j === 0 ? 'M' : 'L') + px.toFixed(1) + ',' + py.toFixed(1);
        }
        pathD += 'Z';

        let circles = [0.33, 0.66, 1.0].map(k => `
            <circle cx="${C}" cy="${C}" r="${R * k}" fill="none" stroke="#E2E8F0" stroke-width="1" />
        `).join('');

        let axes = radarData.months.map((m, i) => {
            const a = -Math.PI / 2 + (i / N) * Math.PI * 2;
            const x1 = C + Math.cos(a) * (R * 0.2);
            const y1 = C + Math.sin(a) * (R * 0.2);
            const x2 = C + Math.cos(a) * R;
            const y2 = C + Math.sin(a) * R;
            const tx = C + Math.cos(a) * (R + 14);
            const ty = C + Math.sin(a) * (R + 14);
            return `
                <line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="#E2E8F0" stroke-width="1" />
                <text x="${tx}" y="${ty}" text-anchor="middle" dominant-baseline="central" font-size="9" fill="#64748B" font-family="'Geist Mono', monospace" font-weight="600">${m}</text>
            `;
        }).join('');

        svg.innerHTML = `
            ${circles}
            ${axes}
            <path d="${pathD}" fill="rgba(16, 185, 129, 0.16)" stroke="#10B981" stroke-width="2.2" stroke-linejoin="round" />
            <circle cx="${C}" cy="${C}" r="${R * 0.2}" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.2" />
            <text x="${C}" y="${C}" text-anchor="middle" dominant-baseline="central" font-size="12">🎯</text>
        `;
    }

    function initAllBento() {
        runCountUp('bentoRevVal', 9134000, 'Rp ');
        initRevenueChart();
        initSessionsChart();
        renderTreemap(0);
        renderRadar(0);
    }

    window.addEventListener('load', initAllBento);
    window.addEventListener('resize', () => {
        initRevenueChart();
        initSessionsChart();
        renderTreemap(activeTreemapPeriod);
        renderRadar(activeRadarYear);
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initAllBento, 80);
    }
})();
</script>
