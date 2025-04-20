<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
// Check that Authorization header is and equal to config["admin_token"]
$headers = getallheaders();
if (!array_key_exists('Authorization', $headers)) {
  die("Authorization header not found");
}
if ($headers['Authorization'] !== $config["admin_token"]) {
  die("Invalid token");
}
// get attributes falaise_id, site_url, site_id, site, site_name from the POST request body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  die("Method not allowed");
}
$input = json_decode(file_get_contents('php://input'), true);


$falaise_id = trim($input['falaise_id'] ?? '');
$site_url = trim($input['site_url'] ?? '');
$site_id = trim($input['site_id'] ?? '');
$site = trim($input['site'] ?? '');
$site_name = trim($input['site_name'] ?? '');

require_once "../database/velogrimpe.php";

$stmt = $mysqli->prepare("INSERT INTO falaises_liens (falaise_id, site_id, site_url, site, site_name) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}

$stmt->bind_param("issss", $falaise_id, $site_id, $site_url, $site, $site_name);
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
