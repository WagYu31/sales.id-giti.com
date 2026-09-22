<?php
/**
 * Scraping Google Maps — Foursquare V2 Venues API (GRATIS)
 * 
 * Menggunakan Foursquare V2 API dengan client_id + client_secret
 * Data: nama toko, alamat lengkap, koordinat, kategori, dll.
 */

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'conn.php';
require_once 'gmaps-config.php';
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => true, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'search';

if ($action === 'stats') {
    echo json_encode(['error' => false, 'stats' => gmaps_get_stats()]);
    exit;
}

if ($action === 'search') {
    $keyword = trim($input['keyword'] ?? '');
    $city    = trim($input['city'] ?? '');
    $radius  = intval($input['radius'] ?? 25000);

    if (empty($keyword) || empty($city)) {
        echo json_encode(['error' => true, 'message' => 'Kata kunci dan kota wajib diisi']);
        exit;
    }

    // Rate limit
    $limit = gmaps_check_limit();
    if (!$limit['allowed']) {
        echo json_encode(['error' => true, 'blocked' => true, 'message' => $limit['reason'], 'stats' => gmaps_get_stats()]);
        exit;
    }

    // Cache (7 hari)
    $cacheDir = __DIR__ . '/gmaps_cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $cacheKey = md5($keyword . '|' . $city . '|' . $radius . '|v2');
    $cacheFile = $cacheDir . '/' . $cacheKey . '.json';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 604800) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && !empty($cached['results'])) {
            gmaps_increment_usage();
            $cached['from_cache'] = true;
            $cached['stats'] = gmaps_get_stats();
            echo json_encode($cached);
            exit;
        }
    }

    // City coordinates
    $cityCoords = getCityCoordinates();
    $lat = null; $lng = null;
    foreach ($cityCoords as $name => $coords) {
        if (strtolower($name) === strtolower(trim($city)) || stripos($name, $city) !== false || stripos($city, $name) !== false) {
            $lat = $coords[0]; $lng = $coords[1]; break;
        }
    }
    if (!$lat || !$lng) {
        echo json_encode(['error' => true, 'message' => "Koordinat kota '$city' tidak ditemukan"]);
        exit;
    }

    // ═══ Foursquare V2 Venues Search ═══
    $clientId = defined('FSQ_CLIENT_ID') ? FSQ_CLIENT_ID : '';
    $clientSecret = defined('FSQ_CLIENT_SECRET') ? FSQ_CLIENT_SECRET : '';
    
    if (empty($clientId) || empty($clientSecret)) {
        echo json_encode(['error' => true, 'message' => 'Foursquare Client ID/Secret belum dikonfigurasi di gmaps-config.php']);
        exit;
    }

    // ═══ Foursquare V2 Venues Search ═══
    $clientId = defined('FSQ_CLIENT_ID') ? FSQ_CLIENT_ID : '';
    $clientSecret = defined('FSQ_CLIENT_SECRET') ? FSQ_CLIENT_SECRET : '';
    
    if (empty($clientId) || empty($clientSecret)) {
        echo json_encode(['error' => true, 'message' => 'Foursquare Client ID/Secret belum dikonfigurasi di gmaps-config.php']);
        exit;
    }

    // Determine query terms: if user typed "Toko CCTV", query both "CCTV" and "Toko CCTV" for precision
    $queryTerms = [$keyword];
    if (preg_match('/\b(cctv|security|kamera|camera|alarm|fingerprint|access control)\b/i', $keyword, $m)) {
        $coreTerm = $m[1];
        if (strtolower($coreTerm) !== strtolower($keyword)) {
            array_unshift($queryTerms, $coreTerm);
        }
    }

    $rawVenues = [];
    $seenIds = [];

    foreach ($queryTerms as $qTerm) {
        $fsqUrl = 'https://api.foursquare.com/v2/venues/search?' . http_build_query([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'v'             => '20260717',
            'query'         => $qTerm,
            'll'            => "$lat,$lng",
            'radius'        => min($radius, 100000),
            'limit'         => 50,
            'intent'        => 'browse',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $fsqUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            $data = json_decode($response, true);
            $vList = $data['response']['venues'] ?? [];
            foreach ($vList as $v) {
                $vId = $v['id'] ?? md5($v['name'] ?? '');
                if (!isset($seenIds[$vId])) {
                    $seenIds[$vId] = true;
                    $rawVenues[] = $v;
                }
            }
        }
    }

    gmaps_increment_usage();

    // ═══ RELEVANCE & NEGATIVE FILTERING ═══
    $cctvKeywords = ['cctv', 'security', 'kamera', 'camera', 'hikvision', 'dahua', 'ezviz', 'imou', 'alarm', 'fingerprint', 'access control', 'absen', 'keamanan', 'ip cam', 'surveillance'];
    $negativeKeywords = [
        'mas', 'emas', 'jewel', 'perhiasan', 'bata', 'sepatu', 'shoes', 'baju', 'pakaian', 'fashion', 'clothing',
        'kue', 'bakery', 'roti', 'kelontong', 'sembako', 'warung', 'obat', 'apotek', 'pharmacy', 'farmasi',
        'salon', 'barber', 'laundry', 'optik', 'optical', 'jam', 'nasi', 'resto', 'restaurant', 'cafe', 'kuliner',
        'daging', 'ikan', 'buah', 'sayur', 'mainan', 'toy', 'sepeda', 'motor', 'mobil', 'bengkel', 'tambal',
        'minimarket', 'indomaret', 'alfamart', 'fotocopy', 'fotokopi', 'atk', 'buku'
    ];

    $isCctvSearch = false;
    foreach ($cctvKeywords as $ck) {
        if (stripos($keyword, $ck) !== false) {
            $isCctvSearch = true;
            break;
        }
    }

    $results = [];

    foreach ($rawVenues as $venue) {
        $name = $venue['name'] ?? '';
        if (empty($name)) continue;

        $nameLower = strtolower($name);
        $catName = !empty($venue['categories']) ? ($venue['categories'][0]['name'] ?? '') : '';
        $catLower = strtolower($catName);

        // Strict filter for CCTV / Security searches
        if ($isCctvSearch) {
            $hasCctvTerm = false;
            foreach ($cctvKeywords as $ck) {
                if (stripos($nameLower, $ck) !== false || stripos($catLower, $ck) !== false) {
                    $hasCctvTerm = true;
                    break;
                }
            }

            $isTechOrElec = (stripos($catLower, 'electronic') !== false || stripos($catLower, 'computer') !== false || stripos($catLower, 'technology') !== false || stripos($catLower, 'it services') !== false || stripos($nameLower, 'elektronik') !== false || stripos($nameLower, 'komputer') !== false || stripos($nameLower, 'tech') !== false);

            if (!$hasCctvTerm) {
                // If it doesn't mention CCTV/Security explicitly, check for negative retail terms
                $isNegative = false;
                foreach ($negativeKeywords as $neg) {
                    if (preg_match('/\b' . preg_quote($neg, '/') . '\b/i', $nameLower) || preg_match('/\b' . preg_quote($neg, '/') . '\b/i', $catLower)) {
                        $isNegative = true;
                        break;
                    }
                }
                // Reject if negative match OR not even tech/elec related
                if ($isNegative || !$isTechOrElec) {
                    continue;
                }
            }
        }

        // Address
        $loc = $venue['location'] ?? [];
        $address = $loc['address'] ?? '';
        $formattedAddr = implode(', ', $loc['formattedAddress'] ?? []);
        if (empty($address) && !empty($formattedAddr)) $address = $formattedAddr;

        // Phone
        $phone = $venue['contact']['formattedPhone'] ?? $venue['contact']['phone'] ?? '';
        
        // Website
        $website = $venue['url'] ?? '';
        
        // Category
        $category = $catName;

        // Coordinates
        $placeLat = $loc['lat'] ?? $lat;
        $placeLng = $loc['lng'] ?? $lng;
        $distance = $loc['distance'] ?? 0; // meters

        // Google Maps URL  
        $mapsUrl = 'https://www.google.com/maps/search/' . urlencode($name . ' ' . ($loc['city'] ?? $city));

        // Trust Score
        $trustScore = 10;
        if (!empty($address)) $trustScore += 15;
        if (!empty($phone)) $trustScore += 20;
        if (!empty($website)) $trustScore += 10;
        if (!empty($category)) $trustScore += 10;
        if ($placeLat != $lat) $trustScore += 10;
        if ($distance < 5000) $trustScore += 10;

        $trustLevel = 'berisiko';
        if ($trustScore >= 60) $trustLevel = 'terpercaya';
        elseif ($trustScore >= 35) $trustLevel = 'perlu_cek';

        $results[] = [
            'place_id'          => $venue['id'] ?? md5($name),
            'name'              => $name,
            'address'           => $address,
            'formatted_address' => $formattedAddr,
            'phone'             => $phone,
            'website'           => $website,
            'maps_url'          => $mapsUrl,
            'rating'            => 0,
            'review_count'      => 0,
            'photo_count'       => 0,
            'photo_ref'         => '',
            'lat'               => $placeLat,
            'lng'               => $placeLng,
            'distance_m'        => $distance,
            'business_status'   => ($venue['closed'] ?? false) ? 'Tutup' : 'Buka',
            'types'             => [$category],
            'primary_type'      => $category,
            'trust_score'       => $trustScore,
            'trust_level'       => $trustLevel,
            'city'              => $loc['city'] ?? $city,
            'state'             => $loc['state'] ?? '',
            'postal_code'       => $loc['postalCode'] ?? '',
        ];
    }

    usort($results, function($a, $b) { return $a['distance_m'] - $b['distance_m']; });

    $responseData = [
        'error'         => false,
        'keyword'       => $keyword,
        'city'          => $city,
        'total_results' => count($results),
        'results'       => $results,
        'scraped_at'    => date('Y-m-d H:i:s'),
        'from_cache'    => false,
        'source'        => 'Foursquare Places API',
    ];
    @file_put_contents($cacheFile, json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

    $responseData['stats'] = gmaps_get_stats();
    echo json_encode($responseData);
    exit;
}

echo json_encode(['error' => true, 'message' => 'Action tidak valid']);

// ══════════════════════════════════════════════════
function getCityCoordinates() {
    return [
        'Tangerang' => [-6.1783, 106.6319], 'Tangerang Selatan' => [-6.2886, 106.7183],
        'Jakarta Pusat' => [-6.1862, 106.8345], 'Jakarta Selatan' => [-6.2615, 106.8106],
        'Jakarta Barat' => [-6.1484, 106.7558], 'Jakarta Timur' => [-6.2250, 106.9004],
        'Jakarta Utara' => [-6.1380, 106.8631], 'Bekasi' => [-6.2383, 106.9756],
        'Bogor' => [-6.5971, 106.8060], 'Depok' => [-6.4025, 106.7942],
        'Bandung' => [-6.9175, 107.6191], 'Surabaya' => [-7.2575, 112.7521],
        'Semarang' => [-6.9666, 110.4196], 'Yogyakarta' => [-7.7972, 110.3688],
        'Medan' => [3.5952, 98.6722], 'Makassar' => [-5.1477, 119.4327],
        'Palembang' => [-2.9761, 104.7754], 'Denpasar' => [-8.6500, 115.2167],
        'Malang' => [-7.9666, 112.6326], 'Surakarta' => [-7.5755, 110.8243],
        'Pekanbaru' => [0.5071, 101.4478], 'Balikpapan' => [-1.2379, 116.8529],
        'Manado' => [1.4748, 124.8421], 'Pontianak' => [-0.0263, 109.3425],
        'Banjarmasin' => [-3.3194, 114.5907], 'Samarinda' => [-0.4948, 117.1436],
        'Serang' => [-6.1103, 106.1512], 'Cilegon' => [-6.0023, 106.0507],
        'Karawang' => [-6.3227, 107.3376], 'Cirebon' => [-6.7320, 108.5523],
        'Tasikmalaya' => [-7.3274, 108.2207], 'Sukabumi' => [-6.9277, 106.9300],
        'Purwokerto' => [-7.4214, 109.2342], 'Tegal' => [-6.8797, 109.1426],
        'Pekalongan' => [-6.8886, 109.6753], 'Kediri' => [-7.8167, 112.0170],
        'Madiun' => [-7.6298, 111.5300], 'Jember' => [-8.1727, 113.6881],
        'Jambi' => [-1.6101, 103.6131], 'Padang' => [-0.9471, 100.4172],
        'Bandar Lampung' => [-5.3971, 105.2668], 'Mataram' => [-8.5833, 116.1167],
        'Kupang' => [-10.1772, 123.6070], 'Ambon' => [-3.6954, 128.1814],
        'Jayapura' => [-2.5916, 140.6690], 'Batam' => [1.0456, 104.0305],
        'Cikarang' => [-6.3103, 107.1731], 'Sidoarjo' => [-7.4478, 112.7183],
        'Gresik' => [-7.1625, 112.6513], 'Kudus' => [-6.8048, 110.8405],
    ];
}
?>
