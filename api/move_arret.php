<?php
/**
 * Repositionne un arrêt (admin) : la position est fixée à la main (`position_manuelle = 1` : ni un recalcul de
 * barycentre ni une mise à jour GTFS ne la déplacent), et le parent de l'arrêt éventuel est recalculé.
 * POST JSON { "arret_id": int, "lat": float, "lng": float }, en-tête `Authorization: Bearer <admin_token>`.
 * POST JSON { "arret_id": int, "auto": true } : repasse un parent en position automatique (barycentre de ses enfants).
 */
header('Content-Type: application/json');
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if ($authHeader !== 'Bearer ' . $config["admin_token"]) {
  http_response_code(401);
  die(json_encode(["error" => "Non autorisé"]));
}

$input = json_decode(file_get_contents('php://input'), true);
$arret_id = (int) ($input['arret_id'] ?? 0);
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;
$auto = ($input['auto'] ?? false) === true;
if ($auto && $arret_id > 0) {
  $lat = $lng = 0; // pas de coordonnées dans ce mode
}
if ($arret_id <= 0 || !is_numeric($lat) || !is_numeric($lng) || abs($lat) > 90 || abs($lng) > 180) {
  http_response_code(400);
  die(json_encode(["error" => "Un id d'arrêt et des coordonnées valides (lat, lng) sont requis"]));
}
$lat = (float) $lat;
$lng = (float) $lng;

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_parents_lib.php';

$stmt = $mysqli->prepare("SELECT ST_Y(loc) AS lat, ST_X(loc) AS lng, parent_id FROM arrets WHERE id = ?");
$stmt->bind_param("i", $arret_id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$old) {
  http_response_code(404);
  die(json_encode(["error" => "Arrêt introuvable"]));
}

if ($auto) {
  if (arret_nb_enfants($mysqli, $arret_id) === 0) {
    http_response_code(409);
    die(json_encode(["error" => "Seul un parent peut repasser en position automatique"]));
  }
  $stmt = $mysqli->prepare("UPDATE arrets SET position_manuelle = 0 WHERE id = ?");
  $stmt->bind_param("i", $arret_id);
  $stmt->execute();
  $stmt->close();
  arret_recalculer_parent($mysqli, $arret_id);
  echo json_encode(["success" => true]);
  exit;
}

$wkt = sprintf('POINT(%.7f %.7f)', $lng, $lat); // WKT = (lng lat)
$stmt = $mysqli->prepare("UPDATE arrets SET loc = ST_GeomFromText(?, 4326), position_manuelle = 1 WHERE id = ?");
$stmt->bind_param("si", $wkt, $arret_id);
$stmt->execute();
$stmt->close();
if ($old["parent_id"] !== null) arret_recalculer_parent($mysqli, (int) $old["parent_id"]);

logChanges("Admin", "", "update", "arrets", $arret_id, null, ["loc" => "$lat,$lng"], ["loc" => $old["lat"] . "," . $old["lng"]]);
echo json_encode(["success" => true]);
