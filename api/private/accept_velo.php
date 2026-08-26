<?php
/**
 * GET /api/private/accept_velo.php?admin=<token>&velo_id=123
 *
 * Valide un itinéraire vélo (velo_public = 1). Appelé depuis le lien du mail
 * envoyé aux admins par add_velo.php / edit_velo.php — même principe que
 * accept_falaise.php. 401 sans token, 403 token invalide (D006).
 */
header('Content-Type: application/json; charset=utf-8');
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$adminParam = $_GET['admin'] ?? null;
if ($adminParam === null || $adminParam === '') {
  http_response_code(401);
  header('WWW-Authenticate: Bearer realm="velogrimpe-admin"');
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}
if (!hash_equals((string) $config['admin_token'], (string) $adminParam)) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$velo_id = isset($_GET['velo_id']) ? intval($_GET['velo_id']) : 0;
if ($velo_id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing velo_id parameter']);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
$stmt = $mysqli->prepare("SELECT velo_id, falaise_id, velo_public FROM velo WHERE velo_id = ?");
$stmt->bind_param('i', $velo_id);
$stmt->execute();
$velo = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$velo) {
  http_response_code(404);
  echo json_encode(['error' => 'Itinéraire introuvable']);
  exit;
}

$stmt = $mysqli->prepare("UPDATE velo SET velo_public = 1, date_modification = CURRENT_TIMESTAMP WHERE velo_id = ?");
$stmt->bind_param('i', $velo_id);
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['error' => 'Execute failed', 'details' => $stmt->error]);
  exit;
}
$stmt->close();

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
logChanges("admin", $config['contact_mail'], "update", "velo", $velo_id, $velo['falaise_id'], ["velo_public" => "Validé (1)"], ["velo_public" => $velo['velo_public']]);

echo json_encode(['success' => true, 'velo_id' => $velo_id, 'previous_velo_public' => intval($velo['velo_public'])]);
