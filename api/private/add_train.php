<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Get Authorization header
$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if (!$authHeader) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => "Authorization header not found"]);
  die();
}
if ($authHeader !== 'Bearer ' . $config["admin_token"]) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => "Invalid token"]);
  die();
}
// get attributes falaise_id, site_url, site_id, site, site_name from the POST request body
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => "Method not allowed"]);
  die();
}
$input = json_decode(file_get_contents('php://input'), true);

$email = $config["contact_mail"];
$message = "";
$nom_prenom = trim($input['user'] ?? '');
$train_contrib = trim("'" . $nom_prenom . "','" . $email . "'");
$train_public = 1; //train_public always 1 for admin insert
$train_tgv = isset($input['train_tgv']) && $input['train_tgv'] !== '' ? (int) $input['train_tgv'] : 0;

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Ensure not a duplicate
$stmt = $mysqli->prepare("SELECT train_id FROM train WHERE ville_id = ? AND gare_id = ? AND train_tgv = ?");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $mysqli->error]);
  die();
}
$stmt->bind_param("iii", $input['ville_id'], $input['gare_id'], $train_tgv);
$stmt->execute();
$res = $stmt->get_result();
$train = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($train) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => "Un itinéraire " . ($train_tgv ? "TGV" : "TER") . " existe déjà entre cette ville et cette gare."
  ]);
  die();
}

$stmt = $mysqli->prepare("INSERT INTO train
        (ville_id, gare_id, train_temps, train_correspmin, train_correspmax, train_public,
        train_descr, train_depart, train_arrivee, train_contrib, train_tgv)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $mysqli->error]);
  die();
}

// Bind des paramètres avec les valeurs, les valeurs null sont gérées comme NULL dans la base de données
$stmt->bind_param(
  "iiiiiissssi",
  $input['ville_id'],
  $input['gare_id'],
  $input['train_temps'],
  $input['train_correspmin'],
  $input['train_correspmax'],
  $train_public,
  $input['train_descr'],
  $input['train_depart'],
  $input['train_arrivee'],
  $train_contrib,
  $train_tgv
);
if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $stmt->error]);
  die();
}

$stmt->close();

//Store in log
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$new_comment = $input;
$collection = 'train';
$type = 'insert';
$record_id = $mysqli->insert_id;
logChanges(
  $nom_prenom,
  $email,
  $type,
  $collection,
  $record_id,
  null,
  $new_comment
);

echo json_encode(['success' => true]);
exit;
