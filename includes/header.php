<?php
// Build canonical + asset URLs
require_once __DIR__ . '/bootstrap.php';
$uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$canonical = ( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $uri;
$ogImage  = base_url('splash.png');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="content-language" content="it">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>MusicFLAC - Scaricatore FLAC Web</title>
    <meta name="description" content="MusicFLAC è una web app per scaricare file FLAC di alta qualità da URL Spotify: tracce singole, album o playlist. Interfaccia moderna, tema scuro.">
    <meta name="keywords" content="FLAC, Spotify, download FLAC, Tidal, Qobuz, Amazon Music, ISRC, musica lossless">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">

    <!-- Open Graph -->
    <meta property="og:locale" content="it_IT">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="MusicFLAC">
    <meta property="og:title" content="MusicFLAC — Downloader FLAC da URL Spotify">
    <meta property="og:description" content="Scarica tracce, album e playlist in FLAC lossless partendo dai link Spotify. Supporto Tidal, Qobuz e Amazon Music.">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:image:secure_url" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="MusicFLAC — Downloader FLAC da URL Spotify">
    <meta name="twitter:description" content="Scarica musica in FLAC con metadati e copertine, anche album/playlist in ZIP.">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">

    <!-- Mobile UI: barra del browser nera -->
    <meta name="theme-color" content="#000000">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="color-scheme" content="dark light">

    <!-- Favicon placeholder -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎵</text></svg>">

    <!-- Preconnect/fonts/CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/style.css">

    <!-- App settings exposed to frontend -->
    <script>
      // Base URL for building API endpoints
      window.APP_BASE = '<?= htmlspecialchars(rtrim(base_url(), "/")) ?>/';
      window.API_URL = window.APP_BASE + 'api.php';

      // Backend debug flag (controls console + debug())
      window.MF_DEBUG = <?php
        try {
            $v = (int)app_get_setting('backend_debug', 0);
            echo $v === 1 ? 'true' : 'false';
        } catch (Throwable $e) {
            echo 'false';
        }
      ?>;
      // UI debug flag (controls visibility of debug pane/button)
      window.MF_DEBUG_UI = <?php
        try {
            $v = (int)app_get_setting('frontend_debug', 0);
            echo $v === 1 ? 'true' : 'false';
        } catch (Throwable $e) {
            echo 'false';
        }
      ?>;
      // Silence console methods when not in debug mode
      (function(){
        if (!window.MF_DEBUG) {
          var noop = function(){};
          var methods = ['log','debug','info','warn','error','group','groupCollapsed','groupEnd','table','trace','time','timeEnd'];
          for (var i=0;i<methods.length;i++) {
            try { if (typeof console !== 'undefined' && console[methods[i]]) console[methods[i]] = noop; } catch(e) {}
          }
        }
      })();
    </script>

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "MusicFLAC",
      "url": "<?= htmlspecialchars(base_url()) ?>",
      "applicationCategory": "MultimediaApplication",
      "operatingSystem": "Web",
      "description": "Web app per scaricare file FLAC lossless da URL Spotify utilizzando ISRC per trovare corrispondenze su servizi come Tidal, Qobuz e Amazon Music.",
      "featureList": [
        "Download tracce, album e playlist",
        "Anteprima tracce",
        "ZIP per album/playlist",
        "Tema scuro responsive",
        "Pulizia automatica file dopo 1 ora"
      ],
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "EUR"
      },
      "inLanguage": "it-IT",
      "potentialAction": {
        "@type": "Action",
        "name": "Scarica FLAC da URL Spotify"
      }
    }
    </script>
</head>
<body class="bg-gray-900 text-white">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gray-900 py-4 px-6 shadow-lg">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold text-accent flex items-center">
                    <i class="fas fa-music mr-2"></i> MusicFLAC
                </h1>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:block">
                    <ul class="flex space-x-6">
                        <li><a href="<?= htmlspecialchars(base_url('index.php')) ?>" class="hover:text-accent transition">Home</a></li>
                        <li><a href="<?= htmlspecialchars(base_url('about.php')) ?>" class="hover:text-accent transition">Informazioni</a></li>
                        <li><a href="<?= htmlspecialchars(base_url('status.php')) ?>" class="hover:text-accent transition">Stato</a></li>
                        <?php if (user_is_admin()): ?>
                            <li><a href="<?= htmlspecialchars(base_url('admin/index.php')) ?>" class="hover:text-accent transition text-red-400"><i class="fas fa-cog mr-1"></i>Admin</a></li>
                        <?php endif; ?>
                        <?php if (user_is_logged_in()): ?>
                            <li><a href="<?= htmlspecialchars(base_url('logout.php')) ?>" class="hover:text-accent transition text-gray-400"><i class="fas fa-sign-out-alt mr-1"></i>Logout</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <!-- Mobile Burger Menu - Only visible on mobile -->
                <button class="block md:hidden burger-menu" id="burgerMenu" aria-label="Toggle menu">
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                    <span class="burger-line"></span>
                </button>
            </div>
            
            <!-- Mobile Menu - Only visible on mobile -->
            <nav class="mobile-nav hidden md:hidden" id="mobileNav">
                <ul class="flex flex-col space-y-4 mt-6 px-6 pb-6">
                    <li><a href="<?= htmlspecialchars(base_url('index.php')) ?>" class="block hover:text-accent transition py-2">Home</a></li>
                    <li><a href="<?= htmlspecialchars(base_url('about.php')) ?>" class="block hover:text-accent transition py-2">Informazioni</a></li>
                    <li><a href="<?= htmlspecialchars(base_url('status.php')) ?>" class="block hover:text-accent transition py-2">Stato</a></li>
                    <?php if (user_is_admin()): ?>
                        <li><a href="<?= htmlspecialchars(base_url('admin/index.php')) ?>" class="block hover:text-accent transition py-2 text-red-400"><i class="fas fa-cog mr-2"></i>Admin</a></li>
                    <?php endif; ?>
                    <?php if (user_is_logged_in()): ?>
                        <li><a href="<?= htmlspecialchars(base_url('logout.php')) ?>" class="block hover:text-accent transition py-2 text-gray-400"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </header>
