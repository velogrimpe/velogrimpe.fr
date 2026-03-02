<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php'; ?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description"
    content="Conditions d'emport des vélos dans les trains en France : TGV, Intercités, TER régionaux. Règles pour vélo démonté et non démonté.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Conditions d'emport vélo en train - Velogrimpe.fr">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/logistique/conditions-emport-velo-train.php">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Conditions d'emport des vélos dans les trains en France : TGV, Intercités, TER régionaux.">
  <title>Conditions d'emport vélo en train - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <?php vite_css('emport'); ?>
</head>

<body class="flex flex-col min-h-screen">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>

  <main class="grow max-w-(--breakpoint-xl) mx-auto p-4 pb-8 w-full">
    <h1 class="text-3xl font-bold mb-2">Conditions d'emport des vélos en train</h1>
    <p class="text-base-content/70 mb-6">
      Règles par type de train et par compagnie/région. Données issues des sources officielles des opérateurs.
    </p>

    <div id="vue-emport"></div>
  </main>

  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <?php vite_js('emport'); ?>
</body>

</html>
