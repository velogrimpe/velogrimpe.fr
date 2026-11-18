<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

// Compteurs : chaque carte renvoie vers la liste correspondante, déjà filtrée.
$count = fn(string $sql): int => (int) $mysqli->query($sql)->fetch_row()[0];
$cards = [
  ['Falaises à vérifier', $count("SELECT COUNT(*) FROM falaises WHERE falaise_public = 2"), '/admin/falaises.php?statut=verifier', 'warning'],
  ['Accès à vérifier', $count("SELECT COUNT(*) FROM acces_falaise WHERE public = 2"), '/admin/acces.php?statut=verifier', 'warning'],
  ['Falaises hors topo', $count("SELECT COUNT(*) FROM falaises WHERE falaise_public = 3"), '/admin/falaises.php?statut=hors_topo', 'neutral'],
  ['Falaises du topo', $count("SELECT COUNT(*) FROM falaises WHERE falaise_public = 1"), '/admin/falaises.php?statut=topo', 'success'],
  ['Accès (vélo et à pied)', $count("SELECT COUNT(*) FROM acces_falaise"), '/admin/acces.php', 'primary'],
  ['Arrêts actifs', $count("SELECT COUNT(*) FROM arrets WHERE deleted = 0"), '/admin/arrets_lignes.php', 'primary'],
];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Panneau d'administration - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="flex flex-col min-h-screen">
  <?php $admin_active = 'dashboard'; include $_SERVER['DOCUMENT_ROOT'] . "/components/admin-layout-open.php"; ?>
  <main class="w-full grow max-w-(--breakpoint-lg) mx-auto p-4 flex flex-col gap-6">
    <h1 class="text-2xl font-bold"><span class="text-red-900">PANNEAU D'ADMINISTRATION</span></h1>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
      <?php foreach ($cards as [$label, $n, $href, $tone]): ?>
        <a href="<?= htmlspecialchars($href) ?>" class="card bg-base-100 shadow hover:shadow-md transition-shadow">
          <div class="card-body p-4 items-center text-center">
            <span class="text-4xl font-bold <?= $tone === 'warning' && $n > 0 ? 'text-warning' : '' ?>"><?= $n ?></span>
            <span class="text-sm"><?= htmlspecialchars($label) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/admin-layout-close.php"; ?>
</body>

</html>
