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

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
  http_response_code(400);
  echo json_encode(['error' => 'slug is required']);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Total active subscribers
$totalResult = $mysqli->query("SELECT COUNT(*) as total FROM mailing_list WHERE desinscrit = 0 AND confirme = 1");
$total = intval($totalResult->fetch_assoc()['total']);

// Sent for this slug
$stmt = $mysqli->prepare("SELECT
  SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
  SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
  FROM newsletter_status WHERE newsletter_slug = ?");
$stmt->bind_param('s', $slug);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$sent = intval($stats['sent'] ?? 0);
$errors = intval($stats['errors'] ?? 0);
$remaining = max(0, $total - $sent);

echo json_encode([
  'total' => $total,
  'sent' => $sent,
  'errors' => $errors,
  'remaining' => $remaining,
]);
