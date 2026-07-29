<?php
/**
 * Asisten Sales Loewix AI Handler Engine - Master CSO & Negotiation Trainer
 * Dynamic Gemini AI integration + Master Loewix Sales & Negotiation Matrix Fallback
 */

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

if (file_exists('includes/db.php')) {
    require_once 'includes/db.php';
}

// Get API Key from environment, constant, or user provided active key
$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: 'AIzaSyBgwtBmX3I6KRHK2V1UpvjhKo4yMoTTAy4');

$input = json_decode(file_get_contents('php://input'), true);
$customerQuestion = trim($input['question'] ?? '');

if (empty($customerQuestion)) {
    echo json_encode(['answers' => [
        [
            'type' => 'Penawaran Value & Premium Closing',
            'strategy' => 'Gunakan untuk menyapa customer baru yang menanyakan paket CCTV.',
            'text' => 'Halo Kak! Terima kasih sudah mengontak Sales Loewix CCTV Indonesia. Kami menyediakan paket CCTV Full Color 24 Jam dengan garansi ganti unit baru resmi. Kakak berencana pasang di rumah atau area toko/kantor?',
            'product_recommendation' => 'Loewix Package Full Color 2MP 4-Channel'
        ],
        [
            'type' => 'Teknik Negosiasi & Bonus Value',
            'strategy' => 'Gunakan jika customer menanyakan ketersediaan promo / nego harga.',
            'text' => 'Halo Kak! Khusus pemesanan minggu ini, kami ada promo paket CCTV Loewix siap pakai sudah termasuk gratis biaya setting pantau HP selamanya dan bonus kabel HDMI. Boleh kami tahu lokasinya Kak?',
            'product_recommendation' => 'Loewix Smart HD Package + Free Cloud P2P App'
        ],
        [
            'type' => 'Strategi Upselling & Offer Upgrade',
            'strategy' => 'Gunakan untuk customer yang menginginkan hasil rekaman super tajam & deteksi manusia.',
            'text' => 'Halo Kak! Jika ingin keamanan maksimal yang bisa zoom detail wajah & plat nomor kendaraan malam hari, kami sangat sarankan Seri Loewix IP Camera 4K dengan AI Human Detection.',
            'product_recommendation' => 'Loewix IP Camera 4K Ultra HD Series'
        ]
    ]]);
    exit;
}

$prompt = "Anda adalah Chief Sales Officer (CSO) & Master Sales Trainer untuk merek CCTV ternama 'Loewix CCTV Indonesia'.

Input dari Sales Rep / Situasi: \"{$customerQuestion}\"

PENTING Mengenai Pemahaman Input:
Input di atas bisa berupa:
A. Teks obrolan / pertanyaan langsung dari Customer.
B. Curhat / Konsultasi dari Sales Rep mengenai situasi prospek (misal: 'kalo client saya sensi di tawarin cara ngadepinnya gmn?', 'Client mau hutang gimana?').

TUGAS UTAMA:
Berikan 3 Taktik Strategi Sales & Skrip Chat WhatsApp Persuasif yang Genius, Sangat Spesifik, dan Siap Kirim sesuai pertanyaan di atas.

Setiap Opsi HARUS berbentuk 1 objek dengan properti:
- \"type\": \"Nama Taktik / Pendekatan Strategis\"
- \"strategy\": \"Tips taktik sales 1-2 kalimat cara pakai opsi ini\"
- \"text\": \"Teks skrip WA persuasif yang siap dikirim Sales ke Customer (Lengkap dengan sapaan & solusi nyata)\"
- \"product_recommendation\": \"Tipe unit/paket Loewix yang disarankan\"

Kembalikan HANYA format JSON valid seperti ini (tanpa markdown ```json):
{
  \"answers\": [
    {
      \"type\": \"...\",
      \"strategy\": \"...\",
      \"text\": \"...\",
      \"product_recommendation\": \"...\"
    },
    ...
  ]
}";

// Endpoint list for Gemini API across supported models
$endpointsToTry = [
    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}",
    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key={$apiKey}",
    "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={$apiKey}",
    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key={$apiKey}"
];

$answers = null;

if (!empty($apiKey) && strlen($apiKey) > 20) {
    foreach ($endpointsToTry as $url) {
        $postData = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]]
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $responseData = json_decode($response, true);
            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                $rawText = $responseData['candidates'][0]['content']['parts'][0]['text'];
                $cleanedJson = preg_replace('/^```json\s*/i', '', $rawText);
                $cleanedJson = preg_replace('/\s*```$/i', '', $cleanedJson);
                $cleanedJson = trim($cleanedJson);

                $parsed = json_decode($cleanedJson, true);
                if (isset($parsed['answers']) && is_array($parsed['answers']) && count($parsed['answers']) >= 1) {
                    $answers = $parsed['answers'];
                    break;
                }
            }
        }
    }
}

// Master Loewix Sales Negotiation & Consultation Matrix Fallback
if (empty($answers)) {
    $q = mb_strtolower($customerQuestion);
    
    // 1. SCENARIO: CLIENT SENSI / SENSITIF / JUTEK / GALAK / TRAUMA / TOLAK / DINGIN
    if (strpos($q, 'sensi') !== false || strpos($q, 'sensitif') !== false || strpos($q, 'jutek') !== false || strpos($q, 'galak') !== false || strpos($q, 'tolak') !== false || strpos($q, 'trauma') !== false || strpos($q, 'marah') !== false || strpos($q, 'cuek') !== false || strpos($q, 'dingin') !== false) {
        $answers = [
            [
                'type' => 'Pendekatan Soft-Approach & Listening First',
                'strategy' => 'Jangan langsung jualan! Posisikan diri sebagai konsultan keamanan yang ingin membantu kendala mereka.',
                'text' => 'Halo Kak! Salam kenal dari tim Loewix CCTV Indonesia. Mohon maaf mengganggu waktunya, kami hanya ingin menyapa dan siap membantu jika Kakak ada kendala teknis atau pertanyaan seputar sistem keamanan di tempat Kakak. Tanpa kewajiban membeli, kami siap berbagi solusi gratis kapan saja Kakak butuhkan.',
                'product_recommendation' => 'Loewix Free Security Consultation Service'
            ],
            [
                'type' => 'Pendekatan Edukasi & Berbagi Panduan Bebas Blind-Spot',
                'strategy' => 'Kirimkan materi tips bermanfaat agar customer merasa dibantu tanpa ada kesan dipaksa membeli.',
                'text' => 'Halo Kak! Sekadar berbagi informasi bermanfaat untuk proteksi area usaha/rumah Kakak, kami ada panduan singkat lokasi titik pemasangan CCTV yang bebas dari blind-spot. Jika kelak Kakak butuh perbandingan spesifikasi atau pembaruan sistem, tim kami siap bantu mengirimkan datanya.',
                'product_recommendation' => 'Loewix Blind-Spot Protection Guide'
            ],
            [
                'type' => 'Pendekatan Solusi Trauma CCTV Rusak & Garansi Ganti Baru',
                'strategy' => 'Jelaskan garansi ganti unit baru 100% jika trauma karena CCTV lamanya sering rewel & garansinya susah.',
                'text' => 'Halo Kak! Kami sangat paham terkadang banyak unit CCTV di pasaran yang sering rewel dan garansinya berbelit. Di Loewix, kami berikan jaminan resmi 1-to-1 Replacement (Ganti Unit Baru 100%) jika ada kendala, jadi Kakak tidak perlu pusing menunggu proses servis berminggu-minggu.',
                'product_recommendation' => 'Loewix 1-to-1 Replacement Guarantee Package'
            ]
        ];
    }
    // 2. SCENARIO: HUTANG / KREDIT / TEMPO / TOP / BAYAR NANTI / CICILAN
    elseif (strpos($q, 'hutang') !== false || strpos($q, 'utang') !== false || strpos($q, 'kredit') !== false || strpos($q, 'tempo') !== false || strpos($q, 'top') !== false || strpos($q, 'cicil') !== false || strpos($q, 'bayar nanti') !== false || strpos($q, 'termin') !== false) {
        $answers = [
            [
                'type' => 'Taktik DP 50% & Pembayaran Bertahap',
                'strategy' => 'Edukasi kebijakan resmi perusahaan untuk menjaga cashflow toko/sales dengan skema DP 50%.',
                'text' => 'Halo Kak! Mengenai pengajuan tempo/kredit, kebijakan resmi manajemen Loewix Indonesia adalah sistem pembayaran bertahap: DP 50% saat pemesanan untuk amankan stok unit, dan pelunasan 50% saat unit selesai dipasang / siap dikirim. Dengan skema ini, alur kas Kakak tetap aman dan Garansi Ganti Baru 1 Tahun aktif 100%.',
                'product_recommendation' => 'Loewix Commercial Safety Package + Term Option'
            ],
            [
                'type' => 'Taktik Cicilan 0% / Kredit via Tokopedia & Shopee',
                'strategy' => 'Solusi cerdas: Arahkan ke E-Commerce agar customer bisa kredit PayLater/CC, tetapi sales tetap terima CASH 100%!',
                'text' => 'Halo Kak! Agar Kakak bisa tetap mencicil bulanan tanpa membebani arus kas, kami menyediakan jalur pembayaran via Tokopedia/Shopee Official Loewix. Kakak bisa gunakan fasilitas Cicilan Kartu Kredit 0% atau Shopee PayLater/Kredivo hingga 12 bulan, sementara unit barang langsung kami kirim hari ini!',
                'product_recommendation' => 'Official Loewix E-Commerce Credit Gateway'
            ],
            [
                'type' => 'Taktik Nego Syarat TOP 14-30 Hari Khusus Instansi / PT',
                'strategy' => 'Untuk proyek sekolah/instansi/PT yang wajib TOP, wajib minta kelengkapan SPK, PO Resmi, & NPWP.',
                'text' => 'Halo Kak! Khusus untuk proyek instansi / PT / sekolah yang membutuhkan Term of Payment (TOP 14-30 hari), mohon dapat melampirkan PO Resmi bersurat, NPWP Perusahaan, & KTP Penanggung Jawab. Proposal penawaran & syarat TOP akan kami ajukan langsung ke Direksi Loewix untuk persetujuan.',
                'product_recommendation' => 'Loewix Corporate Tender Package (TOP Approved)'
            ]
        ];
    }
    // 3. SCENARIO: KEPALA SEKOLAH / PENDIDIKAN / GURU / YAYASAN / DANA BOS
    elseif (strpos($q, 'sekolah') !== false || strpos($q, 'pendidikan') !== false || strpos($q, 'kepala') !== false || strpos($q, 'guru') !== false || strpos($q, 'yayasan') !== false || strpos($q, 'bos') !== false) {
        $answers = [
            [
                'type' => 'Pendekatan Formal & Keamanan Sekolah',
                'strategy' => 'Sapaan terhormat untuk Kepala Sekolah menonjolkan keamanan lingkungan belajar & gerbang murid.',
                'text' => 'Selamat pagi/siang Bapak/Ibu Kepala Sekolah. Terkait peningkatan keamanan & pengawasan lingkungan sekolah, mohon izin kami dari Loewix CCTV Indonesia ingin memberikan penawaran Paket CCTV Khusus Instansi Pendidikan. Kamera Loewix sudah dilengkapi Full Color 24 Jam untuk pemantauan gerbang & area kelas secara jernih.',
                'product_recommendation' => 'Loewix School Safety Package 8-Cam Starlight'
            ],
            [
                'type' => 'Pendekatan Administrasi & Anggaran Sekolah',
                'strategy' => 'Tekankan kemudahan faktur pajak, SPK resmi, & Garansi Ganti Baru 1 Tahun.',
                'text' => 'Bapak/Ibu Kepala Sekolah yang kami hormati, produk Loewix CCTV sangat efisien untuk anggaran sekolah karena sudah mencakup Garansi Ganti Unit Baru 1-to-1 dan kelengkapan administrasi/faktur resmi. Kami siap kirimkan proposal teknis & estimasi perbandingan paketnya.',
                'product_recommendation' => 'Loewix Institutional Package + Invoice Resmi'
            ],
            [
                'type' => 'Pendekatan IP Camera Monitoring Terpusat',
                'strategy' => 'Tawarkan integrasi pantau langsung dari ruang Kepala Sekolah & smartphone.',
                'text' => 'Bapak/Ibu Kepala Sekolah, sistem Loewix IP Camera 4K dapat diintegrasikan langsung ke TV monitoring ruang Kepala Sekolah serta smartphone Bapak/Ibu secara realtime. Pemantauan aktivitas sekolah jadi jauh lebih praktis dan transparan.',
                'product_recommendation' => 'Loewix Smart AI IP Camera Campus Series'
            ]
        ];
    }
    // 4. SCENARIO: HARGA MAHAL / DISKON / NEGO / POTONG / MURAH
    elseif (strpos($q, 'diskon') !== false || strpos($q, 'kurang') !== false || strpos($q, 'mahal') !== false || strpos($q, 'potong') !== false || strpos($q, 'murah') !== false || strpos($q, 'harga') !== false) {
        $answers = [
            [
                'type' => 'Penawaran Value & Premium Closing',
                'strategy' => 'Tekankan garansi ganti unit baru 1-to-1 daripada memotong harga produk.',
                'text' => 'Halo Kak! Untuk harga paket CCTV Loewix ini sudah merupakan harga promo terbaik dengan jaminan Garansi Ganti Baru 1-to-1 Resmi. Daripada potongan harga kecil tapi garansi dipersulit, di Loewix unit Kakak dijamin ganti baru 100% jika ada kendala.',
                'product_recommendation' => 'Loewix HD Full-Color 4-Camera Package'
            ],
            [
                'type' => 'Teknik Negosiasi & Bonus Value',
                'strategy' => 'Pertahankan harga margin dengan memberikan bonus gratis installasi & kabel.',
                'text' => 'Halo Kak! Kami sangat memahami efisiensi budget Kakak. Khusus penutupan transaksi hari ini, kami berikan bonus gratis kabel HDMI 5m + jaminan free setting koneksi HP selamanya tanpa potongan harga unit!',
                'product_recommendation' => 'Loewix Smart HD Package + Free Setting HP'
            ],
            [
                'type' => 'Strategi Upselling & Offer Upgrade',
                'strategy' => 'Tawarkan upgrade ke IP Camera H.265+ hemat harddisk untuk investasi jangka panjang.',
                'text' => 'Halo Kak! Jika ingin efisiensi maksimal, kami sarankan Upgrade ke Seri Loewix IP Camera H.265+. Teknologi kompresinya menghemat kapasitas Harddisk hingga 50%, jadi Kakak hemat pembelian harddisk tambahan di masa depan.',
                'product_recommendation' => 'Loewix IP Camera 4K H.265+ Series'
            ]
        ];
    }
    // 5. SCENARIO: KOMPETITOR / MERK SEBELAH / DAHUA / HIKVISION / NVR / DVR / ONVIF
    elseif (strpos($q, 'dahua') !== false || strpos($q, 'hikvision') !== false || strpos($q, 'nvr') !== false || strpos($q, 'dvr') !== false || strpos($q, 'onvif') !== false || strpos($q, 'merk') !== false || strpos($q, 'sebelah') !== false) {
        $answers = [
            [
                'type' => 'Kompatibilitas ONVIF Universal',
                'strategy' => 'Tekankan bahwa Kamera Loewix 100% kompatibel dipasang di NVR Dahua/Hikvision merek apa saja.',
                'text' => 'Halo Kak! Kamera Loewix sudah 100% mendukung Protokol ONVIF Standardized. Artinya kamera Loewix bisa langsung dipasang dan terkoneksi ke NVR Dahua, Hikvision, maupun DVR merk lain tanpa perlu ganti NVR lama Kakak.',
                'product_recommendation' => 'Loewix Universal ONVIF IP Camera Series'
            ],
            [
                'type' => 'Keunggulan Starlight Full Color 24 Jam',
                'strategy' => 'Bandingkan kualitas malam hari Loewix yang berwarna vs kompetitor yang masih hitam putih.',
                'text' => 'Halo Kak! Berbeda dengan merk lain yang malam hari hasilnya hitam-putih dan gelap, Loewix dilengkapi Sensor Sony Starlight Full Color yang tetap menampilkan gambar berwarna jernih 24 jam penuh meski dalam kondisi gelap gulita.',
                'product_recommendation' => 'Loewix Starlight Full Color Night Vision'
            ],
            [
                'type' => 'Keunggulan Garansi Ganti Baru 1 Tahun',
                'strategy' => 'Tunjukkan nilai lebih garansi 1-to-1 replacement Loewix dibanding servis garansi impor yang lama.',
                'text' => 'Halo Kak! Loewix menawarkan spesifikasi chipset kelas premium dengan harga direct pabrikan yang jauh lebih ekonomis, plus jaminan Ganti Unit Baru 1-to-1 Resmi Indonesia tanpa nunggu servis lama.',
                'product_recommendation' => 'Loewix Enterprise Grade CCTV'
            ]
        ];
    }
    // 6. SCENARIO: RANDOM / NON-SALES INPUT (e.g. makan nasi, tes, main, tidur)
    elseif (strpos($q, 'nasi') !== false || strpos($q, 'makan') !== false || strpos($q, 'minum') !== false || strpos($q, 'main') !== false || strpos($q, 'tidur') !== false || strpos($q, 'lucu') !== false) {
        $answers = [
            [
                'type' => 'Panduan Konsultasi Prospek Sales Loewix',
                'strategy' => 'Input ini tampaknya santai/tidak berkaitan langsung dengan CCTV. Fokuskan konsultasi pada situasi prospek customer.',
                'text' => 'Halo Sales Loewix! Sepertinya input Kakak tidak berkaitan langsung dengan penawaran CCTV Loewix. Silakan ketik pertanyaan seputar penanganan prospek customer (misal: "Client mau hutang gimana?", "Client sensi ditawarin gimana?"), nego harga, atau spesifikasi produk yang ingin Kakak konsultasikan!',
                'product_recommendation' => 'Asisten Sales Loewix Smart Negotiation Coach'
            ],
            [
                'type' => 'Tips Follow-Up Prospek Sambil Ramah Tamah',
                'strategy' => 'Gunakan topik santai untuk membuka percakapan ramah dengan customer.',
                'text' => 'Halo Kak! Semoga harinya menyenangkan. Sekadar mengingatkan untuk penawaran paket CCTV Loewix kemarin, apakah ada spesifikasi tambahan yang perlu kami kirimkan untuk bahan pertimbangan Kakak hari ini?',
                'product_recommendation' => 'Loewix Customer Relationship Manager'
            ],
            [
                'type' => 'Taktik Warm-Closing & Garansi Unit',
                'strategy' => 'Alihkan percakapan santai customer menjadi kepastian transaksi paket CCTV.',
                'text' => 'Halo Kak! Berhubung stok paket Loewix Full Color minggu ini sangat terbatas, jika Kakak ingin kami amankan unit dan teknisi pasangnya hari ini, kami bisa kirimkan invoice resminya sekarang juga.',
                'product_recommendation' => 'Loewix Fast Closing Package'
            ]
        ];
    }
    // 7. SCENARIO: TOKO / RESELLER / DEALER / GROSIR / MAU JUAL LAGI
    elseif (strpos($q, 'toko') !== false || strpos($q, 'reseller') !== false || strpos($q, 'dealer') !== false || strpos($q, 'grosir') !== false || strpos($q, 'jual lagi') !== false) {
        $answers = [
            [
                'type' => 'Skrip Penawaran Harga Khusus Reseller',
                'strategy' => 'Tawarkan pricelist grosir khusus reseller dengan syarat pengambilan quantity (MOQ).',
                'text' => 'Halo Bosku! Kami membuka kemitraan Reseller & Dealer Resmi Loewix CCTV dengan penawaran harga grosir terbaik & margin keuntungan tinggi. Kami juga sediakan spanduk toko & brosur promosi gratis!',
                'product_recommendation' => 'Loewix Reseller Starter Package'
            ],
            [
                'type' => 'Support Display Unit & Spanduk Toko',
                'strategy' => 'Berikan fasilitas papan demo display CCTV menyala untuk dipajang di toko reseller.',
                'text' => 'Halo Bosku! Untuk membantu penjualan di toko Anda, Loewix menyediakan Demo Board CCTV menyala yang siap dipajang agar calon customer Anda bisa melihat langsung ketajaman warna kamera Loewix.',
                'product_recommendation' => 'Loewix Interactive Demo Board for Stores'
            ],
            [
                'type' => 'Jaminan Garansi Tercepat untuk Pelanggan Toko',
                'strategy' => 'Bantu toko jaga reputasi dengan proses klaim garansi kilat ganti baru.',
                'text' => 'Halo Bosku! Dengan jaminan Garansi Ganti Unit Baru 1-to-1 Loewix, toko Anda akan makin dipercaya pelanggan karena jika ada klaim, penggantian unit dilakukan dengan cepat tanpa proses berbelit.',
                'product_recommendation' => 'Loewix Dealer Protection Plan'
            ]
        ];
    }
    // 7. DEFAULT GENERAL CONSULTATION & NEGOTIATION
    else {
        $answers = [
            [
                'type' => 'Taktik Penawaran Value & Closing Cepat',
                'strategy' => 'Fokus pada penanganan kebutuhan customer & keunggulan garansi ganti baru Loewix.',
                'text' => 'Halo Kak! Terkait situasi prospek Kakak, kami sarankan kirimkan penawaran Paket Loewix CCTV dengan menekankan garansi resmi ganti baru 1 tahun & bonus gratis setting P2P pantau dari smartphone selamanya.',
                'product_recommendation' => 'Loewix Commercial Safety Package'
            ],
            [
                'type' => 'Teknik Negosiasi & Bonus Accessories',
                'strategy' => 'Berikan nilai tambah bonus kabel HDMI & bebas konsultasi setting teknisi.',
                'text' => 'Halo Kak! Untuk meyakinkan calon customer Kakak, infokan bahwa tim teknisi Loewix siap membantu konfigurasikan sistem pantau HP secara gratis saat pemasangan agar tinggal pakai secara instan.',
                'product_recommendation' => 'Loewix Turnkey System'
            ],
            [
                'type' => 'Strategi Upselling & Upgrade IP Camera',
                'strategy' => 'Sarankan tipe IP Camera 4K Ultra HD untuk ketajaman gambar maksimal.',
                'text' => 'Halo Kak! Jika customer mengutamakan hasil rekaman super tajam yang tidak pecah saat di-zoom, sangat disarankan menawarkan Upgrade ke Loewix IP Camera 4K Ultra HD Series.',
                'product_recommendation' => 'Loewix IP Camera 4K Ultra HD Package'
            ]
        ];
    }
}

echo json_encode(['answers' => array_values($answers)]);