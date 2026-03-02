<?php
// IMPORTANT: This file is a template for html mails and web pages I need to use only mail-compatible html apis
$date = "Novembre 2025 - Janvier 2026";
$description = "Nouvelles Falaises autour de Paris en Savoie et sur des sites majeurs comme les Calanques et Tautavel) accessibles en TGV. Découvrez toutes les actualités et nouveautés du site Velogrimpe.fr pour la période $date";
$page_title = "Actualités Velogrimpe.fr - $date";
$slugified_date = preg_replace('/ /', '', strtolower($date));
$utm = "source=newsletter-$slugified_date";
$slug = "2026-01-nouveautes-velogrimpe";

$bodyStyle = "font-family: Arial, sans-serif; margin: 0 auto; width: 680px; line-height: 1.6; color: #333; background-color: #eee;";
$astyle = "color: #2e8b57; text-decoration: none; font-weight: bold;";
$tableStyle = "width: 700px; background-color: #fff; padding: 20px;";
$imgTableStyle = "margin: 10px 0;";
$imageContainerStyle = "width: 700px; text-align: center;";
$imageStyle = "border-radius: 12px; border: 1px solid #ccc;";
$logoStyle = "background: white; width: 700px;text-align: center; height: auto;";
$nouvellesFalaiseStyle = "margin: 0 auto; padding-left: 12px;";
$h1Style = "color: #2c3e50; text-align: center;";
$h2Style = "color: #2e8b57; margin-bottom: 4px;";
$h3Style = "color: #2c3e50; margin-bottom: 2px; margin-top: 8px;";
$webLinkStyle = "text-align: center; display: block; width: 100%; font-size: 10px; color: #ccc; margin-bottom: 20px; font-weight: normal;";
$liStyle = "margin: 0px; margin-left: 20px; ";

$nouvellesFalaises = [
  "Ariège" => [
    "falaises" => [
      ["name" => "Quié d'urs", "id" => 338, "contributor" => "Alan"],
    ],
    "img" => "ariege.webp",
  ],
  "Pyrénées Orientales" => [
    "falaises" => [
      ["name" => "Tautavel - Le château", "id" => 339, "contributor" => "Florent"],
      ["name" => "Tautavel - Saint Martin", "id" => 340, "contributor" => "Florent"],
      ["name" => "Tautavel - L'Alzine", "id" => 341, "contributor" => "Florent"],
      ["name" => "Tautavel - L'Alentou", "id" => 342, "contributor" => "Florent"],
      ["name" => "Vingrau", "id" => 343, "contributor" => "Florent"],
      ["name" => "Tautavel - Le Gouleyrous", "id" => 344, "contributor" => "Florent"],
      ["name" => "Tautavel - Le bousquet", "id" => 345, "contributor" => "Florent"],
      ["name" => "Tautavel - La Devèze", "id" => 346, "contributor" => "Florent"],
      ["name" => "Opoul - Les abeilles", "id" => 347, "contributor" => "Florent"],
      ["name" => "Opoul - Gratounette", "id" => 348, "contributor" => "Florent"],
    ],
    "img" => "pyrenees-orientales.webp",
  ],
  "Autour de Paris" => [
    "falaises" => [
      ["name" => "Hauteroche", "id" => 349, "contributor" => "Tanguy"],
      ["name" => "Les Andelys - Secteur Amont", "id" => 350, "contributor" => "Tanguy"],
      ["name" => "Viaduc des Fauvettes", "id" => 351, "contributor" => "Tanguy"],
      ["name" => "Saint Maximin", "id" => 352, "contributor" => "Tanguy"],
      ["name" => "Les Andelys - Secteur Aval", "id" => 353, "contributor" => "Tanguy"],
      ["name" => "Saffres", "id" => 354, "contributor" => "Tanguy"],
      ["name" => "Surgy", "id" => 355, "contributor" => "Tanguy"],
    ],
    "img" => "paris.webp",
  ],
  "Hérault" => [
    "falaises" => [
      ["name" => "Saint Bauzille de Montmel", "id" => 356, "contributor" => "Florent"],
      ["name" => "Le Joncas - Montpeyroux", "id" => 357, "contributor" => "Olivier"],
    ],
    "img" => "herault.webp",
  ],
  "Baronnies - Ventoux" => [
    "falaises" => [
      ["name" => "Dentelles de Montmirail", "id" => 358, "contributor" => "Sandrine"],
    ],
    "img" => "baronnies-ventoux.webp",
  ],
  "Autour du Morvan" => [
    "falaises" => [
      ["name" => "Le Saussois", "id" => 359, "contributor" => "Florent"],
      ["name" => "Rochers du Parc", "id" => 360, "contributor" => "Florent"],
      ["name" => "Montbard", "id" => 361, "contributor" => "Florent"],
    ],
    "img" => "morvan.webp",
  ],
  "Bretagne" => [
    "falaises" => [
      ["name" => "Lanvallay, l'Abbaye de Léhon", "id" => 362, "contributor" => "Louenn PIERRE"],
      ["name" => "Lanvallay, la vieille rivière", "id" => 363, "contributor" => "Louenn PIERRE"],
    ],
    "img" => "bretagne.webp",
  ],
  "Les Calanques" => [
    "falaises" => [
      ["name" => "Les Calanques - Luminy", "id" => 364, "contributor" => "Florent"],
      ["name" => "Les Calanques - Morgiou", "id" => 365, "contributor" => "Florent"],
      ["name" => "Les Calanques - La Melette", "id" => 366, "contributor" => "Florent"],
    ],
    "img" => "calanques.webp",
  ],
  "Savoie" => [
    "falaises" => [
      ["name" => "La Grande Dent", "id" => 367, "contributor" => "David SZUMILO"],
      ["name" => "Roc de Torméry", "id" => 368, "contributor" => "David SZUMILO"],
      ["name" => "Hautecour", "id" => 369, "contributor" => "David SZUMILO"],
      ["name" => "Villette", "id" => 370, "contributor" => "David SZUMILO"],
      ["name" => "St Léger", "id" => 371, "contributor" => "David SZUMILO"],
      ["name" => "Pontamafrey", "id" => 372, "contributor" => "David SZUMILO"],
      ["name" => "Hermillon", "id" => 373, "contributor" => "David SZUMILO"],
    ],
    "img" => "savoie.webp",
  ],
]

  ?>
<!DOCTYPE html>
<html lang="fr" style="background-color: #eee;">

<head>
  <meta charset="UTF-8" />
  <meta name="description" content="<?= $description ?>" />
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta property="og:locale" content="fr_FR" />
  <meta property="og:title" content="<?= $page_title ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="Velogrimpe.fr" />
  <meta property="og:url" content="https://velogrimpe.fr/" />
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp" />
  <meta property="og:description" content="<?= $description ?>" />
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp" />
  <meta name="twitter:title" content="<?= $page_title ?>" />
  <meta name="twitter:description" content="<?= $description ?>" />
  <meta name="viewport" content="width=device-width" />
  <!-- Forcing initial-scale shouldn't be necessary -->
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <!-- Use the latest(edge) version of IE rendering engine -->
  <meta name="x-apple-disable-message-reformatting" />
  <!-- Disable auto-scale in iOS 10 Mail entirely -->
  <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no" />
  <!-- Tell iOS not to automatically link certain text strings. -->
  <meta name="color-scheme" content="light" />
  <meta name="supported-color-schemes" content="light" />
  <!-- What it does: Makes background images in 72ppi Outlook render at correct size. -->
  <title><?= $page_title ?></title>
  <script async defer src="https://velogrimpe.frhttps://velogrimpe.fr/js/pv.js"></script>
  <style>
    /* Hopefully get it rendered */
    a:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body style="<?= $bodyStyle ?>">
  <table role="presentation" style="<?= $tableStyle ?>">
    <tr>
      <td>
        <a style="<?= $webLinkStyle ?>" href="https://velogrimpe.fr/actualites/<?= $slug ?>.php?<?= $utm ?>">un problème
          pour visualiser le contenu ? cliquez ici pour la version web</a>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $logoStyle ?>">
              <a href="https://velogrimpe.fr/?<?= $utm ?>">
                <img width="300px" height="auto" src="https://velogrimpe.fr/images/news/logo.png"
                  alt="logo velogrimpe.fr" />
              </a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td>
        <h1 style="<?= $h1Style ?>"><?= preg_replace('/ - /', '<br />', trim($page_title), 1) ?></h1>
        <p>Salut ! <br />
          <br /> C'est Florent et Yoann, l'équipe derrière le site <a style="<?= $astyle ?>"
            href="https://velogrimpe.fr/?<?= $utm ?>">velogrimpe.fr</a>. Vous recevez ce mail car vous avez à un moment
          montré votre intérêt pour le projet velogrimpe.fr, ou alors parce que vous avez contribué en ajoutant une
          falaise ou un itinéraire sur le site. On s’est dit qu’il serait bien de vous tenir au courant des nouveautés
          du projet. Si vous ne souhaitez plus recevoir cette newsletter, dites le nous en réponse à ce mail, et promis,
          on ne vous contacte plus ! <br />
          <br /> Allez c’est parti, voici un petit résumé en images des dernières contributions et nouveautés sur le
          site, suivies de quelques actualités du projet.
        </p>
        <h2 style="<?= $h2Style ?>">Nouveautés sur le site</h2>
        <p>Du côté du site, on a surtout fait du ménage, un peu de réorganisation et beaucoup de choses invisibles qui
          améliorent grandement la maintenance du projet. Quelques nouveautés visibles tout de même : </p>
        <p style="<?= $liStyle ?>">&bull; Retours d’expériences et récits de sorties : on peut maintenant ajouter un
          commentaire à la page falaise pour faire un retour, raconter son expérience sur un itinéraire, donner des
          conseils aux suivants. C’est quelque chose que l’on souhaitait faire depuis longtemps pour permettre à tout le
          monde d’améliorer le topo en donnant des petits “tips” sur un accès train, des détails d’itinéraires etc.
          Dites nous ce que vous en pensez et comment on pourrait l’améliorer !</p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto"
                alt="exemple de retour d'expérience sur une falaise"
                src="https://velogrimpe.fr/images/news/2025-10/retour-experience.webp" />
            </td>
          </tr>
        </table>
        <p style="<?= $liStyle ?>">&bull; Page d’accueil séparée de la carte pour mieux expliquer le but du site et
          expliquer les différentes fonctionnalités, on en a profité pour utiliser <a style="<?= $astyle ?>"
            href="https://client.monikaglet.com/changerdapprochemountainwilderness/entransportsencommun/">les belles
            photos de Monika Glet</a> issues du stock de photos libres de droit de la campagne Changer d’Approche de
          Mountain Wilderness.</p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto" alt="nouvelle page d'accueil velogrimpe.fr"
                src="https://velogrimpe.fr/images/news/2025-10/accueil.webp" />
            </td>
          </tr>
        </table>
        <p style="<?= $liStyle ?>">&bull; Filtres par le nombre de voies et le type d’escalade dans le tableau et la
          carte principale. On a donc fait une passe sur toutes les falaises pour préciser l’ordre de grandeur du nombre
          de voies de chaque falaise du topo. </p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="250px" height="auto"
                alt="filtres par nombre de voies et type d'escalade"
                src="https://velogrimpe.fr/images/news/2025-10/filtres.webp" />
            </td>
          </tr>
        </table>
        <p style="<?= $liStyle ?>">&bull; Ré-ouverture des contributions sur toutes les falaises (même celles du topo)
          pour faciliter la contribution et permettre d’améliorer les fiches falaises. On a aussi ajouté un bouton
          “suggérer une modification” sur chaque page falaise pour faciliter la contribution. </p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="400px" height="auto" alt="bouton suggérer une modification"
                src="https://velogrimpe.fr/images/news/2025-10/edition.webp" />
            </td>
          </tr>
        </table>
        <h2 style="<?= $h2Style ?>">Un grand rassemblement vélogrimpe en préparation !</h2>
        <p>100 personnes qui se retrouveraient pour grimper en train + vélo, avec des ateliers, des discussions, des
          initiations pour accompagner les néopratiquants, ça vous dirait ? Et bien c’est un projet en cours
          d’organisation : une quinzaine de personnes motivées s’est retrouvée pour commencer à planifier tout ça, ce
          serait dans le Royans en septembre 2026, à suivre ! </p>
        <h2 style="<?= $h2Style ?>">On parle de Vélogrimpe !</h2>
        <p>Présentation de vélogrimpe.fr par Florent à la <a style="<?= $astyle ?>"
            href="https://www.clubalpinlyon.fr/sortie/soiree-changer-d-approche-en-m-9076.html?commission=environnement">soirée
            Changer d’Approche du CAF de Lyon</a> le 29 septembre 2025. </p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto" alt="florent au caf lyon"
                src="https://velogrimpe.fr/images/news/2025-10/caf.jpeg" />
            </td>
          </tr>
        </table>
        <h2 style="<?= $h2Style ?>">Nouvelles falaises sur le site</h2>
        <p>Cet été, des contributeurs ont ajouté une vingtaine de falaises, dont de nombreuses ajoutées par Olivier
          autour de Montpellier (ville qui fait du même coup son entrée dans <a style="<?= $astyle ?>"
            href="https://velogrimpe.fr/tableau.php?ville_id=19&<?= $utm ?>">la liste des “falaises à proximité de…”</a>
          du menu principal), et à Marseille avec trois sites majeurs des Calanques ajoutés par Samuel, qui a fourni un
          travail de fou furieux pour renseigner tous les détails des accès et des secteurs !! (plus de détails plus
          bas). Toutes ces contributions nous amènent à un nombre total de 150 falaises : merci !!! </p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $nouvellesFalaiseStyle ?>">
              <?php foreach ($nouvellesFalaises as $region => $content): ?>
                <h3 style="<?= $h3Style ?>"><?= $region ?></h3>
                <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
                  <tr>
                    <td style="width: 700px;">
                      <img style="<?= $imageStyle ?>" width="500px" height="auto"
                        alt="Aperçu des nouvelles falaises de la région <?= $region ?>"
                        src="https://velogrimpe.fr/images/news/2025-10/<?= $content['img'] ?>" />
                    </td>
                  </tr>
                </table>
                <?php foreach ($content['falaises'] as $falaise): ?>
                  <p style="<?= $liStyle ?>">&bull; <a style="<?= $astyle ?>"
                      href="https://velogrimpe.fr/falaise.php?falaise_id=<?= $falaise['id'] ?>&<?= $utm ?>">
                      <?= $falaise['name'] ?>
                    </a> par <?php
                    $contributors = explode(',', $falaise['contributor']);
                    foreach ($contributors as $index => $contributor) {
                      if ($index > 0) {
                        echo ' et ';
                      }
                      echo trim($contributor);
                    }
                    ?>
                  </p>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </td>
          </tr>
        </table>
        <p>Ces trois zones des Calanques ont été ajoutées avec des cartes détaillées de tous les secteurs et leurs accès
          respectifs !! </p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto" alt="details falaises calanques"
                src="https://velogrimpe.fr/images/news/2025-10/sormiou.webp" />
            </td>
          </tr>
        </table>
        <h2 style="<?= $h2Style ?>">Autres news</h2>
        <p style="<?= $liStyle ?>">&bull; Présence de vélogrimpe.fr sur un stand avec Mountain Wilderness au salon de
          l’escalade en janvier 2026 à Paris. </p>
        <p style="<?= $liStyle ?>">&bull; Présence à la Cordée Jean Macé (à Lyon) pour une soirée Changer d’Approche le
          25 Novembre (infos à suivre pour y participer !).</p>
        <p style="<?= $liStyle ?>">&bull; Florian Garibal et Fanny Audigé sont en pleine campagne de financement
          participatif pour leur topo d’escalade en mobilité douce au départ de Grenoble. <a style="<?= $astyle ?>"
            href="https://fr.ulule.com/topo-doux-depuis-grenoble/">Allez y faire un tour</a>, l’ouvrage est splendide !
        </p>
        <p style="<?= $liStyle ?>">&bull; On aimerait bien cartographier Fontainebleau, mais ne connaissant pas bien le
          secteur, on est toujours à la recherche de connaisseurs pour nous conseiller voire aider dans ce travail.</p>
        <p>Et voilà ! Merci mille fois à ceux qui sont arrivés jusque-là ! Envoyez nous un petit message pour nous dire
          ce que vous en pensez ! Et si vous ne souhaitez plus recevoir de mails comme celui-ci, dites le nous, ceci
          n'est pas un mail automatique on rentre les adresses une à une 😉</p>
      </td>
    </tr>
    <tr>
      <td>
        <span data-placeholder></span>
      </td>
    </tr>
  </table>
</body>

</html>