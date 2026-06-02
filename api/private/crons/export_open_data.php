<?php
// Emit the shortest float representation that round-trips (e.g. 3.8, not
// 3.79999999999998) so coordinates and distances stay clean in the GeoJSON,
// regardless of the server's default serialize_precision.
ini_set('serialize_precision', '-1');

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$vg_token = $config["vg_token"];
// Check that Authorization header is and equal to config["admin_token"]

// Allow CORS from all origins
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: authorization, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}
// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

// Get Authorization header
$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;

// Replace this with your actual token
$validToken = 'Bearer ' . $vg_token;

if (!$authHeader || $authHeader !== $validToken) {
  http_response_code(401);
  echo json_encode([
    'error' => 'Unauthorized',
  ]);
  exit;
}

// add a pageview
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/pv.php';
sendEvent($_SERVER['REQUEST_URI'], "vg", "vg-crons", 'event: export-open-data');

// Cron logic
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Falaises export to geojson
$falaises = $mysqli->query("SELECT
  falaise_id as id,
  falaise_nom as nom,
  falaise_nomformate as nomformate,
  falaise_latlng as latlng,
  falaise_zonename as zone,
  falaise_deptcode as dept,
  falaise_deptname as deptname,
  falaise_exposhort1 as exposhort1,
  falaise_exposhort2 as exposhort2,
  falaise_cotmin as cotmin,
  falaise_cotmax as cotmax,
  falaise_maa as maa,
  falaise_mar as mar,
  falaise_topo as topo,
  falaise_expotxt as expotxt,
  falaise_matxt as matxt,
  falaise_voies as voies,
  falaise_gvtxt as gvtxt,
  falaise_gvnb as gvnb,
  falaise_rq as rq,
  falaise_fermee as fermee,
  falaise_bloc as bloc,
  falaise_nbvoies as nbvoies
  from falaises")->fetch_all(MYSQLI_ASSOC);

// Pre-load all bike itineraries + their departure station, grouped by falaise.
// Reuse the velo/gares join from fetch_sortie_velos.php but without any public
// filter and for every crag (open data exports everything).
$velosByFalaise = [];
$veloResult = $mysqli->query("SELECT
  v.falaise_id,
  v.velo_km,
  v.velo_dplus,
  v.velo_dmoins,
  v.velo_descr,
  v.velo_openrunner,
  v.velo_variante,
  v.velo_apieduniquement,
  v.velo_apiedpossible,
  g.gare_nom,
  g.gare_commune,
  g.gare_departement,
  g.gare_latlng,
  g.gare_codeuic,
  g.gare_tgv
  FROM velo v
  LEFT JOIN gares g ON v.gare_id = g.gare_id
  ORDER BY v.falaise_id, v.velo_km ASC")->fetch_all(MYSQLI_ASSOC);
foreach ($veloResult as $velo) {
  $itineraire = [
    'distance_km' => $velo['velo_km'] !== null ? round((float) $velo['velo_km'], 1) : null,
    'denivele_positif' => $velo['velo_dplus'] !== null ? (int) $velo['velo_dplus'] : null,
    'denivele_negatif' => $velo['velo_dmoins'] !== null ? (int) $velo['velo_dmoins'] : null,
    'a_pied_uniquement' => (bool) $velo['velo_apieduniquement'],
    'a_pied_possible' => (bool) $velo['velo_apiedpossible'],
    'variante' => $velo['velo_variante'],
    'description' => $velo['velo_descr'],
    'openrunner' => $velo['velo_openrunner'] ?: null,
  ];
  if (!empty($velo['gare_nom'])) {
    $gare = [
      'nom' => $velo['gare_nom'],
      'commune' => $velo['gare_commune'],
      'departement' => $velo['gare_departement'],
      'code_uic' => $velo['gare_codeuic'] ?: null,
      'tgv' => (bool) $velo['gare_tgv'],
    ];
    if (!empty($velo['gare_latlng']) && strpos($velo['gare_latlng'], ',') !== false) {
      [$glat, $glng] = explode(',', $velo['gare_latlng']);
      $gare['coordonnees'] = [round((float) $glng, 6), round((float) $glat, 6)];
    }
    $itineraire['gare_depart'] = $gare;
  }
  $velosByFalaise[$velo['falaise_id']][] = $itineraire;
}

// Pre-load external partner links (oblyk, etc.), grouped by falaise.
$liensByFalaise = [];
$liensResult = $mysqli->query("SELECT falaise_id, site, site_url, site_name FROM falaises_liens")->fetch_all(MYSQLI_ASSOC);
foreach ($liensResult as $lien) {
  $liensByFalaise[$lien['falaise_id']][] = [
    'site' => $lien['site'],
    'url' => $lien['site_url'],
    'nom' => $lien['site_name'] ?: null,
  ];
}

$geojson = [
  'type' => 'FeatureCollection',
  'license' => 'CC BY-SA 4.0 et ODbL 1.0',
  'license_note' => 'Le contenu éditorial (descriptions, remarques) est sous CC BY-SA 4.0 ; la base de données (faits : coordonnées, distances, dénivelés, gares) est sous ODbL 1.0. Attribution : © velogrimpe.fr et contributeurs.',
  'license_url_cc' => 'https://creativecommons.org/licenses/by-sa/4.0/',
  'license_url_odbl' => 'https://opendatacommons.org/licenses/odbl/1-0/',
  'attribution' => '© velogrimpe.fr et contributeurs',
  'features' => [],
];

// Merged collection of every crag's geometric topo details (sectors, parkings,
// approaches, bike access…), each feature tagged with its falaise_id.
$detailsGeojson = [
  'type' => 'FeatureCollection',
  'license' => 'CC BY-SA 4.0 et ODbL 1.0',
  'license_note' => 'Le contenu éditorial (descriptions, remarques) est sous CC BY-SA 4.0 ; la base de données (faits : coordonnées, distances, dénivelés, gares) est sous ODbL 1.0. Attribution : © velogrimpe.fr et contributeurs.',
  'license_url_cc' => 'https://creativecommons.org/licenses/by-sa/4.0/',
  'license_url_odbl' => 'https://opendatacommons.org/licenses/odbl/1-0/',
  'attribution' => '© velogrimpe.fr et contributeurs',
  'features' => [],
];
foreach ($falaises as $falaise) {
  $lat = explode(',', $falaise['latlng'])[0];
  $lng = explode(',', $falaise['latlng'])[1];
  $properties = [
    'id' => (int) $falaise['id'],
    'nom' => $falaise['nom'],
    'url' => 'https://velogrimpe.fr/falaise.php?falaise_id=' . (int) $falaise['id'],
    'zone' => $falaise['zone'],
    'departement_code' => $falaise['dept'],
    'departement_nom' => $falaise['deptname'],
    'exposition' => $falaise['exposhort1'],
    'exposition_secondaire' => $falaise['exposhort2'],
    'cotation_min' => $falaise['cotmin'],
    'cotation_max' => $falaise['cotmax'],
    'code_nbvoies' => (int) $falaise['nbvoies'],
    'bloc_type' => (int) $falaise['bloc'],
    'nb_grandes_voies' => $falaise['gvnb'],
    'topo' => $falaise['topo'] !== '' ? $falaise['topo'] : null,
    'fermee' => $falaise['fermee'] !== '',
    'approche_aller' => $falaise['maa'],
    'approche_retour' => $falaise['mar'],
    'description_exposition' => $falaise['expotxt'],
    'description_approche' => $falaise['matxt'],
    'description_voies' => $falaise['voies'],
    'description_grandes_voies' => $falaise['gvtxt'],
    'remarques' => $falaise['rq'],
    'itineraires_velo' => $velosByFalaise[$falaise['id']] ?? [],
    'liens_externes' => $liensByFalaise[$falaise['id']] ?? [],
  ];

  // Link to the geometric topo details file only when it exists, and merge its
  // features into the global details collection (tagging each with falaise_id).
  $details_file = $_SERVER['DOCUMENT_ROOT'] . '/bdd/barres/' . $falaise['id'] . '_' . $falaise['nomformate'] . '.geojson';
  if (file_exists($details_file)) {
    $properties['details_url'] = 'https://velogrimpe.fr/bdd/barres/' . $falaise['id'] . '_' . $falaise['nomformate'] . '.geojson';

    $details_content = json_decode(file_get_contents($details_file), true);
    if (is_array($details_content) && !empty($details_content['features']) && is_array($details_content['features'])) {
      foreach ($details_content['features'] as $detail_feature) {
        if (!is_array($detail_feature)) {
          continue;
        }
        $detail_props = $detail_feature['properties'] ?? [];
        // "falaise_voisine" features carry their own falaise_id pointing to the
        // neighbouring crag; preserve it under a distinct key so the injected
        // parent falaise_id stays authoritative for traceability.
        if (isset($detail_props['falaise_id'])) {
          $detail_props['voisine_falaise_id'] = $detail_props['falaise_id'];
        }
        // Prepend the parent falaise_id / falaise_nom (authoritative).
        $detail_feature['properties'] = array_merge(
          [
            'falaise_id' => (int) $falaise['id'],
            'falaise_nom' => $falaise['nom'],
          ],
          $detail_props,
          ['falaise_id' => (int) $falaise['id']]
        );
        $detailsGeojson['features'][] = $detail_feature;
      }
    }
  }

  $feature = [
    'type' => 'Feature',
    'properties' => $properties,
    'geometry' => [
      'type' => 'Point',
      // explode and cast to float rounded to 6 decimals and reverse order for geojson [lng, lat]
      'coordinates' => [
        round((float) $lng, 6),
        round((float) $lat, 6),
      ],
    ],
  ];
  $geojson['features'][] = $feature;
}
// Save to file
$file = $_SERVER['DOCUMENT_ROOT'] . '/open-data/falaises.geojson';
file_put_contents($file, json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Save the merged details collection
$details_export = $_SERVER['DOCUMENT_ROOT'] . '/open-data/falaises-details.geojson';
file_put_contents($details_export, json_encode($detailsGeojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Respond with success
http_response_code(200);
echo json_encode([
  'status' => 'success',
  'message' => 'Open data exported successfully',
]);