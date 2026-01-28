<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$input = $_POST;

// Required fields
$sortie_id = !empty($input['sortie_id']) ? intval($input['sortie_id']) : null;
$participant_nom = trim($input['participant_nom'] ?? '');
$participant_email = trim($input['participant_email'] ?? '');
$preferences_contact = trim($input['preferences_contact'] ?? ''); // JSON string

// Optional fields
$participant_telephone = trim($input['participant_telephone'] ?? '');
$message = trim($input['message'] ?? '');

// Validation
if ($sortie_id === null || empty($participant_nom) || empty($participant_email) || empty($preferences_contact)) {
  http_response_code(400);
  die(json_encode(["error" => "Champs obligatoires manquants"]));
}

// Validate email
if (!filter_var($participant_email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  die(json_encode(["error" => "Email invalide"]));
}

// Validate JSON for preferences_contact
$preferences_decoded = json_decode($preferences_contact, true);
if (json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(400);
  die(json_encode(["error" => "Format JSON invalide pour preferences_contact"]));
}

// Generate validation token
$validation_token = bin2hex(random_bytes(32)); // 64 chars

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Check if sortie exists
$stmt = $mysqli->prepare("SELECT * FROM sorties WHERE sortie_id = ? AND sortie_public = 1");
$stmt->bind_param("i", $sortie_id);
$stmt->execute();
$sortie = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sortie) {
  http_response_code(404);
  die(json_encode(["error" => "Sortie non trouvée"]));
}

// Check if sortie is in the past
if (strtotime($sortie['date_debut']) < strtotime('today')) {
  http_response_code(400);
  die(json_encode(["error" => "Cette sortie est déjà passée"]));
}

// Insert participation request
$stmt = $mysqli->prepare(
  "INSERT INTO participation_requests
    (sortie_id, participant_nom, participant_email, participant_telephone,
     preferences_contact, message, request_status, validation_token)
  VALUES (?, ?, ?, ?, ?, ?, 2, ?)"
);

if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur de préparation de la requête : " . $mysqli->error]));
}

$stmt->bind_param(
  "issssss",
  $sortie_id,
  $participant_nom,
  $participant_email,
  $participant_telephone,
  $preferences_contact,
  $message,
  $validation_token
);

if (!$stmt->execute()) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'exécution : " . $stmt->error]));
}

if ($stmt->affected_rows === 0) {
  http_response_code(500);
  die(json_encode(["error" => "Aucune ligne insérée"]));
}

$request_id = $mysqli->insert_id;
$stmt->close();

// Update nb_interesses count
$mysqli->query("UPDATE sorties SET nb_interesses = nb_interesses + 1 WHERE sortie_id = $sortie_id");

// Success response
echo json_encode([
  'success' => true,
  'request_id' => $request_id
]);

// Send email to admin
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';

$date_display = date('d/m/Y', strtotime($sortie['date_debut']));
if (!empty($sortie['date_fin'])) {
  $date_display .= ' au ' . date('d/m/Y', strtotime($sortie['date_fin']));
}

// Build contact preferences display
$prefs = [];
if ($preferences_decoded['signal']) $prefs[] = 'Signal';
if ($preferences_decoded['whatsapp']) $prefs[] = 'WhatsApp';
if ($preferences_decoded['email']) $prefs[] = 'Email';
if ($preferences_decoded['telephone']) $prefs[] = 'Téléphone';
$prefs_display = implode(', ', $prefs);

$validate_url = "https://velogrimpe.fr/api/private/validate_participation.php?request_id=$request_id&token=$validation_token";
$reject_url = "https://velogrimpe.fr/api/private/reject_participation.php?request_id=$request_id&token=$validation_token";

$admin_subject = "Nouvelle demande de participation : {$sortie['falaise_principale_nom']} ($date_display)";
$admin_html = "<html><body>";
$admin_html .= "<h1>Nouvelle demande de participation</h1>";
$admin_html .= "<h2>Sortie</h2>";
$admin_html .= "<ul>";
$admin_html .= "<li><strong>Falaise :</strong> {$sortie['falaise_principale_nom']}</li>";
$admin_html .= "<li><strong>Date :</strong> $date_display</li>";
$admin_html .= "<li><strong>Organisateur :</strong> {$sortie['organisateur_nom']} (<a href='mailto:{$sortie['organisateur_email']}'>{$sortie['organisateur_email']}</a>)</li>";
$admin_html .= "</ul>";
$admin_html .= "<h2>Participant</h2>";
$admin_html .= "<ul>";
$admin_html .= "<li><strong>Nom :</strong> $participant_nom</li>";
$admin_html .= "<li><strong>Email :</strong> <a href='mailto:$participant_email'>$participant_email</a></li>";
if (!empty($participant_telephone)) {
  $admin_html .= "<li><strong>Téléphone :</strong> $participant_telephone</li>";
}
$admin_html .= "<li><strong>Moyens de contact préférés :</strong> $prefs_display</li>";
$admin_html .= "</ul>";
if (!empty($message)) {
  $admin_html .= "<p><strong>Message :</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";
}
$admin_html .= "<hr>";
$admin_html .= "<p><strong>Actions :</strong></p>";
$admin_html .= "<p><a href=\"$validate_url\" style=\"background: green; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;\">Valider et transférer à l'organisateur</a></p>";
$admin_html .= "<p><a href=\"$reject_url\" style=\"background: red; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;\">Rejeter</a></p>";
$admin_html .= "</body></html>";

sendMail([
  'to' => $config['contact_mail'],
  'subject' => $admin_subject,
  'html' => $admin_html,
  'h:Reply-To' => $participant_email
]);
