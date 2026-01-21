<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php'; ?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description"
    content="Escalade en mobilité douce à vélo et en train. Rejoingnez la communauté Vélogrimpe sur instagram et Signal.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Velogrimpe.fr - Communauté">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Escalade en mobilité douce à vélo et en train. Rejoingnez la communauté Vélogrimpe sur instagram et Signal.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title"
    content="<?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Escalade en mobilité douce à vélo et en train. Rejoingnez la communauté Vélogrimpe sur instagram et Signal.">
  <title>Communauté - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen flex flex-col">
  <?php include "./components/header.html"; ?>
  <div class="hero min-h-100 bg-top" style="background-image: url(/images/mw/0100b-grimpe-60.webp);">
    <div class="hero-overlay bg-slate-600/70"></div>
    <div class="hero-content text-center text-base-100">
      <div class="max-w-md">
        <h1 class="text-5xl font-bold">Communauté</h1>
      </div>
    </div>
  </div>
  <main class="grow w-full max-w-(--breakpoint-md) mx-auto flex flex-col gap-2 md:gap-4 p-4 mb-2">
    <h2 class="text-2xl font-bold text-center">Actualités du site et ajouts au topo</h2>
    <p class="md:text-center text-normal">Nous publions une newsletter (environ trimestrielle) pour vous tenir au
      courant des nouveautés du site et des nouvelles falaises ajoutées au topo. Si vous souhaitez la recevoir,
      inscrivez-vous ci-dessous.</p>
    <div class="mb-8">
      <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/newsletter-form.php"; ?>
    </div>
    <h2 class="text-2xl font-bold text-center">Réseaux sociaux</h2>
    <div class="flex flex-row items-center justify-center gap-8">
      <a href="https://instagram.com/velogrimpe" target="_blank">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a5/Instagram_icon.png/250px-Instagram_icon.png"
          alt="Instagram Vélogrimpe" class="w-16 md:w-24 h-16 md:h-24" />
      </a>
      <a href="https://signal.org/fr/download/" target="_blank">
        <img src="/images/pages/communaute/signal.svg" alt="Signal" class="w-16 md:w-24 h-16 md:h-24" />
      </a>
    </div>
    <p class="md:text-center text-normal">Depuis peu nous avons créé une page Instagram <a
        href="https://instagram.com/velogrimpe" target="_blank">@velogrimpe</a>, venez nous y suivre !</p>
    <p class="md:text-center text-normal">Il existe un groupe Signal "Vélogrimpe", sur lequel des propositions de
      sorties sont partagées. <br> Pour le rejoindre, merci de remplir de formulaire ci-dessous, nous vous y ajouterons
      dès que possible.</p>
    <div class="w-full flex flex-col items-center">
      <form action="/mails/rejoindre_communaute.php" method="post"
        class="flex flex-col items-center w-96 max-w-full p-4 pt-1 border rounded-lg bg-base-100 border-base-300 shadow-lg">
        <div class="w-full">
          <div class="label">
            <span class="label-text">Email</span>
          </div>
          <label class="input input-primary flex items-center gap-2 w-full">
            <input class="grow" type="email" id="email" name="email" required />
            <svg class="w-4 h-4 fill-none stroke-current">
              <use href="#mail-line"></use>
            </svg>
          </label>
        </div>
        <div class="w-full">
          <div class="label">
            <span class="label-text">Numéro de téléphone</span>
          </div>
          <label class="input input-primary flex items-center gap-2 w-full">
            <input class="grow" type="tel" id="phone" name="phone" />
            <svg class="w-4 h-4 fill-none stroke-current">
              <use href="#phone-line"></use>
            </svg>
          </label>
        </div>
        <div class="w-full">
          <div class="label">
            <span class="label-text text-wrap">Pourquoi voulez-vous rejoindre le groupe Signal "Vélogrimpe" ?</span>
          </div>
          <textarea class="textarea leading-6 textarea-primary w-full" id="message" name="message" rows="4"
            minlength="100" required
            placeholder="Petit texte de présentation, pour éviter l'invasion par les bots ! 100 caractères minimum."></textarea>
        </div>
        <div class="mt-2 w-full">
          <button class="btn btn-primary w-full" type="submit">Envoyer</button>
        </div>
      </form>
    </div>
  </main>
  <?php include "./components/footer.php"; ?>
</body>

</html>