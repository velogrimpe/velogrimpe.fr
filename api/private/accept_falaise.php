<?php
// GET /api/private/batch-geocode.php?admin=...
// Retrieves falaises from DB, geocodes their zone and department from falaise_latlng,
// then updates columns: falaise_zonename, falaise_deptcode, falaise_deptname

header('Content-Type: application/json; charset=utf-8');

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$adminToken = $config['admin_token'] ?? null;
$adminParam = $_GET['admin'] ?? null;
if (!$adminToken || $adminParam !== $adminToken || $_SERVER['REQUEST_METHOD'] !== 'GET') {
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

// Update falaise to set falaise_public to 1
$falaise_id = $_GET['falaise_id'] ?? null;
if (!$falaise_id) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing falaise_id parameter']);
  ;
  exit;
}

$updateStmt = $mysqli->prepare("UPDATE falaises SET falaise_public = 1 WHERE falaise_id = ?");
if (!$updateStmt) {
  http_response_code(500);
  echo json_encode(['error' => 'Prepare failed', 'details' => $mysqli->error]);
  exit;
}
$updateStmt->bind_param('i', $falaise_id);
if (!$updateStmt->execute()) {
  http_response_code(500);
  echo json_encode(['error' => 'Execute failed', 'details' => $updateStmt->error]);
  exit;
}


require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
logChanges("admin", "contact@velogrimpe.fr", "update", "falaise", $falaise_id, $falaise_id, ["falaise_public" => "Validée (1)"], []);


http_response_code(200);
echo json_encode(['success' => true, 'falaise_id' => $falaise_id]);
