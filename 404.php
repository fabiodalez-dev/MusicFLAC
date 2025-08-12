<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - MusicFLAC</title>
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
        <main class="flex-grow flex items-center justify-center">
            <div class="text-center max-w-2xl px-4">
                <div class="text-6xl text-accent mb-6">
                    <i class="fas fa-music"></i>
                </div>
                <h1 class="text-5xl font-bold mb-6">404</h1>
                <h2 class="text-3xl font-semibold mb-6">Page Not Found</h2>
                <p class="text-xl text-gray-400 mb-10">
                    Sorry, the page you're looking for doesn't exist or has been moved.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="index.php" class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold transition flex items-center justify-center">
                        <i class="fas fa-home mr-2"></i> Go Home
                    </a>
                    <a href="about.php" class="bg-gray-700 hover:bg-gray-600 px-6 py-3 rounded-lg font-semibold transition flex items-center justify-center">
                        <i class="fas fa-info-circle mr-2"></i> About MusicFLAC
                    </a>
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
</body>
</html>
