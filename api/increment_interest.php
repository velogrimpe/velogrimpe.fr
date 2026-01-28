<?php
/**
 * API endpoint to increment the nb_interesses counter for a sortie
 * Called when a user clicks on "Rejoindre le groupe"
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$sortie_id = !empty($_POST['sortie_id']) ? intval($_POST['sortie_id']) : null;

if ($sortie_id === null) {
  http_response_code(400);
  die(json_encode(["error" => "sortie_id manquant"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Check if sortie exists and get current count
$stmt = $mysqli->prepare("SELECT sortie_id, nb_interesses FROM sorties WHERE sortie_id = ?");
$stmt->bind_param("i", $sortie_id);
$stmt->execute();
$result = $stmt->get_result();
$sortie = $result->fetch_assoc();
$stmt->close();

if (!$sortie) {
  http_response_code(404);
  die(json_encode(["error" => "Sortie non trouvée"]));
}

// Increment the counter
$stmt = $mysqli->prepare("UPDATE sorties SET nb_interesses = nb_interesses + 1 WHERE sortie_id = ?");
$stmt->bind_param("i", $sortie_id);
$stmt->execute();
$stmt->close();

$new_count = $sortie['nb_interesses'] + 1;

echo json_encode([
  'success' => true,
  'nb_interesses' => $new_count
]);
