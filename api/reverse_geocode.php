<?php
// GET /api/reverse_geocode.php?lat=...&lng=...
// Géocodage inverse unitaire (formulaire d'ajout d'arrêt, Lot 3, cf.
// docs/plan-implementation-arrets.md). Renvoie JSON :
//   { "commune": string|null, "code_insee": string|null, "cp": string|null }
// commune/code_insee/cp à null si aucun résultat trouvé à proximité.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/geoplateforme_lib.php';

$latRaw = $_GET['lat'] ?? null;
$lngRaw = $_GET['lng'] ?? null;

if (
  $latRaw === null || $lngRaw === null
  || !is_numeric($latRaw) || !is_numeric($lngRaw)
) {
  http_response_code(400);
  echo json_encode(['error' => 'Paramètres lat/lng invalides ou manquants']);
  exit;
}

$lat = (float) $latRaw;
$lng = (float) $lngRaw;
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
  http_response_code(400);
  echo json_encode(['error' => 'Paramètres lat/lng hors bornes']);
  exit;
}

$result = fetch_geoplateforme_reverse_geocode($lat, $lng);

echo json_encode([
  'commune' => $result['city'] ?? null,
  'code_insee' => $result['citycode'] ?? null,
  'cp' => $result['postcode'] ?? null,
]);
