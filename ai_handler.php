<?php
/**
 * Asisten Sales Loewix AI Handler Engine - Master CSO & Negotiation Trainer
 * Provides structured tactical advice, ready-to-send WhatsApp scripts, and product upsell recommendations.
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

Pengetahuan Produk & Selling Point Loewix:
- Sensor Optik: Sony Starlight & CMOS Smart Sensor (Full Color 24 Jam tetap berwarna di malam hari).
- Kompresi Video: H.265+ High Efficiency (hemat memori harddisk hingga 50%).
- Fitur AI: Smart Human & Vehicle Motion Detection (mengurangi false alarm hingga 98%).
- Layanan Garansi: Garansi 1-to-1 Replacement (Ganti Unit Baru Resmi 1 Tahun) & Service Center Resmi Indonesia.
- Aplikasi Mobile: Loewix Smart / XMEye (Gratis seumur hidup, cloud P2P instant remote viewing tanpa IP Publik).

Situasi / Pertanyaan Customer: \"{$customerQuestion}\"

Tugas Anda:
Berikan 3 Taktik Jawaban Negosiasi Sales yang Sangat Profesional, Persuasif, dan Menjual.

Untuk SETIAP OPSI, buat format terstruktur:
1. Opsi 1 (Penawaran Value & Premium Closing):
   Fokus menonjolkan kualitas sensor Starlight Full Color, Garansi Replace Baru 1-to-1, dan hemat memori H.265+.
2. Opsi 2 (Handling Objection & Bonus Value Negosiasi):
   Atasi penolakan harga / kompetitor dengan memberikan bonus gratis setting HP, bonus kabel premium HDMI, atau voucher promo agar margin sales aman.
3. Opsi 3 (Upselling & Offer Upgrade Unit High-End):
   Fokus mengarahkan upgrade ke Loewix IP Camera 4K atau Smart AI Motion Detection dengan penjelasan return on investment jangka panjang.

Setiap Opsi HARUS berbentuk 1 objek dengan properti:
- \"type\": \"Nama Taktik\"
- \"strategy\": \"Tips taktik sales 1 kalimat kapan pakai opsi ini\"
- \"text\": \"Teks chat WA persuasif maksimal 3-4 kalimat, sopan, ramah, dan sangat menjual\"
- \"product_recommendation\": \"Tipe unit/paket Loewix yang disarankan\"

Kembalikan HANYA format JSON valid seperti ini (tanpa markdown ```json):
{
  \"answers\": [
    {
      \"type\": \"Penawaran Value & Premium Closing\",
      \"strategy\": \"Gunakan jika customer mencari kualitas rekaman terbaik & kejelasan plat nomor malam hari.\",
      \"text\": \"...\",
      \"product_recommendation\": \"Loewix Ultra HD Starlight 5MP Package\"
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
    
    if (strpos($q, 'diskon') !== false || strpos($q, 'kurang') !== false || strpos($q, 'mahal') !== false || strpos($q, 'potong') !== false) {
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
    } elseif (strpos($q, 'merek') !== false || strpos($q, 'sebelah') !== false || strpos($q, 'lain') !== false || strpos($q, 'banding') !== false) {
        $answers = [
            [
                'type' => 'Penawaran Value & Premium Closing',
                'strategy' => 'Bandingkan kualitas sensor Starlight Full Color & garansi resmi lokal.',
                'text' => 'Halo Kak! Merek lain mungkin lebih murah beberapa puluh ribu, namun Loewix mengunggulkan Sensor Sony Starlight yang tetap merekam berwarna terang di malam hari. Ditambah garansi ganti unit baru di Service Center Resmi Indonesia.',
                'product_recommendation' => 'Loewix Sony Starlight Full Color Series'
            ],
            [
                'type' => 'Teknik Negosiasi & Bonus Value',
                'strategy' => 'Tekankan bebas biaya langganan aplikasi cloud selamanya.',
                'text' => 'Halo Kak! Hati-hati dengan merek murah yang mengenakan biaya langganan aplikasi bulanan. Aplikasi pemantauan HP Loewix Smart gratis 100% selamanya tanpa biaya tersembunyi!',
                'product_recommendation' => 'Loewix Smart P2P App Integration'
            ],
            [
                'type' => 'Strategi Upselling & Offer Upgrade',
                'strategy' => 'Gunakan fitur AI Smart Human Detection untuk mengalahkan kompetitor.',
                'text' => 'Halo Kak! Dibandingkan CCTV standar merek lain, Loewix sudah dilengkapi fitur Smart Human Detection yang mampu membedakan gerakan manusia dengan hewan/angin, mencegah salah alarm hingga 98%.',
                'product_recommendation' => 'Loewix Smart AI Detection Series'
            ]
        ];
    } else {
        $answers = [
            [
                'type' => 'Penawaran Value & Premium Closing',
                'strategy' => 'Fokus pada solusi kebutuhan customer & garansi resmi Loewix.',
                'text' => 'Halo Kak! Mengenai "' . htmlspecialchars($customerQuestion) . '", kami siap berikan penawaran paket Loewix CCTV paling efisien lengkap dengan garansi resmi ganti baru 1 tahun.',
                'product_recommendation' => 'Loewix Commercial Safety Package'
            ],
            [
                'type' => 'Teknik Negosiasi & Bonus Value',
                'strategy' => 'Berikan nilai tambah bonus kabel & bebas konsultasi setting.',
                'text' => 'Halo Kak! Untuk pertanyaan Kakak, tim teknisi kami siap bantu konfigurasikan sistem pantau HP gratis saat pemasangan agar Kakak tinggal pakai secara instan.',
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