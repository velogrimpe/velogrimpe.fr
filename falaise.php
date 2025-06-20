<?php

$falaise_id = $_GET['falaise_id'] ?? null;
if (empty($falaise_id)) {
  echo 'Pas de falaise renseignée.';
  exit;
}

require_once "./database/velogrimpe.php";

$stmtF = $mysqli->prepare("SELECT * FROM falaises WHERE falaise_id = ?");
if (!$stmtF) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$stmtF->bind_param("i", $falaise_id);
$stmtF->execute();
$resF = $stmtF->get_result();

$dataF = $resF->fetch_assoc();
$stmtF->close();

$stmtIt = $mysqli->prepare("
  SELECT *
  FROM velo
  LEFT JOIN gares ON velo.gare_id = gares.gare_id
  WHERE velo.falaise_id = ?");
$stmtIt->bind_param("i", $falaise_id);
$stmtIt->execute();
$result = $stmtIt->get_result();
$itineraires = [];
while ($row = $result->fetch_assoc()) {
  $itineraires[] = $row;
}
$stmtIt->close();

$stmtOblyk = $mysqli->prepare("
  SELECT site_url as url, site_name as name
  FROM falaises_liens
  WHERE falaise_id = ? AND site = 'oblyk'
  ORDER BY site_name
  ");
$stmtOblyk->bind_param("i", $falaise_id);
$stmtOblyk->execute();
$result = $stmtOblyk->get_result();
$liensOblyk = [];
while ($row = $result->fetch_assoc()) {
  $liensOblyk[] = $row;
}
$stmtOblyk->close();

if (!$dataF) {
  echo "Falaise introuvable.";
  exit;
}

// Définition des variables

$falaise_nom = $dataF['falaise_nom'];
$falaise_nomformate = $dataF['falaise_nomformate'];
$falaise_cottxt = $dataF['falaise_cottxt'];
$falaise_cotmin = $dataF['falaise_cotmin'];
$falaise_cotmax = $dataF['falaise_cotmax'];
$falaise_voies = $dataF['falaise_voies'];
$falaise_expotxt = $dataF['falaise_expotxt'];
$falaise_exposhort1 = $dataF['falaise_exposhort1'];
$falaise_exposhort2 = $dataF['falaise_exposhort2'];
$falaise_matxt = $dataF['falaise_matxt'];
$falaise_maa = $dataF['falaise_maa'];
$falaise_topo = $dataF['falaise_topo'];
$falaise_gvtxt = $dataF['falaise_gvtxt'];
$falaise_rq = $dataF['falaise_rq'];
$falaise_fermee = $dataF['falaise_fermee'] ?? null;
$falaise_txt1 = $dataF['falaise_txt1'] ?? null;
$falaise_txt2 = $dataF['falaise_txt2'] ?? null;
$falaise_leg1 = $dataF['falaise_leg1'] ?? null;
$falaise_txt3 = $dataF['falaise_txt3'] ?? null;
$falaise_txt4 = $dataF['falaise_txt4'] ?? null;
$falaise_leg2 = $dataF['falaise_leg2'] ?? null;
$falaise_leg3 = $dataF['falaise_leg3'] ?? null;
$latlng = $dataF['falaise_latlng'];
$lat = trim(explode(",", $latlng)[0]);
$lng = trim(explode(",", $latlng)[1]);
$falaise_contrib_name = preg_replace("(^'|'$)", "", explode(',', $dataF['falaise_contrib'])[0]);

$ville_id_get = (int) ($_GET['ville_id'] ?? 0);

$nbvoies_corresp = [
  10 => "0-20 voies",
  20 => "~20 voies",
  35 => "20-50 voies",
  50 => "~50 voies",
  75 => "50-100 voies",
  100 => "~100 voies",
  150 => "100-200 voies",
  200 => "~200 voies",
  350 => "200-500 voies",
  500 => "~500 voies",
  1000 => "&ge; 500 voies",
];
$falaise_nbvoies = $nbvoies_corresp[$dataF['falaise_nbvoies']] ?? "inconnue";

$stmtV = $mysqli->prepare("
SELECT DISTINCT v.ville_id, v.ville_nom
FROM train t
INNER JOIN villes v ON t.ville_id = v.ville_id
WHERE t.train_public >= 1
AND EXISTS (
    SELECT 1 
    FROM velo ve
    WHERE ve.gare_id = t.gare_id AND ve.falaise_id = ?
)
ORDER BY v.ville_nom
");
if (!$stmtV) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$stmtV->bind_param("i", $falaise_id);
$stmtV->execute();
$resV = $stmtV->get_result();

$selected_ville_nom = null;
$villes = [];
while ($dataV = $resV->fetch_assoc()) {
  // Add ville to villes array
  $villes[] = [
    'ville_id' => $dataV['ville_id'],
    'ville_nom' => $dataV['ville_nom']
  ];
  // If ville_id matches the one in the URL, set selected_ville_nom
  if ($dataV['ville_id'] == $ville_id_get) {
    $selected_ville_nom = $dataV['ville_nom'];
  }
}
$stmtV->close();

?>

<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <title>Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ
      de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://velogrimpe.fr/falaise.php?falaise_id=<?= $falaise_id ?>" />
  <meta name="description"
    content="Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?>. Découvrez les accès en vélo et en train, les topos et les informations pratiques pour une sortie vélo-grimpe en mobilité douce.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title"
    content="Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url"
    content="https://velogrimpe.fr/falaise.php?falaise_id=<?= $falaise_id ?>&ville_id=<?= $ville_id_get ?>">
  <meta property="og:image" content="https://velogrimpe.fr/images/logo_velogrimpe.png">
  <meta property="og:description"
    content="Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?>. Découvrez les accès en vélo et en train, les topos et les informations pratiques pour une sortie vélo-grimpe en mobilité douce.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/logo_velogrimpe.png">
  <meta name="twitter:title"
    content="Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?>. Découvrez les accès en vélo et en train, les topos et les informations pratiques pour une sortie vélo-grimpe en mobilité douce.">

  <!-- Carte -->
  <script src=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js "></script>
  <link href=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css " rel="stylesheet">
  <!-- Carte : traces gpx -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/2.1.2/gpx.min.js"></script>
  <!-- Carte : fullscreen -->
  <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
  <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css'
    rel='stylesheet' />
  <!-- Carte : locate -->
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.84.2/dist/L.Control.Locate.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol@0.84.2/dist/L.Control.Locate.min.js"
    charset="utf-8"></script>
  <!-- Carte : Lignes de train-->
  <script src="https://unpkg.com/protomaps-leaflet@5.0.1/dist/protomaps-leaflet.js"></script>
  <!-- Carte : Pour les détails falaise-->
  <script src="/js/vendor/leaflet-textpath.js"></script>
  <!-- Styles -->
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Barycentre -->
  <script src="https://cdn.jsdelivr.net/npm/@turf/turf@7/turf.min.js"></script>
  <!-- Rose des vents -->
  <script src="https://d3js.org/d3.v7.min.js"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <link rel="stylesheet" href="/global.css">
  <link rel="stylesheet" href="falaise.css">
  <!-- Rose des vents -->
  <script src="js/rose-des-vents.js"></script>
  <style type="text/tailwindcss">
    @tailwind base;
    @tailwind components;
    @tailwind utilities;
    @layer base {
      .vg-a-primary a {
        @apply text-[#2e8b57] font-bold;
      }
  }
  </style>

</head>



<body>
  <?php include "./components/header.html"; ?>
  <main class="max-w-screen-lg mx-auto p-4 flex flex-col items-center gap-4 bg-base-100 my-2 rounded-xl">

    <div class="flex justify-between items-center w-full">
      <a class="text-primary font-bold" href="/">← Retour à la carte</a>
      <div class="flex flex-row items-center gap-2">
        <div class="dropdown dropdown-end hidden">
          <div tabindex="0" role="button"
            class="btn btn-sm md:btn-md btn-circle btn-outline btn-primary focus:pointer-events-none" title="J'y ai été"
            id="veloFilterBtn">
            <svg class="w-4 md:w-6 h-4 md:h-6 fill-current">
              <use xlink:href="/symbols/icons.svg#ri-chat-4-line"></use>
            </svg>
          </div>
          <div class="dropdown-content gap-1 menu bg-base-200 rounded-box z-[1] m-1 w-64 p-2 shadow-lg">
            <a class="btn btn-primary btn-outline btn-sm py-1 h-fit"
              href="/ajout_commentaire.php?falaise_id=<?= $falaise_id ?>">
              Raconter ma sortie vélogrimpe
            </a>
          </div>
        </div>
        <div class="dropdown dropdown-end hidden">
          <div tabindex="0" role="button" class="btn btn-sm md:btn-md btn-circle btn-outline focus:pointer-events-none"
            title="Proposer des modifications" id="veloFilterBtn">
            <svg class="w-4 md:w-6 h-4 md:h-6 fill-current">
              <use xlink:href="/symbols/icons.svg#ri-pencil-line"></use>
            </svg>
          </div>
          <div class="dropdown-content gap-1 menu bg-base-200 rounded-box z-[1] m-1 w-64 p-2 shadow-lg">
            <a class="btn btn-primary btn-outline btn-sm py-1 h-fit"
              href="/edition/commentaire_falaise.php?falaise_id=<?= $falaise_id ?>">
              Modifier la fiche falaise
            </a>
            <a class="btn btn-primary btn-outline btn-sm py-1 h-fit"
              href="/edition/commentaire_velo.php?falaise_id=<?= $falaise_id ?>">
              Modifier un accès vélo
            </a>
            <a class="btn btn-primary btn-outline btn-sm py-1 h-fit"
              href="/ajout/ajout_velo.php?falaise_id=<?= $falaise_id ?>">
              Ajouter un accès vélo
            </a>
            <a class="btn btn-primary btn-outline btn-sm py-1 h-fit"
              href="/ajout/ajout_train.php?falaise_id=<?= $falaise_id ?>">
              Ajouter un accès train
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Message si la falaise est interdite -->
    <?php if (!empty($falaise_fermee)): ?>
      <div class="alert text-center flex flex-col items-center">
        <div class="text-error font-bold text-2xl">
          FALAISE INTERDITE !
        </div>
        <div class="text-error">
          <?= nl2br($falaise_fermee) ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="flex flex-col items-center mb-10 gap-4">
      <h1 class="inline-flex flex-col text-[48px] font-bold text-center leading-none text-primary">
        <?= htmlspecialchars($falaise_nom) ?>
        <?php if ($ville_id_get): ?>
          <br>
          <span class="text-base font-normal">au départ de
            <?= htmlspecialchars($selected_ville_nom) ?></span>
        <?php endif; ?>
      </h1>

      <button class="drawer-button btn btn-neutral btn-sm rounded-full btn-outline" onclick="meteoModal.showModal()">
        Météo
        <span class="flex items-center gap-1">
          <svg class="w-4 h-4 fill-[gold]">
            <use xlink:href="/symbols/icons.svg#ri-sun-foggy-fill"></use>
          </svg>
          <span class="font-normal">/</span>
          <svg class="w-4 h-4 fill-[LightSlateGray]">
            <use xlink:href="/symbols/icons.svg#ri-sun-cloudy-fill"></use>
          </svg>
        </span>
      </button>
      <dialog id="meteoModal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box md:w-fit max-w-screen-xl">
          <form method="dialog">
            <button tabindex="-1" class="btn btn-circle btn-ghost absolute right-2 top-2">✕</button>
          </form>
          <div class="p-4 w-[240px] font-bold mx-auto">
            <span class="text-lg font-bold">
              Météo par <a class="text-primary font-bold"
                href="https://www.meteoblue.com/fr/meteo/semaine/<?= $lat ?>N<?= $lng ?>E391_Europe%2FParis?utm_source=daily_widget&utm_medium=linkus&utm_content=daily&utm_campaign=Weather%2BWidget"
                target="_blank" rel="noopener">meteoblue
              </a>
            </span>
            <iframe
              src="https://www.meteoblue.com/fr/meteo/widget/daily/<?= $lat ?>N<?= $lng ?>E391_Europe%2FParis?geoloc=fixed&days=4&tempunit=CELSIUS&windunit=KILOMETER_PER_HOUR&precipunit=MILLIMETER&coloured=coloured&pictoicon=1&maxtemperature=1&mintemperature=1&windspeed=1&windgust=0&winddirection=1&uv=0&humidity=0&precipitation=1&precipitationprobability=1&spot=1&pressure=0&layout=light"
              frameborder="0" scrolling="NO" allowtransparency="true"
              sandbox="allow-same-origin allow-scripts allow-popups allow-popups-to-escape-sandbox"
              style="width: 216px; height: 350px"></iframe>
          </div>
        </div>
        <form method="dialog" class="modal-backdrop">
          <button>close</button>
        </form>
      </dialog>
    </div>


    <div class="flex flex-col items-center gap-4 w-full md:flex-row md:items-start">

      <!-- TABLEAU STATIQUE DESCRIPTION FALAISE -->
      <div class="vg-a-primary flex flex-col gap-4 md:gap-10 w-full items-center md:my-auto max-w-[600px] mx-auto">
        <div class="flex flex-row gap-2 items-start justify-around w-full">
          <div class="flex flex-col items-center justify-start gap-2">
            <img src="/images/abacus_color.png" alt=" Logo Nb voies" class="h-12 w-12 mx-auto" />
            <div class="font-bold text-center text-lg"><?= $falaise_nbvoies ?></div>
          </div>
          <div class="flex flex-col items-center justify-start gap-2">
            <img src="/images/speedometer_color.png" alt=" Logo difficulté" class="h-12 w-12 mx-auto" />
            <div class="font-bold text-center text-lg">
              <?= $falaise_cotmin ?> à <?= $falaise_cotmax ?>
            </div>
          </div>
        </div>

        <div class="flex flex-row gap-2 items-center justify-center mx-auto">
          <div class='w-full grid grid-cols-[auto_auto] gap-4 md:gap-y-6 items-center'>
            <img src="/images/rock-climbing_color.png" alt=" Voies" class="h-12 w-12 mx-auto" />
            <!-- <div class="font-bold ">Voies</div> -->
            <div class="">
              <?= nl2br($falaise_voies) ?>
              <?php if (!empty($falaise_cottxt)): ?>
                <div><span>Cotations</span> :
                  <?= nl2br(mb_strtolower(substr($falaise_cottxt, 0, 1))) . nl2br(substr($falaise_cottxt, 1)) ?>
                </div>
              <?php endif ?>
            </div>
            <img src="/images/guidebook_color.png" alt="Topo" class="h-12 w-12 mx-auto" />
            <!-- <div class="font-bold  ">Topo(s)</div> -->
            <div class="">
              <div><?= nl2br($falaise_topo) ?></div>
              <?php if (count($liensOblyk) > 1): ?>
                <div class="dropdown w-fit">
                  <a tabindex="0" role="button"
                    class="font-normal text-nowrap focus:pointer-events-none flex items-center gap-1"
                    id="approcheFilterBtn">
                    Fiches Oblyk
                    <span class="badge badge-sm badge-primary"><?= count($liensOblyk) ?></span>
                  </a>
                  <div
                    class="dropdown-content menu bg-base-200 rounded-box z-10 m-1 p-2 shadow-lg w-60 max-h-[250px] flex-nowrap overflow-auto"
                    tabindex="1">
                    <?php foreach ($liensOblyk as $lien): ?>
                      <a target="_blank" href="<?= htmlspecialchars($lien['url']) ?>"
                        class="text-primary font-bold hover:underline cursor-pointer">
                        <span><?= htmlspecialchars($lien['name']) ?></span>&nbsp;<svg class="w-3 h-3 fill-current inline">
                          <use xlink:href="/symbols/icons.svg#ri-external-link-line"></use>
                        </svg>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php elseif (count($liensOblyk) == 1): ?>
                <a target="_blank" href="<?= htmlspecialchars($liensOblyk[0]['url']) ?>"
                  class="text-primary font-bold hover:underline cursor-pointer">
                  Fiche Oblyk
                </a>
              <?php endif ?>
            </div>
            <img src="/images/hiking_color.png" alt=" Approche" class="h-12 w-12 mx-auto" />
            <!-- <div class="font-bold  ">Approche</div> -->
            <div class=""><?= nl2br($falaise_matxt) ?></div>
            <?php if (!empty($falaise_gvtxt)): ?>
              <img src="/images/mountain_color.png" alt=" Grande voies" class="h-12 w-12 mx-auto" />
              <!-- <div class="font-bold  ">Grandes voies</div> -->
              <div class="">
                <?= nl2br($falaise_gvtxt) ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($falaise_rq)): ?>
              <img src="/images/note_color.png" alt=" Remarques" class="h-12 w-12 mx-auto" />
              <!-- <div class="font-bold ">Remarques</div> -->
              <div class=""><?= nl2br($falaise_rq) ?></div>
            <?php endif; ?>

            <!-- <img src="/images/expo.png" alt="Exposition" class="h-12 w-12 mx-auto" /> -->
            <div id="rose-des-vents"></div>
            <!-- <div id="rose-mini" class="sm:hidden"></div> -->
            <!-- <div class="font-bold self-stretch flex items-center">Exposition</div> -->
            <div class=" flex flex-row gap-2 items-center">
              <?= nl2br($falaise_expotxt) ?>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Texte optionnel 2 (juste après le tableau descriptif) -->
    <?php if (!empty($falaise_txt2)): ?>
      <div>
        <?= nl2br($falaise_txt2) ?>
      </div>
    <?php endif; ?>

    <!-- Menu déroulant pour choisir la ville de départ -->
    <form id="dropdown_menu" class="flex flex-col md:flex-row items-center justify-center gap-2 w-full">
      <?php
      // s'il n'y a pas de villes de départ possible, on n'affiche pas le menu déroulant
      if (count($villes) === 0): ?>
        <div class='text-center'>
          <div>Pas d'itinéraire train décrit pour cette falaise.</div>
          <a class="btn btn-primary btn-xs" href="/ajout/ajout_train.php">Proposer un itinéraire en train</a>
        </div>
      <?php else: ?>
        <div>Vous partez de :</div>
        <select name="ville_id" class="select select-bordered select-primary"
          onchange="location.href='?falaise_id=<?= urlencode($falaise_id) ?>&ville_id=' + this.value;">
          <option value="" <?= !$ville_id_get ? 'selected' : '' ?>>-- Choisir une ville de départ --</option>
          <?php foreach ($villes as $ville): ?>
            <option value="<?= $ville['ville_id'] ?>" <?= $ville['ville_id'] == $ville_id_get ? 'selected' : '' ?>>
              <?= htmlspecialchars($ville['ville_nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </form>


    <?php
    // Fonction pour formater le temps en h'm
    
    function format_time($minutes)
    {
      if ($minutes === null) {
        return "";
      }
      $hours = floor($minutes / 60);
      $remaining_minutes = $minutes % 60;

      if ($hours > 0) {
        return sprintf("%dh%02d", $hours, $remaining_minutes);
      } else {
        return sprintf("%d'", $remaining_minutes);
      }
    }

    //Calculatrice de temps de trajet vélo (km/20+dplus/500 à vélo, km/4+dplus/500 à pied)
    
    function calculate_time($distance_km, $elevation_m, $velo_apieduniquement)
    {
      if ($velo_apieduniquement == 1) {
        $time_in_hours = $distance_km / 4 + $elevation_m / 500;
      } else {
        $time_in_hours = $distance_km / 20 + $elevation_m / 500;
      }
      $time_in_minutes = round($time_in_hours * 60);
      return $time_in_minutes;
    }

    $stmtG = $mysqli->prepare("
    SELECT DISTINCT g.gare_id, g.gare_nom
    FROM velo v
    INNER JOIN gares g ON v.gare_id = g.gare_id
    LEFT JOIN train t ON v.gare_id = t.gare_id AND t.ville_id = ?
    WHERE v.falaise_id = ?
    ORDER BY t.ville_id desc, v.velo_km, g.gare_nom
    ");
    if (!$stmtG) {
      die("Problème de préparation de la requête : " . $mysqli->error);
    }
    $stmtG->bind_param("ii", $ville_id_get, $falaise_id);
    $stmtG->execute();
    $resG = $stmtG->get_result();

    $gares = [];
    while ($dataG = $resG->fetch_assoc()) {
      $gares[] = [
        'gare_id' => $dataG['gare_id'],
        'gare_nom' => $dataG['gare_nom']
      ];
    }
    $stmtG->close();


    foreach ($gares as $gare) {
      $stmtT = $mysqli->prepare("
        SELECT t.train_temps, t.train_descr, t.train_correspmin, t.train_correspmax
        FROM train t
        WHERE t.ville_id = ? AND t.gare_id = ?
    ");
      $stmtT->bind_param("ii", $ville_id_get, $gare['gare_id']);
      $stmtT->execute();
      $resT = $stmtT->get_result();
      $dataT = $resT->fetch_assoc();
      $stmtT->close();

      $stmtVG = $mysqli->prepare("
        SELECT v.ville_id, v.ville_nom
        FROM villes v
        INNER JOIN train t ON v.ville_id = t.ville_id
        WHERE t.gare_id = ?
    ");
      $stmtVG->bind_param("i", $gare['gare_id']);
      $stmtVG->execute();
      $resVG = $stmtVG->get_result();
      $villesFrom = [];
      while ($dataVG = $resVG->fetch_assoc()) {
        $villesFrom[] = $dataVG;
      }
      $stmtVG->close();

      $train_temps = $dataT['train_temps'] ?? null;

      $train_descr = $dataT['train_descr'] ?? null;
      $train_correspmin = $dataT['train_correspmin'] ?? null;
      $train_correspmax = $dataT['train_correspmax'] ?? null;

      $formatted_time = $train_temps !== null ? format_time($train_temps) : null;
      $corresp_text = null;
      if ($train_correspmin !== null && $train_correspmax !== null) {
        $corresp_text = ($train_correspmin === $train_correspmax)
          ? "Nb. corresp. : $train_correspmin"
          : "Nb.  corresp. : de $train_correspmin à $train_correspmax";
      }

      $stmtVelo = $mysqli->prepare("
        SELECT
          v.velo_id, v.velo_km, v.velo_dplus, v.velo_dmoins, v.velo_descr,
          v.velo_variante, v.velo_apieduniquement, velo_varianteformate,
          velo_depart, velo_arrivee, velo_openrunner
        FROM velo v
        WHERE v.gare_id = ? AND v.falaise_id = ?
    ");
      $stmtVelo->bind_param("ii", $gare['gare_id'], $falaise_id);
      $stmtVelo->execute();
      $resVelo = $stmtVelo->get_result();

      //Calcul des temps de trajet pour tous les itinéraires gare->falaise
      $velo_itineraires = [];
      while ($dataVelo = $resVelo->fetch_assoc()) {
        $dataVelo['velo_tpsa_calculated'] = calculate_time($dataVelo['velo_km'], $dataVelo['velo_dplus'], $dataVelo['velo_apieduniquement']);
        $dataVelo['velo_tpsr_calculated'] = calculate_time($dataVelo['velo_km'], $dataVelo['velo_dmoins'], $dataVelo['velo_apieduniquement']);
        $velo_itineraires[] = $dataVelo;
      }
      $stmtVelo->close();
      $shortest_velo_time = min(array_column($velo_itineraires, 'velo_tpsa_calculated'));
      ?>

      <div class="collapse collapse-arrow rounded-xl shadow-lg overflow-hidden w-full bg-base-100">
        <input type="checkbox" />
        <div
          class='collapse-title bg-base-200 text-base-content cursor-pointer min-h-0 flex gap-2 items-center justify-between'>
          <div class="text-lg">
            Accès via la gare de <span class="font-bold capitalize">
              <?php echo htmlspecialchars($gare['gare_nom']) ?>
            </span>
            <?php if ($selected_ville_nom && $train_descr): ?>
              : <span class="font-bold text-primary">
                <?php echo format_time($shortest_velo_time + $train_temps + $falaise_maa) ?>
              </span>
            <?php endif ?>
          </div>
          <div class="hidden md:block">
            <div class="text-sm text-slate-400">
              🚲 - <?= format_time($shortest_velo_time) ?>
              (<?= htmlspecialchars($velo_itineraires[0]['velo_km']) ?> km,
              <?= htmlspecialchars($velo_itineraires[0]['velo_dplus']) ?> D+)
            </div>
            <?php if ($train_temps): ?>
              <div class="text-sm text-slate-400">🚆 - <?= format_time($train_temps) ?>
                (<?= $train_correspmin > 0 ? $train_correspmin . ' Corresp.' : 'Direct' ?>)
              </div>
            <?php endif ?>
          </div>
        </div>
        <!-- CREATION DES TABLEAUX DYNAMIQUES -->
        <table class='collapse-content table bg-base-100 border-spacing-0'>
          <colgroup>
            <col style='width: 30%;'>
            <col style='width: 70%;'>
          </colgroup>

          <!-- // LIGNE 1 "ACCES DEPUIS LA GARE..." -->
          <!-- <thead>
          <tr>
            <td class='rounded-t-xl text-center text-lg font-bold bg-base-200 text-base-content text-wrap' colspan='2'>
              Accès depuis la gare de : <?php echo htmlspecialchars(mb_strtoupper($gare['gare_nom'], 'UTF-8')) ?>
              <?php if ($selected_ville_nom): ?>
                Total : <?php echo format_time($shortest_velo_time + $train_temps + $falaise_maa) ?>
              <?php endif ?>
            </td>
          </tr>
        </thead> -->

          <!-- // LIGNE 2 TRAIN : -->
          <tr>
            <td class="justify-center border-t border-r border-b border-1 border-base-300">
              <div class="flex flex-col md:flex-row gap-4 items-center">
                <img src="/images/train-station_color.png" alt="Logo Train" class="h-10 w-auto">
                <div>
                  <?php if ($selected_ville_nom): ?>
                    <b><?= htmlspecialchars($selected_ville_nom) . " → " . htmlspecialchars($gare["gare_nom"]) ?></b>
                  <?php else: ?>
                    Rejoindre la gare de : <b><?= htmlspecialchars($gare["gare_nom"]) ?></b>
                  <?php endif; ?>

                  <?php if (!empty($formatted_time)): ?>
                    : <span class="text-lg font-bold"><?= $formatted_time ?></span>
                  <?php endif ?>
                  <?php if (!empty($corresp_text)): ?>
                    <br>
                    <?= nl2br($corresp_text) ?>
                  <?php endif ?>
                  <!-- <button class="btn btn-xs btn-outline btn-accent" onclick="gare<?= $gare["gare_id"] ?>.showModal()">
                    <svg class="w-3 md:w-4 h-3 md:h-4 fill-current">
                      <use xlink:href="/symbols/icons.svg#ri-ticket-line"></use>
                    </svg>
                    Acheter un billet
                  </button>
                  <dialog id="gare<?= $gare["gare_id"] ?>" class="modal">
                    <div class="modal-box p-0 max-w-screen-lg w-full bg-transparent"
                      id="container__booking__gare_<?= $gare["gare_id"] ?>">
                    </div>
                    <form method="dialog" class="modal-backdrop">
                      <button>close</button>
                    </form>
                  </dialog> -->
                </div>
              </div>
            </td>

            <td class='border-t border-b border-1 border-base-300'>
              <?php if ($ville_id_get): ?>
                <?php if ($train_descr): ?>
                  <?= nl2br($train_descr) ?>
                <?php else: ?>
                  Itinéraire non décrit (soit il est peu pertinent, soit j'ai pas eu le temps !).
                <?php endif ?>
              <?php else: ?>
                <div>
                  <?php if (count($villesFrom) > 0): ?>
                    Accès décrits depuis:
                    <ul class="list-disc pl-6">
                      <?php foreach ($villesFrom as $villeFrom): ?>
                        <li>
                          <a class="text-primary font-bold hover:underline cursor-pointer"
                            href="?falaise_id=<?= htmlspecialchars($falaise_id) ?>&ville_id=<?= htmlspecialchars($villeFrom['ville_id']) ?>">
                            <?= htmlspecialchars($villeFrom['ville_nom']) ?>
                          </a>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    Pas d'accès train décrits depuis cette gare.
                    <a class="btn btn-primary btn-xs"
                      href="/ajout/ajout_train.php?falaise_id=<?= htmlspecialchars($falaise_id) ?>&gare_id=<?= htmlspecialchars($gare['gare_id']) ?>">Proposer
                      un accès train</a>
                  <?php endif ?>
                </div>
              <?php endif ?>
            </td>
          </tr>

          <!-- // LIGNES VELO -->
          <?php foreach ($velo_itineraires as $velo): ?>
            <tr>
              <td class='justify-center border-t border-r border-b border-1 border-base-300'>
                <div class='flex flex-col md:flex-row gap-4 items-center'>

                  <?php if (isset($velo['velo_apieduniquement']) && $velo['velo_apieduniquement'] == 1): ?>
                    <img src="/images/hiking_color.png" alt="Logo À Pied" class="h-auto w-10">
                  <?php else: ?>
                    <img src="/images/bicycle_color.png" alt="Logo Vélo" class="h-auto w-10">
                  <?php endif ?>

                  <div class='flex flex-col items-start'>
                    <?php if (!empty($velo['velo_variante'])): ?>
                      <div class='text-slate-400'><?= htmlspecialchars($velo['velo_variante']) ?></div>
                    <?php endif ?>
                    <div>Aller : <span
                        class='text-lg font-bold'><?= htmlspecialchars(format_time($velo['velo_tpsa_calculated'])) ?></span>
                    </div>
                    <div>Retour : <span
                        class='text-lg font-bold'><?= htmlspecialchars(format_time($velo['velo_tpsr_calculated'])) ?></span>
                    </div>
                  </div>
                </div>
              </td>

              <td class='border-t border-b border-1 border-base-300'>
                <?= htmlspecialchars($velo['velo_km']) . " km, " . htmlspecialchars($velo['velo_dplus']) . " D+, " . htmlspecialchars($velo['velo_dmoins']) . " D-." ?>
                <br>
                <?= nl2br($velo['velo_descr']) ?>
                <br>
                <?php if ($velo['velo_openrunner']): ?>
                  <!-- Desktop : ouvre juste en dessous -->
                  <a class="font-bold text-primary hidden md:inline" href='#'
                    onclick="document.getElementById('profil_<?= $velo['velo_id'] ?>').classList.toggle('hidden'); return false;">
                    Profil altimétrique
                  </a>
                  <!-- Mobile : ouvre dans un dialog -->
                  <a class="text-primary font-bold hover:underline cursor-pointer inline md:hidden"
                    onclick="document.getElementById('profil_<?= $velo['velo_id'] ?>_modal').showModal()">
                    Profil altimétrique
                  </a>
                <?php endif; ?>
                <?php
                $gpx_path = "./bdd/gpx/" . $velo['velo_id'] . '_' . $velo['velo_depart'] . '_' . $velo['velo_arrivee'] . '_' . $velo['velo_varianteformate'] . ".gpx";
                $exists = file_exists($gpx_path);
                if ($velo['velo_openrunner'] && $exists): ?>
                  |
                <?php endif; ?>
                <?php
                if ($exists):
                  ?>
                  <a class="font-bold text-primary" href="<?= htmlspecialchars($gpx_path) ?>" target='_blank'>Trace GPS</a>
                <?php endif; ?>


                <!-- Desktop : div en dessous -->
                <div id="profil_<?= $velo['velo_id'] ?>" class="hidden mt-2">
                  <iframe width="100%" height="650" loading="lazy" src="<?= $velo['velo_openrunner'] ?>"
                    style="border: none;"></iframe>
                </div>
                <!-- Mobile : ouvre dans un dialog -->
                <dialog id="profil_<?= $velo['velo_id'] ?>_modal" class="modal modal-bottom">
                  <div class="modal-box md:w-4/5 max-w-3xl m-0 pt-10 p-4">
                    <form method="dialog">
                      <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                    </form>

                    <iframe width="100%" height="650" loading="lazy" src="<?= $velo['velo_openrunner'] ?>"
                      style="border: none;"></iframe>
                  </div>
                </dialog>

              </td>
            </tr>
          <?php endforeach ?>
        </table>
      </div>
    <?php } ?>

    <!-- Remarque entre tableaux dynamique et tableau descriptif (rq générale sur l'accès) -->
    <?php if (!empty($falaise_txt1)): ?>
      <div>
        <?= nl2br($falaise_txt1) ?>
      </div>
    <?php endif; ?>

    <!-- Remarque spécifique pour l'accès entre une ville V et la falaise F (table rqvillefalaise, champ rqvillefalaise_txt) -->
    <?php if ($ville_id_get): ?>
      <?php
      $stmtRqVF = $mysqli->prepare("
            SELECT rqvillefalaise_txt 
            FROM rqvillefalaise 
            WHERE ville_id = ? AND falaise_id = ?
        ");
      if (!$stmtRqVF) {
        die("Problème de préparation de la requête : " . $mysqli->error);
      }
      $stmtRqVF->bind_param("ii", $ville_id_get, $falaise_id);
      $stmtRqVF->execute();
      $resRqVF = $stmtRqVF->get_result();
      $dataRqVF = $resRqVF->fetch_assoc();
      $stmtRqVF->close();

      if (!empty($dataRqVF['rqvillefalaise_txt'])):
        ?>
        <div>
          <?= nl2br(htmlspecialchars($dataRqVF['rqvillefalaise_txt'])) ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>



    <div class="flex flex-col items-center gap-2 w-full mb-4">
      <div id="map" class="h-[600px] w-full bg-black rounded-lg"></div>
    </div>

    <!-- Image optionnelle 1 -->
    <?php if (urlExists("https://www.velogrimpe.fr/bdd/images_falaises/" . htmlspecialchars($falaise_id) . "_" . htmlspecialchars($falaise_nomformate) . "_img1.png")): ?>
      <div class="flex flex-col items-center gap-1">
        <?php
        echo '<img src="https://www.velogrimpe.fr/bdd/images_falaises/' . htmlspecialchars($falaise_id) . '_' . htmlspecialchars($falaise_nomformate) . '_img1.png" class="border-1 border-base-300 rounded-xl shadow-lg md:w-4/5">';
        if (!empty($falaise_leg1)) {
          echo '<div class="text-base-content">' . nl2br($falaise_leg1) . '</div>';
        }
        ?>
      </div>
    <?php endif; ?>


    <?php if (!empty($falaise_txt3)): ?>
      <div>
        <?= nl2br($falaise_txt3) ?>
      </div>
    <?php endif; ?>

    <!-- Fonction pour vérifier si une URL existe -->

    <?php
    function urlExists($url)
    {
      $headers = @get_headers($url);
      return $headers && strpos($headers[0], '200') !== false;
    }
    ?>

    <!-- Image optionnelle 2 -->
    <?php if (urlExists("https://www.velogrimpe.fr/bdd/images_falaises/" . htmlspecialchars($falaise_id) . "_" . htmlspecialchars($falaise_nomformate) . "_img2.png")): ?>
      <div class="flex flex-col items-center gap-1">
        <?php
        echo '<img src="https://www.velogrimpe.fr/bdd/images_falaises/' . htmlspecialchars($falaise_id) . '_' . htmlspecialchars($falaise_nomformate) . '_img2.png" class="border-1 border-base-300 rounded-xl shadow-lg md:w-4/5">';
        if (!empty($falaise_leg2)) {
          echo '<div class="text-base-content">' . nl2br($falaise_leg2) . '</div>';
        }
        ?>
      </div>
    <?php endif; ?>



    <!-- Texte optionnel numéro 4 -->
    <?php if (!empty($falaise_txt4)): ?>
      <div>
        <?= nl2br($falaise_txt4) ?>
      </div>
    <?php endif; ?>

    <!-- Image optionnelle 3 -->
    <?php if (urlExists("https://www.velogrimpe.fr/bdd/images_falaises/" . htmlspecialchars($falaise_id) . "_" . htmlspecialchars($falaise_nomformate) . "_img3.png")): ?>
      <div class="flex flex-col items-center gap-1">
        <?php
        echo '<img src="https://www.velogrimpe.fr/bdd/images_falaises/' . htmlspecialchars($falaise_id) . '_' . htmlspecialchars($falaise_nomformate) . '_img3.png" class="border-1 border-base-300 rounded-xl shadow-lg md:w-4/5">';
        if (!empty($falaise_leg3)) {
          echo '<div class="text-base-content">' . nl2br($falaise_leg3) . '</div>';
        }
        ?>
      </div>
    <?php endif; ?>

    <div class="text-center text-slate-600 text-sm italic opacity-60">
      Falaise ajoutée par <?= $falaise_contrib_name ?>
    </div>

  </main>

  <script>

    const ignTiles = L.tileLayer(
      "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&FORMAT=image/png&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}", {
      maxZoom: 19,
      minZoom: 0,
      attribution: "IGN-F/Geoportail",
      crossOrigin: true,
    })
    const ignOrthoTiles = L.tileLayer(
      "https://data.geopf.fr/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}", {
      maxZoom: 18,
      minZoom: 0,
      tileSize: 256,
      attribution: "IGN-F/Geoportail",
      crossOrigin: true,
    })
    const landscapeTiles = L.tileLayer(
      "https://{s}.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
      maxZoom: 19,
      minZoom: 0,
      attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      crossOrigin: true,
    })
    const outdoorsTiles = L.tileLayer(
      "https://{s}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
      maxZoom: 19,
      minZoom: 0,
      attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      crossOrigin: true,
    })
    var baseMaps = {
      "Landscape": landscapeTiles,
      'IGNv2': ignTiles,
      'Satellite': ignOrthoTiles,
      'Outdoors': outdoorsTiles,
    };
  </script>
  <script type="module">

    import Falaise from "/js/components/map/falaise.js";
    import Velo from "/js/components/map/velo.js";
    import AccesVelo from "/js/components/map/acces-velo.js";
    import Secteur from "/js/components/map/secteur.js";
    import Approche from "/js/components/map/approche.js";
    import Parking from "/js/components/map/parking.js";
    import FalaiseVoisine from "/js/components/map/falaise-voisine.js";
    import Gare from "/js/components/map/gare.js";

    const falaise = <?php echo json_encode($dataF); ?>;
    const itineraires = <?php echo json_encode($itineraires); ?>;

    const center = falaise.falaise_latlng.split(",").map(parseFloat);
    const zoom = 13;
    const bounds = [
      falaise.falaise_latlng.split(",").map(parseFloat),
      itineraires.map(it => it.gare_latlng.split(",").map(parseFloat))
    ];
    var map = L.map("map", { layers: [landscapeTiles], center, zoom, fullscreenControl: true });
    L.control.locate().addTo(map);

    map.fitBounds(bounds, { maxZoom: 15 });
    var layerControl = L.control.layers(baseMaps, undefined, { position: "topleft", size: 22 }).addTo(map);

    //  --- Ajout des lignes de train ---
    const paintRules = [
      {
        dataLayer: "fr",
        symbolizer: new protomapsL.LineSymbolizer({
          color: "#000",
          width: (z) => (z <= 6 ? 0.5 : z < 9 ? 1 : 1.5),
        })
      }
    ]
    var trainLayer = protomapsL.leafletLayer({ url: '/bdd/trains/trainlines.pmtiles', paintRules, maxDataZoom: 16, pane: "overlayPane" })
    trainLayer.addTo(map);

    // --- Ajout de la falaise et itinéraires vélos ---
    const falaiseObject = new Falaise(map, falaise);
    const veloObjects = itineraires.map((velo, index) => new Velo(map, velo, { index }));
    const gareObjects = (
      itineraires
        .map(it => ({ gare_nom: it.gare_nom, gare_latlng: it.gare_latlng }))
        .reduce((acc, gare) => {
          if (acc.find(g => g.gare_nom === gare.gare_nom)) {
            return acc;
          }
          return [...acc, gare];
        }, [])
        .map((it, index) => new Gare(map, it))
    );

    const featureMap = {};

    const updateAssociations = () => {
      const features = Object.values(featureMap);
      features.forEach(feature => {
        feature.updateAssociations(features);
      })
    }
    fetch(`/api/private/falaise_details.php?falaise_id=${falaise.falaise_id}`).then(response => {
      if (!response.ok) {
        throw new Error("Erreur lors de la récupération des détails de la falaise");
      }
      return response.json();
    })
      .then((data) => {
        let id = 0;
        if (data.features && data.features.length > 0) {
          data.features.forEach(feature => {
            let obj;
            if (feature.properties.type === "secteur" || feature.properties.type === undefined) {
              if (Secteur.isInvalidSecteur(feature)) return;
              obj = new Secteur(map, feature);
            } else if (feature.properties.type === "approche") {
              obj = new Approche(map, feature);
            } else if (feature.properties.type === "acces_velo") {
              obj = new AccesVelo(map, feature);
            } else if (feature.properties.type === "parking") {
              obj = new Parking(map, feature);
            } else if (feature.properties.type === "falaise_voisine") {
              obj = new FalaiseVoisine(map, feature);
            }
            obj._element_id = id++;
            if (obj) {
              featureMap[obj._element_id] = obj;
            }
          });
          updateAssociations();
          map.flyTo(falaise.falaise_latlng.split(","), 14, { duration: 0.25 });

        }
      })
      .catch(error => {
        console.error("Erreur lors du chargement des données de falaise :", error);
      });

  </script>

  <?php include "./components/footer.html"; ?>
</body>

</html>