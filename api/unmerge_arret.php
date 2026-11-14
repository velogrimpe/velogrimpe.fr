<?php
/**
 * Défusionne un parent (admin) : ses enfants redeviennent orphelins, le parent est supprimé logiquement.
 * POST JSON { "parent_id": int, "heritier_id"?: int }, en-tête `Authorization: Bearer <admin_token>`.
 * Accès et trajets train du parent reviennent à `heritier_id` (un de ses enfants ; déduit s'il n'y a qu'un
 * enfant ; 400 s'il manque alors que le parent porte des accès ou des trajets). 409 si l'arrêt n'est pas un parent.
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
$parent_id = (int) ($input['parent_id'] ?? 0);
$heritier_id = isset($input['heritier_id']) && $input['heritier_id'] !== null ? (int) $input['heritier_id'] : null;
if ($parent_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "Un id de parent est requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_parents_lib.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli->begin_transaction();
try {
  arret_defusionner($mysqli, $parent_id, $heritier_id);
  $mysqli->commit();
  echo json_encode(["success" => true]);
} catch (ArretFusionException $e) {
  $mysqli->rollback();
  http_response_code($e->http);
  echo json_encode(["error" => $e->getMessage()]);
} catch (Throwable $e) {
  $mysqli->rollback();
  error_log('[unmerge_arret] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(["error" => "Défusion impossible"]);
}
