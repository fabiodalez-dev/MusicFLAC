<?php
// Lightweight Spotify client to fetch metadata and ISRCs

// Note: This mirrors getMetadata.py logic without caching.

function is_safe_url(string $url): bool {
    $p = @parse_url($url);
    if (!$p) return false;
    
    $scheme = strtolower($p['scheme'] ?? '');
    if (!in_array($scheme, ['http','https'], true)) return false;
    
    $host = strtolower($p['host'] ?? '');
    if ($host === '' ) return false;
    
    // Enhanced SSRF protection
    // Block localhost variations
    $localhost_patterns = [
        'localhost', '127.0.0.1', '::1', '0.0.0.0',
        '127.', '0x7f.', '017700000001', '2130706433'
    ];
    
    foreach ($localhost_patterns as $pattern) {
        if (strpos($host, $pattern) === 0) return false;
    }
    
    // Block private IP ranges (IPv4)
    if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.|169\.254\.)/', $host)) return false;
    
    // Block link-local and multicast
    if (preg_match('/^(224\.|225\.|226\.|227\.|228\.|229\.|23[0-9]\.|24[0-9]\.|25[0-5]\.)/', $host)) return false;
    
    // Block IPv6 private ranges
    if (preg_match('/^(fe80|fc00|fd|::ffff:0:|::ffff:127\.)/', $host)) return false;
    
    // Resolve hostname to IP and check again
    $ip = @gethostbyname($host);
    if ($ip && $ip !== $host) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    
    // Additional whitelist for known safe domains
    $allowed_domains = [
        'open.spotify.com', 'api.spotify.com', 'accounts.spotify.com',
        'i.scdn.co', 'scontent.xx.fbcdn.net', 'embed.spotify.com',
        'cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'fonts.googleapis.com',
        'fonts.gstatic.com'
    ];
    
    // Check if domain is in whitelist
    foreach ($allowed_domains as $domain) {
        if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -strlen('.' . $domain)) === '.' . $domain)) {
            return true;
        }
    }
    
    // For non-whitelisted domains, be more restrictive
    // Only allow if it's clearly external and not suspicious
    if (strpos($host, '.') === false) return false; // No domain extension
    if (preg_match('/^[0-9.]+$/', $host)) return false; // Direct IP access
    
    return true;
}

function http_get_json($url, $headers = [], $timeout = 15)
{
    if (!is_safe_url($url)) { debug_log('HTTP GET JSON blocked', ['url' => $url]); return ["error" => "Blocked URL"]; }
    debug_log('HTTP GET JSON start', ['url' => $url]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers)
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        debug_log('HTTP GET JSON error', ['url' => $url, 'error' => $err]);
        return ["error" => $err ?: "cURL error"];
    }
    $data = json_decode($resp, true);
    if ($code >= 400) {
        debug_log('HTTP GET JSON http_error', ['code' => $code, 'url' => $url, 'body' => substr($resp,0,200)]);
        return ["error" => "HTTP $code", "body" => $resp];
    }
    debug_log('HTTP GET JSON ok', ['code' => $code, 'url' => $url]);
    return $data ?: ["error" => "Invalid JSON response", "body" => $resp];
}

function http_get($url, $headers = [], $timeout = 15)
{
    if (!is_safe_url($url)) { debug_log('HTTP GET blocked', ['url' => $url]); return null; }
    debug_log('HTTP GET start', ['url' => $url]);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => array_map(function ($k, $v) { return "$k: $v"; }, array_keys($headers), $headers)
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        debug_log('HTTP GET error', ['url' => $url, 'error' => $err]);
        return null;
    }
    if ($code >= 400) {
        debug_log('HTTP GET http_error', ['url' => $url, 'code' => $code]);
        return null;
    }
    debug_log('HTTP GET ok', ['url' => $url, 'code' => $code]);
    return $resp;
}

function parse_spotify_uri($uri)
{
    debug_log('parse_spotify_uri', ['uri' => $uri]);
    $parts = parse_url($uri);
    if (!isset($parts['scheme']) && !isset($parts['host'])) {
        return ["type" => "playlist", "id" => $uri];
    }
    // Handle embed.host
    if (isset($parts['host']) && $parts['host'] === 'embed.spotify.com') {
        parse_str($parts['query'] ?? '', $qs);
        if (!isset($qs['uri'])) {
            throw new Exception("URL Spotify non supportata");
        }
        return parse_spotify_uri($qs['uri']);
    }
    // spotify:album:ID / spotify:track:ID / spotify:playlist:ID
    if (($parts['scheme'] ?? '') === 'spotify') {
        $p = explode(':', $uri);
        if (count($p) >= 3 && in_array($p[1], ['album', 'track', 'playlist'])) {
            return ["type" => $p[1], "id" => $p[2]];
        }
        throw new Exception("Impossibile determinare il tipo di URL Spotify");
    }
    // Standard web URLs: open.spotify.com / play.spotify.com
    if (!in_array($parts['host'] ?? '', ['open.spotify.com', 'play.spotify.com'])) {
        throw new Exception("URL Spotify non supportata");
    }
    // Normalize path segments (ignore leading/trailing slashes)
    $segments = array_values(array_filter(explode('/', $parts['path'] ?? ''), fn($s) => $s !== ''));
    // Find type token position robustly
    foreach (['album', 'track', 'playlist'] as $type) {
        $idx = array_search($type, $segments, true);
        if ($idx !== false && isset($segments[$idx + 1])) {
            debug_log('parse_spotify_uri result', ['type' => $type, 'id' => $segments[$idx+1]]);
            return ["type" => $type, "id" => $segments[$idx + 1]];
        }
    }
    throw new Exception("Impossibile determinare il tipo di URL Spotify");
}

// Base32 decode (RFC 4648)
function base32_decode_custom($b32)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper($b32);
    $buffer = 0;
    $bitsLeft = 0;
    $result = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $ch = $b32[$i];
        if ($ch === '=') break;
        $val = strpos($alphabet, $ch);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $result .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $result;
}

function totp_code($b32_secret, $timestamp, $period = 30, $digits = 6)
{
    $counter = pack('N*', 0) . pack('N*', floor($timestamp / $period));
    $key = base32_decode_custom($b32_secret);
    $hash = hash_hmac('sha1', $counter, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $binary = ((ord($hash[$offset]) & 0x7F) << 24) |
              ((ord($hash[$offset + 1]) & 0xFF) << 16) |
              ((ord($hash[$offset + 2]) & 0xFF) << 8) |
              (ord($hash[$offset + 3]) & 0xFF);
    $otp = $binary % pow(10, $digits);
    return str_pad((string)$otp, $digits, '0', STR_PAD_LEFT);
}

function spotify_generate_totp_secret()
{
    debug_log('spotify_generate_totp_secret start');
    $secrets_url = 'https://raw.githubusercontent.com/Thereallo1026/spotify-secrets/refs/heads/main/secrets/secretBytes.json';
    $resp = http_get_json($secrets_url, [], 10);
    if (!$resp || isset($resp['error'])) {
        debug_log('spotify_generate_totp_secret error', ['error' => $resp['error'] ?? 'unknown']);
        throw new Exception('Impossibile recuperare i segreti TOTP');
    }
    $latest = null;
    foreach ($resp as $entry) {
        if ($latest === null || ($entry['version'] ?? 0) > ($latest['version'] ?? 0)) {
            $latest = $entry;
        }
    }
    if (!$latest) throw new Exception('Nessun segreto disponibile');
    $cipher = $latest['secret'] ?? [];
    $processed = '';
    foreach ($cipher as $i => $byte) {
        $processed .= (string)($byte ^ (($i % 33) + 9));
    }
    $utf8 = $processed; // already string of digits
    $hex = bin2hex($utf8);
    $secret_bytes = hex2bin($hex);
    $b32 = strtoupper(rtrim(base64_encode($secret_bytes), '=')); // Use base64 then treat as base32? We need true base32
    // Build true base32
    $b32_enc = base32_encode_custom($secret_bytes);
    debug_log('spotify_generate_totp_secret ok', ['version' => $latest['version'] ?? null]);
    return [$b32_enc, $latest['version']];
}

function base32_encode_custom($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $result = '';
    $buffer = 0;
    $bitsLeft = 0;
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $buffer = ($buffer << 8) | ord($data[$i]);
        $bitsLeft += 8;
        while ($bitsLeft >= 5) {
            $bitsLeft -= 5;
            $result .= $alphabet[($buffer >> $bitsLeft) & 0x1F];
        }
    }
    if ($bitsLeft > 0) {
        $result .= $alphabet[($buffer << (5 - $bitsLeft)) & 0x1F];
    }
    return $result; // no padding
}

function spotify_get_access_token()
{
    debug_log('spotify_get_access_token start');
    // Get server time
    $server = http_get_json('https://open.spotify.com/api/server-time', [
        'User-Agent' => 'Mozilla/5.0',
        'Accept' => '*/*'
    ], 10);
    if (!$server || isset($server['error']) || !isset($server['serverTime'])) {
        debug_log('spotify_get_access_token server_time_error', ['server' => $server]);
        return ["error" => "Impossibile ottenere l'orario del server Spotify"];
    }
    [$b32, $ver] = spotify_generate_totp_secret();
    $otp = totp_code($b32, (int)$server['serverTime']);
    $ts_ms = (int)(microtime(true) * 1000);
    $qs = http_build_query([
        'reason' => 'init',
        'productType' => 'web-player',
        'totp' => $otp,
        'totpServerTime' => $server['serverTime'],
        'totpVer' => (string)$ver,
        'sTime' => $server['serverTime'],
        'cTime' => $ts_ms,
        'buildVer' => 'web-player_2025-07-02_1720000000000_12345678',
        'buildDate' => '2025-07-02'
    ]);
    $token = http_get_json('https://open.spotify.com/api/token?' . $qs, [
        'User-Agent' => 'Mozilla/5.0',
        'Accept' => 'application/json',
        'Referer' => 'https://open.spotify.com/',
        'Origin' => 'https://open.spotify.com'
    ], 10);
    if (!$token || isset($token['error'])) {
        debug_log('spotify_get_access_token token_error', ['token' => $token]);
        return ["error" => $token['error'] ?? 'Token request failed'];
    }
    debug_log('spotify_get_access_token ok');
    return $token;
}

function spotify_api_get($url, $access_token)
{
    return http_get_json($url, [
        'Authorization' => 'Bearer ' . $access_token,
        'User-Agent' => 'Mozilla/5.0',
        'Accept' => 'application/json'
    ], 15);
}

function spotify_fetch_raw($spotify_url)
{
    debug_log('spotify_fetch_raw start', ['url' => $spotify_url]);
    $info = parse_spotify_uri($spotify_url);
    $token = spotify_get_access_token();
    if (!$token || isset($token['error'])) {
        debug_log('spotify_fetch_raw token_error', ['token' => $token]);
        return ["error" => $token['error'] ?? 'Errore nel recupero token'];
    }
    $access = $token['accessToken'] ?? null;
    if (!$access) return ["error" => 'Token assente'];

    if ($info['type'] === 'track') {
        $data = spotify_api_get("https://api.spotify.com/v1/tracks/{$info['id']}", $access);
        if (!$data || isset($data['error'])) return ["error" => 'Errore API track'];
        debug_log('spotify_fetch_raw track_ok', ['id' => $info['id']]);
        return ['_token' => $access, 'type' => 'track', 'data' => $data];
    } elseif ($info['type'] === 'album') {
        $data = spotify_api_get("https://api.spotify.com/v1/albums/{$info['id']}", $access);
        if (!$data || isset($data['error'])) return ["error" => 'Errore API album'];
        // also fetch tracks list fully
        $tracks = [];
        $next = "https://api.spotify.com/v1/albums/{$info['id']}/tracks?limit=50";
        while ($next) {
            $page = spotify_api_get($next, $access);
            if (!$page || isset($page['error'])) break;
            foreach ($page['items'] as $it) $tracks[] = $it;
            $next = $page['next'] ?? null;
        }
        $data['tracks']['items'] = $tracks ?: ($data['tracks']['items'] ?? []);
        $data['_token'] = $access;
        debug_log('spotify_fetch_raw album_ok', ['id' => $info['id'], 'tracks' => count($data['tracks']['items'] ?? [])]);
        return ['_token' => $access, 'type' => 'album', 'data' => $data];
    } elseif ($info['type'] === 'playlist') {
        $data = spotify_api_get("https://api.spotify.com/v1/playlists/{$info['id']}", $access);
        if (!$data || isset($data['error'])) return ["error" => 'Errore API playlist'];
        // fetch all playlist tracks
        $tracks = [];
        $next = "https://api.spotify.com/v1/playlists/{$info['id']}/tracks?limit=100";
        while ($next) {
            $page = spotify_api_get($next, $access);
            if (!$page || isset($page['error'])) break;
            foreach ($page['items'] as $it) $tracks[] = $it;
            $next = $page['next'] ?? null;
        }
        $data['tracks']['items'] = $tracks ?: ($data['tracks']['items'] ?? []);
        $data['_token'] = $access;
        debug_log('spotify_fetch_raw playlist_ok', ['id' => $info['id'], 'tracks' => count($data['tracks']['items'] ?? [])]);
        return ['_token' => $access, 'type' => 'playlist', 'data' => $data];
    }
    return ["error" => 'Tipo URL non supportato'];
}

function spotify_format_track($track)
{
    debug_log('spotify_format_track', ['id' => $track['id'] ?? null]);
    $artists = array_map(function ($a) { return $a['name'] ?? ''; }, $track['artists'] ?? []);
    $img = '';
    if (!empty($track['album']['images'][0]['url'])) $img = $track['album']['images'][0]['url'];
    return [
        'track' => [
            'artists' => implode(', ', array_filter($artists)),
            'name' => $track['name'] ?? '',
            'album' => $track['album']['name'] ?? '',
            'duration_ms' => $track['duration_ms'] ?? 0,
            'images' => $img,
            'release_date' => $track['album']['release_date'] ?? '',
            'track_number' => $track['track_number'] ?? 0,
            'external_urls' => $track['external_urls']['spotify'] ?? '',
            'isrc' => $track['external_ids']['isrc'] ?? ''
        ]
    ];
}

function spotify_format_album($album, $access_token)
{
    debug_log('spotify_format_album', ['id' => $album['id'] ?? null]);
    $artists = array_map(function ($a) { return $a['name'] ?? ''; }, $album['artists'] ?? []);
    $img = '';
    if (!empty($album['images'][0]['url'])) $img = $album['images'][0]['url'];
    $tracks = [];
    foreach (($album['tracks']['items'] ?? []) as $t) {
        debug_log('spotify_format_album_track', ['tid' => $t['id'] ?? null]);
        $artists_t = array_map(function ($a) { return $a['name'] ?? ''; }, $t['artists'] ?? []);
        $isrc = '';
        $tid = $t['id'] ?? '';
        if ($tid) {
            $tr = spotify_api_get("https://api.spotify.com/v1/tracks/{$tid}", $access_token);
            if ($tr && empty($tr['error'])) $isrc = $tr['external_ids']['isrc'] ?? '';
        }
        $tracks[] = [
            'artists' => implode(', ', array_filter($artists_t)),
            'name' => $t['name'] ?? '',
            'album' => $album['name'] ?? '',
            'duration_ms' => $t['duration_ms'] ?? 0,
            'images' => $img,
            'release_date' => $album['release_date'] ?? '',
            'track_number' => $t['track_number'] ?? 0,
            'external_urls' => $t['external_urls']['spotify'] ?? '',
            'isrc' => $isrc
        ];
    }
    return [
        'album_info' => [
            'total_tracks' => $album['total_tracks'] ?? count($tracks),
            'name' => $album['name'] ?? '',
            'release_date' => $album['release_date'] ?? '',
            'artists' => implode(', ', array_filter($artists)),
            'images' => $img
        ],
        'track_list' => $tracks
    ];
}

function spotify_format_playlist($playlist)
{
    debug_log('spotify_format_playlist', ['id' => $playlist['id'] ?? null]);
    $img = '';
    if (!empty($playlist['images'][0]['url'])) $img = $playlist['images'][0]['url'];
    $tracks = [];
    foreach (($playlist['tracks']['items'] ?? []) as $item) {
        $track = $item['track'] ?? null;
        if (!$track) continue;
        $artists = array_map(function ($a) { return $a['name'] ?? ''; }, $track['artists'] ?? []);
        $img_t = '';
        if (!empty($track['album']['images'][0]['url'])) $img_t = $track['album']['images'][0]['url'];
        $tracks[] = [
            'artists' => implode(', ', array_filter($artists)),
            'name' => $track['name'] ?? '',
            'album' => $track['album']['name'] ?? '',
            'duration_ms' => $track['duration_ms'] ?? 0,
            'images' => $img_t,
            'release_date' => $track['album']['release_date'] ?? '',
            'track_number' => $track['track_number'] ?? 0,
            'external_urls' => $track['external_urls']['spotify'] ?? '',
            'isrc' => $track['external_ids']['isrc'] ?? ''
        ];
    }
    return [
        'playlist_info' => [
            'name' => $playlist['name'] ?? 'Playlist',
            'owner' => ['display_name' => $playlist['owner']['display_name'] ?? ''],
            'tracks' => ['total' => $playlist['tracks']['total'] ?? count($tracks)],
            'images' => $img
        ],
        'track_list' => $tracks
    ];
}

function spotify_fetch_and_format($url)
{
    $raw = spotify_fetch_raw($url);
    if (isset($raw['error'])) return $raw;
    $token = $raw['_token'] ?? null;
    if (($raw['type'] ?? '') === 'track') {
        return spotify_format_track($raw['data']);
    } elseif (($raw['type'] ?? '') === 'album') {
        return spotify_format_album($raw['data'], $token);
    } elseif (($raw['type'] ?? '') === 'playlist') {
        return spotify_format_playlist($raw['data']);
    }
    return ['error' => 'Tipo non supportato'];
}

// Intentionally no closing PHP tag to avoid accidental output
