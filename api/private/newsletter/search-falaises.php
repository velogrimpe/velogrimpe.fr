<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if (!$authHeader || $authHeader !== 'Bearer ' . $config['admin_token']) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

$q = trim($_GET['q'] ?? '');
if (empty($q)) {
  echo json_encode([]);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$search = '%' . $q . '%';
$stmt = $mysqli->prepare("SELECT DISTINCT f.falaise_id as id, f.falaise_nom as name, f.falaise_deptname as department, f.falaise_contrib as contrib FROM falaises f INNER JOIN velo v ON f.falaise_id = v.falaise_id WHERE f.falaise_nom LIKE ? ORDER BY f.falaise_nom LIMIT 20");
$stmt->bind_param('s', $search);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Parse contributor name from falaise_contrib ('Nom','email' format)
foreach ($rows as &$row) {
  $contributor = '';
  if (!empty($row['contrib'])) {
    // Extract first quoted value (the name)
    if (preg_match("/^'([^']*)',/", $row['contrib'], $m)) {
      $contributor = $m[1];
    } else {
      $contributor = $row['contrib'];
    }
  }
  $row['contributor'] = $contributor;
  unset($row['contrib']);
}
unset($row);

echo json_encode($rows);
