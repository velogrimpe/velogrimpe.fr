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

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$result = $mysqli->query("SELECT DISTINCT falaise_zonename FROM falaises WHERE falaise_zonename IS NOT NULL AND falaise_zonename != '' ORDER BY falaise_zonename");
$zones = [];
while ($row = $result->fetch_assoc()) {
  $zones[] = $row['falaise_zonename'];
}

echo json_encode($zones);
