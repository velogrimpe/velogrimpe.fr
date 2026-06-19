<?php
// GET /api/private/backfill_altitudes.php?admin=...[&limit=N]
// Récupère les falaises sans altitude (falaise_altitude IS NULL), interroge l'API
// altimétrie IGN pour chacune, met à jour la colonne falaise_altitude et journalise
// la modification (edit_logs). On limite ainsi les appels aux seules falaises non
// encore renseignées ; un point hors couverture reste NULL et sera retenté au
// prochain run.

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

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/altitude_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';

// Limite optionnelle du nombre de falaises traitées par run (0 = toutes).
$limit = isset($_GET['limit']) ? max(0, (int) $_GET['limit']) : 0;

$sql = "SELECT falaise_id, falaise_latlng FROM falaises WHERE falaise_altitude IS NULL";
if ($limit > 0) {
  $sql .= " LIMIT " . $limit;
}
$res = $mysqli->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(['error' => 'Query failed', 'details' => $mysqli->error]);
  exit;
}

$updateStmt = $mysqli->prepare("UPDATE falaises SET falaise_altitude = ? WHERE falaise_id = ?");
if (!$updateStmt) {
  http_response_code(500);
  echo json_encode(['error' => 'Prepare failed', 'details' => $mysqli->error]);
  exit;
}

$traites = 0;
$renseignes = 0;
$echecs = [];

$contactMail = $config['contact_mail'] ?? 'contact@velogrimpe.fr';

while ($row = $res->fetch_assoc()) {
  $traites++;
  $falaise_id = (int) $row['falaise_id'];
  $parts = array_map('trim', explode(',', (string) $row['falaise_latlng']));

  if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
    $echecs[] = ['falaise_id' => $falaise_id, 'raison' => 'coordonnées invalides'];
    continue;
  }

  $alt = fetch_ign_altitude((float) $parts[0], (float) $parts[1]);
  if ($alt === null) {
    // API indisponible / hors couverture : on laisse NULL, retenté plus tard.
    $echecs[] = ['falaise_id' => $falaise_id, 'raison' => 'altitude indisponible'];
    continue;
  }

  $updateStmt->bind_param('ii', $alt, $falaise_id);
  if (!$updateStmt->execute()) {
    $echecs[] = ['falaise_id' => $falaise_id, 'raison' => 'update échoué: ' . $updateStmt->error];
    continue;
  }

  // Un edit log par falaise pour notifier la modification.
  logChanges("admin", $contactMail, "update", "falaise", $falaise_id, $falaise_id, ["falaise_altitude" => $alt], []);
  $renseignes++;

  // Politesse envers l'API IGN (100 ms entre deux appels).
  usleep(100000);
}

http_response_code(200);
echo json_encode([
  'success' => true,
  'traites' => $traites,
  'renseignes' => $renseignes,
  'echecs' => $echecs,
], JSON_UNESCAPED_UNICODE);
