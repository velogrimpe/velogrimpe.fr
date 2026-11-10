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

// Suppression logique (colonne `deleted`) : acces_falaise et train référencent arrets sans cascade.
$stmt = $mysqli->prepare("SELECT deleted FROM arrets WHERE id = ?");
$stmt->bind_param("i", $arret_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
  http_response_code(404);
  die(json_encode(["error" => "Arrêt introuvable"]));
}

$stmt = $mysqli->prepare("UPDATE arrets SET deleted = 1 WHERE id = ?");
$stmt->bind_param("i", $arret_id);
$stmt->execute();
$stmt->close();
echo json_encode(["success" => true]);
