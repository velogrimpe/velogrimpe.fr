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
if (!$input) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid JSON body']);
  exit;
}

$slug = trim($input['slug'] ?? '');
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$status = $input['status'] ?? 'draft';
$sections = json_encode($input['sections'] ?? [], JSON_UNESCAPED_UNICODE);
$id = intval($input['id'] ?? 0);

if (empty($slug) || empty($title)) {
  http_response_code(400);
  echo json_encode(['error' => 'slug and title are required']);
  exit;
}

if (!preg_match('/^[a-z0-9-]+(\/[a-z0-9-]+)*$/', $slug)) {
  http_response_code(400);
  echo json_encode(['error' => 'slug must contain only lowercase letters, digits, hyphens and /']);
  exit;
}

if (!in_array($status, ['draft', 'published'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid status']);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

if ($id > 0) {
  $stmt = $mysqli->prepare("UPDATE pages SET slug = ?, title = ?, description = ?, status = ?, sections = ? WHERE id = ?");
  $stmt->bind_param('sssssi', $slug, $title, $description, $status, $sections, $id);
  $stmt->execute();

  if ($stmt->affected_rows === 0 && $mysqli->errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $mysqli->error]);
    exit;
  }
} else {
  $stmt = $mysqli->prepare("INSERT INTO pages (slug, title, description, status, sections) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param('sssss', $slug, $title, $description, $status, $sections);
  $stmt->execute();

  if ($stmt->errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Insert failed: ' . $mysqli->error]);
    exit;
  }

  $id = $stmt->insert_id;
}

echo json_encode(['success' => true, 'id' => $id]);
