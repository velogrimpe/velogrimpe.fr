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
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_parents_lib.php';
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
  $n_stop = trim((string) ($n['stop_id'] ?? ''));
  if (strlen($n_stop) > 128) {
    http_response_code(400);
    die(json_encode(["error" => "Identifiant GTFS invalide"]));
  }
  // Regroupement : avec un arrêt existant (`regrouper_avec`) ou avec d'autres arrêts créés par le même envoi
  // (`groupe` ; `groupe_stop_id` = identifiant GTFS de la station parente, porté par le parent créé).
  $n_avec = (int) ($n['regrouper_avec'] ?? 0);
  $n_groupe = trim((string) ($n['groupe'] ?? ''));
  $n_groupe_stop = trim((string) ($n['groupe_stop_id'] ?? ''));
  if (strlen($n_groupe) > 128 || strlen($n_groupe_stop) > 128 || $n_avec < 0) {
    http_response_code(400);
    die(json_encode(["error" => "Regroupement d'arrêt invalide"]));
  }
  $nouveaux[] = [
    "nom" => $n_nom, "lat" => (float) $n_lat, "lng" => (float) $n_lng, "types" => $n_types,
    "stop_id" => $n_stop !== '' ? $n_stop : null,
    "regrouper_avec" => $n_avec > 0 ? $n_avec : null,
    "groupe" => $n_groupe !== '' ? $n_groupe : null,
    "groupe_stop_id" => $n_groupe_stop !== '' ? $n_groupe_stop : null,
  ];
}

// Identifiants GTFS des arrêts existants liés (import / mise à jour GTFS) :
// [{arret_id, stop_id, nom?}], écrits en références externes (source « gtfs »).
$gtfs_refs = [];
foreach (is_array($input['gtfs_refs'] ?? null) ? $input['gtfs_refs'] : [] as $r) {
  $r_arret = (int) ($r['arret_id'] ?? 0);
  $r_stop = trim((string) ($r['stop_id'] ?? ''));
  if ($r_arret <= 0 || $r_stop === '' || strlen($r_stop) > 128) {
    http_response_code(400);
    die(json_encode(["error" => "Référence GTFS invalide"]));
  }
  $r_lat = $r['lat'] ?? null;
  $r_lng = $r['lng'] ?? null;
  $r_pos = is_numeric($r_lat) && is_numeric($r_lng) && abs((float) $r_lat) <= 90 && abs((float) $r_lng) <= 180
    ? [(float) $r_lat, (float) $r_lng] : null;
  $gtfs_refs[] = ["arret_id" => $r_arret, "stop_id" => $r_stop, "nom" => trim((string) ($r['nom'] ?? '')) ?: null, "pos" => $r_pos];
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
    // Une ligne dessert des arrêts « feuilles » : un parent (qui a des enfants) ne s'y lie pas directement.
    $nb_parents = (int) $mysqli->query(
      "SELECT COUNT(*) AS n FROM arrets a WHERE a.id IN ($in)
       AND EXISTS (SELECT 1 FROM arrets c WHERE c.parent_id = a.id AND c.deleted = 0)"
    )->fetch_assoc()['n'];
    if ($nb_parents > 0) {
      $mysqli->rollback();
      http_response_code(400);
      die(json_encode(["error" => "Un arrêt parent ne se lie pas directement à une ligne : liez ses arrêts enfants"]));
    }
  }
  foreach ($nouveaux as $n) {
    if ($n['regrouper_avec'] === null) continue;
    $avec = (int) $n['regrouper_avec'];
    $ok = $mysqli->query("SELECT 1 FROM arrets WHERE id = $avec AND deleted = 0")->fetch_assoc();
    if (!$ok) {
      $mysqli->rollback();
      http_response_code(400);
      die(json_encode(["error" => "Arrêt de regroupement inconnu : $avec"]));
    }
  }

  $ligne_existait = $ligne_id !== null;
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

  // Réimport : un arrêt déjà suivi par cette ligne sous cette référence GTFS est repositionné (jamais le nom ;
  // jamais une position fixée à la main). Avant le remplacement des liens, qui sert de preuve du suivi.
  $parents_a_recalculer = [];
  if ($ligne_existait) {
    $stmtSuivi = $mysqli->prepare(
      "SELECT a.parent_id FROM arrets a
       JOIN arrets_lignes_transport alt ON alt.arret_id = a.id AND alt.ligne_id = ?
       JOIN references_externes re ON re.arret_id = a.id AND re.source = 'gtfs' AND re.ref = ?
       WHERE a.id = ? AND a.position_manuelle = 0 AND a.deleted = 0"
    );
    $stmtMove = $mysqli->prepare("UPDATE arrets SET loc = ST_GeomFromText(?, 4326) WHERE id = ?");
    foreach ($gtfs_refs as $r) {
      if ($r['pos'] === null) continue;
      $stmtSuivi->bind_param("isi", $ligne_id, $r['stop_id'], $r['arret_id']);
      $stmtSuivi->execute();
      $suivi = $stmtSuivi->get_result()->fetch_assoc();
      if (!$suivi) continue;
      $wkt = sprintf('POINT(%.7f %.7f)', $r['pos'][1], $r['pos'][0]); // WKT = (lng lat)
      $stmtMove->bind_param("si", $wkt, $r['arret_id']);
      $stmtMove->execute();
      if ($suivi['parent_id'] !== null) $parents_a_recalculer[(int) $suivi['parent_id']] = true;
    }
    $stmtSuivi->close();
    $stmtMove->close();
    foreach (array_keys($parents_a_recalculer) as $pid) arret_recalculer_parent($mysqli, $pid);
  }

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
    if ($n['stop_id'] !== null) {
      $gtfs_refs[] = ["arret_id" => $mysqli->insert_id, "stop_id" => $n['stop_id'], "nom" => $n['nom']];
    }
  }
  $stmt->close();
  $arret_ids = array_values(array_unique(array_merge($arret_ids, $arrets_crees)));

  // Regroupements : sous le parent d'un arrêt existant (créé si l'arrêt est orphelin), ou entre arrêts du même
  // groupe GTFS. Le parent est toujours le dessus : `arret_fusionner` crée / réutilise, un refus annule tout.
  $parents_crees = [];
  $racine = function (int $id) use ($mysqli): int {
    $p = $mysqli->query("SELECT parent_id FROM arrets WHERE id = $id")->fetch_assoc()['parent_id'];
    return $p !== null ? (int) $p : $id;
  };
  $regrouper = function (int $arret, int $avec) use ($mysqli, $racine, &$parents_crees): int {
    $cible = $racine($avec);
    $etait_parent = arret_nb_enfants($mysqli, $cible) > 0;
    $parent = arret_fusionner($mysqli, $arret, $cible)['parent_id'];
    if (!$etait_parent) $parents_crees[] = $parent;
    return $parent;
  };
  // Ref GTFS de la station parente, portée par le parent (doublon ignoré)
  $ref_station = function (int $parent, string $station, string $libelle) use ($mysqli): void {
    $stmt = $mysqli->prepare("INSERT IGNORE INTO references_externes (arret_id, source, ref, libelle) VALUES (?, 'gtfs', ?, ?)");
    $stmt->bind_param("iss", $parent, $station, $libelle);
    $stmt->execute();
    $stmt->close();
  };
  foreach ($nouveaux as $i => $n) {
    if ($n['regrouper_avec'] === null) continue;
    $parent = $regrouper($arrets_crees[$i], (int) $n['regrouper_avec']);
    if ($n['groupe_stop_id'] !== null) $ref_station($parent, $n['groupe_stop_id'], $n['nom']);
  }
  $groupes = [];
  foreach ($nouveaux as $i => $n) {
    if ($n['groupe'] !== null) $groupes[$n['groupe']][] = $i;
  }
  foreach ($groupes as $membres) {
    if (count($membres) < 2) continue;
    $ancre = $arrets_crees[$membres[0]];
    foreach (array_slice($membres, 1) as $i) $parent = $regrouper($arrets_crees[$i], $ancre);
    $station = null;
    foreach ($membres as $i) $station ??= $nouveaux[$i]['groupe_stop_id'];
    if ($station !== null) $ref_station($parent, $station, $nouveaux[$membres[0]]['nom']);
  }

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

  // Références GTFS : seulement pour des arrêts liés à la ligne ; doublon ignoré
  $stmt = $mysqli->prepare(
    "INSERT IGNORE INTO references_externes (arret_id, source, ref, libelle) VALUES (?, 'gtfs', ?, ?)"
  );
  foreach ($gtfs_refs as $r) {
    if (!in_array($r['arret_id'], $arret_ids, true)) {
      continue;
    }
    $stmt->bind_param("iss", $r['arret_id'], $r['stop_id'], $r['nom']);
    $stmt->execute();
  }
  $stmt->close();

  $mysqli->commit();
  echo json_encode(["success" => true, "ligne_id" => $ligne_id, "arrets_crees" => $arrets_crees, "parents" => array_values(array_unique($parents_crees))]);
} catch (ArretFusionException $e) {
  $mysqli->rollback();
  http_response_code($e->http);
  echo json_encode(["error" => $e->getMessage()]);
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
