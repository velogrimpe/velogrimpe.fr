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
  "Les Calanques" => [
    "falaises" => [
      ["name" => "Les Calanques - Luminy", "id" => 364, "contributor" => "Florent"],
      ["name" => "Les Calanques - Morgiou", "id" => 365, "contributor" => "Florent"],
      ["name" => "Les Calanques - La Melette", "id" => 366, "contributor" => "Florent"],
    ],
    "img" => "calanques.webp",
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
        <p>Salut tout le monde !</p>
        <p>Pfiou, il s'en est passé des choses sur Vélogrimpe.fr ces derniers mois ! On va essayer de rattraper le
          retard dans notre newsletter, allez c'est parti !</p>
        <p>Déjà, on tient à vous rappeler que ce site web est contributif. Vous pouvez&nbsp;:</p>
        <ul>
          <li>ajouter des falaises (cliquez sur "contribuer").</li>
          <li>modifier des falaises déjà présentes (icône "modifier" en haut à droite d'une fiche falaise)&nbsp;: vous
            connaissez bien une falaise ? Allez voir sa fiche, et n'hésitez pas à ajouter/modifier une info
            (hébergement, accès, cotations...)</li>
          <li>écrire un petit compte rendu d'une sortie vélogrimpe réalisée sur une falaise (icône "commentaire" en haut
            à droite de la fiche falaise)</li>
        </ul>
        <h2>NOUVELLES FONCTIONNALITES</h2>
        <ul>
          <li>Accès TGV&nbsp;: dorénavant, en plus des accès TER, les accès TGV sont décrits (lignes/gares TGV visibles
            sur
            les cartes, temps de trajet TGV renseignés, possibilité de filtrer TER/TGV). Mais comment je mets mon vélo
            dans un TGV ? On répondra bientôt à cette question en détail...&nbsp;🙂</li>
        </ul>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto" alt="Carte avec les voies TGV en rouge"
                src="https://velogrimpe.fr/images/news/2026-01/tgv.webp" />
            </td>
          </tr>
        </table>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto" alt="Sormiou depuis Paris en TGV"
                src="https://velogrimpe.fr/images/news/2026-01/tgv-sormiou.webp" />
            </td>
          </tr>
        </table>
        <ul>
          <li>Accès en bus&nbsp;: certaines falaises sont très facilement accessibles en bus, on a commencé à renseigner
            les
            accès bus pour certaines (Calanques, la Balme de Yenne, autour de Vaison la Romaine...). L'itinéraire bus
            est rempli dans un nouveau champ de la fiche falaise, et les arrêts sont représentés sur la carte de la
            falaise</li>
        </ul>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto" alt="Accès en bus à la Balme de Yenne"
                src="https://velogrimpe.fr/images/news/2026-01/bus-fiche-falaise.webp" />
            </td>
          </tr>
        </table>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto" alt="Accès en bus aux Calanques"
                src="https://velogrimpe.fr/images/news/2026-01/bus-calanques.webp" />
            </td>
          </tr>
        </table>
        <ul>
          <li>Camping et gîtes&nbsp;: sur toutes les cartes du site web, vous pouvez afficher les campings et gîtes à
            proximité. Nous avons aussi ajouté des champs "hébergements" dans les fiches falaises. Si vous connaissez
            des bons plans, n'hésitez pas à les ajouter (allez sur la fiche du falaise, puis cliquez sur le bouton
            "modifier" en haut à droite)</li>
        </ul>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto"
                alt="Carte avec les campings à proximité d'une falaise"
                src="https://velogrimpe.fr/images/news/2026-01/couche-camping.webp" />
            </td>
          </tr>
        </table>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto"
                alt="Description d'un gîte dans la fiche falaise"
                src="https://velogrimpe.fr/images/news/2026-01/gite-fiche-falaise.webp" />
            </td>
          </tr>
        </table>
        <ul>
          <li>Édition de détails falaises&nbsp;: vous aviez déjà remarqué que sur certaines falaises, on avait créé des
            cartes interactives précises, avec les barres rocheuses, les parkings, les sentiers de marche d'approche...?
            Et bien maintenant, vous pouvez vous aussi contribuer à créer ces cartes détaillés:</li>
          <ul>
            <li>Sur une page falaise, cliquez sur l'icône d'édition puis sur "Ajouter des détails"</li>
            <li>Vous avez ensuite accès à une interface pour ajouter directement sur la carte les différents détails de
              la falaise.</li>
          </ul>
        </ul>

        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto"
                alt="Bouton d'édition des détails d'une falaise à partir de la page falaise"
                src="https://velogrimpe.fr/images/news/2026-01/editer-details.webp" />
            </td>
          </tr>
        </table>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $imageContainerStyle ?>">
              <img style="<?= $imageStyle ?>" width="500px" height="auto" alt="Éditeur de détails falaise"
                src="https://velogrimpe.fr/images/news/2026-01/editeur-details.webp" />
            </td>
          </tr>
        </table>
        <ul>
          <li>Page falaises prioritaires&nbsp;: on a cartographié toutes les falaises françaises situées à moins de 6km
            d'une
            gare, plus de 730 falaises accessibles en train même sans vélo ! De très bonnes candidates pour de nouvelles
            <a href="https://velogrimpe.fr/contribuer.php?<?= $utm ?>">contributions sur vélogrimpe.fr</a>
          </li>
        </ul>
        <h2>NOUVELLES FALAISES</h2>
        <p>Ces derniers mois, on a eu de nombreuses contributions dans plusieurs coins de la France dont 3 grosses
          zones&nbsp;: Tautavel grâce à Florent, autour de Paris grâce à Tanguy et la Savoie grâce à David. En plus de
          ça, on a eu plusieurs autres contributions locales par 7 contributeurs différents. Ça peut paraître peu mais
          vu qu'il faut quand même un peu de temps pour renseigner une falaise et comparé
          à il y a un an et l'intégralité des falaises ajoutées par Florent, c'est génialissime !! Encore merci à eux !
        </p>
        <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
          <tr>
            <td style="<?= $nouvellesFalaiseStyle ?>">
              <?php foreach ($nouvellesFalaises as $region => $content): ?>
                <!-- <a
                  href="http://localhost:4002/carte.php?h=<?= join(',', array_column($content['falaises'], 'id')) ?>">HIGHLIGHT</a> -->
                <h3 style="<?= $h3Style ?>"><?= $region ?></h3>
                <table cellpadding="0" cellspacing="0" border="0" style="<?= $imgTableStyle ?>">
                  <tr>
                    <td style="width: 700px;">
                      <img style="<?= $imageStyle ?>" width="500px" height="auto"
                        alt="Aperçu des nouvelles falaises de la région <?= $region ?>"
                        src="https://velogrimpe.fr/images/news/2026-01/<?= $content['img'] ?>" />
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
        <p>Du travail a aussi été fourni sur les détails de certaines falaises, notamment dans les Calanques&nbsp;:
          Accès bus, différents secteurs représentés, mégasecteurs cartographiés...</p>

        <h2>ON PARLE DE VELOGRIMPE</h2>
        <p>Le site Vélogrimpe a été présenté au public lors de plusieurs évènements ces derniers mois&nbsp;:</p>
        <ul>
          <li>Dans 2 soirées "une montagne d'aventures sans voiture" organisées par Mountain Wilderness&nbsp;: à La
            Cordée Jean Macé de Lyon en Novembre, à La Cordée Annecy en Janvier&nbsp;: les deux soirées ont fait salle
            comble, avec une cinquantaine de personnes ! <a
              href="https://www.mountainwilderness.fr/actualites/soirees-aventure-sans-voiture-eco-mobilite">Lien</a>
          </li>
          <li>Au Salon de l'Escalade à Paris en Janvier.</li>
        </ul>

        <p>Et voilà, ça fait déja pas mal de nouvelles choses développées en trois mois, et promis, c'est pas fini 😉
        </p>
        <p>À bientôt !</p>
        <p>Florent et Yoann, l'équipe vélogrimpe</p>
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