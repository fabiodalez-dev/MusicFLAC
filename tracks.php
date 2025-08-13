<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once 'includes/functions.php';

// Function to format date as dd-MM-YYYY
function formatDate($dateString) {
    if (empty($dateString)) return '';
    
    // Try to parse the date
    $date = strtotime($dateString);
    if ($date === false) return $dateString;
    
    return date('d-m-Y', $date);
}

// Require frontend auth
require_frontend_auth();

// Cleanup old downloads on each request
if (function_exists('cleanup_old_downloads')) {
    cleanup_old_downloads();
}

// Check if metadata is available
if (!isset($_SESSION['metadata'])) {
    header('Location: index.php');
    exit;
}

$metadata = $_SESSION['metadata'];
$service = $_SESSION['service'] ?? 'tidal';
if (!array_key_exists($service, SUPPORTED_SERVICES)) { $service = 'tidal'; }
if (function_exists('app_service_enabled') && !app_service_enabled($service)) {
    // pick first enabled service
    $svcs = app_list_services();
    foreach ($svcs as $k=>$s) { if ($s['enabled']) { $service = $k; break; } }
}

// Handle download actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting for downloads
    try {
        security_rate_limit('download', 10, 100); // 10/min, 100/hour
    } catch (Exception $e) {
        http_response_code(429);
        die('Too many download requests');
    }
    
    if (isset($_POST['download_track'])) {
        // Download single track
        $trackIndex = (int)$_POST['track_index'];
        
        // Validate track index
        $tracks = $metadata['track_list'] ?? [$metadata['track']];
        if ($trackIndex < 0 || $trackIndex >= count($tracks)) {
            http_response_code(400);
            die('Invalid track index');
        }
        
        $track = $tracks[$trackIndex];
        
        $filepath = downloadTrack($track, $service);
        if ($filepath && file_exists($filepath)) {
            $title = (($track['artists'] ?? '') ? ($track['artists'] . ' - ') : '') . ($track['name'] ?? '');
            app_log_download('track', $title, $track['external_urls'] ?? '', ['service' => $service, 'file' => basename($filepath)]);
        }
        
        if ($filepath && file_exists($filepath)) {
            // SECURITY: Validate file path before download
            try {
                security_check_file_access($filepath, [DOWNLOAD_DIR]);
                
                // Enhanced security headers
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: no-referrer');
                header('Content-Security-Policy: default-src \"none\"');
                
                // Set headers for download
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                
                // Sanitize filename
                $safe_filename = preg_replace('/[^\\w\\s\\-\\.]/u', '', basename($filepath));
                header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
                header('Expires: 0');
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                // File size limit check
                $filesize = filesize($filepath);
                if ($filesize > 104857600) { // 100MB
                    throw new Exception('File too large');
                }
                
                header('Content-Length: ' . $filesize);
                readfile($filepath);
                
                // Remove the file immediately after sending
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
                exit;
            } catch (Exception $e) {
                error_log("[SECURITY] Blocked download attempt: " . $e->getMessage() . " - File: $filepath");
                http_response_code(403);
                die('Access denied');
            }
        }
    } elseif (isset($_POST['download_album'])) {
        // Download entire album/playlist as ZIP
        $tracks = $metadata['track_list'] ?? [$metadata['track']];
        $albumName = $metadata['album_info']['name'] ?? $metadata['playlist_info']['name'] ?? 'MusicFLAC_Download';
        
        $zipFilepath = createAlbumZip($tracks, $albumName, $service);
        if ($zipFilepath && file_exists($zipFilepath)) {
            $spotify_url = !empty($tracks[0]['external_urls']) ? $tracks[0]['external_urls'] : '';
            app_log_download('album', $albumName, $spotify_url, ['service' => $service, 'file' => basename($zipFilepath), 'tracks' => count($tracks)]);
        }
        
        if ($zipFilepath && file_exists($zipFilepath)) {
            // SECURITY: Validate ZIP file path before download
            try {
                security_check_file_access($zipFilepath, [DOWNLOAD_DIR]);
                
                // Enhanced security headers
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: no-referrer');
                header('Content-Security-Policy: default-src \"none\"');
                
                // Set headers for download
                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                
                // Sanitize filename
                $safe_filename = preg_replace('/[^\\w\\s\\-\\.]/u', '', basename($zipFilepath));
                header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
                header('Expires: 0');
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                // File size limit check
                $filesize = filesize($zipFilepath);
                if ($filesize > 524288000) { // 500MB for ZIP files
                    throw new Exception('ZIP file too large');
                }
                
                header('Content-Length: ' . $filesize);
                readfile($zipFilepath);
                
                // Remove the file immediately after sending
                if (file_exists($zipFilepath)) {
                    @unlink($zipFilepath);
                }
                exit;
            } catch (Exception $e) {
                error_log("[SECURITY] Blocked ZIP download attempt: " . $e->getMessage() . " - File: $zipFilepath");
                http_response_code(403);
                die('Access denied');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracks - MusicFLAC</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-900 text-white">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gray-800 py-4 px-6 shadow-lg">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold text-accent flex items-center">
                    <i class="fas fa-music mr-2"></i> MusicFLAC
                </h1>
                <nav>
                    <ul class="flex space-x-6">
                        <li><a href="index.php" class="hover:text-accent transition">Home</a></li>
                        <li><a href="about.php" class="hover:text-accent transition">About</a></li>
                        <li><a href="status.php" class="hover:text-accent transition">Status</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-6xl mx-auto">
                <!-- Album/Track Info -->
                <div class="bg-gray-800 rounded-2xl p-6 mb-8 shadow-xl backdrop-blur-lg bg-opacity-70 border border-gray-700">
                    <div class="flex flex-col md:flex-row gap-6">
                        <?php if (isset($metadata['track'])): ?>
                            <!-- Single Track -->
                            <div class="md:w-1/3">
                                <img src="<?= htmlspecialchars($metadata['track']['images']) ?>" alt="Cover" class="w-full rounded-xl shadow-lg">
                            </div>
                            <div class="md:w-2/3">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h2 class="text-3xl font-bold"><?= htmlspecialchars($metadata['track']['name']) ?></h2>
                                        <p class="text-xl text-gray-300 mt-2"><?= htmlspecialchars($metadata['track']['artists']) ?></p>
                                        <p class="text-lg text-gray-400 mt-1"><?= htmlspecialchars($metadata['track']['album']) ?></p>
                                    </div>
                                    <span class="bg-green-600 text-white px-3 py-1 rounded-full text-sm">Track</span>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <p class="text-gray-400">Duration</p>
                                        <p class="text-lg"><?= formatDuration($metadata['track']['duration_ms']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400">Release Date</p>
                                        <p class="text-lg"><?= htmlspecialchars(formatDate($metadata['track']['release_date'])) ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="track_index" value="0">
                                        <button type="submit" name="download_track" class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold transition flex items-center">
                                            <i class="fas fa-download mr-2"></i> Download Track
                                        </button>
                                    </form>
                                    <button onclick="playPreview()" class="bg-gray-700 hover:bg-gray-600 px-6 py-3 rounded-lg font-semibold transition flex items-center">
                                        <i class="fas fa-play mr-2"></i> Preview
                                    </button>
                                </div>
                            </div>
                        <?php elseif (isset($metadata['album_info'])): ?>
                            <!-- Album -->
                            <div class="md:w-1/3">
                                <img src="<?= htmlspecialchars($metadata['album_info']['images']) ?>" alt="Cover" class="w-full rounded-xl shadow-lg">
                            </div>
                            <div class="md:w-2/3">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h2 class="text-3xl font-bold"><?= htmlspecialchars($metadata['album_info']['name']) ?></h2>
                                        <p class="text-xl text-gray-300 mt-2"><?= htmlspecialchars($metadata['album_info']['artists']) ?></p>
                                    </div>
                                    <span class="bg-purple-600 text-white px-3 py-1 rounded-full text-sm">Album</span>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <p class="text-gray-400">Release Date</p>
                                        <p class="text-lg"><?= htmlspecialchars(formatDate($metadata['album_info']['release_date'])) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400">Tracks</p>
                                        <p class="text-lg"><?= htmlspecialchars($metadata['album_info']['total_tracks']) ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <form method="POST" class="inline">
                                        <button type="submit" name="download_album" class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold transition flex items-center">
                                            <i class="fas fa-download mr-2"></i> Download Album
                                        </button>
                                    </form>
                                    <button onclick="playPreview()" class="bg-gray-700 hover:bg-gray-600 px-6 py-3 rounded-lg font-semibold transition flex items-center">
                                        <i class="fas fa-play mr-2"></i> Preview
                                    </button>
                                </div>
                            </div>
                        <?php elseif (isset($metadata['playlist_info'])): ?>
                            <!-- Playlist -->
                            <div class="md:w-1/3">
                                <img src="<?= htmlspecialchars($metadata['playlist_info']['owner']['images'] ?? 'https://placehold.co/300') ?>" alt="Cover" class="w-full rounded-xl shadow-lg">
                            </div>
                            <div class="md:w-2/3">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h2 class="text-3xl font-bold"><?= htmlspecialchars($metadata['playlist_info']['name']) ?></h2>
                                        <p class="text-xl text-gray-300 mt-2">By <?= htmlspecialchars($metadata['playlist_info']['owner']['display_name']) ?></p>
                                    </div>
                                    <span class="bg-blue-600 text-white px-3 py-1 rounded-full text-sm">Playlist</span>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <p class="text-gray-400">Tracks</p>
                                        <p class="text-lg"><?= htmlspecialchars($metadata['playlist_info']['tracks']['total']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-gray-400">Followers</p>
                                        <p class="text-lg"><?= number_format($metadata['playlist_info']['followers']['total']) ?></p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <form method="POST" class="inline">
                                        <button type="submit" name="download_album" class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold transition flex items-center">
                                            <i class="fas fa-download mr-2"></i> Download Playlist
                                        </button>
                                    </form>
                                    <button onclick="playPreview()" class="bg-gray-700 hover:bg-gray-600 px-6 py-3 rounded-lg font-semibold transition flex items-center">
                                        <i class="fas fa-play mr-2"></i> Preview
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Track List -->
                <div class="bg-gray-800 rounded-2xl p-6 shadow-xl backdrop-blur-lg bg-opacity-70 border border-gray-700">
                    <h3 class="text-2xl font-bold mb-6">Tracks</h3>
                    <div class="space-y-4">
                        <?php 
                        $tracks = $metadata['track_list'] ?? (isset($metadata['track']) ? [$metadata['track']] : []);
                        foreach ($tracks as $index => $track): ?>
                            <div class="bg-gray-700 rounded-xl p-4 flex items-center hover:bg-gray-600 transition">
                                <div class="w-10 text-center text-gray-400 mr-4">
                                    <?= $track['track_number'] ?? ($index + 1) ?>
                                </div>
                                <div class="flex-grow">
                                    <h4 class="font-semibold"><?= htmlspecialchars($track['name']) ?></h4>
                                    <p class="text-gray-400 text-sm"><?= htmlspecialchars($track['artists']) ?> • <?= htmlspecialchars($track['album']) ?></p>
                                </div>
                                <div class="text-gray-400 mr-4">
                                    <?= formatDuration($track['duration_ms']) ?>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="track_index" value="<?= $index ?>">
                                        <button type="submit" name="download_track" class="bg-green-600 hover:bg-green-700 w-10 h-10 rounded-full flex items-center justify-center transition">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </form>
                                    <button onclick="playPreview()" class="bg-gray-600 hover:bg-gray-500 w-10 h-10 rounded-full flex items-center justify-center transition">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 py-6 px-4 border-t border-gray-700">
            <div class="container mx-auto text-center text-gray-400">
                <p>MusicFLAC Web &copy; 2025 - High-quality FLAC downloads</p>
            </div>
        </footer>
    </div>

    <!-- Audio Player -->
    <div id="audioPlayer" class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 p-4 hidden">
        <div class="container mx-auto flex items-center">
            <div class="w-16 h-16 bg-gray-700 rounded mr-4 flex items-center justify-center">
                <i class="fas fa-music text-2xl"></i>
            </div>
            <div class="flex-grow">
                <div class="font-semibold">Preview Track</div>
                <div class="text-sm text-gray-400">0:00 / 0:30</div>
                <div class="w-full bg-gray-700 rounded-full h-2 mt-2">
                    <div class="bg-green-600 h-2 rounded-full" style="width: 0%"></div>
                </div>
            </div>
            <div class="flex gap-4">
                <button class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center hover:bg-gray-600">
                    <i class="fas fa-play"></i>
                </button>
                <button onclick="closePlayer()" class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center hover:bg-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
