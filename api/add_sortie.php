<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$input = $_POST;

// Required fields
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
$falaises_alternatives = trim($input['falaises_alternatives'] ?? '[]'); // JSON string
$velo_nom = trim($input['velo_nom'] ?? '');
$velo_id = !empty($input['velo_id']) ? intval($input['velo_id']) : null;
$date_fin = trim($input['date_fin'] ?? ''); // NULL if empty

// Validation
if (
  empty($organisateur_nom) || empty($organisateur_email) || empty($ville_depart) ||
  empty($falaise_principale_nom) || empty($description) || empty($date_debut)
) {
  http_response_code(400);
  die(json_encode(["error" => "Champs obligatoires manquants"]));
}

// Validate email
if (!filter_var($organisateur_email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  die(json_encode(["error" => "Email invalide"]));
}

// Validate date format (YYYY-MM-DD)
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

// Generate secure tokens
$edit_token = bin2hex(random_bytes(32)); // 64 chars
$delete_token = bin2hex(random_bytes(32)); // 64 chars

// Insert into database
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$stmt = $mysqli->prepare(
  "INSERT INTO sorties
    (organisateur_nom, organisateur_email, ville_depart, ville_id,
     falaise_principale_nom, falaise_principale_id, falaises_alternatives,
     velo_nom, velo_id, lien_groupe, description, date_debut, date_fin,
     sortie_public, nb_interesses, edit_token, delete_token)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?)"
);

if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur de préparation de la requête : " . $mysqli->error]));
}

// Prepare nullable date_fin
$date_fin_value = !empty($date_fin) ? $date_fin : null;

$stmt->bind_param(
  "sssissssissssss",
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
  $edit_token,
  $delete_token
);

if (!$stmt->execute()) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'exécution : " . $stmt->error]));
}

if ($stmt->affected_rows === 0) {
  http_response_code(500);
  die(json_encode(["error" => "Aucune ligne insérée"]));
}

$sortie_id = $mysqli->insert_id;
$stmt->close();

// Log the insertion
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$new_sortie = [
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
  'insert',
  'sorties',
  $sortie_id,
  $falaise_principale_id,
  $new_sortie
);

// Success response
echo json_encode([
  'success' => true,
  'sortie_id' => $sortie_id,
  'edit_token' => $edit_token
]);

// Send emails
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';

// 1. Email de confirmation à l'organisateur
$date_display = date('d/m/Y', strtotime($date_debut));
if (!empty($date_fin)) {
  $date_display .= ' au ' . date('d/m/Y', strtotime($date_fin));
}

$edit_url = "https://velogrimpe.fr/ajout/edit_sortie.php?sortie_id=$sortie_id&token=$edit_token";

$organizer_subject = "Votre sortie \"$falaise_principale_nom\" a été publiée sur Velogrimpe.fr";
$organizer_html = "<html><body>";
$organizer_html .= "<h1>Votre sortie a été publiée !</h1>";
$organizer_html .= "<p>Bonjour $organisateur_nom,</p>";
$organizer_html .= "<p>Votre sortie d'escalade a bien été publiée sur Velogrimpe.fr.</p>";
$organizer_html .= "<h2>Récapitulatif</h2>";
$organizer_html .= "<ul>";
$organizer_html .= "<li><strong>Falaise :</strong> $falaise_principale_nom</li>";
$organizer_html .= "<li><strong>Date :</strong> $date_display</li>";
$organizer_html .= "<li><strong>Départ depuis :</strong> $ville_depart</li>";
$organizer_html .= "<li><strong>Lien du groupe :</strong> <a href=\"$lien_groupe\">$lien_groupe</a></li>";
$organizer_html .= "</ul>";
$organizer_html .= "<p><strong>Description :</strong><br>" . nl2br(htmlspecialchars($description)) . "</p>";
$organizer_html .= "<hr>";
$organizer_html .= "<p><a href=\"$edit_url\">Modifier ou supprimer ma sortie</a></p>";
$organizer_html .= "<p><em>Conservez ce lien précieusement, il vous permettra de modifier votre sortie.</em></p>";
$organizer_html .= "</body></html>";

sendMail([
  'to' => $organisateur_email,
  'subject' => $organizer_subject,
  'html' => $organizer_html
]);

// 2. Notification admin (anti-spam)
$delete_url = "https://velogrimpe.fr/api/private/delete_sortie.php?sortie_id=$sortie_id&token=$delete_token";

$admin_subject = "🚵 Nouvelle sortie proposée : $falaise_principale_nom ($date_display)";
$admin_html = "<html><body>";
$admin_html .= "<h1>Nouvelle sortie publiée</h1>";
$admin_html .= "<ul>";
$admin_html .= "<li><strong>Organisateur :</strong> $organisateur_nom (<a href='mailto:$organisateur_email'>$organisateur_email</a>)</li>";
$admin_html .= "<li><strong>Falaise :</strong> $falaise_principale_nom</li>";
$admin_html .= "<li><strong>Date :</strong> $date_display</li>";
$admin_html .= "<li><strong>Ville de départ :</strong> $ville_depart</li>";
$admin_html .= "<li><strong>Lien du groupe :</strong> <a href=\"$lien_groupe\">$lien_groupe</a></li>";
$admin_html .= "</ul>";
$admin_html .= "<p><strong>Description :</strong><br>" . nl2br(htmlspecialchars($description)) . "</p>";
$admin_html .= "<hr>";
$admin_html .= "<p><strong>Action anti-spam :</strong></p>";
$admin_html .= "<p><a href=\"$delete_url\" style=\"color: red;\">Supprimer cette sortie (spam)</a></p>";
$admin_html .= "</body></html>";

sendMail([
  'to' => $config['contact_mail'],
  'subject' => $admin_subject,
  'html' => $admin_html,
  'h:Reply-To' => $organisateur_email
]);
