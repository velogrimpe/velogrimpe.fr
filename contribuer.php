<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$email = $config['contact_mail'];
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description"
    content="Escalade en mobilité douce à vélo et en train. Contribuez au topos d'accès vélo + train pour se rendre en falaise. Partagez vos bon plans et expérience, traces et falaises accessibles à vélo.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Velogrimpe.fr - Contribuer">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Escalade en mobilité douce à vélo et en train. Contribuez au topos d'accès vélo + train pour se rendre en falaise. Partagez vos bon plans et expérience, traces et falaises accessibles à vélo.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title"
    content="<?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Escalade en mobilité douce à vélo et en train. Contribuez au topos d'accès vélo + train pour se rendre en falaise. Partagez vos bon plans et expérience, traces et falaises accessibles à vélo.">
  <title>Contribuer - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen">
  <?php include "./components/header.html"; ?>
  <div class="hero min-h-100 bg-bottom" style="background-image: url(/images/mw/078-groupe-5.webp);">
    <div class="hero-overlay bg-slate-600/70"></div>
    <div class="hero-content text-center text-base-100">
      <div class="max-w-md">
        <h1 class="text-5xl font-bold">Contribuer</h1>
      </div>
    </div>
  </div>
  <div class="max-w-(--breakpoint-lg) mx-auto prose p-4">
    <p> Comme vous l'imaginez, ce projet de site Vélogrimpe nécessite du temps et de l'énergie : si vous voulez nous
      aider, vous êtes les bienvenus ! </p>
    <h2>AJOUTER DES DONNÉES</h2>
    <p> Si vous souhaitez ajouter des données (falaise, itinéraire...), suivez les étapes suivantes :</a>.</a>
    </p>
    <ul>
      <li>
        <b>Etape 1 :</b>
        <a href="/ajout/ajout_falaise.php">ajouter/modifier une falaise.</a><br /> Prérequis : avoir le topo sous la
        main.
      </li>
      <li>
        <b>Etape 2 :</b>
        <a href="/ajout/ajout_velo.php">ajouter un itinéraire vélo/à pied d'une gare à une falaise.</a><br /> Prérequis
        : avoir déjà ajouté la falaise, et avoir une trace GPS entre une gare et la falaise.
      </li>
    </ul>
    <p> Si seule l'étape 1 est réalisée, c'est déjà bien mais la falaise n'apparaitra pas sur le site.<br /> Pour
      qu'elle apparaisse sur la carte, il faut connecter la falaise à au moins une gare, en réalisant l'étape 2.<br />
    </p>
    <p>Pour faire apparaître la falaise dans le tableau "falaises proches de ...", il est nécessaire de renseigner
      également un itinéraire train. Nous avons fermé la contribution sur cette partie pour le moment, donc envoie nous
      un message sur <a href="mailto:<?= $email ?>">contact@velogrimpe.fr</a> et on l'ajoutera rapidement.</p>
    <h3>Falaises prioritaires</h3>
    <p>Les falaises très proches des gares sont particulièrement intéressantes car avec un court trajet à vélo, voire à
      pied, la falaise devient accessible à un plus grand public. Nous avons réalisé une carte interactive qui localise
      les falaises à moins de 10km à vol d'oiseau d'une gare, et qui met en avant les plus proches. N'hésitez pas à
      aller voir cette carte et qui sait, peut être que vous en connaissez une et que vous pourrez l'ajouter au topo !!
    </p>
    <a class="btn btn-sm btn-primary not-prose" href="/ajout/falaises_accessibles_a_pied.php"> Carte des falaises
      prioritaires </a>
    <h2>AUTRES CONTRIBUTIONS</h2>
    <p> - Si vous voulez corriger des informations sur une falaise, vous pouvez le faire depuis la fiche falaise en
      question.</p>
    <p> - Pour les itinéraires vélo, vous pouvez laisser un commentaire de sortie sur la fiche falaise concernée ou nous
      contacter par mail</p>
    <p> - Enfin, si vous avez des suggestions, envoyez-nous un mail à <a
        href="mailto:<?= $email ?>">contact@velogrimpe.fr</a>.</p>
    <p> - Vous connaissez bien les falaises d'une certaine zone, et voudriez bien vérifier les informations déjà en
      ligne, répondre à nos questions, et nous tenir au courant de l'actualité locale (falaises fermées, nouveaux
      secteurs...) ? Nous cherchons des <b>référents locaux</b> pour jouer ce rôle, écrivez-nous ! Nous avons déjà des
      groupes pour Paris, Les Calanques, Annecy, ... n'hésitez pas à les rejoindre en nous contactant !</p>
    <p>- Si vous avez des talents d'artiste et que vous pouvez nous créer un logo, une affiche...ça serait super !</p>
    <p>- Si vous avez envie de contribuer au code de ce site, signaler des erreurs ou suggérer des évolutions, ça se
      passe sur Github : <a href="https://github.com/velogrimpe/velogrimpe.fr" target="_blank">code source</a></p>
  </div>
  <?php include "./components/footer.html"; ?>
</body>

</html>