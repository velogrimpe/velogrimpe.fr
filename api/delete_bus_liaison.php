<?php
header('Content-Type: application/json');
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  die(json_encode(["error" => "Corps de requête JSON invalide"]));
}

$liaison_id = isset($input['liaison_id']) && $input['liaison_id'] !== '' ? (int) $input['liaison_id'] : null;
$nom_prenom = trim($input['nom_prenom'] ?? '');
$email = trim($input['email'] ?? '');

if (!$liaison_id) {
  http_response_code(400);
  die(json_encode(["error" => "Un id de liaison est requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Récupération de la liaison existante (pour le log)
$stmt = $mysqli->prepare(
  "SELECT arret_1_id, arret_2_id, ligne_id, description FROM bus_liaisons WHERE id = ?"
);
$stmt->bind_param("i", $liaison_id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$old) {
  http_response_code(404);
  die(json_encode(["error" => "Liaison introuvable"]));
}

$stmt = $mysqli->prepare("DELETE FROM bus_liaisons WHERE id = ?");
$stmt->bind_param("i", $liaison_id);
$stmt->execute();
$stmt->close();

// Log de la suppression
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
logChanges(
  $nom_prenom !== '' ? $nom_prenom : 'inconnu',
  $email,
  'delete',
  'bus_liaisons',
  $liaison_id,
  null,
  [], // pas de nouvelles valeurs
  [
    "arret_1_id" => $old["arret_1_id"],
    "arret_2_id" => $old["arret_2_id"],
    "ligne_id" => $old["ligne_id"],
    "description" => $old["description"],
  ]
);

echo json_encode(["success" => true]);
