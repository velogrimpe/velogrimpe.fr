<?php
// Endpoint privé : exige `Authorization: Bearer <admin_token>`.
// Absence d'en-tête => 401 ; en-tête présent mais token invalide => 403.

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Vary: Origin');

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
  header('Access-Control-Allow-Headers: Authorization, Content-Type');
  http_response_code(200);
  exit;
}

header('Content-Type: application/json');

// Authentification avant tout le reste : sans token, l'endpoint ne révèle même
// pas quelles méthodes il accepte.
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if (!$authHeader) {
  header('WWW-Authenticate: Bearer realm="velogrimpe"');
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}
// hash_equals : comparaison à temps constant, et évite le piège du `==` lâche.
if (!hash_equals('Bearer ' . $config['vg_token'], $authHeader)) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$falaises = $mysqli->query("SELECT falaise_id, falaise_nom, date_creation FROM falaises")->fetch_all(MYSQLI_ASSOC);

echo json_encode($falaises);