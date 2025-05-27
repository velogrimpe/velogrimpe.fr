<?php
// Check that Authorization header is and equal to config["admin_token"]

// Allow CORS from all origins
header('Access-Control-Allow-Origin: localhost:4000, https://velogrimpe.fr, https://www.velogrimpe.fr');
header('Access-Control-Allow-Methods: GET, OPTIONS');

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

$falaise_id = trim($_GET['falaise_id'] ?? '');
if (empty($falaise_id)) {
  die("falaise_id is required");
}
header('Content-Type: application/json');

require_once "../../database/velogrimpe.php";
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

// Check existance of falaise details geojson file and load it if exists
$geojson_file = $_SERVER['DOCUMENT_ROOT'] . "/bdd/barres/" . $falaise["falaise_id"] . "_" . $falaise["falaise_nomformate"] . ".geojson";
if (file_exists($geojson_file)) {
  $geojson_content = file_get_contents($geojson_file);
  $geojson = json_decode($geojson_content, true);
} else {
  $geojson = ["type" => "FeatureCollection", "features" => []];
}


// Close the statement
$stmt->close();

// Return the result as JSON
echo json_encode($geojson);
// Close the database connection
$mysqli->close();
