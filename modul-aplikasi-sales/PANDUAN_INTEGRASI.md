# Panduan Integrasi Modul Aplikasi Sales ke sales.id-giti.com

File-file di dalam folder / ZIP ini adalah modul lengkap untuk **Aplikasi Sales (Mobile Management)** yang dipisahkan dari `jadwal.id-giti.com`.

---

## 1. Daftar Modul & File

### A. TIP TOK (Titip Toko / Konsinyasi)
- `tiptok.php` ➔ Halaman Dashboard Web Admin untuk monitor stok titipan, audit penjualan, & approval klaim insentif.
- `tiptok-ajax.php` ➔ Backend AJAX handler untuk semua aksi di halaman `tiptok.php`.
- `api_tiptok.php` ➔ Mirror API untuk sinkronisasi dengan aplikasi Flutter mobile sales.

### B. Jadwal Kunjungan Sales
- `kegiatan-baru.php` ➔ Form Admin menjadwalkan kunjungan harian ke toko/dealer mitra.
- `kegiatan-db.php` ➔ Tabel & kalender jadwal kunjungan sales.
- `kegiatan-selesai.php` ➔ Riwayat kunjungan yang sudah selesai.
- `proses_jadwalkan.php`, `proses_reschedule_admin.php`, `proses-edit-kunjungan.php`, `proses-hapus-kunjungan.php` ➔ Handler CRUD jadwal.

### C. Laporan Kunjungan (Visit Report)
- `laporan-kegiatan.php` & `laporan-cust.php` ➔ Laporan bukti check-in/out, catatan sales, foto toko, & invoice.
- `get_visit_details.php` & `get_sales_customer_visits.php` ➔ Handler detail riwayat visit.

### D. Customer Toko / Dealer Mitra
- `customer.php` & `customer_management.php` ➔ Database master toko/dealer, alamat, GPS & PIC kontak.
- `tambah-customer.php`, `edit-customer.php`, `import-gmaps-customer.php` ➔ Form tambah/edit/import toko.

### E. Tools Scraping Google Maps
- `scraping-gmaps.php`, `scraping-gmaps-api.php`, `gmaps-config.php` ➔ Fitur scraper leads calon toko langsung dari GMaps.

---

## 2. Kode Menu Sidebar untuk `sales.id-giti.com`

Sisipkan kode berikut pada file sidebar (misal `includes/sidebar.php`) **di bawah menu Sales Management**:

```html
<!-- ═════════════════════════════════════════════════════════ -->
<!-- SEKSI KHUSUS: APLIKASI SALES (MOBILE)                     -->
<!-- ═════════════════════════════════════════════════════════ -->
<div class="mt-6 mb-2 px-3">
  <span class="text-[10px] font-bold tracking-wider text-blue-400 uppercase opacity-80">
    APLIKASI SALES (MOBILE)
  </span>
</div>

<!-- 1. Jadwal Kunjungan -->
<a href="kegiatan-db.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
  <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
  </svg>
  <span>Jadwal Kunjungan App</span>
</a>

<!-- 2. Laporan Visit -->
<a href="laporan-kegiatan.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
  <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
  </svg>
  <span>Laporan Visit App</span>
</a>

<!-- 3. TIP TOK (Konsinyasi Toko) -->
<a href="tiptok.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
  <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
  </svg>
  <span>TIP TOK (Konsinyasi)</span>
</a>

<!-- 4. Customer Sales -->
<a href="customer.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition">
  <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
  </svg>
  <span>Customer Toko/Dealer</span>
</a>
```

---

## 3. Catatan Koneksi Database (`conn.php`)
Pastikan file `conn.php` terhubung ke database MySQL yang memuat tabel:
- `kegiatan_sales` & `team_kegiatan_sales`
- `pelaksanaan_sales`
- `tiptok_penitipan`, `tiptok_items`, `tiptok_kunjungan`, `tiptok_claims`
- `sales_customer`
- `sales`

Dengan demikian, penjadwalan dan monitoring di web `sales.id-giti.com` akan 100% sinkron secara real-time dengan aplikasi Android Sales Loewix!
