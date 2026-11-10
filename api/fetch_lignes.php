<?php
// Sans paramètre : liste légère (sans géométrie). Avec ?geometry=1 : toutes les lignes avec
// leur géométrie (carte d'admin) ; avec &arrets=1, chaque ligne porte aussi ses arrêts (carte.php). Avec ?arret_id= : lignes
// desservant cet arrêt, avec géométrie et arrêts liés (ids + coordonnées) (bouton « voir la ligne »).
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/lignes_lib.php';

$arret_id = (int) ($_GET['arret_id'] ?? 0);

if ($arret_id > 0) {
  $stmt = $mysqli->prepare(
    LIGNE_SELECT . " WHERE id IN (SELECT ligne_id FROM arrets_lignes_transport WHERE arret_id = ?) ORDER BY nom"
  );
  $stmt->bind_param("i", $arret_id);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  $lignes = array_map(function ($row) use ($mysqli) {
    $l = ligne_format($row, true);
    $l["arret_ids"] = ligne_arret_ids($mysqli, $l["id"]);
    $l["arrets"] = ligne_arrets($mysqli, $l["id"]);
    return $l;
  }, $rows);
} else {
  $withGeometry = ($_GET['geometry'] ?? '') === '1';
  $res = $mysqli->query(LIGNE_SELECT . " ORDER BY nom");
  $withArrets = ($_GET['arrets'] ?? '') === '1';
  $lignes = array_map(function ($row) use ($mysqli, $withGeometry, $withArrets) {
    $l = ligne_format($row, $withGeometry);
    if ($withArrets) $l["arrets"] = ligne_arrets($mysqli, $l["id"]);
    return $l;
  }, $res->fetch_all(MYSQLI_ASSOC));
}

echo json_encode(["lignes" => $lignes]);
