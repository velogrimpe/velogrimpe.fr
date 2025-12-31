<?php
// GET /api/geocode.php?lat=...&lng=...
// Returns JSON: { "zone": string|null, "dept": string|null }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/geocode_lib.php';

// Simple input parsing and validation
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
  echo json_encode(['zone' => null, 'dept' => null]);
  exit;
}

// Paths to GeoJSON files (relative to public_html)
$zonesPath = $_SERVER['DOCUMENT_ROOT'] . '/bdd/zones/zones.geojson';
$deptsPath = $_SERVER['DOCUMENT_ROOT'] . '/bdd/zones/departements.geojson';

$zones = geojson_load($zonesPath);
$depts = geojson_load($deptsPath);

$zoneLabel = null;
$deptLabel = null;
$deptCode = null;

if ($zones) {
  $zFeat = geo_find_containing_feature($zones, $lng, $lat);
  if ($zFeat)
    $zoneLabel = geo_extract_zone_label($zFeat);
}

if ($depts) {
  $dFeat = geo_find_containing_feature($depts, $lng, $lat);
  if ($dFeat) {
    $dept = geo_extract_dept_info($dFeat);
    $deptLabel = $dept['name'];
    $deptCode = $dept['code'];
  }
}

echo json_encode([
  'zone' => $zoneLabel,
  'dept_name' => $deptLabel,
  'dept_code' => $deptCode
]);
