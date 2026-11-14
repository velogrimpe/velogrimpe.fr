<?php
/**
 * Liste des arrêts de bus pour l'éditeur de détails falaise.
 *
 * Renvoie les arrêts situés dans la bbox fournie + tous les arrêts liés à la
 * falaise (même hors bbox), avec leur statut de liaison et leurs accès à la falaise.
 *
 * GET /api/fetch_arrets.php?falaise_id=39&bbox=43.80,5.30,43.90,5.45&types=bus,tram
 *   bbox = south,west,north,east (optionnel : sans bbox, seuls les arrêts liés
 *   sont renvoyés).
 *   types = sous-ensemble de ter,tgv,bus,tram,telepherique (optionnel : sans
 *   ce paramètre, toutes les modalités sont renvoyées).
 */
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_lib.php';

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

// Filtre optionnel par modalité (sous-ensemble de ter/tgv/bus/tram/teleph).
// Noms de colonnes whitelistés (pas de valeur utilisateur interpolée telle quelle).
$typeFilterSql = '';
if (isset($_GET['types']) && $_GET['types'] !== '') {
  $requested = array_map(
    fn($t) => $t === 'telepherique' ? 'teleph' : $t,
    array_map('trim', explode(',', $_GET['types']))
  );
  $columns = array_values(array_intersect(['ter', 'tgv', 'bus', 'tram', 'teleph'], $requested));
  if (!empty($columns)) {
    $typeFilterSql = ' AND (' . implode(' OR ', array_map(fn($c) => "a.$c = 1", $columns)) . ')';
  }
}

if ($bbox) {
  [$south, $west, $north, $east] = $bbox;
  $sql =
    "SELECT a.id, a.nom, a.description, a.ter, a.tgv, a.bus, a.tram, a.teleph,
            ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng,
            EXISTS (SELECT 1 FROM acces_falaise baf
                    WHERE baf.arret_id = a.id AND baf.falaise_id = ? AND baf.type = 'pied') AS linked
     FROM arrets a
     WHERE ((ST_Y(a.loc) BETWEEN ? AND ? AND ST_X(a.loc) BETWEEN ? AND ?)
        OR EXISTS (SELECT 1 FROM acces_falaise baf2
                   WHERE baf2.arret_id = a.id AND baf2.falaise_id = ? AND baf2.type = 'pied'))
       AND a.parent_id IS NULL $typeFilterSql";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("iddddi", $falaise_id, $south, $north, $west, $east, $falaise_id);
} else {
  $sql =
    "SELECT a.id, a.nom, a.description, a.ter, a.tgv, a.bus, a.tram, a.teleph,
            ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng,
            1 AS linked
     FROM arrets a
     WHERE EXISTS (SELECT 1 FROM acces_falaise baf
                   WHERE baf.arret_id = a.id AND baf.falaise_id = ? AND baf.type = 'pied') AND a.parent_id IS NULL $typeFilterSql";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("i", $falaise_id);
}

$stmt->execute();
$res = $stmt->get_result();

$arrets = [];
$idToIndex = []; // arret_id => index dans $arrets (pour rattacher les accès)
while ($row = $res->fetch_assoc()) {
  $idToIndex[(int) $row["id"]] = count($arrets);
  $arrets[] = [
    "id" => (int) $row["id"],
    "nom" => $row["nom"],
    "description" => $row["description"],
    "types" => arret_types($row),
    "type_dominant" => arret_type_dominant($row),
    "lat" => (float) $row["lat"],
    "lng" => (float) $row["lng"],
    "linked" => (bool) $row["linked"],
  ];
}
$stmt->close();

// Accès (vélo ou sans vélo) déjà décrits entre chacun de ces arrêts et la falaise : l'éditeur de falaise les
// liste dans le popup de l'arrêt et propose toujours d'en ajouter un autre (variante). Aucun contributeur.
foreach ($arrets as &$a) {
  $a["acces"] = [];
}
unset($a);
if (!empty($idToIndex)) {
  $ids = array_keys($idToIndex);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $mysqli->prepare(
    "SELECT arret_id, acces_id, type, variante, km, dplus, dmoins, public
     FROM acces_falaise WHERE falaise_id = ? AND public >= 0 AND arret_id IN ($placeholders)
     ORDER BY type DESC, variante, acces_id"
  );
  $params = array_merge([$falaise_id], $ids);
  $stmt->bind_param('i' . str_repeat('i', count($ids)), ...$params);
  $stmt->execute();
  foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $ac) {
    $arrets[$idToIndex[(int) $ac["arret_id"]]]["acces"][] = [
      "acces_id" => (int) $ac["acces_id"],
      "type" => $ac["type"],
      "variante" => $ac["variante"],
      "km" => $ac["km"] !== null ? (float) $ac["km"] : null,
      "dplus" => $ac["dplus"] !== null ? (int) $ac["dplus"] : null,
      "dmoins" => $ac["dmoins"] !== null ? (int) $ac["dmoins"] : null,
      "public" => (int) $ac["public"],
    ];
  }
  $stmt->close();
}

echo json_encode(["arrets" => $arrets]);
