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
                <div class="text-center mb-12">
                    <h1 class="text-4xl font-bold mb-4">Informazioni su MusicFLAC</h1>
                    <p class="text-xl text-gray-400">Download FLAC di alta qualità da link Spotify</p>
                </div>

                <div class="bg-gray-900 rounded-2xl p-8 mb-8 shadow-xl backdrop-blur-lg bg-opacity-70 border border-gray-700">
                    <h2 class="text-2xl font-bold mb-6 text-green-500">Cos’è MusicFLAC?</h2>
                    <p class="text-gray-300 mb-6">
                        MusicFLAC è un’applicazione web che ti permette di scaricare file musicali FLAC (Free Lossless Audio Codec) 
                        di alta qualità partendo da URL di Spotify. A differenza del formato di streaming di Spotify, il FLAC offre qualità audio senza perdita, 
                        preservando tutti i dati audio originali.
                    </p>
                    
                    <h2 class="text-2xl font-bold mb-6 text-green-500">Come Funziona</h2>
                    <div class="grid md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gray-700 p-6 rounded-xl">
                            <div class="text-accent text-3xl mb-4">
                                <i class="fas fa-link"></i>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">1. Recupera i Metadati</h3>
                            <p class="text-gray-300">
                                Incolla qualsiasi URL di Spotify (brano, album o playlist) per ottenere metadati dettagliati, inclusi i codici ISRC.
                            </p>
                        </div>
                        <div class="bg-gray-700 p-6 rounded-xl">
                            <div class="text-accent text-3xl mb-4">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">2. Trova le Corrispondenze</h3>
                            <p class="text-gray-300">
                                Il nostro sistema utilizza i codici ISRC per trovare corrispondenze esatte su servizi musicali di alta qualità come Tidal, Qobuz e altri.
                            </p>
                        </div>
                        <div class="bg-gray-700 p-6 rounded-xl">
                            <div class="text-accent text-3xl mb-4">
                                <i class="fas fa-download"></i>
                            </div>
                            <h3 class="text-xl font-semibold mb-3">3. Scarica in FLAC</h3>
                            <p class="text-gray-300">
                                Scarica file FLAC senza perdita di qualità con metadati e copertina incorporati, oppure interi album/playlist in file ZIP.
                            </p>
                        </div>
                    </div>
                    
                    <h2 class="text-2xl font-bold mb-6 text-green-500">Servizi Supportati</h2>
                    <div class="grid md:grid-cols-2 gap-6 mb-8">
                        <div class="flex items-start">
                            <div class="bg-gray-700 p-3 rounded-lg mr-4">
                                <i class="fas fa-wave-square text-accent"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold mb-2">Tidal</h3>
                                <p class="text-gray-300">
                                    Audio lossless di alta qualità con mastering MQA per un suono eccezionale.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="bg-gray-700 p-3 rounded-lg mr-4">
                                <i class="fas fa-mountain text-accent"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold mb-2">Qobuz</h3>
                                <p class="text-gray-300">
                                    Audio in alta risoluzione fino a 24-bit/192kHz con registrazioni di qualità da studio.
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="bg-gray-700 p-3 rounded-lg mr-4">
                                <i class="fas fa-amazon text-accent"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold mb-2">Amazon Music</h3>
                                <p class="text-gray-300">
                                    Streaming audio di alta qualità con contenuti esclusivi e opzioni HD.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <h2 class="text-2xl font-bold mb-6 text-green-500">Perché Usare MusicFLAC?</h2>
                    <ul class="space-y-4 text-gray-300">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-accent mt-1 mr-3"></i>
                            <span><strong>Qualità Lossless:</strong> Scarica file FLAC senza compressione o perdita di qualità</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-accent mt-1 mr-3"></i>
                            <span><strong>Metadati Completi:</strong> Tutti i file includono copertina, info artista e dettagli traccia incorporati</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-accent mt-1 mr-3"></i>
                            <span><strong>Facile da Usare:</strong> Interfaccia semplice, basta un URL di Spotify</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-accent mt-1 mr-3"></i>
                            <span><strong>Servizi Multipli:</strong> Scegli tra diversi servizi musicali di alta qualità</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-accent mt-1 mr-3"></i>
                            <span><strong>Download Organizzati:</strong> Album e playlist organizzati automaticamente in file ZIP</span>
                        </li>
                    </ul>
                </div>
                
                <div class="bg-gray-900 rounded-2xl p-8 shadow-xl backdrop-blur-lg bg-opacity-70 border border-gray-700">
                    <h2 class="text-2xl font-bold mb-6 text-green-500">Avviso Legale</h2>
                    <p class="text-gray-300 mb-4">
                        MusicFLAC è pensato solo per scopi educativi. Gli utenti sono responsabili di assicurarsi di avere il diritto 
                        di scaricare e utilizzare qualsiasi file musicale. Questo servizio non ospita né distribuisce contenuti protetti da copyright.
                    </p>
                    <p class="text-gray-300">
                        Si prega di rispettare i diritti di proprietà intellettuale di artisti ed etichette discografiche. Questo strumento dovrebbe essere utilizzato 
                        solo per musica che hai acquistato legalmente o per la quale hai un’autorizzazione esplicita al download.
                    </p>
                </div>
            </div>
        </main>

<?php include 'includes/footer.php'; ?>
