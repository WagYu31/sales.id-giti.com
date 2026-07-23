<?php
// ai_handler.php (Versi Percakapan)
session_start(); // Mulai session untuk menyimpan riwayat chat

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- KONFIGURASI PENTING ---
$apiKey = 'AIzaSyC9WgTHoRv5qREa5R7LVyOEL58lgn-UaWs';

// Inisialisasi riwayat chat jika belum ada
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

$input = json_decode(file_get_contents('php://input'), true);
$userQuestion = $input['question'] ?? '';

if (empty($userQuestion)) {
    echo json_encode(['error' => 'Pertanyaan tidak boleh kosong.']);
    exit;
}

// Persona dan instruksi dasar untuk AI
$system_instruction = "Anda adalah Asisten Sales Loewix, sebuah AI yang ramah, sopan, dan sangat membantu. Jawab pertanyaan pengguna secara langsung dan informatif dalam satu balasan singkat dan jelas. Jangan memberikan opsi, berikan jawaban langsung.";

// Bangun 'contents' dari riwayat dan pertanyaan baru
$contents = [];
// Tambahkan instruksi sistem di awal (jika riwayat kosong)
if(empty($_SESSION['chat_history'])){
    $contents[] = ['role' => 'user', 'parts' => [['text' => $system_instruction]]];
    $contents[] = ['role' => 'model', 'parts' => [['text' => 'Tentu, saya siap membantu. Ada yang bisa saya bantu jelaskan tentang produk Loewix?']]];
}
// Tambahkan riwayat percakapan
foreach ($_SESSION['chat_history'] as $entry) {
    $contents[] = [
        'role' => $entry['role'],
        'parts' => [['text' => $entry['text']]]
    ];
}
// Tambahkan pertanyaan baru dari pengguna
$contents[] = ['role' => 'user', 'parts' => [['text' => $userQuestion]]];


$url = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=' . $apiKey;
$data = ['contents' => $contents];
$jsonData = json_encode($data);

$headers = ['Content-Type: application/json'];
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

if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $aiReply = $responseData['candidates'][0]['content']['parts'][0]['text'];

    // Simpan percakapan baru ke session
    $_SESSION['chat_history'][] = ['role' => 'user', 'text' => $userQuestion];
    $_SESSION['chat_history'][] = ['role' => 'model', 'text' => $aiReply];

    // Kirim balasan tunggal ke frontend
    echo json_encode(['reply' => $aiReply]);
} else {
    echo json_encode(['error' => 'Gagal mendapatkan balasan dari AI.', 'details' => $responseData]);
}