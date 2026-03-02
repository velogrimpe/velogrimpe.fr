<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$result = $mysqli->query("SELECT
    emport_id,
    type_train,
    compagnie_region,
    regle_demonte,
    regle_nondemonte,
    source1,
    source2
  FROM cartotrain_emport
  ORDER BY
    FIELD(type_train, 'GRANDE VITESSE', 'INTERCITÉS') DESC,
    type_train ASC,
    compagnie_region ASC");

if (!$result) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur requête: " . $mysqli->error]));
}

$rows = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['success' => true, 'data' => $rows]);
