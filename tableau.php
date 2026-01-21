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

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

$ville = $mysqli->query("SELECT ville_nom FROM villes WHERE ville_id = $ville_id")->fetch_assoc();

$stmt = $mysqli->prepare("
  SELECT
    f.*,
    g.gare_nom,
    t.train_depart, t.train_arrivee, t.train_temps, t.train_correspmin, t.train_correspmax, t.train_descr, COALESCE(t.train_tgv, 0) AS train_tgv,
      v.velo_depart, v.velo_arrivee, v.velo_km, v.velo_dplus, v.velo_dmoins, v.velo_descr, v.velo_variante, v.velo_apieduniquement, velo_apiedpossible,
      villes.ville_nom,
      f.falaise_zonename AS zone_nom
  FROM `falaises` f
  left join velo v on v.falaise_id = f.falaise_id
  left join gares g on g.gare_id = v.gare_id AND g.deleted = 0
  left join train t on t.gare_id = g.gare_id
  left join villes on villes.ville_id = t.ville_id
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://velogrimpe.fr/tableau.php?ville_id=<?= $ville_id ?>" />
  <meta name="description"
    content="Sorties escalade au départ de <?= $ville['ville_nom'] ?>. <?= count($falaises) ?> falaises décrites avec accès vélo-train.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Escalade au départ de <?= $ville['ville_nom'] ?> - Vélogrimpe.fr">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/tableau.php?ville_id=<?= $ville_id ?>">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Sorties escalade au départ de <?= $ville['ville_nom'] ?>. <?= count($falaises) ?> falaises décrites avec accès vélo-train.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title" content="Escalade au départ de <?= $ville['ville_nom'] ?> - Vélogrimpe.fr">
  <meta name="twitter:description"
    content="Sorties escalade au départ de <?= $ville['ville_nom'] ?>. <?= count($falaises) ?> falaises décrites avec accès vélo-train.">
  <title>Escalade au départ de <?= $ville['ville_nom'] ?> - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
</head>

<body class="w-screen min-h-screen">
  <?php include "./components/header.html"; ?>
  <div class="hero min-h-100 bg-center" style="background-image: url(/images/mw/005-train-2-10.webp);">
    <div class="hero-overlay bg-slate-600/70"></div>
    <div class="hero-content text-center text-base-100">
      <div class="max-w-md">
        <h1 class="text-5xl font-bold"> Falaises proches de <?php echo $ville['ville_nom'] ?>
        </h1>
      </div>
    </div>
  </div>
  <main class="md:w-4/5 max-w-(--breakpoint-xl) mx-auto p-4 flex flex-col gap-4">
    <div class="flex flex-col justify-center gap-1 items-end w-full">
      <div class="flex justify-between w-full items-center">
        <button class="btn btn-xs w-fit" onclick="instructionsDialog.showModal()">
          <svg class="w-4 h-4 fill-current">
            <use xlink:href="/symbols/icons.svg#information"></use>
          </svg> Comment lire ce tableau ?</button>
        <dialog id="instructionsDialog" class="modal">
          <div class="modal-box">
            <h3 class="text-lg font-bold"> Comment lire ce tableau ? </h3>
            <div class="p-2">
              <p>Les falaises sont classées en fonction du temps total de trajet depuis
                <?php echo $ville["ville_nom"] ?>. Ce «temps total», très théorique, est l'addition du meilleur temps
                possible en train, du temps à vélo et du temps de marche d'approche. </p>
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
      </div>
      <div class="mx-auto">
        <div class="flex flex-row items-center gap-1 px-8">
          <div class="h-px my-2 bg-base-300 rounded-lg grow"></div>
          <div class="text-xs text-slate-500 rounded-lg px-3"> Filtres</div>
          <div class="h-px my-2 bg-base-300 rounded-lg grow"></div>
        </div>
        <!-- Vue Filter Panel -->
        <div id="vue-filters"
          class="flex flex-col md:flex-row gap-1 items-center w-full max-w-full justify-center flex-wrap"></div>
      </div>
    </div>
    <!-- Vue Tableau List -->
    <div id="vue-tableau"></div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>
<!-- Initialize Vue data -->
<script>
  window.__TABLEAU_DATA__ = {
    falaises: <?php echo json_encode(array_values($falaises)); ?>,
    villeId: <?php echo $ville_id; ?>
  };
</script>
<!-- Vue.js Tableau App -->
<script type="module" src="/dist/tableau.js"></script>

</html>