<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/lignes_lib.php';

$ligne_id = (int) ($_GET['ligne_id'] ?? 0);
if ($ligne_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "Un id de ligne est requis"]));
}

$stmt = $mysqli->prepare(LIGNE_SELECT . " WHERE id = ?");
$stmt->bind_param("i", $ligne_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
  http_response_code(404);
  die(json_encode(["error" => "Ligne introuvable"]));
}

echo json_encode([
  "ligne" => ligne_format($row, true),
  "arret_ids" => ligne_arret_ids($mysqli, $ligne_id),
]);
