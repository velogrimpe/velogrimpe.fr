<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Get Authorization header
$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if (!$authHeader) {
  die("Authorization header not found");
}
if ($authHeader !== 'Bearer ' . $config["admin_token"]) {
  die("Invalid token");
}
// get attributes falaise_id, site_url, site_id, site, site_name from the POST request body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die("Method not allowed");
}
$input = json_decode(file_get_contents('php://input'), true);


$ville_id = trim($input['ville_id'] ?? '');
$gare_id = trim($input['gare_id'] ?? '');
$falaise_id = trim($input['falaise_id'] ?? '');
if (empty($ville_id) || empty($gare_id) || empty($falaise_id)) {
  die("Ville ID and Gare ID and Falaise ID are required.");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$stmt = $mysqli->prepare("INSERT INTO exclusions_villes_gares_falaises (ville_id, gare_id, falaise_id) VALUES (?, ?, ?)");
if (!$stmt) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}

$stmt->bind_param("iii", $ville_id, $gare_id, $falaise_id);
if (!$stmt) {
  die("Problème de liaison des paramètres : " . $mysqli->error);
}
// Execute the statement
$stmt->execute();
if ($stmt->error) {
  die("Erreur lors de l'exécution de la requête : " . $stmt->error);
}
// Check if the insert was successful
if ($stmt->affected_rows === 0) {
  die("Aucune ligne insérée.");
}
$stmt->close();

echo json_encode(true);
