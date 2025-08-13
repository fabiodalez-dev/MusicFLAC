<?php
require_once 'config.php';
require_once __DIR__ . '/spotify.php';
require_once __DIR__ . '/services/tidal.php';
require_once __DIR__ . '/services/qobuz.php';
require_once __DIR__ . '/services/amazon.php';
require_once __DIR__ . '/app.php';

/**
 * Fetch metadata from Spotify URL
 * @param string $url Spotify URL
 * @return array Metadata or error
 */
function fetchSpotifyMetadata($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL) || strpos($url, 'spotify.com') === false) {
        return ['error' => 'URL Spotify non valido'];
    }
    try {
        return spotify_fetch_and_format($url);
    } catch (Throwable $e) {
        return ['error' => 'Errore nel recupero dati da Spotify: ' . $e->getMessage()];
    }
}

/**
 * Format duration from milliseconds to MM:SS
 * @param int $milliseconds
 * @return string Formatted duration
 */
function formatDuration($milliseconds) {
    $seconds = floor($milliseconds / 1000);
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $seconds);
}

/**
 * Sanitize filename
 * @param string $filename
 * @return string Sanitized filename
 */
function sanitizeFilename($filename) {
    // Remove dangerous characters but keep more readability
    $filename = preg_replace('/[\/\\:*?"<>|]/', '', $filename);
    // Remove any remaining special characters except spaces, hyphens, and dots
    $filename = preg_replace('/[^a-zA-Z0-9\s\-\.]/', '', $filename);
    // Normalize multiple spaces to single space
    $filename = preg_replace('/\s+/', ' ', $filename);
    // Trim whitespace
    $filename = trim($filename);
    // Limit length
    $filename = substr($filename, 0, 100);
    return $filename ?: 'unknown';
}

function command_exists($cmd) {
    // Enhanced security: whitelist allowed commands
    $allowed_commands = ['metaflac', 'ffmpeg', 'ffprobe'];
    if (!in_array(basename($cmd), $allowed_commands)) {
        return false;
    }
    
    $where = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'command -v';
    $out = [];
    // Additional validation of command name
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $cmd)) {
        return false;
    }
    @exec($where . ' ' . escapeshellarg($cmd), $out, $code);
    return $code === 0 && !empty($out);
}

function embed_flac_metadata($filepath, array $track): bool {
    if (!is_file($filepath)) return false;
    if (strtolower(pathinfo($filepath, PATHINFO_EXTENSION)) !== 'flac') return false;
    if (!command_exists('metaflac')) return false;
    
    // Enhanced security: validate file path
    try {
        security_check_file_access($filepath, [DOWNLOAD_DIR]);
    } catch (Exception $e) {
        error_log("[SECURITY] Blocked metadata embed for file: $filepath - " . $e->getMessage());
        return false;
    }

    // Sanitize all metadata values to prevent injection
    $title = security_validate_input($track['name'] ?? '', 'string', 200);
    $artist = security_validate_input($track['artists'] ?? '', 'string', 200);
    $album = security_validate_input($track['album'] ?? '', 'string', 200);
    $tracknum = security_validate_input((string)($track['track_number'] ?? ''), 'alphanumeric', 10);
    $date = security_validate_input($track['release_date'] ?? '', 'alphanumeric', 20);
    $isrc = security_validate_input($track['isrc'] ?? '', 'alphanumeric', 20);

    // Clean existing tags
    $cmd = 'metaflac --remove-all ' . escapeshellarg($filepath);
    security_prevent_command_injection($cmd);
    @exec($cmd);

    $tags = [];
    if ($title) $tags[] = 'TITLE=' . $title;
    if ($artist) $tags[] = 'ARTIST=' . $artist;
    if ($album) $tags[] = 'ALBUM=' . $album;
    if ($tracknum) $tags[] = 'TRACKNUMBER=' . $tracknum;
    if ($date) $tags[] = 'DATE=' . $date;
    if ($isrc) $tags[] = 'ISRC=' . $isrc;

    foreach ($tags as $t) {
        $cmd = 'metaflac --set-tag=' . escapeshellarg($t) . ' ' . escapeshellarg($filepath);
        try {
            security_prevent_command_injection($cmd);
            @exec($cmd);
        } catch (Exception $e) {
            error_log("[SECURITY] Blocked potentially dangerous metaflac command: $cmd");
        }
    }

    // Cover art from track image - SECURITY: Use only service-provided URLs
    $img_url = $track['images'] ?? '';
    if ($img_url) {
        try {
            // CRITICAL SECURITY: Strict URL validation to prevent SSRF/LFI
            if (!is_safe_url($img_url)) {
                throw new Exception('Unsafe cover art URL blocked');
            }
            
            // Additional validation: must be HTTPS and from trusted domains
            $parsed = parse_url($img_url);
            if (!$parsed || ($parsed['scheme'] ?? '') !== 'https') {
                throw new Exception('Cover art URL must be HTTPS');
            }
            
            // Whitelist trusted domains for cover art
            $allowed_cover_domains = [
                'i.scdn.co', 'mosaic.scdn.co', 'image-cdn-ak.spotifycdn.com',
                'images.genius.com', 'cdns-images.dzcdn.net', 
                'images.qobuz.com', 'resources.tidal.com'
            ];
            
            $host = strtolower($parsed['host'] ?? '');
            $allowed = false;
            foreach ($allowed_cover_domains as $domain) {
                if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -strlen('.' . $domain)) === '.' . $domain)) {
                    $allowed = true;
                    break;
                }
            }
            
            if (!$allowed) {
                throw new Exception('Cover art domain not in whitelist: ' . $host);
            }
            
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cover_' . uniqid() . '.jpg';
            $img = http_get($img_url, ['User-Agent' => 'Mozilla/5.0'], 15);
            if ($img && strlen($img) > 0 && strlen($img) < 5242880) { // Max 5MB
                // Validate image content
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detected_type = $finfo->buffer($img);
                if (!in_array($detected_type, ['image/jpeg', 'image/png', 'image/webp'])) {
                    throw new Exception('Invalid image type: ' . $detected_type);
                }
                
                file_put_contents($tmp, $img);
                
                $cmd1 = 'metaflac --remove --block-type=PICTURE ' . escapeshellarg($filepath);
                $cmd2 = 'metaflac --import-picture-from=' . escapeshellarg($tmp) . ' ' . escapeshellarg($filepath);
                
                security_prevent_command_injection($cmd1);
                security_prevent_command_injection($cmd2);
                
                @exec($cmd1);
                @exec($cmd2);
                @unlink($tmp);
            }
        } catch (Exception $e) {
            error_log("[SECURITY] Blocked cover art processing: " . $e->getMessage() . " - URL: " . $img_url);
        }
    }
    return true;
}

/**
 * Get fallback services in consistent order: Tidal -> Amazon -> Qobuz
 * Always tries services in this order, excluding the primary service
 * @param string $primary_service Primary service that was requested
 * @return array List of fallback services to try
 */
function get_fallback_services($primary_service) {
    $preferred_order = ['tidal', 'amazon', 'qobuz'];
    
    // Find the position of the primary service and reorder
    $primary_pos = array_search($primary_service, $preferred_order);
    if ($primary_pos === false) return $preferred_order; // fallback if service not found
    
    // Create array starting from tidal but excluding primary
    $fallbacks = [];
    foreach ($preferred_order as $service) {
        if ($service !== $primary_service) {
            $fallbacks[] = $service;
        }
    }
    
    return $fallbacks;
}

/**
 * Download track using selected service with automatic fallback
 * @param array $track Track data
 * @param string $service Service to use
 * @return string Path to downloaded file or error
 */
function downloadTrack($track, $service, ?string $job_id = null) {
    debug_log('downloadTrack start', ['service' => $service, 'track' => [
        'name' => $track['name'] ?? '', 'artists' => $track['artists'] ?? '', 'isrc' => $track['isrc'] ?? ''
    ]]);
    
    $artist = $track['artists'] ?? 'Unknown Artist';
    $title = $track['name'] ?? 'Unknown Track';
    $track_no = (int)($track['track_number'] ?? 0);
    $prefix = $track_no > 0 ? str_pad((string)$track_no, 2, '0', STR_PAD_LEFT) . ' - ' : '';
    $filename = sanitizeFilename("{$prefix}{$artist} - {$title}.flac");
    $filepath = DOWNLOAD_DIR . $filename;

    // Ensure directory exists
    if (!is_dir(DOWNLOAD_DIR)) @mkdir(DOWNLOAD_DIR, 0755, true);

    // Optionally track job
    if ($job_id) {
        app_job_start($job_id, 1, 'track');
        $title_title = (($track['artists'] ?? '') ? ($track['artists'] . ' - ') : '') . ($track['name'] ?? '');
        app_active_add($title_title, $job_id);
    }

    // Try primary service first, then fallbacks
    $services_to_try = array_merge([$service], get_fallback_services($service));
    
    foreach ($services_to_try as $current_service) {
        try {
            debug_log('downloadTrack attempt', ['service' => $current_service]);
            
            if ($current_service === 'qobuz') {
                $isrc = $track['isrc'] ?? '';
                if (!$isrc) throw new Exception('ISRC mancante per Qobuz');
                qobuz_download_track_by_isrc($isrc, $filepath, 'us');
            } elseif ($current_service === 'tidal') {
                $query = trim(($track['name'] ?? '') . ' ' . ($track['artists'] ?? ''));
                $isrc = $track['isrc'] ?? null;
                $t = tidal_search_track($query, $isrc);
                if (!$t) throw new Exception('Traccia non trovata su Tidal');
                tidal_download_track($t, $filepath);
            } elseif ($current_service === 'amazon') {
                $ext = $track['external_urls'] ?? '';
                $sid = '';
                if ($ext && preg_match('#/track/([A-Za-z0-9]+)#', $ext, $m)) $sid = $m[1];
                if (!$sid) throw new Exception('Spotify ID mancante per Amazon');
                amazon_lucida_download($sid, $filepath);
            } else {
                throw new Exception('Servizio non supportato');
            }
            
            // If we get here, download succeeded
            debug_log('downloadTrack success', ['service' => $current_service, 'path' => $filepath]);
            embed_flac_metadata($filepath, $track);
            if ($job_id) { app_active_remove($title_title, $job_id); app_job_increment_complete($job_id); }
            return $filepath;
            
        } catch (Throwable $e) {
            debug_log('downloadTrack failed', ['service' => $current_service, 'error' => $e->getMessage()]);
            // Clean up any partial files
            if (file_exists($filepath)) @unlink($filepath);
            // Continue to next service
        }
    }
    
    // All services failed
    debug_log('downloadTrack all_failed', ['track' => $artist . ' - ' . $title]);
    if ($job_id) { app_active_remove($title_title, $job_id); }
    throw new Exception('Download fallito su tutti i servizi per: ' . $artist . ' - ' . $title);
}

/**
 * Download multiple tracks simultaneously for better performance
 * @param array $tracks List of tracks to download
 * @param string $service Service to use
 * @return array Array of successfully downloaded file paths
 */
function downloadTracksSimultaneously($tracks, $service, ?string $job_id = null) {
    debug_log('downloadTracksSimultaneously start', ['tracks' => count($tracks), 'service' => $service]);
    if (!is_dir(DOWNLOAD_DIR)) @mkdir(DOWNLOAD_DIR, 0755, true);

    // Step 1: resolve download URLs per track with fallbacks
    if ($job_id) app_job_start($job_id, count($tracks), 'album');
    $entries = [];
    foreach ($tracks as $track) {
        $artist = $track['artists'] ?? 'Unknown Artist';
        $title = $track['name'] ?? 'Unknown Track';
        $track_no = (int)($track['track_number'] ?? 0);
        $prefix = $track_no > 0 ? str_pad((string)$track_no, 2, '0', STR_PAD_LEFT) . ' - ' : '';
        $filename = sanitizeFilename("{$prefix}{$artist} - {$title}.flac");
        $target = DOWNLOAD_DIR . $filename;
        $resolved = resolve_download_for_track($track, $service);
        if ($resolved && isset($resolved['url'])) {
            $entries[] = [ 'url' => $resolved['url'], 'target' => $target, 'track' => $track ];
        }
    }

    // Step 2: download concurrently using curl_multi
    $conc = (int) (app_get_setting('download_concurrency', 4));
    if ($conc < 1) $conc = 1; if ($conc > 8) $conc = 8;
    $downloaded = concurrent_download_files($entries, $conc, $job_id);
    debug_log('downloadTracksSimultaneously complete', ['total_downloaded' => count($downloaded)]);
    return $downloaded;
}

/**
 * Create ZIP archive of tracks with simultaneous downloads
 * @param array $tracks List of tracks
 * @param string $albumName Album name
 * @param string $service Service to use
 * @return string Path to ZIP file or error
 */
function createAlbumZip($tracks, $albumName, $service, ?string $job_id = null) {
    debug_log('createAlbumZip start', ['tracks' => count($tracks), 'album' => $albumName, 'service' => $service]);
    
    // Get artist name from first track for better filename
    $artistName = '';
    if (!empty($tracks[0]['artists'])) {
        $artistName = $tracks[0]['artists'];
    }
    
    // Create a more descriptive filename with artist name
    $filenameBase = $albumName;
    if ($artistName) {
        $filenameBase = $artistName . ' - ' . $albumName;
    }
    
    $filenameBase = sanitizeFilename($filenameBase ?: 'MusicFLAC_Album');
    if (!is_dir(DOWNLOAD_DIR)) @mkdir(DOWNLOAD_DIR, 0755, true);
    $zipFilename = $filenameBase . '.zip';
    $zipFilepath = DOWNLOAD_DIR . $zipFilename;

    $tmp_dir = DOWNLOAD_DIR . '.tmp_' . uniqid();
    @mkdir($tmp_dir, 0755, true);

    // Use simultaneous download function
    $downloaded = downloadTracksSimultaneously($tracks, $service, $job_id);

    // If no tracks downloaded successfully, return error
    if (empty($downloaded)) {
        debug_log('createAlbumZip no_tracks', []);
        @rmdir($tmp_dir);
        throw new Exception('Nessuna traccia scaricata con successo');
    }

    // Fetch cover (from first track image) if available - SECURITY HARDENED
    $coverPath = null;
    if (!empty($tracks[0]['images'])) {
        $coverUrl = $tracks[0]['images'];
        try {
            // CRITICAL SECURITY: Same strict validation as embed_flac_metadata
            if (!is_safe_url($coverUrl)) {
                throw new Exception('Unsafe cover URL blocked for ZIP');
            }
            
            // Must be HTTPS and from trusted domains
            $parsed = parse_url($coverUrl);
            if (!$parsed || ($parsed['scheme'] ?? '') !== 'https') {
                throw new Exception('Cover URL must be HTTPS for ZIP');
            }
            
            // Whitelist trusted domains for cover art
            $allowed_cover_domains = [
                'i.scdn.co', 'mosaic.scdn.co', 'image-cdn-ak.spotifycdn.com',
                'images.genius.com', 'cdns-images.dzcdn.net', 
                'images.qobuz.com', 'resources.tidal.com'
            ];
            
            $host = strtolower($parsed['host'] ?? '');
            $allowed = false;
            foreach ($allowed_cover_domains as $domain) {
                if ($host === $domain || (strlen($host) > strlen($domain) && substr($host, -strlen('.' . $domain)) === '.' . $domain)) {
                    $allowed = true;
                    break;
                }
            }
            
            if (!$allowed) {
                throw new Exception('Cover domain not in whitelist for ZIP: ' . $host);
            }
            
            $coverPath = $tmp_dir . '/cover.jpg';
            $img = http_get($coverUrl, ['User-Agent' => 'Mozilla/5.0'], 15);
            if ($img && strlen($img) > 0 && strlen($img) < 5242880) { // Max 5MB
                // Validate image content
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detected_type = $finfo->buffer($img);
                if (!in_array($detected_type, ['image/jpeg', 'image/png', 'image/webp'])) {
                    throw new Exception('Invalid image type for ZIP: ' . $detected_type);
                }
                file_put_contents($coverPath, $img);
            } else {
                $coverPath = null;
            }
        } catch (Exception $e) {
            error_log("[SECURITY] Blocked ZIP cover processing: " . $e->getMessage() . " - URL: " . $coverUrl);
            $coverPath = null;
        }
    }

    $zip = new ZipArchive();
    if ($zip->open($zipFilepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        debug_log('createAlbumZip zip_open_failed', ['zip' => $zipFilepath]);
        // Cleanup
        foreach ($downloaded as $f) @unlink($f);
        if ($coverPath) @unlink($coverPath);
        @rmdir($tmp_dir);
        throw new Exception('Impossibile creare il file ZIP');
    }

    foreach ($downloaded as $file) {
        $zip->addFile($file, basename($file));
    }
    if ($coverPath && file_exists($coverPath)) {
        $zip->addFile($coverPath, 'cover.jpg');
    }
    $zip->close();
    debug_log('createAlbumZip done', ['zip' => $zipFilepath, 'tracks_count' => count($downloaded)]);

    // Remove individual files after zipping
    foreach ($downloaded as $f) @unlink($f);
    if ($coverPath) @unlink($coverPath);
    @rmdir($tmp_dir);

    return $zipFilepath;
}

function resolve_download_for_track(array $track, string $service): ?array {
    $services_to_try = array_merge([$service], get_fallback_services($service));
    foreach ($services_to_try as $svc) {
        try {
            if ($svc === 'qobuz') {
                $isrc = $track['isrc'] ?? '';
                if (!$isrc) throw new Exception('ISRC mancante');
                $dl = qobuz_get_download_url(qobuz_select_track_by_isrc($isrc, 'us')['id'], 'us');
                if ($dl) return ['service' => $svc, 'url' => $dl];
            } elseif ($svc === 'tidal') {
                $query = trim(($track['name'] ?? '') . ' ' . ($track['artists'] ?? ''));
                $isrc = $track['isrc'] ?? null;
                $t = tidal_search_track($query, $isrc);
                if ($t) {
                    $dl = tidal_get_original_download_url($t);
                    if ($dl) return ['service' => $svc, 'url' => $dl];
                }
            } elseif ($svc === 'amazon') {
                $ext = $track['external_urls'] ?? '';
                $sid = '';
                if ($ext && preg_match('#/track/([A-Za-z0-9]+)#', $ext, $m)) $sid = $m[1];
                if ($sid) {
                    $dl = amazon_lucida_resolve_download_url($sid);
                    if ($dl) return ['service' => $svc, 'url' => $dl];
                }
            }
        } catch (Throwable $e) {
            debug_log('resolve_download_error', ['service' => $svc, 'error' => $e->getMessage()]);
        }
    }
    return null;
}

function concurrent_download_files(array $entries, int $concurrency = 4, ?string $job_id = null): array {
    $mh = curl_multi_init();
    $handles = [];
    $files = [];
    $active = 0;
    $queue = $entries;
    $downloaded = [];

    $addHandle = function($entry) use ($mh, &$handles, &$files, $job_id) {
        $url = $entry['url']; $target = $entry['target']; $track = $entry['track'];
        $tmp = $target . '.part';
        $fp = fopen($tmp, 'wb');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_USERAGENT => 'Mozilla/5.0'
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int)$ch] = ['ch' => $ch, 'fp' => $fp, 'entry' => $entry, 'tmp' => $tmp];
        $title = (($track['artists'] ?? '') ? ($track['artists'] . ' - ') : '') . ($track['name'] ?? '');
        if ($job_id) app_active_add($title, $job_id);
    };

    // Prime the pipeline
    while ($concurrency-- > 0 && !empty($queue)) {
        $addHandle(array_shift($queue));
    }

    do {
        $status = curl_multi_exec($mh, $active);
        if ($status > CURLM_OK) break;
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $key = (int)$ch;
            if (!isset($handles[$key])) { curl_multi_remove_handle($mh, $ch); curl_close($ch); continue; }
            $meta = $handles[$key];
            fclose($meta['fp']);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $track = $meta['entry']['track'];
            $target = $meta['entry']['target'];
            $tmp = $meta['tmp'];
            $title = (($track['artists'] ?? '') ? ($track['artists'] . ' - ') : '') . ($track['name'] ?? '');
            if ($job_id) app_active_remove($title, $job_id);
            if ($code < 400 && file_exists($tmp) && filesize($tmp) > 0) {
                rename($tmp, $target);
                embed_flac_metadata($target, $track);
                $downloaded[] = $target;
                if ($job_id) app_job_increment_complete($job_id);
            } else {
                @unlink($tmp);
            }
            unset($handles[$key]);
            if (!empty($queue)) { $addHandle(array_shift($queue)); }
        }
        curl_multi_select($mh, 1);
    } while ($active || !empty($queue));

    foreach ($handles as $h) { @fclose($h['fp']); if (is_resource($h['ch'])) { curl_multi_remove_handle($mh, $h['ch']); curl_close($h['ch']); } }
    curl_multi_close($mh);
    return $downloaded;
}
// Intentionally no closing PHP tag to avoid accidental output
