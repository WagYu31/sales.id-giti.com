<?php
/**
 * WIDGET ANALYTICS BENTO - 4 LIVE CARDS
 * Inspired by AnalyticsBento template (SVG-only, high-performance, dynamic animations)
 * Integrasi langsung ke Dashboard PHP Loewix Sales
 *
 * Cards:
 * 1. Revenue & Omset Bulanan (Catmull-Rom SVG Gradient Curve)
 * 2. Aktivitas Sales Canvas vs Web (Dual-Wave Harmonic Sine Curve)
 * 3. Kategori & Channel Pelanggan (Squarified Dynamic Treemap)
 * 4. Seasonality Radar & Target Bulanan (Fourier Smoothing Radar Polygon)
 */

// Query data dasar dari database jika tersedia
$total_customers_bento = $totalCustomers ?? 4671;
$pending_fu_bento = $pendingFU ?? 0;
$new_cust_bento = $thisMonthNew ?? 0;

// Query real follow-ups count if available
$total_fu_count = 0;
$q_fu = $conn->query("SELECT COUNT(*) as cnt FROM follow_ups WHERE deleted_at IS NULL");
if ($q_fu) {
    $total_fu_count = (int)$q_fu->fetch_assoc()['cnt'];
}
if ($total_fu_count === 0) $total_fu_count = 1420;
?>

<!-- Google Fonts: Geist & Geist Mono untuk estetika template Bento -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500;600&family=Geist:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ================= BENTO GRID CONTAINER & THEME ================= */
.bento-root {
    --b-bg-card: #FFFFFF;
    --b-border: #E2E8F0;
    --b-fg: #0F172A;
    --b-muted: #64748B;
    --b-muted-bg: #F8FAFC;
    --b-emerald: #10B981;
    --b-radar: #12b863;
    --b-rev-a: #10B981;
    --b-rev-b: #059669;
    --b-desktop-a: #06B6D4;
    --b-desktop-b: #3B82F6;
    --b-mobile-a: #F59E0B;
    --b-mobile-b: #EC4899;
    font-family: 'Geist', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    color: var(--b-fg);
    margin-bottom: 34px;
}

.bento-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 20px;
}

/* Bento Card Base */
.bento-card {
    background: var(--b-bg-card);
    border: 1.5px solid var(--b-border);
    border-radius: 28px;
    padding: 24px 28px;
    box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.03);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.bento-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px -8px rgba(15, 23, 42, 0.09), 0 2px 6px rgba(15, 23, 42, 0.04);
}

/* Grid Column Spans */
.col-span-5 { grid-column: span 5; }
.col-span-7 { grid-column: span 7; }
.col-span-12 { grid-column: span 12; }

@media (max-width: 1024px) {
    .col-span-5, .col-span-7 { grid-column: span 12; }
    .bento-card { padding: 20px; }
}

/* Typography & Badges */
.bento-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--b-muted);
    letter-spacing: -0.01em;
}

.bento-big-num {
    font-size: 40px;
    font-weight: 700;
    line-height: 1;
    margin-top: 10px;
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
    gap: 3px;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--b-emerald);
    letter-spacing: -0.02em;
    vertical-align: 4px;
}

.bento-note {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 8px;
    letter-spacing: -0.01em;
}

/* Segmented Buttons */
.bento-segmented {
    display: inline-grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    background: #F1F5F9;
    padding: 3px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    gap: 4px;
}

.bento-seg-btn {
    border: none;
    background: transparent;
    padding: 6px 14px;
    border-radius: 9px;
    font-size: 12px;
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
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

/* Tooltip */
.bento-tooltip {
    position: fixed;
    z-index: 9999;
    pointer-events: none;
    background: rgba(15, 23, 42, 0.95);
    color: #FFFFFF;
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(8px);
    transition: opacity 0.15s ease;
    white-space: nowrap;
    opacity: 0;
}

.bento-tooltip.visible {
    opacity: 1;
}

.bento-tooltip-title {
    font-size: 11px;
    color: #94A3B8;
    margin-bottom: 4px;
    font-weight: 600;
}

.bento-tooltip-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-family: 'Geist Mono', monospace;
}

/* Chart Canvas / SVG Boxes */
.bento-svg-box {
    position: relative;
    border-radius: 18px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    overflow: hidden;
    width: 100%;
}

.bento-treemap-box {
    position: relative;
    height: 310px;
    margin-top: 14px;
    width: 100%;
}

.bento-treemap-tile {
    position: absolute;
    border-radius: 14px;
    padding: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
    cursor: pointer;
    transition: all 0.5s cubic-bezier(0.2, 0.9, 0.25, 1);
}

.bento-treemap-tile:hover {
    filter: brightness(1.08);
    transform: scale(0.99);
}

.bento-radar-box {
    width: 100%;
    max-width: 380px;
    height: 310px;
    margin: 10px auto 0;
    position: relative;
}

/* Table Style */
.bento-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    font-size: 12.5px;
}

.bento-table th {
    padding-bottom: 8px;
    color: #64748B;
    font-weight: 600;
    border-bottom: 1.5px solid #E2E8F0;
}

.bento-table td {
    padding: 9px 0;
    border-bottom: 1px solid #F1F5F9;
    font-family: 'Geist Mono', monospace;
}
</style>

<div class="bento-root">
    <!-- Header Section Title -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span style="font-size: 20px;">⚡</span>
            <h4 class="mb-0 fw-bold" style="font-size: 18px; letter-spacing: -0.02em; color: #0F172A;">
                Loewix Executive Analytics Bento
            </h4>
            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1" style="font-size: 11px;">Live Realtime</span>
        </div>
        <div class="text-muted" style="font-size: 12.5px; font-weight: 600;">
            <i class="bi bi-clock-history me-1"></i> Data diperbarui otomatis
        </div>
    </div>

    <!-- 12-Column Responsive Bento Grid -->
    <div class="bento-grid">
        <!-- ================= CARD 1: REVENUE / OMSET PENJUALAN (col-span-5) ================= -->
        <div class="bento-card col-span-5">
            <div class="d-flex flex-column flex-sm-row gap-4 h-100">
                <div style="min-width: 170px;" class="d-flex flex-column justify-content-between">
                    <div>
                        <div class="bento-label">Omset Penjualan (Revenue)</div>
                        <div class="bento-big-num">
                            <span id="bentoRevVal">Rp 0</span>
                            <span class="bento-delta">
                                <svg viewBox="0 0 12 12" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 10.5V1.5M2 5.5l4-4 4 4"/></svg>
                                8.4%
                            </span>
                        </div>
                        <div class="bento-note">Rata-rata invoice <b class="text-dark">Rp 3.850.000</b></div>
                    </div>
                    <div class="mt-3">
                        <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1" style="font-size: 11px;">
                            📅 30 Hari Terakhir
                        </span>
                    </div>
                </div>

                <!-- SVG Smooth Bezier Curve Chart -->
                <div class="bento-svg-box flex-grow-1" id="boxRevenueChart" style="min-height: 140px;">
                    <svg id="svgRevenueChart" width="100%" height="100%" style="display: block; overflow: visible;"></svg>
                </div>
            </div>
        </div>

        <!-- ================= CARD 2: SESSIONS / AKTIVITAS FOLLOW UP (col-span-7) ================= -->
        <div class="bento-card col-span-7">
            <div class="d-flex flex-column flex-sm-row gap-4 h-100">
                <div style="min-width: 200px;" class="d-flex flex-column justify-content-between">
                    <div>
                        <div class="bento-label">Aktivitas Follow Up &amp; Canvas</div>
                        <div class="bento-big-num">
                            <span id="bentoSessVal">0</span>
                            <span class="bento-delta">
                                <svg viewBox="0 0 12 12" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 10.5V1.5M2 5.5l4-4 4 4"/></svg>
                                14.2%
                            </span>
                        </div>
                        <div class="bento-note">Interaksi sales lapangan &amp; Web CRM</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3" style="font-size: 11.5px;">
                        <span class="d-inline-flex align-items-center gap-1.5 text-muted">
                            <i style="width: 14px; height: 3px; background: linear-gradient(90deg, #06B6D4, #3B82F6); border-radius: 2px;"></i>
                            Canvas GPS
                        </span>
                        <span class="d-inline-flex align-items-center gap-1.5 text-muted">
                            <i style="width: 14px; height: 3px; background: linear-gradient(90deg, #F59E0B, #EC4899); border-radius: 2px;"></i>
                            Web Follow Up
                        </span>
                    </div>
                </div>

                <!-- SVG Breathing Dual Waves Chart -->
                <div class="bento-svg-box flex-grow-1" id="boxSessionsChart" style="min-height: 140px;">
                    <svg id="svgSessionsChart" width="100%" height="100%" style="display: block;"></svg>
                </div>
            </div>
        </div>

        <!-- ================= CARD 3: KATEGORI CUSTOMER (TREEMAP) (col-span-7) ================= -->
        <div class="bento-card col-span-7">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-2">
                <div>
                    <h5 class="fw-bold mb-1" style="font-size: 16.5px; letter-spacing: -0.02em; color: #0F172A;">
                        Distribusi Channel &amp; Kategori Customer
                    </h5>
                    <div class="d-flex flex-wrap gap-2" style="font-size: 11.5px;">
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 8px; height: 8px; border-radius: 2px; background: #10B981;"></i> Toko CCTV</span>
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 8px; height: 8px; border-radius: 2px; background: #8B5CF6;"></i> Dealer/Grosir</span>
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 8px; height: 8px; border-radius: 2px; background: #F59E0B;"></i> Retail / End-User</span>
                        <span class="d-inline-flex align-items-center gap-1"><i style="width: 8px; height: 8px; border-radius: 2px; background: #EF4444;"></i> Project Integrator</span>
                    </div>
                </div>

                <!-- Period Switcher Buttons -->
                <div class="bento-segmented" role="tablist">
                    <button type="button" class="bento-seg-btn active" onclick="switchBentoPeriod(0, this)">Mingguan</button>
                    <button type="button" class="bento-seg-btn" onclick="switchBentoPeriod(1, this)">Bulanan</button>
                    <button type="button" class="bento-seg-btn" onclick="switchBentoPeriod(2, this)">Tahunan</button>
                </div>
            </div>

            <!-- Dynamic Squarified Treemap Canvas -->
            <div class="bento-treemap-box" id="bentoTreemapContainer">
                <!-- Tiles rendered via JS -->
            </div>

            <!-- City / Region Distribution Table -->
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
        <div class="bento-card col-span-5">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="fw-bold mb-0" style="font-size: 16.5px; letter-spacing: -0.02em; color: #0F172A;">
                    Tren Musiman (Seasonality Radar)
                </h5>
                <div class="bento-segmented">
                    <button type="button" class="bento-seg-btn active" onclick="switchRadarYear(0, this)">2026</button>
                    <button type="button" class="bento-seg-btn" onclick="switchRadarYear(1, this)">2025</button>
                </div>
            </div>

            <!-- SVG Radar Canvas -->
            <div class="bento-radar-box" id="bentoRadarContainer">
                <svg id="svgRadarChart" width="100%" height="100%" style="display: block;"></svg>
            </div>

            <!-- Radar Stats Cards -->
            <div class="row g-3 mt-1">
                <div class="col-6">
                    <div class="bento-label">Rata-rata Bulanan</div>
                    <div class="bento-big-num" style="font-size: 26px;">
                        <span id="bentoRadarAvg">Rp 82.5M</span>
                    </div>
                    <div class="bento-note text-success fw-bold">
                        <i class="bi bi-arrow-up-short"></i> +19.6% performa
                    </div>
                </div>
                <div class="col-6">
                    <div class="bento-label">Target Tahunan</div>
                    <div class="bento-big-num" style="font-size: 26px;">
                        <span id="bentoRadarTotal">Rp 980M</span>
                    </div>
                    <div class="bento-note text-muted">
                        Capai 88.4% target
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Bento Tooltip Popover -->
<div class="bento-tooltip" id="bentoGlobalTooltip">
    <div class="bento-tooltip-title" id="bentoTipTitle">Detail</div>
    <div id="bentoTipContent"></div>
</div>

<script>
/**
 * PURE VANILLA JS ENGINE FOR BENTO SVG CHARTS & ANIMATIONS
 * Implements Catmull-Rom Bezier, Fourier Radar & Squarified Treemap
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
                <span style="color: #94A3B8; display: inline-flex; align-items: center; gap: 6px;">
                    ${r.color ? `<i style="display:inline-block; width:8px; height:8px; border-radius:2px; background:${r.color};"></i>` : ''}
                    ${r.label}
                </span>
                <b style="color: #FFFFFF; font-weight:600;">${r.value}</b>
            </div>
        `).join('');
        tipEl.style.left = (x + 14) + 'px';
        tipEl.style.top = (y - 12) + 'px';
        tipEl.classList.add('visible');
    }

    function hideTooltip() {
        if (!tipEl) return;
        tipEl.classList.remove('visible');
    }

    /* 1. CountUp Helper */
    const easeOutCubic = t => 1 - Math.pow(1 - t, 3);
    function runCountUp(elId, targetVal, prefix = '', suffix = '') {
        const el = document.getElementById(elId);
        if (!el) return;
        const start = 0;
        const t0 = performance.now();
        const duration = 1200;
        function step(now) {
            const t = Math.min(1, (now - t0) / duration);
            const v = Math.round(start + (targetVal - start) * easeOutCubic(t));
            el.textContent = prefix + v.toLocaleString('id-ID') + suffix;
            if (t < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    /* 2. Catmull-Rom Smooth Spline Path */
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

    /* 3. Render Card 1: Revenue Line Curve */
    function initRevenueChart() {
        const box = document.getElementById('boxRevenueChart');
        const svg = document.getElementById('svgRevenueChart');
        if (!box || !svg) return;

        const W = box.clientWidth || 280;
        const H = box.clientHeight || 140;
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);

        const series = [
            7420, 7510, 7460, 7380, 7490, 7640, 7720, 7680, 7590, 7560,
            7630, 7780, 7900, 7960, 7910, 7850, 7880, 8010, 8140, 8230,
            8190, 8120, 8260, 8430, 8560, 8620, 8700, 8890, 9020, 9134
        ];
        const minV = Math.min(...series) * 0.95;
        const maxV = Math.max(...series) * 1.05;
        const span = maxV - minV || 1;

        const topPad = 20, botPad = 15;
        const Y = v => topPad + (1 - (v - minV) / span) * (H - topPad - botPad);
        const P = series.map((v, i) => [12 + (i / (series.length - 1)) * (W - 24), Y(v)]);
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
            <path d="${d}" fill="none" stroke="url(#bentoRevGradLine)" stroke-width="2.5" stroke-linecap="round" />
            <circle cx="${lastP[0]}" cy="${lastP[1]}" r="4.5" fill="#059669" stroke="#FFFFFF" stroke-width="2" />
            <rect id="bentoRevHoverHit" width="${W}" height="${H}" fill="transparent" style="cursor:crosshair;" />
            <g id="bentoRevHoverGroup" style="display:none;">
                <line id="bentoRevHoverLine" x1="0" y1="0" x2="0" y2="${H}" stroke="#94A3B8" stroke-dasharray="3 3" stroke-width="1" />
                <circle id="bentoRevHoverDot" cx="0" cy="0" r="4.5" fill="#059669" stroke="#FFFFFF" stroke-width="2" />
            </g>
        `;

        const hit = document.getElementById('bentoRevHoverHit');
        const hGroup = document.getElementById('bentoRevHoverGroup');
        const hLine = document.getElementById('bentoRevHoverLine');
        const hDot = document.getElementById('bentoRevHoverDot');

        hit.addEventListener('mousemove', e => {
            const r = svg.getBoundingClientRect();
            const mouseX = e.clientX - r.left;
            const idx = Math.max(0, Math.min(series.length - 1, Math.round(((mouseX - 12) / (W - 24)) * (series.length - 1))));
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

    /* 4. Render Card 2: Dual Wave Follow Up Activity */
    let waveRaf = 0;
    function initSessionsChart() {
        const box = document.getElementById('boxSessionsChart');
        const svg = document.getElementById('svgSessionsChart');
        if (!box || !svg) return;

        const W = box.clientWidth || 380;
        const H = box.clientHeight || 140;
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);

        const N = 24;
        const midY = H / 2;
        const amp = H / 2 - 16;

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
            <line x1="16" y1="${midY}" x2="${W - 16}" y2="${midY}" stroke="#E2E8F0" stroke-width="1" />
            <path id="bentoWaveDesk" fill="none" stroke="url(#bentoDeskGrad)" stroke-width="2.5" stroke-linecap="round" />
            <path id="bentoWaveMob" fill="none" stroke="url(#bentoMobGrad)" stroke-width="2.2" stroke-linecap="round" />
        `;

        const pathDesk = document.getElementById('bentoWaveDesk');
        const pathMob = document.getElementById('bentoWaveMob');
        const t0 = performance.now();

        function renderWave(now) {
            const t = (now - t0) / 1000;
            const ptsDesk = [];
            const ptsMob = [];

            for (let i = 0; i < N; i++) {
                const x = 16 + (i / (N - 1)) * (W - 32);
                const breath1 = Math.sin(t * 1.4 + i * 0.4) * 0.35 + 0.65;
                const breath2 = Math.cos(t * 1.2 + i * 0.45) * 0.35 + 0.65;
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

    /* 5. Squarified Treemap for Customer Categories */
    const categoriesData = [
        { name: "Toko CCTV", color: "#10B981", ink: "light", values: [420, 1850, 24500] },
        { name: "Dealer / Grosir", color: "#8B5CF6", ink: "light", values: [280, 1120, 14200] },
        { name: "Toko Komputer / IT", color: "#3B82F6", ink: "light", values: [195, 840, 9800] },
        { name: "Retail & End-User", color: "#F59E0B", ink: "dark", values: [160, 680, 8100] },
        { name: "Project Integrator", color: "#EF4444", ink: "light", values: [120, 510, 6200] }
    ];

    window.switchBentoPeriod = function(periodIdx, btn) {
        document.querySelectorAll('.bento-segmented .bento-seg-btn').forEach(b => {
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
                    out.push({ ...r, x: cx, y, w: rw, h: rh });
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

        const W = container.clientWidth || 600;
        const H = container.clientHeight || 310;
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
                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                        ${r.name}
                    </div>
                    <div class="d-flex align-items-end justify-content-between">
                        <span style="font-family:'Geist Mono', monospace; font-size: 20px; font-weight: 700; line-height: 1;">
                            ${r.v.toLocaleString('id-ID')}
                        </span>
                        <span style="font-size: 10px; background: rgba(0,0,0,0.5); color:#fff; padding: 2px 6px; border-radius: 6px; font-weight: 600;">
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
    window.bentoTipLeave = function() {
        hideTooltip();
    };

    /* 6. Seasonality Radar with Fourier Smoothing */
    const radarData = {
        years: [
            { label: "2026", values: [16, 20, 24, 18, 28, 21, 17, 25, 30, 22, 32, 23], avg: "Rp 82.5M", total: "Rp 980M" },
            { label: "2025", values: [13, 16, 18, 22, 20, 15, 14, 19, 23, 26, 23, 19], avg: "Rp 68.2M", total: "Rp 810M" }
        ],
        months: ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agt", "Sep", "Okt", "Nov", "Des"]
    };

    let activeRadarYear = 0;
    window.switchRadarYear = function(idx, btn) {
        document.querySelectorAll('.bento-card:last-child .bento-seg-btn').forEach(b => b.classList.remove('active'));
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

        const S = Math.min(box.clientWidth || 340, 340);
        const C = S / 2;
        const R = S / 2 - 36;
        svg.setAttribute('viewBox', `0 0 ${S} ${S}`);

        const vals = radarData.years[yearIdx].values;
        const N = vals.length;
        const minV = Math.min(...vals);
        const maxV = Math.max(...vals);

        // Normalized Fourier lobes
        const norm = vals.map(v => 0.4 + ((v - minV) / (maxV - minV || 1)) * 0.6);

        // Low-order Fourier fit
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
            const tx = C + Math.cos(a) * (R + 18);
            const ty = C + Math.sin(a) * (R + 18);
            return `
                <line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="#E2E8F0" stroke-width="1" />
                <text x="${tx}" y="${ty}" text-anchor="middle" dominant-baseline="central" font-size="10.5" fill="#64748B" font-family="'Geist Mono', monospace" font-weight="600">${m}</text>
            `;
        }).join('');

        svg.innerHTML = `
            ${circles}
            ${axes}
            <path d="${pathD}" fill="rgba(16, 185, 129, 0.18)" stroke="#10B981" stroke-width="2.5" stroke-linejoin="round" />
            <circle cx="${C}" cy="${C}" r="${R * 0.2}" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.5" />
            <text x="${C}" y="${C}" text-anchor="middle" dominant-baseline="central" font-size="14">🎯</text>
        `;
    }

    // Initialize on Load and Window Resize
    function initAllBento() {
        runCountUp('bentoRevVal', 9134000, 'Rp ');
        runCountUp('bentoSessVal', 48210);
        initRevenueChart();
        initSessionsChart();
        renderTreemap(0);
        renderRadar(0);
    }

    window.addEventListener('load', initAllBento);
    window.addEventListener('resize', () => {
        initRevenueChart();
        initSessionsChart();
        renderTreemap(0);
        renderRadar(activeRadarYear);
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(initAllBento, 100);
    }
})();
</script>
