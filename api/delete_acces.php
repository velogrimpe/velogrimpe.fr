<?php
/**
 * POST /api/delete_acces.php   (Authorization: Bearer <admin_token>, corps JSON { "acces_id": 123 })
 *
 * Supprime DÉFINITIVEMENT un accès arrêt → falaise (`acces_falaise`, vélo ou à pied) et son éventuelle
 * trace GPX. La ligne part d'abord (journalisée dans edit_logs avec ses valeurs en `old`), le fichier
 * ensuite : un échec sur le fichier laisse un GPX orphelin, jamais un accès sans trace.
 * Les versions archivées (gpx-historique/) sont conservées.
 * Réponse : { success, acces_id, gpx_supprime }.
 */
header('Content-Type: application/json');
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

// Jeton admin en Bearer, comparé à temps constant ; un jeton de config vide n'authentifie jamais personne.
$headers = getallheaders();
$authHeader = (string) ($headers['authorization'] ?? $headers['Authorization'] ?? '');
$adminToken = (string) ($config["admin_token"] ?? '');
if ($adminToken === '' || !hash_equals('Bearer ' . $adminToken, $authHeader)) {
  http_response_code(401);
  die(json_encode(["error" => "Non autorisé"]));
}

$input = json_decode(file_get_contents('php://input'), true);
$acces_id = (int) ($input['acces_id'] ?? 0);
if ($acces_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "Un id d'accès est requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $mysqli->begin_transaction();
  $stmt = $mysqli->prepare("SELECT * FROM acces_falaise WHERE acces_id = ? FOR UPDATE");
  $stmt->bind_param('i', $acces_id);
  $stmt->execute();
  $old = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$old) {
    $mysqli->rollback();
    http_response_code(404);
    die(json_encode(["error" => "Accès introuvable"]));
  }

  $stmt = $mysqli->prepare("DELETE FROM acces_falaise WHERE acces_id = ?");
  $stmt->bind_param('i', $acces_id);
  $stmt->execute();
  $stmt->close();

  $falaise_id = (int) $old['falaise_id'];
  logChanges("Admin", "", "delete", "acces_falaise", $acces_id, $falaise_id, array_fill_keys(array_keys($old), ''), $old, $mysqli);
  $mysqli->commit();
} catch (Throwable $e) {
  try { $mysqli->rollback(); } catch (Throwable $_) {}
  error_log('[delete_acces] ' . $e->getMessage());
  http_response_code(500);
  die(json_encode(["error" => "Erreur serveur"]));
}

// Trace GPX (même nommage que partout : velo_gpx_rel). Absente pour la plupart des accès à pied.
$gpx_supprime = false;
$rel = velo_gpx_rel($acces_id, (string) $old['depart_slug'], (string) $old['arrivee_slug'], (string) $old['variante_formate']);
if (vg_data_exists($rel)) {
  $gpx_supprime = @unlink(vg_data_path($rel));
  if (!$gpx_supprime) error_log("[delete_acces] GPX non supprimé : $rel");
}

echo json_encode(["success" => true, "acces_id" => $acces_id, "gpx_supprime" => $gpx_supprime]);
