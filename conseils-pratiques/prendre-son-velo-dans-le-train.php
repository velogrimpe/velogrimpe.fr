<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$title = 'Conseils pratiques vélo - Velogrimpe.fr';
$description = 'Conseils pratiques pour le vélogrimpe : comment équiper son vélo, que faut-il penser à prendre ?';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= htmlspecialchars($description) ?>">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/0040-train-social-40.webp">
  <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/0040-train-social-40.webp">
  <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
  <title><?= htmlspecialchars($title) ?></title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <?php vite_css('emport'); ?>
</head>

<body class="flex flex-col min-h-screen">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <div class="hero min-h-100 bg-center" style="background-image: url(/images/mw/040-train-20.webp);">
    <div class="hero-overlay bg-slate-600/70"></div>
    <div class="hero-content text-center text-base-100">
      <div class="max-w-md">
        <h1 class="text-5xl font-bold">Conseils pratiques : prendre son vélo dans le train</h1>
      </div>
    </div>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/nav-conseils-pratiques.php"; ?>
  <main class="grow max-w-(--breakpoint-lg) mx-auto p-4 pb-8 w-full prose">
    <p>Prendre son vélo dans un train, c'est possible, et ça a même un nom savant : l'intermodalité (wahou !). </p>
    <p>Parfois, il faut démonter son vélo. Parfois, faut réserver... Et les règles varient beaucoup suivant les régions,
      les compagnies et les années ! Alors comment savoir ??</p>
    <p>C'est là qu'arrive Jean-Luc Levoux, du projet <a href="https://cartotrain.fr">Cartotrain</a> : grâce aux
      informations qu'il a patiemment collectées, nous pouvons répondre à cette question ! </p>
    <div id="vue-emport"></div>
    <p>Quelques conseils supplémentaires : </p>
    <ul>
      <li>De nombreux trains ont des wagons prévus pour accrocher les vélos, qui sont généralement indiqués par des
        autocollants de vélos sur les portes correspondantes. On trouve souvent des crochets pour la roue avant, afin de
        suspendre verticalement le vélo. Parfois, c’est un espace dans lequel on peut appuyer quelques vélos les uns
        contre les autres. Dans d’autres cas, rien n’est prévu, ou alors tous les emplacements sont déjà pris, et avoir
        déjà joué à Tetris devient alors un avantage certain.</li>
      <li>Il est conseillé d’enlever les sacoches avant de monter dans le train, et de les porter en bandoulière, c’est
        plus pratique.</li>
    </ul>
    <h2>Détails des conditions d'emport du vélo dans les différents types de trains</h2>
    <p class="text-base-content/70 mb-6"> Règles par type de train et par compagnie/région. Données issues des sources
      officielles des opérateurs. </p>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
  <?php vite_js('emport'); ?>
</body>

</html>