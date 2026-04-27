<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit;
}

$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if (!$authHeader || $authHeader !== 'Bearer ' . $config['admin_token']) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);

if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'id is required']);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$stmt = $mysqli->prepare("DELETE FROM pages WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

echo json_encode(['success' => true]);
