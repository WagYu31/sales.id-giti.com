<?php
// includes/customer_import_helper.php
// Shared engine untuk Import Customer Massal via Excel (.xlsx/.xls), CSV, dan Link Google Sheets

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

if (!function_exists('customerImportEnsureAutoload')) {
    function customerImportEnsureAutoload(): bool
    {
        if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            return true;
        }
        $paths = [
            dirname(__DIR__) . '/vendor/autoload.php',
            dirname(__DIR__, 2) . '/vendor/autoload.php',
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) {
                require_once $p;
                break;
            }
        }
        return class_exists('PhpOffice\PhpSpreadsheet\IOFactory');
    }
}

if (!function_exists('customerImportNormalizeHeader')) {
    function customerImportNormalizeHeader(string $header): string
    {
        // Hapus UTF-8 BOM jika ada
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);
        return trim($header, '_');
    }
}

if (!function_exists('customerImportMapHeaders')) {
    function customerImportMapHeaders(array $rawHeaderRow): array
    {
        $aliases = [
            'nama_toko' => [
                'nama_toko', 'toko', 'nama_customer', 'customer', 'nama_perusahaan', 'perusahaan',
                'nama_toko_perusahaan', 'store_name', 'store', 'outlet', 'nama_outlet', 'dealer',
                'nama_dealer', 'nama_usaha', 'usaha', 'nama_mitra', 'mitra', 'company', 'company_name', 'nama'
            ],
            'kategori' => [
                'kategori', 'category', 'kategori_toko', 'klasifikasi', 'klasifikasi_kategori',
                'klasifikasi_kategori_toko', 'tipe', 'type', 'jenis', 'segmen', 'segment'
            ],
            'nama_pic' => [
                'nama_pic', 'pic', 'owner', 'nama_owner', 'nama_owner_pic', 'kontak', 'nama_kontak',
                'contact', 'contact_person', 'cp', 'pemilik', 'nama_pemilik'
            ],
            'tlp_pic' => [
                'tlp_pic', 'tlp', 'telp', 'telepon', 'no_telp', 'no_tlp', 'no_telepon', 'no_hp', 'hp',
                'wa', 'no_wa', 'whatsapp', 'no_whatsapp', 'phone', 'phone_number', 'nomor_telepon',
                'nomor_hp', 'nomor_wa', 'no_telepon_whatsapp', 'no_telepon_whatsapp_pic', 'telepon_pic',
                'telepon_wa', 'handphone', 'mobile', 'telp_pic', 'kontak_wa'
            ],
            'alamat' => [
                'alamat', 'alamat_lengkap', 'address', 'jalan', 'lokasi', 'alamat_toko', 'full_address'
            ],
            'kota' => [
                'kota', 'kabupaten', 'kota_kabupaten', 'city', 'kab', 'kota_kab', 'area', 'wilayah', 'daerah'
            ],
            'provinsi' => [
                'provinsi', 'province', 'propinsi', 'prov', 'state'
            ],
            'link_google_map' => [
                'link_google_map', 'link_google_maps', 'google_map', 'google_maps', 'maps', 'link_maps',
                'url_maps', 'gmaps', 'link_gmaps', 'url_google_maps', 'link_google_maps_url', 'map_url', 'google_map_url'
            ],
            'sales_name' => [
                'sales_name', 'sales', 'nama_sales', 'sales_pic', 'sales_pj', 'sales_penanggung_jawab',
                'sales_representative', 'assign_sales', 'pic_sales', 'sales_id', 'penanggung_jawab'
            ],
            'tgl_input' => [
                'tgl_input', 'tanggal_input', 'tanggal', 'tgl', 'date', 'created_at', 'tgl_daftar'
            ],
        ];

        $lookup = [];
        foreach ($aliases as $canonical => $list) {
            foreach ($list as $alias) {
                $lookup[$alias] = $canonical;
            }
        }

        $colMap = [];      // canonical => colIndex (0-based)
        $detectedLabels = []; // canonical => original header text
        foreach ($rawHeaderRow as $idx => $cellText) {
            $orig = trim((string)$cellText);
            if ($orig === '') {
                continue;
            }
            $norm = customerImportNormalizeHeader($orig);
            if (isset($lookup[$norm])) {
                $field = $lookup[$norm];
                if (!isset($colMap[$field])) {
                    $colMap[$field] = $idx;
                    $detectedLabels[$field] = $orig;
                }
            }
        }

        return ['map' => $colMap, 'labels' => $detectedLabels];
    }
}

if (!function_exists('customerImportNormalizePhone')) {
    function customerImportNormalizePhone($rawVal, $formattedVal = ''): string
    {
        // Pulihkan angka presisi penuh jika Excel menyimpannya sebagai float/int (mencegah 8.12E+10)
        if (is_int($rawVal) || is_float($rawVal)) {
            $str = sprintf('%.0f', $rawVal);
        } else {
            $str = trim((string)$rawVal);
            if ($str === '' && $formattedVal !== '') {
                $str = trim((string)$formattedVal);
            }
            // Jika masih berupa string scientific notation dari CSV (misal "8.12345E+10")
            if (preg_match('/^\d+(?:[.,]\d+)?[eE]\+\d+$/', $str)) {
                $floatVal = (float)str_replace(',', '.', $str);
                $str = sprintf('%.0f', $floatVal);
            }
        }

        $digits = preg_replace('/[^0-9]/', '', $str);
        if ($digits === '' || preg_match('/^0+$/', $digits)) {
            return '';
        }

        if (strpos($digits, '620') === 0 && strlen($digits) >= 10) {
            $digits = '0' . substr($digits, 3);
        } elseif (strpos($digits, '62') === 0 && strlen($digits) >= 9) {
            $digits = '0' . substr($digits, 2);
        } elseif (strpos($digits, '8') === 0 && strlen($digits) >= 8) {
            $digits = '0' . $digits;
        }

        return substr($digits, 0, 20);
    }
}

if (!function_exists('customerImportNormalizeKategori')) {
    function customerImportNormalizeKategori(string $val, string $defaultKategori = 'DEALER'): string
    {
        $clean = strtoupper(trim($val));
        if ($clean === '') {
            return $defaultKategori;
        }
        $norm = preg_replace('/[\s_-]+/', ' ', $clean);
        $map = [
            'INSTALLER' => 'INSTALLER',
            'INSTALATOR' => 'INSTALLER',
            'TEKNISI' => 'INSTALLER',
            'MASTER DEALER' => 'MASTER DEALER',
            'MD' => 'MASTER DEALER',
            'MASTERDEALER' => 'MASTER DEALER',
            'DISTRIBUTOR' => 'MASTER DEALER',
            'DEALER' => 'DEALER',
            'TOKO' => 'DEALER',
            'RESELLER' => 'DEALER',
            'USER' => 'USER',
            'END USER' => 'USER',
            'ENDUSER' => 'USER',
            'PRIBADI' => 'USER',
            'PROYEK' => 'USER',
            'PROJECT' => 'USER',
        ];
        if (isset($map[$norm])) {
            return $map[$norm];
        }
        return mb_substr($clean, 0, 255);
    }
}

if (!function_exists('customerImportParseDate')) {
    function customerImportParseDate($rawVal, $formattedVal = ''): string
    {
        $today = date('Y-m-d');
        if ($rawVal === null || $rawVal === '') {
            return $today;
        }

        // Excel serial date (biasanya antara tahun 1955 s/d 2100 -> 20000..75000)
        if ((is_int($rawVal) || is_float($rawVal)) && $rawVal > 20000 && $rawVal < 80000) {
            try {
                if (class_exists('PhpOffice\PhpSpreadsheet\Shared\Date')) {
                    return ExcelDate::excelToDateTimeObject($rawVal)->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // fallback ke string parsing
            }
        }

        $str = trim((string)($formattedVal !== '' ? $formattedVal : $rawVal));
        if ($str === '') {
            return $today;
        }

        // Cek format eksplisit
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y', 'm/d/Y'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $str);
            if ($dt && $dt->format($fmt) === $str) {
                $year = (int)$dt->format('Y');
                if ($year >= 2000 && $year <= 2100) {
                    return $dt->format('Y-m-d');
                }
            }
        }

        $ts = strtotime($str);
        if ($ts !== false) {
            $y = (int)date('Y', $ts);
            if ($y >= 2000 && $y <= 2100) {
                return date('Y-m-d', $ts);
            }
        }
        return $today;
    }
}

if (!function_exists('customerImportFetchGoogleSheet')) {
    /**
     * Unduh Google Spreadsheet publik ke file sementara (aman dari SSRF: hanya domain docs.google.com/spreadsheets/d/...)
     * @return array{ok:bool, path?:string, ext?:string, error?:string}
     */
    function customerImportFetchGoogleSheet(string $url): array
    {
        $url = trim($url);
        $isPublished = false;
        $sheetId = '';
        $pubId = '';
        if (preg_match('#^https://docs\.google\.com/spreadsheets/d/e/(2PACX-[a-zA-Z0-9_-]{20,})#', $url, $pm)) {
            $isPublished = true;
            $pubId = $pm[1];
        } elseif (preg_match('#^https://docs\.google\.com/spreadsheets/d/([a-zA-Z0-9_-]{15,})#', $url, $m)) {
            $sheetId = $m[1];
        } else {
            return [
                'ok' => false,
                'error' => 'Format link Google Sheets tidak valid. Contoh yang benar: https://docs.google.com/spreadsheets/d/1ABCxyz.../edit'
            ];
        }

        $gid = '';
        if (preg_match('/[?&#]gid=(\d+)/', $url, $gm)) {
            $gid = $gm[1];
        }

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'error' => 'Ekstensi PHP cURL belum aktif di server sehingga belum bisa menarik link Google Sheets langsung. Silakan unduh file sebagai .xlsx dari Google Sheets lalu upload lewat tab File Excel.'
            ];
        }

        $downloadUrl = function (string $targetUrl): array {
            $ch = curl_init($targetUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 6,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; LoewixCRM-SheetImporter/2.0)',
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv, */*'],
            ]);
            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            return [
                'body' => $body,
                'http' => $httpCode,
                'ctype' => $contentType,
                'err' => $curlErr
            ];
        };

        $isHtmlResponse = function ($body, $ctype): bool {
            if (stripos($ctype, 'text/html') !== false) {
                return true;
            }
            $head = ltrim(substr((string)$body, 0, 512));
            return (stripos($head, '<!DOCTYPE') === 0 || stripos($head, '<html') === 0 || stripos($head, 'accounts.google.com') !== false);
        };

        // 1. Coba tarik sebagai XLSX (preserve format terbaik, juga mendukung &gid= untuk tab tertentu)
        if ($isPublished) {
            $xlsxUrl = "https://docs.google.com/spreadsheets/d/e/{$pubId}/pub?output=xlsx" . ($gid !== '' ? "&gid={$gid}&single=true" : '');
            $csvUrl  = "https://docs.google.com/spreadsheets/d/e/{$pubId}/pub?output=csv"  . ($gid !== '' ? "&gid={$gid}&single=true" : '');
        } else {
            $xlsxUrl = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=xlsx" . ($gid !== '' ? "&gid={$gid}" : '');
            $csvUrl  = "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv"  . ($gid !== '' ? "&gid={$gid}" : '');
        }
        $res = $downloadUrl($xlsxUrl);

        if ($res['err'] !== '') {
            return [
                'ok' => false,
                'error' => 'Gagal menghubungi server Google Sheets: ' . $res['err']
            ];
        }

        if ($res['http'] === 200 && is_string($res['body']) && strlen($res['body']) > 100 && substr($res['body'], 0, 2) === 'PK') {
            $tmpFile = tempnam(sys_get_temp_dir(), 'gsheet_') . '.xlsx';
            file_put_contents($tmpFile, $res['body']);
            return ['ok' => true, 'path' => $tmpFile, 'ext' => 'xlsx'];
        }

        // 2. Fallback ke CSV export (sangat andal untuk tab tertentu dengan gid)
        $resCsv = $downloadUrl($csvUrl);

        if ($resCsv['http'] === 200 && is_string($resCsv['body']) && strlen(trim($resCsv['body'])) > 0 && !$isHtmlResponse($resCsv['body'], $resCsv['ctype'])) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'gsheet_') . '.csv';
            file_put_contents($tmpFile, $resCsv['body']);
            return ['ok' => true, 'path' => $tmpFile, 'ext' => 'csv'];
        }

        if ($res['http'] === 401 || $res['http'] === 403 || $isHtmlResponse($res['body'], $res['ctype'])) {
            return [
                'ok' => false,
                'error' => 'Link Google Sheets masih terkunci (Private). Buka Google Sheets Anda → klik tombol "Bagikan" (Share) → pada Akses Umum pilih "Siapa saja yang memiliki link" (Pelihat / Viewer) → lalu coba lagi.'
            ];
        }

        return [
            'ok' => false,
            'error' => "Gagal mengunduh Google Sheets (HTTP {$res['http']}). Pastikan link benar dan akses sudah diset ke 'Siapa saja yang memiliki link (Pelihat)'."
        ];
    }
}

if (!function_exists('customerImportReadTable')) {
    /**
     * Baca file .xlsx / .xls / .csv menjadi matriks [ ['raw' => ..., 'fmt' => ...], ... ]
     * @return array{ok:bool, rows?:array, sheet_name?:string, error?:string}
     */
    function customerImportReadTable(string $filePath, string $ext): array
    {
        $ext = strtolower($ext);
        $hasPhpSpreadsheet = customerImportEnsureAutoload();

        // Native CSV reader fallback bila PhpSpreadsheet tidak tersedia atau file CSV
        if ($ext === 'csv' && !$hasPhpSpreadsheet) {
            return customerImportReadCsvNative($filePath);
        }

        if (!$hasPhpSpreadsheet) {
            return [
                'ok' => false,
                'error' => 'Library PhpSpreadsheet belum terpasang di server. Silakan upload dalam format .CSV atau jalankan composer install.'
            ];
        }

        try {
            if ($ext === 'csv') {
                $reader = IOFactory::createReader('Csv');
                // Deteksi delimiter otomatis (, atau ; atau tab)
                $firstLine = '';
                $fh = @fopen($filePath, 'r');
                if ($fh) {
                    $firstLine = (string)fgets($fh);
                    fclose($fh);
                }
                $semiCount = substr_count($firstLine, ';');
                $commaCount = substr_count($firstLine, ',');
                $tabCount = substr_count($firstLine, "\t");
                if ($tabCount > $commaCount && $tabCount > $semiCount) {
                    $reader->setDelimiter("\t");
                } elseif ($semiCount > $commaCount) {
                    $reader->setDelimiter(';');
                } else {
                    $reader->setDelimiter(',');
                }
                $reader->setInputEncoding('UTF-8');
                $spreadsheet = $reader->load($filePath);
            } else {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(false);
                $spreadsheet = $reader->load($filePath);
            }

            // Pilih sheet pertama (atau sheet bernama "Data Customer" jika ada)
            $worksheet = $spreadsheet->getSheetByName('Data Customer') ?? $spreadsheet->getSheet(0);
            $sheetName = $worksheet->getTitle();

            $highestRow = min((int)$worksheet->getHighestDataRow(), 5005);
            $highestColIndex = min(Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()), 40);

            $rows = [];
            for ($r = 1; $r <= $highestRow; $r++) {
                $rowCells = [];
                $hasAnyData = false;
                for ($c = 1; $c <= $highestColIndex; $c++) {
                    $coord = Coordinate::stringFromColumnIndex($c) . $r;
                    $cell = $worksheet->getCell($coord);
                    $raw = $cell->getCalculatedValue();
                    if ($raw === null) {
                        $raw = $cell->getValue();
                    }
                    $fmt = $cell->getFormattedValue();
                    if (trim((string)$raw) !== '' || trim((string)$fmt) !== '') {
                        $hasAnyData = true;
                    }
                    $rowCells[] = ['raw' => $raw, 'fmt' => $fmt];
                }
                if ($hasAnyData) {
                    $rows[$r] = $rowCells;
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return ['ok' => true, 'rows' => $rows, 'sheet_name' => $sheetName];
        } catch (\Throwable $e) {
            // Jika file sebenarnya CSV tapi berekstensi lain, fallback
            if ($ext === 'csv') {
                return customerImportReadCsvNative($filePath);
            }
            return [
                'ok' => false,
                'error' => 'Gagal membaca file spreadsheet: ' . $e->getMessage()
            ];
        }
    }
}

if (!function_exists('customerImportReadCsvNative')) {
    function customerImportReadCsvNative(string $filePath): array
    {
        $fh = @fopen($filePath, 'r');
        if (!$fh) {
            return ['ok' => false, 'error' => 'Gagal membuka file CSV.'];
        }
        $firstLine = (string)fgets($fh);
        rewind($fh);

        $semiCount = substr_count($firstLine, ';');
        $commaCount = substr_count($firstLine, ',');
        $tabCount = substr_count($firstLine, "\t");
        $delimiter = ',';
        if ($tabCount > $commaCount && $tabCount > $semiCount) {
            $delimiter = "\t";
        } elseif ($semiCount > $commaCount) {
            $delimiter = ';';
        }

        $rows = [];
        $r = 1;
        while (($data = fgetcsv($fh, 0, $delimiter)) !== false && $r <= 5005) {
            $rowCells = [];
            $hasAny = false;
            foreach ($data as $idx => $val) {
                if ($r === 1 && $idx === 0) {
                    $val = preg_replace('/^\xEF\xBB\xBF/', '', (string)$val);
                }
                if (trim((string)$val) !== '') {
                    $hasAny = true;
                }
                $rowCells[] = ['raw' => $val, 'fmt' => $val];
            }
            if ($hasAny) {
                $rows[$r] = $rowCells;
            }
            $r++;
        }
        fclose($fh);
        return ['ok' => true, 'rows' => $rows, 'sheet_name' => 'CSV'];
    }
}

if (!function_exists('customerImportProcess')) {
    /**
     * Mesin utama Preview & Import Customer Massal
     *
     * @param mysqli $conn
     * @param array $options [
     *   'mode' => 'preview'|'import',
     *   'source_type' => 'file'|'gsheet',
     *   'file' => $_FILES['customer_file']|null,
     *   'gsheet_url' => string,
     *   'force_sales_id' => int|null,     // Diisi jika role = sales
     *   'default_sales_id' => int|null,   // Opsional default sales untuk superadmin/adminsales
     *   'default_kategori' => string,     // Default kategori bila kosong di file
     *   'skip_duplicate_phone' => bool,
     *   'skip_duplicate_store' => bool,
     * ]
     */
    function customerImportProcess(mysqli $conn, array $options): array
    {
        @set_time_limit(180);
        @ini_set('memory_limit', '256M');

        $mode = ($options['mode'] ?? 'import') === 'preview' ? 'preview' : 'import';
        $sourceType = ($options['source_type'] ?? 'file') === 'gsheet' ? 'gsheet' : 'file';
        $forceSalesId = isset($options['force_sales_id']) && $options['force_sales_id'] !== null ? (int)$options['force_sales_id'] : null;
        $defaultSalesId = isset($options['default_sales_id']) && $options['default_sales_id'] !== '' && $options['default_sales_id'] !== null ? (int)$options['default_sales_id'] : null;
        $defaultKategori = trim((string)($options['default_kategori'] ?? 'DEALER'));
        $skipDupPhone = !isset($options['skip_duplicate_phone']) || (bool)$options['skip_duplicate_phone'];
        $skipDupStore = !isset($options['skip_duplicate_store']) || (bool)$options['skip_duplicate_store'];

        $tempToDelete = null;
        $filePath = '';
        $ext = '';
        $sourceLabel = '';

        try {
            if ($sourceType === 'gsheet') {
                $gsUrl = trim((string)($options['gsheet_url'] ?? ''));
                if ($gsUrl === '') {
                    return ['ok' => false, 'error' => 'Silakan tempel link Google Sheets terlebih dahulu.'];
                }
                $fetched = customerImportFetchGoogleSheet($gsUrl);
                if (!$fetched['ok']) {
                    return ['ok' => false, 'error' => $fetched['error']];
                }
                $filePath = $fetched['path'];
                $ext = $fetched['ext'];
                $tempToDelete = $filePath;
                $sourceLabel = 'Google Sheets';
            } else {
                $file = $options['file'] ?? null;
                if (!$file || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                    $code = $file['error'] ?? -1;
                    return ['ok' => false, 'error' => "File belum dipilih atau gagal diunggah (kode error: {$code})."];
                }
                if (($file['size'] ?? 0) > 15 * 1024 * 1024) {
                    return ['ok' => false, 'error' => 'Ukuran file melebihi batas maksimal 15 MB.'];
                }
                $origName = (string)($file['name'] ?? '');
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                    return ['ok' => false, 'error' => 'Format file tidak didukung. Gunakan file .xlsx, .xls, atau .csv.'];
                }
                $filePath = $file['tmp_name'];
                $sourceLabel = $origName;
            }

            $readRes = customerImportReadTable($filePath, $ext);
            if (!$readRes['ok']) {
                return ['ok' => false, 'error' => $readRes['error']];
            }

            $rows = $readRes['rows'];
            if (count($rows) < 1) {
                return ['ok' => false, 'error' => 'File spreadsheet kosong dan tidak memiliki baris data.'];
            }

            // Cari baris header (cek baris pertama yg terisi, atau baris kedua jika baris pertama judul)
            $rowNumbers = array_keys($rows);
            $headerRowNum = null;
            $colMap = [];
            $detectedLabels = [];

            foreach (array_slice($rowNumbers, 0, 5) as $rNum) {
                $headerTexts = array_map(function ($c) {
                    return trim((string)($c['fmt'] !== '' ? $c['fmt'] : $c['raw']));
                }, $rows[$rNum]);

                $mapped = customerImportMapHeaders($headerTexts);
                if (isset($mapped['map']['nama_toko'])) {
                    $headerRowNum = $rNum;
                    $colMap = $mapped['map'];
                    $detectedLabels = $mapped['labels'];
                    break;
                }
            }

            if ($headerRowNum === null) {
                $firstRowPreview = array_filter(array_map(function ($c) {
                    return trim((string)($c['fmt'] !== '' ? $c['fmt'] : $c['raw']));
                }, $rows[$rowNumbers[0]]));
                $foundCols = !empty($firstRowPreview) ? implode(', ', array_slice($firstRowPreview, 0, 10)) : '(kosong)';
                return [
                    'ok' => false,
                    'error' => "Kolom wajib 'nama_toko' tidak ditemukan pada baris header. Header yang terbaca: [{$foundCols}]. Silakan gunakan Template Resmi yang tersedia di tombol Unduh Template."
                ];
            }

            // Load peta Sales aktif untuk pencocokan nama / email / ID
            $salesByName = [];
            $salesById = [];
            $salesRes = $conn->query("SELECT id, nama_lengkap, email FROM sales WHERE deleted_at IS NULL");
            if ($salesRes) {
                while ($sr = $salesRes->fetch_assoc()) {
                    $sid = (int)$sr['id'];
                    $sname = trim($sr['nama_lengkap']);
                    $salesById[$sid] = $sname;
                    $salesByName[strtolower($sname)] = $sid;
                    if (!empty($sr['email'])) {
                        $salesByName[strtolower(trim($sr['email']))] = $sid;
                    }
                }
            }

            // Preload existing phone numbers & store names untuk deteksi duplikat cepat (O(1) lookup)
            $existingPhones = [];
            $resPhones = $conn->query("SELECT tlp_pic FROM customer_pics WHERE deleted_at IS NULL AND tlp_pic IS NOT NULL AND tlp_pic <> ''");
            if ($resPhones) {
                while ($pr = $resPhones->fetch_assoc()) {
                    $np = customerImportNormalizePhone($pr['tlp_pic']);
                    if ($np !== '') {
                        $existingPhones[$np] = true;
                    }
                }
            }

            $existingStores = [];
            $resStores = $conn->query("SELECT nama_toko FROM customers WHERE deleted_at IS NULL AND nama_toko IS NOT NULL AND nama_toko <> ''");
            if ($resStores) {
                while ($str = $resStores->fetch_assoc()) {
                    $ns = mb_strtolower(trim($str['nama_toko']));
                    if ($ns !== '') {
                        $existingStores[$ns] = true;
                    }
                }
            }

            // Helper ambil cell berdasarkan canonical field
            $getCell = function (array $rowCells, string $field) use ($colMap): array {
                if (!isset($colMap[$field])) {
                    return ['raw' => '', 'fmt' => ''];
                }
                $idx = $colMap[$field];
                return $rowCells[$idx] ?? ['raw' => '', 'fmt' => ''];
            };

            $getText = function (array $rowCells, string $field) use ($getCell): string {
                $c = $getCell($rowCells, $field);
                $val = trim((string)($c['fmt'] !== '' ? $c['fmt'] : $c['raw']));
                return $val;
            };

            $parsedRows = [];
            $previewRows = [];
            $issues = [];
            $seenPhonesInFile = [];
            $seenStoresInFile = [];

            $totalDataRows = 0;
            $readyCount = 0;
            $duplicateCount = 0;
            $errorCount = 0;
            $exampleCount = 0;

            foreach ($rows as $rNum => $rowCells) {
                if ($rNum <= $headerRowNum) {
                    continue;
                }

                $namaToko = mb_substr($getText($rowCells, 'nama_toko'), 0, 150);
                $kategoriRaw = $getText($rowCells, 'kategori');
                $namaPicRaw = $getText($rowCells, 'nama_pic');
                $tlpCell = $getCell($rowCells, 'tlp_pic');
                $alamat = $getText($rowCells, 'alamat');
                $kota = mb_substr($getText($rowCells, 'kota'), 0, 100);
                $provinsi = mb_substr($getText($rowCells, 'provinsi'), 0, 255);
                $linkMap = mb_substr($getText($rowCells, 'link_google_map'), 0, 255);
                $salesRaw = $getText($rowCells, 'sales_name');
                $tglCell = $getCell($rowCells, 'tgl_input');

                // Abaikan baris yang benar-benar kosong pada nama_toko & kolom utama
                $tlpDisplayCheck = trim((string)($tlpCell['fmt'] !== '' ? $tlpCell['fmt'] : $tlpCell['raw']));
                if ($namaToko === '' && $namaPicRaw === '' && $tlpDisplayCheck === '' && $alamat === '') {
                    continue;
                }

                $totalDataRows++;

                // Lewati otomatis baris contoh dari template ("CONTOH - ...")
                if (preg_match('/^contoh\b/i', $namaToko)) {
                    $exampleCount++;
                    if (count($previewRows) < 40) {
                        $previewRows[] = [
                            'row_num' => $rNum,
                            'status' => 'example',
                            'reason' => 'Baris contoh template (dilewati otomatis)',
                            'nama_toko' => $namaToko,
                            'kategori' => $kategoriRaw,
                            'nama_pic' => $namaPicRaw,
                            'tlp_pic' => $tlpDisplayCheck,
                            'kota' => $kota,
                            'provinsi' => $provinsi,
                            'sales_display' => $salesRaw !== '' ? $salesRaw : '-',
                            'tgl_input' => customerImportParseDate($tglCell['raw'], $tglCell['fmt']),
                        ];
                    }
                    continue;
                }

                // Validasi kolom wajib: nama_toko
                if ($namaToko === '') {
                    $errorCount++;
                    $msg = "Baris {$rNum}: Nama Toko kosong.";
                    if (count($issues) < 100) $issues[] = $msg;
                    if (count($previewRows) < 40) {
                        $previewRows[] = [
                            'row_num' => $rNum,
                            'status' => 'error',
                            'reason' => 'Nama Toko wajib diisi',
                            'nama_toko' => '(Kosong)',
                            'kategori' => $kategoriRaw,
                            'nama_pic' => $namaPicRaw,
                            'tlp_pic' => $tlpDisplayCheck,
                            'kota' => $kota,
                            'provinsi' => $provinsi,
                            'sales_display' => '-',
                            'tgl_input' => date('Y-m-d'),
                        ];
                    }
                    continue;
                }

                // Normalisasi kategori, tanggal, dan nomor telepon (mendukung multi-nomor dipisah ';')
                $kategori = customerImportNormalizeKategori($kategoriRaw, $defaultKategori);
                $tglInput = customerImportParseDate($tglCell['raw'], $tglCell['fmt']);

                // Parse PIC & Telepon (mendukung multi-PIC bila dipisah titik koma ';')
                $picsToInsert = [];
                $rawPhoneStr = is_string($tlpCell['raw']) ? trim($tlpCell['raw']) : '';
                if ($rawPhoneStr !== '' && strpos($rawPhoneStr, ';') !== false) {
                    $phoneParts = array_map('trim', explode(';', $rawPhoneStr));
                    $picParts = $namaPicRaw !== '' ? array_map('trim', explode(';', $namaPicRaw)) : [];
                    foreach ($phoneParts as $pIdx => $pVal) {
                        $normPhone = customerImportNormalizePhone($pVal, $pVal);
                        $pName = $picParts[$pIdx] ?? ($picParts[0] ?? 'Owner');
                        if ($pName === '') $pName = 'Owner';
                        if ($normPhone !== '' || $pName !== 'Owner') {
                            $picsToInsert[] = [
                                'nama_pic' => mb_substr($pName, 0, 100),
                                'tlp_pic' => $normPhone
                            ];
                        }
                    }
                } else {
                    $normPhone = customerImportNormalizePhone($tlpCell['raw'], $tlpCell['fmt']);
                    if ($normPhone !== '' || $namaPicRaw !== '') {
                        $picsToInsert[] = [
                            'nama_pic' => mb_substr($namaPicRaw !== '' ? $namaPicRaw : 'Owner', 0, 100),
                            'tlp_pic' => $normPhone
                        ];
                    }
                }

                // Validasi format nomor telepon jika diisi (harus >= 7 digit)
                $phoneWarning = '';
                $validPhonesThisRow = [];
                foreach ($picsToInsert as &$picItem) {
                    if ($picItem['tlp_pic'] !== '' && strlen($picItem['tlp_pic']) < 7) {
                        $phoneWarning = "Nomor '{$tlpDisplayCheck}' terlalu pendek (<7 digit), dikosongkan.";
                        $picItem['tlp_pic'] = '';
                    } elseif ($picItem['tlp_pic'] !== '') {
                        $validPhonesThisRow[] = $picItem['tlp_pic'];
                    }
                }
                unset($picItem);

                // Resolusi Sales PIC
                $resolvedSalesId = null;
                $salesNote = '';
                if ($forceSalesId !== null) {
                    $resolvedSalesId = $forceSalesId;
                } elseif ($salesRaw !== '') {
                    $sKey = strtolower($salesRaw);
                    if (ctype_digit($salesRaw) && isset($salesById[(int)$salesRaw])) {
                        $resolvedSalesId = (int)$salesRaw;
                    } elseif (isset($salesByName[$sKey])) {
                        $resolvedSalesId = $salesByName[$sKey];
                    } else {
                        // Coba pencocokan parsial unik (misal "Edi" -> "Edi Suprianto")
                        $partialMatches = [];
                        foreach ($salesById as $sid => $sname) {
                            if (stripos($sname, $salesRaw) !== false) {
                                $partialMatches[] = $sid;
                            }
                        }
                        if (count($partialMatches) === 1) {
                            $resolvedSalesId = $partialMatches[0];
                        } else {
                            $resolvedSalesId = $defaultSalesId;
                            $salesNote = "Sales '{$salesRaw}' tidak ditemukan di DB";
                        }
                    }
                } else {
                    $resolvedSalesId = $defaultSalesId;
                }

                $salesDisplay = ($resolvedSalesId !== null && isset($salesById[$resolvedSalesId]))
                    ? $salesById[$resolvedSalesId]
                    : 'Umum (Belum Di-assign)';

                // Cek Duplikat (Nomor HP & Nama Toko)
                $dupReason = '';
                if ($skipDupPhone && !empty($validPhonesThisRow)) {
                    foreach ($validPhonesThisRow as $vp) {
                        if (isset($existingPhones[$vp])) {
                            $dupReason = "Nomor telepon {$vp} sudah terdaftar di database";
                            break;
                        }
                        if (isset($seenPhonesInFile[$vp])) {
                            $dupReason = "Nomor telepon {$vp} duplikat di dalam file (baris {$seenPhonesInFile[$vp]})";
                            break;
                        }
                    }
                }

                $storeKey = mb_strtolower($namaToko);
                if ($dupReason === '' && $skipDupStore) {
                    if (isset($existingStores[$storeKey])) {
                        $dupReason = "Nama toko '{$namaToko}' sudah terdaftar di database";
                    } elseif (isset($seenStoresInFile[$storeKey])) {
                        $dupReason = "Nama toko '{$namaToko}' duplikat di dalam file (baris {$seenStoresInFile[$storeKey]})";
                    }
                }

                $displayPhones = implode(', ', array_filter(array_column($picsToInsert, 'tlp_pic')));
                $displayPics = implode(', ', array_filter(array_column($picsToInsert, 'nama_pic')));

                if ($dupReason !== '') {
                    $duplicateCount++;
                    $msg = "Baris {$rNum} ({$namaToko}): {$dupReason}.";
                    if (count($issues) < 100) $issues[] = $msg;
                    if (count($previewRows) < 40) {
                        $previewRows[] = [
                            'row_num' => $rNum,
                            'status' => 'duplicate',
                            'reason' => $dupReason,
                            'nama_toko' => $namaToko,
                            'kategori' => $kategori,
                            'nama_pic' => $displayPics !== '' ? $displayPics : '-',
                            'tlp_pic' => $displayPhones !== '' ? $displayPhones : '-',
                            'kota' => $kota !== '' ? $kota : '-',
                            'provinsi' => $provinsi !== '' ? $provinsi : '-',
                            'sales_display' => $salesDisplay,
                            'tgl_input' => $tglInput,
                        ];
                    }
                    continue;
                }

                // Tandai sudah dipakai agar duplikat dalam 1 file juga tertangkap
                foreach ($validPhonesThisRow as $vp) {
                    $seenPhonesInFile[$vp] = $rNum;
                }
                $seenStoresInFile[$storeKey] = $rNum;

                $readyCount++;
                $notes = array_filter([$phoneWarning, $salesNote]);
                $parsedRows[] = [
                    'row_num' => $rNum,
                    'sales_id' => $resolvedSalesId,
                    'tgl_input' => $tglInput,
                    'nama_toko' => $namaToko,
                    'kategori' => $kategori,
                    'pics' => $picsToInsert,
                    'alamat' => $alamat,
                    'kota' => $kota,
                    'provinsi' => $provinsi,
                    'link_google_map' => $linkMap,
                ];

                if (count($previewRows) < 40) {
                    $previewRows[] = [
                        'row_num' => $rNum,
                        'status' => 'valid',
                        'reason' => !empty($notes) ? implode(' | ', $notes) : 'Siap masuk database',
                        'nama_toko' => $namaToko,
                        'kategori' => $kategori,
                        'nama_pic' => $displayPics !== '' ? $displayPics : '-',
                        'tlp_pic' => $displayPhones !== '' ? $displayPhones : '-',
                        'kota' => $kota !== '' ? $kota : '-',
                        'provinsi' => $provinsi !== '' ? $provinsi : '-',
                        'sales_display' => $salesDisplay,
                        'tgl_input' => $tglInput,
                    ];
                }
            }

            $summary = [
                'source_label' => $sourceLabel,
                'sheet_name' => $readRes['sheet_name'] ?? 'Sheet1',
                'header_row' => $headerRowNum,
                'total_rows' => $totalDataRows,
                'ready_count' => $readyCount,
                'imported_count' => 0,
                'duplicate_count' => $duplicateCount,
                'error_count' => $errorCount,
                'example_count' => $exampleCount,
            ];

            // Jika hanya mode Pratinjau, kembalikan langsung tanpa mengubah database
            if ($mode === 'preview') {
                return [
                    'ok' => true,
                    'mode' => 'preview',
                    'detected_headers' => $detectedLabels,
                    'summary' => $summary,
                    'preview_rows' => $previewRows,
                    'issues' => $issues,
                ];
            }

            // Mode IMPORT: Simpan ke database dalam satu transaksi aman
            if (empty($parsedRows)) {
                return [
                    'ok' => true,
                    'mode' => 'import',
                    'detected_headers' => $detectedLabels,
                    'summary' => $summary,
                    'preview_rows' => $previewRows,
                    'issues' => $issues,
                    'message' => 'Tidak ada baris baru yang diimpor (semua baris kosong, contoh template, atau sudah ada di database).'
                ];
            }

            $conn->begin_transaction();
            try {
                $stmtCust = $conn->prepare("INSERT INTO customers (sales_id, tgl_input, nama_toko, kategori) VALUES (?, ?, ?, ?)");
                $stmtPic = $conn->prepare("INSERT INTO customer_pics (customer_id, nama_pic, tlp_pic) VALUES (?, ?, ?)");
                $stmtAddr = $conn->prepare("INSERT INTO customer_addresses (customer_id, alamat, kota, provinsi, link_google_map) VALUES (?, ?, ?, ?, ?)");

                if (!$stmtCust || !$stmtPic || !$stmtAddr) {
                    throw new \Exception("Gagal menyiapkan query database: " . $conn->error);
                }

                $importedCount = 0;
                foreach ($parsedRows as $item) {
                    $sid = $item['sales_id']; // bisa null -> bind_param 'i' mendukung NULL di mysqli
                    $tgl = $item['tgl_input'];
                    $toko = $item['nama_toko'];
                    $kat = $item['kategori'];

                    $stmtCust->bind_param("isss", $sid, $tgl, $toko, $kat);
                    if (!$stmtCust->execute()) {
                        throw new \Exception("Baris {$item['row_num']} ({$toko}): " . $stmtCust->error);
                    }
                    $customerId = (int)$conn->insert_id;

                    foreach ($item['pics'] as $pic) {
                        $pName = $pic['nama_pic'];
                        $pPhone = $pic['tlp_pic'];
                        $stmtPic->bind_param("iss", $customerId, $pName, $pPhone);
                        if (!$stmtPic->execute()) {
                            throw new \Exception("Baris {$item['row_num']} PIC ({$toko}): " . $stmtPic->error);
                        }
                    }

                    if ($item['alamat'] !== '' || $item['kota'] !== '' || $item['provinsi'] !== '' || $item['link_google_map'] !== '') {
                        $aAlamat = $item['alamat']; // NOT NULL di tabel -> selalu string (boleh '')
                        $aKota = $item['kota'];
                        $aProv = $item['provinsi'];
                        $aMap = $item['link_google_map'];
                        $stmtAddr->bind_param("issss", $customerId, $aAlamat, $aKota, $aProv, $aMap);
                        if (!$stmtAddr->execute()) {
                            throw new \Exception("Baris {$item['row_num']} Alamat ({$toko}): " . $stmtAddr->error);
                        }
                    }

                    $importedCount++;
                }

                $stmtCust->close();
                $stmtPic->close();
                $stmtAddr->close();
                $conn->commit();

                $summary['imported_count'] = $importedCount;

                return [
                    'ok' => true,
                    'mode' => 'import',
                    'detected_headers' => $detectedLabels,
                    'summary' => $summary,
                    'preview_rows' => $previewRows,
                    'issues' => $issues,
                    'message' => "Berhasil mengimpor {$importedCount} data customer baru ke database!"
                ];
            } catch (\Throwable $dbEx) {
                $conn->rollback();
                return [
                    'ok' => false,
                    'error' => 'Transaksi database dibatalkan (tidak ada data parsial masuk): ' . $dbEx->getMessage()
                ];
            }
        } finally {
            if ($tempToDelete && file_exists($tempToDelete)) {
                @unlink($tempToDelete);
            }
        }
    }
}
