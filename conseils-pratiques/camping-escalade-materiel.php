<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$title = 'Conseils pratiques : camping, matériel etc.  - Velogrimpe.fr';
$description = 'Conseils pratiques pour le vélogrimpe : Checklist du matériel à emporter, conseils pour le bivouac, etc.';
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
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
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
  <div class="hero min-h-100 bg-center" style="background-image: url(/images/mw/075-matos-5.webp);">
    <div class="hero-overlay bg-slate-600/70"></div>
    <div class="hero-content text-center text-base-100">
      <div class="max-w-md">
        <h1 class="text-5xl font-bold">Conseils pratiques pour le vélo-grimpe : camping, escalade et matériel</h1>
      </div>
    </div>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/nav-conseils-pratiques.php"; ?>
  <main class="max-w-(--breakpoint-lg) mx-auto prose p-4">
    <h2>Quel matériel emporter dans une sortie train + vélo + grimpe ?</h2>
    <p>PARTIE TRAIN : billet, carte de réduction. Avoir réservé pour le vélo si besoin.</p>
    <p>PARTIE VÉLO :</p>
    <ul>
      <li>Vélo + porte-bagages + sacoches arrière.</li>
      <li>Casque.</li>
      <li>Tendeur(s).</li>
      <li>Sac à dos à fixer sur le porte-bagages.</li>
      <li>Cadenas (pas trop petit, sinon c’est impossible de faire le tour d’un arbre).</li>
      <li>Matériel de réparation. On pourrait en parler des heures, et ça dépend du vélo, mais au minimum de quoi
        réparer une crevaison (pompe, démonte pneus, clef plate pour démonter la roue si nécessaire, chambre à air de
        rechange ou pack rustines). A ça, je rajouterais des clefs allen de 3 à 6 mm, clefs plates nécessaires à votre
        vélo, tournevis plat/cruciforme. Peut-être un dérive-chaîne. Scotch solide et ficelle.</li>
      <li>Optionnel : autre contenant (sacoche avant, panier…), gourde.</li>
    </ul>
    <p>PARTIE GRIMPE : ça, je vous laisse vous débrouiller ;)</p>
    <h2>Et pour partir sur plusieurs jours ?</h2>
    <p>On l’a vu plus haut, c’est faisable aussi, en acceptant de trimballer un peu plus de matériel.</p>
    <p> Si vous souhaitez bivouaquer, assurez-vous de rester discret ; trop de bivouacs à proximité des falaises peuvent
      créer des tensions avec les riverains, et aboutir à une interdiction de grimper. </p>
    <h2>Le train et le vélo, c’est beaucoup plus écolo que la voiture ?</h2>
    <p> Encore faudrait-il définir le mot « écolo ». Intéressons-nous simplement aux émissions de gaz à effet de serre.
      Voici les chiffres de l’ADEME, prenant en compte la fabrication, l’utilisation et la fin de vie des différents
      moyens de transport, mais pas les infrastructures sur lesquelles ils circulent : </p>
    <ul>
      <li><b>Vélo : 0 g/km</b> <i class="text-xs">(le chiffre de ≈5 g/km me semblerait plus crédible !)</i></li>
      <li>Vélo électrique : 10,9 g/km <i class="text-xs">(dont 80% pour la fabrication du vélo, 20% pour l’électricité
          de l’utilisation).</i></li>
      <li><b>TER électrique* : 8,91 g/km/passager.</b></li>
      <li>TER gazole* : 79,8 g/km/passager.</li>
      <li><b>Voiture : 231g/km</b> <i class="text-xs">(dont 18% pour la fabrication, 82% pour le carburant)</i></li>
    </ul>
    <p>
      <i class="text-sm"> * Pour savoir quelles lignes TER sont électrifiées, on peut se référer à <a
          href="https://www.openrailwaymap.org/?style=electrified&lat=45.25362179991922&lon=5.10589599609375&zoom=8">cette
          carte d’openrailwaymap</a>. </i>
    </p>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>