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
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/newsletter_renderer.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';

// Fetch newsletter
$stmt = $mysqli->prepare("SELECT * FROM newsletters WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$newsletter = $stmt->get_result()->fetch_assoc();

if (!$newsletter) {
  http_response_code(404);
  echo json_encode(['error' => 'Newsletter not found']);
  exit;
}

$newsletter['sections'] = json_decode($newsletter['sections'], true);
$slug = $newsletter['slug'];
$title = $newsletter['title'];

// Render email HTML
$mailBody = renderNewsletterEmail($newsletter);

// Remove tracking script from email
$mailBody = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $mailBody);

$baseUrl = rtrim($config['base_url'] ?? 'http://localhost', '/');
$host = strpos($baseUrl, 'localhost') !== false ? $baseUrl . ':4002' : $baseUrl;

// Get recipients: confirmed, not unsubscribed, not already sent for this slug
$recipientsStmt = $mysqli->prepare("SELECT
  ml.mail, ml.token
  FROM mailing_list ml
  LEFT JOIN newsletter_status ns ON ml.mail = ns.mail AND ns.newsletter_slug = ?
  WHERE ml.desinscrit = 0 AND ml.confirme = 1
    AND (ns.mail IS NULL OR ns.status != 'sent')");
$recipientsStmt->bind_param('s', $slug);
$recipientsStmt->execute();
$recipients = $recipientsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$successCount = 0;
$errorCount = 0;

$statusStmt = $mysqli->prepare("INSERT INTO
  newsletter_status (mail, newsletter_slug, status, last_attempt)
VALUES (?, ?, ?, NOW())
ON DUPLICATE KEY UPDATE
  status = VALUES(status),
  last_attempt = NOW()");

foreach ($recipients as $recipient) {
  $unsubscribeLink = $host . "/actualites/gestion/unsubscribe.php?mail=" . urlencode($recipient['mail']) . "&token=" . urlencode($recipient['token']);

  $mailBodyForRecipient = str_replace(
    '<span data-placeholder></span>',
    '<a style="text-align: center; display: block; margin-top: 20px; width: 100%; font-size: 10px; color: #ccc; margin-bottom: 20px; font-weight: normal;" href="' . $unsubscribeLink . '">Se désinscrire</a>',
    $mailBody
  );

  $data = [
    'from' => 'Velogrimpe.fr <contact@velogrimpe.fr>',
    'to' => $recipient['mail'],
    'subject' => $title,
    'html' => $mailBodyForRecipient,
    'h:List-Unsubscribe' => "<$unsubscribeLink>",
  ];

  $res = sendMail($data);
  $status = $res === true ? 'sent' : 'error';
  $successCount += $res === true ? 1 : 0;
  $errorCount += $res === true ? 0 : 1;

  $statusStmt->bind_param('sss', $recipient['mail'], $slug, $status);
  $statusStmt->execute();
}

// Only mark newsletter as sent if at least one email was delivered
if ($successCount > 0) {
  $updateStmt = $mysqli->prepare("UPDATE newsletters SET status = 'sent', date_sent = NOW() WHERE id = ?");
  $updateStmt->bind_param('i', $id);
  $updateStmt->execute();
}

echo json_encode([
  'status' => 'ok',
  'sent_to' => count($recipients),
  'success' => $successCount,
  'error' => $errorCount,
]);
