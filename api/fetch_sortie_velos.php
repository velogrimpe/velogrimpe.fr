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

// Query to get bike routes for this crag
$query = "SELECT
    v.velo_id,
    v.gare_id,
    v.velo_depart,
    v.velo_arrivee,
    v.velo_km,
    v.velo_dplus,
    v.velo_dmoins,
    v.velo_descr,
    v.velo_openrunner,
    v.velo_variante,
    v.velo_apieduniquement,
    v.velo_apiedpossible,
    g.gare_nom,
    g.gare_nomformate,
    g.gare_tgv
  FROM velo v
  LEFT JOIN gares g ON v.gare_id = g.gare_id
  WHERE v.falaise_id = ?
    AND v.velo_public = 1
    AND (g.deleted IS NULL OR g.deleted = 0)
  ORDER BY v.velo_km ASC";

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
