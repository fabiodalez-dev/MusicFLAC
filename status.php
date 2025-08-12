<?php
require_once 'includes/config.php';
require_once 'includes/app.php';
require_once 'includes/auth.php';
require_frontend_auth();
include 'includes/header.php';
?>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-10">
                    <h1 class="text-4xl font-bold mb-4">Service Status</h1>
                    <p class="text-xl text-gray-400">Current status of all supported music services</p>
                </div>

                <div class="bg-gray-800 rounded-2xl p-6 mb-8 shadow-xl backdrop-blur-lg bg-opacity-70 border border-gray-700">
                    <h2 class="text-2xl font-bold mb-6">Service Status</h2>
                    <div class="space-y-4">
                        <?php foreach (SUPPORTED_SERVICES as $key => $name): ?>
                        <div class="bg-gray-700 rounded-xl p-4 flex items-center">
                            <div class="w-12 h-12 rounded-full bg-accent flex items-center justify-center mr-4">
                                <i class="fas fa-check text-white"></i>
                            </div>
                            <div class="flex-grow">
                                <h3 class="text-lg font-semibold"><?= htmlspecialchars($name) ?></h3>
                                <p class="text-gray-400">Operational</p>
                            </div>
                            <div class="text-accent font-semibold">
                                <i class="fas fa-circle mr-2"></i> Online
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-gray-800 rounded-2xl p-6 shadow-xl backdrop-blur-lg bg-opacity-70 border border-gray-700">
                    <h2 class="text-2xl font-bold mb-6">System Information</h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="bg-gray-700 p-5 rounded-xl">
                            <h3 class="text-xl font-semibold mb-3">Application</h3>
                            <ul class="space-y-2 text-gray-300">
                                <li class="flex justify-between">
                                    <span>Version</span>
                                    <span>1.0.0</span>
                                </li>
                                <li class="flex justify-between">
                                    <span>Status</span>
                                    <span class="text-green-500">Operational</span>
                                </li>
                                <li class="flex justify-between">
                                    <span>Last Updated</span>
                                    <span><?= date('Y-m-d H:i:s') ?></span>
                                </li>
                            </ul>
                        </div>
                        <div class="bg-gray-700 p-5 rounded-xl">
                            <h3 class="text-xl font-semibold mb-3">Storage</h3>
                            <ul class="space-y-2 text-gray-300">
                                <li class="flex justify-between">
                                    <span>Download Directory</span>
                                    <span><?= is_writable(DOWNLOAD_DIR) ? 'Writable' : 'Not Writable' ?></span>
                                </li>
                                <li class="flex justify-between">
                                    <span>Cache Directory</span>
                                    <span><?= is_writable(CACHE_DIR) ? 'Writable' : 'Not Writable' ?></span>
                                </li>
                                <li class="flex justify-between">
                                    <span>Free Space</span>
                                    <span><?= round(disk_free_space('.') / (1024 * 1024 * 1024), 2) ?> GB</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>

<?php include 'includes/footer.php'; ?>
