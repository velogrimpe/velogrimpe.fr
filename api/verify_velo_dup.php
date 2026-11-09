<?php
/**
 * GET /api/verify_velo_dup.php?arret_id=&falaise_id=&type=velo|pied[&variante=]
 *
 * Un accès DU MÊME TYPE et de la MÊME VARIANTE existe-t-il déjà entre cet arrêt et cette falaise ? Un accès
 * vélo n'empêche pas d'ajouter un accès sans vélo (et inversement), et une variante nommée autrement est un
 * autre accès (variante vide par défaut : comme avant). Réponse : true | false.
 */
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';

// `gare_id` reste accepté (anciens appelants).
$arret_id = $_GET['arret_id'] ?? $_GET['gare_id'] ?? null;
if (!isset($_GET['falaise_id']) || $arret_id === null) {
  echo json_encode(["error" => "ID manquant"]);
  exit;
}
$type = acces_lire_type($_GET['type'] ?? null);
$falaise_id = (int) $_GET['falaise_id'];
$arret_id = (int) $arret_id;

$variante = trim((string) ($_GET['variante'] ?? ''));

$stmt = $mysqli->prepare("SELECT 1 FROM acces_falaise WHERE type = ? AND falaise_id = ? AND arret_id = ? AND variante = ? LIMIT 1");
$stmt->bind_param("siis", $type, $falaise_id, $arret_id, $variante);
$stmt->execute();
echo json_encode($stmt->get_result()->num_rows > 0);
$stmt->close();
