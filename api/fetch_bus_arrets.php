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
$idToIndex = []; // arret_id => index dans $arrets (pour rattacher les liaisons)
while ($row = $res->fetch_assoc()) {
  $idToIndex[(int) $row["id"]] = count($arrets);
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
    "liaisons" => [], // arrêts reliés (coords incluses) — rempli ci-dessous
  ];
}
$stmt->close();

// Liaisons : pour chaque arrêt renvoyé, les arrêts reliés (avec coordonnées,
// pour tracer les arcs). Les arêtes sont non orientées : on attache la liaison
// au(x) endpoint(s) présent(s) dans le jeu de résultats.
if (!empty($idToIndex)) {
  $ids = array_keys($idToIndex);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $sql =
    "SELECT l.arret_1_id, l.arret_2_id, l.description AS liaison_descr, li.nom AS ligne_nom,
            a1.nom AS a1_nom, ST_Y(a1.loc) AS a1_lat, ST_X(a1.loc) AS a1_lng,
            a2.nom AS a2_nom, ST_Y(a2.loc) AS a2_lat, ST_X(a2.loc) AS a2_lng
     FROM bus_liaisons l
     JOIN bus_lignes li ON li.id = l.ligne_id
     JOIN bus_arrets a1 ON a1.id = l.arret_1_id
     JOIN bus_arrets a2 ON a2.id = l.arret_2_id
     WHERE l.arret_1_id IN ($placeholders) OR l.arret_2_id IN ($placeholders)";
  $stmt = $mysqli->prepare($sql);
  $params = array_merge($ids, $ids);
  $stmt->bind_param(str_repeat('i', count($params)), ...$params);
  $stmt->execute();
  $r = $stmt->get_result();
  while ($lr = $r->fetch_assoc()) {
    $a1 = (int) $lr["arret_1_id"];
    $a2 = (int) $lr["arret_2_id"];
    if (isset($idToIndex[$a1])) {
      $arrets[$idToIndex[$a1]]["liaisons"][] = [
        "arret_id" => $a2,
        "nom" => $lr["a2_nom"],
        "lat" => (float) $lr["a2_lat"],
        "lng" => (float) $lr["a2_lng"],
        "ligne" => $lr["ligne_nom"],
        "description" => $lr["liaison_descr"],
      ];
    }
    if (isset($idToIndex[$a2])) {
      $arrets[$idToIndex[$a2]]["liaisons"][] = [
        "arret_id" => $a1,
        "nom" => $lr["a1_nom"],
        "lat" => (float) $lr["a1_lat"],
        "lng" => (float) $lr["a1_lng"],
        "ligne" => $lr["ligne_nom"],
        "description" => $lr["liaison_descr"],
      ];
    }
  }
  $stmt->close();
}

echo json_encode(["arrets" => $arrets]);
