<?php
header('Content-Type: application/json');
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if ($authHeader !== 'Bearer ' . $config["admin_token"]) {
  http_response_code(401);
  die(json_encode(["error" => "Non autorisé"]));
}

$input = json_decode(file_get_contents('php://input'), true);
$ligne_id = (int) ($input['ligne_id'] ?? 0);
if ($ligne_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "Un id de ligne est requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// arrets_lignes_transport est en ON DELETE CASCADE ; les arrêts ne sont pas touchés.
$stmt = $mysqli->prepare("DELETE FROM lignes_transport WHERE id = ?");
$stmt->bind_param("i", $ligne_id);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();

if ($deleted === 0) {
  http_response_code(404);
  die(json_encode(["error" => "Ligne introuvable"]));
}
echo json_encode(["success" => true]);
