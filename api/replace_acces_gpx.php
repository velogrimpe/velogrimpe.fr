<?php
/**
 * POST /api/replace_acces_gpx.php   (Authorization: Bearer <admin_token>, multipart : acces_id + gpx_file)
 *
 * Remplace la trace GPX d'un accès (ou l'ajoute s'il n'en avait pas). Même traitement que les formulaires
 * (velo_lire_gpx_upload : taille max, XML valide, racine <gpx>, waypoints retirés). L'ancienne trace est
 * archivée dans gpx-historique/. Les km / D+ / D- de l'accès ne sont PAS recalculés.
 * Réponse : { success, acces_id, gpx: 'remplacée' | 'ajoutée' }. 422 si le GPX est invalide.
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

$acces_id = (int) ($_POST['acces_id'] ?? 0);
if ($acces_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "Un id d'accès est requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';

$stmt = $mysqli->prepare("SELECT acces_id, falaise_id, depart_slug, arrivee_slug, variante_formate FROM acces_falaise WHERE acces_id = ?");
$stmt->bind_param('i', $acces_id);
$stmt->execute();
$acces = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$acces) {
  http_response_code(404);
  die(json_encode(["error" => "Accès introuvable"]));
}

try {
  $dom = velo_lire_gpx_upload('gpx_file');
} catch (InvalidArgumentException $e) {
  http_response_code(422);
  die(json_encode(["error" => $e->getMessage()]));
}
if ($dom === null) {
  http_response_code(400);
  die(json_encode(["error" => "Un fichier GPX (gpx_file) est requis"]));
}

try {
  vg_data_prepare(VELO_GPX_DIR);
  vg_data_prepare(VELO_GPX_ARCHIVE_DIR);
} catch (VgDataException $e) {
  error_log('[replace_acces_gpx] ' . $e->getMessage());
  http_response_code(500);
  die(json_encode(["error" => "Dossier des traces GPX indisponible"]));
}

$rel = velo_gpx_rel($acces_id, (string) $acces['depart_slug'], (string) $acces['arrivee_slug'], (string) $acces['variante_formate']);
$existait = vg_data_exists($rel);
if ($existait && velo_archiver_gpx($rel) === null) {
  // Pas d'écrasement sans sauvegarde de l'ancienne trace.
  error_log("[replace_acces_gpx] archivage impossible : $rel");
  http_response_code(500);
  die(json_encode(["error" => "Archivage de l'ancienne trace impossible, rien n'a été modifié"]));
}
if ($dom->save(vg_data_path($rel)) === false) {
  error_log("[replace_acces_gpx] écriture impossible : $rel");
  http_response_code(500);
  die(json_encode(["error" => "Écriture du GPX impossible"]));
}

$statut = $existait ? 'remplacée' : 'ajoutée';
$mysqli->query("UPDATE acces_falaise SET date_modification = CURRENT_TIMESTAMP WHERE acces_id = $acces_id");
logChanges("Admin", "", "update", "acces_falaise", $acces_id, (int) $acces['falaise_id'], ["gpx_file" => $statut], ["gpx_file" => 'inchangée']);

echo json_encode(["success" => true, "acces_id" => $acces_id, "gpx" => $statut]);
