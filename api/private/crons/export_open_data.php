<?php
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
  falaise_cottxt as cottxt,
  falaise_voies as voies,
  falaise_gvtxt as gvtxt,
  falaise_gvnb as gvnb,
  falaise_rq as rq,
  falaise_fermee as fermee,
  falaise_bloc as bloc,
  falaise_nbvoies as nbvoies
  from falaises")->fetch_all(MYSQLI_ASSOC);
$geojson = [
  'type' => 'FeatureCollection',
  'license' => 'CC BY-NC-SA 4.0',
  'license_url' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
  'features' => [],
];
foreach ($falaises as $falaise) {
  $lat = explode(',', $falaise['latlng'])[0];
  $lng = explode(',', $falaise['latlng'])[1];
  $feature = [
    'type' => 'Feature',
    'properties' => [
      'id' => (int) $falaise['id'],
      'nom' => $falaise['nom'],
      'zone' => $falaise['zone'],
      'exposition' => $falaise['exposhort1'],
      'cotation_min' => $falaise['cotmin'],
      'cotation_max' => $falaise['cotmax'],
      'approche_aller' => $falaise['maa'],
      'approche_retour' => $falaise['mar'],
      'topo' => (bool) $falaise['topo'],
      'nb_grandes_voies' => $falaise['gvnb'],
      'fermee' => (bool) $falaise['fermee'],
      'bloc_type' => (bool) $falaise['bloc'],
      'code_nbvoies' => (int) $falaise['nbvoies'],
      'departement_code' => $falaise['dept'],
      'departement_nom' => $falaise['deptname'],
      'description_exposition' => $falaise['expotxt'],
      'description_approche' => $falaise['matxt'],
      'description_cotation' => $falaise['cottxt'],
      'description_voies' => $falaise['voies'],
      'description_grandes_voies' => $falaise['gvtxt'],
      'remarques' => $falaise['rq'],
    ],
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

// Respond with success
http_response_code(200);
echo json_encode([
  'status' => 'success',
  'message' => 'Open data exported successfully',
]);