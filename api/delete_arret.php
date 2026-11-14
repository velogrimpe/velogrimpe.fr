<?php
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
if ($arret_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "Un id d'arrêt est requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_parents_lib.php';

// Suppression logique (colonne `deleted`) : acces_falaise et train référencent arrets sans cascade.
// Un parent qui a des enfants, ou le dernier enfant d'un parent, ne se supprime pas : défusionner d'abord.
$stmt = $mysqli->prepare("SELECT deleted, parent_id FROM arrets WHERE id = ?");
$stmt->bind_param("i", $arret_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
  http_response_code(404);
  die(json_encode(["error" => "Arrêt introuvable"]));
}

if (arret_nb_enfants($mysqli, $arret_id) > 0) {
  http_response_code(409);
  die(json_encode(["error" => "Cet arrêt est un parent : défusionnez-le avant de le supprimer"]));
}
$parent_id = $row["parent_id"] !== null ? (int) $row["parent_id"] : null;
if ($parent_id !== null && !$row["deleted"] && arret_nb_enfants($mysqli, $parent_id) <= 1) {
  http_response_code(409);
  die(json_encode(["error" => "C'est le dernier enfant de son parent : défusionnez le parent à la place"]));
}

$stmt = $mysqli->prepare("UPDATE arrets SET deleted = 1 WHERE id = ?");
$stmt->bind_param("i", $arret_id);
$stmt->execute();
$stmt->close();
if ($parent_id !== null) arret_recalculer_parent($mysqli, $parent_id);
echo json_encode(["success" => true]);
