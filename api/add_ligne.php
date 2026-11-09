<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  die(json_encode(["error" => "Corps de requête JSON invalide"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/lignes_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/geoplateforme_lib.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$ligne_id = isset($input['ligne_id']) && $input['ligne_id'] !== '' ? (int) $input['ligne_id'] : null;
$nom = trim($input['nom'] ?? '');
if ($nom === '') {
  http_response_code(400);
  die(json_encode(["error" => "Le nom de la ligne est obligatoire"]));
}

$fields = [];
foreach (LIGNE_TEXT_FIELDS as $f) {
  $v = trim($input[$f] ?? '');
  $fields[$f] = $v !== '' ? $v : null;
}

$geojson = null;
$geometry = $input['geometry'] ?? null;
if ($geometry !== null) {
  if (!is_array($geometry) || ($geometry['type'] ?? null) !== 'MultiLineString' || !is_array($geometry['coordinates'] ?? null)) {
    http_response_code(400);
    die(json_encode(["error" => "La géométrie doit être un GeoJSON MultiLineString"]));
  }
  $geojson = json_encode($geometry);
}

$arret_ids = is_array($input['arret_ids'] ?? null) ? array_values(array_unique(array_map('intval', $input['arret_ids']))) : [];

// Arrêts à créer en même temps que la ligne (import GTFS) : tous validés avant
// toute écriture, créés dans la même transaction.
const NOUVEL_ARRET_TYPES = ['ter' => 'ter', 'tgv' => 'tgv', 'bus' => 'bus', 'tram' => 'tram', 'telepherique' => 'teleph'];
$nouveaux = [];
foreach (is_array($input['nouveaux_arrets'] ?? null) ? $input['nouveaux_arrets'] : [] as $n) {
  $n_nom = trim($n['nom'] ?? '');
  $n_lat = $n['lat'] ?? null;
  $n_lng = $n['lng'] ?? null;
  $n_types = $n['types'] ?? ['bus'];
  if (
    $n_nom === '' || !is_numeric($n_lat) || !is_numeric($n_lng)
    || abs((float) $n_lat) > 90 || abs((float) $n_lng) > 180
    || !is_array($n_types) || !$n_types || array_diff($n_types, array_keys(NOUVEL_ARRET_TYPES))
  ) {
    http_response_code(400);
    die(json_encode(["error" => "Nouvel arrêt invalide (nom, coordonnées ou modalité)"]));
  }
  $nouveaux[] = ["nom" => $n_nom, "lat" => (float) $n_lat, "lng" => (float) $n_lng, "types" => $n_types];
}

// Commune / code INSEE / code postal des nouveaux arrêts : géocodage inverse
// IGN (unitaire jusqu'à 10 arrêts, un seul appel batch au-delà), fait AVANT la
// transaction pour ne pas la garder ouverte pendant des appels réseau. Best
// effort : sans résultat, les champs restent vides et l'arrêt est créé quand même.
$geocodes = fetch_geoplateforme_reverse_geocode_many(
  array_map(fn($n) => [$n['lat'], $n['lng']], $nouveaux)
);

try {
  $mysqli->begin_transaction();

  if ($arret_ids) {
    $in = implode(',', $arret_ids);
    $n = (int) $mysqli->query("SELECT COUNT(*) AS n FROM arrets WHERE id IN ($in)")->fetch_assoc()['n'];
    if ($n !== count($arret_ids)) {
      $mysqli->rollback();
      http_response_code(400);
      die(json_encode(["error" => "Arrêt lié inconnu"]));
    }
  }

  if ($ligne_id !== null) {
    $stmt = $mysqli->prepare("SELECT id FROM lignes_transport WHERE id = ?");
    $stmt->bind_param("i", $ligne_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) {
      $mysqli->rollback();
      http_response_code(404);
      die(json_encode(["error" => "Ligne introuvable"]));
    }
    $stmt = $mysqli->prepare(
      "UPDATE lignes_transport
       SET nom = ?, numero = ?, operateur = ?, operateur_url = ?, horaire_url = ?, ref = ?, couleur = ?,
           geometry = IF(? IS NULL, NULL, ST_GeomFromGeoJSON(?))
       WHERE id = ?"
    );
    $stmt->bind_param(
      "sssssssssi",
      $nom, $fields['numero'], $fields['operateur'], $fields['operateur_url'],
      $fields['horaire_url'], $fields['ref'], $fields['couleur'], $geojson, $geojson, $ligne_id
    );
  } else {
    $stmt = $mysqli->prepare(
      "INSERT INTO lignes_transport (nom, numero, operateur, operateur_url, horaire_url, ref, couleur, geometry)
       VALUES (?, ?, ?, ?, ?, ?, ?, IF(? IS NULL, NULL, ST_GeomFromGeoJSON(?)))"
    );
    $stmt->bind_param(
      "sssssssss",
      $nom, $fields['numero'], $fields['operateur'], $fields['operateur_url'],
      $fields['horaire_url'], $fields['ref'], $fields['couleur'], $geojson, $geojson
    );
  }
  $stmt->execute();
  if ($ligne_id === null) {
    $ligne_id = $mysqli->insert_id;
  }
  $stmt->close();

  // Création des nouveaux arrêts (import GTFS), liés ensuite à la ligne
  $arrets_crees = [];
  $contrib = 'Import GTFS';
  $contrib_mail = (string) ($config['contact_mail'] ?? '');
  $stmt = $mysqli->prepare(
    "INSERT INTO arrets (loc, nom, slug, ter, tgv, bus, tram, teleph, commune, code_insee, cp, contrib, contrib_mail)
     VALUES (ST_GeomFromText(?, 4326), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
  );
  foreach ($nouveaux as $i => $n) {
    $commune = $geocodes[$i]['city'] ?? null;
    $code_insee = $geocodes[$i]['citycode'] ?? null;
    $cp = $geocodes[$i]['postcode'] ?? null;
    $wkt = sprintf('POINT(%.7f %.7f)', $n['lng'], $n['lat']); // WKT = (lng lat)
    $slug = arret_slugify($n['nom']);
    $flags = [];
    foreach (NOUVEL_ARRET_TYPES as $type => $col) {
      $flags[$col] = (int) in_array($type, $n['types'], true);
    }
    $stmt->bind_param(
      "sssiiiiisssss",
      $wkt, $n['nom'], $slug,
      $flags['ter'], $flags['tgv'], $flags['bus'], $flags['tram'], $flags['teleph'],
      $commune, $code_insee, $cp,
      $contrib, $contrib_mail
    );
    $stmt->execute();
    $arrets_crees[] = $mysqli->insert_id;
  }
  $stmt->close();
  $arret_ids = array_values(array_unique(array_merge($arret_ids, $arrets_crees)));

  // Remplacement du set d'arrêts liés
  $stmt = $mysqli->prepare("DELETE FROM arrets_lignes_transport WHERE ligne_id = ?");
  $stmt->bind_param("i", $ligne_id);
  $stmt->execute();
  $stmt->close();
  $stmt = $mysqli->prepare("INSERT INTO arrets_lignes_transport (ligne_id, arret_id) VALUES (?, ?)");
  foreach ($arret_ids as $arret_id) {
    $stmt->bind_param("ii", $ligne_id, $arret_id);
    $stmt->execute();
  }
  $stmt->close();

  $mysqli->commit();
  echo json_encode(["success" => true, "ligne_id" => $ligne_id, "arrets_crees" => $arrets_crees]);
} catch (Throwable $e) {
  error_log('[add_ligne] ' . $e->getMessage());
  try {
    $mysqli->rollback();
  } catch (Throwable $e2) {
    // connexion perdue (ex. max_allowed_packet dépassé) : MySQL a déjà annulé la transaction
  }
  http_response_code(500);
  echo json_encode(["error" => "Erreur lors de l'enregistrement de la ligne"]);
}
