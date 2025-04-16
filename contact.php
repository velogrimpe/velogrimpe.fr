<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact - Vélogrimpe.fr</title>
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

  <main class="w-full flex-grow max-w-screen-xl mx-auto flex flex-col gap-2 md:gap-4 p-4">

    <h1 class="text-4xl font-bold text-wrap text-center ">
      Nous contacter
    </h1>

    <div class="w-full flex flex-col items-center">
      <form action="/mails/contact.php" method="post"
        class="flex flex-col items-center w-full md:w-2/3 max-w-full p-4 pt-1 border rounded-lg bg-base-100 border-base-300 shadow-lg">
        <div class="w-full">
          <div class="label">
            <span class="label-text">Nom</span>
          </div>
          <label class="input input-primary flex items-center gap-2 w-full">
            <input class="grow" type="text" id="name" name="name" required />
            <svg class="w-4 h-4 fill-current">
              <use xlink:href="/symbols/icons.svg#ri-user-line"></use>
            </svg>
          </label>
        </div>
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
            <span class="label-text">
              Votre message
            </span>
          </div>
          <textarea class="textarea textarea-primary w-full leading-[18px]" id="message" name="message" rows="10"
            minlength="30" required placeholder="Votre message, 30 caractères minimum."></textarea>
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