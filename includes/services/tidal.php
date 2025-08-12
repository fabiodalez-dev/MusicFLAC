<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../spotify.php';
require_once __DIR__ . '/../app.php';

function tidal_get_access_token()
{
    debug_log('tidal_get_access_token start');
    $client_id = 'zU4XHVVkc2tDPo4t';
    $client_secret = 'VJKhDFqJPqvsPVNBV6ukXTJmwlvbttP7wlMlrc72se4=';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://auth.tidal.com/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => $client_id,
            'grant_type' => 'client_credentials'
        ]),
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $client_id . ':' . $client_secret,
        CURLOPT_TIMEOUT => 15
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    debug_log('tidal_get_access_token result', ['hasToken' => isset($data['access_token'])]);
    return $data['access_token'] ?? null;
}

function tidal_search_track($query, $isrc = null)
{
    $token = tidal_get_access_token();
    if (!$token) throw new Exception('Tidal token non disponibile');
    debug_log('tidal_search_track', ['query' => $query, 'isrc' => $isrc]);
    $base = app_get_service_endpoint('tidal', defined('TIDAL_API_URL') ? TIDAL_API_URL : 'https://api.tidal.com/v1');
    $url = rtrim($base, '/') . '/search/tracks?limit=25&offset=0&countryCode=US&query=' . urlencode($query);
    $data = http_get_json($url, ['authorization' => 'Bearer ' . $token], 15);
    if (!$data || isset($data['error'])) throw new Exception('Ricerca Tidal fallita');
    $items = $data['items'] ?? [];
    if ($isrc) {
        $filtered = array_values(array_filter($items, function ($i) use ($isrc) { return ($i['isrc'] ?? '') === $isrc; }));
        if (!empty($filtered)) return $filtered[0];
    }
    $found = $items[0] ?? null;
    debug_log('tidal_search_track_result', ['found' => (bool)$found]);
    return $found;
}

function tidal_download_track($track, $target_path)
{
    $track_id = $track['id'] ?? null;
    if (!$track_id) throw new Exception('ID traccia Tidal mancante');
    $api = 'https://hifi.401658.xyz/track/?id=' . urlencode($track_id) . '&quality=LOSSLESS';
    debug_log('tidal_download_track', ['track_id' => $track_id, 'target' => $target_path]);
    $data = http_get_json($api, [], 30);
    if (!$data || isset($data['error'])) throw new Exception('URL download Tidal non trovato');
    $download_url = null;
    foreach ($data as $it) {
        if (isset($it['OriginalTrackUrl'])) { $download_url = $it['OriginalTrackUrl']; break; }
    }
    if (!$download_url) throw new Exception('Nessun OriginalTrackUrl');

    $tmp = $target_path . '.part';
    $fp = fopen($tmp, 'wb');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $download_url,
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 900
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if (!$ok || $code >= 400) {
        @unlink($tmp);
        throw new Exception('Fallito scaricamento Tidal');
    }
    rename($tmp, $target_path);
    debug_log('tidal_download_track_done', ['path' => $target_path]);
    return $target_path;
}

function tidal_get_original_download_url($track): ?string
{
    $track_id = $track['id'] ?? null;
    if (!$track_id) return null;
    $api = 'https://hifi.401658.xyz/track/?id=' . urlencode($track_id) . '&quality=LOSSLESS';
    $data = http_get_json($api, [], 30);
    if (!$data || isset($data['error'])) return null;
    foreach ($data as $it) {
        if (isset($it['OriginalTrackUrl'])) return $it['OriginalTrackUrl'];
    }
    return null;
}

// Intentionally no closing PHP tag to avoid accidental output
