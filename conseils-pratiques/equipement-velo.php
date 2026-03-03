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
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/0026-velo-social-20.webp">
  <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/0026-velo-social-20.webp">
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
  <div class="hero min-h-100 bg-center" style="background-image: url(/images/mw/0026-velos-20.webp);">
    <div class="hero-overlay bg-slate-600/70"></div>
    <div class="hero-content text-center text-base-100">
      <div class="max-w-md">
        <h1 class="text-5xl font-bold">Conseils pratiques pour le vélo-grimpe : équipements vélo</h1>
      </div>
    </div>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/nav-conseils-pratiques.php"; ?>
  <main class="max-w-(--breakpoint-lg) mx-auto prose p-4">
    <h2>Comment trimballer toutes mes affaires sur mon vélo ?</h2>
    <p> Certes, vous pouvez faire du vélo en portant un sac à dos de 15 kg…mais ce n’est pas ainsi que vous prendrez
      plaisir à pratiquer le vélogrimpe ! Si vous tenez vraiment à porter un sac à dos, chargez-le le moins possible…il
      vous faudra donc mettre vos affaires ailleurs ! </p>
    <h3>Dans quoi mettre mes affaires ?</h3>
    <p> Le plus classique est d’installer un porte-bagages arrière, pour y positionner deux sacoches. Il reste encore la
      place d’accrocher un sac à dos (ou une corde !) sur le porte-bagages, avec l’aide d’un tendeur (voir ci-contre).
      Pour partir à la journée, ça suffit largement. Si on veut partir sur plusieurs jours, avec du matériel de bivouac,
      c’est possible aussi, mais on risque de se retrouver avec beaucoup de poids à l’arrière. Pour mieux répartir la
      charge, on peut envisager d’installer un porte-bagages avant supplémentaire, pour y accrocher deux autres
      sacoches. </p>
    <div class="flex flex-col md:flex-row gap-4 justify-center items-center">
      <img src="/images/pages/logistique/logistique_0.jpg" alt="Vélo chargé à l'arrière" class="w-full md:w-1/2" />
      <img src="/images/pages/logistique/logistique_1.jpg" alt="Saccoches avant et arrière" class="w-full md:w-1/2" />
    </div>
    <p>
      <i> A gauche : départ pour 3 jours (avec 2 bivouacs), sans chercher à faire light. Je porte tout mon matériel
        perso + corde de 80m + 25 dégaines, ma binôme porte le matériel de bivouac (tente, popote, gaz,…). Ça passe,
        mais beaucoup de poids à l’arrière. </i>
    </p>
    <p>
      <i> A droite : départ pour 2 jours (avec 2 bivouacs), sans chercher à faire light. Je porte tout mon matériel
        perso, le matériel de bivouac (tente, popote,…), la corde de 80m et 25 dégaines. Ce coup-ci, en plus des
        sacoches arrière 2x20L, j’ai des sacoches avant 2x12,5L, ce qui permet d’avoir un sac à dos à l’arrière quasi
        vide. C’est plus stable. </i>
    </p>
    <p>A noter qu’il existe d’autres types de contenants que les sacoches avant/arrière :</p>
    <ul>
      <li>Sacoches de selle, de cadre et de guidon (voir ci-dessous) : leur volume est moindre, mais on peut les prendre
        en complément.</li>
      <li>Panier à fixer sur le guidon : pas recommandé pour porter de lourdes charges.</li>
    </ul>
    <div class="flex flex-col md:flex-row gap-4 justify-center items-center">
      <img src="/images/pages/logistique/logistique_2.jpg" alt="Saccoches de selle, de cadre et de guidon"
        class="w-full md:w-1/2" />
    </div>
    <h3>Le choix du porte-bagages</h3>
    <p>Lorsque vous choisissez un porte-bagages :</p>
    <ul>
      <li>Vérifiez la charge maximale qu’il peut supporter, et choisissez selon votre utilisation (quel poids de bagages
        portez-vous ? Est-il envisagé d’y fixer un siège bébé ?).</li>
      <li>Soyez sûrs que votre porte-bagages puisse se fixer sur votre vélo. Il existe de nombreux moyens de fixation :
        souvent, des œillets percés dans le cadre permettent de l’y visser directement. Si ces œillets sont absents, on
        peut trouver des modèles de porte-bagages qui se fixent ailleurs (tige de selle, haubans, étriers de frein,
        fourche…), parfois à l’aide de colliers. Dans le doute, prenez le vélo avec vous dans le magasin.</li>
    </ul>
    <p>Prix neuf : à partir de 30€ pour des portes bagages solides.</p>
    <h3>Le choix des sacoches</h3>
    <p>Voici les questions à se poser avant l’achat :</p>
    <ul>
      <li>Quel volume ? Le standard est de 20L/sacoche pour l’arrière, 12,5L/sacoche à l’avant.</li>
      <li>Etanches ou pas ? C’est plus cher, mais c’est pratique…à vous de voir </li>
      <li>Quelle solidité ? Si vos sacoches sont destinées à trimballer votre quincaillerie pour grimper, privilégiez
        des sacoches solides, histoire qu’elles ne se déchirent pas en rase campagne.</li>
      <li>Sont-elles compatibles avec mon porte-bagages ? Elles sont vendues avec des réducteurs de diamètre en
        plastique, pour être sûr que le crochet de fixation de la sacoche soit compatible avec le diamètre des tiges du
        porte-bagages. Soyez vigilants si vous achetez d’occasion.</li>
      <li>Les sacoches ont-elles des bandoulières ? C’est plus pratique.</li>
    </ul>
    <p>Prix neuf : 30€ la paire de sacoches arrière pour le premier prix, et jusqu’à 150€ la paire pour des sacoches
      arrières étanches haut de gamme.</p>
    <h3>Peut-on louer tout cet équipement ?</h3>
    <p>Oui. A Lyon, le magasin <a href="https://velostrada.com/">Velostrada</a>, spécialisé dans les voyages à vélo,
      permet de louer tout ceci. En 2024, la location d’un vélo coûte 58€ pour 2j, et 155€ pour une semaine. La paire de
      sacoches arrière coûte 25€ la semaine.</p>
    <h2>Comment accrocher son vélo (et ses sacoches), si on ne peut pas l’emmener à la falaise ?</h2>
    <p> Pour ma part, j’accroche mon vélo à un arbre, dans un coin discret si possible. De manière générale, privilégiez
      un bon cadenas (chaîne ou U ; la chaîne permet un choix d’arbres plus large). Si vous êtes plusieurs, vous pouvez
      choisir d’accrocher tous vos vélos les uns aux autres, dans une sorte de chaos inextricable. </p>
    <p> Je mets les affaires nécessaires à la grimpe dans le sac à dos que je trimballais sur le porte-bagages, et je
      monte à la falaise avec. Concernant les sacoches et le matériel inutile pour grimper, soit je les attache au vélo
      avec le cadenas, soit je les cache dans la forêt. </p>
    <h2>Et si j’ai un problème de mécanique vélo pendant ma sortie ?</h2>
    <p> Forcément, quand on décide de se déplacer sur deux roues, on devient dépendant de son vélo… Il est bon de savoir
      bricoler un minimum, ou de partir avec quelqu’un qui sait ! </p>
    <p> A mon avis, il faut au grand minimum savoir changer une chambre à air, et remettre une chaîne qui a déraillé.
      Pour les autres problèmes, on peut essayer de bricoler quelque chose de plus ou moins temporaire (même si on ne
      sait a priori pas faire, on peut parfois se surprendre soi-même !), ou rouler prudemment jusqu’à la maison si le
      vélo le permet, ou encore compter sur les gens sur place (automobilistes, habitants…). </p>
    <p> Prenez avec vous de quoi bricoler un minimum : tournevis plat, cruciforme, clefs allen, démonte pneu, pompe,
      chambre à air et/ou rustines, scotch, ficelle, clefs plates </p>
    <p> Vous pouvez apprendre la base de la mécanique vélo en suivant des tutos sur Internet, ou en vous rendant dans un
      des <a href="https://clavette-lyon.heureux-cyclage.org/">ateliers d’autoréparation lyonnais</a>. </p>
    <h2>Comment suivre un itinéraire à vélo ?</h2>
    <p> Vous pouvez planifier cet itinéraire depuis chez vous, puis le suivre soit à l’aide d’une carte papier
      suffisamment précise, soit avec votre téléphone. </p>
    <p> Si vous décidez de vous orienter avec votre téléphone, vous pouvez vous contenter de regarder la carte sur une
      appli quelconque, mais vous pouvez aussi y superposer la trace GPS de l’itinéraire (récupérée en amont sur ce
      topo, ou réalisée par vos soins). Il existe de nombreuses applis permettant d’afficher des traces GPS sur des
      fonds de cartes, je vous laisse faire votre choix. Mes traces sont conçues sous Openrunner. </p>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>