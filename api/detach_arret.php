<?php
/**
 * Détache un arrêt enfant de son parent (admin) : il redevient orphelin, le parent est recalculé.
 * POST JSON { "arret_id": int }, en-tête `Authorization: Bearer <admin_token>`.
 * 409 si l'arrêt n'est pas un enfant, ou si c'est le dernier enfant de son parent (utiliser unmerge_arret.php).
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
if ($arret_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "Un id d'arrêt est requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_parents_lib.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli->begin_transaction();
try {
  arret_detacher($mysqli, $arret_id);
  $mysqli->commit();
  echo json_encode(["success" => true]);
} catch (ArretFusionException $e) {
  $mysqli->rollback();
  http_response_code($e->http);
  echo json_encode(["error" => $e->getMessage()]);
} catch (Throwable $e) {
  $mysqli->rollback();
  error_log('[detach_arret] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(["error" => "Détachement impossible"]);
}
