<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Vélogrimpe.fr - Actualités</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://velogrimpe.fr/actualites/" />
  <meta name="description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Velogrimpe.fr - Actualités">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/actualites/">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title"
    content="<?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="./index.css" />
  <link rel="manifest" href="./site.webmanifest" />
</head>

<body>
  <?php include "./components/header.html"; ?>
  <main class="pb-4">
    <div class="hero min-h-[400px] bg-bottom" style="background-image: url(/images/mw/078-groupe-5.webp);">
      <div class="hero-overlay bg-opacity-60"></div>
      <div class="hero-content text-center text-base-100">
        <div class="max-w-md">
          <h1 class="text-5xl font-bold">Actualités</h1>
        </div>
      </div>
    </div>
  </main>
  <?php include "./components/footer.html"; ?>
</body>

</html>