<?php
/**
 * includes/openwa_helper.php
 * Engine Klien OpenWA (rmyndharis/OpenWA) & 8-Layer Anti-Ban / Anti-Logout Protection
 * untuk Loewix Sales CRM (sales.id-giti.com).
 *
 * 8 Lapis Proteksi:
 *  1. Multi-Session & Auto Load-Balancing antar nomor sales (Least-Used First)
 *  2. Pre-Flight Check Nomor Aktif WhatsApp (contacts/check) — skip nomor mati
 *  3. Simulasi Mengetik Manusia (SIMULATE_TYPING + chats/typing dinamis)
 *  4. Spintax & Anti-Duplicate Fingerprint (tiap pesan unik secara kriptografis)
 *  5. Daily Cap & Mode Warmup Guardrail (Warmup: 20, Normal: 45, Boost: 75/hari)
 *  6. Circuit Breaker Otomatis (pause 30 menit jika 3x gagal / disconnect / restricted)
 *  7. Deduplikasi Anti-Spam Ganda (auto-skip toko yang sudah di-BC dalam N hari)
 *  8. Jam Operasional Guard (08:00-19:30 WIB, bisa di-override untuk tes)
 */

if (!function_exists('ensureOpenWATables')) {
    function ensureOpenWATables($conn) {
        static $done = false;
        if ($done || !$conn) return;
        $done = true;

        $conn->query("
            CREATE TABLE IF NOT EXISTS `wa_openwa_sessions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(120) NOT NULL UNIQUE,
                `session_name` VARCHAR(120) NOT NULL,
                `label` VARCHAR(150) NOT NULL DEFAULT '',
                `phone_number` VARCHAR(35) NOT NULL DEFAULT '',
                `sales_id` INT NULL DEFAULT NULL,
                `mode` ENUM('warmup','normal','boost') NOT NULL DEFAULT 'warmup',
                `daily_limit` INT NOT NULL DEFAULT 20,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_status` VARCHAR(50) NOT NULL DEFAULT 'created',
                `last_restriction` VARCHAR(255) NULL DEFAULT NULL,
                `last_error` TEXT NULL,
                `consecutive_fails` INT NOT NULL DEFAULT 0,
                `paused_until` DATETIME NULL DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_sales_id` (`sales_id`),
                KEY `idx_is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS `wa_broadcast_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(120) NOT NULL DEFAULT '',
                `session_label` VARCHAR(150) NOT NULL DEFAULT '',
                `sales_id` INT NULL DEFAULT NULL,
                `customer_id` INT NULL DEFAULT NULL,
                `customer_name` VARCHAR(255) NOT NULL DEFAULT '',
                `phone` VARCHAR(35) NOT NULL DEFAULT '',
                `message_hash` VARCHAR(64) NOT NULL DEFAULT '',
                `message_preview` VARCHAR(255) NOT NULL DEFAULT '',
                `provider` ENUM('openwa','fonnte','direct') NOT NULL DEFAULT 'openwa',
                `status` ENUM('sent','skipped_not_wa','skipped_recent','skipped_limit','failed') NOT NULL DEFAULT 'sent',
                `error_detail` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_session_day` (`session_id`, `status`, `created_at`),
                KEY `idx_phone_dedup` (`phone`, `status`, `created_at`),
                KEY `idx_customer_dedup` (`customer_id`, `status`, `created_at`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('openwaModeDefaultLimit')) {
    function openwaModeDefaultLimit($mode) {
        switch (strtolower(trim((string)$mode))) {
            case 'normal': return 45;
            case 'boost':  return 75;
            default:       return 20; // warmup
        }
    }
}

if (!function_exists('normalizeIndonesianPhone')) {
    function normalizeIndonesianPhone($rawPhone) {
        $clean = preg_replace('/[^0-9]/', '', (string)$rawPhone);
        if ($clean === '') return '';
        if (substr($clean, 0, 1) === '0') {
            $clean = '62' . substr($clean, 1);
        } elseif (substr($clean, 0, 2) !== '62') {
            $clean = '62' . $clean;
        }
        return $clean;
    }
}

if (!function_exists('getOpenWAConfig')) {
    function getOpenWAConfig($conn) {
        $cfg = [
            'base_url'        => 'http://127.0.0.1:2785',
            'api_key'         => '',
            'dedup_days'      => 7,
            'work_start_hour' => 8,
            'work_end_hour'   => 19,
            'work_hours_only' => 1,
        ];

        $configFile = __DIR__ . '/wa_gateway_config.json';
        if (file_exists($configFile)) {
            $json = json_decode(@file_get_contents($configFile), true);
            if (is_array($json)) {
                if (!empty($json['openwa_base_url'])) $cfg['base_url'] = trim($json['openwa_base_url']);
                if (!empty($json['openwa_api_key']))  $cfg['api_key']  = trim($json['openwa_api_key']);
                if (isset($json['openwa_dedup_days'])) $cfg['dedup_days'] = max(0, min(90, (int)$json['openwa_dedup_days']));
                if (isset($json['openwa_work_hours_only'])) $cfg['work_hours_only'] = (int)$json['openwa_work_hours_only'];
            }
        }

        if ($conn) {
            $keys = "'openwa_base_url','openwa_api_key','openwa_dedup_days','openwa_work_hours_only'";
            $res = @$conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$keys})");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $k = $row['setting_key'];
                    $v = trim((string)($row['setting_value'] ?? ''));
                    if ($k === 'openwa_base_url' && $v !== '') $cfg['base_url'] = $v;
                    if ($k === 'openwa_api_key' && $v !== '' && $cfg['api_key'] === '') $cfg['api_key'] = $v;
                    if ($k === 'openwa_dedup_days' && $v !== '') $cfg['dedup_days'] = max(0, min(90, (int)$v));
                    if ($k === 'openwa_work_hours_only' && $v !== '') $cfg['work_hours_only'] = (int)$v;
                }
            }
        }

        // Bersihkan trailing slash & /api
        $cfg['base_url'] = rtrim($cfg['base_url'], '/');
        if (substr($cfg['base_url'], -4) === '/api') {
            $cfg['base_url'] = substr($cfg['base_url'], 0, -4);
        }

        return $cfg;
    }
}

if (!function_exists('saveOpenWAConfig')) {
    function saveOpenWAConfig($conn, array $input) {
        $baseUrl = rtrim(trim((string)($input['base_url'] ?? 'http://127.0.0.1:2785')), '/');
        if (substr($baseUrl, -4) === '/api') {
            $baseUrl = substr($baseUrl, 0, -4);
        }
        if ($baseUrl === '') $baseUrl = 'http://127.0.0.1:2785';

        $apiKey        = trim((string)($input['api_key'] ?? ''));
        $dedupDays     = max(0, min(90, (int)($input['dedup_days'] ?? 7)));
        $workHoursOnly = !empty($input['work_hours_only']) ? 1 : 0;

        // 1. Simpan ke JSON lokal (tidak ikut ter-push ke Git)
        $configFile = __DIR__ . '/wa_gateway_config.json';
        $existing = file_exists($configFile) ? json_decode(@file_get_contents($configFile), true) : [];
        if (!is_array($existing)) $existing = [];
        $existing['openwa_base_url']        = $baseUrl;
        $existing['openwa_api_key']         = $apiKey;
        $existing['openwa_dedup_days']      = $dedupDays;
        $existing['openwa_work_hours_only'] = $workHoursOnly;
        $existing['openwa_updated_at']      = date('Y-m-d H:i:s');
        @file_put_contents($configFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Simpan juga ke tabel settings DB
        if ($conn) {
            @$conn->query("ALTER TABLE settings MODIFY COLUMN setting_value TEXT NULL");
            $pairs = [
                'openwa_base_url'        => $baseUrl,
                'openwa_api_key'         => $apiKey,
                'openwa_dedup_days'      => (string)$dedupDays,
                'openwa_work_hours_only' => (string)$workHoursOnly,
            ];
            foreach ($pairs as $k => $v) {
                $stmtDel = $conn->prepare("DELETE FROM settings WHERE setting_key = ?");
                if ($stmtDel) {
                    $stmtDel->bind_param("s", $k);
                    $stmtDel->execute();
                    $stmtDel->close();
                }
                $stmtIns = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                if ($stmtIns) {
                    $stmtIns->bind_param("ss", $k, $v);
                    $stmtIns->execute();
                    $stmtIns->close();
                }
            }
        }
    }
}

if (!function_exists('openwaRequest')) {
    function openwaRequest($config, $method, $path, $body = null, $timeout = 25) {
        $baseUrl = rtrim($config['base_url'] ?? 'http://127.0.0.1:2785', '/');
        $apiKey  = trim((string)($config['api_key'] ?? ''));
        $url     = $baseUrl . '/api/' . ltrim($path, '/');

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if ($apiKey !== '') {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);

        $raw      = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return [
                'ok'        => false,
                'http_code' => 0,
                'data'      => null,
                'error'     => 'Tidak bisa terhubung ke server OpenWA (' . $baseUrl . '): ' . $curlErr,
            ];
        }

        $decoded = json_decode((string)$raw, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'ok'        => true,
                'http_code' => $httpCode,
                'data'      => $decoded !== null ? $decoded : $raw,
                'error'     => '',
            ];
        }

        $errMsg = 'HTTP ' . $httpCode;
        if (is_array($decoded)) {
            $m = $decoded['message'] ?? ($decoded['error'] ?? '');
            if (is_array($m)) $m = implode('; ', $m);
            if ($m !== '') $errMsg = $m . ' (HTTP ' . $httpCode . ')';
        } elseif (is_string($raw) && trim($raw) !== '') {
            $errMsg = substr(trim(strip_tags($raw)), 0, 180) . ' (HTTP ' . $httpCode . ')';
        }

        return [
            'ok'        => false,
            'http_code' => $httpCode,
            'data'      => $decoded,
            'error'     => $errMsg,
        ];
    }
}

/**
 * Ekstrak daftar sesi secara defensif dari respons OpenWA (array langsung atau {data: [...]})
 */
if (!function_exists('openwaFetchLiveSessions')) {
    function openwaFetchLiveSessions($config) {
        $res = openwaRequest($config, 'GET', 'sessions', null, 12);
        if (!$res['ok']) {
            return ['ok' => false, 'sessions' => [], 'error' => $res['error']];
        }
        $data = $res['data'];
        $list = [];
        if (is_array($data)) {
            if (isset($data['data']) && is_array($data['data'])) {
                $list = $data['data'];
            } elseif (isset($data['sessions']) && is_array($data['sessions'])) {
                $list = $data['sessions'];
            } elseif (array_is_list($data) || empty($data)) {
                $list = $data;
            }
        }
        return ['ok' => true, 'sessions' => $list, 'error' => ''];
    }
}

/**
 * Hitung pemakaian pengiriman sukses hari ini per session_id
 */
if (!function_exists('openwaGetTodayUsageMap')) {
    function openwaGetTodayUsageMap($conn) {
        $map = [];
        if (!$conn) return $map;
        $res = @$conn->query("
            SELECT session_id, COUNT(*) AS cnt
            FROM wa_broadcast_logs
            WHERE provider = 'openwa' AND status = 'sent' AND DATE(created_at) = CURDATE()
            GROUP BY session_id
        ");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $map[$r['session_id']] = (int)$r['cnt'];
            }
        }
        return $map;
    }
}

/**
 * Sinkronkan sesi live OpenWA dengan tabel wa_openwa_sessions + hitung kuota hari ini
 */
if (!function_exists('openwaSyncAndListSessions')) {
    function openwaSyncAndListSessions($conn, $config) {
        ensureOpenWATables($conn);
        $live = openwaFetchLiveSessions($config);
        $usageMap = openwaGetTodayUsageMap($conn);

        $liveById = [];
        if ($live['ok']) {
            foreach ($live['sessions'] as $s) {
                $sid = $s['id'] ?? ($s['sessionId'] ?? '');
                if ($sid === '') continue;
                $liveById[$sid] = $s;

                $name        = trim((string)($s['name'] ?? $sid));
                $status      = strtolower(trim((string)($s['status'] ?? 'unknown')));
                $phone       = normalizeIndonesianPhone($s['phone'] ?? ($s['me']['id'] ?? ''));
                $restriction = null;
                if (!empty($s['restriction'])) {
                    $restriction = is_string($s['restriction']) ? $s['restriction'] : json_encode($s['restriction']);
                }

                // Upsert ke DB lokal
                $chk = $conn->prepare("SELECT id, phone_number, label FROM wa_openwa_sessions WHERE session_id = ? LIMIT 1");
                if ($chk) {
                    $chk->bind_param("s", $sid);
                    $chk->execute();
                    $ex = $chk->get_result()->fetch_assoc();
                    $chk->close();

                    if ($ex) {
                        $finalPhone = ($phone !== '') ? $phone : $ex['phone_number'];
                        $upd = $conn->prepare("UPDATE wa_openwa_sessions SET session_name = ?, phone_number = ?, last_status = ?, last_restriction = ? WHERE session_id = ?");
                        if ($upd) {
                            $upd->bind_param("sssss", $name, $finalPhone, $status, $restriction, $sid);
                            $upd->execute();
                            $upd->close();
                        }
                    } else {
                        $label = $name;
                        $ins = $conn->prepare("INSERT INTO wa_openwa_sessions (session_id, session_name, label, phone_number, mode, daily_limit, is_active, last_status, last_restriction) VALUES (?, ?, ?, ?, 'warmup', 20, 1, ?, ?)");
                        if ($ins) {
                            $ins->bind_param("ssssss", $sid, $name, $label, $phone, $status, $restriction);
                            $ins->execute();
                            $ins->close();
                        }
                    }
                }
            }
        }

        // Ambil semua data dari DB beserta nama sales
        $rows = [];
        $sql = "
            SELECT ws.*, s.nama_lengkap AS sales_name
            FROM wa_openwa_sessions ws
            LEFT JOIN sales s ON ws.sales_id = s.id
            ORDER BY ws.id ASC
        ";
        $res = @$conn->query($sql);
        $nowTs = time();

        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $sid = $r['session_id'];
                // Jika OpenWA online dan sesi ini sudah tidak ada di OpenWA, tandai 'not_found'
                $liveStatus = $r['last_status'];
                $engine = 'baileys';
                $pushName = '';
                $existsInOpenwa = isset($liveById[$sid]);
                if ($existsInOpenwa) {
                    $ls = $liveById[$sid];
                    $liveStatus = strtolower(trim((string)($ls['status'] ?? $liveStatus)));
                    $engine     = $ls['engine'] ?? 'baileys';
                    $pushName   = $ls['pushName'] ?? '';
                } elseif ($live['ok']) {
                    $liveStatus = 'missing';
                }

                $sentToday = $usageMap[$sid] ?? 0;
                $dailyLimit = max(1, (int)$r['daily_limit']);
                $remaining = max(0, $dailyLimit - $sentToday);

                $isPaused = false;
                $pausedRemainingSec = 0;
                if (!empty($r['paused_until'])) {
                    $pTs = strtotime($r['paused_until']);
                    if ($pTs && $pTs > $nowTs) {
                        $isPaused = true;
                        $pausedRemainingSec = $pTs - $nowTs;
                    }
                }

                $hasRestriction = !empty($r['last_restriction']);
                $canSend = (
                    $existsInOpenwa &&
                    $liveStatus === 'ready' &&
                    !$hasRestriction &&
                    (int)$r['is_active'] === 1 &&
                    !$isPaused &&
                    $remaining > 0
                );

                $rows[] = [
                    'id'                   => (int)$r['id'],
                    'session_id'           => $sid,
                    'session_name'         => $r['session_name'],
                    'label'                => $r['label'] ?: $r['session_name'],
                    'phone_number'         => $r['phone_number'],
                    'sales_id'             => $r['sales_id'] ? (int)$r['sales_id'] : null,
                    'sales_name'           => $r['sales_name'] ?: 'Semua Sales (Pool Rotasi)',
                    'mode'                 => $r['mode'],
                    'daily_limit'          => $dailyLimit,
                    'sent_today'           => $sentToday,
                    'remaining_today'      => $remaining,
                    'is_active'            => (int)$r['is_active'],
                    'status'               => $liveStatus,
                    'engine'               => $engine,
                    'push_name'            => $pushName,
                    'has_restriction'      => $hasRestriction,
                    'last_restriction'     => $r['last_restriction'],
                    'last_error'           => $r['last_error'],
                    'consecutive_fails'    => (int)$r['consecutive_fails'],
                    'is_paused'            => $isPaused,
                    'paused_until'         => $r['paused_until'],
                    'paused_remaining_sec' => $pausedRemainingSec,
                    'can_send'             => $canSend,
                    'exists_in_openwa'     => $existsInOpenwa,
                ];
            }
        }

        return [
            'ok'             => $live['ok'],
            'openwa_online'  => $live['ok'],
            'error'          => $live['error'],
            'sessions'       => $rows,
        ];
    }
}

/**
 * Layer 4: Spintax + Variabel + Anti-Duplicate Fingerprint Engine
 * Contoh: "{Halo|Selamat {salam_waktu}|Permisi} {nama_pic} dari *{nama_toko}*"
 */
if (!function_exists('renderAntiBanMessage')) {
    function renderAntiBanMessage($template, array $vars, $enableSpintax = true) {
        $hour = (int)date('G');
        if ($hour >= 4 && $hour < 11)      $salamWaktu = 'pagi';
        elseif ($hour >= 11 && $hour < 15) $salamWaktu = 'siang';
        elseif ($hour >= 15 && $hour < 18) $salamWaktu = 'sore';
        else                               $salamWaktu = 'malam';

        $text = (string)$template;
        $replacements = [
            '{nama_toko}'   => trim((string)($vars['nama_toko'] ?? 'Toko')) ?: 'Toko',
            '{nama_pic}'    => trim((string)($vars['nama_pic'] ?? 'Bpk/Ibu')) ?: 'Bpk/Ibu',
            '{kota}'        => trim((string)($vars['kota'] ?? 'Kota Anda')) ?: 'Kota Anda',
            '{nama_sales}'  => trim((string)($vars['nama_sales'] ?? 'Sales Loewix')) ?: 'Sales Loewix',
            '{salam_waktu}' => $salamWaktu,
        ];
        $text = strtr($text, $replacements);

        // Proses Spintax {opsi1|opsi2|opsi3} — mendukung nested sampai 5 level
        if ($enableSpintax) {
            for ($depth = 0; $depth < 5; $depth++) {
                $newText = preg_replace_callback('/\{([^{}|]+\|[^{}]+)\}/u', function ($m) {
                    $options = explode('|', $m[1]);
                    return $options[array_rand($options)];
                }, $text);
                if ($newText === null || $newText === $text) break;
                $text = $newText;
            }
        }

        // Anti-Duplicate Hash Micro-Salt (variasi spasi akhir / invisible zero-width space ringan
        // agar hash setiap pesan unik tanpa mengganggu tampilan baca penerima)
        $zwChars = ["\u{200B}", "\u{200C}", "\u{200D}"];
        $salt = '';
        $saltLen = random_int(2, 4);
        for ($i = 0; $i < $saltLen; $i++) {
            $salt .= $zwChars[array_rand($zwChars)];
        }

        return trim($text) . $salt;
    }
}

/**
 * Layer 2: Pre-Flight Check Nomor Terdaftar di WhatsApp
 */
if (!function_exists('openwaCheckNumberRegistered')) {
    function openwaCheckNumberRegistered($config, $sessionId, $cleanPhone) {
        $res = openwaRequest($config, 'GET', 'sessions/' . rawurlencode($sessionId) . '/contacts/check/' . rawurlencode($cleanPhone), null, 15);
        if (!$res['ok']) {
            // Jika endpoint check tidak didukung atau timeout sementara, bedakan antara error 400/404 vs network
            if ($res['http_code'] === 400) {
                return ['checked' => true, 'exists' => false, 'chatId' => $cleanPhone . '@c.us', 'error' => $res['error']];
            }
            return ['checked' => false, 'exists' => true, 'chatId' => $cleanPhone . '@c.us', 'error' => $res['error']];
        }

        $d = is_array($res['data']) ? $res['data'] : [];
        if (isset($d['data']) && is_array($d['data'])) $d = $d['data'];

        $exists = true;
        if (array_key_exists('exists', $d))            $exists = (bool)$d['exists'];
        elseif (array_key_exists('isRegistered', $d))  $exists = (bool)$d['isRegistered'];
        elseif (array_key_exists('registered', $d))    $exists = (bool)$d['registered'];
        elseif (array_key_exists('onWhatsApp', $d))    $exists = (bool)$d['onWhatsApp'];

        $chatId = $d['whatsappId'] ?? ($d['jid'] ?? ($d['chatId'] ?? ($cleanPhone . '@c.us')));
        if (!str_contains((string)$chatId, '@')) {
            $chatId = $cleanPhone . '@c.us';
        }

        return [
            'checked' => true,
            'exists'  => $exists,
            'chatId'  => $chatId,
            'error'   => '',
        ];
    }
}

/**
 * Layer 3: Simulasi Mengetik Manusiawi
 */
if (!function_exists('openwaSimulateHumanTyping')) {
    function openwaSimulateHumanTyping($config, $sessionId, $chatId, $messageLength) {
        // Durasi mengetik 2-4 detik proporsional panjang pesan (tidak terlalu lama agar PHP tidak timeout)
        $typingSec = min(4, max(2, (int)round($messageLength / 110)));
        @openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/chats/typing', [
            'chatId' => $chatId,
            'state'  => 'typing',
        ], 6);

        sleep($typingSec);

        @openwaRequest($config, 'POST', 'sessions/' . rawurlencode($sessionId) . '/chats/typing', [
            'chatId' => $chatId,
            'state'  => 'paused',
        ], 5);

        return $typingSec;
    }
}

/**
 * Layer 6: Circuit Breaker Helper
 */
if (!function_exists('openwaRecordSuccess')) {
    function openwaRecordSuccess($conn, $sessionId) {
        if (!$conn) return;
        $stmt = $conn->prepare("UPDATE wa_openwa_sessions SET consecutive_fails = 0, last_error = NULL, paused_until = NULL WHERE session_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $sessionId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('openwaRecordFailure')) {
    function openwaRecordFailure($conn, $sessionId, $errorMsg, $forceTrip = false) {
        if (!$conn) return ['tripped' => false, 'fails' => 1];

        $fails = 1;
        $chk = $conn->prepare("SELECT consecutive_fails FROM wa_openwa_sessions WHERE session_id = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param("s", $sessionId);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ($row) $fails = ((int)$row['consecutive_fails']) + 1;
        }

        // Trip Circuit Breaker jika gagal 3x berturut-turut ATAU error kritis (disconnect/logout/restricted)
        $lowerErr = strtolower((string)$errorMsg);
        $isCritical = $forceTrip
            || str_contains($lowerErr, 'disconnect')
            || str_contains($lowerErr, 'not ready')
            || str_contains($lowerErr, 'logged out')
            || str_contains($lowerErr, 'restricted')
            || str_contains($lowerErr, 'rate')
            || str_contains($lowerErr, 'ban');

        $tripped = ($fails >= 3 || $isCritical);
        if ($tripped) {
            $pausedUntil = date('Y-m-d H:i:s', time() + (30 * 60)); // Cooldown 30 menit
            $stmt = $conn->prepare("UPDATE wa_openwa_sessions SET consecutive_fails = ?, last_error = ?, paused_until = ? WHERE session_id = ?");
            if ($stmt) {
                $stmt->bind_param("isss", $fails, $errorMsg, $pausedUntil, $sessionId);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE wa_openwa_sessions SET consecutive_fails = ?, last_error = ? WHERE session_id = ?");
            if ($stmt) {
                $stmt->bind_param("iss", $fails, $errorMsg, $sessionId);
                $stmt->execute();
                $stmt->close();
            }
        }

        return ['tripped' => $tripped, 'fails' => $fails];
    }
}

/**
 * Layer 7: Cek Deduplikasi Anti-Spam Ganda (sudah di-BC dalam N hari terakhir?)
 */
if (!function_exists('openwaWasRecentlySent')) {
    function openwaWasRecentlySent($conn, $cleanPhone, $customerId, $dedupDays) {
        if (!$conn || $dedupDays <= 0) return null;
        $stmt = $conn->prepare("
            SELECT created_at, session_label
            FROM wa_broadcast_logs
            WHERE status = 'sent'
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND (phone = ? OR (? > 0 AND customer_id = ?))
            ORDER BY created_at DESC
            LIMIT 1
        ");
        if (!$stmt) return null;
        $stmt->bind_param("isii", $dedupDays, $cleanPhone, $customerId, $customerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

/**
 * Catat log ke wa_broadcast_logs
 */
if (!function_exists('openwaWriteBroadcastLog')) {
    function openwaWriteBroadcastLog($conn, array $log) {
        if (!$conn) return;
        ensureOpenWATables($conn);
        $sessionId    = (string)($log['session_id'] ?? '');
        $sessionLabel = (string)($log['session_label'] ?? '');
        $salesId      = isset($log['sales_id']) ? (int)$log['sales_id'] : null;
        $customerId   = isset($log['customer_id']) ? (int)$log['customer_id'] : null;
        $customerName = (string)($log['customer_name'] ?? '');
        $phone        = (string)($log['phone'] ?? '');
        $msgHash      = (string)($log['message_hash'] ?? '');
        $msgPreview   = mb_substr((string)($log['message_preview'] ?? ''), 0, 240);
        $provider     = (string)($log['provider'] ?? 'openwa');
        $status       = (string)($log['status'] ?? 'sent');
        $errorDetail  = isset($log['error_detail']) ? (string)$log['error_detail'] : null;

        $stmt = $conn->prepare("
            INSERT INTO wa_broadcast_logs
                (session_id, session_label, sales_id, customer_id, customer_name, phone, message_hash, message_preview, provider, status, error_detail)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param(
                "ssiisssssss",
                $sessionId, $sessionLabel, $salesId, $customerId, $customerName,
                $phone, $msgHash, $msgPreview, $provider, $status, $errorDetail
            );
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Layer 1 & 5: Pilih Sesi Terbaik (Auto-Rotate Least-Used / My Sales / Specific)
 */
if (!function_exists('openwaSelectHealthySession')) {
    function openwaSelectHealthySession($conn, $config, $selectionMode = 'auto_rotate', $activeSalesId = 0) {
        $sync = openwaSyncAndListSessions($conn, $config);
        if (!$sync['openwa_online']) {
            return [
                'ok'      => false,
                'reason'  => 'openwa_offline',
                'message' => $sync['error'] ?: 'Server OpenWA sedang offline. Pastikan container Docker openwa-api berjalan.',
            ];
        }

        $all = $sync['sessions'];
        if (empty($all)) {
            return [
                'ok'      => false,
                'reason'  => 'no_sessions',
                'message' => 'Belum ada nomor WhatsApp terdaftar di OpenWA. Buka menu Pesan Masuk WA untuk menambahkan & scan QR nomor sales.',
            ];
        }

        $candidates = [];
        if ($selectionMode === 'my_sales' && $activeSalesId > 0) {
            foreach ($all as $s) {
                if ((int)($s['sales_id'] ?? 0) === (int)$activeSalesId && $s['can_send']) {
                    $candidates[] = $s;
                }
            }
            if (empty($candidates)) {
                return [
                    'ok'      => false,
                    'reason'  => 'sales_session_unavailable',
                    'message' => 'Nomor WA sales Anda belum terhubung/ready di OpenWA atau kuota aman hari ini sudah habis.',
                ];
            }
        } elseif ($selectionMode !== 'auto_rotate' && $selectionMode !== '') {
            // Specific session_id dipilih user
            foreach ($all as $s) {
                if ($s['session_id'] === $selectionMode) {
                    if (!$s['can_send']) {
                        $why = 'status: ' . $s['status'];
                        if ($s['is_paused']) $why = 'sedang cooldown Circuit Breaker (' . ceil($s['paused_remaining_sec'] / 60) . ' menit lagi)';
                        elseif ($s['remaining_today'] <= 0) $why = 'kuota aman harian (' . $s['daily_limit'] . '/hari) sudah tercapai';
                        elseif ($s['has_restriction']) $why = 'terdeteksi pembatasan WhatsApp';
                        return [
                            'ok'      => false,
                            'reason'  => 'session_unavailable',
                            'message' => "Sesi {$s['label']} tidak bisa mengirim ({$why}).",
                        ];
                    }
                    $candidates[] = $s;
                    break;
                }
            }
        } else {
            // auto_rotate: Ambil semua sesi sehat & ready
            foreach ($all as $s) {
                if ($s['can_send']) {
                    $candidates[] = $s;
                }
            }
        }

        if (empty($candidates)) {
            // Cek apakah semua habis kuota atau belum scan QR
            $readyCount = 0;
            $pausedCount = 0;
            $quotaFullCount = 0;
            foreach ($all as $s) {
                if ($s['status'] === 'ready') $readyCount++;
                if ($s['is_paused']) $pausedCount++;
                if ($s['status'] === 'ready' && $s['remaining_today'] <= 0) $quotaFullCount++;
            }
            if ($quotaFullCount > 0 && $quotaFullCount === $readyCount) {
                return [
                    'ok'      => false,
                    'reason'  => 'all_quota_reached',
                    'message' => 'Semua nomor WA aktif sudah mencapai batas kuota aman hari ini! Lanjutkan besok agar akun tetap 100% aman dari blokir.',
                ];
            }
            if ($pausedCount > 0) {
                return [
                    'ok'      => false,
                    'reason'  => 'circuit_breaker_all',
                    'message' => 'Semua nomor WA sedang dalam masa istirahat Circuit Breaker karena terdeteksi gangguan koneksi.',
                ];
            }
            return [
                'ok'      => false,
                'reason'  => 'no_ready_session',
                'message' => 'Belum ada sesi OpenWA berstatus READY. Silakan scan QR di menu Pesan Masuk WA terlebih dahulu.',
            ];
        }

        // Load balancing: pilih sesi dengan sent_today paling sedikit (jika sama, acak agar natural)
        usort($candidates, function ($a, $b) {
            if ($a['sent_today'] === $b['sent_today']) {
                return random_int(-1, 1);
            }
            return $a['sent_today'] <=> $b['sent_today'];
        });

        return [
            'ok'               => true,
            'session'          => $candidates[0],
            'healthy_count'    => count($candidates),
            'total_remaining'  => array_sum(array_column($candidates, 'remaining_today')),
        ];
    }
}
