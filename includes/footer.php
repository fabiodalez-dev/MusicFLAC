        <!-- Footer -->
        <footer class="bg-gray-900 py-6 px-4 border-t border-gray-700">
            <div class="container mx-auto text-center text-gray-400">
                <p>MusicFLAC Web &copy; 2025 - Download FLAC di alta qualità</p>
            </div>
        </footer>
    </div>

    <!-- Error Modal -->
    <?php if (isset($error)): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-gray-900 rounded-2xl p-6 max-w-md w-full mx-4 border border-gray-700">
            <div class="text-red-500 text-2xl mb-4">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            </div>
            <h3 class="text-xl font-bold mb-2">Errore</h3>
            <p class="text-gray-300 mb-6"><?= htmlspecialchars($error) ?></p>
            <button onclick="this.parentElement.parentElement.remove()" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg w-full font-semibold transition" aria-label="Chiudi errore">
                Chiudi
            </button>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
    <script src="js/main.js?v=<?php echo urlencode(filemtime(__DIR__ . '/../js/main.js')); ?>"></script>
    <script src="js/ux.js?v=<?php echo file_exists(__DIR__ . '/../js/ux.js') ? urlencode(filemtime(__DIR__ . '/../js/ux.js')) : time(); ?>"></script>
</body>
</html>
