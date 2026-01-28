<?php
// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$input = $_POST;

// Required fields
$sortie_id = !empty($input['sortie_id']) ? intval($input['sortie_id']) : null;
$edit_token = trim($input['edit_token'] ?? '');
$organisateur_nom = trim($input['organisateur_nom'] ?? '');
$organisateur_email = trim($input['organisateur_email'] ?? '');
$ville_depart = trim($input['ville_depart'] ?? '');
$falaise_principale_nom = trim($input['falaise_principale_nom'] ?? '');
$lien_groupe = trim($input['lien_groupe'] ?? '');
$description = trim($input['description'] ?? '');
$date_debut = trim($input['date_debut'] ?? '');

// Optional fields
$ville_id = !empty($input['ville_id']) ? intval($input['ville_id']) : null;
$falaise_principale_id = !empty($input['falaise_principale_id']) ? intval($input['falaise_principale_id']) : null;
$falaises_alternatives = trim($input['falaises_alternatives'] ?? '[]');
$velo_nom = trim($input['velo_nom'] ?? '');
$velo_id = !empty($input['velo_id']) ? intval($input['velo_id']) : null;
$date_fin = trim($input['date_fin'] ?? '');

// Validation
if ($sortie_id === null || empty($edit_token) || empty($organisateur_nom) ||
    empty($organisateur_email) || empty($ville_depart) || empty($falaise_principale_nom) ||
    empty($description) || empty($date_debut)) {
  http_response_code(400);
  die(json_encode(["error" => "Champs obligatoires manquants"]));
}

// Validate email
if (!filter_var($organisateur_email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  die(json_encode(["error" => "Email invalide"]));
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_debut)) {
  http_response_code(400);
  die(json_encode(["error" => "Format de date invalide pour date_debut"]));
}

if (!empty($date_fin) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_fin)) {
  http_response_code(400);
  die(json_encode(["error" => "Format de date invalide pour date_fin"]));
}

// Validate JSON for falaises_alternatives
$falaises_alt_decoded = json_decode($falaises_alternatives, true);
if (json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(400);
  die(json_encode(["error" => "Format JSON invalide pour falaises_alternatives"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Verify edit token
$stmt = $mysqli->prepare("SELECT * FROM sorties WHERE sortie_id = ? AND edit_token = ?");
$stmt->bind_param("is", $sortie_id, $edit_token);
$stmt->execute();
$sortie = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sortie) {
  http_response_code(403);
  die(json_encode(["error" => "Token invalide ou sortie non trouvée"]));
}

// Store old values for logging
$old_values = [
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

// Update sortie
$date_fin_value = !empty($date_fin) ? $date_fin : null;

$stmt = $mysqli->prepare(
  "UPDATE sorties SET
    organisateur_nom = ?,
    organisateur_email = ?,
    ville_depart = ?,
    ville_id = ?,
    falaise_principale_nom = ?,
    falaise_principale_id = ?,
    falaises_alternatives = ?,
    velo_nom = ?,
    velo_id = ?,
    lien_groupe = ?,
    description = ?,
    date_debut = ?,
    date_fin = ?
  WHERE sortie_id = ? AND edit_token = ?"
);

if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur de préparation de la requête : " . $mysqli->error]));
}

$stmt->bind_param(
  "sssissssissssis",
  $organisateur_nom,
  $organisateur_email,
  $ville_depart,
  $ville_id,
  $falaise_principale_nom,
  $falaise_principale_id,
  $falaises_alternatives,
  $velo_nom,
  $velo_id,
  $lien_groupe,
  $description,
  $date_debut,
  $date_fin_value,
  $sortie_id,
  $edit_token
);

if (!$stmt->execute()) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'exécution : " . $stmt->error]));
}

$stmt->close();

// Log the changes
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$new_values = [
  "organisateur_nom" => $organisateur_nom,
  "organisateur_email" => $organisateur_email,
  "ville_depart" => $ville_depart,
  "ville_id" => $ville_id,
  "falaise_principale_nom" => $falaise_principale_nom,
  "falaise_principale_id" => $falaise_principale_id,
  "falaises_alternatives" => $falaises_alternatives,
  "velo_nom" => $velo_nom,
  "velo_id" => $velo_id,
  "lien_groupe" => $lien_groupe,
  "description" => $description,
  "date_debut" => $date_debut,
  "date_fin" => $date_fin_value
];

logChanges(
  $organisateur_nom,
  $organisateur_email,
  'update',
  'sorties',
  $sortie_id,
  $falaise_principale_id,
  $new_values,
  $old_values
);

// Success response
echo json_encode([
  'success' => true,
  'sortie_id' => $sortie_id
]);
