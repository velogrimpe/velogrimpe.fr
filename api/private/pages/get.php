<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if (!$authHeader || $authHeader !== 'Bearer ' . $config['admin_token']) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'id is required']);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$stmt = $mysqli->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
  http_response_code(404);
  echo json_encode(['error' => 'Not found']);
  exit;
}

$page['sections'] = json_decode($page['sections'], true);

echo json_encode($page);
