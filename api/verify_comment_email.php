<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// get attributes falaise_id, site_url, site_id, site, site_name from the POST request body
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}
$commentaire_id = trim($_GET['commentaire_id'] ?? '');
$email = trim($_GET['email'] ?? '');
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
  die(json_encode(["error" => "Email ne correspond pas."]));
}
echo json_encode(['success' => true]);