<?php
header('Content-Type: application/json');
include "../database/velogrimpe.php";

if (!isset($_GET['falaise_id']) || !isset($_GET['gare_id'])) {
  echo json_encode(["error" => "ID manquant"]);
  exit;
}

$stmt = $mysqli->prepare("SELECT velo_id FROM velo WHERE falaise_id = ? AND gare_id = ?");
$stmt->execute([$_GET['falaise_id'], $_GET['gare_id']]);
$velo = $stmt->fetch();

if ($velo) {
  echo json_encode(true);
} else {
  echo json_encode(false);
}
?>