<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

header('Access-Control-Allow-Methods: GET, OPTIONS');

// Support both Bearer header and ?admin= query param (needed for iframe preview)
$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
$adminParam = $_GET['admin'] ?? null;
$isAuthed = ($authHeader && $authHeader === 'Bearer ' . $config['admin_token'])
  || ($adminParam && $adminParam === $config['admin_token']);
if (!$isAuthed) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo 'Method Not Allowed';
  exit;
}

$id = intval($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'web';

if ($id <= 0) {
  http_response_code(400);
  echo 'id is required';
  exit;
}

if (!in_array($format, ['web', 'email'])) {
  http_response_code(400);
  echo 'format must be web or email';
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/newsletter_renderer.php';

$stmt = $mysqli->prepare("SELECT * FROM newsletters WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$newsletter = $stmt->get_result()->fetch_assoc();

if (!$newsletter) {
  http_response_code(404);
  echo 'Not found';
  exit;
}

$newsletter['sections'] = json_decode($newsletter['sections'], true);

header('Content-Type: text/html; charset=UTF-8');

if ($format === 'email') {
  echo renderNewsletterEmail($newsletter);
} else {
  echo renderNewsletterWeb($newsletter);
}
