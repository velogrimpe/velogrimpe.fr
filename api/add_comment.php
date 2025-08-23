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

require_once "../database/velogrimpe.php";
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
$stmt = $mysqli->prepare(
  "INSERT INTO edit_logs (type, collection, record_id, author, author_email, changes) VALUES ('insert', ?, ?, ?, ?, ?)"
);
if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Problème de préparation de la requête : " . $mysqli->error]));
}
$table = 'commentaires_falaises';
$record_id = $mysqli->insert_id;
$author = $nom;
$author_email = $email;
$changes = json_encode([
  'falaise_id' => $falaise_id,
  'velo_id' => $velo_id,
  'commentaire' => $commentaire,
  'nom' => $nom,
  'email' => $email,
  'ville_nom' => $ville_nom,
  'gare_depart' => $gare_depart,
  'gare_arrivee' => $gare_arrivee,
]);
$stmt->bind_param("sisss", $table, $record_id, $author, $author_email, $changes);
if (!$stmt) {
  die("Problème de liaison des paramètres : " . $mysqli->error);
}
// Execute the statement
$stmt->execute();
if ($stmt->error) {
  die("Erreur lors de l'exécution de la requête : " . $stmt->error);
}
$stmt->close();

echo json_encode(['success' => true]);

// send mail to admin
$to = $config['contact_mail'];
$subject = "Nouveau commentaire de $nom sur la falaise ID $falaise_id";
$message = "Un nouveau commentaire a été ajouté à la falaise ID $falaise_id.\n\n" .
  "Nom: $nom\n" .
  "Email: $email\n" .
  "Commentaire: $commentaire\n\n" .
  "<a href='https://velogrimpe.fr/falaise.php?falaise_id=$falaise_id#commentaires'>Voir les commentaires</a>";
$headers = "From: no-reply@velogrimpe.fr\r\n" .
  "X-Mailer: PHP/" . phpversion();
mail($to, $subject, $message, $headers);