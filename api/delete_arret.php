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
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';

// Suppression logique (colonne `deleted`). Un parent est supprimé avec ses enfants, et les accès falaise de
// l'arrêt et de ses enfants sont effacés : uniquement si `cascade: true` (confirmation côté admin), sinon 409.
// Le dernier enfant d'un parent ne se supprime pas : défusionner le parent à la place.
$cascade = ($input['cascade'] ?? false) === true;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli->begin_transaction();
try {
  $r = arret_supprimer($mysqli, $arret_id, $cascade);
  $mysqli->commit();
  echo json_encode(["success" => true] + $r);
} catch (ArretFusionException $e) {
  $mysqli->rollback();
  http_response_code($e->http);
  echo json_encode(["error" => $e->getMessage()]);
} catch (Throwable $e) {
  $mysqli->rollback();
  error_log('[delete_arret] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(["error" => "Erreur serveur"]);
}
