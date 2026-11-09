<?php
header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

// Get falaise_id parameter
$falaise_id = isset($_GET['falaise_id']) && !empty($_GET['falaise_id']) ? intval($_GET['falaise_id']) : null;

if ($falaise_id === null) {
  http_response_code(400);
  die(json_encode(["error" => "Paramètre falaise_id manquant"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';

// Query to get bike routes for this crag
$query = "SELECT
    v.acces_id AS velo_id,
    v.arret_id,
    v.depart_slug AS velo_depart,
    v.arrivee_slug AS velo_arrivee,
    v.km AS velo_km,
    v.dplus AS velo_dplus,
    v.dmoins AS velo_dmoins,
    v.description AS velo_descr,
    v.lien_openrunner AS velo_openrunner,
    v.variante AS velo_variante,
    " . acces_a_pied_sql('v') . " AS velo_apiedpossible,
    a.nom AS arret_nom,
    a.slug AS arret_nomformate
  FROM acces_falaise v
  LEFT JOIN arrets a ON v.arret_id = a.id
  WHERE v.falaise_id = ?
    AND v.type = 'velo'
    AND v.public = 1
    AND (a.deleted IS NULL OR a.deleted = 0)
  ORDER BY v.km ASC";

$stmt = $mysqli->prepare($query);

if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur de préparation de la requête : " . $mysqli->error]));
}

$stmt->bind_param("i", $falaise_id);

if (!$stmt->execute()) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'exécution : " . $stmt->error]));
}

$result = $stmt->get_result();
$velos = [];

while ($row = $result->fetch_assoc()) {
  $velos[] = $row;
}

$stmt->close();

// Return results
echo json_encode([
  'success' => true,
  'velos' => $velos,
  'count' => count($velos)
]);
