<?php
/**
 * Fusion de deux arrêts (admin) en un parent et ses enfants : plus rien n'est supprimé, les arrêts gardent leur
 * identité (références GTFS, lignes) et sont regroupés sous un parent (cf. lib/arrets_parents_lib.php).
 * POST JSON { "source_id": int, "cible_id": int }, en-tête `Authorization: Bearer <admin_token>`.
 *
 * Deux orphelins → nouveau parent (nom et attributs de `cible_id`, position au barycentre). Un parent et un
 * orphelin → l'orphelin rejoint le parent. Deux parents → `cible_id` absorbe les enfants de `source_id`.
 * Accès aux falaises et trajets train passent au parent. Refus 409 : arrêt enfant ou supprimé, modes de transport
 * sans famille commune, accès identique (même falaise, type et variante) avec la liste en cause.
 * Réponse : { success, parent_id, acces_deplaces }.
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
$source = (int) ($input['source_id'] ?? 0);
$cible = (int) ($input['cible_id'] ?? 0);
if ($source <= 0 || $cible <= 0 || $source === $cible) {
  http_response_code(400);
  die(json_encode(["error" => "Deux arrêts distincts sont requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_parents_lib.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli->begin_transaction();
try {
  $res = arret_fusionner($mysqli, $source, $cible);
  $mysqli->commit();
  echo json_encode(["success" => true] + $res);
} catch (ArretFusionException $e) {
  $mysqli->rollback();
  http_response_code($e->http);
  echo json_encode(["error" => $e->getMessage()]);
} catch (Throwable $e) {
  $mysqli->rollback();
  error_log('[merge_arrets] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(["error" => "Fusion impossible"]);
}
