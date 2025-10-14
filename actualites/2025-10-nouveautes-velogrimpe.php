<?php
// IMPORTANT: This file is a template for html mails and web pages I need to use only mail-compatible html apis
$date = "Juin - Octobre 2025";
$description = "Actualités et nouveautés du site Velogrimpe.fr: $date";
$page_title = "Actualités Velogrimpe.fr<br/>$date";
$slugified_date = preg_replace('/ /', '', strtolower($date));
$utm = "utm_source=newsletter-$slugified_date";
$slug = "2025-10-nouveautes-velogrimpe";
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="description" content="<?= $description ?>" />
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
  <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />
  <script async defer src="https://velogrimpe.frhttps://velogrimpe.fr/js/pv.js"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0 auto;
      width: 680px;
      line-height: 1.6;
      color: #333;
      background-color: #eee;
    }

    a {
      color: #2e8b57;
      text-decoration: none;
      font-weight: bold;
    }

    a:hover {
      text-decoration: underline;
    }

    body>table {
      width: 700px;
      background-color: #fff;
      padding: 20px;
    }

    .image-container {
      width: 100%;
      text-align: center;
    }

    .image-container a {
      padding: 20px;
    }

    .image-container img {
      width: 80%;
      height: auto;
      border-radius: 12px;
      border: 1px solid #ccc;
    }

    .nouvelles-falaises {
      margin: 0 auto;
      padding-left: 12px;
    }

    .logo {
      text-align: center;
      height: auto;
      margin: 10px 0;
    }

    .logo img {
      max-width: 300px;
      height: auto;
    }

    h1 {
      color: #2c3e50;
      text-align: center;
    }

    h3 {
      color: #2c3e50;
      margin-bottom: 2px;
      margin-top: 8px;
    }

    #webLink {
      text-align: center;
      display: block;
      width: 100%;
      font-size: 0.8em;
      color: #ccc;
      margin-bottom: 20px;
      font-weight: normal;
    }

    /* FIXME */
    /* table,
    td {
      border: 2px solid #000000 !important;
    } */
  </style>
</head>

<body>
  <table role="presentation">
    <tr>
      <td>
        <a id="webLink" href="https://velogrimpe.fr/news/<?= $slug ?>?<?= $utm ?>">version web</a>
        <div class="logo">
          <a href="https://velogrimpe.fr/?<?= $utm ?>">
            <img src="https://velogrimpe.fr/images/logo_titre_horizontal.webp" />
          </a>
        </div>
      </td>
    </tr>
    <tr>
      <td>
        <h1><?= $page_title ?></h1>
        <p>Salut !<br /><br />
          C'est Florent et Yoann, l'équipe derrière le site <a
            href="https://velogrimpe.fr/?<?= $utm ?>">velogrimpe.fr</a>.
          Vous recevez ce mail car vous avez à un moment montré votre intérêt pour le projet velogrimpe.fr, ou alors
          parce
          que
          vous avez contribué en ajoutant une falaise ou un itinéraire sur le site. On s’est dit qu’il serait bien de
          vous
          tenir au courant des nouveautés du projet. Si vous ne souhaitez plus recevoir cette newsletter, dites le nous
          en
          réponse à ce mail, et promis, on ne vous contacte plus !<br /><br />
          Allez c’est parti, voici un petit résumé en images des dernières contributions et nouveautés sur le site,
          suivies
          de
          quelques actualités du projet.
        </p>
        <h2>Nouveautés sur le site</h2>
        <p>Du côté du site, on a surtout fait du ménage, un peu de réorganisation et beaucoup de choses invisibles qui
          améliorent grandement la maintenance du projet. Quelques nouveautés visibles tout de même :
        </p>
        <ul>
          <li>
            Retours d’expériences et récits de sorties : on peut maintenant ajouter un commentaire à la page falaise
            pour
            faire un retour, raconter son expérience sur un itinéraire, donner des conseils aux suivants. C’est quelque
            chose
            que l’on souhaitait faire depuis longtemps pour permettre à tout le monde d’améliorer le topo en donnant des
            petits “tips” sur un accès train, des détails d’itinéraires etc. Dites nous ce que vous en pensez et comment
            on
            pourrait l’améliorer !
          </li>
        </ul>
        <div class="image-container">
          <a href="https://velogrimpe.fr/falaise.php?falaise_id=33&<?= $utm ?>">
            <img src="https://velogrimpe.fr/images/news/2025-10/retour-experience.webp" />
          </a>
        </div>
        <ul>
          <li>Page d’accueil séparée de la carte pour mieux expliquer le but du site et expliquer les différentes
            fonctionnalités, on en a profité pour utiliser <a
              href="https://client.monikaglet.com/changerdapprochemountainwilderness/entransportsencommun/">les belles
              photos
              de Monika Glet</a> issues du stock de photos libres de droit de la campagne Changer d’Approche de Mountain
            Wilderness.</li>
        </ul>
        <div class="image-container">
          <a href="https://velogrimpe.fr/?<?= $utm ?>">
            <img src="https://velogrimpe.fr/images/news/2025-10/accueil.webp" />
          </a>
        </div>
        <ul>
          <li>Filtres par le nombre de voies et le type d’escalade dans le tableau et la carte principale. On a donc
            fait
            une
            passe sur toutes les falaises pour préciser l’ordre de grandeur du nombre de voies de chaque falaise du
            topo.
          </li>
        </ul>
        <div class="image-container">
          <a href="https://velogrimpe.fr/?<?= $utm ?>">
            <img src="https://velogrimpe.fr/images/news/2025-10/filtres.webp" style="width: 250px;" />
          </a>
        </div>
        <ul>
          <li>Ré-ouverture des contributions sur toutes les falaises (même celles du topo) pour faciliter la
            contribution
            et
            permettre d’améliorer les fiches falaises. On a aussi ajouté un bouton “suggérer une modification” sur
            chaque
            page
            falaise pour faciliter la contribution.
          </li>
        </ul>
        <div class="image-container">
          <a href="https://velogrimpe.fr/contribuer?<?= $utm ?>">
            <img src="https://velogrimpe.fr/images/news/2025-10/edition.webp" style="width: 400px;" />
          </a>
        </div>

        <h2>Un grand rassemblement vélogrimpe en préparation !</h2>
        <p>100 personnes qui se retrouveraient pour grimper en train + vélo, avec des ateliers, des discussions, des
          initiations pour accompagner les néopratiquants, ça vous dirait ? Et bien c’est un projet en cours
          d’organisation
          :
          une quinzaine de personnes motivées s’est retrouvée pour commencer à planifier tout ça, ce serait dans le
          Royans
          en
          septembre 2026, à suivre !
        </p>

        <h2>On parle de Vélogrimpe !</h2>
        <p>Présentation de vélogrimpe.fr par Florent à la <a
            href="https://www.clubalpinlyon.fr/sortie/soiree-changer-d-approche-en-m-9076.html?commission=environnement">soirée
            Changer d’Approche du CAF de Lyon</a> le 29 septembre 2025.
        </p>
        <div class="image-container">
          <img src="https://velogrimpe.fr/images/news/2025-10/caf.jpeg" />
        </div>

        <h2>Nouvelles falaises sur le site</h2>
        <p>Cet été, des contributeurs ont ajouté une vingtaine de falaises, dont de nombreuses ajoutées par Olivier
          autour de Montpellier (ville qui fait du même coup son entrée dans <a
            href="https://velogrimpe.fr/tableau.php?ville_id=19&<?= $utm ?>">la liste des “falaises à proximité
            de…”</a> du menu principal), et à Marseille avec trois sites majeurs des Calanques ajoutés par Samuel, qui a
          fourni un travail de fou furieux pour renseigner tous les détails des accès et des secteurs !! (plus de
          détails plus bas). Toutes ces contributions nous amènent à un nombre total de 150 falaises : merci !!!
        </p>
        <div class="nouvelles-falaises">

          <h3>Autour de Montpellier</h3>
          <ul>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=336&<?= $utm ?>">Moulin du trou - Saint Jean de
                Védas</a> par @olivier</li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=330&<?= $utm ?>">Roc de Pampelune - Saugras</a>
              par
              @olivier
            </li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=334&<?= $utm ?>">Castries</a> par @olivier</li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=331&<?= $utm ?>">Le Caroux (Mons-la-Trivalle)</a>
              par
              @olivier
            </li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=329&<?= $utm ?>">Le Roc Rouge</a> par @olivier
            </li>
          </ul>

          <h3>Vallée de la Drôme</h3>
          <ul>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=97&<?= $utm ?>">Valcroissant</a> par @olivier</li>
          </ul>

          <h3>Ariège</h3>
          <ul>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=337&<?= $utm ?>">Calamès</a> par @fanny</li>
          </ul>

          <h3>Royans</h3>
          <ul>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=142&<?= $utm ?>">Grotte de l’Ours</a> par @yoann
              et
              @florent
            </li>
          </ul>

          <h3>Gard</h3>
          <ul>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=325&<?= $utm ?>">Pont Saint Nicolas</a> par
              @florent
            </li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=324&<?= $utm ?>">Collias</a> par @florent</li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=323&<?= $utm ?>">Estézargues</a> par @florent</li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=322&<?= $utm ?>">Aubais</a> par @florent</li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=321&<?= $utm ?>">Rochefort du Gard</a> par
              @florent
            </li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=320&<?= $utm ?>">Montfaucon</a> par @florent</li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=288&<?= $utm ?>">Russan</a> par @florent</li>
          </ul>

          <h3>Calanques</h3>
          <ul>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=247&<?= $utm ?>">Sormiou</a> par @samuel</li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=326&<?= $utm ?>">Les Goudes</a> par @samuel</li>
            <li><a href="https://velogrimpe.fr/falaise.php?falaise_id=314&<?= $utm ?>">Marseilleveyre</a> par @samuel
            </li>
          </ul>
          <p>Ces trois zones des Calanques ont été ajoutées avec des cartes détaillées de tous les secteurs et leurs
            accès
            respectifs !!
          </p>
          <div class="image-container">
            <a href="https://velogrimpe.fr/falaise.php?falaise_id=247&<?= $utm ?>">
              <img src="https://velogrimpe.fr/images/news/2025-10/sormiou.webp" />
            </a>
          </div>
        </div>

        <h2>Autres news</h2>
        <ul>
          <li>Présence de vélogrimpe.fr sur un stand avec Mountain Wilderness au salon de l’escalade en janvier 2026 à
            Paris.
          </li>
          <li>Présence à la Cordée Jean Macé (à Lyon) pour une soirée Changer d’Approche le 25 Novembre (infos à suivre
            pour y participer !).</li>
          <li>Florian Garibal et Fanny Audigé sont en pleine campagne de financement participatif pour leur topo
            d’escalade en mobilité douce au départ de Grenoble. <a
              href="https://fr.ulule.com/topo-doux-depuis-grenoble/?utm_campaign=presale_205100&utm_source=shared-from-Ulule-project-page-on---http.referer--&utm_medium=uluid_2444055">Allez
              y faire un tour</a>, l’ouvrage est splendide !</li>
          <li>On aimerait bien cartographier Fontainebleau, mais ne connaissant pas bien le secteur, on est toujours à
            la
            recherche de connaisseurs pour nous conseiller voire aider dans ce travail.</li>
        </ul>

        <p>Et voilà ! Merci mille fois à ceux qui sont arrivés jusque-là ! Envoyez nous un petit message pour nous dire
          ce que vous en pensez ! Et si vous ne souhaitez plus recevoir de mails comme celui-ci, dites le nous, ceci
          n'est pas un mail automatique on rentre les adresses une à une 😉</p>
      </td>
    </tr>
  </table>
</body>

</html>