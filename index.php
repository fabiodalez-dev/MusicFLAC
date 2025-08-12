<?php
require_once __DIR__ . '/includes/bootstrap.php';
// Check installation via helper
if (!app_is_installed()) {
    header('Location: installer/install.php');
    exit;
}

@ini_set('display_errors', '0');
error_reporting(E_ALL);
debug_log('index_page_load');

// Global frontend auth
require_frontend_auth();

// Cleanup old downloads on each request
if (function_exists('cleanup_old_downloads')) {
    cleanup_old_downloads();
}

// Include header
include 'includes/header.php';
?>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8 fade-in">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-10">
                    <h2 class="text-4xl font-bold mb-4">MusicFLAC</h2>
                    <p class="text-gray-400 text-lg">Inserisci un URL Spotify, scegli il servizio e recupera i brani in FLAC</p>
                </div>

                <!-- Search Form -->
                <div class="bg-gray-900 rounded-2xl p-6 mb-8 shadow-xl backdrop-blur-lg bg-opacity-70 border border-gray-700">
                    <form id="fetchForm" class="space-y-6" aria-label="Form di recupero tracce da Spotify">
                        <div>
                            <label for="spotify_url" class="block text-lg font-medium mb-2">URL Spotify</label>
                            <!-- Desktop Layout -->
                            <div class="hidden md:flex">
                                <input 
                                    type="url" 
                                    id="spotify_url" 
                                    name="spotify_url" 
                                    placeholder="https://open.spotify.com/track/..." 
                                    class="flex-grow px-4 py-3 bg-gray-700 border border-gray-600 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    autocomplete="off"
                                    inputmode="url"
                                >
                                <button 
                                    type="submit" 
                                    class="animated-btn rounded-r-lg rounded-l-none"
                                    aria-label="Recupera metadati"
                                >
                                    <span><i class="fas fa-search mr-2" aria-hidden="true"></i> Recupera</span>
                                </button>
                            </div>
                            <!-- Mobile Layout -->
                            <div class="md:hidden space-y-4">
                                <input 
                                    type="url" 
                                    id="spotify_url_mobile" 
                                    name="spotify_url_mobile" 
                                    placeholder="https://open.spotify.com/track/..." 
                                    class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                    autocomplete="off"
                                    inputmode="url"
                                >
                                <button 
                                    type="submit" 
                                    class="animated-btn w-full rounded-lg"
                                    aria-label="Recupera metadati"
                                >
                                    <span><i class="fas fa-search mr-2" aria-hidden="true"></i> Recupera</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <span class="block text-lg font-medium mb-2">Servizio di download</span>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 services-grid-mobile">
                                <?php 
                                $services = app_list_services();
                                $order = ['tidal','amazon','qobuz'];
                                $icons = [
                                    'tidal' => 'fa-solid fa-wave-square',
                                    'amazon' => 'fa-brands fa-amazon',
                                    'qobuz' => 'fa-solid fa-music',
                                ];
                                $firstChecked = false;
                                foreach ($order as $key):
                                    if (!isset($services[$key]) || !$services[$key]['enabled']) continue;
                                    $label = htmlspecialchars($services[$key]['label']);
                                    $checked = $firstChecked ? '' : 'checked';
                                    $firstChecked = true;
                                ?>
                                <label class="service-label cursor-pointer">
                                    <input type="radio" name="service" value="<?= htmlspecialchars($key) ?>" class="hidden" <?= $checked ?>>
                                    <div class="service-content">
                                        <i class="<?= $icons[$key] ?> service-icon text-accent"></i>
                                        <span><?= $label ?></span>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Results Section (dynamic) -->
                <div id="results" class="hidden"></div>

                <!-- Info Section -->
                <div class="bg-gray-900 rounded-2xl p-6 shadow-xl backdrop-blur-lg bg-opacity-70 border border-gray-700">
                    <h3 class="text-2xl font-bold mb-4">Come Funziona</h3>
                    <div class="grid md:grid-cols-3 gap-6">
                        <div class="bg-gray-700 p-5 rounded-xl">
                            <div class="text-accent text-2xl mb-3">
                                <i class="fas fa-link" aria-hidden="true"></i>
                            </div>
                            <h4 class="text-xl font-semibold mb-2">1. Incolla Link</h4>
                            <p class="text-gray-300">Copia e incolla qualsiasi URL di traccia, album o playlist Spotify</p>
                        </div>
                        <div class="bg-gray-700 p-5 rounded-xl">
                            <div class="text-accent text-2xl mb-3">
                                <i class="fas fa-download" aria-hidden="true"></i>
                            </div>
                            <h4 class="text-xl font-semibold mb-2">2. Scarica</h4>
                            <p class="text-gray-300">Scegli il tuo servizio preferito e scarica in FLAC ad alta qualità</p>
                        </div>
                        <div class="bg-gray-700 p-5 rounded-xl">
                            <div class="text-accent text-2xl mb-3">
                                <i class="fas fa-headphones" aria-hidden="true"></i>
                            </div>
                            <h4 class="text-xl font-semibold mb-2">3. Ascolta</h4>
                            <p class="text-gray-300">Goditi la tua musica con una qualità audio impeccabile</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

<?php include 'includes/footer.php'; ?>
