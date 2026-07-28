<?php
/**
 * Asisten Sales Loewix AI Handler Engine - Master CSO & Negotiation Trainer
 * Context-aware AI engine handling both customer quotes and sales rep situational consultations.
 */

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

$apiKey = 'AIzaSyC9WgTHoRv5qREa5R7LVyOEL58lgn-UaWs';

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
A. Teks obrolan / pertanyaan langsung dari Customer (misal: 'Berapa harganya?', 'Bisa kurang gak?').
B. Curhat / Konsultasi dari Sales Rep mengenai calon customer / segmen tertentu (misal: 'Saya dapat customer Kepala Sekolah', 'Gimana cara nawarin ke sekolah / toko emas / rumah sakit').

Aturan Utama:
Jika input adalah KONSULTASI SALES (Case B), JANGAN MENGULANG KALIMAT KONSULTASI SALES! Tapi buatlah 3 SKRIP CHAT WHATSAPP PERSUASIF YANG BISA LANGSUNG DIKIRIMKAN SALES REPOSITORI TERSEBUT KEPADA CUSTOMER NYA (seperti Bapak/Ibu Kepala Sekolah atau Klien Instansi), lengkap dengan sapaan hormat yang sesuai!

Pengetahuan Produk & Selling Point Loewix:
- Sensor Optik: Sony Starlight & CMOS Smart Sensor (Full Color 24 Jam tetap berwarna di malam hari).
- Kompresi Video: H.265+ High Efficiency (hemat memori harddisk hingga 50%).
- Fitur AI: Smart Human & Vehicle Motion Detection (mengurangi false alarm hingga 98%).
- Layanan Garansi: Garansi 1-to-1 Replacement (Ganti Unit Baru Resmi 1 Tahun) & Service Center Resmi Indonesia.
- Legalitas & Faktur: Menyediakan faktur pajak resmi, SPK, & invoice lengkap untuk instansi/sekolah (Dana BOS/Yayasan).

Tugas Anda:
Berikan 3 Taktik Jawaban / Skrip Chat WhatsApp Sales yang Sangat Profesional, Persuasif, dan Siap Kirim.

Untuk SETIAP OPSI, buat format terstruktur:
1. Opsi 1 (Pendekatan Formal, Kehormatan & Keamanan Area):
   Gunakan bahasa yang sangat sopan & terhormat (seperti Bapak/Ibu Kepala Sekolah / Direktur). Tekankan keamanan murid/area, transparansi pengawasan, dan kualitas rekaman Starlight Full Color.
2. Opsi 2 (Pendekatan Solusi Anggaran & Garansi Ganti Baru 1 Tahun):
   Tekankan kemudahan fleksibilitas anggaran, kelengkapan administrasi/faktur resmi, dan garansi ganti unit baru tanpa merepotkan instansi.
3. Opsi 3 (Pendekatan IP Camera Smart AI & Monitoring Terpusat):
   Tawarkan paket IP Camera Loewix dengan integrasi monitoring terpusat di ruang Kepala Sekolah/Pimpinan & smartphone untuk pemantauan realtime.

Setiap Opsi HARUS berbentuk 1 objek dengan properti:
- \"type\": \"Nama Taktik / Pendekatan\"
- \"strategy\": \"Tips taktik sales 1 kalimat kapan pakai opsi ini\"
- \"text\": \"Teks skrip WA persuasif yang siap dikirim Sales ke Customer (Bapak/Ibu Kepala Sekolah / Klien)\"
- \"product_recommendation\": \"Tipe unit/paket Loewix yang disarankan\"

Kembalikan HANYA format JSON valid seperti ini (tanpa markdown ```json):
{
  \"answers\": [
    {
      \"type\": \"Pendekatan Formal & Keamanan Sekolah\",
      \"strategy\": \"Gunakan untuk pesan pembuka ke Kepala Sekolah / Pihak Yayasan.\",
      \"text\": \"...\",
      \"product_recommendation\": \"Loewix School Safety Package 8-Cam\"
    },
    ...
  ]
}";

// Models to attempt in sequence across v1beta API endpoints
$modelsToTry = [
    'gemini-1.5-flash',
    'gemini-2.0-flash',
    'gemini-1.5-pro'
];

$answers = null;

foreach ($modelsToTry as $modelName) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";
    $postData = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]]
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 7);

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

// Fallback to Loewix Negotiation & Product Offering Engine if API is rate limited
if (empty($answers)) {
    $q = mb_strtolower($customerQuestion);
    
    if (strpos($q, 'sekolah') !== false || strpos($q, 'pendidikan') !== false || strpos($q, 'kepala') !== false || strpos($q, 'guru') !== false) {
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
    } elseif (strpos($q, 'diskon') !== false || strpos($q, 'kurang') !== false || strpos($q, 'mahal') !== false || strpos($q, 'potong') !== false) {
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
    } else {
        $answers = [
            [
                'type' => 'Penawaran Value & Premium Closing',
                'strategy' => 'Fokus pada solusi kebutuhan customer & garansi resmi Loewix.',
                'text' => 'Halo Kak! Mengenai konsultasi kebutuhan Kakak, kami siap berikan skrip penawaran paket Loewix CCTV paling efisien lengkap dengan garansi resmi ganti baru 1 tahun.',
                'product_recommendation' => 'Loewix Commercial Safety Package'
            ],
            [
                'type' => 'Teknik Negosiasi & Bonus Value',
                'strategy' => 'Berikan nilai tambah bonus kabel & bebas konsultasi setting.',
                'text' => 'Halo Kak! Untuk calon customer Kakak, tim teknisi kami siap bantu konfigurasikan sistem pantau HP gratis saat pemasangan agar tinggal pakai secara instan.',
                'product_recommendation' => 'Loewix Turnkey Installation System'
            ],
            [
                'type' => 'Strategi Upselling & Offer Upgrade',
                'strategy' => 'Sarankan tipe IP Camera 4K Ultra HD untuk ketajaman gambar maksimal.',
                'text' => 'Halo Kak! Kami sangat menyarankan menggunakan Loewix IP Camera 4K Ultra HD untuk area ini agar rekaman terlihat sangat jernih dan tidak pecah saat di-zoom.',
                'product_recommendation' => 'Loewix IP Camera 4K Ultra HD Package'
            ]
        ];
    }
}

echo json_encode(['answers' => array_values($answers)]);