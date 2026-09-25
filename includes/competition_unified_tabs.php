<?php
/**
 * UNIFIED COMPETITION & LEADERBOARD TAB SWITCHER
 * Menggabungkan:
 * 1. Bonus Sultan Loewix (Kompetisi 1 Bulan)
 * 2. Program Per Semester (Sirkuit Lari 3 Bulan)
 * Menjadi satu panel kontrol yang rapi, compact, dan modern.
 */
?>
<style>
/* Competition Unified Tab Navigation */
.competition-unified-container {
    margin-bottom: 30px;
}

.comp-header-bar {
    background: #FFFFFF;
    border: 1.5px solid #E2E8F0;
    border-radius: 24px;
    padding: 16px 24px;
    margin-bottom: 18px;
    box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.05);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.comp-header-title-box {
    display: flex;
    align-items: center;
    gap: 14px;
}

.comp-icon-badge {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    box-shadow: 0 8px 18px -3px rgba(245, 158, 11, 0.4);
    flex-shrink: 0;
}

.comp-header-title {
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    font-size: 18px;
    font-weight: 800;
    color: #0F172A;
    margin: 0;
    line-height: 1.2;
}

.comp-header-subtitle {
    font-size: 12.5px;
    color: #64748B;
    font-weight: 600;
    margin: 0;
}

.comp-pills-nav {
    display: inline-flex;
    background: #F1F5F9;
    padding: 5px;
    border-radius: 40px;
    gap: 6px;
    border: 1px solid #E2E8F0;
}

.comp-pill-btn {
    border: none;
    background: transparent;
    padding: 9px 20px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 800;
    color: #64748B;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    white-space: nowrap;
}

.comp-pill-btn:hover {
    color: #0F172A;
    background: rgba(255, 255, 255, 0.6);
}

.comp-pill-btn.active {
    background: #0F172A;
    color: #FFFFFF;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);
}

.comp-pill-btn.active.btn-sultan {
    background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
    color: #FFFFFF;
    box-shadow: 0 6px 18px rgba(220, 38, 38, 0.35);
}

.comp-pill-btn.active.btn-semester {
    background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%);
    color: #FFFFFF;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
}

.comp-tab-content-box {
    position: relative;
    min-height: 100px;
}

.comp-tab-pane {
    animation: fadeInTab 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeInTab {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .comp-header-bar {
        padding: 16px;
    }
    .comp-pills-nav {
        width: 100%;
        justify-content: stretch;
    }
    .comp-pill-btn {
        flex: 1;
        justify-content: center;
        padding: 8px 12px;
        font-size: 12px;
    }
}
</style>

<div class="competition-unified-container">
    <!-- Header Control Bar with Tab Switcher -->
    <div class="comp-header-bar">
        <div class="comp-header-title-box">
            <div class="comp-icon-badge">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div>
                <h4 class="comp-header-title">Pusat Leaderboard &amp; Kompetisi Sales</h4>
                <p class="comp-header-subtitle">Pilih program kompetisi untuk melihat papan peringkat &amp; target omset</p>
            </div>
        </div>

        <div class="comp-pills-nav" role="tablist">
            <button type="button" class="comp-pill-btn btn-sultan active" id="btnTabSultan" onclick="switchCompetitionView('sultan')" title="Kompetisi Bonus 1 Bulan">
                <span>🔥</span>
                <span>Bonus Sultan (1 Bulan)</span>
            </button>
            <button type="button" class="comp-pill-btn btn-semester" id="btnTabSemester" onclick="switchCompetitionView('semester')" title="Program Semester 3 Bulan">
                <i class="bi bi-flag-fill"></i>
                <span>Program Semester (3 Bulan)</span>
            </button>
        </div>
    </div>

    <!-- Tab Content Panes -->
    <div class="comp-tab-content-box">
        <!-- PANE 1: BONUS SULTAN LOEWIX (1 BULAN) -->
        <div id="paneCompetitionSultan" class="comp-tab-pane">
            <?php include __DIR__ . '/bonus_competition_widget.php'; ?>
        </div>

        <!-- PANE 2: PROGRAM PER SEMESTER DALAM 3 BULAN (SIRKUIT LARI) -->
        <div id="paneCompetitionSemester" class="comp-tab-pane d-none">
            <?php include __DIR__ . '/sales_ranking_widget.php'; ?>
        </div>
    </div>
</div>

<script>
function switchCompetitionView(tabName) {
    const paneSultan = document.getElementById('paneCompetitionSultan');
    const paneSemester = document.getElementById('paneCompetitionSemester');
    const btnSultan = document.getElementById('btnTabSultan');
    const btnSemester = document.getElementById('btnTabSemester');

    if (!paneSultan || !paneSemester || !btnSultan || !btnSemester) return;

    if (tabName === 'semester') {
        paneSultan.classList.add('d-none');
        paneSemester.classList.remove('d-none');
        btnSultan.classList.remove('active');
        btnSemester.classList.add('active');
        localStorage.setItem('loewix_active_comp_tab', 'semester');
    } else {
        paneSemester.classList.add('d-none');
        paneSultan.classList.remove('d-none');
        btnSemester.classList.remove('active');
        btnSultan.classList.add('active');
        localStorage.setItem('loewix_active_comp_tab', 'sultan');
    }

    // Trigger window resize so Chart.js / Track layout aligns immediately
    setTimeout(() => {
        window.dispatchEvent(new Event('resize'));
    }, 100);
}

// Restore user's previous active tab
document.addEventListener('DOMContentLoaded', () => {
    const savedTab = localStorage.getItem('loewix_active_comp_tab');
    if (savedTab === 'semester') {
        switchCompetitionView('semester');
    }
});
</script>
