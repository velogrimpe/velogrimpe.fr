<?php
/**
 * Repositionne un arrêt (admin) : seule la colonne `loc` est modifiée.
 * POST JSON { "arret_id": int, "lat": float, "lng": float }, en-tête `Authorization: Bearer <admin_token>`.
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
if ($arret_id <= 0 || !is_numeric($lat) || !is_numeric($lng) || abs($lat) > 90 || abs($lng) > 180) {
  http_response_code(400);
  die(json_encode(["error" => "Un id d'arrêt et des coordonnées valides (lat, lng) sont requis"]));
}
$lat = (float) $lat;
$lng = (float) $lng;

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';

$stmt = $mysqli->prepare("SELECT ST_Y(loc) AS lat, ST_X(loc) AS lng FROM arrets WHERE id = ?");
$stmt->bind_param("i", $arret_id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$old) {
  http_response_code(404);
  die(json_encode(["error" => "Arrêt introuvable"]));
}

$wkt = sprintf('POINT(%.7f %.7f)', $lng, $lat); // WKT = (lng lat)
$stmt = $mysqli->prepare("UPDATE arrets SET loc = ST_GeomFromText(?, 4326) WHERE id = ?");
$stmt->bind_param("si", $wkt, $arret_id);
$stmt->execute();
$stmt->close();

logChanges("Admin", "", "update", "arrets", $arret_id, null, ["loc" => "$lat,$lng"], ["loc" => $old["lat"] . "," . $old["lng"]]);
echo json_encode(["success" => true]);
