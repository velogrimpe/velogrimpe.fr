<?php
// Check that Authorization header is and equal to config["admin_token"]

// Allow CORS from all origins
header('Access-Control-Allow-Origin: localhost:4002, https://velogrimpe.fr, https://www.velogrimpe.fr');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}
// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

$falaise_id = trim($_GET['falaise_id'] ?? '');
if (empty($falaise_id)) {
  die("falaise_id is required");
}
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
// Prepare the SQL statement
$stmt = $mysqli->prepare("SELECT
falaise_id, falaise_nomformate
FROM falaises
WHERE falaise_id = ?"
);
if (!$stmt) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
// Bind the parameter
$stmt->bind_param("s", $falaise_id);
if (!$stmt) {
  die("Problème de liaison des paramètres : " . $mysqli->error);
}
// Execute the statement
$stmt->execute();
if ($stmt->error) {
  die("Erreur lors de l'exécution de la requête : " . $stmt->error);
}
// Get the result
$result = $stmt->get_result();
if ($stmt->error) {
  die("Erreur lors de la récupération du résultat : " . $stmt->error);
}
// Fetch the results
$falaise = $result->fetch_assoc();
if (!$falaise) {
  http_response_code(404);
  echo json_encode(['error' => 'Falaise not found']);
  exit;
}
// Close the statement
$stmt->close();
// Close the database connection
$mysqli->close();

// Check existance of falaise details geojson file and load it if exists
$geojson_file = $_SERVER['DOCUMENT_ROOT'] . "/bdd/barres/" . $falaise["falaise_id"] . "_" . $falaise["falaise_nomformate"] . ".geojson";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

  if (file_exists($geojson_file)) {
    $geojson_content = file_get_contents($geojson_file);
    $geojson = json_decode($geojson_content, true);
  } else {
    $geojson = ["type" => "FeatureCollection", "features" => []];
  }

  // Return the result as JSON
  echo json_encode($geojson);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Get Authorization header
  $headers = getallheaders();
  $authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;

  $config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
  if ($authHeader !== "Bearer " . $config['contrib_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
  }
  // Handle POST request to update falaise details
  $data = json_decode(file_get_contents('php://input'), true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
  }

  // Create backup of existing file before saving
  if (file_exists($geojson_file)) {
    $backup_dir = $_SERVER['DOCUMENT_ROOT'] . "/bdd/barres-historique";
    if (!is_dir($backup_dir)) {
      mkdir($backup_dir, 0755, true);
    }
    $date_suffix = date('Y-m-d-H\Hi');
    $base_name = $falaise["falaise_id"] . "_" . $falaise["falaise_nomformate"];
    $backup_file = $backup_dir . "/" . $base_name . "-" . $date_suffix . ".geojson";
    copy($geojson_file, $backup_file);
  }

  // Save the updated geojson content
  if (file_put_contents($geojson_file, json_encode($data, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => 'Falaise details updated successfully']);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save falaise details']);
  }
}
