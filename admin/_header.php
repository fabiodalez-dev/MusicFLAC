<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<?php require_once __DIR__ . '/../includes/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — MusicFLAC</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    body { background: #0b0c0d; }
    .card { background:#0f172a; border:1px solid #1f2937; }
  </style>
</head>
<body class="text-white">
  <header class="px-6 py-4 border-b border-gray-800">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <a href="<?= htmlspecialchars(base_url('admin/index.php')) ?>" class="text-xl font-semibold text-accent">MusicFLAC — Admin</a>
      <nav class="hidden md:flex items-center space-x-4 text-sm">
        <a class="hover:text-accent" href="<?= htmlspecialchars(base_url('index.php')) ?>">App Principale</a>
        <a class="hover:text-accent" href="<?= htmlspecialchars(base_url('admin/users.php')) ?>">Utenti</a>
        <a class="hover:text-accent" href="<?= htmlspecialchars(base_url('admin/settings.php')) ?>">Impostazioni</a>
        <a class="hover:text-accent" href="<?= htmlspecialchars(base_url('admin/services.php')) ?>">Servizi</a>
        
        <a class="hover:text-accent" href="<?= htmlspecialchars(base_url('admin/stats.php')) ?>">Statistiche</a>
        <a class="hover:text-red-400" href="<?= htmlspecialchars(base_url('admin/logout.php')) ?>">Esci</a>
      </nav>
      <button class="md:hidden" id="adminBurger" aria-label="Apri menu">
        <span class="block w-6 h-0.5 bg-gray-300 mb-1"></span>
        <span class="block w-6 h-0.5 bg-gray-300 mb-1"></span>
        <span class="block w-6 h-0.5 bg-gray-300"></span>
      </button>
    </div>
    <nav id="adminMobileNav" class="md:hidden hidden">
      <div class="max-w-6xl mx-auto py-3 space-y-2 text-sm">
        <a class="block px-2 hover:text-accent" href="<?= htmlspecialchars(base_url('index.php')) ?>">App Principale</a>
        <a class="block px-2 hover:text-accent" href="<?= htmlspecialchars(base_url('admin/index.php')) ?>">Dashboard</a>
        <a class="block px-2 hover:text-accent" href="<?= htmlspecialchars(base_url('admin/users.php')) ?>">Utenti</a>
        <a class="block px-2 hover:text-accent" href="<?= htmlspecialchars(base_url('admin/settings.php')) ?>">Impostazioni</a>
        <a class="block px-2 hover:text-accent" href="<?= htmlspecialchars(base_url('admin/services.php')) ?>">Servizi</a>
        
        <a class="block px-2 hover:text-accent" href="<?= htmlspecialchars(base_url('admin/stats.php')) ?>">Statistiche</a>
        <a class="block px-2 hover:text-red-400" href="<?= htmlspecialchars(base_url('admin/logout.php')) ?>">Esci</a>
      </div>
    </nav>
  </header>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      var b=document.getElementById('adminBurger'); var n=document.getElementById('adminMobileNav');
      if(b&&n){ b.addEventListener('click', function(){ n.classList.toggle('hidden'); }); }
    });
  </script>
