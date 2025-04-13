<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$email = $config['contact_mail'];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contribuer - Vélogrimpe.fr</title>
  <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />

  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen">
  <?php include "./components/header.html"; ?>

  <div class="max-w-screen-lg mx-auto prose p-4
              prose-a:text-[oklch(var(--p)/1)] prose-a:font-bold prose-a:no-underline
              hover:prose-a:underline hover:prose-a:text-[oklch(var(--pf)/1)]">
    <h1 class="text-4xl font-bold text-wrap text-center ">
      CONTRIBUER
    </h1>
    <p>
      Comme vous l'imaginez, ce projet de site Vélogrimpe nécessite du temps et de l'énergie :
      si vous voulez nous aider, vous êtes les bienvenus !
    </p>
    <p><b>AJOUTER DES DONNÉES</b></p>
    <p>
      Si vous souhaitez ajouter des données (falaise, itinéraire...), suivez les étapes suivantes :</a>.</a>
    </p>
    <ul>
      <li>
        <b>Etape 1 :</b>
        <a href="/ajout/ajout_falaise.php">ajouter une falaise.</a><br />
        Prérequis : avoir le topo sous la main.
      </li>
      <li>
        <b>Etape 2 :</b>
        <a href="/ajout/ajout_velo.php">ajouter un itinéraire vélo/à pied d'une gare à une falaise.</a><br />
        Prérequis : avoir déjà ajouté la falaise, et avoir une trace GPS entre
        une gare et la falaise.
      </li>
      <li>
        <b>Etape 3 :</b>
        <a href="/ajout/ajout_train.php">ajouter une description d'un itinéraire en train.</a>
      </li>
    </ul>

    <p>
      Si seule l'étape 1 est réalisée, c'est déjà bien mais la falaise
      n'apparaitra pas sur le site.<br />
      Pour qu'elle apparaisse sur la carte, il faut connecter la falaise à au
      moins une gare, en réalisant l'étape 2.<br />
      Et si tu veux être complet, réalise l'étape 3 pour connecter la falaise
      à une "ville de départ", et elle apparaitra aussi dans le tableau
      "falaises proches de ...".
    </p>
    <p><b>AUTRES CONTRIBUTIONS</b></p>
    <p>
      - Si vous voulez corriger des informations, ou que vous avez des suggestions, envoyez-nous un mail à <a
        href="mailto:<?= $email ?>">contact@velogrimpe.fr</a>.<br>
    </p>
    <p>
      - Vous connaissez bien les falaises d'une certaine zone, et voudriez bien vérifier les informations déjà en
      ligne, répondre à nos questions, et nous tenir au courant de l'actualité locale
      (falaises fermées, nouveaux secteurs...) ? Nous cherchons des <b>référents locaux</b> pour jouer ce rôle,
      écrivez-nous !
    </p>
    <p>- Si vous avez des talents d'artiste et que vous pouvez nous créer un logo, une affiche...ça serait super !</p>
  </div>

</body>

</html>