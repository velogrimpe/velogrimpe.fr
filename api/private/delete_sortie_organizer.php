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

// Log la suppression (valeurs supprimées conservées dans les changements)
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$old_sortie = [
  "organisateur_nom" => $sortie['organisateur_nom'],
  "organisateur_email" => $sortie['organisateur_email'],
  "ville_depart" => $sortie['ville_depart'],
  "ville_id" => $sortie['ville_id'],
  "falaise_principale_nom" => $sortie['falaise_principale_nom'],
  "falaise_principale_id" => $sortie['falaise_principale_id'],
  "falaises_alternatives" => $sortie['falaises_alternatives'],
  "velo_nom" => $sortie['velo_nom'],
  "velo_id" => $sortie['velo_id'],
  "lien_groupe" => $sortie['lien_groupe'],
  "description" => $sortie['description'],
  "date_debut" => $sortie['date_debut'],
  "date_fin" => $sortie['date_fin']
];
logChanges($sortie['organisateur_nom'], $sortie['organisateur_email'], 'delete', 'sorties', $sortie_id, $sortie['falaise_principale_id'] ?: null, array_fill_keys(array_keys($old_sortie), ''), $old_sortie);

// Success response
echo json_encode([
  'success' => true,
  'message' => 'Sortie supprimée avec succès'
]);
