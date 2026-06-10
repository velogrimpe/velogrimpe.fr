<?php
/**
 * Liste des arrêts de bus pour l'éditeur de détails falaise.
 *
 * Renvoie les arrêts situés dans la bbox fournie + tous les arrêts liés à la
 * falaise (même hors bbox), avec leur statut de liaison et les lignes desservant
 * l'arrêt (pour l'info-bulle).
 *
 * GET /api/fetch_bus_arrets.php?falaise_id=39&bbox=43.80,5.30,43.90,5.45
 *   bbox = south,west,north,east (optionnel : sans bbox, seuls les arrêts liés
 *   sont renvoyés).
 */
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$falaise_id = isset($_GET['falaise_id']) && $_GET['falaise_id'] !== '' ? (int) $_GET['falaise_id'] : null;
if (!$falaise_id) {
  http_response_code(400);
  die(json_encode(["error" => "Un id de falaise est requis"]));
}

// Parse bbox : "south,west,north,east"
$bbox = null;
if (isset($_GET['bbox']) && $_GET['bbox'] !== '') {
  $parts = array_map('trim', explode(',', $_GET['bbox']));
  if (count($parts) === 4 && count(array_filter($parts, 'is_numeric')) === 4) {
    $bbox = array_map('floatval', $parts); // [south, west, north, east]
  }
}

if ($bbox) {
  [$south, $west, $north, $east] = $bbox;
  $sql =
    "SELECT a.id, a.nom, a.description, ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng,
            (baf.falaise_id IS NOT NULL) AS linked,
            GROUP_CONCAT(DISTINCT li.nom ORDER BY li.nom SEPARATOR ', ') AS lignes
     FROM bus_arrets a
     LEFT JOIN bus_arrets_falaise baf ON baf.arret_id = a.id AND baf.falaise_id = ?
     LEFT JOIN bus_liaisons l ON (l.arret_1_id = a.id OR l.arret_2_id = a.id)
     LEFT JOIN bus_lignes li ON li.id = l.ligne_id
     WHERE (ST_Y(a.loc) BETWEEN ? AND ? AND ST_X(a.loc) BETWEEN ? AND ?)
        OR baf.falaise_id IS NOT NULL
     GROUP BY a.id, a.nom, a.description, lat, lng, linked";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("idddd", $falaise_id, $south, $north, $west, $east);
} else {
  $sql =
    "SELECT a.id, a.nom, a.description, ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng,
            1 AS linked,
            GROUP_CONCAT(DISTINCT li.nom ORDER BY li.nom SEPARATOR ', ') AS lignes
     FROM bus_arrets a
     JOIN bus_arrets_falaise baf ON baf.arret_id = a.id AND baf.falaise_id = ?
     LEFT JOIN bus_liaisons l ON (l.arret_1_id = a.id OR l.arret_2_id = a.id)
     LEFT JOIN bus_lignes li ON li.id = l.ligne_id
     GROUP BY a.id, a.nom, a.description, lat, lng";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("i", $falaise_id);
}

$stmt->execute();
$res = $stmt->get_result();

$arrets = [];
while ($row = $res->fetch_assoc()) {
  $arrets[] = [
    "id" => (int) $row["id"],
    "nom" => $row["nom"],
    "description" => $row["description"],
    "lat" => (float) $row["lat"],
    "lng" => (float) $row["lng"],
    "linked" => (bool) $row["linked"],
    "lignes" => $row["lignes"] !== null && $row["lignes"] !== ""
      ? array_map('trim', explode(',', $row["lignes"]))
      : [],
  ];
}
$stmt->close();

echo json_encode(["arrets" => $arrets]);
