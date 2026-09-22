<?php
/**
 * API Sales Version Check
 * Returns the latest and minimum required app version.
 * To force update: set min_version equal to latest_version.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

echo json_encode([
    'latest_version' => '1.6.0',
    'min_version'    => '1.6.0',   // ← Samakan dengan latest_version untuk WAJIB update
    'download_url'   => 'https://api-teknisi.id-giti.com/downloads/LoewixSales-latest.apk',
    'changelog'      => 'Fitur baru: Penambahan kolom Nama Client dan Nomor Client di Clock Out.',
]);
