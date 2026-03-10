<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php'; ?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Page introuvable - Velogrimpe.fr</title>
  <meta name="robots" content="noindex">
  <?php vite_css('main'); ?>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="min-h-screen flex flex-col">
  <?php include "./components/header.html"; ?>
  <div class="flex-1 flex items-center justify-center p-4">
    <div class="text-center max-w-md">
      <h1 class="text-8xl font-bold text-primary">404</h1>
      <p class="text-2xl font-bold mt-4">Page introuvable</p>
      <p class="text-base-content/70 mt-2">La page que vous cherchez n'existe pas ou a été déplacée.</p>
      <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
        <a href="/" class="btn btn-primary">Retour à l'accueil</a>
        <a href="/carte.php" class="btn btn-outline btn-primary">Voir la carte</a>
      </div>
    </div>
  </div>
  <?php include "./components/footer.php"; ?>
</body>

</html>
