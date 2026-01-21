<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php'; ?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://velogrimpe.fr/" />
  <meta name="description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Velogrimpe.fr - Carte des falaises accessibles en vélo et train">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title"
    content="<?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <?php vite_css('main'); ?>
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
    <!-- <div
      class="bg-base-100 --bg-[#e9f5ec] --border-solid --border-l-4 --border-l-primary p-6 text-center m-5 shadow-xs rounded-md">
      <span class="font-bold text-lg text-primary">VÉLOGRIMPE :</span>
      <br />
      Activité consistant à combiner train et vélo pour aller
      grimper en falaise. En plus de privilégier une mobilité douce, le vélogrimpe donne
      l'occasion de vivre de petites aventures.
      <br />
      Synonyme : escaladopédalage.
    </div> -->
    <div class="hero min-h-125 md:min-h-160" style="background-image: url(/images/mw/027-velo-aiguille-40.webp);">
      <div class="hero-overlay bg-slate-600/70"></div>
      <div class="hero-content text-center">
        <div class="max-w-md">
          <h1 class="text-5xl font-bold text-base-100">Vélogrimpe</h1>
          <p class="py-6 text-base-100 italic"> Activité consistant à combiner train et vélo pour aller grimper en
            falaise. En plus de privilégier une mobilité douce, le vélogrimpe donne l'occasion de vivre de petites
            aventures. Synonyme : escaladopédalage. </p>
          <a class="btn" href="/carte.php">C'est parti !</a>
        </div>
      </div>
    </div>
    <div class="hero bg-base-200 min-h-125">
      <div class="hero-content flex-col sm:flex-row-reverse text-center sm:text-left">
        <a class="flex max-w-60 rounded-lg shadow-2xl" href="/carte.php">
          <img class="max-w-60 rounded-lg shadow-2xl" src="/images/captures/saou-vert-60.webp" />
        </a>
        <div class="max-w-xl">
          <h2 class="text-3xl font-bold">Carte des sites d'escalade accessibles en train & vélo</h2>
          <p class="py-6"> Velogrimpe.fr recense les sites d'escalade en extérieur (couenne, bloc, grandes voies, ...)
            accessibles en train + vélo à partir des grandes villes de France. <br />
            <br /> Les accès en train et vélo sont détaillés pour chaque falaise, il n'y a plus qu'à réserver les
            billets de train et préparer les sacoches !
          </p>
          <a class="btn btn-primary" href="/carte.php">Voir la carte</a>
        </div>
      </div>
    </div>
    <div class="hero min-h-125 md:min-h-160" style="background-image: url(/images/mw/040-train-20.webp);">
      <div class="hero-overlay bg-slate-600/70"></div>
      <div class="hero-content text-center text-base-100">
        <div class="max-w-md">
          <h2 class="text-3xl font-bold">Un topo collaboratif !</h2>
          <p class="py-6"> Les falaises et leurs accès vélo+train sont renseignés par la communauté. Tu connais bien un
            secteur ? Tu veux partager ton expérience vélo-grimpe ? C'est par ici ! </p>
          <a class="btn" href="/contribuer.php">Contribuer</a>
        </div>
      </div>
    </div>
    <div class="hero bg-base-200 min-h-125">
      <div class="hero-content flex-col sm:flex-row text-center sm:text-left">
        <img src="/images/captures/signal-2-40.webp" class="max-w-60 rounded-lg shadow-2xl" />
        <div class="max-w-xl">
          <h2 class="text-3xl font-bold">Une communauté pour partager l'expérience vélogrimpe</h2>
          <p class="py-6"> Tu cherches des partenaires pour aller vélogrimper à plusieurs ? Nous avons un groupe sur
            Signal dans lequel chacun partage ses propositions de sorties et son expérience. </p>
          <a class="btn btn-primary" href="/communaute.php">Nous rejoindre</a>
        </div>
      </div>
    </div>
    <div class="hero min-h-125 md:min-h-160" style="background-image: url(/images/mw/078-groupe-5.webp);">
      <div class="hero-overlay bg-slate-600/70"></div>
      <div class="hero-content text-center text-base-100">
        <div class="max-w-md">
          <h2 class="text-3xl font-bold">Une initiative open-source</h2>
          <p class="py-6"> Si tu as envie d'aider à améliorer le site et ses fonctionalités, le code est en open-source,
            tu peux nous aider en faisant remonter les bugs ou en proposant tes modifications. </p>
          <a class="btn" target="_blank" href="https://github.com/velogrimpe/velogrimpe.fr">Accéder au code</a>
        </div>
      </div>
    </div>
  </main>
  <?php include "./components/footer.html"; ?>
</body>

</html>