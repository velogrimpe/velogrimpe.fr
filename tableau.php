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

//Calculatrice de temps de trajet vélo (km/20+dplus/500)

function calculate_time($distance_km, $elevation_m)
{
  $time_in_hours = $distance_km / 20 + $elevation_m / 500;
  $time_in_minutes = round($time_in_hours * 60);
  return $time_in_minutes;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/schema.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';

$ville_id = (int) $ville_id;
$ville = $mysqli->query("SELECT ville_nom FROM villes WHERE ville_id = $ville_id")->fetch_assoc();

// Lignes envoyées telles quelles à l'app Vue (window.__TABLEAU_DATA__) : n'y
// mettre que les champs de TableauItinerary (frontend/src/types/tableau.ts).
// Pas de f.* : la ligne contient l'email du contributeur (falaise_contrib).
// Valable aussi pour la requête des accès piétons plus bas.
$stmt = $mysqli->prepare("
  SELECT
    f.falaise_id, f.falaise_nom, f.falaise_cotmin, f.falaise_cotmax, f.falaise_nbvoies, f.falaise_maa,
    f.falaise_exposhort1, f.falaise_exposhort2, f.falaise_altitude, f.falaise_bloc, f.falaise_gvnb,
    f.falaise_fermee, f.falaise_zonename, f.falaise_deptname, f.falaise_deptcode,
    a.nom AS gare_nom,
    t.train_arrivee, t.train_temps, t.train_correspmin, t.train_correspmax, COALESCE(t.train_tgv, 0) AS train_tgv,
      v.km AS velo_km, v.dplus AS velo_dplus, v.dmoins AS velo_dmoins, " . acces_a_pied_sql('v') . " AS velo_apiedpossible,
      0 AS acces_pieton,
      EXISTS(SELECT 1 FROM acces_falaise p WHERE p.type = 'pied' AND p.falaise_id = f.falaise_id) AS falaise_acces_pieton,
      villes.ville_nom
  FROM `falaises` f
  left join acces_falaise v on v.falaise_id = f.falaise_id and v.type = 'velo'
  left join arrets a on a.id = v.arret_id AND a.deleted = 0
  left join train t on t.arret_id = a.id
  left join villes on villes.ville_id = t.ville_id
  where
    f.falaise_fermee = ''
    and villes.ville_id = ?
    and v.acces_id is not null
    and t.train_id is not null
    and f.falaise_public >= 0
    and v.public >= 0
    and t.train_public >= 0
  order by f.falaise_id;
  ");
$stmt->bind_param("i", $ville_id);

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Accès à pied depuis un arrêt relié à la ville par un train : une ligne par
// accès, en plus des itinéraires vélo (même depuis le même arrêt). Sans
// itinéraire vélo (velo_km/dplus/dmoins à 0) ni temps total : le temps de marche
// arrêt → falaise n'est pas connu. Modes et lignes de l'arrêt en `acces_pieton_*`.
$stmtPieton = $mysqli->prepare("
  SELECT
    f.falaise_id, f.falaise_nom, f.falaise_cotmin, f.falaise_cotmax, f.falaise_nbvoies, f.falaise_maa,
    f.falaise_exposhort1, f.falaise_exposhort2, f.falaise_altitude, f.falaise_bloc, f.falaise_gvnb,
    f.falaise_fermee, f.falaise_zonename, f.falaise_deptname, f.falaise_deptcode,
    a.nom AS gare_nom,
    a.ter, a.tgv, a.bus, a.tram, a.teleph, a.id AS acces_pieton_arret_id,
    t.train_arrivee, t.train_temps, t.train_correspmin, t.train_correspmax, COALESCE(t.train_tgv, 0) AS train_tgv,
    0 AS velo_km, 0 AS velo_dplus, 0 AS velo_dmoins,
    p.acces_id AS acces_pieton_acces_id, p.km AS acces_pieton_km, p.dplus AS acces_pieton_dplus, p.dmoins AS acces_pieton_dmoins,
    1 AS velo_apiedpossible, 1 AS acces_pieton, 1 AS falaise_acces_pieton,
    villes.ville_nom
  FROM acces_falaise p
  JOIN falaises f ON f.falaise_id = p.falaise_id
  JOIN arrets a ON a.id = p.arret_id AND a.deleted = 0
  JOIN train t ON t.arret_id = a.id
  JOIN villes ON villes.ville_id = t.ville_id
  WHERE
    p.type = 'pied'
    AND p.public >= 0
    AND f.falaise_fermee = ''
    AND villes.ville_id = ?
    AND f.falaise_public >= 0
    AND t.train_public >= 0
  ORDER BY f.falaise_id");
$stmtPieton->bind_param("i", $ville_id);
$stmtPieton->execute();
$rowsPieton = $stmtPieton->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPieton->close();
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_lib.php';
$lignesParArret = [];
if ($rowsPieton) {
  $ids = implode(',', array_unique(array_map(fn($r) => (int) $r['acces_pieton_arret_id'], $rowsPieton)));
  // Les lignes desservent les arrêts enfants : celles d'un parent sont celles de ses enfants (une fois chacune)
  foreach ($mysqli->query("SELECT DISTINCT COALESCE(a.parent_id, a.id) AS arret_id, l.nom
      FROM arrets_lignes_transport alt
      JOIN arrets a ON a.id = alt.arret_id
      JOIN lignes_transport l ON l.id = alt.ligne_id
      WHERE COALESCE(a.parent_id, a.id) IN ($ids) ORDER BY l.nom")->fetch_all(MYSQLI_ASSOC) as $l) {
    $lignesParArret[(int) $l['arret_id']][] = $l['nom'];
  }
}
$rowsPieton = array_map(function ($r) use ($lignesParArret) {
  $r['acces_pieton_types'] = arret_types($r);
  $r['acces_pieton_lignes'] = $lignesParArret[(int) $r['acces_pieton_arret_id']] ?? [];
  unset($r['ter'], $r['tgv'], $r['bus'], $r['tram'], $r['teleph'], $r['acces_pieton_arret_id']);
  // Distance / dénivelés de l'accès, quand renseignés (null sinon)
  $r['acces_pieton_km'] = $r['acces_pieton_km'] !== null ? (float) $r['acces_pieton_km'] : null;
  $r['acces_pieton_dplus'] = $r['acces_pieton_dplus'] !== null ? (int) $r['acces_pieton_dplus'] : null;
  $r['acces_pieton_dmoins'] = $r['acces_pieton_dmoins'] !== null ? (int) $r['acces_pieton_dmoins'] : null;
  $r['temps_total'] = null;
  $r['variante'] = 0;
  $r['variante_a_pied'] = 1;
  return $r;
}, $rowsPieton);

$falaises = [];
// Store the results in a record where the key is the falaise_id and the value is the list of row where falaise_id is the same
if (count($rows) > 0 || count($rowsPieton) > 0) {
  foreach ($rows as $row) {
    $row['acces_pieton_types'] = [];
    $row['acces_pieton_lignes'] = [];
    $row['acces_pieton_km'] = $row['acces_pieton_dplus'] = $row['acces_pieton_dmoins'] = null;
    $row['temps_total'] = calculate_time($row['velo_km'], $row['velo_dplus']) + $row["train_temps"] + $row["falaise_maa"];
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
        // la variante remplacée reste une option : on garde son accès à pied
        $row['variante_a_pied'] = $existing_row['variante_a_pied'];
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
  // Les accès à pied s'ajoutent après les itinéraires vélo de leur falaise (sans variantes ni tri par temps).
  foreach ($rowsPieton as $row) {
    $falaises[$row['falaise_id']][] = $row;
  }
  // Falaises ayant un accès à pied (arrêt de bus, tram…) qui n'est PAS affiché ci-dessus, faute de train depuis cette
  // ville vers l'arrêt : l'interface ajoute une ligne « Également accessible sans vélo via les TC ».
  $accesAffiches = array_flip(array_map(fn($r) => (int) $r['acces_pieton_acces_id'], $rowsPieton));
  $idsFalaises = array_keys($falaises);
  $falaisesTcAilleurs = [];
  if ($idsFalaises) {
    $in = implode(',', array_map('intval', $idsFalaises));
    foreach ($mysqli->query("SELECT p.acces_id, p.falaise_id FROM acces_falaise p JOIN arrets a ON a.id = p.arret_id AND a.deleted = 0
        WHERE p.type = 'pied' AND p.public >= 0 AND p.falaise_id IN ($in)")->fetch_all(MYSQLI_ASSOC) as $p) {
      if (!isset($accesAffiches[(int) $p['acces_id']])) {
        $falaisesTcAilleurs[(int) $p['falaise_id']] = 1;
      }
    }
  }
  foreach ($falaises as $fid => &$rowsFalaise) {
    foreach ($rowsFalaise as &$r) {
      $r['falaise_acces_tc_ailleurs'] = $falaisesTcAilleurs[(int) $fid] ?? 0;
    }
    unset($r);
  }
  unset($rowsFalaise);
  // sort the falaises by the minimum total time ascending ; sans temps total (accès piéton seuls) : en dernier
  usort($falaises, function ($a, $b) {
    return ($a[0]['temps_total'] ?? PHP_INT_MAX) <=> ($b[0]['temps_total'] ?? PHP_INT_MAX);
  });
} else {
  echo "0 results";
}

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
    content="Sorties escalade au départ de <?= htmlspecialchars($ville['ville_nom'], ENT_QUOTES, 'UTF-8') ?>. <?= count($falaises) ?> falaises décrites avec accès vélo-train.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Escalade au départ de <?= htmlspecialchars($ville['ville_nom'], ENT_QUOTES, 'UTF-8') ?> - Vélogrimpe.fr">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/tableau.php?ville_id=<?= $ville_id ?>">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Sorties escalade au départ de <?= htmlspecialchars($ville['ville_nom'], ENT_QUOTES, 'UTF-8') ?>. <?= count($falaises) ?> falaises décrites avec accès vélo-train.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title" content="Escalade au départ de <?= htmlspecialchars($ville['ville_nom'], ENT_QUOTES, 'UTF-8') ?> - Vélogrimpe.fr">
  <meta name="twitter:description"
    content="Sorties escalade au départ de <?= htmlspecialchars($ville['ville_nom'], ENT_QUOTES, 'UTF-8') ?>. <?= count($falaises) ?> falaises décrites avec accès vélo-train.">
  <title>Escalade au départ de <?= htmlspecialchars($ville['ville_nom'], ENT_QUOTES, 'UTF-8') ?> - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <?php
  // --- Données structurées JSON-LD ---
  $tableau_url = VG_BASE . '/tableau.php?ville_id=' . $ville_id;
  $ville_nom = $ville['ville_nom'] ?? '';

  $list_items = [];
  $pos = 0;
  foreach (array_values($falaises) as $rows) {
    $first = $rows[0] ?? null;
    if (!$first) {
      continue;
    }
    $pos++;
    $list_items[] = [
      '@type'    => 'ListItem',
      'position' => $pos,
      'name'     => $first['falaise_nom'],
      'url'      => VG_BASE . '/falaise.php?falaise_id=' . $first['falaise_id'] . '&ville_id=' . $ville_id,
    ];
  }

  $collection = [
    '@type'       => 'CollectionPage',
    'name'        => 'Falaises accessibles à vélo + train au départ de ' . $ville_nom,
    'description' => 'Sorties escalade au départ de ' . $ville_nom . '. ' . count($falaises) . ' falaises décrites avec accès vélo-train.',
    'url'         => $tableau_url,
    'isPartOf'    => ['@id' => VG_BASE . '/#website'],
    'mainEntity'  => [
      '@type'           => 'ItemList',
      'numberOfItems'   => count($list_items),
      'itemListElement' => $list_items,
    ],
  ];

  $breadcrumb = vg_breadcrumb([
    ['name' => 'Accueil', 'url' => '/'],
    ['name' => $ville_nom, 'url' => '/tableau.php?ville_id=' . $ville_id],
  ]);

  vg_jsonld(vg_organization(), $collection, $breadcrumb);
  ?>
</head>

<body class="min-h-screen">
  <?php include "./components/header.html"; ?>
  <div class="hero min-h-100 bg-center" style="background-image: url(/images/mw/005-train-2-10.webp);">
    <div class="hero-overlay bg-slate-600/70"></div>
    <div class="hero-content text-center text-base-100">
      <div class="max-w-md">
        <h1 class="text-5xl font-bold"> Falaises proches de <?= htmlspecialchars($ville['ville_nom'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
      </div>
    </div>
  </div>
  <main class="md:w-4/5 max-w-(--breakpoint-xl) mx-auto p-4 flex flex-col gap-4">
    <div class="flex flex-col justify-center gap-1 items-end w-full">
      <div class="flex justify-between w-full items-center">
        <button class="btn btn-xs w-fit" onclick="instructionsDialog.showModal()">
          <svg class="w-4 h-4 fill-none stroke-current">
            <use href="#information"></use>
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
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>
<!-- Initialize Vue data -->
<script>
  window.__TABLEAU_DATA__ = {
    falaises: <?php echo json_encode(array_values($falaises)); ?>,
    villeId: <?= (int) $ville_id ?>
  };
</script>
<!-- Vue.js Tableau App -->
<script type="module" src="/dist/tableau.js"></script>

</html>