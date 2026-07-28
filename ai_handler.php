<?php
/**
 * Asisten Sales Loewix AI Handler Engine
 * Elite Negotiation & Product Upselling Coach for Sales Representatives
 */

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

$apiKey = 'AIzaSyC9WgTHoRv5qREa5R7LVyOEL58lgn-UaWs';

$input = json_decode(file_get_contents('php://input'), true);
$customerQuestion = trim($input['question'] ?? '');

if (empty($customerQuestion)) {
    echo json_encode(['answers' => [
        "Halo Kak! Terima kasih sudah menghubungi Sales Loewix CCTV. Ada kebutuhan kamera keamanan atau spesifikasi area yang ingin dikonsultasikan hari ini?",
        "Halo Kak! Salam hangat dari Loewix CCTV Indonesia. Kami menyediakan solusi paket CCTV siap pakai dari paket ekonomis hingga sistem IP Camera 4K pintar. Mau kami rekomendasikan paket terbaik?",
        "Halo Kak! Siap membantu konsultasi & estimasi biaya pemasangan CCTV Loewix. Silakan informasikan lokasi & jumlah titik kamera yang dibutuhkan ya Kak."
    ]]);
    exit;
}

$prompt = "Anda adalah Senior Sales Director & Expert Negotiation Coach untuk merek CCTV terkemuka 'Loewix CCTV Indonesia'. 
Tugas Anda adalah melatih dan memberikan 3 strategi & respons siap pakai untuk tim Sales Loewix dalam menghadapi customer, mengatasi penolakan (handling objections), melakukan negosiasi harga, dan menawarkan produk unggulan/upselling.

Konteks Pertanyaan / Keluhan / Situasi Customer: \"{$customerQuestion}\"

Berikan 3 variasi respons taktis yang dapat langsung dikirimkan Sales ke WhatsApp Customer:

1. Opsi 1 (Penawaran Value & Closing Persuasif):
   Fokus menonjolkan value produk Loewix (sensor chipset HD, garansi resmi ganti baru, fitur Full Color Night Vision) daripada sekadar memotong harga. Ramah, meyakinkan, dan mendorong closing.

2. Opsi 2 (Teknik Negosiasi & Handling Objection):
   Fokus meredam keberatan customer (seperti minta diskon besar / merek lain lebih murah) dengan menawarkan bonus value (bebas biaya setting / bonus kabel / paket promo) agar margin sales tetap terjaga.

3. Opsi 3 (Strategi Upselling & Penawaran Produk Unggulan):
   Fokus menawarkan upgrade unit/paket yang lebih tinggi (seperti upgrade ke IP Camera 4K, Smart AI Motion Detection, atau Harddisk lebih besar) dengan argumen investasi jangka panjang.

Aturan Penulisan:
- Gunakan bahasa sales profesional khas Indonesia yang ramah, sopan, persuasif, dan alami (seperti obrolan WhatsApp sales ahli, BUKAN seperti robot/AI).
- Maksimal 3-4 kalimat per opsi.
- Kembalikan HANYA format JSON valid tanpa tanda markdown:
{\"answers\": [\"(Opsi 1...)\", \"(Opsi 2...)\", \"(Opsi 3...)\"]}";

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);

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
            "Halo Kak! Untuk harga paket CCTV Loewix ini sudah merupakan harga promo terbaik dengan garansi ganti baru resmi 1 tahun. Agar lebih hemat, mohon izin kami berikan bonus gratis kabel & jaminan free setting online ya Kak!",
            "Halo Kak! Kami paham budget sangat penting. Khusus transaksi minggu ini, jika Kakak ambil paket 4 Kamera Loewix, kami berikan potongan khusus pendaftaran member installer + bonus kabel HDMI gratis Kak!",
            "Halo Kak! Daripada ambil spesifikasi standar, kami sangat sarankan paket Loewix Full Color Night Vision ini karena hasil rekaman malam harinya tetap berwarna terang. Investasi jangka panjangnya jauh lebih menguntungkan Kak."
        ];
    } elseif (strpos($q, 'merek') !== false || strpos($q, 'sebelah') !== false || strpos($q, 'lain') !== false || strpos($q, 'banding') !== false) {
        $answers = [
            "Halo Kak! Loewix CCTV mengunggulkan kualitas sensor chipset terbaru dengan daya tahan cuaca ekstrem dan garansi replace ganti baru tanpa ribet. Kualitas rekaman kami jauh lebih jernih di kelasnya Kak.",
            "Halo Kak! Merek lain mungkin menawarkan harga sedikit di bawah, namun Loewix memberikan layanan purna jual Service Center resmi dan gratis aplikasi pemantauan HP selamanya tanpa biaya langganan bulanan.",
            "Halo Kak! Jika dibandingkan spesifikasinya, Loewix sudah dilengkapi fitur Smart Human Motion Detection yang mengurangi salah alarm hingga 95%. Sangat disarankan untuk keamanan maksimal tempat usaha Kakak."
        ];
    } elseif (strpos($q, 'paket') !== false || strpos($q, 'rekomendasi') !== false || strpos($q, 'toko') !== false || strpos($q, 'rumah') !== false) {
        $answers = [
            "Halo Kak! Untuk ruko/toko, kami rekomendasikan Paket Loewix 4 Kamera Full Color 2MP (lengkap DVR + Harddisk 1TB + Power Supply). Gambar tetap berwarna jernih meski kondisi malam gelap gulita!",
            "Halo Kak! Jika ingin hasil rekaman ekstra tajam yang bisa zoom plat nomor kendaraan, kami sarankan Upgrade ke Paket IP Camera Loewix 4K 5MP. Kualitas rekamannya sangat detail dan tahan lama.",
            "Halo Kak! Kami menyediakan paket custom sesuai titik lokasi. Boleh kami kirimkan brosur perbandingan Paket Analog HD vs Paket IP Camera Loewix ke WhatsApp Kakak?"
        ];
    } else {
        $answers = [
            "Halo Kak! Mengenai '" . htmlspecialchars($customerQuestion) . "', kami siap berikan penawaran harga terbaik serta garansi resmi ganti baru khusus pemesanan hari ini.",
            "Halo Kak! Untuk kebutuhan '" . htmlspecialchars($customerQuestion) . "', kami bisa sertakan bonus pemasangan & free konfig pemantauan HP agar Kakak tinggal terima beres.",
            "Halo Kak! Kami sarankan ambil tipe Loewix IP Camera Series untuk '" . htmlspecialchars($customerQuestion) . "' agar hasil rekaman lebih jernih dan dapat dipantau dari mana saja secara realtime."
        ];
    }
}

echo json_encode(['answers' => array_values($answers)]);