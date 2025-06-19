<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Communauté - Vélogrimpe.fr</title>
  <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />

  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen flex flex-col">
  <?php include "./components/header.html"; ?>

  <main class="flex-grow w-full max-w-screen-xl mx-auto flex flex-col gap-2 md:gap-4 p-4">

    <h1 class="text-4xl font-bold text-wrap text-center ">
      COMMUNAUTÉ
    </h1>

    <div>
      <a href="https://instagram.com/velogrimpe" target="_blank">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a5/Instagram_icon.png/250px-Instagram_icon.png"
          alt="Instagram Vélogrimpe" class="w-16 md:w-24 h-16 md:h-24 mx-auto mb-2" />
      </a>
    </div>
    <p class="md:text-center text-normal">Depuis peu nous avons créé une page Instagram <a
        href="https://instagram.com/velogrimpe" target="_blank">@velogrimpe</a>, venez nous y
      suivre !</p>


    <div class="flex flex-col items-center">
      <img src="/images/signal.svg" alt="Signal" class="w-16 md:w-24 h-16 md:h-24" />
    </div>
    <p class="md:text-center text-normal">Il existe un groupe Signal "Vélogrimpe", sur lequel des propositions de
      sorties sont partagées. <br>
      Pour le rejoindre, merci de remplir de formulaire ci-dessous, nous vous y ajouterons dès que possible.</p>

    <div class="w-full flex flex-col items-center">
      <form action="/mails/rejoindre_communaute.php" method="post"
        class="flex flex-col items-center w-96 max-w-full p-4 pt-1 border rounded-lg bg-base-100 border-base-300 shadow-lg">
        <div class="w-full">
          <div class="label">
            <span class="label-text">Email</span>
          </div>
          <label class="input input-primary flex items-center gap-2 w-full">
            <input class="grow" type="email" id="email" name="email" required />
            <svg class="w-4 h-4 fill-current">
              <use xlink:href="/symbols/icons.svg#ri-mail-line"></use>
            </svg>
          </label>
        </div>
        <div class="w-full">
          <div class="label">
            <span class="label-text">Numéro de téléphone</span>
          </div>
          <label class="input input-primary flex items-center gap-2 w-full">
            <input class="grow" type="tel" id="phone" name="phone" />
            <svg class="w-4 h-4 fill-current">
              <use xlink:href="/symbols/icons.svg#ri-phone-line"></use>
            </svg>
          </label>
        </div>
        <div class="w-full">
          <div class="label">
            <span class="label-text">
              Pourquoi voulez-vous rejoindre le groupe Signal "Vélogrimpe" ?
            </span>
          </div>
          <textarea class="textarea leading-6 textarea-primary w-full leading-[18px]" id="message" name="message"
            rows="4" minlength="100" required
            placeholder="Petit texte de présentation, pour éviter l'invasion par les bots ! 100 caractères minimum."></textarea>
        </div>
        <div class="mt-2 w-full">
          <button class="btn btn-primary w-full" type="submit">Envoyer</button>
        </div>
      </form>
    </div>
  </main>
  <?php include "./components/footer.html"; ?>
</body>

</html>