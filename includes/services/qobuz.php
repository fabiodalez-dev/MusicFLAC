<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../spotify.php';

function qobuz_select_track_by_isrc($isrc, $region = 'us')
{
    debug_log('qobuz_select_track_by_isrc', ['isrc' => $isrc, 'region' => $region]);
    $search = 'https://' . $region . '.qobuz.squid.wtf/api/get-music?q=' . urlencode($isrc) . '&offset=0&limit=10';
    $data = http_get_json($search, [], 20);
    if (!$data || empty($data['success'])) throw new Exception('Qobuz ricerca fallita');
    $items = $data['data']['tracks']['items'] ?? [];
    if (!$items) throw new Exception('Traccia Qobuz non trovata');
    $selected = null;
    $prio = function ($t) { $d = $t['maximum_bit_depth'] ?? 0; return $d == 24 ? 1 : ($d == 16 ? 2 : 3); };
    foreach ($items as $t) {
        if (($t['isrc'] ?? '') === $isrc) {
            if ($selected === null || $prio($t) < $prio($selected)) $selected = $t;
            if ($prio($selected) === 1) break;
        }
    }
    if (!$selected) throw new Exception('ISRC non presente su Qobuz');
    debug_log('qobuz_select_track_by_isrc_result', ['found' => (bool)$selected]);
    return $selected;
}

function qobuz_get_download_url($track_id, $region = 'us')
{
    debug_log('qobuz_get_download_url', ['track_id' => $track_id, 'region' => $region]);
    $url = 'https://' . $region . '.qobuz.squid.wtf/api/download-music?track_id=' . urlencode($track_id) . '&quality=27';
    $data = http_get_json($url, [], 20);
    if (!$data || empty($data['success'])) throw new Exception('Qobuz API download fallita');
    $dl = $data['data']['url'] ?? null;
    if (!$dl) throw new Exception('Qobuz URL mancante');
    debug_log('qobuz_get_download_url_result', ['hasUrl' => (bool)$dl]);
    return $dl;
}

function qobuz_download_track_by_isrc($isrc, $target_path, $region = 'us')
{
    debug_log('qobuz_download_track_by_isrc', ['isrc' => $isrc, 'target' => $target_path]);
    $track = qobuz_select_track_by_isrc($isrc, $region);
    $dl = qobuz_get_download_url($track['id'], $region);
    $tmp = $target_path . '.part';
    $fp = fopen($tmp, 'wb');
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $dl,
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
        throw new Exception('Download Qobuz fallito');
    }
    rename($tmp, $target_path);
    debug_log('qobuz_download_done', ['path' => $target_path]);
    return $target_path;
}

// Intentionally no closing PHP tag to avoid accidental output
