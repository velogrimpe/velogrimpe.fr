<?php
/**
 * Exclut une falaise pour une ville (admin, tableau des trains).
 * POST JSON { "ville_id": int, "falaise_id": int }, en-tête `Authorization: Bearer <admin_token>`.
 * Réponse : { success: true } ; 401 non autorisé, 405 méthode, 400 paramètre manquant, 409 déjà exclu, 500 erreur SQL.
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
$ville_id = (int) ($input['ville_id'] ?? 0);
$falaise_id = (int) ($input['falaise_id'] ?? 0);
if ($ville_id <= 0 || $falaise_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "ville_id et falaise_id sont requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $stmt = $mysqli->prepare("INSERT INTO exclusions_villes_falaises (ville_id, falaise_id) VALUES (?, ?)");
  $stmt->bind_param("ii", $ville_id, $falaise_id);
  $stmt->execute();
  $exclusion_id = $mysqli->insert_id; // avant logChanges : son INSERT écraserait insert_id
  $stmt->close();
} catch (mysqli_sql_exception $e) {
  if ($e->getCode() === 1062) {
    http_response_code(409);
    die(json_encode(["error" => "Exclusion déjà enregistrée"]));
  }
  error_log('[exclude_falaise] ' . $e->getMessage());
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'enregistrement de l'exclusion"]));
}

logChanges("Admin", "", "insert", "exclusions_villes_falaises", $exclusion_id, $falaise_id, ["ville_id" => $ville_id, "falaise_id" => $falaise_id]);

echo json_encode(["success" => true]);
