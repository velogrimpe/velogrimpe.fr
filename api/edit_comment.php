<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// get attributes falaise_id, site_url, site_id, site, site_name from the POST request body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}
$input = $_POST;

// Required
$commentaire_id = trim($input['commentaire_id'] ?? '');
$falaise_id = trim($input['falaise_id'] ?? '');
$velo_id = trim($input['velo_id'] ?? null);
$commentaire = trim($input['commentaire'] ?? '');
$nom = trim($input['nom'] ?? '');
$email = trim($input['email'] ?? '');
// Optional
$ville_nom = trim($input['ville_nom'] ?? '');
$gare_depart = trim($input['gare_depart'] ?? '');
$gare_arrivee = trim($input['gare_arrivee'] ?? '');
if (empty($falaise_id) || empty($commentaire) || empty($nom) || empty($commentaire_id) || empty($email)) {
  // set status code 400
  http_response_code(400);
  die(json_encode(["error" => "Missing required field: $falaise_id, $commentaire, $nom, $commentaire_id, $email"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Get existing comment
$stmt = $mysqli->prepare(
  "SELECT falaise_id, velo_id, commentaire, nom, email, ville_nom, gare_depart, gare_arrivee FROM commentaires_falaises WHERE id = ?"
);
if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Problème de préparation de la requête : " . $mysqli->error]));
}
$stmt->bind_param("i", $commentaire_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  http_response_code(404);
  die(json_encode(["error" => "Commentaire introuvable."]));
}
$existing_comment = $result->fetch_assoc();
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
$type = 'update';
$record_id = $commentaire_id;
logChanges(
  $nom,
  $email,
  $type,
  $collection,
  $record_id,
  $falaise_id,
  $new_comment,
  $existing_comment
);

$stmt = $mysqli->prepare(
  "UPDATE commentaires_falaises
    SET
      falaise_id = ?,
      velo_id = ?,
      commentaire = ?,
      nom = ?,
      email = ?,
      ville_nom = ?,
      gare_depart = ?,
      gare_arrivee = ?,
      date_modification = NOW()
    WHERE id = ?"
);
if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Problème de préparation de la requête : " . $mysqli->error]));
}
$stmt->bind_param(
  "iisssssss",
  $falaise_id,
  $velo_id,
  $commentaire,
  $nom,
  $email,
  $ville_nom,
  $gare_depart,
  $gare_arrivee,
  $commentaire_id,
);
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
echo json_encode(['success' => true]);