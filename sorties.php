<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

// Load villes for filter
$villes = $mysqli->query("SELECT * FROM villes ORDER BY ville_nom")->fetch_all(MYSQLI_ASSOC);

$meta_title = "Sorties - Vélogrimpe.fr";
$meta_description = "Proposez ou rejoignez des sorties d'escalade en mobilité douce. Trouvez votre binôme pour la journée ou un groupe pour partir à la semaine.";

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($meta_title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://velogrimpe.fr/sorties.php" />
  <meta name="description" content="<?= htmlspecialchars($meta_description) ?>">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="<?= htmlspecialchars($meta_title) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/sorties.php">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description" content="<?= htmlspecialchars($meta_description) ?>">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title" content="<?= htmlspecialchars($meta_title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($meta_description) ?>">
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <!-- Vue Component Styles -->
  <?php vite_css('sorties'); ?>
</head>

<body>
  <?php include "./components/header.html"; ?>
  <div class="drawer drawer-end" id="sortie-drawer-root">
    <input id="sortie-drawer" type="checkbox" class="drawer-toggle" />
    <div class="drawer-content">
      <main class="pb-8 px-2 md:px-8 pt-4">
        <div class="max-w-7xl mx-auto">
          <div class="mb-6 md:flex md:justify-between md:items-start">
            <div>
              <h1 class="text-3xl md:text-4xl font-bold mb-2">Sorties</h1>
              <p class="text-base-content/70">Proposez ou rejoignez des sorties en vélogrimpe !</p>
            </div>
            <div class="mt-4 flex justify-end">
              <a href="/ajout/ajout_sortie.php" class="btn btn-primary"> Proposer une sortie </a>
            </div>
          </div>
          <!-- Vue App Container -->
          <div id="vue-sorties" data-villes='<?= htmlspecialchars(json_encode($villes), ENT_QUOTES) ?>'>
          </div>
        </div>
      </main>
      <!-- Drawer side will be teleported here by Vue -->
    </div>
    <div class="drawer-side z-50">
      <label for="sortie-drawer" class="drawer-overlay" aria-label="close sidebar"></label>
      <div class="pt-16 relative h-full bg-base-100">
        <!-- Close button -->
        <button class="btn btn-sm btn-circle btn-ghost absolute right-4 top-20 z-10"
          onclick="document.getElementById('sortie-drawer').checked=false;">✕</button>
        <div id="drawer-side-target"></div>
      </div>
    </div>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <!-- Load Vue app -->
  <?php vite_js('sorties'); ?>
</body>

</html>