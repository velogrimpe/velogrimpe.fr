<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter des données (admin)</title>
  <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png" />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="min-h-screen">
  <div class="max-w-screen-md mx-auto p-10 flex flex-col gap-8">
    <h1 class="text-4xl font-bold text-wrap text-center">
      AJOUTER DES DONNÉES <span class="text-red-900">(ADMIN)</span>
    </h1>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_ville.php?admin=<?= $token ?>">Ajouter une ville de
        départ</a>
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_zone.php?admin=<?= $token ?>">Ajouter une zone</a>
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_falaise.php?admin=<?= $token ?>">Ajouter une falaise</a>
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_train.php?admin=<?= $token ?>">Ajouter un itinéraire
        train (ville - gare)</a>
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_velo.php?admin=<?= $token ?>">Ajouter un itinéraire vélo
        (gare - falaise)</a>
    </div>
  </div>
</body>

</html>