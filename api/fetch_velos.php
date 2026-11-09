<?php
/**
 * GET /api/fetch_velos.php?falaise_id=123[&types=velo|pied|all]
 *
 * Itinéraires vélo (acces_falaise, type = 'velo') d'une falaise (`types=all` : aussi les accès sans vélo,
 * type = 'pied', pour la page d'édition ; chaque ligne porte son `type`), avec leur gare de départ, pour la cascade de
 * sélecteurs de la page d'édition (Falaise → Gare → Variante). Ces données sont
 * déjà publiques sur falaise.php.
 */
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$falaise_id = isset($_GET['falaise_id']) ? intval($_GET['falaise_id']) : 0;
if ($falaise_id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Paramètre falaise_id manquant ou invalide']);
  exit;
}

// Par défaut : accès vélo seulement (les lecteurs historiques n'attendent pas d'accès sans vélo)
$typesDemandes = match ($_GET['types'] ?? 'velo') {
  'all' => ['velo', 'pied'],
  'pied' => ['pied'],
  default => ['velo'],
};
$typesSql = "'" . implode("','", $typesDemandes) . "'"; // valeurs fixes ci-dessus, jamais de saisie

$stmt = $mysqli->prepare("
  SELECT v.type, v.acces_id AS velo_id, v.arret_id, v.falaise_id, v.depart_slug AS velo_depart, v.arrivee_slug AS velo_arrivee,
         v.variante AS velo_variante, v.variante_formate AS velo_varianteformate, v.km AS velo_km,
         v.dplus AS velo_dplus, v.dmoins AS velo_dmoins,
         v.description AS velo_descr, v.lien_openrunner AS velo_openrunner,
         " . acces_a_pied_sql('v') . " AS velo_apiedpossible, v.public AS velo_public,
         a.nom AS arret_nom, a.slug AS arret_nomformate, CONCAT(ST_Y(a.loc), ',', ST_X(a.loc)) AS arret_latlng,
         a.ter, a.tgv, a.bus, a.tram, a.teleph
  FROM acces_falaise v
  INNER JOIN arrets a ON a.id = v.arret_id AND a.deleted = 0
  WHERE v.type IN ($typesSql) AND v.falaise_id = ? AND v.public >= 0
  ORDER BY a.nom, v.variante, v.acces_id");
$stmt->bind_param("i", $falaise_id);
$stmt->execute();
$res = $stmt->get_result();

$velos = [];
while ($row = $res->fetch_assoc()) {
  $velo_id = (int) $row['velo_id'];
  $gpx_exists = file_exists(velo_gpx_chemin($velo_id, $row['velo_depart'], $row['velo_arrivee'], (string) $row['velo_varianteformate']));
  $velos[] = [
    'type' => $row['type'],
    'velo_id' => $velo_id,
    'arret_id' => (int) $row['arret_id'],
    'falaise_id' => (int) $row['falaise_id'],
    'velo_depart' => $row['velo_depart'],
    'velo_arrivee' => $row['velo_arrivee'],
    'velo_variante' => $row['velo_variante'],
    'velo_varianteformate' => $row['velo_varianteformate'],
    'velo_km' => $row['velo_km'] !== null ? (float) $row['velo_km'] : null,
    'velo_dplus' => $row['velo_dplus'] !== null ? (int) $row['velo_dplus'] : null,
    'velo_dmoins' => $row['velo_dmoins'] !== null ? (int) $row['velo_dmoins'] : null,
    'velo_descr' => (string) $row['velo_descr'],
    'velo_openrunner' => (string) ($row['velo_openrunner'] ?? ''),
    'velo_apiedpossible' => (int) ($row['velo_apiedpossible'] ?? 0),
    'velo_public' => (int) $row['velo_public'],
    'arret' => [
      'id' => (int) $row['arret_id'],
      'nom' => $row['arret_nom'],
      'nomformate' => $row['arret_nomformate'],
      'latlng' => $row['arret_latlng'],
      'types' => arret_types($row),
    ],
    'gpx_url' => $gpx_exists
      ? velo_gpx_url($velo_id, $row['velo_depart'], $row['velo_arrivee'], (string) $row['velo_varianteformate'])
      : null,
  ];
}
$stmt->close();

echo json_encode(['falaise_id' => $falaise_id, 'velos' => $velos], JSON_UNESCAPED_UNICODE);
