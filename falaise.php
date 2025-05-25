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
  <title>Falaise : <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ
      de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Carte -->
  <script src=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js "></script>
  <link href=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css " rel="stylesheet">
  <!-- Carte : traces gpx -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/2.1.2/gpx.min.js"></script>
  <!-- Carte : fullscreen -->
  <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
  <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css'
    rel='stylesheet' />
  <!-- Carte : Lignes de train-->
  <script src="https://unpkg.com/protomaps-leaflet@5.0.1/dist/protomaps-leaflet.js"></script>
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
  <div class="max-w-screen-lg mx-auto p-4 flex flex-col items-center gap-4 bg-base-100 my-2 rounded-xl">

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
                <div><span>Cotations</span>: <?= nl2br($falaise_cottxt) ?></div>
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

  </div>

  <script>
    // Paramètres généraux
    const iconSize = 30;
    const defaultMarkerSize = iconSize;
    const hoverMarkerSize = iconSize * 1.5;
    // const itinerairesColors = ["indianRed", "tomato", "salmon", "lightSalmon", "fireBrick", "darkorange"]
    const itinerairesColors = ["indianRed", "tomato", "teal", "paleVioletRed", "mediumSlateBlue", "lightSalmon", "fireBrick", "crimson", "purple", "hotPink", "mediumOrchid"]
    const icon = (size) =>
      L.icon({
        iconUrl: "http://www.velogrimpe.fr/images/icone_falaise_carte.png",
        iconSize: [size, size],
        iconAnchor: [size / 2, size],
      });
    const trainIcon = (size = 24) => {
      return L.icon({
        iconUrl: "http://www.velogrimpe.fr/images/icone_train_carte.png",
        className: "train-icon" + (size === 24 ? " bgwhite" : " bgblue"),
        iconSize: [size, size],
        iconAnchor: [size / 2, size / 2],
      });
    };
    const ignTiles = L.tileLayer(
      "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&FORMAT=image/png&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}", {
      maxZoom: 19,
      minZoom: 0,
      attribution: "IGN-F/Geoportail",
      name: "IGNv2",
      crossOrigin: true,
    })
    const ignOrthoTiles = L.tileLayer(
      "https://data.geopf.fr/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}", {
      maxZoom: 18,
      minZoom: 0,
      tileSize: 256,
      attribution: "IGN-F/Geoportail",
      name: "IGNv2",
      crossOrigin: true,
    })
    const landscapeTiles = L.tileLayer(
      "https://{s}.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
      maxZoom: 19,
      minZoom: 0,
      attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      name: "IGNv2",
      crossOrigin: true,
    })
    const outdoorsTiles = L.tileLayer(
      "https://{s}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
      maxZoom: 19,
      minZoom: 0,
      attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      name: "IGNv2",
      crossOrigin: true,
    })
    var baseMaps = {
      "Landscape": landscapeTiles,
      'IGNv2': ignTiles,
      'Satellite': ignOrthoTiles,
      'Outdoors': outdoorsTiles,
    };

    const gpx_path = (it) => {
      return (
        it.velo_id + "_" + it.velo_depart + "_" + it.velo_arrivee + "_" + (it.velo_varianteformate || "") + ".gpx"
      )
    }
    function format_time(minutes) {
      if (minutes === null) {
        return "";
      }
      const hours = Math.floor(minutes / 60);
      const remaining_minutes = minutes % 60;

      if (hours > 0) {
        return `${hours}h${remaining_minutes.toString().padStart(2, "0")}`;
      } else {
        return `${remaining_minutes}&apos;`;
      }
    }
    const calculate_time = (it) => {
      const { velo_km, velo_dplus, velo_apieduniquement } = it;
      let time_in_hours;
      if (velo_apieduniquement == "1") {
        time_in_hours = parseFloat(velo_km) / 4 + parseInt(velo_dplus) / 500;
      } else {
        time_in_hours = parseFloat(velo_km) / 20 + parseInt(velo_dplus) / 500;
      }
      const time_in_minutes = Math.round(time_in_hours * 60);
      return time_in_minutes;
    }
    const reverse = (lnglat) => {
      const [lng, lat, ...rest] = lnglat;
      return [lat, lng, ...rest]
    }
    const toGeoJSON = (feature) => ({ type: "FeatureCollection", features: [feature] });
    const parkingIcon = (size, pname) => L.divIcon({
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2],
      className: "bg-none flex flex-row justify-center items-start",
      html: (
        `<div class="text-white bg-blue-600 text-[${size / 2 + 1}px] rounded-full aspect-square w-[${size}px] h-[${size}px] flex justify-center items-center font-bold border border-white">${pname}</div>`
      ),
    })
    const isInvalidSecteur = (secteur) => {
      return (
        !secteur.geometry
        || !["Polygon", "LineString"].includes(secteur.geometry.type)
        || secteur.geometry.coordinates.length === 0
        || secteur.geometry.coordinates[0].length === 0
      );
    }
  </script>
  <script>
    const falaise = <?php echo json_encode($dataF); ?>;
    const itineraires = <?php echo json_encode($itineraires); ?>;

    const center = falaise.falaise_latlng.split(",").map(parseFloat);
    const zoom = 13;
    const zoomSwitch = 14;
    const bounds = [
      falaise.falaise_latlng.split(",").map(parseFloat),
      itineraires.map(it => it.gare_latlng.split(",").map(parseFloat))
    ];
    var map = L.map("map", { layers: [landscapeTiles], center, zoom, fullscreenControl: true });

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
    var layer = protomapsL.leafletLayer({ url: '/bdd/trains/trainlines.pmtiles', paintRules, maxDataZoom: 16, pane: "overlayPane" })
    layer.addTo(map);

    function renderGpx(it, c) {
      const lopts = { weight: 5, color: c };
      const options = {
        async: true,
        markers: {
          startIcon: null,
          endIcon: null,
        },
        polyline_options: lopts,
      };
      return new L.GPX("./bdd/gpx/" + gpx_path(it), options)
        .addTo(map)
        .on('loaded', e => {
          e.target.bindTooltip(format_time(calculate_time(it)),
            {
              className: `p-[1px] bg-[${c}] text-white border-[${c}] font-bold`,
              permanent: true,
              direction: "center",
            });
          e.target.on('mouseover', e => {
            e.originalEvent.target.ownerSVGElement.appendChild(e.originalEvent.target);
            e.target.eachLayer((l) => l.setStyle({ weight: 10, color: c }))
          });
          e.target.on('mouseout', e => {
            e.target.eachLayer((l) => l.setStyle(lopts))
          });
          e.target.on('click', e => {
            L.DomEvent.stopPropagation(e);
          });
        }
        );
    }

    const falaiseMarker = L.marker(
      falaise.falaise_latlng.split(","),
      {
        icon: icon(defaultMarkerSize),
        riseOnHover: true,
        autoPanOnFocus: true,
      }
    ).addTo(map);
    falaiseMarker.on("click", () => {
      map.flyTo(falaise.falaise_latlng.split(","), 15, { duration: 0.25 });
    });
    itineraires.map((it, i) => {
      const c = itinerairesColors[i % itinerairesColors.length];
      const options = {
        async: true,
        markers: {
          startIcon: null,
          endIcon: null,
        },
        polyline_options: {
          weight: 5,
          color: c,
        },
      };
      const gpx = renderGpx(it, c);
      const gareMarker = L.marker(
        it.gare_latlng.split(","),
        {
          icon: trainIcon(),
          riseOnHover: true,
          autoPanOnFocus: true,
        }
      ).addTo(map);
      gareMarker.bindTooltip(it.gare_nom, {
        direction: "right",
        permanent: true,
        offset: [iconSize / 2, 0],
        className: `rounded-md bg-[${c}] border-[${c}] text-white px-[1px] py-0 before:border-r-[${c}]`,
      });
    });

    let falaiseDetails = {};
    let falaiseDetailsLayers = {};
    let falaiseDetailsIndicator = undefined;
    let parkingIndicators = [];
    const renderFalaiseDetails = () => {
      const zoom = map.getZoom();
      if (zoom < 13) {
        if (Object.values(falaiseDetailsLayers).length > 0) {
          Object.values(falaiseDetailsLayers).forEach((arr) => {
            if (!arr) {
              return;
            }
            arr.forEach((el) => {
              if (el.layer) {
                map.removeLayer(el.layer);
                el.layer = undefined;
              }
              if (el.marker) {
                map.removeLayer(el.marker);
                el.marker = undefined;
              }
            });
          });
          falaiseDetailsLayers = {};
        }
        return;
      }
      if (!falaiseDetails || Object.values(falaiseDetailsLayers).length > 0) {
        return;
      }
      const approcheBaseStyle = {
        color: "blue",
        weight: 2,
        dashArray: "5 5",
      };
      const approcheHighlightedStyle = {
        color: "DodgerBlue",
        weight: 6,
        dashArray: "10",
      };
      const approcheColors = ["DodgerBlue", "DarkBlue", "SteelBlue", "royalblue"]
      const approches = falaiseDetails.approches?.map(approche => {
        const layer = L.geoJSON(toGeoJSON(approche), {
          style: approcheBaseStyle,
        }).addTo(map);
        approche.layer = layer;
        return approche;
      })
      const accesVelos = falaiseDetails.accesVelos?.map(accesVelo => {
        const layer = L.geoJSON(toGeoJSON(accesVelo), {
          style: {
            color: itinerairesColors[0],
            weight: 3,
          }
        }).addTo(map);
        accesVelo.layer = layer;
        return accesVelo;
      })
      const parkings = falaiseDetails.parkings?.map(parking => {
        const pname = parking.properties.name.length > 2 ? parking.properties.name.substring(0, 1) : parking.properties.name;
        const layer = L.marker(
          reverse(parking.geometry.coordinates),
          {
            icon: parkingIcon(18, pname),
          }).addTo(map);
        if (parking.properties.name.length > 2) {
          layer.bindTooltip(parking.properties.name, {
            direction: "right",
            offset: [iconSize / 2, 0],
            className: `rounded-md bg-blue-600 border-blue-600 text-white px-[1px] py-0 before:border-r-blue-600`,
          });
        }
        parking.layer = layer;
        return parking;
      })
      const secteurs = falaiseDetails.secteurs?.map(secteur => {
        const name = secteur.properties.name;
        if (isInvalidSecteur(secteur)) return;
        secteur.center = reverse(turf.centerOfMass(toGeoJSON(secteur)).geometry.coordinates);
        const weight = secteur.geometry.type === "Polygon" ? 1 : 6;
        const marker = name ? L.marker(secteur.center, {
          pane: "tooltipPane",
          icon: L.divIcon({
            iconSize: [0, 0],
            iconAnchor: [0, 0],
            className: "relative",
            html: (
              `<div id="marker-${name.replace(/"/g, "")}" class="absolute top-0 left-1/2 w-fit text-nowrap -translate-x-1/2 text-black bg-white text-xs p-[1px] leading-none rounded-md opacity-80">`
              + name
              + `</div>`
            ),
          }),
        }).addTo(map) : undefined;
        const layer = L.geoJSON(toGeoJSON(secteur), {
          style: {
            color: "#333",
            weight,
            className: "cursor-grab",
          }
        }
        ).addTo(map);
        const mouseover = (e) => {
          L.DomEvent.stopPropagation(e);
          layer.eachLayer(l => l.setStyle({ color: "darkred", weight: weight + 2 }));
          if (marker) {
            document.getElementById(`marker-${name.replace(/"/g, "")}`).classList.add("bg-red-900", "text-white");
            document.getElementById(`marker-${name.replace(/"/g, "")}`).classList.remove("bg-white", "text-black");
          }
          const parkingList = secteur.properties.parking?.split(",").map(p => p.trim()) || [];
          const approcheList = secteur.properties.approche?.split(",").map(p => p.trim()) || [];
          approcheList.map((approche, i) => {
            const ap = approche ? approches.find(a => a.properties.name === approche) : undefined;
            if (ap) {
              ap.layer.setStyle(approcheHighlightedStyle);
            }
          })
          parkingList.map(parking => {
            const pk = parking ? parkings.find(p => p.properties.name === parking) : undefined;
            if (pk) {
              map.removeLayer(pk.layer);
              pk.layer = L.marker(reverse(pk.geometry.coordinates), { icon: parkingIcon(24, parking) }).addTo(map);
              if (approcheList.length === 0) {
                parkingIndicators.push(
                  L.polyline([reverse(pk.geometry.coordinates), secteur.center], {
                    color: "black",
                    weight: 1,
                    dashArray: "5",
                  }).addTo(map)
                );
              }
            }
          })
        }
        const mouseout = (e) => {
          removeParkingLinks();
          resetParkingMarkers();
          approches.map(approche => {
            approche.layer.setStyle(approcheBaseStyle);
          })
          layer.eachLayer(l => l.setStyle({ color: "black", weight }));
          if (marker) {
            document.getElementById(`marker-${name.replace(/"/g, "")}`).classList.remove("bg-red-900", "text-white");
            document.getElementById(`marker-${name.replace(/"/g, "")}`).classList.add("bg-white", "text-black");
          }
        }
        layer.eachLayer(l => {
          l.on("mouseover click", mouseover);
          l.on("mouseout", mouseout);
        });
        if (marker) {
          marker.on("click mouseover", mouseover);
          marker.on("mouseout", mouseout);
          secteur.marker = marker;
        }
        secteur.layer = layer;
        return secteur;
      })
      parkings?.map(parking => {
        parking.layer.on("click", function (e) {
          removeParkingLinks();
          secteurs.map(secteur => {
            if (secteur.properties.parking === parking.properties.name) {
              parkingIndicators.push(
                L.polyline([reverse(parking.geometry.coordinates), secteur.center], {
                  color: "black",
                  weight: 1,
                  dashArray: "5",
                }).addTo(map)
              );
            }
          });
        });
      })
      falaiseDetailsLayers = {
        approches,
        accesVelos,
        parkings,
        secteurs: secteurs.filter(Boolean),
      };
    }

    function renderFalaiseDetailsIndicator() {
      const zoom = map.getZoom();
      if (zoom >= 13) {
        if (falaiseDetailsIndicator) {
          map.removeLayer(falaiseDetailsIndicator);
          falaiseDetailsIndicator = undefined;
        }
        return;
      }
      if (!falaiseDetails) {
        return;
      }
      if (!falaiseDetailsIndicator && falaiseDetails.secteurs && falaiseDetails.secteurs.length > 0) {
        falaiseDetailsIndicator = L.marker(falaise.falaise_latlng.split(","), {
          pane: "tooltipPane",
          icon: L.divIcon({
            iconSize: [200, 200],
            iconAnchor: [100, 0],
            className: "bg-none flex flex-row justify-center items-start",
            html: (
              `<div class="text-black text-center bg-white text-xs rounded-full px-2 max-w-48 w-fit opacity-70">`
              + `Cliquez ou zoomez pour voir les secteurs de la falaise`
              + `</div>`
            ),
          }),
        }).addTo(map);
        falaiseDetailsIndicator.on("click", function (e) {
          marker.fire('click');
        });
      }
    }
    map.on("zoomend", () => { renderFalaiseDetails(); renderFalaiseDetailsIndicator(); });

    const removeParkingLinks = () => {
      parkingIndicators.forEach(layer => map.removeLayer(layer));
      parkingIndicators = [];
    }
    const resetParkingMarkers = () => {
      falaiseDetails.parkings?.map(parking => {
        if (parking.layer) {
          map.removeLayer(parking.layer);
          parking.layer = L.marker(reverse(parking.geometry.coordinates), { icon: parkingIcon(18, parking.properties.name) }).addTo(map);
        }
      })
    }
    const toggleNames = (shown) => {
      falaiseDetails.secteurs?.map(secteur => {
        if (secteur.marker) {
          if (shown) {
            document.getElementById(`marker-${secteur.properties.name.replace(/"/g, "")}`).classList.remove("hidden");
          } else {
            document.getElementById(`marker-${secteur.properties.name.replace(/"/g, "")}`).classList.add("hidden");
          }
        }
      })
    }
    const toggleFalaiseIcon = (shown) => {
      if (shown) {
        falaiseMarker.setOpacity(1);
      } else {
        falaiseMarker.setOpacity(0);
      }
    }
    map.on("click", () => { removeParkingLinks(); resetParkingMarkers(); });
    map.on("zoomend", () => {
      const hasSecteurs = falaiseDetails.secteurs && falaiseDetails.secteurs.length > 0;
      toggleNames(map.getZoom() > zoomSwitch);
      if (hasSecteurs) { toggleFalaiseIcon(map.getZoom() <= zoomSwitch); }
    });
    fetch("./bdd/barres/" + falaise.falaise_id + "_" + falaise.falaise_nomformate + ".geojson")
      .then(response => response.json())
      .then(data => {
        const accesVelos = data.features.filter(f => f.properties.type === "acces_velo");
        const secteurs = data.features.filter(f => f.properties.type === "secteur" || f.properties.type === undefined);
        const parkings = data.features.filter(f => f.properties.type === "parking");
        const approches = data.features.filter(f => f.properties.type === "approche");
        falaiseDetails = {
          accesVelos,
          secteurs,
          parkings,
          approches,
        };
        map.flyTo(falaise.falaise_latlng.split(","), 14, { duration: 0.25 });
      })
      .catch(() => { });


  </script>

  <script>
    window.addEventListener("DOMContentLoaded", function () {
      roseFromExpo("rose-des-vents", "<?php echo $falaise_exposhort1 ?>", "<?php echo $falaise_exposhort2 ?>", 60, 60);
      // roseFromExpo("rose-mini", "<?php echo $falaise_exposhort1 ?>", "<?php echo $falaise_exposhort2 ?>", 36, 36);
    });
  </script>

  <!-- <script>
    <?php foreach ($gares as $gare): ?>
      document.addEventListener(
        "IvtsWidgetsExternal.Booking.Ready",
        ({ detail: bookingWidget }) => {
          bookingWidget.init("container__booking__gare_<?= $gare['gare_id'] ?>", {
            titleIndex: 2,
            // inwardDate: { isDisabled: true },
            <?php if ($ville_id_get): ?>origin: { defaultValue: "<?= htmlspecialchars($selected_ville_nom) ?>" }, <?php endif; ?>
                            destination: {
              defaultValue: "<?= $gare['gare_nom'] ?>",
              isDisabled: true,
            },
            outwardDate: {
              defaultValue: new Date().toLocaleDateString("fr"),
            },
            // outwardTime: {
            //   defaultValue: new Date().toLocaleTimeString("fr", { hour: "2-digit", minute: "2-digit" }),
            // },
            tracking: {
              wizalyQueryParameters:
                "wiz_medium=part&wiz_source=velogrimpe&wiz_campaign=fr_conv_widget_contenu_filrouge_tr-multiproduit__mk_202405&wiz_content=fr",
            },
          });
        }
      );
    <?php endforeach; ?>

  </script> -->

  <!-- <script async defer src="https://www.sncf-connect.com/widget-external/web-widgets-external.js"></script> -->

  <?php include "./components/footer.html"; ?>
</body>

</html>