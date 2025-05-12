<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$oblyk_token = $config["oblyk_token"];
// Check that Authorization header is and equal to config["admin_token"]

// Allow CORS from all origins
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: authorization, Authorization');

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

// Get Authorization header
$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;

// Replace this with your actual token
$validToken = 'Bearer ' . $oblyk_token;

if (!$authHeader || $authHeader !== $validToken) {
  http_response_code(401);
  echo json_encode([
    'error' => 'Unauthorized',
  ]);
  exit;
}

// add a pageview
require_once '../../lib/pv.php';
sendEvent($_SERVER['REQUEST_URI'], "oblyk");

$oblyk_id = trim($_GET['oblyk_id'] ?? '');
if (empty($oblyk_id)) {
  die("oblyk_id is required");
}
header('Content-Type: application/json');

require_once "../../database/velogrimpe.php";
// Prepare the SQL statement
$stmt = $mysqli->prepare("SELECT
f.falaise_id, falaise_nom
FROM falaises_liens
LEFT JOIN falaises f ON falaises_liens.falaise_id = f.falaise_id
WHERE site = 'oblyk' and site_id = ?"
);
if (!$stmt) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
// Bind the parameter
$stmt->bind_param("s", $oblyk_id);
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

$falaises = [];
// echo "coucou";
while ($row = $result->fetch_assoc()) {
  $falaises[] = [
    'id' => $row['falaise_id'],
    'name' => $row['falaise_nom'],
  ];
}
// Close the statement
$stmt->close();
// Return the result as JSON
echo json_encode($falaises);
// Close the database connection
$mysqli->close();
