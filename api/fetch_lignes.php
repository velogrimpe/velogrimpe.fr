<?php
// Sans paramètre : liste légère (sans géométrie). Avec ?geometry=1 : toutes les lignes avec
// leur géométrie (carte d'admin) ; avec &arrets=1, chaque ligne porte aussi ses arrêts (carte.php). Avec ?arret_id= : lignes
// desservant cet arrêt, avec géométrie et arrêts liés (ids + coordonnées) (bouton « voir la ligne »).
// Avec ?falaise_id= : lignes desservant un arrêt lié à la falaise (accès `pied`, enfants inclus), avec géométrie
// (page falaise). Avec ?bbox=sud,ouest,nord,est : lignes dont l'emprise rencontre la boîte, avec géométrie
// (éditeur de détails falaise).
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/lignes_lib.php';

$arret_id = (int) ($_GET['arret_id'] ?? 0);

if ($arret_id > 0) {
  $stmt = $mysqli->prepare(
    // Un parent est desservi par les lignes de ses enfants
    LIGNE_SELECT . " WHERE id IN (SELECT ligne_id FROM arrets_lignes_transport
                                  WHERE arret_id = ? OR arret_id IN (SELECT id FROM arrets WHERE parent_id = ?)) ORDER BY nom"
  );
  $stmt->bind_param("ii", $arret_id, $arret_id);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  $lignes = array_map(function ($row) use ($mysqli) {
    $l = ligne_format($row, true);
    $l["arret_ids"] = ligne_arret_ids($mysqli, $l["id"]);
    $l["arrets"] = ligne_arrets($mysqli, $l["id"]);
    return $l;
  }, $rows);
} elseif (($falaise_id = (int) ($_GET['falaise_id'] ?? 0)) > 0) {
  $stmt = $mysqli->prepare(
    LIGNE_SELECT . " WHERE id IN (SELECT alt.ligne_id FROM arrets_lignes_transport alt JOIN arrets a ON a.id = alt.arret_id
                                  WHERE COALESCE(a.parent_id, a.id) IN (SELECT arret_id FROM acces_falaise
                                                                        WHERE falaise_id = ? AND type = 'pied' AND public >= 0)) ORDER BY nom"
  );
  $stmt->bind_param("i", $falaise_id);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  $lignes = array_map(fn($row) => ligne_format($row, true), $rows);
} elseif (preg_match('/^(-?\d+(\.\d+)?,){3}-?\d+(\.\d+)?$/', $_GET['bbox'] ?? '')) {
  [$s, $w, $n, $e] = array_map('floatval', explode(',', $_GET['bbox']));
  $box = json_encode(["type" => "Polygon", "coordinates" => [[[$w, $s], [$e, $s], [$e, $n], [$w, $n], [$w, $s]]]]);
  $stmt = $mysqli->prepare(LIGNE_SELECT . " WHERE geometry IS NOT NULL AND MBRIntersects(geometry, ST_GeomFromGeoJSON(?)) ORDER BY nom");
  $stmt->bind_param("s", $box);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  $lignes = array_map(fn($row) => ligne_format($row, true), $rows);
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
