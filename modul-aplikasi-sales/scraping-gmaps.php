<?php
include "conn.php";
include "session.php";
include "get-user-data.php";
$pageNow = "Scraping GMaps";
$currentPage = "Today";

// Hanya Admin & Super Admin yang boleh akses
if (!in_array($role, ['Super Admin', 'Admin'])) {
    header('Location: index-sa.php');
    exit;
}

date_default_timezone_set('Asia/Jakarta');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scraping Google Maps — Pencarian Toko CCTV</title>
  <?php include "head.php"; ?>
  <style>
    /* ── Premium Base ── */
    .card-premium {
      background: #fff;
      border: none;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
      margin-bottom: 24px;
    }

    /* ── Header Gradient ── */
    .scraping-header {
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 40%, #2563eb 100%);
      padding: 28px 36px;
      position: relative;
      overflow: hidden;
    }
    .scraping-header::before {
      content: '';
      position: absolute;
      top: -40px; right: -20px;
      width: 180px; height: 180px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
    }
    .scraping-header::after {
      content: '';
      position: absolute;
      bottom: -50px; right: 100px;
      width: 120px; height: 120px;
      border-radius: 50%;
      background: rgba(255,255,255,0.03);
    }
    .scraping-header h5 {
      color: #fff;
      font-size: 20px;
      font-weight: 800;
      margin: 0 0 4px;
      position: relative;
      z-index: 1;
    }
    .scraping-header p {
      color: rgba(255,255,255,0.7);
      font-size: 13px;
      margin: 0;
      position: relative;
      z-index: 1;
    }

    /* ── Usage Meter ── */
    .usage-meter {
      display: flex;
      gap: 20px;
      margin-top: 18px;
      position: relative;
      z-index: 1;
    }
    .meter-card {
      flex: 1;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 12px;
      padding: 14px 18px;
      backdrop-filter: blur(10px);
    }
    .meter-label {
      font-size: 10px;
      color: rgba(255,255,255,0.6);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      font-weight: 700;
      margin-bottom: 6px;
    }
    .meter-bar {
      height: 6px;
      background: rgba(255,255,255,0.15);
      border-radius: 3px;
      overflow: hidden;
      margin-bottom: 6px;
    }
    .meter-bar-fill {
      height: 100%;
      border-radius: 3px;
      transition: width 0.6s ease;
    }
    .meter-bar-fill.safe { background: #10b981; }
    .meter-bar-fill.warning { background: #f59e0b; }
    .meter-bar-fill.danger { background: #ef4444; }
    .meter-text {
      font-size: 12px;
      color: #fff;
      font-weight: 700;
    }
    .meter-text span {
      font-weight: 400;
      color: rgba(255,255,255,0.5);
    }

    /* ── Search Form ── */
    .search-form {
      padding: 28px 36px;
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
    }
    .search-row {
      display: flex;
      gap: 14px;
      align-items: flex-end;
      flex-wrap: wrap;
    }
    .search-group {
      flex: 1;
      min-width: 180px;
    }
    .search-group label {
      display: block;
      font-size: 11px;
      font-weight: 700;
      color: #475569;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 6px;
    }
    .search-group select,
    .search-group input {
      width: 100%;
      padding: 10px 14px;
      font-size: 13px;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
      color: #1e293b;
      font-weight: 500;
      transition: all 0.2s;
    }
    .search-group select:focus,
    .search-group input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
    }
    .btn-search {
      padding: 10px 28px;
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
      white-space: nowrap;
    }
    .btn-search:hover {
      background: linear-gradient(135deg, #1d4ed8, #1e40af);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(37,99,235,0.3);
    }
    .btn-search:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    /* ── Filter Bar ── */
    .filter-bar {
      padding: 16px 36px;
      background: #fff;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      gap: 14px;
      align-items: center;
      flex-wrap: wrap;
    }
    .filter-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border: 1.5px solid #e2e8f0;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      color: #475569;
      background: #f8fafc;
      cursor: pointer;
      transition: all 0.2s;
    }
    .filter-chip:hover, .filter-chip.active {
      border-color: #3b82f6;
      color: #2563eb;
      background: #eff6ff;
    }
    .filter-input {
      width: 70px;
      padding: 5px 10px;
      font-size: 12px;
      border: 1.5px solid #e2e8f0;
      border-radius: 8px;
      text-align: center;
      font-weight: 600;
    }
    .filter-input:focus {
      outline: none;
      border-color: #3b82f6;
    }

    /* ── Results Table ── */
    .results-section {
      padding: 0;
    }
    .results-header {
      padding: 20px 36px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #f1f5f9;
    }
    .results-count {
      font-size: 14px;
      font-weight: 700;
      color: #1e293b;
    }
    .btn-import {
      padding: 10px 24px;
      background: linear-gradient(135deg, #10b981, #059669);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
    }
    .btn-import:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(16,185,129,0.3);
    }
    .btn-import:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }
    .results-table {
      width: 100%;
      border-collapse: collapse;
    }
    .results-table th {
      background: #f8fafc;
      padding: 10px 16px;
      font-size: 10px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
      position: sticky;
      top: 0;
    }
    .results-table td {
      padding: 12px 16px;
      font-size: 13px;
      color: #334155;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
    }
    .results-table tr:hover td {
      background: #f8fafc;
    }
    .results-table tr.selected td {
      background: #eff6ff;
    }

    /* ── Trust Badge ── */
    .trust-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
    }
    .trust-badge.terpercaya { background: #dcfce7; color: #15803d; }
    .trust-badge.perlu_cek { background: #fef3c7; color: #92400e; }
    .trust-badge.berisiko { background: #fee2e2; color: #dc2626; }

    /* ── Score Circle ── */
    .score-circle {
      width: 40px; height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 800;
      color: #fff;
    }
    .score-circle.high { background: linear-gradient(135deg, #10b981, #059669); }
    .score-circle.medium { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .score-circle.low { background: linear-gradient(135deg, #ef4444, #dc2626); }

    /* ── Place Name Cell ── */
    .place-name {
      font-weight: 700;
      color: #1e293b;
      font-size: 13.5px;
    }
    .place-address {
      font-size: 11.5px;
      color: #64748b;
      margin-top: 2px;
      max-width: 300px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .place-meta {
      display: flex;
      gap: 12px;
      margin-top: 4px;
    }
    .place-meta-item {
      font-size: 11px;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 3px;
    }
    .place-meta-item i { font-size: 12px; }

    /* ── Loading / Empty ── */
    .results-loading, .results-empty {
      padding: 60px 20px;
      text-align: center;
    }
    .results-loading .spinner {
      width: 40px; height: 40px;
      border: 4px solid #e2e8f0;
      border-top-color: #3b82f6;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin: 0 auto 16px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .results-empty i {
      font-size: 48px;
      color: #cbd5e1;
      margin-bottom: 12px;
    }

    /* ── Blocked Alert ── */
    .blocked-alert {
      margin: 20px 36px;
      padding: 16px 20px;
      background: #fef2f2;
      border: 1.5px solid #fecaca;
      border-radius: 12px;
      color: #991b1b;
      font-size: 13px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .blocked-alert i { font-size: 20px; color: #ef4444; }

    /* ── Import Notification ── */
    .import-toast {
      position: fixed;
      bottom: 30px;
      right: 30px;
      padding: 16px 24px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 700;
      color: #fff;
      z-index: 9999;
      transform: translateY(100px);
      opacity: 0;
      transition: all 0.4s ease;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .import-toast.show {
      transform: translateY(0);
      opacity: 1;
    }
    .import-toast.success { background: linear-gradient(135deg, #10b981, #059669); }
    .import-toast.error { background: linear-gradient(135deg, #ef4444, #dc2626); }

    .checkbox-premium {
      width: 18px; height: 18px;
      cursor: pointer;
      accent-color: #3b82f6;
    }

    .link-maps {
      color: #3b82f6;
      text-decoration: none;
      font-size: 11px;
      font-weight: 600;
    }
    .link-maps:hover { text-decoration: underline; }

    /* Responsive */
    @media (max-width: 768px) {
      .search-row { flex-direction: column; gap: 12px; }
      .search-group { width: 100%; min-width: 100%; }
      .btn-search { width: 100%; justify-content: center; min-height: 48px; font-size: 14px; touch-action: manipulation !important; }
      .usage-meter { flex-direction: column; gap: 10px; }
      .scraping-header, .search-form, .filter-bar, .results-header {
        padding-left: 16px !important;
        padding-right: 16px !important;
      }
      .filter-bar {
        overflow-x: auto;
        flex-wrap: nowrap;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 12px;
        gap: 8px;
      }
      .filter-chip {
        white-space: nowrap;
        flex-shrink: 0;
        min-height: 38px;
        padding: 6px 14px;
        touch-action: manipulation !important;
      }
      .results-table { font-size: 12px; }
    }

    <?php include "css/floating-menu2.css"; ?>
  </style>
</head>
<body class="g-sidenav-show bg-gray-200">
<?php include "cek-menu.php"; ?>
<main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
  <?php include "nav-top.php"; ?>
  <div class="container-fluid py-4">

    <div class="card-premium">
      <!-- ═══ Header + Usage Meter ═══ -->
      <div class="scraping-header">
        <h5><i class="fa-solid fa-map-location-dot me-2"></i> Scraping Google Maps</h5>
        <p>Pencarian otomatis toko CCTV dari Google Maps — langsung import ke database customer</p>

        <div class="usage-meter">
          <div class="meter-card">
            <div class="meter-label">📊 Kuota Hari Ini</div>
            <div class="meter-bar"><div class="meter-bar-fill safe" id="dailyBar" style="width:0%"></div></div>
            <div class="meter-text"><span id="dailyUsed">-</span> / <span id="dailyLimit">-</span> <span>request</span></div>
          </div>
          <div class="meter-card">
            <div class="meter-label">📅 Kuota Bulan Ini</div>
            <div class="meter-bar"><div class="meter-bar-fill safe" id="monthlyBar" style="width:0%"></div></div>
            <div class="meter-text"><span id="monthlyUsed">-</span> / <span id="monthlyLimit">-</span> <span>request</span></div>
          </div>
        </div>
      </div>

      <!-- ═══ Search Form ═══ -->
      <div class="search-form">
        <div class="search-row">
          <div class="search-group" style="flex:1.5;">
            <label><i class="fa-solid fa-magnifying-glass me-1"></i> Kata Kunci</label>
            <select id="keyword">
              <option value="Toko CCTV">Toko CCTV</option>
              <option value="Distributor CCTV">Distributor CCTV</option>
              <option value="Installer CCTV">Installer CCTV</option>
              <option value="Toko Keamanan">Toko Keamanan</option>
              <option value="Jual CCTV">Jual CCTV</option>
              <option value="Supplier CCTV">Supplier CCTV</option>
              <option value="CCTV Hikvision">CCTV Hikvision</option>
              <option value="CCTV Dahua">CCTV Dahua</option>
              <option value="Pasang CCTV">Pasang CCTV</option>
              <option value="Toko Elektronik CCTV">Toko Elektronik CCTV</option>
              <option value="Security System">Security System</option>
              <option value="Alarm CCTV">Alarm CCTV</option>
              <option value="Jasa CCTV">Jasa CCTV</option>
              <option value="Agen CCTV">Agen CCTV</option>
              <option value="custom">✏️ Ketik Manual...</option>
            </select>
          </div>
          <div class="search-group" id="customKeywordGroup" style="display:none; flex:1;">
            <label>Kata Kunci Custom</label>
            <input type="text" id="customKeyword" placeholder="Masukkan kata kunci...">
          </div>
          <div class="search-group" style="flex:1.5;">
            <label><i class="fa-solid fa-location-dot me-1"></i> Kota / Kabupaten</label>
            <select id="city">
              <optgroup label="🏙️ Jabodetabek & Banten">
                <option value="Jakarta Pusat">Jakarta Pusat</option>
                <option value="Jakarta Utara">Jakarta Utara</option>
                <option value="Jakarta Barat">Jakarta Barat</option>
                <option value="Jakarta Selatan">Jakarta Selatan</option>
                <option value="Jakarta Timur">Jakarta Timur</option>
                <option value="Tangerang" selected>Tangerang</option>
                <option value="Tangerang Selatan">Tangerang Selatan</option>
                <option value="Bekasi">Bekasi</option>
                <option value="Depok">Depok</option>
                <option value="Bogor">Bogor</option>
                <option value="Cilegon">Cilegon</option>
                <option value="Serang">Serang</option>
                <option value="Lebak">Lebak</option>
                <option value="Pandeglang">Pandeglang</option>
              </optgroup>
              <optgroup label="🏔️ Jawa Barat">
                <option value="Bandung">Bandung</option>
                <option value="Cimahi">Cimahi</option>
                <option value="Karawang">Karawang</option>
                <option value="Purwakarta">Purwakarta</option>
                <option value="Subang">Subang</option>
                <option value="Sukabumi">Sukabumi</option>
                <option value="Cianjur">Cianjur</option>
                <option value="Garut">Garut</option>
                <option value="Tasikmalaya">Tasikmalaya</option>
                <option value="Cirebon">Cirebon</option>
                <option value="Indramayu">Indramayu</option>
                <option value="Majalengka">Majalengka</option>
                <option value="Kuningan">Kuningan</option>
                <option value="Sumedang">Sumedang</option>
                <option value="Banjar">Banjar</option>
              </optgroup>
              <optgroup label="🌿 Jawa Tengah">
                <option value="Semarang">Semarang</option>
                <option value="Solo">Solo</option>
                <option value="Salatiga">Salatiga</option>
                <option value="Magelang">Magelang</option>
                <option value="Pekalongan">Pekalongan</option>
                <option value="Tegal">Tegal</option>
                <option value="Brebes">Brebes</option>
                <option value="Cilacap">Cilacap</option>
                <option value="Purwokerto">Purwokerto</option>
                <option value="Kebumen">Kebumen</option>
                <option value="Kudus">Kudus</option>
                <option value="Demak">Demak</option>
                <option value="Jepara">Jepara</option>
                <option value="Klaten">Klaten</option>
                <option value="Boyolali">Boyolali</option>
                <option value="Karanganyar">Karanganyar</option>
                <option value="Wonogiri">Wonogiri</option>
                <option value="Blora">Blora</option>
                <option value="Rembang">Rembang</option>
                <option value="Kendal">Kendal</option>
                <option value="Batang">Batang</option>
                <option value="Pemalang">Pemalang</option>
              </optgroup>
              <optgroup label="🌋 Jawa Timur">
                <option value="Surabaya">Surabaya</option>
                <option value="Malang">Malang</option>
                <option value="Sidoarjo">Sidoarjo</option>
                <option value="Gresik">Gresik</option>
                <option value="Mojokerto">Mojokerto</option>
                <option value="Pasuruan">Pasuruan</option>
                <option value="Probolinggo">Probolinggo</option>
                <option value="Lumajang">Lumajang</option>
                <option value="Jember">Jember</option>
                <option value="Banyuwangi">Banyuwangi</option>
                <option value="Kediri">Kediri</option>
                <option value="Blitar">Blitar</option>
                <option value="Tulungagung">Tulungagung</option>
                <option value="Nganjuk">Nganjuk</option>
                <option value="Madiun">Madiun</option>
                <option value="Ponorogo">Ponorogo</option>
                <option value="Lamongan">Lamongan</option>
                <option value="Tuban">Tuban</option>
                <option value="Bojonegoro">Bojonegoro</option>
              </optgroup>
              <optgroup label="🏛️ DIY Yogyakarta">
                <option value="Yogyakarta">Yogyakarta</option>
                <option value="Sleman">Sleman</option>
                <option value="Bantul">Bantul</option>
              </optgroup>
              <optgroup label="🏝️ Bali & Nusa Tenggara">
                <option value="Denpasar">Denpasar</option>
                <option value="Badung">Badung</option>
                <option value="Mataram">Mataram</option>
                <option value="Kupang">Kupang</option>
              </optgroup>
              <optgroup label="🌴 Sumatera">
                <option value="Medan">Medan</option>
                <option value="Pekanbaru">Pekanbaru</option>
                <option value="Padang">Padang</option>
                <option value="Palembang">Palembang</option>
                <option value="Bandar Lampung">Bandar Lampung</option>
                <option value="Batam">Batam</option>
                <option value="Jambi">Jambi</option>
                <option value="Bengkulu">Bengkulu</option>
                <option value="Banda Aceh">Banda Aceh</option>
              </optgroup>
              <optgroup label="🌳 Kalimantan">
                <option value="Pontianak">Pontianak</option>
                <option value="Banjarmasin">Banjarmasin</option>
                <option value="Balikpapan">Balikpapan</option>
                <option value="Samarinda">Samarinda</option>
                <option value="Palangka Raya">Palangka Raya</option>
              </optgroup>
              <optgroup label="🏔️ Sulawesi">
                <option value="Makassar">Makassar</option>
                <option value="Manado">Manado</option>
                <option value="Palu">Palu</option>
                <option value="Kendari">Kendari</option>
                <option value="Gorontalo">Gorontalo</option>
              </optgroup>
              <optgroup label="🌊 Maluku & Papua">
                <option value="Ambon">Ambon</option>
                <option value="Jayapura">Jayapura</option>
                <option value="Sorong">Sorong</option>
              </optgroup>
            </select>
          </div>
          <div class="search-group" style="flex:0.7;">
            <label><i class="fa-solid fa-bullseye me-1"></i> Radius</label>
            <select id="radius">
              <option value="10000">10 km</option>
              <option value="25000" selected>25 km</option>
              <option value="50000">50 km</option>
            </select>
          </div>
          <button class="btn-search" id="btnSearch" onclick="doSearch()">
            <i class="fa-solid fa-satellite-dish"></i> Cari Toko
          </button>
        </div>
      </div>

      <!-- ═══ Filter Bar ═══ -->
      <div class="filter-bar" id="filterBar" style="display:none;">
        <span style="font-size:11px; font-weight:700; color:#475569;"><i class="fa-solid fa-filter me-1"></i> Filter:</span>
        <div class="filter-chip active" onclick="toggleFilter(this,'trust','all')">Semua</div>
        <div class="filter-chip" onclick="toggleFilter(this,'trust','terpercaya')">🟢 Terpercaya</div>
        <div class="filter-chip" onclick="toggleFilter(this,'trust','perlu_cek')">🟡 Perlu Cek</div>
        <div class="filter-chip" onclick="toggleFilter(this,'trust','berisiko')">🔴 Berisiko</div>
        <span style="margin-left:auto; font-size:11px; font-weight:600; color:#94a3b8;">Min Review:</span>
        <input type="number" class="filter-input" id="minReview" value="0" min="0" onchange="applyFilters()">
        <span style="font-size:11px; font-weight:600; color:#94a3b8;">Min Rating:</span>
        <input type="number" class="filter-input" id="minRating" value="0" min="0" max="5" step="0.5" onchange="applyFilters()">
      </div>

      <!-- ═══ Blocked Alert (hidden by default) ═══ -->
      <div class="blocked-alert" id="blockedAlert" style="display:none;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span id="blockedMsg">-</span>
      </div>

      <!-- ═══ Results Section ═══ -->
      <div class="results-section">
        <div class="results-header" id="resultsHeader" style="display:none;">
          <div class="results-count">
            <span id="resultCount">0</span> toko ditemukan
            <span id="filteredInfo" style="font-size:12px; color:#94a3b8; font-weight:400; margin-left:8px;"></span>
          </div>
          <div style="display:flex; gap:10px;">
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-size:12px; font-weight:600; color:#475569;">
              <input type="checkbox" id="selectAll" class="checkbox-premium" onchange="toggleSelectAll()">
              Pilih Semua
            </label>
            <button class="btn-import" id="btnImport" disabled onclick="doImport()">
              <i class="fa-solid fa-download"></i> Import Terpilih (<span id="selectedCount">0</span>)
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div class="results-loading" id="resultsLoading" style="display:none;">
          <div class="spinner"></div>
          <div style="font-size:14px; font-weight:600; color:#475569;">Sedang mencari toko...</div>
          <div style="font-size:12px; color:#94a3b8; margin-top:4px;">Menghubungi Google Maps API</div>
        </div>

        <!-- Empty State -->
        <div class="results-empty" id="resultsEmpty">
          <i class="fa-solid fa-map-location-dot"></i>
          <div style="font-size:15px; font-weight:700; color:#475569;">Mulai Pencarian</div>
          <div style="font-size:12px; color:#94a3b8; margin-top:4px;">Pilih kata kunci & kota, lalu klik "Cari Toko"</div>
        </div>

        <!-- Table -->
        <div id="resultsTableWrap" style="display:none; overflow-x:auto;">
          <table class="results-table">
            <thead>
              <tr>
                <th style="width:40px;"><input type="checkbox" class="checkbox-premium" id="selectAllTop" onchange="toggleSelectAll()"></th>
                <th style="width:50px;">Score</th>
                <th>Nama Toko</th>
                <th>Telepon</th>
                <th style="width:80px;">Rating</th>
                <th style="width:80px;">Review</th>
                <th style="width:100px;">Trust</th>
                <th style="width:70px;">Maps</th>
              </tr>
            </thead>
            <tbody id="resultsBody"></tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
  <?php include "footer.php"; ?>
</main>
<?php include "js-include.php"; ?>

<!-- ═══ Import Toast ═══ -->
<div class="import-toast" id="importToast"></div>

<script>
// ══════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════
let allResults = [];
let currentFilter = 'all';

// ══════════════════════════════════════════════════
// KEYWORD TOGGLE
// ══════════════════════════════════════════════════
document.getElementById('keyword').addEventListener('change', function() {
  document.getElementById('customKeywordGroup').style.display = this.value === 'custom' ? '' : 'none';
});

// ══════════════════════════════════════════════════
// LOAD USAGE STATS
// ══════════════════════════════════════════════════
async function loadStats() {
  try {
    const res = await fetch('scraping-gmaps-api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'stats'})
    });
    const data = await res.json();
    if (data.stats) updateMeter(data.stats);
  } catch(e) { console.error('Stats error:', e); }
}

function updateMeter(stats) {
  const dailyPct = Math.round((stats.daily_used / stats.daily_limit) * 100);
  const monthlyPct = Math.round((stats.monthly_used / stats.monthly_limit) * 100);

  document.getElementById('dailyUsed').textContent = stats.daily_used;
  document.getElementById('dailyLimit').textContent = stats.daily_limit;
  document.getElementById('monthlyUsed').textContent = stats.monthly_used;
  document.getElementById('monthlyLimit').textContent = stats.monthly_limit;

  const dailyBar = document.getElementById('dailyBar');
  dailyBar.style.width = Math.min(dailyPct, 100) + '%';
  dailyBar.className = 'meter-bar-fill ' + (dailyPct >= 80 ? 'danger' : dailyPct >= 50 ? 'warning' : 'safe');

  const monthlyBar = document.getElementById('monthlyBar');
  monthlyBar.style.width = Math.min(monthlyPct, 100) + '%';
  monthlyBar.className = 'meter-bar-fill ' + (monthlyPct >= 80 ? 'danger' : monthlyPct >= 50 ? 'warning' : 'safe');
}

// ══════════════════════════════════════════════════
// SEARCH
// ══════════════════════════════════════════════════
async function doSearch() {
  const keywordSel = document.getElementById('keyword');
  const keyword = keywordSel.value === 'custom' 
    ? document.getElementById('customKeyword').value.trim()
    : keywordSel.value;
  const city = document.getElementById('city').value;
  const radius = parseInt(document.getElementById('radius').value);

  if (!keyword) { alert('Masukkan kata kunci pencarian'); return; }

  // UI: loading
  document.getElementById('btnSearch').disabled = true;
  document.getElementById('btnSearch').innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;margin:0;"></div> Mencari...';
  document.getElementById('resultsLoading').style.display = '';
  document.getElementById('resultsEmpty').style.display = 'none';
  document.getElementById('resultsTableWrap').style.display = 'none';
  document.getElementById('resultsHeader').style.display = 'none';
  document.getElementById('blockedAlert').style.display = 'none';

  try {
    const res = await fetch('scraping-gmaps-api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'search', keyword, city, radius})
    });
    const data = await res.json();

    if (data.stats) updateMeter(data.stats);

    if (data.blocked) {
      document.getElementById('blockedMsg').textContent = data.message;
      document.getElementById('blockedAlert').style.display = 'flex';
      document.getElementById('resultsLoading').style.display = 'none';
      document.getElementById('resultsEmpty').style.display = '';
      resetSearchBtn();
      return;
    }

    if (data.error) {
      alert('Error: ' + data.message);
      document.getElementById('resultsLoading').style.display = 'none';
      document.getElementById('resultsEmpty').style.display = '';
      resetSearchBtn();
      return;
    }

    allResults = data.results || [];
    // Add city info for import
    allResults.forEach(r => r.city = city);
    renderResults();

  } catch(e) {
    alert('Gagal menghubungi server: ' + e.message);
    document.getElementById('resultsLoading').style.display = 'none';
    document.getElementById('resultsEmpty').style.display = '';
  }

  resetSearchBtn();
}

function resetSearchBtn() {
  document.getElementById('btnSearch').disabled = false;
  document.getElementById('btnSearch').innerHTML = '<i class="fa-solid fa-satellite-dish"></i> Cari Toko';
}

// ══════════════════════════════════════════════════
// RENDER RESULTS
// ══════════════════════════════════════════════════
function renderResults() {
  const filtered = getFilteredResults();
  const tbody = document.getElementById('resultsBody');
  tbody.innerHTML = '';

  document.getElementById('resultsLoading').style.display = 'none';

  if (allResults.length === 0) {
    document.getElementById('resultsEmpty').style.display = '';
    document.getElementById('resultsTableWrap').style.display = 'none';
    document.getElementById('resultsHeader').style.display = 'none';
    document.getElementById('filterBar').style.display = 'none';
    return;
  }

  document.getElementById('resultsEmpty').style.display = 'none';
  document.getElementById('resultsTableWrap').style.display = '';
  document.getElementById('resultsHeader').style.display = 'flex';
  document.getElementById('filterBar').style.display = 'flex';

  document.getElementById('resultCount').textContent = filtered.length;
  document.getElementById('filteredInfo').textContent = 
    filtered.length < allResults.length ? `(${allResults.length} total, ${allResults.length - filtered.length} disembunyikan)` : '';

  filtered.forEach((r, i) => {
    const scoreClass = r.trust_score >= 70 ? 'high' : r.trust_score >= 40 ? 'medium' : 'low';
    const trustLabel = r.trust_level === 'terpercaya' ? '🟢 Terpercaya' : r.trust_level === 'perlu_cek' ? '🟡 Perlu Cek' : '🔴 Berisiko';

    const tr = document.createElement('tr');
    tr.dataset.idx = i;
    tr.innerHTML = `
      <td><input type="checkbox" class="checkbox-premium row-check" data-idx="${i}" onchange="updateSelectedCount()"></td>
      <td><div class="score-circle ${scoreClass}">${r.trust_score}</div></td>
      <td>
        <div class="place-name">${escHtml(r.name)}</div>
        <div class="place-address" title="${escHtml(r.address)}">${escHtml(r.address)}</div>
        <div class="place-meta">
          ${r.photo_count > 0 ? `<span class="place-meta-item"><i class="fa-solid fa-image" style="color:#7c3aed;"></i> ${r.photo_count} foto</span>` : ''}
          ${r.website ? `<span class="place-meta-item"><i class="fa-solid fa-globe" style="color:#06b6d4;"></i> Website</span>` : ''}
        </div>
      </td>
      <td style="font-weight:600;">${r.phone ? escHtml(r.phone) : '<span style="color:#cbd5e1;">—</span>'}</td>
      <td>
        <span style="font-weight:700; color:#f59e0b;">${r.rating > 0 ? '⭐ ' + r.rating.toFixed(1) : '—'}</span>
      </td>
      <td style="font-weight:600;">${r.review_count > 0 ? r.review_count.toLocaleString() : '—'}</td>
      <td><span class="trust-badge ${r.trust_level}">${trustLabel}</span></td>
      <td>${r.maps_url ? `<a href="${r.maps_url}" target="_blank" class="link-maps"><i class="fa-solid fa-arrow-up-right-from-square"></i> Buka</a>` : '—'}</td>
    `;
    tbody.appendChild(tr);
  });

  updateSelectedCount();
}

// ══════════════════════════════════════════════════
// FILTERS
// ══════════════════════════════════════════════════
function toggleFilter(el, type, value) {
  document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  currentFilter = value;
  applyFilters();
}

function applyFilters() {
  renderResults();
}

function getFilteredResults() {
  const minReview = parseInt(document.getElementById('minReview').value) || 0;
  const minRating = parseFloat(document.getElementById('minRating').value) || 0;

  return allResults.filter(r => {
    if (currentFilter !== 'all' && r.trust_level !== currentFilter) return false;
    if (r.review_count < minReview) return false;
    if (r.rating < minRating) return false;
    return true;
  });
}

// ══════════════════════════════════════════════════
// SELECT / IMPORT
// ══════════════════════════════════════════════════
function toggleSelectAll() {
  const checked = document.getElementById('selectAll')?.checked || document.getElementById('selectAllTop')?.checked || false;
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = checked);
  // Sync both checkboxes
  if (document.getElementById('selectAll')) document.getElementById('selectAll').checked = checked;
  if (document.getElementById('selectAllTop')) document.getElementById('selectAllTop').checked = checked;
  updateSelectedCount();
}

function updateSelectedCount() {
  const count = document.querySelectorAll('.row-check:checked').length;
  document.getElementById('selectedCount').textContent = count;
  document.getElementById('btnImport').disabled = count === 0;
}

async function doImport() {
  const checked = document.querySelectorAll('.row-check:checked');
  if (checked.length === 0) return;

  const filtered = getFilteredResults();
  const places = [];

  checked.forEach(cb => {
    const idx = parseInt(cb.dataset.idx);
    const r = filtered[idx];
    if (r) {
      places.push({
        place_id: r.place_id,
        name: r.name,
        address: r.address,
        phone: r.phone,
        city: r.city,
        lat: String(r.lat),
        lng: String(r.lng),
        website: r.website,
      });
    }
  });

  if (places.length === 0) return;

  if (!confirm(`Import ${places.length} toko ke database customer?\n\nData duplikat (nama + kota sama) akan otomatis dilewati.`)) return;

  document.getElementById('btnImport').disabled = true;
  document.getElementById('btnImport').innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;margin:0;"></div> Importing...';

  try {
    const res = await fetch('import-gmaps-customer.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({places})
    });
    const data = await res.json();

    showToast(data.message, data.error ? 'error' : 'success');

    // Uncheck all
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
    if (document.getElementById('selectAll')) document.getElementById('selectAll').checked = false;
    if (document.getElementById('selectAllTop')) document.getElementById('selectAllTop').checked = false;
    updateSelectedCount();
  } catch(e) {
    showToast('Gagal import: ' + e.message, 'error');
  }

  document.getElementById('btnImport').disabled = false;
  document.getElementById('btnImport').innerHTML = '<i class="fa-solid fa-download"></i> Import Terpilih (<span id="selectedCount">0</span>)';
  updateSelectedCount();
}

// ══════════════════════════════════════════════════
// UTILITIES
// ══════════════════════════════════════════════════
function escHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

function showToast(msg, type) {
  const toast = document.getElementById('importToast');
  toast.textContent = msg;
  toast.className = 'import-toast ' + type + ' show';
  setTimeout(() => toast.className = 'import-toast', 4000);
}

// ══════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════
loadStats();
</script>

</body>
</html>
