<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// `gare_id` reste accepté (anciens appels) : une gare est un arrêt.
if (!isset($_GET['ville_id']) || !(isset($_GET['arret_id']) || isset($_GET['gare_id'])) || !isset($_GET['train_tgv'])) {
  echo json_encode(["error" => "ID manquant"]);
  exit;
}

$ville_id = (int) $_GET['ville_id'];
$arret_id = (int) ($_GET['arret_id'] ?? $_GET['gare_id']);
$train_tgv = (int) $_GET['train_tgv'];

  $stmt = $mysqli->prepare("SELECT train_id FROM train WHERE ville_id = ? AND arret_id = ? AND train_tgv = ?");
  if (!$stmt) {
  echo json_encode(["error" => $mysqli->error]);
  exit;
}
$stmt->bind_param("iii", $ville_id, $arret_id, $train_tgv);

$stmt->execute();
$res = $stmt->get_result();
$train = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($train !== null) {
  echo json_encode(true);
} else {
  echo json_encode(false);
}
