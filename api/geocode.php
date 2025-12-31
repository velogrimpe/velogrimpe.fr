<?php
// GET /api/geocode.php?lat=...&lng=...
// Returns JSON: { "zone": string|null, "dept": string|null }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Simple input parsing and validation
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
  echo json_encode(['zone' => null, 'dept' => null]);
  exit;
}

// Paths to GeoJSON files (relative to public_html)
$zonesPath = __DIR__ . '/../bdd/zones/zones.geojson';
$deptsPath = __DIR__ . '/../bdd/zones/departements.geojson';

function loadGeoJson($path)
{
  if (!file_exists($path)) {
    return null;
  }
  $content = file_get_contents($path);
  if ($content === false || trim($content) === '') {
    return null;
  }
  $data = json_decode($content, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    return null;
  }
  return $data;
}

function pointInRing($lng, $lat, $ring)
{
  // Ray casting algorithm; ring is array of [lng, lat]
  $inside = false;
  $n = count($ring);
  if ($n === 0)
    return false;
  // Ensure closed ring optional: not required for algorithm
  for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
    $xi = $ring[$i][0];
    $yi = $ring[$i][1];
    $xj = $ring[$j][0];
    $yj = $ring[$j][1];
    $intersect = (($yi > $lat) != ($yj > $lat)) &&
      ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
    if ($intersect)
      $inside = !$inside;
  }
  return $inside;
}

function pointInPolygon($lng, $lat, $coordinates)
{
  // coordinates: [ outerRing, holeRing1, holeRing2, ... ]
  if (!is_array($coordinates) || count($coordinates) === 0)
    return false;
  $inOuter = pointInRing($lng, $lat, $coordinates[0]);
  if (!$inOuter)
    return false;
  // If inside outer, ensure not inside any hole
  for ($k = 1; $k < count($coordinates); $k++) {
    if (pointInRing($lng, $lat, $coordinates[$k])) {
      return false;
    }
  }
  return true;
}

function featureContainsPoint($feature, $lng, $lat)
{
  if (!isset($feature['geometry']) || !isset($feature['geometry']['type']))
    return false;
  $type = $feature['geometry']['type'];
  $coords = $feature['geometry']['coordinates'] ?? null;
  if (!$coords)
    return false;

  if ($type === 'Polygon') {
    return pointInPolygon($lng, $lat, $coords);
  } elseif ($type === 'MultiPolygon') {
    foreach ($coords as $poly) {
      if (pointInPolygon($lng, $lat, $poly))
        return true;
    }
    return false;
  } else {
    return false; // Not supported geometry
  }
}

function findContainingFeature($fc, $lng, $lat)
{
  if (!$fc || !isset($fc['features']) || !is_array($fc['features']))
    return null;
  foreach ($fc['features'] as $feat) {
    if (featureContainsPoint($feat, $lng, $lat)) {
      return $feat;
    }
  }
  return null;
}

$zones = loadGeoJson($zonesPath);
$depts = loadGeoJson($deptsPath);

$zoneLabel = null;
$deptLabel = null;
$deptCode = null;

if ($zones) {
  $zFeat = findContainingFeature($zones, $lng, $lat);
  if ($zFeat)
    $zoneLabel = $zFeat['properties']['name'] ?? null;
}

if ($depts) {
  $dFeat = findContainingFeature($depts, $lng, $lat);
  if ($dFeat) {
    $deptLabel = $dFeat['properties']['nom'] ?? null;
    $deptCode = $dFeat['properties']['code'] ?? null;
  }
}

echo json_encode([
  'zone' => $zoneLabel,
  'dept_name' => $deptLabel,
  'dept_code' => $deptCode
]);
