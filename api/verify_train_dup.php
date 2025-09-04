<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

if (!isset($_GET['ville_id']) || !isset($_GET['gare_id'])) {
  echo json_encode(["error" => "ID manquant"]);
  exit;
}

$stmt = $mysqli->prepare("SELECT train_id FROM train WHERE ville_id = ? AND gare_id = ?");
$stmt->execute([$_GET['ville_id'], $_GET['gare_id']]);
$train = $stmt->fetch();

if ($train) {
  echo json_encode(true);
} else {
  echo json_encode(false);
}
?>