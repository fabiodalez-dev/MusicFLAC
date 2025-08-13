<?php
@ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/spotify.php';

// Require frontend auth for API access
require_frontend_auth();

$action = $_GET['action'] ?? '';

try {
    debug_log('api_request', ['action' => $action, 'method' => $_SERVER['REQUEST_METHOD'] ?? '']);
    switch ($action) {
        case 'status':
            echo json_encode([
                'status' => 'ok',
                'version' => '1.0.0',
                'timestamp' => date('c')
            ]);
            break;

        case 'services':
            $svcs = app_list_services();
            $out = [];
            foreach ($svcs as $k => $s) {
                $out[] = ['key' => $k, 'label' => $s['label'], 'enabled' => (bool)$s['enabled']];
            }
            echo json_encode(['services' => $out]);
            break;

        case 'fetch_metadata':
            $url = $_POST['url'] ?? $_GET['url'] ?? '';
            debug_log('fetch_metadata', ['url' => $url]);
            if (!$url) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing url']);
                break;
            }
            $data = fetchSpotifyMetadata($url);
            debug_log('fetch_metadata_result', ['ok' => !isset($data['error'])]);
            if (!$data || isset($data['error'])) {
                http_response_code(500);
                echo json_encode(['error' => $data['error'] ?? 'Failed to fetch']);
                break;
            }
            echo json_encode(['ok' => true, 'data' => $data]);
            break;

        case 'prepare_track':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $service = $input['service'] ?? 'tidal';
            $track = $input['track'] ?? null;
            $jobId = $input['job_id'] ?? null;
            debug_log('prepare_track', ['service' => $service, 'hasTrack' => (bool)$track]);
            if (!$track) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid request']);
                break;
            }
            if (!app_service_enabled($service)) {
                http_response_code(400);
                echo json_encode(['error' => 'Servizio non disponibile']);
                break;
            }
            
            // SECURITY: Validate cover image URL if provided
            if (!empty($track['images'])) {
                $img_url = $track['images'];
                if (!is_safe_url($img_url)) {
                    security_log_event('blocked_cover_url', ['url' => $img_url, 'reason' => 'unsafe_url']);
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid cover image URL']);
                    break;
                }
                
                // Must be HTTPS from trusted domain
                $parsed = parse_url($img_url);
                if (!$parsed || ($parsed['scheme'] ?? '') !== 'https') {
                    security_log_event('blocked_cover_url', ['url' => $img_url, 'reason' => 'not_https']);
                    http_response_code(400);
                    echo json_encode(['error' => 'Cover image must be HTTPS']);
                    break;
                }
                
                $allowed_domains = [
                    'i.scdn.co', 'mosaic.scdn.co', 'image-cdn-ak.spotifycdn.com',
                    'images.genius.com', 'cdns-images.dzcdn.net', 
                    'images.qobuz.com', 'resources.tidal.com'
                ];
                
                $host = strtolower($parsed['host'] ?? '');
                $allowed = false;
                foreach ($allowed_domains as $domain) {
                    if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -strlen('.' . $domain)) === '.' . $domain)) {
                        $allowed = true;
                        break;
                    }
                }
                
                if (!$allowed) {
                    security_log_event('blocked_cover_url', ['url' => $img_url, 'reason' => 'domain_not_whitelisted', 'host' => $host]);
                    http_response_code(400);
                    echo json_encode(['error' => 'Cover image domain not allowed']);
                    break;
                }
            }
            try {
                $path = downloadTrack($track, $service, $jobId);
                debug_log('prepare_track_result', ['path' => $path, 'exists' => file_exists($path)]);
                if (!$path || !file_exists($path)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Download failed']);
                    break;
                }
                // Log download
                $title = (($track['artists'] ?? '') ? ($track['artists'] . ' - ') : '') . ($track['name'] ?? '');
                app_log_download('track', $title, $track['external_urls'] ?? '', ['service' => $service, 'file' => basename($path)]);
                echo json_encode(['ok' => true, 'file' => basename($path)]);
            } catch (Throwable $e) {
                debug_log('prepare_track_error', ['error' => $e->getMessage()]);
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case 'prepare_album':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $service = $input['service'] ?? 'tidal';
            $tracks = $input['tracks'] ?? [];
            $album = $input['album'] ?? 'MusicFLAC_Album';
            $jobId = $input['job_id'] ?? null;
            debug_log('prepare_album', ['service' => $service, 'tracks' => count($tracks), 'album' => $album]);
            if (!$tracks) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid request']);
                break;
            }
            if (!app_service_enabled($service)) {
                http_response_code(400);
                echo json_encode(['error' => 'Servizio non disponibile']);
                break;
            }
            
            // SECURITY: Validate all track image URLs in album
            foreach ($tracks as $index => $track) {
                if (!empty($track['images'])) {
                    $img_url = $track['images'];
                    if (!is_safe_url($img_url)) {
                        security_log_event('blocked_album_cover_url', ['url' => $img_url, 'track_index' => $index, 'reason' => 'unsafe_url']);
                        http_response_code(400);
                        echo json_encode(['error' => "Invalid cover image URL in track $index"]);
                        break 2; // Exit both foreach and case
                    }
                    
                    $parsed = parse_url($img_url);
                    if (!$parsed || ($parsed['scheme'] ?? '') !== 'https') {
                        security_log_event('blocked_album_cover_url', ['url' => $img_url, 'track_index' => $index, 'reason' => 'not_https']);
                        http_response_code(400);
                        echo json_encode(['error' => "Cover image must be HTTPS in track $index"]);
                        break 2;
                    }
                    
                    $allowed_domains = [
                        'i.scdn.co', 'mosaic.scdn.co', 'image-cdn-ak.spotifycdn.com',
                        'images.genius.com', 'cdns-images.dzcdn.net', 
                        'images.qobuz.com', 'resources.tidal.com'
                    ];
                    
                    $host = strtolower($parsed['host'] ?? '');
                    $allowed = false;
                    foreach ($allowed_domains as $domain) {
                        if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -strlen('.' . $domain)) === '.' . $domain)) {
                            $allowed = true;
                            break;
                        }
                    }
                    
                    if (!$allowed) {
                        security_log_event('blocked_album_cover_url', ['url' => $img_url, 'track_index' => $index, 'reason' => 'domain_not_whitelisted', 'host' => $host]);
                        http_response_code(400);
                        echo json_encode(['error' => "Cover image domain not allowed in track $index"]);
                        break 2;
                    }
                }
            }
            try {
                $zip = createAlbumZip($tracks, $album, $service, $jobId);
                debug_log('prepare_album_result', ['zip' => $zip, 'exists' => file_exists($zip)]);
                if (!$zip || !file_exists($zip)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'ZIP creation failed']);
                    break;
                }
                // Log album/playlist download
                $spotify_url = '';
                if (!empty($tracks[0]['external_urls'])) $spotify_url = $tracks[0]['external_urls'];
                app_log_download('album', $album, $spotify_url, ['service' => $service, 'file' => basename($zip), 'tracks' => count($tracks)]);
                echo json_encode(['ok' => true, 'file' => basename($zip)]);
            } catch (Throwable $e) {
                debug_log('prepare_album_error', ['error' => $e->getMessage()]);
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case 'active_downloads':
            $job = $_GET['job_id'] ?? null;
            echo json_encode(['active' => app_active_list($job)]);
            break;

        case 'job_status':
            $job = $_GET['job_id'] ?? null;
            if (!$job) { http_response_code(400); echo json_encode(['error' => 'Missing job_id']); break; }
            $info = app_job_get($job);
            echo json_encode(['job' => $info, 'active' => app_active_list($job)]);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'error' => 'Unknown action',
                'available_actions' => ['status', 'services', 'fetch_metadata', 'prepare_track', 'prepare_album']
            ]);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
