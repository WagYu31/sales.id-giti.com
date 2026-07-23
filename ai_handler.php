<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- KONFIGURASI PENTING ---
$apiKey = 'AIzaSyC9WgTHoRv5qREa5R7LVyOEL58lgn-UaWs';

$input = json_decode(file_get_contents('php://input'), true);
$customerQuestion = $input['question'] ?? '';

if (empty($customerQuestion)) {
    echo json_encode(['error' => 'Pertanyaan tidak boleh kosong.']);
    exit;
}

$prompt = "Anda adalah asisten sales yang sangat ahli untuk merek CCTV bernama 'Loewix'. Anda selalu memberikan jawaban yang sopan, profesional, membantu, namun tidak kaku.
Berdasarkan pertanyaan dari customer berikut, berikan 3 opsi jawaban yang berbeda dan relevan untuk digunakan oleh seorang sales. Jangan seperti AI. Jawaban harus ramah, friendly, tidak kaku, tapi tetap sopan khas sales profesional

Pertanyaan Customer: '{$customerQuestion}'

Tugas Anda:
1.  Cari tahu dan pelajari tentang CCTV merek Loewix. Dan coba kamu sebagai sales resmi Loewix.
2.  Buat 3 (tiga) variasi jawaban yang unik.
3.  Setiap jawaban harus dalam satu paragraf singkat (maksimal 3 kalimat), sopan tapi jangan terlalu kaku, seperti manusia sungguhan bukan AI.
4.  Pastikan jawaban relevan dengan produk CCTV Loewix atau info seputar Loewix.
5.  Jangan berikan jawaban bertele-tele, ingat kamu adalah sales profesional Loewix.
6.  Kembalikan jawaban HANYA dalam format JSON yang valid, seperti ini:
    {\"answers\": [\"(Opsi jawaban pertama di sini)\", \"(Opsi jawaban kedua di sini)\", \"(Opsi jawaban ketiga di sini)\"]}
";

$url = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

$data = [
    'contents' => [['parts' => [['text' => $prompt]]]]
];

$jsonData = json_encode($data);

$headers = [
    'Content-Type: application/json',
];


// die("URL yang sedang dicoba: " . htmlspecialchars($url));

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

$responseData = json_decode($response, true);

if (isset($responseData['error'])) {
    echo json_encode(['error' => 'API Error: ' . $responseData['error']['message']]);
} elseif (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $aiGeneratedText = $responseData['candidates'][0]['content']['parts'][0]['text'];
    
    // --- PERBAIKAN FINAL: Bersihkan output dari AI ---
    // Hapus Markdown fences (```json dan ```) yang mungkin ditambahkan oleh AI
    $cleanedJsonString = preg_replace('/^```json\s*/', '', $aiGeneratedText);
    $cleanedJsonString = preg_replace('/\s*```$/', '', $cleanedJsonString);
    $cleanedJsonString = trim($cleanedJsonString);

    // Kirimkan JSON yang sudah bersih ke browser
    echo $cleanedJsonString;

} else {
    echo json_encode(['error' => 'Format respons dari AI tidak dikenal.', 'details' => $responseData]);
}