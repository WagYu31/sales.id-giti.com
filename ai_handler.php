<?php
/**
 * Asisten Sales Loewix AI Handler Engine
 * Multi-Model Fallback & Smart Sales Template Backup
 */

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

$apiKey = 'AIzaSyC9WgTHoRv5qREa5R7LVyOEL58lgn-UaWs';

$input = json_decode(file_get_contents('php://input'), true);
$customerQuestion = trim($input['question'] ?? '');

if (empty($customerQuestion)) {
    echo json_encode(['answers' => [
        "Halo Kak! Terima kasih sudah menghubungi Sales Loewix CCTV. Ada yang bisa kami bantu terkait kebutuhan keamanan hari ini?",
        "Halo Kak! Salam hangat dari Loewix CCTV. Apakah ada tipe kamera atau lokasi pemasangan yang ingin dikonsultasikan?",
        "Halo Kak! Kami siap membantu memberikan info produk & rekomendasi paket CCTV Loewix terbaik. Silakan informasikan kebutuhannya ya Kak."
    ]]);
    exit;
}

$prompt = "Anda adalah asisten sales profesional Loewix CCTV Indonesia.
Berdasarkan pertanyaan dari customer berikut, berikan 3 opsi cara menjawab yang berbeda, ramah, profesional, dan mudah dipahami.

Pertanyaan Customer: \"{$customerQuestion}\"

Tugas Anda:
1. Buat 3 (tiga) variasi jawaban unik dari sudut pandang sales resmi Loewix CCTV.
2. Setiap jawaban maksimal 3 kalimat, sopan, ramah, tidak kaku (seperti manusia sungguhan bukan AI).
3. Kembalikan HANYA format JSON valid tanpa tanda backtick markdown seperti ini:
{\"answers\": [\"Opsi 1...\", \"Opsi 2...\", \"Opsi 3...\"]}";

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

// Fallback to Smart Loewix Contextual Sales Engine if Google API is rate limited
if (empty($answers)) {
    $q = mb_strtolower($customerQuestion);
    
    if (strpos($q, 'harga') !== false || strpos($q, 'berapa') !== false || strpos($q, 'biaya') !== false || strpos($q, 'paket') !== false || strpos($q, 'murah') !== false) {
        $answers = [
            "Halo Kak! Untuk estimasi harga & paket CCTV Loewix terbaru sangat bervariasi sesuai jumlah channel & resolusi kamera. Kakak mau untuk area rumah atau tempat usaha/toko?",
            "Halo Kak! Kami menyediakan promo paket CCTV Loewix siap pakai (lengkap DVR + Harddisk + Kabel & Pemasangan). Boleh tahu rencana pemasangan di kota mana Kak?",
            "Halo Kak! Paket CCTV Loewix kami mulai dari paket ekonomis hingga resolusi ultra HD/IP Camera. Kami kirimkan brosur price list lengkapnya ke WA Kakak ya?"
        ];
    } elseif (strpos($q, 'garansi') !== false || strpos($q, 'rusak') !== false || strpos($q, 'klaim') !== false || strpos($q, 'service') !== false || strpos($q, 'mati') !== false) {
        $answers = [
            "Halo Kak! Seluruh produk CCTV Loewix dilengkapi Garansi Resmi. Kakak bisa membawa unit ke Service Center resmi kami atau mengontak tim support kami dengan menyertakan nomor serial/nota.",
            "Halo Kak! Jangan khawatir, garansi produk Loewix dijamin aman. Mohon infokan kendala atau nomor nota pembelian agar kami bantu koordinasikan dengan tim teknisi kami.",
            "Halo Kak! Kami siap membantu proses klaim garansi produk Loewix Kakak. Silakan infokan nomor invoice atau foto serial number di stiker DVR/kamera ya Kak."
        ];
    } elseif (strpos($q, 'halo') !== false || strpos($q, 'hai') !== false || strpos($q, 'pagi') !== false || strpos($q, 'siang') !== false || strpos($q, 'sore') !== false || strpos($q, 'malam') !== false) {
        $answers = [
            "Halo Kak! Terima kasih sudah menghubungi Sales Loewix CCTV. Ada yang bisa kami bantu terkait kebutuhan kamera keamanan Kakak hari ini?",
            "Halo Kak! Salam hangat dari Loewix CCTV Indonesia. Apakah ada tipe kamera atau paket CCTV yang ingin dikonsultasikan?",
            "Halo Kak! Kami siap membantu memberikan informasi produk & rekomendasi paket CCTV Loewix terbaik sesuai budget Kakak."
        ];
    } else {
        $answers = [
            "Halo Kak! Terima kasih pertanyaannya. Mengenai '" . htmlspecialchars($customerQuestion) . "', kami siap berikan informasi & konsultasi lengkap produk Loewix terbaik untuk Kakak.",
            "Halo Kak! Salam dari Sales Loewix. Untuk kebutuhan '" . htmlspecialchars($customerQuestion) . "', tim kami bisa rekomendasikan spesifikasi unit yang paling cocok dan efisien untuk Kakak.",
            "Halo Kak! Mohon izin bantu rekomendasikan. Agar jawabannya lebih akurat, apakah Kakak butuh CCTV indoor/outdoor atau tipe IP Camera nirkabel?"
        ];
    }
}

echo json_encode(['answers' => array_values($answers)]);