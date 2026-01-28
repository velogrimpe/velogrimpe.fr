<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

// Load villes for autocomplete
$villes = $mysqli->query("SELECT * FROM villes ORDER BY ville_nom")->fetch_all(MYSQLI_ASSOC);

// Load falaises for autocomplete
$falaises = $mysqli->query("SELECT falaise_id, falaise_nom FROM falaises WHERE falaise_public >= 1 ORDER BY falaise_nom")->fetch_all(MYSQLI_ASSOC);

// Load gares for autocomplete (for velo routes)
$gares = $mysqli->query("SELECT gare_id, gare_nom FROM gares WHERE deleted = 0 ORDER BY gare_nom")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Proposer une sortie - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Contrib storage -->
  <script src="/js/contrib-storage.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <?php vite_css('ajout-sortie'); ?>
</head>

<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="pb-8 px-2 md:px-8 pt-4">
    <div class="max-w-4xl mx-auto">
      <div class="mb-6">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">Proposer une sortie d'escalade</h1>
        <p class="text-base-content/70"> Remplissez le formulaire ci-dessous pour proposer une sortie d'escalade en
          mobilité douce. </p>
      </div>
      <!-- Vue Form Container -->
      <div id="vue-sortie-form" data-villes='<?= htmlspecialchars(json_encode($villes), ENT_QUOTES) ?>'
        data-falaises='<?= htmlspecialchars(json_encode($falaises), ENT_QUOTES) ?>'
        data-gares='<?= htmlspecialchars(json_encode($gares), ENT_QUOTES) ?>'>
      </div>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <!-- Load Vue app -->
  <?php vite_js('ajout-sortie'); ?>
</body>

</html>