<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$title = 'Conseils pratiques train - Velogrimpe.fr';
$description = 'Conseils pratiques pour le vélogrimpe : comment organiser son déplacement en train ? horaires, cartes, réductions et astuces pour un voyage en toute sérénité.';
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
</head>

<body class="min-h-screen">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <div class="hero min-h-100 bg-center" style="background-image: url(/images/mw/train-40.webp);">
    <div class="hero-overlay bg-slate-600/70"></div>
    <div class="hero-content text-center text-base-100">
      <div class="max-w-md">
        <h1 class="text-5xl font-bold">Conseils pratiques pour le vélo-grimpe : se déplacer en train</h1>
      </div>
    </div>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/nav-conseils-pratiques.php"; ?>
  <main class="max-w-(--breakpoint-lg) mx-auto prose p-4">
    <h2>Les cartes de réduction</h2>
    <p> Selon les régions, il existe des cartes de réduction. Dans la région AURA, la carte illico liberté permet de
      bénéficier de réductions sur les TER de la région AURA : </p>
    <ul>
      <li>25% en semaine.</li>
      <li>50% les week-ends et jours fériés.</li>
      <li>Ces deux réductions marchent aussi pour un billet de TER connectant la région AURA à une région limitrophe
        <span class="text-sm">(exemple : les réductions s’appliquent sur Lyon-Marseille (25€), Lyon-Paris
          (32€),…).</span></li>
      <li>Possibilité de faire bénéficier jusqu’à trois accompagnants de la réduction de 50% les week-ends et jours
        fériés, seulement pour les sections en AURA</li>
    </ul>
    <p> Cette carte est vendue en gare, mais aussi sur le site internet TER AURA (depuis 2024), au prix de 30€/an. Mais
      surveillez le site internet : au moins un mois par an (souvent l’été), la carte est soldée à 15€/an. </p>
    <p>Autant dire qu’on rentabilise cette carte très vite !</p>
    <p> Il existe aussi la carte illico solidaire pour les personnes à la situation financière précaire (sans emploi,
      RSA,…), gratuite et encore plus avantageuse, et la carte illico jeunes pour les moins de 26 ans, moins chère et
      plus avantageuse que Illico liberté. </p>
    <h2>Quelques astuces pour organiser son voyage en train</h2>
    <ul>
      <li>On peut se référer à <a
          href="https://mmt.vsct.fr/sites/default/files/swt/CARA/2021-11/Carte_reseau_TER_Auvergne-Rhone-Alpes_40x60_1.pdf">la
          carte du réseau</a>, pour voir où sont les lignes et les gares.<br> Voir aussi <a href="/carte.php">la carte
          interactive du topo</a>, moins exhaustive, mais plus lisible.</li>
      <li>Le site et l’appli SNCF Connect ne donnent pas tous les trains existants, et blacklistent souvent les TER sur
        les longs trajets. On peut utiliser un autre planificateur d’itinéraire ; moi j’utilise <a
          href="https://hafas.bene-system.com/bin/query.exe/en?L=ns_hispeed&protocol=https:">celui-ci</a>.</li>
    </ul>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>