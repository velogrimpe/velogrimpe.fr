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
    CASE type_train
      WHEN 'GRANDE VITESSE' THEN 1
      WHEN 'INTERCITÉS' THEN 2
      ELSE 3
    END,
    CASE
      WHEN type_train = 'GRANDE VITESSE' AND compagnie_region LIKE '%TGV Inoui%' THEN 1
      WHEN type_train = 'GRANDE VITESSE' AND compagnie_region LIKE '%Ouigo%' THEN 2
      WHEN type_train = 'GRANDE VITESSE' AND compagnie_region LIKE '%TGV Lyria%' THEN 3
      WHEN type_train = 'GRANDE VITESSE' AND compagnie_region LIKE '%Renfe%' THEN 4
      WHEN type_train = 'GRANDE VITESSE' AND compagnie_region LIKE '%Trenitalia%' THEN 5
      WHEN type_train = 'GRANDE VITESSE' AND compagnie_region LIKE '%DB%' THEN 6
      WHEN type_train = 'INTERCITÉS' AND compagnie_region LIKE '%Ouigo%' THEN 99
      WHEN type_train NOT IN ('GRANDE VITESSE','INTERCITÉS') AND compagnie_region LIKE '%Léman%' THEN 99
      ELSE 50
    END,
    compagnie_region ASC");

if (!$result) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur requête: " . $mysqli->error]));
}

$rows = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['success' => true, 'data' => $rows]);
