<?php
// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$input = $_POST;

$sortie_id = !empty($input['sortie_id']) ? intval($input['sortie_id']) : null;
$edit_token = trim($input['edit_token'] ?? '');

if ($sortie_id === null || empty($edit_token)) {
  http_response_code(400);
  die(json_encode(["error" => "Paramètres manquants"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Verify edit token
$stmt = $mysqli->prepare(
  "SELECT * FROM sorties WHERE sortie_id = ? AND edit_token = ?"
);
$stmt->bind_param("is", $sortie_id, $edit_token);
$stmt->execute();
$result = $stmt->get_result();
$sortie = $result->fetch_assoc();
$stmt->close();

if (!$sortie) {
  http_response_code(403);
  die(json_encode(["error" => "Token invalide ou sortie non trouvée"]));
}

// Delete sortie (cascade will delete participation_requests)
$stmt = $mysqli->prepare("DELETE FROM sorties WHERE sortie_id = ?");
$stmt->bind_param("i", $sortie_id);
$stmt->execute();
$stmt->close();

// Success response
echo json_encode([
  'success' => true,
  'message' => 'Sortie supprimée avec succès'
]);
