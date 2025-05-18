<?php

$ville_id = $_GET['ville_id'] ?? null;
if (empty($ville_id)) {
  echo 'Pas de ville renseignée.';
  exit;
}

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
  1000 => ">= 500 voies",
];

require_once "./database/velogrimpe.php";

$ville = $mysqli->query("SELECT ville_nom FROM villes WHERE ville_id = $ville_id")->fetch_assoc();

$stmt = $mysqli->prepare("
  SELECT
    f.*,
    g.gare_nom,
    t.train_depart, t.train_arrivee, t.train_temps, t.train_correspmin, t.train_correspmax, t.train_descr,
      v.velo_depart, v.velo_arrivee, v.velo_km, v.velo_dplus, v.velo_dmoins, v.velo_descr, v.velo_variante, v.velo_apieduniquement, velo_apiedpossible,
      villes.ville_nom,
      z.zone_nom
  FROM `falaises` f
  left join velo v on v.falaise_id = f.falaise_id
  left join gares g on g.gare_id = v.gare_id
  left join train t on t.gare_id = g.gare_id
  left join villes on villes.ville_id = t.ville_id
  left join zones z on z.zone_id = f.falaise_zone
  where
    f.falaise_fermee = ''
    and villes.ville_id = ?
    and v.velo_id is not null
    and t.train_id is not null
    and f.falaise_public >= 0
    and v.velo_public >= 0
    and t.train_public >= 0
  order by f.falaise_id;
  ");
$stmt->bind_param("i", $ville_id);

$stmt->execute();
$result = $stmt->get_result();
// Store the results in a record where the key is the falaise_id and the value is the list of row where falaise_id is the same
if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $row['temps_total'] = calculate_time($row['velo_km'], $row['velo_dplus'], $row['velo_apieduniquement']) + $row["train_temps"] + $row["falaise_maa"];
    // If there is already a record for this falaise with the same train_arrivee, keep the one with the shortest total time and increment an attribute 'variante'
    // 1. find the existing row with the same train_arrivee
    // 2. if there is one, compare the total times and keep the shortest
    // 3. if the new row is shorter, replace the existing row with the new row and set the new row 'variante' to 1 + the existing row 'variante'
    // 4. if the new row is longer, increment the existing row 'variante' by 1
    // 5. if there is no existing row with the same train_arrivee, add the new row to the record
    // 6. sort the rows in the record by the total time ascending
    $existing_index = null;
    $existing_row = null;
    foreach ($falaises[$row['falaise_id']] ?? [] as $key => $existing) {
      if ($existing['train_arrivee'] == $row['train_arrivee']) {
        $existing_index = $key;
        $existing_row = $existing;
        break;
      }
    }
    if ($existing_index !== null) {
      if ($row['temps_total'] < $existing_row['temps_total']) {
        $row['variante'] = $existing_row['variante'] + 1;
        $falaises[$row['falaise_id']][$existing_index] = $row;
      } else {
        $falaises[$row['falaise_id']][$existing_index]['variante']++;
      }
      if ($row["velo_apiedpossible"] == 1) {
        $falaises[$row['falaise_id']][$existing_index]['variante_a_pied'] = 1;
      }
    } else {
      $row['variante'] = 0;
      $row['variante_a_pied'] = $row['velo_apiedpossible'];
      $falaises[$row['falaise_id']][] = $row;
    }
    // sort the rows in the record by the total time ascending
    usort($falaises[$row['falaise_id']], function ($a, $b) {
      return $a['temps_total'] <=> $b['temps_total'];
    });

  }
  // sort the falaises by the minimum total time ascending
  usort($falaises, function ($a, $b) {
    return $a[0]['temps_total'] <=> $b[0]['temps_total'];
  });
} else {
  echo "0 results";
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Au départ de <?php echo $ville['ville_nom'] ?> - Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://d3js.org/d3.v7.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <script src="/js/rose-des-vents.js"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <style type="text/tailwindcss">
    @tailwind base;
    @tailwind components;
    @tailwind utilities;
    @layer base {
      a {
        @apply text-[#2e8b57] font-bold;
      }
  }
  </style>
</head>

<body class="w-screen min-h-screen">
  <?php include "./components/header.html"; ?>
  <main class="md:w-4/5 max-w-screen-xl mx-auto p-4 flex flex-col gap-4">

    <h1 class="text-4xl font-bold text-wrap text-center mb-4 mt-2">
      Falaises proches de <?php echo $ville['ville_nom'] ?>
    </h1>
    <div class="flex flex-col justify-center gap-1 items-end w-full">
      <div class="flex justify-between w-full items-center">
        <button class="btn btn-xs w-fit" onclick="instructionsDialog.showModal()">
          <svg class="w-4 h-4 fill-current">
            <use xlink:href="/symbols/icons.svg#ri-information-line"></use>
          </svg>
          Comment lire ce tableau ?</button>
        <dialog id="instructionsDialog" class="modal">
          <div class="modal-box">
            <h3 class="text-lg font-bold">
              Comment lire ce tableau ?
            </h3>
            <div class="p-2">
              <p>Les falaises sont classées en fonction du temps total de trajet depuis
                <?php echo $ville["ville_nom"] ?>.
                Ce «temps total», très théorique, est l’addition du meilleur temps possible
                en train, du temps à vélo et du temps de marche d’approche.
              </p>
              <p><b>Abréviations</b> : </p>
              <ul class="list-disc list-inside">
                <li><b>D</b> pour "train direct", <b>1C</b> pour "une correspondance".</li>
                <li><b>D+/D-</b> pour dénivelé positif/négatif.</li>
                <li><b>GV</b> pour "grande voie".</li>
                <li>Cotations : <b>6-</b> : voies de 6a à 6b ; <b>6+</b>: voies de 6b+ à 6c+.</li>
              </ul>
            </div>
          </div>
          <form method="dialog" class="modal-backdrop">
            <button>close</button>
          </form>
        </dialog>
        <div id="nbFalaisesInFilter" class="text-primary text-sm font-bold">
          <!-- Rempli dynamiquement par la fonction `updateInfo` -->
        </div>
      </div>


      <div class="mx-auto">
        <div class="flex flex-row items-center gap-1 px-8">
          <div class="h-[1px] my-2 bg-base-300 rounded-lg flex-grow"></div>
          <div class="text-xs text-slate-500 rounded-lg px-3">
            Filtres</div>
          <div class="h-[1px] my-2 bg-base-300 rounded-lg flex-grow"></div>
        </div>

        <form id="filtersForm"
          class="flex flex-col md:flex-row gap-1 items-center w-full max-w-full justify-center flex-wrap">
          <div class="flex gap-1 items-center">
            <div class="dropdown w-fit">
              <div tabindex="0" role="button" class="btn btn-sm text-nowrap focus:pointer-events-none"
                id="voiesFilterBtn">Voies 🧗‍♀️</div>
              <div class="dropdown-content menu gap-1 bg-base-200 rounded-box z-[1] m-1 w-64 p-2 shadow-lg"
                tabindex="1">
                <div class="flex flex-col gap-2">
                  <div class="flex flex-col gap-3">
                    <div><span class="font-bold">Cotations</span> (ex: 5+ ET 6+)</div>
                    <div class="flex flex-col gap-1">
                      <div class="flex flex-row gap-4">
                        <label class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1">
                          <input type="checkbox" id="filterCot40" value="40"
                            class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                          <span class="label-text">4 et -</span>
                        </label>
                      </div>
                      <div class="flex flex-row gap-4">
                        <label
                          class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 w-16 justify-start p-0 pr-1">
                          <input type="checkbox" id="filterCot50"
                            class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                          <span class="label-text">5-</span>
                        </label>
                        <label
                          class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 w-16 justify-start p-0 pr-1">
                          <input type="checkbox" id="filterCot59"
                            class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                          <span class="label-text">5+</span>
                        </label>
                      </div>
                      <div class="flex flex-row gap-4">
                        <label
                          class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 w-16 justify-start p-0 pr-1">
                          <input type="checkbox" id="filterCot60"
                            class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                          <span class="label-text">6-</span>
                        </label>
                        <label
                          class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 w-16 justify-start p-0 pr-1">
                          <input type="checkbox" id="filterCot69"
                            class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                          <span class="label-text">6+</span>
                        </label>
                      </div>
                      <div class="flex flex-row gap-4">
                        <label
                          class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 w-16 justify-start p-0 pr-1">
                          <input type="checkbox" id="filterCot70"
                            class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                          <span class="label-text">7-</span>
                        </label>
                        <label
                          class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 w-16 justify-start p-0 pr-1">
                          <input type="checkbox" id="filterCot79"
                            class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                          <span class="label-text">7+</span>
                        </label>
                      </div>
                      <div class="flex flex-row gap-4">
                        <label class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1">
                          <input type="checkbox" id="filterCot80"
                            class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                          <span class="label-text">8 et +</span>
                        </label>
                      </div>
                      <span class="italic text-base-300 text-sm">(5- = de 5a à 5b, 5+ = de 5b+ à 5c+)</span>
                    </div>
                  </div>
                </div>
                <div class="font-bold">Types de voies</div>
                <div class="grid grid-cols-[auto_auto] gap-x-2 gap-y-1 w-full">
                  <div class="flex flex-row gap-2 items-center w-full hidden">
                    <label for=""
                      class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1 w-full justify-start">
                      <input type="checkbox" id="couenne"
                        class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" checked />
                      <span class="label-text">Couenne</span>
                    </label>
                  </div>
                  <div class="flex flex-row gap-2 items-center w-full">
                    <label for=""
                      class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1 w-full justify-start">
                      <input type="checkbox" id="avecgv"
                        class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                      <span class="label-text">Grandes voies</span>
                    </label>
                  </div>
                  <div class="flex flex-row gap-2 items-center w-full hidden">
                    <label for=""
                      class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1 w-full justify-start">
                      <input type="checkbox" id="bloc"
                        class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                      <span class="label-text">Bloc</span>
                    </label>
                  </div>
                  <div class="flex flex-row gap-2 items-center w-full hidden">
                    <label for=""
                      class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1 w-full justify-start">
                      <input type="checkbox" id="psychobloc"
                        class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                      <span class="label-text">Psychobloc</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <div class="dropdown w-fit">
              <div tabindex="0" role="button" class="btn btn-sm text-nowrap focus:pointer-events-none"
                id="expoFilterBtn">Exposition 🔅</div>
              <div class="dropdown-content menu bg-base-200 rounded-box z-[1] m-1 w-40 p-2 shadow-lg" tabindex="1">
                <div class="flex flex-row gap-1 items-center">
                  <div class="max-w-96 flex flex-col gap-1 w-full">
                    <label
                      class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1 w-full justify-start">
                      <input type="checkbox" id="filterExpoN"
                        class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                      <span class="label-text">Nord
                        <span class="text-xs text-slate-400">(NO, N, NE)</span>
                      </span>
                    </label>
                    <label
                      class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1 w-full justify-start">
                      <input type="checkbox" id="filterExpoE"
                        class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                      <span class="label-text">Est
                        <span class="text-xs text-slate-400">(NE, E, SE)</span>
                      </span>
                    </label>
                    <label
                      class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1 w-full justify-start">
                      <input type="checkbox" id="filterExpoS"
                        class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                      <span class="label-text">Sud
                        <span class="text-xs text-slate-400">(SE, S, SO)</span>
                      </span>
                    </label>
                    <label
                      class="label hover:bg-base-300 rounded-lg cursor-pointer gap-2 p-0 pr-1 w-full justify-start">
                      <input type="checkbox" id="filterExpoO"
                        class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))] checkbox-sm" />
                      <span class="label-text">Ouest
                        <span class="text-xs text-slate-400">(SO, O, NO)</span>
                      </span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <div class="dropdown w-fit dropdown-end">
              <div tabindex="0" role="button" class="btn btn-sm text-nowrap focus:pointer-events-none"
                id="trainFilterBtn">Train 🚞</div>
              <div class="dropdown-content menu bg-base-200 rounded-box z-[1] m-1 w-64 p-2 shadow-lg" tabindex="1">
                <label class="flex flex-row gap-2 items-center">
                  <div class="font-bold">Durée</div>
                  <div class="text-normal font-bold">&le;</div>
                  <input type="number" id="tempsMaxTrain" step="1" min="0" class="input input-bordered input-sm w-14" />
                  <div>min.</div>
                </label>
                <div class="flex flex-row items-center gap-1">
                  <div>Nb. Corresp. Max</div>
                  <div class="flex flex-row gap-2 items-center">
                    <label class="label cursor-pointer gap-1">
                      <input value="0" type="radio" name="nbCorrespMax" id="nbCorrespMax0"
                        class="radio radio-primary radio-xs" />
                      <span class="label-text">0</span>
                    </label>
                    <label class="label cursor-pointer gap-1">
                      <input value="1" type="radio" name="nbCorrespMax" id="nbCorrespMax1"
                        class="radio radio-primary radio-xs" />
                      <span class="label-text">&le;1</span>
                    </label>
                    <label class="label cursor-pointer gap-1">
                      <input value="10" type="radio" name="nbCorrespMax" id="nbCorrespMax10"
                        class="radio radio-primary radio-xs" checked hidden />
                      <svg id="nbCorrespMax10Reset" class="w-3 h-3 fill-current hidden">
                        <use xlink:href="/symbols/icons.svg#ri-repeat-line"></use>
                      </svg>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="flex gap-1 items-center">
            <div class="dropdown w-fit">
              <div tabindex="0" role="button" class="btn btn-sm text-nowrap focus:pointer-events-none"
                id="veloFilterBtn">Vélo 🚲
              </div>
              <div class="dropdown-content menu bg-base-200 rounded-box z-[1] m-1 w-52 p-2 shadow-lg" tabindex="1">
                <div class="flex flex-row gap-3 items-center">
                  <div>Trajet vélo</div>
                  <div class="flex flex-col gap-1">
                    <label class="flex flex-row gap-2 flex-wrap items-center">
                      <div class="text-normal font-bold">&le;</div>
                      <input type="number" id="tempsMaxVelo" step="1" min="0"
                        class="input input-bordered input-sm w-14" />
                      <div>min</div>
                    </label>
                    <label class="flex flex-row gap-2 items-center">
                      <div class="text-normal font-bold">&le;</div>
                      <input type="number" id="distMaxVelo" step="1" min="0"
                        class="input input-bordered input-sm w-14" />
                      <div>km</div>
                    </label>
                    <label class="flex flex-row gap-2 items-center">
                      <div class="text-normal font-bold">&le;</div>
                      <input type="number" id="denivMaxVelo" step="1" min="0"
                        class="input input-bordered input-sm w-14" />
                      <div>D+</div>
                    </label>
                  </div>
                </div>
                <div class="flex flex-row gap-2 items-center">
                  <div class="bg-base-100 rounded-full w-6 h-6 border-2 border-base-300
                flex items-center justify-center text-xs text-slate-600 font-bold">OU</div>
                  <label class="flex flex-row gap-2 items-center hover:bg-base-300 rounded-lg cursor-pointer p-0 pr-1">
                    <input type="checkbox" id="apieduniquement"
                      class="checkbox border-base-300 bg-base-100 [--chkbg:oklch(var(--p))]" />
                    <div>Accessible à pied</div>
                  </label>
                </div>
              </div>
            </div>
            <div class="dropdown w-fit">
              <div tabindex="0" role="button" class="btn btn-sm text-nowrap focus:pointer-events-none"
                id="approcheFilterBtn">Marche 🥾</div>
              <div class="dropdown-content menu bg-base-200 rounded-box z-[1] m-1 w-48 p-2 shadow-lg" tabindex="1">
                <label class="flex flex-row gap-2 items-center">
                  <div class="font-bold">Approche</div>
                  <div class="text-normal font-bold">&le;</div>
                  <input type="number" id="tempsMaxMA" step="1" min="0" class="input input-bordered input-sm w-14" />
                  <div>min.</div>
                </label>
              </div>
            </div>
            <div class="dropdown w-fit dropdown-end">
              <div tabindex="0" role="button" class="btn btn-sm text-nowrap focus:pointer-events-none"
                id="totalFilterBtn">Total ⏱️</div>
              <div class="dropdown-content menu bg-base-200 rounded-box z-[1] m-1 p-2 shadow-lg" tabindex="1">
                <div class="flex flex-col gap-2 items-end">
                  <label class="flex flex-row gap-1 items-center">
                    <div class="">Train+Vélo</div>
                    <div class="text-normal font-bold">&le;</div>
                    <input type="number" id="tempsMaxTV" step="1" min="0" class="input input-bordered input-sm w-14" />
                    <div>min.</div>
                  </label>
                  <label class="flex flex-row gap-1 items-center">
                    <div class="">Train+Vélo+Approche</div>
                    <div class="text-normal font-bold">&le;</div>
                    <input type="number" id="tempsMaxTVA" step="1" min="0" class="input input-bordered input-sm w-14" />
                    <div>min.</div>
                  </label>
                </div>
              </div>
            </div>
            <button id="resetButton" class="btn btn-xs btn-ghost px-1 text-nowrap disabled:hidden" type="reset"
              title="Réinitialiser les filtres">
              <svg class="w-4 h-4 fill-current">
                <use xlink:href="/symbols/icons.svg#ri-close-circle-line"></use>
              </svg>
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- VERSION MOBILE -->
    <div class="flex flex-col gap-4 md:hidden">
      <?php foreach ($falaises as $falaise_id => $acces): ?>
        <?php $common = $acces[0]; ?>
        <a href="<?php echo '/falaise.php?falaise_id=' . $common['falaise_id'] . "&ville_id=" . $ville_id ?>"
          class="text-base-content hover:no-underline font-normal" id="falaise-<?= $common['falaise_id'] ?>-mobile">
          <div class="flex flex-col rounded-lg shadow-xl bg-base-100 p-2 text-sm
                      ">
            <div class="flex flex-row justify-between gap-1">
              <h3 class="text-xl font-bold text-primary hover:underline">
                <?php echo $common["falaise_nom"] ?>
                <?php if (!empty($common["falaise_fermee"])): ?>
                  <span class="text-error font-normal">Falaise Interdite</span>
                <?php endif; ?>
              </h3>
              <div class="font-bold text-xl"><?php echo format_time($common["temps_total"]) ?></div>
            </div>
            <div class="w-full flex flex-row items-center justify-between gap-2">
              <div class="flex flex-col items-start justify-start flex-grow">
                <div>
                  <b>Zone</b> : <?php echo $common['zone_nom'] ?>
                </div>
                <div>
                  <b title="Cotations (6-: 6a à 6b, 6+: 6b+ à 6c+ etc.)">Cotations</b> : <span>de
                    <?php echo $common["falaise_cotmin"] ?> à
                    <?php echo $common["falaise_cotmax"] ?>
                  </span>
                </div>
                <?php if ($common["falaise_gvnb"] > 0): ?>
                  <div class="text-accent"><?php echo $common["falaise_gvnb"] ?> <span title="Grandes Voies"></span></div>
                <?php endif; ?>
                <div>
                  <b title="Marche d'approche">Marche d'approche</b> :
                  <?php if ($common["falaise_maa"] > 0): ?>
                    <span>
                      <?php echo format_time($common["falaise_maa"]) ?>
                    </span>
                  <?php else: ?>
                    <span>Aucune</span>
                  <?php endif; ?>
                </div>


              </div>
              <div id="<?php echo 'rose-mobile-' . $common['falaise_id'] ?>" class="w-[72px]"></div>
            </div>
            <div class="w-full">
              <!-- <hr class="w-4/5 border-base-300 border-t-[1px] mx-auto" /> -->
              <div class="border-base-300"><b>Accès depuis <?php echo $common["ville_nom"] ?> :</b></div>
              <ul class="list-disc list-inside">
                <?php foreach ($acces as $row): ?>
                  <li>
                    <?php if ($row["train_temps"] > 0): ?>
                      Train pour <?php echo $row["train_arrivee"] ?>
                      (<?php echo format_time($row["train_temps"]) ?>,
                      <span title='D=Direct / C=Correspondances'>
                        <?php echo ($row["train_correspmin"] == 0 ? "D" : $row["train_correspmin"] . "C")
                          . ($row["train_correspmax"] == 0 || $row["train_correspmax"] == $row["train_correspmin"] ? "" : "/" . $row["train_correspmax"] . "C")
                          ?></span>)
                      +
                    <?php endif; ?>
                    <?php echo format_time(calculate_time($row['velo_km'], $row['velo_dplus'], $row['velo_apieduniquement'])) ?>
                    <?php echo $row["velo_apieduniquement"] == 1 ? "À pied" : "à vélo" ?>
                    <?php if (($row["variante_a_pied"] ?? 0) == 1): ?>
                      <br /><span class='text-primary'>Aussi accessible à pied</span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
      <div id="nomatch-mobile"
        class="bg-base-100 text-center w-full col-span-6 py-4 font-bold hidden rounded-lg shadow-lg">Aucune falaise
        ne correspond aux filtres.
      </div>
    </div>
    <!-- VERSION DESKTOP -->
    <div class="hidden
                md:grid grid-cols-[1.5fr_1fr_2fr_2fr_60px] gap-[1px] 
                bg-base-300 shadow-xl rounded-lg overflow-hidden
                text-center items-center text-sm">
      <div class="bg-base-100 px-2 py-1 self-stretch flex items-center justify-center"></div>
      <div class="bg-base-100 px-2 py-1 self-stretch flex items-center justify-center">
        <img class="h-12" alt="Train" src="/images/train-station_color.png" />
      </div>
      <div class="bg-base-100 px-2 py-1 self-stretch flex items-center justify-center">
        <img class="h-12" alt="Velo" src="/images/bicycle_color.png" />
      </div>
      <div class="bg-base-100 px-2 py-1 self-stretch flex items-center justify-center">
        <img class="h-12" alt="Corde" src="/images/rock-climbing_color.png" />
      </div>
      <!-- <div class="bg-base-100 px-2 py-1 self-stretch flex items-center justify-center font-bold">Zone</div> -->
      <div class="bg-base-100 px-1 py-1 self-stretch flex items-center justify-center font-bold text-xs">
        Temps total (T+V+A)
      </div>
      <?php foreach ($falaises as $falaise_id => $acces): ?>
        <?php $common = $acces[0]; ?>
        <div
          class="bg-base-100 px-2 py-1 self-stretch font-bold flex flex-col items-center justify-center text-base falaise-<?= $common['falaise_id'] ?>-desktop">
          <div>
            <a href="<?php echo '/falaise.php?falaise_id=' . $acces[0]['falaise_id'] . "&ville_id=" . $ville_id ?>">
              <?php echo $common["falaise_nom"] ?>
            </a>
            <?php if (!empty($common["falaise_fermee"])): ?>
              <div class="text-error text-sm font-normal">Falaise Interdite</div>
            <?php endif; ?>
            <?php if (!empty($common["zone_nom"])): ?>
              <div class="font-normal text-xs">(<?= $common["zone_nom"] ?>)</div>
            <?php endif; ?>
          </div>
        </div>
        <div
          class="bg-base-100 py-1 self-stretch grid grid-rows-<?php echo count($acces) ?> divide-y divide-slate-200 items-center falaise-<?= $common['falaise_id'] ?>-desktop">
          <?php foreach ($acces as $row): ?>
            <div class="self-stretch flex flex-col justify-center py-2 px-2">
              <div class="text-base font-bold">
                <?php
                if ($row["train_temps"] > 0) {
                  echo format_time($row["train_temps"])
                    . " <span title='D=Direct / C=Correspondances'>("
                    . ($row["train_correspmin"] == 0 ? "D" : $row["train_correspmin"] . "C")
                    . ($row["train_correspmax"] == 0 || $row["train_correspmax"] == $row["train_correspmin"] ? "" : "/" . $row["train_correspmax"] . "C")
                    . ")</span>";
                } else {
                  echo "Pas de train à prendre";
                }
                ?>
              </div>
              <div class="text-nowrap"><?php echo $row["train_arrivee"] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div
          class="bg-base-100 py-1 self-stretch grid grid-rows-<?php echo count($acces) ?> divide-y divide-slate-200 items-center falaise-<?= $common['falaise_id'] ?>-desktop">
          <?php foreach ($acces as $row): ?>
            <div class="self-stretch flex flex-col justify-center py-2 px-2">
              <div class="text-base font-bold">
                Aller :
                <?php echo format_time(calculate_time($row['velo_km'], $row['velo_dplus'], $row['velo_apieduniquement'])) ?>
                -
                Retour :
                <?php echo format_time(calculate_time($row['velo_km'], $row['velo_dmoins'], $row['velo_apieduniquement'])) ?>
              </div>
              <div><?php echo $row["velo_km"] ?> km, <?php echo $row["velo_dplus"] ?> D+,
                <?php echo $row["velo_dmoins"] ?> D-
              </div>
              <?php if ($row["velo_apieduniquement"] == 1): ?>
                <div class="text-primary">À pied uniquement</div>
              <?php endif; ?>
              <?php if (($row["variante_a_pied"] ?? 0) == 1): ?>
                <div class="text-primary">Aussi accessible à pied</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div
          class="bg-base-100 px-2 py-1 self-stretch flex flex-row items-center justify-end gap-2 falaise-<?= $common['falaise_id'] ?>-desktop">
          <div class="flex flex-col items-center justify-center gap-1 flex-grow">
            <?php $row = $acces[0]; ?>
            <div><span title="Marche d'approche">Marche d'approche</span> : <span class="font-bold">
                <?php if ($row["falaise_maa"] > 0): ?>
                  <?= format_time($row["falaise_maa"]) ?>
                <?php else: ?>
                  Aucune
                <?php endif; ?>
              </span>
            </div>
            <div>
              <span class="font-bold"><?= $nbvoies_corresp[$row["falaise_nbvoies"]] ?? "Voies" ?></span>
              de
              <span class="font-bold" title="Cotations (6-: 6a à 6b, 6+: 6b+ à 6c+ etc.)"><?= $row["falaise_cotmin"] ?> à
                <?= $row["falaise_cotmax"] ?></span>
            </div>
            <?php if ($row["falaise_gvnb"] > 0): ?>
              <div class="text-accent"><?php echo $row["falaise_gvnb"] ?> <span title="Grandes Voies"></span></div>
            <?php endif; ?>
          </div>
          <div id="<?php echo 'rose-' . $row['falaise_id'] ?>" class="w-[72px]"></div>
        </div>
        <!-- <div
          class="bg-base-100 px-2 py-1 self-stretch flex flex-col justify-center items-center falaise-<?= $common['falaise_id'] ?>-desktop">
          <?php echo $row["zone_nom"] ?>
        </div> -->
        <div
          class="bg-base-100 py-1 self-stretch flex flex-col items-center justify-center gap-1 font-bold h-full divide-y divide-slate-200 falaise-<?= $common['falaise_id'] ?>-desktop">
          <?php foreach ($acces as $row): ?>
            <div class="flex-grow flex items-center justify-center px-2">
              <?php echo format_time(calculate_time($row['velo_km'], $row['velo_dplus'], $row['velo_apieduniquement']) + $row["train_temps"] + $row["falaise_maa"]) ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div id="nomatch" class="bg-base-100 text-center w-full col-span-5 py-4 font-bold hidden">Aucune falaise
        ne correspond aux filtres.
      </div>
    </div>

  </main>
</body>

<script>
  window.addEventListener("DOMContentLoaded", function () {
    <?php foreach ($falaises as $falaise_id => $acces): ?>
      roseFromExpo("rose-" + <?php echo $acces[0]['falaise_id'] ?>, "<?php echo $acces[0]["falaise_exposhort1"] ?>", "<?php echo $acces[0]["falaise_exposhort2"] ?>", 72, 72);
      roseFromExpo("rose-mobile-" + <?php echo $acces[0]['falaise_id'] ?>, "<?php echo $acces[0]["falaise_exposhort1"] ?>", "<?php echo $acces[0]["falaise_exposhort2"] ?>", 72, 72);
    <?php endforeach; ?>
  });


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

  // ============================================ FILTRES ============================================
  const falaises = <?php echo json_encode($falaises); ?>;

  function renderFalaises() {
    falaises.forEach(f => {
      const desktopEls = [].slice.call(document.getElementsByClassName("falaise-" + f[0].falaise_id + "-desktop"));
      const mobileEl = document.getElementById("falaise-" + f[0].falaise_id + "-mobile");
      if (f[0].filteredOut) {
        desktopEls.forEach(el => el.style.display = "none");
        mobileEl.style.display = "none";
      } else {
        // remove display style
        desktopEls.forEach(el => el.style.removeProperty("display"));
        mobileEl.style.removeProperty("display");
      }
    });
    if (falaises.filter(f => !f[0].filteredOut).length === 0) {
      document.getElementById("nomatch").style.display = "block";
      document.getElementById("nomatch-mobile").style.display = "block";
    } else {
      document.getElementById("nomatch").style.display = "none";
      document.getElementById("nomatch-mobile").style.display = "none";
    }
  }

  function updateInfo() {
    const nbFalaisesInFilter = falaises.filter(f => !f[0].filteredOut).length;
    const nbFalaises = falaises.length;
    const infoDiv = document.getElementById("nbFalaisesInFilter");
    infoDiv.textContent = nbFalaisesInFilter + " falaises";
  }
  updateInfo();


  const expoFilterBtn = document.getElementById("expoFilterBtn");
  const voiesFilterBtn = document.getElementById("voiesFilterBtn");
  const trainFilterBtn = document.getElementById("trainFilterBtn");
  const veloFilterBtn = document.getElementById("veloFilterBtn");
  const approcheFilterBtn = document.getElementById("approcheFilterBtn");
  const totalFilterBtn = document.getElementById("totalFilterBtn");

  function resetFalaises() {
    falaises.forEach(falaise => {
      falaise[0].filteredOut = false;
    });
    renderFalaises();
    updateInfo();
    [expoFilterBtn, voiesFilterBtn, trainFilterBtn, veloFilterBtn, approcheFilterBtn, totalFilterBtn].map(
      btn => btn.classList.remove("btn-primary")
    );
  }

  const filterHandler = (event) => {
    const expoN = document.getElementById("filterExpoN").checked;
    const expoE = document.getElementById("filterExpoE").checked;
    const expoS = document.getElementById("filterExpoS").checked;
    const expoO = document.getElementById("filterExpoO").checked;
    const cot40 = document.getElementById("filterCot40").checked;
    const cot50 = document.getElementById("filterCot50").checked;
    const cot59 = document.getElementById("filterCot59").checked;
    const cot60 = document.getElementById("filterCot60").checked;
    const cot69 = document.getElementById("filterCot69").checked;
    const cot70 = document.getElementById("filterCot70").checked;
    const cot79 = document.getElementById("filterCot79").checked;
    const cot80 = document.getElementById("filterCot80").checked;
    const avecgv = document.getElementById("avecgv").checked;
    const apieduniquement = document.getElementById("apieduniquement").checked;
    const tempsMaxVelo = document.getElementById("tempsMaxVelo").value;
    const distMaxVelo = document.getElementById("distMaxVelo").value;
    const denivMaxVelo = document.getElementById("denivMaxVelo").value;
    const tempsMaxTrain = document.getElementById("tempsMaxTrain").value;
    const nbCorrespMax0 = document.getElementById("nbCorrespMax0").checked;
    const nbCorrespMax1 = document.getElementById("nbCorrespMax1").checked;
    const nbCorrespMax = nbCorrespMax0 ? 0 : nbCorrespMax1 ? 1 : 10;
    const tempsMaxMA = document.getElementById("tempsMaxMA").value;
    const tempsMaxTV = document.getElementById("tempsMaxTV").value;
    const tempsMaxTVA = document.getElementById("tempsMaxTVA").value;

    const expoFiltered = [expoN, expoE, expoS, expoO].some(e => e);
    const cotFiltered = [cot40, cot50, cot59, cot60, cot69, cot70, cot79, cot80].some(e => e);
    // Indicators
    if (expoFiltered) {
      expoFilterBtn.classList.add("btn-primary");
    } else {
      expoFilterBtn.classList.remove("btn-primary");
    }
    if (cotFiltered || avecgv) {
      voiesFilterBtn.classList.add("btn-primary");
    } else {
      voiesFilterBtn.classList.remove("btn-primary");
    }
    if (tempsMaxTrain !== "" || nbCorrespMax !== 10) {
      trainFilterBtn.classList.add("btn-primary");
    } else {
      trainFilterBtn.classList.remove("btn-primary");
    }
    if (tempsMaxVelo !== "" || distMaxVelo !== "" || denivMaxVelo !== "" || apieduniquement) {
      veloFilterBtn.classList.add("btn-primary");
    } else {
      veloFilterBtn.classList.remove("btn-primary");
    }
    if (tempsMaxMA !== "") {
      approcheFilterBtn.classList.add("btn-primary");
    } else {
      approcheFilterBtn.classList.remove("btn-primary");
    }
    if (tempsMaxTV !== "" || tempsMaxTVA !== "") {
      totalFilterBtn.classList.add("btn-primary");
    } else {
      totalFilterBtn.classList.remove("btn-primary");
    }

    // Case 1 : all default values --> set all falaises visible (even hors topo)
    if (
      !expoFiltered
      && !cotFiltered
      && !avecgv
      && !apieduniquement
      && tempsMaxVelo === ""
      && denivMaxVelo === ""
      && distMaxVelo === ""
      && tempsMaxMA === ""
      && tempsMaxTV === ""
      && tempsMaxTVA === ""
      && nbCorrespMax === 10
      && tempsMaxTrain === ""
    ) {
      resetFalaises();
      resetButton.disabled = true;
    }
    // // Case 2: At least one filter is set --> set falaises hors topo hidden and apply filters
    // Case 2: At least one filter is set --> set falaises hors topo hidden and apply filters
    else {
      resetButton.disabled = false;
      falaises.forEach(falaiseItineraires => {
        const falaise = falaiseItineraires[0]; // Note: Common part
        const estCotationsCompatible = (
          (!cot40 || ("4+".localeCompare(falaise.falaise_cotmin) >= 0))
          && (!cot50 || ("5-".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("5-") >= 0))
          && (!cot59 || ("5+".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("5+") >= 0))
          && (!cot60 || ("6-".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("6-") >= 0))
          && (!cot69 || ("6+".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("6+") >= 0))
          && (!cot70 || ("7-".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("7-") >= 0))
          && (!cot79 || ("7+".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("7+") >= 0))
          && (!cot80 || (falaise.falaise_cotmax.localeCompare("8-") >= 0))
        );
        const estTrainCompatible = (
          falaiseItineraires.some(it => {
            const duration = calculate_time(it);
            return (
              (tempsMaxTrain === "" || parseInt(it.train_temps) <= parseInt(tempsMaxTrain))
              && (nbCorrespMax === 10 || parseInt(it.train_correspmax) <= nbCorrespMax)
              && (tempsMaxTV === "" || parseInt(it.train_temps) + duration <= parseInt(tempsMaxTV))
              && (tempsMaxTVA === "" || parseInt(it.temps_total) <= parseInt(tempsMaxTVA))
            )
          }));

        // Main filter logic
        if (
          (!expoFiltered || (
            (expoN && (falaise.falaise_exposhort1.includes("'N") || falaise.falaise_exposhort2.includes("'N")))
            || (expoE && (falaise.falaise_exposhort1.match(/('E|'NE'|'SE')/) || falaise.falaise_exposhort2.match(/('E|'NE'|'SE')/)))
            || (expoS && (falaise.falaise_exposhort1.includes("'S") || falaise.falaise_exposhort2.includes("'S")))
            || (expoO && (falaise.falaise_exposhort1.match(/('O|'NO'|'SO')/) || falaise.falaise_exposhort2.match(/('O|'NO'|'SO')/)))
          ))
          && (!cotFiltered || estCotationsCompatible)
          && (tempsMaxMA === "" || parseInt(falaise.falaise_maa || 0) <= parseInt(tempsMaxMA))
          && (avecgv === false || !!falaise.falaise_gvnb)
          && estTrainCompatible
          && falaiseItineraires.some(it => {
            const duration = calculate_time(it);
            return (
              (tempsMaxVelo === "" || duration <= tempsMaxVelo)
              && (denivMaxVelo === "" || parseInt(it.velo_dplus) <= denivMaxVelo)
              && (distMaxVelo === "" || parseFloat(it.velo_km) <= distMaxVelo)
              && (apieduniquement === false || it.velo_apieduniquement === 1 || it.velo_apiedpossible === 1)
            );
          }
          )
        ) {
          falaise.filteredOut = false;
        } else {
          falaise.filteredOut = true;
        }
      });
    }
    // }
    const nbInFilter = falaises.filter(f => !f[0].filteredOut).length;
    renderFalaises();
    updateInfo();
  }
  document.querySelectorAll("#filtersForm input").forEach(i => i.addEventListener("change", filterHandler));
  document.querySelectorAll("input[type=radio][name=nbCorrespMax]").forEach(i => i.addEventListener("change", (e) => {
    const resetIcon = document.getElementById("nbCorrespMax10Reset");
    const defaultCheckbox = document.getElementById("nbCorrespMax10");
    if (!defaultCheckbox.checked) {
      resetIcon.classList.add("text-primary");
      resetIcon.classList.remove("hidden");
    } else {
      resetIcon.classList.add("hidden");
      resetIcon.classList.remove("text-primary");
    }
  }));
  resetButton.addEventListener("click", (e) => {
    e.preventDefault();
    document.getElementById("filtersForm").reset();
    resetButton.disabled = true;
    resetFalaises();
  });
  document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("filtersForm").reset();
    resetButton.disabled = true;
    resetFalaises();
    updateInfo();
  });

</script>

</html>