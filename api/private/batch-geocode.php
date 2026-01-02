<?php
// GET /api/private/batch-geocode.php?admin=...
// Retrieves falaises from DB, geocodes their zone and department from falaise_latlng,
// then updates columns: falaise_zonename, falaise_deptcode, falaise_deptname

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/geocode_lib.php';

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$adminToken = $config['admin_token'] ?? null;
$adminParam = $_GET['admin'] ?? null;
if (!$adminToken || $adminParam !== $adminToken) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed', 'details' => $mysqli->connect_error]);
  exit;
}

$mysqli->set_charset('utf8mb4');

// Ensure target columns exist (MariaDB/MySQL 10.3+ supports IF NOT EXISTS)
$alterSqls = [
  "ALTER TABLE falaises ADD COLUMN IF NOT EXISTS falaise_zonename VARCHAR(255) NULL",
  "ALTER TABLE falaises ADD COLUMN IF NOT EXISTS falaise_deptcode VARCHAR(10) NULL",
  "ALTER TABLE falaises ADD COLUMN IF NOT EXISTS falaise_deptname VARCHAR(255) NULL"
];
foreach ($alterSqls as $sql) {
  $mysqli->query($sql);
}

// Paths to GeoJSON files
$zonesPath = $_SERVER['DOCUMENT_ROOT'] . '/bdd/zones/zones.geojson';
$deptsPath = $_SERVER['DOCUMENT_ROOT'] . '/bdd/zones/departements.geojson';

$zones = geojson_load($zonesPath);
$depts = geojson_load($deptsPath);
if (!$zones || !$depts) {
  http_response_code(500);
  echo json_encode(['error' => 'GeoJSON load failed', 'zones' => !!$zones, 'depts' => !!$depts]);
  exit;
}

// Fetch falaises to process
$sql = "SELECT falaise_id, falaise_nom, falaise_latlng FROM falaises WHERE falaise_latlng IS NOT NULL AND falaise_latlng <> ''";
$res = $mysqli->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(['error' => 'Query failed', 'details' => $mysqli->error]);
  exit;
}

$updateStmt = $mysqli->prepare("UPDATE falaises SET falaise_zonename = ?, falaise_deptcode = ?, falaise_deptname = ? WHERE falaise_id = ?");
if (!$updateStmt) {
  http_response_code(500);
  echo json_encode(['error' => 'Prepare update failed', 'details' => $mysqli->error]);
  exit;
}

$processed = 0;
$updated = 0;
$skipped = [];
$nozone = [];
$errors = [];

while ($row = $res->fetch_assoc()) {
  $processed++;
  $latlngStr = trim($row['falaise_latlng']);
  $parts = array_map('trim', explode(',', $latlngStr));
  if (count($parts) !== 2) {
    $skipped[] = ['falaise_id' => $row['falaise_id'], 'falaise_nom' => $row['falaise_nom']];
    $errors[] = ['falaise_id' => $row['falaise_id'], 'error' => 'Invalid latlng format'];
    continue;
  }
  $lat = floatval($parts[0]);
  $lng = floatval($parts[1]);
  if (!is_finite($lat) || !is_finite($lng)) {
    $skipped[] = ['falaise_id' => $row['falaise_id'], 'falaise_nom' => $row['falaise_nom']];
    $errors[] = ['falaise_id' => $row['falaise_id'], 'error' => 'Non-finite lat/lng'];
    continue;
  }

  $zoneLabel = null;
  $deptCode = null;
  $deptName = null;

  $zFeat = geo_find_containing_feature($zones, $lng, $lat);
  if ($zFeat) {
    $zoneLabel = geo_extract_zone_label($zFeat);
  } else {
    $nozone[] = ['falaise_id' => $row['falaise_id'], 'falaise_nom' => $row['falaise_nom']];
  }

  $dFeat = geo_find_containing_feature($depts, $lng, $lat);
  if ($dFeat) {
    $dept = geo_extract_dept_info($dFeat);
    $deptCode = $dept['code'];
    $deptName = $dept['name'];
  }

  if ($zoneLabel === null && $deptCode === null && $deptName === null) {
    $skipped[] = ['falaise_id' => $row['falaise_id'], 'falaise_nom' => $row['falaise_nom']];
    continue;
  }

  $falaise_id = (int) $row['falaise_id'];
  $updateStmt->bind_param('sssi', $zoneLabel, $deptCode, $deptName, $falaise_id);
  if ($updateStmt->execute()) {
    $updated++;
  } else {
    $errors[] = ['falaise_id' => $falaise_id, 'error' => $updateStmt->error];
  }
}

$updateStmt->close();

echo json_encode([
  'processed' => $processed,
  'updated' => $updated,
  'skipped' => $skipped,
  'nozone' => $nozone,
  'errors' => $errors,
]);

?>