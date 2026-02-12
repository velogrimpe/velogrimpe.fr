<?php

$falaise_id = $_GET['falaise_id'] ?? null;
if (empty($falaise_id)) {
  echo 'Pas de falaise renseignée.';
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

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
  SELECT *, concat(gares.gare_nom, ' → ', f.falaise_nom, ' (', velo.velo_variante, ')') as velo_nom
  FROM velo
  LEFT JOIN gares ON velo.gare_id = gares.gare_id AND gares.deleted = 0
  LEFT JOIN falaises f ON velo.falaise_id = f.falaise_id
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

$stmtAllGares = $mysqli->prepare("SELECT gare_id, gare_nom FROM gares WHERE deleted = 0 ORDER BY gare_nom");
$stmtAllGares->execute();
$result = $stmtAllGares->get_result();
$allGares = [];
while ($row = $result->fetch_assoc()) {
  $allGares[] = $row;
}
$stmtAllGares->close();

$stmtAllVilles = $mysqli->prepare("SELECT ville_id, ville_nom FROM villes ORDER BY ville_nom");
$stmtAllVilles->execute();
$result = $stmtAllVilles->get_result();
$allVilles = [];
while ($row = $result->fetch_assoc()) {
  $allVilles[] = $row;
}
$stmtAllVilles->close();

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
$falaise_hebergement = $dataF['falaise_hebergement'] ?? null;
$falaise_acces_bus = $dataF['falaise_acces_bus'] ?? null;
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

// Get comments from the database
$stmtC = $mysqli->prepare("
  SELECT
    cf.id, cf.commentaire, cf.date_creation, cf.nom,
    cf.velo_id, cf.ville_nom, cf.gare_depart, cf.gare_arrivee,
    concat(gares.gare_nom, ' → ', f.falaise_nom, ' (', velo.velo_variante, ')') as velo_nom
  FROM commentaires_falaises cf
  left join velo on cf.velo_id = velo.velo_id
  left join gares on velo.gare_id = gares.gare_id AND gares.deleted = 0
  left join falaises f on velo.falaise_id = f.falaise_id
  WHERE cf.falaise_id = ? 
  ORDER BY date_creation DESC
");
if (!$stmtC) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$stmtC->bind_param("i", $falaise_id);
$stmtC->execute();
$resC = $stmtC->get_result();

$comments = [];
while ($dataC = $resC->fetch_assoc()) {
  $comments[] = $dataC;
}
$stmtC->close();
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
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?>. Découvrez les accès en vélo et en train, les topos et les informations pratiques pour une sortie vélo-grimpe en mobilité douce.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title"
    content="Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Escalade à <?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?>. Découvrez les accès en vélo et en train, les topos et les informations pratiques pour une sortie vélo-grimpe en mobilité douce.">
  <!-- Map libraries bundle (Leaflet, GPX, Fullscreen, Locate, Turf) -->
  <script src="/dist/map.js"></script>
  <link rel="stylesheet" href="/dist/map.css" />
  <!-- Carte : Lignes de train-->
  <!-- <script src="https://unpkg.com/protomaps-leaflet@5.1.0/dist/protomaps-leaflet.js"></script> -->
  <script src="/js/vendor/protomaps-leaflet.js"></script>
  <!-- Carte : Pour les détails falaise-->
  <script src="/js/vendor/leaflet-textpath.js"></script>
  <!-- Styles -->
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="stylesheet" href="/global.css">
  <link rel="stylesheet" href="falaise.css">
</head>

<body>
  <?php include "./components/header.html"; ?>
  <main class="max-w-(--breakpoint-lg) w-full mx-auto p-4 flex flex-col items-center gap-4 bg-base-100 my-2 rounded-xl">
    <section class="flex flex-col items-center gap-4 w-full">
      <div class="flex justify-between items-center w-full">
        <a class="text-primary font-bold" href="/carte.php">← Retour à la carte</a>
        <div class="flex flex-row items-center gap-2">
          <div class="dropdown dropdown-end w-fit">
            <div tabindex="0" role="button"
              class="btn btn-sm md:btn-md btn-circle btn-outline btn-primary focus:pointer-events-none"
              title="J'y ai été">
              <svg class="w-4 md:w-6 h-4 md:h-6 fill-none stroke-current">
                <use href="#chat"></use>
              </svg>
            </div>
            <div class="dropdown-content gap-1 menu bg-base-200 rounded-box z-1 m-1 w-64 p-2 shadow-lg" tabindex="1">
              <button class="btn btn-primary btn-outline btn-sm py-1 h-fit" onclick="newComment()"> Raconter ma sortie
                vélogrimpe </button>
            </div>
          </div>
          <div class="dropdown dropdown-end w-fit">
            <div tabindex="0" role="button"
              class="btn btn-sm md:btn-md btn-circle btn-outline focus:pointer-events-none"
              title="Proposer des modifications">
              <svg class="w-4 md:w-6 h-4 md:h-6 fill-none stroke-current">
                <use href="#pencil"></use>
              </svg>
            </div>
            <div class="dropdown-content gap-1 menu bg-base-200 rounded-box z-1 m-1 w-64 p-2 shadow-lg" tabindex="1">
              <a class="btn btn-primary btn-outline btn-sm py-1 h-fit"
                href="/ajout/ajout_falaise.php?falaise_id=<?= $falaise_id ?>"> Modifier la fiche falaise </a>
              <a class="btn btn-primary btn-outline btn-sm py-1 h-fit"
                href="/ajout/edit_details_falaise.php?falaise_id=<?= $falaise_id ?>"> Ajouter des détails (secteurs,
                parking...) </a>
              <a class="btn btn-primary btn-outline btn-sm py-1 h-fit"
                href="/ajout/ajout_velo.php?falaise_id=<?= $falaise_id ?>"> Ajouter un accès vélo </a>
              <a class="hidden btn btn-primary btn-outline btn-sm py-1 h-fit"
                href="/edition/commentaire_velo.php?falaise_id=<?= $falaise_id ?>"> Modifier un accès vélo </a>
              <a class="hidden btn btn-primary btn-outline btn-sm py-1 h-fit"
                href="/ajout/ajout_train.php?falaise_id=<?= $falaise_id ?>"> Demander l'ajout d'un accès train depuis
                une nouvelle ville </a>
            </div>
          </div>
        </div>
      </div>
      <!-- Message si la falaise est interdite -->
      <?php if (!empty($falaise_fermee)): ?>
        <div class="alert text-center flex flex-col items-center">
          <div class="text-error font-bold text-2xl"> FALAISE INTERDITE ! </div>
          <div class="text-error">
            <?= nl2br($falaise_fermee) ?>
          </div>
        </div>
      <?php endif; ?>
      <!-- Message si la falaise n'est reliée à aucune gare (pas visible sur la carte ni dans les tableaux) -->
      <?php if (count($itineraires) === 0): ?>
        <div class="alert alert-warning alert-soft text-center flex flex-col items-center">
          <div class="font-bold text-lg">Falaise non reliée au réseau</div>
          <div> Cette falaise n'a pas encore d'itinéraire vélo depuis une gare. Elle n'apparaît donc pas sur la carte ni
            dans les tableaux. </div>
          <a class="btn btn-primary btn-sm mt-2" href="/ajout/ajout_velo.php?falaise_id=<?= $falaise_id ?>">Proposer un
            accès vélo</a>
        </div>
      <?php endif; ?>
      <div class="flex flex-col items-center mb-10 gap-4">
        <h1 class="inline-flex flex-col text-[48px] font-bold text-center leading-none text-primary">
          <?= htmlspecialchars($falaise_nom) ?>
          <?php if ($ville_id_get): ?>
            <br>
            <span class="text-base font-normal">au départ de <?= htmlspecialchars($selected_ville_nom) ?></span>
          <?php endif; ?>
        </h1>
        <button class="drawer-button btn btn-neutral btn-sm rounded-full btn-outline" onclick="meteoModal.showModal()">
          Météo <span class="flex items-center gap-1">
            <svg class="w-4 h-4 fill-[gold]">
              <use href="#sun-foggy"></use>
            </svg>
            <span class="font-normal">/</span>
            <svg class="w-4 h-4 fill-[LightSlateGray]">
              <use href="#sun-cloudy"></use>
            </svg>
          </span>
        </button>
        <dialog id="meteoModal" class="modal modal-bottom sm:modal-middle">
          <div class="modal-box md:w-fit max-w-(--breakpoint-xl)">
            <form method="dialog">
              <button tabindex="-1" class="btn btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <div class="p-4 w-60 font-bold mx-auto">
              <span class="text-lg font-bold"> Météo par <a class="text-primary font-bold"
                  href="https://www.meteoblue.com/fr/meteo/semaine/<?= $lat ?>N<?= $lng ?>E391_Europe%2FParis?utm_source=daily_widget&utm_medium=linkus&utm_content=daily&utm_campaign=Weather%2BWidget"
                  target="_blank" rel="noopener">meteoblue </a>
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
        <div class="vg-a-primary flex flex-col gap-4 md:gap-10 w-full items-center md:my-auto max-w-150 mx-auto">
          <div class="flex flex-row gap-2 items-start justify-around w-full">
            <?php if ($falaise_nbvoies !== "inconnue"): ?>
              <div class="flex flex-col items-center justify-start gap-2">
                <img src="/images/icons/abacus_color.png" alt=" Logo Nb voies" class="h-12 w-12 mx-auto" />
                <div class="font-bold text-center text-lg"><?= $falaise_nbvoies ?></div>
              </div>
            <?php endif; ?>
            <?php if (!empty($falaise_cotmin) && !empty($falaise_cotmax)): ?>
              <div class="flex flex-col items-center justify-start gap-2">
                <img src="/images/icons/speedometer_color.png" alt=" Logo difficulté" class="h-12 w-12 mx-auto" />
                <div class="font-bold text-center text-lg">
                  <?= $falaise_cotmin ?> à <?= $falaise_cotmax ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
          <div class="flex flex-row gap-2 items-center justify-center mx-auto">
            <div class='w-full grid grid-cols-[auto_auto] gap-4 md:gap-y-6 items-center'>
              <?php if (!empty($falaise_voies) || !empty($falaise_cottxt)): ?>
                <img src="/images/icons/rock-climbing_color.png" alt=" Voies" class="h-12 w-12 mx-auto" />
                <!-- <div class="font-bold ">Voies</div> -->
                <div class="">
                  <?= nl2br($falaise_voies) ?>
                  <?php if (!empty($falaise_cottxt)): ?>
                    <div><span>Cotations</span> :
                      <?= nl2br(mb_strtolower(substr($falaise_cottxt, 0, 1))) . nl2br(substr($falaise_cottxt, 1)) ?>
                    </div>
                  <?php endif ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($falaise_topo)): ?>
                <img src="/images/icons/guidebook_color.png" alt="Topo" class="h-12 w-12 mx-auto" />
                <!-- <div class="font-bold  ">Topo(s)</div> -->
                <div class="">
                  <div><?= nl2br($falaise_topo) ?></div>
                  <?php if (count($liensOblyk) > 1): ?>
                    <div class="dropdown w-fit">
                      <a tabindex="0" role="button"
                        class="font-normal text-nowrap focus:pointer-events-none flex items-center gap-1"
                        id="approcheFilterBtn"> Fiches Oblyk <span
                          class="badge badge-sm badge-primary"><?= count($liensOblyk) ?></span>
                      </a>
                      <div
                        class="dropdown-content menu bg-base-200 rounded-box z-10 m-1 p-2 shadow-lg w-60 max-h-62.5 flex-nowrap overflow-auto"
                        tabindex="1">
                        <?php foreach ($liensOblyk as $lien): ?>
                          <a target="_blank" href="<?= htmlspecialchars($lien['url']) ?>"
                            class="text-primary font-bold hover:underline cursor-pointer">
                            <span><?= htmlspecialchars($lien['name']) ?></span>&nbsp;<svg
                              class="w-3 h-3 fill-none stroke-current inline">
                              <use href="#external-link"></use>
                            </svg>
                          </a>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php elseif (count($liensOblyk) == 1): ?>
                    <a target="_blank" href="<?= htmlspecialchars($liensOblyk[0]['url']) ?>"
                      class="text-primary font-bold hover:underline cursor-pointer"> Fiche Oblyk </a>
                  <?php endif ?>
                </div>
              <?php endif ?>
              <?php if (!empty($falaise_matxt)): ?>
                <img src="/images/icons/hiking_color.png" alt=" Approche" class="h-12 w-12 mx-auto" />
                <!-- <div class="font-bold  ">Approche</div> -->
                <div class="">Approche :
                  <?= nl2br(mb_strtolower(substr($falaise_matxt, 0, 1))) . nl2br(substr($falaise_matxt, 1)) ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($falaise_gvtxt)): ?>
                <img src="/images/icons/mountain_color.png" alt=" Grande voies" class="h-12 w-12 mx-auto" />
                <!-- <div class="font-bold  ">Grandes voies</div> -->
                <div class="">
                  <?= nl2br($falaise_gvtxt) ?>
                </div>
              <?php endif; ?>
              <!-- Rose des vents (Vue component) --> <?php if (!empty($falaise_expotxt)): ?>
                <div id="vue-rose-des-vents" data-expo1="<?= htmlspecialchars($falaise_exposhort1) ?>"
                  data-expo2="<?= htmlspecialchars($falaise_exposhort2) ?>" data-size="60"></div>
                <div class=" flex flex-row gap-2 items-center">
                  <?= nl2br($falaise_expotxt) ?>
                </div> <?php endif; ?>
              <?php if (!empty($falaise_rq)): ?>
                <img src="/images/icons/note_color.png" alt=" Remarques" class="h-12 w-12 mx-auto" />
                <!-- <div class="font-bold ">Remarques</div> -->
                <div class=""><?= nl2br($falaise_rq) ?></div>
              <?php endif; ?>
              <?php if (!empty($falaise_hebergement)): ?>
                <img src="/images/icons/camping.png" alt=" Hébergement" class="h-12 w-12 mx-auto" />
                <!-- <div class="font-bold ">Hébergement</div> -->
                <div class=""><?= nl2br($falaise_hebergement) ?></div>
              <?php endif; ?>
              <?php if (!empty($falaise_acces_bus)): ?>
                <img src="/images/icons/bus.png" alt=" Accès en bus" class="h-12 w-12 mx-auto" />
                <!-- <div class="font-bold ">Accès en bus</div> -->
                <div class=""><?= nl2br($falaise_acces_bus) ?></div>
              <?php endif; ?>
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
          SELECT
            t.train_temps, t.train_descr, t.train_correspmin,
            t.train_correspmax, COALESCE(t.train_tgv, 0) AS train_tgv
            FROM train t
            WHERE t.ville_id = ? AND t.gare_id = ?
            ORDER BY COALESCE(t.train_tgv, 0) ASC
          ");
        $stmtT->bind_param("ii", $ville_id_get, $gare['gare_id']);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        $train_itineraires = [];
        while ($row = $resT->fetch_assoc()) {
          $train_itineraires[] = $row;
        }
        $stmtT->close();
        //   $stmtT = $mysqli->prepare("
        //     SELECT t.train_temps, t.train_descr, t.train_correspmin, t.train_correspmax
        //     FROM train t
        //     WHERE t.ville_id = ? AND t.gare_id = ?
        // ");
        //   $stmtT->bind_param("ii", $ville_id_get, $gare['gare_id']);
        //   $stmtT->execute();
        //   $resT = $stmtT->get_result();
        //   $dataT = $resT->fetch_assoc();
        //   $stmtT->close();
      
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

        $best_train_temps = count($train_itineraires) > 0 ? min(array_column($train_itineraires, 'train_temps')) : null;

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
            <div class="text-lg"> Accès via la gare de <span class="font-bold capitalize">
                <?php echo htmlspecialchars($gare['gare_nom']) ?>
              </span>
              <?php if ($selected_ville_nom && count($train_itineraires) > 0): ?> : <span class="font-bold text-primary">
                  <?= format_time($shortest_velo_time + $best_train_temps + $falaise_maa) ?>
                </span>
                <span class="text-base" title="Temps total (Train + Velo + Approche)">(🚃+🚲+🥾)</span>
              <?php endif ?>
            </div>
            <div class="hidden md:block">
              <div class="text-sm text-slate-400"> 🚲 - <?= format_time($shortest_velo_time) ?>
                (<?= htmlspecialchars($velo_itineraires[0]['velo_km']) ?> km,
                <?= htmlspecialchars($velo_itineraires[0]['velo_dplus']) ?> D+) </div>
              <?php if (count($train_itineraires) > 0): ?>
                <div class="text-sm text-slate-400">
                  <?php foreach ($train_itineraires as $t): ?>
                    <div> 🚆 - <?= format_time($t['train_temps']) ?> (<?= ($t['train_correspmin'] ?? 0) > 0
                         ? (($t['train_correspmin'] === $t['train_correspmax'])
                           ? $t['train_correspmin'] . ' Corresp.'
                           : 'de ' . $t['train_correspmin'] . ' à ' . $t['train_correspmax'] . ' Corresp.')
                         : 'Direct' ?>)<?php if (!empty($t['train_tgv'])): ?>
                        <span class="badge badge-accent badge-sm" title="Trajet empruntant un segment TGV"> TGV </span>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif ?>
            </div>
          </div>
          <!-- CREATION DES TABLEAUX DYNAMIQUES -->
          <div class='collapse-content'>
            <table class='table bg-base-100 border-spacing-0'>
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
                  Total : <?php echo format_time($shortest_velo_time + ($best_train_temps ?? 0) + $falaise_maa) ?>
                <?php endif ?>
              </td>
            </tr>
          </thead> -->
              <!-- // LIGNE 2 TRAIN : -->
              <tr>
                <td class="justify-center border-t border-r border-b border border-base-300">
                  <div class="flex flex-col md:flex-row gap-4 items-center">
                    <img src="/images/icons/train-station_color.png" alt="Logo Train" class="h-10 w-auto">
                    <div>
                      <?php if ($selected_ville_nom): ?>
                        <b><?= htmlspecialchars($selected_ville_nom) . " → " . htmlspecialchars($gare["gare_nom"]) ?></b>
                      <?php else: ?> Rejoindre la gare de : <b><?= htmlspecialchars($gare["gare_nom"]) ?></b>
                      <?php endif; ?>
                      <?php if (count($train_itineraires) > 0): ?>
                        <div class="mt-1 flex flex-col gap-1">
                          <?php foreach ($train_itineraires as $t): ?>
                            <div class="text-sm">
                              <span class="text-lg font-bold ml-1"><?= format_time($t['train_temps']) ?></span> (<?= ($t['train_correspmin'] ?? 0) > 0
                                  ? (($t['train_correspmin'] === $t['train_correspmax'])
                                    ? $t['train_correspmin'] . ' Corresp.'
                                    : 'de ' . $t['train_correspmin'] . ' à ' . $t['train_correspmax'] . ' Corresp.')
                                  : 'Direct' ?>) <?php if (!empty($t['train_tgv'])): ?>
                                <span class="badge badge-accent badge-sm" title="Trajet empruntant un segment TGV">TGV</span>
                              <?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php endif ?>
                      <!-- <button class="btn btn-xs btn-outline btn-accent" onclick="gare<?= $gare["gare_id"] ?>.showModal()">
                      <svg class="w-3 md:w-4 h-3 md:h-4 fill-none stroke-current">
                        <use href="#ticket"></use>
                      </svg>
                      Acheter un billet
                    </button>
                    <dialog id="gare<?= $gare["gare_id"] ?>" class="modal">
                      <div class="modal-box p-0 max-w-(--breakpoint-lg) w-full bg-transparent"
                        id="container__booking__gare_<?= $gare["gare_id"] ?>">
                      </div>
                      <form method="dialog" class="modal-backdrop">
                        <button>close</button>
                      </form>
                    </dialog> -->
                    </div>
                  </div>
                </td>
                <td class='border-t border-b border border-base-300'>
                  <?php if ($ville_id_get): ?>
                    <?php if (count($train_itineraires) > 0): ?>
                      <?php foreach ($train_itineraires as $t): ?>
                        <!-- If index > 0 add a <hr element> -->
                        <?php if ($t !== reset($train_itineraires)): ?>
                          <hr class="my-4">
                        <?php endif; ?>
                        <?php if (!empty($t['train_tgv'])): ?>
                          <span class="badge badge-accent badge-sm" title="Trajet empruntant un segment TGV">Option avec
                            TGV</span>
                        <?php endif; ?>
                        <div class="mb-2">
                          <?php if (!empty($t['train_descr'])): ?>
                            <span class="vg-a-primary"><?= nl2br($t['train_descr']) ?></span>
                          <?php else: ?>
                            <span class="ml-2">Itinéraire non décrit (soit il est peu pertinent, soit pas encore
                              renseigné).</span>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    <?php else: ?> Itinéraire non décrit (soit il est peu pertinent, soit j'ai pas eu le temps !).
                    <?php endif ?>
                  <?php else: ?>
                    <div>
                      <?php if (count($villesFrom) > 0): ?> Accès décrits depuis: <ul class="list-disc pl-6">
                          <?php foreach ($villesFrom as $villeFrom): ?>
                            <li>
                              <a class="text-primary font-bold hover:underline cursor-pointer"
                                href="?falaise_id=<?= htmlspecialchars($falaise_id) ?>&ville_id=<?= htmlspecialchars($villeFrom['ville_id']) ?>">
                                <?= htmlspecialchars($villeFrom['ville_nom']) ?>
                              </a>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?> Pas d'accès train décrits depuis cette gare. <a class="btn btn-primary btn-xs"
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
                  <td class='justify-center border-t border-r border-b border border-base-300'>
                    <div class='flex flex-col md:flex-row gap-4 items-center'>
                      <?php if (isset($velo['velo_apieduniquement']) && $velo['velo_apieduniquement'] == 1): ?>
                        <img src="/images/icons/hiking_color.png" alt="Logo À Pied" class="h-auto w-10">
                      <?php else: ?>
                        <img src="/images/icons/bicycle_color.png" alt="Logo Vélo" class="h-auto w-10">
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
                  <td class='border-t border-b border border-base-300'>
                    <?= htmlspecialchars($velo['velo_km']) . " km, " . htmlspecialchars($velo['velo_dplus']) . " D+, " . htmlspecialchars($velo['velo_dmoins']) . " D-." ?>
                    <br>
                    <span class="vg-a-primary"><?= nl2br($velo['velo_descr']) ?></span>
                    <br>
                    <?php if ($velo['velo_openrunner']): ?>
                      <!-- Desktop : ouvre juste en dessous -->
                      <a class="font-bold text-primary hidden md:inline" href='#'
                        onclick="document.getElementById('profil_<?= $velo['velo_id'] ?>').classList.toggle('hidden'); return false;">
                        Profil altimétrique </a>
                      <!-- Mobile : ouvre dans un dialog -->
                      <a class="text-primary font-bold hover:underline cursor-pointer inline md:hidden"
                        onclick="document.getElementById('profil_<?= $velo['velo_id'] ?>_modal').showModal()"> Profil
                        altimétrique </a>
                    <?php endif; ?>
                    <?php
                    $gpx_path = "./bdd/gpx/" . $velo['velo_id'] . '_' . $velo['velo_depart'] . '_' . $velo['velo_arrivee'] . '_' . $velo['velo_varianteformate'] . ".gpx";
                    $exists = file_exists($gpx_path);
                    if ($velo['velo_openrunner'] && $exists): ?> | <?php endif; ?>
                    <?php
                    if ($exists):
                      ?>
                      <a class="font-bold text-primary" href="<?= htmlspecialchars($gpx_path) ?>" target='_blank'>Trace
                        GPS</a>
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
        </div>
      <?php } ?>
      <!-- Remarque entre tableaux dynamique et tableau descriptif (rq générale sur l'accès) -->
      <?php if (!empty($falaise_txt1)): ?>
        <div class="vg-a-primary">
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
        <div id="map" class="h-150 w-full bg-black rounded-lg"></div>
      </div>
      <!-- Image optionnelle 1 -->
      <?php $path = "/bdd/images_falaises/" . htmlspecialchars($falaise_id) . "_" . htmlspecialchars($falaise_nomformate) . "_img1.webp"; ?>
      <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)): ?>
        <div class="flex flex-col items-center gap-1">
          <img src="<?= $path ?>" class="border border-base-300 rounded-xl shadow-lg md:w-4/5">
          <?php if (!empty($falaise_leg1)): ?>
            <div class="text-base-content"><?= nl2br($falaise_leg1) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($falaise_txt3)): ?>
        <div>
          <?= nl2br($falaise_txt3) ?>
        </div>
      <?php endif; ?>
      <!-- Image optionnelle 2 -->
      <?php $path = "/bdd/images_falaises/" . htmlspecialchars($falaise_id) . "_" . htmlspecialchars($falaise_nomformate) . "_img2.webp"; ?>
      <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)): ?>
        <div class="flex flex-col items-center gap-1">
          <img src="<?= $path ?>" class="border border-base-300 rounded-xl shadow-lg md:w-4/5">
          <?php if (!empty($falaise_leg2)): ?>
            <div class="text-base-content"><?= nl2br($falaise_leg2) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <!-- Texte optionnel numéro 4 -->
      <?php if (!empty($falaise_txt4)): ?>
        <div>
          <?= nl2br($falaise_txt4) ?>
        </div>
      <?php endif; ?>
      <!-- Image optionnelle 3 -->
      <?php $path = "/bdd/images_falaises/" . htmlspecialchars($falaise_id) . "_" . htmlspecialchars($falaise_nomformate) . "_img3.webp"; ?>
      <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)): ?>
        <div class="flex flex-col items-center gap-1">
          <img src="<?= $path ?>" class="border border-base-300 rounded-xl shadow-lg md:w-4/5">
          <?php if (!empty($falaise_leg3)): ?>
            <div class="text-base-content"><?= nl2br($falaise_leg3) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="text-center text-slate-600 text-sm italic opacity-60"> Falaise ajoutée par
        <?= $falaise_contrib_name ?>
      </div>
    </section>
    <section id="commentaires" class="w-full">
      <?php
      $formatter = new IntlDateFormatter(
        'fr_FR',                // Locale française
        IntlDateFormatter::LONG, // Format long pour la date
        IntlDateFormatter::SHORT, // Format court pour l'heure
        'Europe/Paris',         // Fuseau horaire
        IntlDateFormatter::GREGORIAN,
        "d MMMM y 'à' HH:mm"    // Pattern personnalisé
      );
      ?>
      <h2 class="text-3xl font-bold text-center">Retours d'expérience et récits de sorties</h2>
      <div id="comments">
        <?php if (!empty($comments)): ?>
          <?php foreach ($comments as $comment): ?>
            <div class="border-b border-base-300 py-2">
              <div class="flex flex-row justify-between flex-wrap gap-2">
                <div class="flex flex-row gap-2 items-center">
                  <div class="font-bold text-primary"><?= htmlspecialchars($comment['nom']) ?></div>
                  <div class="text-sm text-slate-500"><?= $formatter->format(new DateTime($comment['date_creation'])) ?>
                  </div>
                </div>
                <button title="Modifier le commentaire" class="btn btn-xs btn-ghost btn-circle"
                  onclick="editComment(<?= $comment['id'] ?>)">
                  <svg class="w-3 md:w-4 h-3 md:h-4 fill-none stroke-current">
                    <use href="#pencil"></use>
                  </svg>
                </button>
              </div>
              <div class="flex flex-row justify-between items-center flex-wrap gap-2">
                <div class="flex flex-row gap-4 flex-wrap text-sm text-slate-600">
                  <?php if (!empty($comment['ville_nom'])): ?>
                    <div class="flex gap-1 items-center">
                      <svg class="w-4 h-4 fill-none stroke-current">
                        <use href="#building"></use>
                      </svg> <?= htmlspecialchars($comment['ville_nom']) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($comment['gare_depart'])): ?>
                    <div class="flex gap-1 items-center">
                      <svg class="w-4 h-4 fill-none stroke-current">
                        <use href="#get-out"></use>
                      </svg> <?= htmlspecialchars($comment['gare_depart']) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($comment['gare_arrivee'])): ?>
                    <div class="flex gap-1 items-center">
                      <svg class="w-4 h-4 fill-none stroke-current">
                        <use href="#get-in"></use>
                      </svg> <?= htmlspecialchars($comment['gare_arrivee']) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($comment['velo_id']) || $comment['velo_id'] === 0): ?>
                    <div class="flex gap-1 items-center">
                      <svg class="w-4 h-4 fill-current">
                        <use href="#riding"></use>
                      </svg>
                      <?= $comment['velo_id'] === 0 ? 'Autre' : str_replace(" ()", "", htmlspecialchars($comment['velo_nom'])) ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              <div><?= nl2br(htmlspecialchars($comment['commentaire'])) ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-center">Aucune sortie trouvée.</p>
        <?php endif; ?>
        <div class="text-center my-4">
          <button class="btn btn-primary" onclick="newComment()">Raconter ma sortie / mon itinéraire</button>
        </div>
      </div>
      <dialog id="commentFormModal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box w-screen sm:max-w-(--breakpoint-md)">
          <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
          </form>
          <h3 id="commentFormEditTitle" class="font-bold text-xl hidden">Modifier le récit</h3>
          <h3 id="commentFormNewTitle" class="font-bold text-xl">Nouveau récit</h3>
          <form id="commentForm" class="flex flex-col gap-1">
            <input type="hidden" name="commentaire_id" id="commentaire_id" value="">
            <input type="hidden" name="falaise_id" id="falaise_id" value="<?= htmlspecialchars($falaise_id) ?>">
            <div class="form-control w-full">
              <label class="label" for="nom">
                <span class="label-text">Nom<span class="text-red-500">*</span></span>
              </label>
              <input type="text" id="nom" name="nom" class="input input-primary w-full" required>
            </div>
            <div class="form-control w-full">
              <label class="label" for="email">
                <span class="label-text">Email<span class="text-red-500">*</span> (n'apparaitra pas sur le site)</span>
              </label>
              <input type="email" id="email" name="email" class="input input-primary w-full" required>
            </div>
            <div id="vue-falaise-comment" data-villes='<?= htmlspecialchars(json_encode(array_map(function ($v) {
              return ["id" => $v["ville_nom"], "nom" => $v["ville_nom"]];
            }, $allVilles)), ENT_QUOTES, 'UTF-8') ?>' data-gares='<?= htmlspecialchars(json_encode(array_map(function ($g) {
                  return ["id" => $g["gare_id"], "nom" => $g["gare_nom"]];
                }, $allGares)), ENT_QUOTES, 'UTF-8') ?>'>
            </div>
            <div class="form-control w-full">
              <label class="label" for="velo_id">
                <span class="label-text">Itinéraire vélo</span>
              </label>
              <select id="velo_id" name="velo_id" class="select select-bordered w-full">
                <option value="">-- Aucun --</option>
                <?php foreach ($itineraires as $it): ?>
                  <option value="<?= $it['velo_id'] ?>">
                    <?= str_replace(" ()", "", htmlspecialchars($it['velo_nom'])) ?>
                  </option>
                <?php endforeach; ?>
                <option value="0">-- Autre (préciser dans le commentaire) --</option>
              </select>
            </div>
            <div class="form-control w-full">
              <label class="label" for="commentaire">
                <span class="label-text">Récit, retour d'expérience ou commentaires sur les itinéraires<span
                    class="text-red-500">*</span></span>
              </label>
              <textarea id="commentaire" name="commentaire" class="textarea textarea-primary w-full" rows="5"
                required></textarea>
            </div>
            <div class="modal-action">
              <button type="submit" class="btn btn-primary">Enregistrer</button>
              <button type="button" class="btn btn-error text-base-100 hidden"
                onclick="deleteComment()">Supprimer</button>
              <button type="button" class="btn btn-outline"
                onclick="document.getElementById('commentFormModal').close()">Annuler</button>
            </div>
          </form>
        </div>
        <form method="dialog" class="modal-backdrop">
          <button>close</button>
        </form>
      </dialog>
      <dialog id="emailPromptDialog" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box w-screen sm:max-w-(--breakpoint-md)">
          <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
          </form>
          <h3 class="font-bold text-xl">Modifier le récit</h3>
          <div> Veuillez confirmer l'adresse mail utilisée pour écrire le récit afin d'autoriser la modification. </div>
          <form id="emailPromptForm" class="flex flex-col gap-1">
            <input type="hidden" name="emailPromptCommentId" id="emailPromptCommentId" value="">
            <div class="form-control w-full">
              <label class="label" for="emailPromptEmail">
                <span class="label-text">Email<span class="text-red-500">*</span></span>
              </label>
              <input type="email" id="emailPromptEmail" name="emailPromptEmail" class="input input-primary w-full"
                required>
            </div>
            <div class="modal-action">
              <button type="submit" class="btn btn-primary">Suivant</button>
              <button type="button" class="btn btn-outline" onclick="emailPromptDialog.close()">Annuler</button>
            </div>
          </form>
        </div>
        <form method="dialog" class="modal-backdrop">
          <button>close</button>
        </form>
      </dialog>
    </section>
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
    import BusStop from "/js/components/map/bus-stop.js";
    import FalaiseVoisine from "/js/components/map/falaise-voisine.js";
    import Gare from "/js/components/map/gare.js";
    import { campingLayer, giteLayer, trainlinesLayer, tgvLayer, biodivLayer } from "/js/components/map/load-vector-tiles.js";

    const falaise = <?php echo json_encode($dataF); ?>;
    const itineraires = <?php echo json_encode($itineraires); ?>;

    const center = falaise.falaise_latlng.split(",").map(parseFloat);
    const zoom = 13;
    const bounds = [
      falaise.falaise_latlng.split(",").map(parseFloat),
      itineraires.map(it => it.gare_latlng.split(",").map(parseFloat))
    ];
    var map = L.map("map", { layers: [landscapeTiles], center, zoom, fullscreenControl: true });
    L.control.scale({ position: "bottomleft", metric: true, imperial: false, maxWidth: 125 }).addTo(map);
    L.control.locate().addTo(map);

    map.fitBounds(bounds, { maxZoom: 15 });
    var layerControl = L.control.layers(baseMaps, undefined, { position: "topleft", size: 22 }).addTo(map);

    // --- Ajout de la falaise et itinéraires vélos ---
    const falaiseObject = new Falaise(map, falaise, { visibility: { to: 12 } });
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
        if (data.features && !data.features.find(f => f.properties.type === "secteur")) {
          falaiseObject.setVisibility({ from: 0, to: 30 });
        }
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
            } else if (feature.properties.type === "bus_stop") {
              obj = new BusStop(map, feature);
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
    window.map = map; // Pour debug

    campingLayer.addTo(map);
    trainlinesLayer.addTo(map);
    tgvLayer.addTo(map);
    layerControl.addOverlay(tgvLayer, 'Lignes et Gares TGV');
    layerControl.addOverlay(campingLayer, 'Campings');
    layerControl.addOverlay(giteLayer, 'Gîtes');
    layerControl.addOverlay(biodivLayer, 'Aires de protections de la biodiversité (escalade réglementée ou interdite)');
  </script>
  <script type="module" src="/dist/falaise-comment.js"></script>
  <script type="module" src="/dist/falaise-rose.js"></script>
  <script>
    const comments = <?= json_encode($comments) ?>;
    function editComment(commentId) {
      document.getElementById('emailPromptDialog').showModal();
      document.getElementById('emailPromptCommentId').value = commentId;
    }
    function newComment() {
      // Réinitialiser le formulaire
      document.getElementById('commentForm').reset();
      document.getElementById('commentaire_id').value = '';
      // Afficher le titre de nouveau commentaire et masquer celui de modification
      document.getElementById('commentFormNewTitle').classList.remove('hidden');
      document.getElementById('commentFormEditTitle').classList.add('hidden');
      // Cacher le bouton de suppression
      document.querySelector('#commentFormModal .btn.btn-error').classList.add('hidden');
      // Ouvrir le formulaire de commentaire
      document.getElementById('commentFormModal').showModal();
    }
    function checkEmailAndOpenForm(event) {
      console.log("Vérification de l'email pour le commentaire...");
      event.preventDefault();
      const commentId = document.getElementById('emailPromptCommentId').value;
      const email = document.getElementById('emailPromptEmail').value;

      fetch(`/api/verify_comment_email.php?commentaire_id=${commentId}&email=${encodeURIComponent(email)}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const comment = comments.find(c => c.id == commentId);
            console.log("Commentaire trouvé :", comment);
            if (!comment) {
              alert('Une erreur est survenue, commentaire non trouvé.');
              return;
            }
            // Pré-remplir le formulaire avec les données du commentaire
            document.getElementById('commentaire_id').value = comment.id;
            document.getElementById('nom').value = comment.nom;
            document.getElementById('email').value = email;
            // Update Vue autocomplete values
            if (window.setCommentFormValues) {
              window.setCommentFormValues({
                ville_nom: comment.ville_nom || '',
                gare_depart: comment.gare_depart || '',
                gare_arrivee: comment.gare_arrivee || ''
              });
            }
            document.getElementById('velo_id').value = comment.velo_id || '';
            document.getElementById('commentaire').value = comment.commentaire;

            // Afficher le titre de modification et masquer celui de nouveau commentaire
            document.getElementById('commentFormEditTitle').classList.remove('hidden');
            document.getElementById('commentFormNewTitle').classList.add('hidden');
            // Afficher le bouton de suppression
            document.querySelector('#commentFormModal .btn.btn-error').classList.remove('hidden');

            // Ouvrir le formulaire de commentaire
            document.getElementById('commentFormModal').showModal();
            document.getElementById('emailPromptDialog').close();
          } else {
            alert(data.error || 'Vérification échouée. Veuillez réessayer.');
          }
        })
        .catch(error => {
          console.error('Erreur lors de la vérification de l\'email :', error);
          alert('Une erreur est survenue. Veuillez réessayer plus tard.');
        });
    }
    document.getElementById('emailPromptForm').addEventListener('submit', checkEmailAndOpenForm);
    document.getElementById('commentForm').addEventListener('submit', function (event) {
      event.preventDefault();
      const formData = new FormData(this);
      if (document.getElementById('commentaire_id').value === '') {
        fetch('/api/add_comment.php', {
          method: 'POST',
          body: formData
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              location.reload(); // Recharger la page pour afficher le nouveau commentaire
            } else {
              alert(data.error || 'Échec de l\'enregistrement. Veuillez réessayer.');
            }
          })
          .catch(error => {
            console.error('Erreur lors de l\'enregistrement du commentaire :', error);
            alert('Une erreur est survenue. Veuillez réessayer plus tard.');
          });
      } else {
        fetch('/api/edit_comment.php', {
          method: 'POST',
          body: formData
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              location.reload(); // Recharger la page pour afficher le nouveau commentaire
            } else {
              alert(data.error || 'Échec de l\'enregistrement. Veuillez réessayer.');
            }
          })
          .catch(error => {
            console.error('Erreur lors de l\'enregistrement du commentaire :', error);
            alert('Une erreur est survenue. Veuillez réessayer plus tard.');
          });
      }
    });
    function deleteComment() {
      if (!confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ? Cette action est irréversible.')) {
        return;
      }
      const commentId = document.getElementById('commentaire_id').value;
      const email = document.getElementById('email').value;
      fetch(`/api/delete_comment.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ commentaire_id: commentId, email: email })
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            location.reload(); // Recharger la page pour refléter la suppression
          } else {
            alert(data.error || 'Échec de la suppression. Veuillez réessayer.');
          }
        })
        .catch(error => {
          console.error('Erreur lors de la suppression du commentaire :', error);
          alert('Une erreur est survenue. Veuillez réessayer plus tard.');
        });
    }

    // Autocomplete is now handled by Vue component in /dist/falaise-comment.js

  </script>
  <?php include "./components/footer.php"; ?>
</body>

</html>