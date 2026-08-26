<?php
/**
 * GET /api/fetch_velos.php?falaise_id=123
 *
 * Itinéraires vélo d'une falaise, avec leur gare de départ, pour la cascade de
 * sélecteurs de la page d'édition (Falaise → Gare → Variante). Ces données sont
 * déjà publiques sur falaise.php.
 */
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';

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

$stmt = $mysqli->prepare("
  SELECT v.velo_id, v.gare_id, v.falaise_id, v.velo_depart, v.velo_arrivee,
         v.velo_variante, v.velo_varianteformate, v.velo_km, v.velo_dplus, v.velo_dmoins,
         v.velo_descr, v.velo_openrunner, v.velo_apieduniquement, v.velo_apiedpossible, v.velo_public,
         g.gare_nom, g.gare_nomformate, g.gare_latlng
  FROM velo v
  INNER JOIN gares g ON g.gare_id = v.gare_id AND g.deleted = 0
  WHERE v.falaise_id = ?
  ORDER BY g.gare_nom, v.velo_variante, v.velo_id");
$stmt->bind_param("i", $falaise_id);
$stmt->execute();
$res = $stmt->get_result();

$velos = [];
while ($row = $res->fetch_assoc()) {
  $velo_id = (int) $row['velo_id'];
  $gpx_exists = file_exists(velo_gpx_chemin($velo_id, $row['velo_depart'], $row['velo_arrivee'], (string) $row['velo_varianteformate']));
  $velos[] = [
    'velo_id' => $velo_id,
    'gare_id' => (int) $row['gare_id'],
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
    'velo_apieduniquement' => (int) $row['velo_apieduniquement'],
    'velo_apiedpossible' => (int) ($row['velo_apiedpossible'] ?? 0),
    'velo_public' => (int) $row['velo_public'],
    'gare' => [
      'id' => (int) $row['gare_id'],
      'nom' => $row['gare_nom'],
      'nomformate' => $row['gare_nomformate'],
      'latlng' => $row['gare_latlng'],
    ],
    'gpx_url' => $gpx_exists
      ? velo_gpx_url($velo_id, $row['velo_depart'], $row['velo_arrivee'], (string) $row['velo_varianteformate'])
      : null,
  ];
}
$stmt->close();

echo json_encode(['falaise_id' => $falaise_id, 'velos' => $velos], JSON_UNESCAPED_UNICODE);
