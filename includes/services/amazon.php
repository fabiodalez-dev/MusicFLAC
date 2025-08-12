<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../spotify.php';
require_once __DIR__ . '/../app.php';

function amazon_lucida_download($spotify_track_id, $target_path)
{
    debug_log('amazon_lucida_download', ['spotify_track_id' => $spotify_track_id, 'target' => $target_path]);
    $base = app_get_service_endpoint('amazon', 'https://lucida.to');
    $client = curl_init();
    $params = http_build_query([
        'url' => 'https://open.spotify.com/track/' . $spotify_track_id,
        'country' => 'auto',
        'to' => 'amazon'
    ]);
    curl_setopt_array($client, [
        CURLOPT_URL => $base . '?' . $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0']
    ]);
    $html = curl_exec($client);
    curl_close($client);
    if (!$html) { debug_log('lucida_html_empty'); throw new Exception('Lucida non disponibile'); }

    $token = null; $url = null; $expiry = null;
    if (preg_match('/token:\\"([^\\"]+)\\"/i', $html, $m) || preg_match('/"token"\s*:\s*"([^"]+)"/i', $html, $m)) $token = $m[1];
    if (preg_match('/"url":"([^"]+)"/i', $html, $m) || preg_match('/url:\\"([^\\"]+)\\"/i', $html, $m)) $url = $m[1];
    if (preg_match('/tokenExpiry:(\d+)/i', $html, $m) || preg_match('/"tokenExpiry"\s*:\s*(\d+)/i', $html, $m)) $expiry = $m[1];
    if (!$token || !$url) { debug_log('lucida_missing_data', ['token' => (bool)$token, 'url' => (bool)$url]); throw new Exception('Dati necessari non trovati su Lucida'); }
    $url = str_replace('\\/', '/', $url);

    // Prepare request
    $decoded_token = $token; // keep as-is (double base64 occasionally)
    $payload = [
        'account' => ['id' => 'auto', 'type' => 'country'],
        'compat' => 'false',
        'downscale' => 'original',
        'handoff' => true,
        'metadata' => true,
        'private' => true,
        'token' => ['primary' => $decoded_token, 'expiry' => (int)$expiry],
        'upload' => ['enabled' => false, 'service' => 'pixeldrain'],
        'url' => $url
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $base . '/api/load?url=/api/fetch/stream/v2',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: Mozilla/5.0'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400 || !$resp) { debug_log('lucida_load_error', ['code' => $code]); throw new Exception('Errore richiesta Lucida'); }
    $data = json_decode($resp, true);
    if (!$data || empty($data['success'])) { debug_log('lucida_load_fail', ['data' => $data]); throw new Exception('Lucida ha restituito errore'); }

    $server = $data['server'] ?? null; $handoff = $data['handoff'] ?? null;
    if (!$server || !$handoff) throw new Exception('Dati handoff mancanti');
    $status_url = "https://{$server}.lucida.to/api/fetch/request/{$handoff}";

    // Poll until completed
    $max_wait = 120; $elapsed = 0;
    while ($elapsed < $max_wait) {
        $st = http_get_json($status_url, ['User-Agent' => 'Mozilla/5.0'], 15);
        if ($st && ($st['status'] ?? '') === 'completed') { debug_log('lucida_status_completed'); break; }
        if ($st && ($st['status'] ?? '') === 'error') throw new Exception('Lucida processing error');
        sleep(1); $elapsed += 1;
    }
    if ($elapsed >= $max_wait) { debug_log('lucida_status_timeout'); throw new Exception('Timeout in attesa di Lucida'); }

    $download_url = $status_url . '/download';
    $tmp = $target_path . '.part';
    $fp = fopen($tmp, 'wb');
    $dl = curl_init();
    curl_setopt_array($dl, [
        CURLOPT_URL => $download_url,
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 900,
        CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0']
    ]);
    $ok = curl_exec($dl);
    $code = curl_getinfo($dl, CURLINFO_HTTP_CODE);
    curl_close($dl);
    fclose($fp);
    if (!$ok || $code >= 400) { @unlink($tmp); debug_log('lucida_download_error', ['code' => $code]); throw new Exception('Download Lucida fallito'); }
    rename($tmp, $target_path);
    debug_log('amazon_lucida_download_done', ['path' => $target_path]);
    return $target_path;
}

function amazon_lucida_resolve_download_url($spotify_track_id): ?string
{
    $base = app_get_service_endpoint('amazon', 'https://lucida.to');
    $client = curl_init();
    $params = http_build_query([
        'url' => 'https://open.spotify.com/track/' . $spotify_track_id,
        'country' => 'auto',
        'to' => 'amazon'
    ]);
    curl_setopt_array($client, [
        CURLOPT_URL => $base . '?' . $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0']
    ]);
    $html = curl_exec($client);
    curl_close($client);
    if (!$html) return null;
    $token = null; $url = null; $expiry = null;
    if (preg_match('/token:\\"([^\\"]+)\\"/i', $html, $m) || preg_match('/"token"\s*:\s*"([^"]+)"/i', $html, $m)) $token = $m[1];
    if (preg_match('/"url":"([^"]+)"/i', $html, $m) || preg_match('/url:\\"([^\\"]+)\\"/i', $html, $m)) $url = $m[1];
    if (preg_match('/tokenExpiry:(\d+)/i', $html, $m) || preg_match('/"tokenExpiry"\s*:\s*(\d+)/i', $html, $m)) $expiry = $m[1];
    if (!$token || !$url) return null;
    $url = str_replace('\\/', '/', $url);

    $payload = [
        'account' => ['id' => 'auto', 'type' => 'country'],
        'compat' => 'false',
        'downscale' => 'original',
        'handoff' => true,
        'metadata' => true,
        'private' => true,
        'token' => ['primary' => $token, 'expiry' => (int)$expiry],
        'upload' => ['enabled' => false, 'service' => 'pixeldrain'],
        'url' => $url
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $base . '/api/load?url=/api/fetch/stream/v2',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: Mozilla/5.0'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400 || !$resp) return null;
    $data = json_decode($resp, true);
    if (!$data || empty($data['success'])) return null;
    $server = $data['server'] ?? null; $handoff = $data['handoff'] ?? null;
    if (!$server || !$handoff) return null;
    $status_url = "https://{$server}.lucida.to/api/fetch/request/{$handoff}";

    $max_wait = 120; $elapsed = 0;
    while ($elapsed < $max_wait) {
        $st = http_get_json($status_url, ['User-Agent' => 'Mozilla/5.0'], 15);
        if ($st && ($st['status'] ?? '') === 'completed') break;
        if ($st && ($st['status'] ?? '') === 'error') return null;
        sleep(1); $elapsed += 1;
    }
    if ($elapsed >= $max_wait) return null;
    return $status_url . '/download';
}

// Intentionally no closing PHP tag to avoid accidental output
