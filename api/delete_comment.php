<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// get attributes falaise_id, site_url, site_id, site, site_name from the POST request body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}
// decode body in application/json
$input = json_decode(file_get_contents('php://input'), true);
$commentaire_id = trim($input['commentaire_id'] ?? '');
$email = trim($input['email'] ?? '');

if (empty($commentaire_id) || empty($email)) {
  http_response_code(400);
  die(json_encode(["error" => "Missing required field."]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
// Get existing comment
$stmt = $mysqli->prepare(
  "SELECT email FROM commentaires_falaises WHERE id = ?"
);
$stmt->bind_param("i", $commentaire_id);
$stmt->execute();
$result = $stmt->get_result();
$existing_comment = $result->fetch_assoc();
$stmt->close();
if (empty($existing_comment)) {
  http_response_code(404);
  die(json_encode(["error" => "Commentaire introuvable."]));
}
if ($existing_comment['email'] !== $email) {
  http_response_code(403);
  die(json_encode(["error" => "Vous n'avez pas le droit de supprimer ce commentaire."]));
}
// Delete comment
$stmt = $mysqli->prepare(
  "DELETE FROM commentaires_falaises WHERE id = ?"
);
if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Problème de préparation de la requête : " . $mysqli->error]));
}
$stmt->bind_param("i", $commentaire_id);
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
// Check if the delete was successful
if ($stmt->affected_rows === 0) {
  http_response_code(500);
  die(json_encode(["error" => "Aucune ligne supprimée."]));
}
$stmt->close();

//Store in log
$stmt = $mysqli->prepare(
  "INSERT INTO edit_logs (type, collection, record_id, author, author_email) VALUES ('delete', 'commentaires_falaises', ?, ?, ?)"
);
if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Problème de préparation de la requête : " . $mysqli->error]));
}
$record_id = $commentaire_id;
$author_email = $email;
$author = $email;
$stmt->bind_param("ssi", $record_id, $author, $author_email);
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
$stmt->close();
$mysqli->close();
echo json_encode(['success' => true]);