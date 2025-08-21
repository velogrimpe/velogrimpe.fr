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

  <script src=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js "></script>
  <link href=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css " rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/2.1.2/gpx.min.js" defer></script>
  <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
  <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css'
    rel='stylesheet' />
  <!-- Carte : locate -->
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.84.2/dist/L.Control.Locate.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.84.2/dist/L.Control.Locate.min.js"
    charset="utf-8"></script>
  <script src="https://unpkg.com/protomaps-leaflet@5.0.1/dist/protomaps-leaflet.js"></script>
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
    <!-- <div
      class="bg-base-100 --bg-[#e9f5ec] --border-solid --border-l-4 --border-l-primary p-6 text-center m-5 shadow-sm rounded-md">
      <span class="font-bold text-lg text-primary">VÉLOGRIMPE :</span>
      <br />
      Activité consistant à combiner train et vélo pour aller
      grimper en falaise. En plus de privilégier une mobilité douce, le vélogrimpe donne
      l'occasion de vivre de petites aventures.
      <br />
      Synonyme : escaladopédalage.
    </div> -->
    <div class="hero min-h-[500px] md:min-h-[640px]"
      style="background-image: url(/images/mw/027-velo-aiguille-40.webp);">
      <div class="hero-overlay bg-opacity-60"></div>
      <div class="hero-content text-center">
        <div class="max-w-md">
          <h1 class="text-5xl font-bold text-base-100">Vélogrimpe</h1>
          <p class="py-6 text-base-100 italic">
            Activité consistant à combiner train et vélo pour aller
            grimper en falaise. En plus de privilégier une mobilité douce, le vélogrimpe donne
            l'occasion de vivre de petites aventures. Synonyme : escaladopédalage.
          </p>
          <a class="btn" href="/carte.php">C'est parti !</a>
        </div>
      </div>
    </div>

    <div class="hero bg-base-200 min-h-[500px]">
      <div class="hero-content flex-col sm:flex-row-reverse">
        <img src="/images/captures/saou-vert-60.webp" class="max-w-[240px] rounded-lg shadow-2xl" />
        <div class="max-w-xl">
          <h2 class="text-3xl font-bold">Tous les sites d'escalade accessibles à vélo</h2>
          <p class="py-6">
            Velogrimpe.fr recense les sites d'escalade en extérieur (sportive, bloc, grandes voies, ...)
            accessible en train + vélo à partir des grandes villes de France.
            <br />
            <br />
            Chaque accès en train et en vélo est détaillé pour chaque falaise, il n'y a plus qu'à réserver les billets
            de train et préparer les saccoches.
          </p>
          <a class="btn btn-primary" href="/carte.php">Voir la carte</a>
        </div>
      </div>
    </div>

    <div class="hero min-h-[500px] md:min-h-[640px]" style="background-image: url(/images/mw/040-train-20.webp);">
      <div class="hero-overlay bg-opacity-60"></div>
      <div class="hero-content text-center text-base-100">
        <div class="max-w-md">
          <h2 class="text-3xl font-bold">Un topo collaboratif !</h2>
          <p class="py-6">
            Les falaises et leurs accès vélo+trains sont renseignées par la communauté. Tu connais bien un secteur ? Tu
            veux partager ton expérience vélo-grimpe ? C'est par ici !
          </p>
          <a class="btn" href="/contribuer.php">Contribuer</a>
        </div>
      </div>
    </div>

    <div class="hero bg-base-200 min-h-[500px]">
      <div class="hero-content flex-col sm:flex-row">
        <img src="/images/captures/signal-2-40.webp" class="max-w-[240px] rounded-lg shadow-2xl" />
        <div class="max-w-xl">
          <h2 class="text-3xl font-bold">Une communauté pour partager l'expérience vélogrimpe</h2>
          <p class="py-6">
            Tu cherches des partenaires pour aller vélogrimper à plusieurs ? Nous avons un groupe sur Signal dans lequel
            chacun partage ses propositions de sorties et son expérience.
          </p>
          <a class="btn btn-primary" href="/carte.php">Nous rejoindre</a>
        </div>
      </div>
    </div>

    <div class="hero min-h-[500px] md:min-h-[640px]" style="background-image: url(/images/mw/078-groupe-5.webp);">
      <div class="hero-overlay bg-opacity-60"></div>
      <div class="hero-content text-center text-base-100">
        <div class="max-w-md">
          <h2 class="text-3xl font-bold">Une initiative open-source</h2>
          <p class="py-6">
            Envie d'aider à améliorer le site et ses fonctionalités, le site est en open-source, tu peux nous aider en
            faisant remonter les bugs ou en proposant tes modifications.
          </p>
          <a class="btn" href="/carte.php">Accéder au code</a>
        </div>
      </div>
    </div>

  </main>
  <?php include "./components/footer.html"; ?>
</body>

</html>