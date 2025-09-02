<?php
// Check that Authorization header is and equal to config["admin_token"]

// Allow CORS from all origins
// header('Access-Control-Allow-Origin: https://velogrimpe.fr, https://www.velogrimpe.fr, https://couble.eu, http://localhost:3100');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$headers = getallheaders();
$http_origin = $headers['Origin'] ?? $headers['origin'] ?? null;

$allowed_http_origins = [
  "https://velogrimpe.fr",
  "https://www.velogrimpe.fr",
  "https://couble.eu",
  "http://localhost:3100",
];
if (in_array($http_origin, $allowed_http_origins)) {
  header("Access-Control-Allow-Origin: " . $http_origin);
}

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

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
// Prepare the SQL statement

$falaises = $mysqli->query("SELECT falaise_id, falaise_nom FROM falaises")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // Return the result as JSON
  echo json_encode($falaises);
}