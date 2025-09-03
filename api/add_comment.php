<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// get attributes falaise_id, site_url, site_id, site, site_name from the POST request body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}
$input = $_POST;

// Required
$falaise_id = trim($input['falaise_id'] ?? '');
$velo_id = trim($input['velo_id'] ?? '');
$commentaire = trim($input['commentaire'] ?? '');
$nom = trim($input['nom'] ?? '');
$email = trim($input['email'] ?? '');
// Optional
$ville_nom = trim($input['ville_nom'] ?? '');
$gare_depart = trim($input['gare_depart'] ?? '');
$gare_arrivee = trim($input['gare_arrivee'] ?? '');
if (empty($falaise_id) || empty($commentaire) || empty($nom) || empty($email)) {
  http_response_code(400);
  die(json_encode(["error" => "Missing required field. $falaise_id, $commentaire, $nom, $email"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
$stmt = $mysqli->prepare(
  "INSERT INTO commentaires_falaises
    (falaise_id, velo_id, commentaire, nom, email, ville_nom, gare_depart, gare_arrivee)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Problème de préparation de la requête : " . $mysqli->error]));
}
$stmt->bind_param("iissssss", $falaise_id, $velo_id, $commentaire, $nom, $email, $ville_nom, $gare_depart, $gare_arrivee);
if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Problème de liaison des paramètres : " . $mysqli->error]));
}
// Execute the statement
$stmt->execute();
if ($stmt->error) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'exécution de la requête : " . $stmt->error]));
}
// Check if the insert was successful
if ($stmt->affected_rows === 0) {
  http_response_code(500);
  die(json_encode(["error" => "Aucune ligne insérée."]));
}
$stmt->close();

//Store in log
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$new_comment = [
  "falaise_id" => $falaise_id,
  "velo_id" => $velo_id,
  "commentaire" => $commentaire,
  "nom" => $nom,
  "email" => $email,
  "ville_nom" => $ville_nom,
  "gare_depart" => $gare_depart,
  "gare_arrivee" => $gare_arrivee
];
$collection = 'commentaires_falaises';
$type = 'insert';
$record_id = $mysqli->insert_id;
logChanges(
  $nom,
  $email,
  $type,
  $collection,
  $record_id,
  $falaise_id,
  $new_comment
);

echo json_encode(['success' => true]);

// get falaise nom from db
$falaise_nom = '';
$stmt = $mysqli->prepare("SELECT falaise_nom FROM falaises WHERE falaise_id = ?");
$stmt->bind_param("i", $falaise_id);
$stmt->execute();
$stmt->bind_result($falaise_nom);
$stmt->fetch();
$stmt->close();

// send mail to admin
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
$to = $config["contact_mail"];
$subject = "Nouveau commentaire de $nom sur la falaise $falaise_nom (ID: $falaise_id)";
$html = "<html><body>";
$html .= "<h1>Nouveau commentaire sur $falaise_nom</h1>";
$html .= "<ul>";
$html .= "<li><strong>Nom:</strong> $nom</li>";
$html .= "<li><strong>Email:</strong> <a href='mailto:$email'>$email</a></li>";
$html .= "<li><strong>Commentaire:</strong> " . htmlspecialchars(nl2br(trim($commentaire))) . "</li>";
$html .= "<li><a href='https://velogrimpe.fr/falaise.php?falaise_id=$falaise_id#commentaires'>Voir les commentaires</a></li>";
$html .= "</ul>";
$html .= "</body></html>";

$data = [
  'to' => $to,
  'subject' => $subject,
  'html' => $html,
  'h:Reply-To' => $email
];

sendMail($data);