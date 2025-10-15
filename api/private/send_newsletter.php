<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
// Check that Authorization header is and equal to config["admin_token"]
header('Access-Control-Allow-Methods: GET, OPTIONS');

$headers = getallheaders();

$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if (!$authHeader) {
  die("Authorization header not found");
}
if ($authHeader !== 'Bearer ' . $config["admin_token"]) {
  die("Invalid token");
}

// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

$slug = trim($_GET['slug'] ?? '');
if (empty($slug)) {
  http_response_code(400);
  die("Slug is required.");
}

$host = $config['base_url'] ?? 'http://localhost';
$url = "$host/actualites/$slug.php";
$options = [
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
];

$ch = curl_init();
curl_setopt_array($ch, $options);
$mailBody = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// Check that the response is a 200
if ($httpCode !== 200) {
  http_response_code(500);
  die("Failed to fetch the newsletter content: $url - HTTP code: $httpCode, body='$mailBody'");
}
curl_close($ch);

// Store document in a variable mailBody
// $recipients = ["yoann@couble.eu", "couble.yoann@gmail.com"];//, "ycouble@icloud.com", "contact@velogrimpe.fr", "marc_miroil@hotmail.com", "amandine.spiandore@orange.fr", "amandine.spiandore@hotmail.fr"];
// parse html for title tag
preg_match('/<title>(.*?)<\/title>/', $mailBody, $matches);
$title = trim($matches[1]) ?? 'Actualités Velogrimpe.fr';
// Send the email

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$recipientsStmt = $mysqli->prepare("SELECT
  ml.mail
  FROM mailing_list ml
  LEFT JOIN newsletter_status ns ON ml.mail = ns.mail AND ns.newsletter_slug = ?
  WHERE ml.desinscrit = 0
    AND (ns.mail IS NULL OR ns.status != 'sent')");
// add slug parameter to the query
$recipientsStmt->bind_param('s', $slug);
$recipientsStmt->execute();
$recipientsStmt = $recipientsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recipients = array_map(fn($row) => $row['mail'], $recipientsStmt);

$successCount = 0;
$errorCount = 0;

// send one by one
foreach ($recipients as $recipient) {
  $data = [
    'from' => 'Velogrimpe.fr <contact@velogrimpe.fr>',
    'to' => $recipient,
    'subject' => $title,
    'html' => $mailBody,
  ];
  $res = sendMail($data);
  // store the status in the database
  $status = $res === true ? 'sent' : 'error';
  // count successes and errors
  $successCount += $res === true ? 1 : 0;
  $errorCount += $res === true ? 0 : 1;
  $stmt = $mysqli->prepare("INSERT INTO
    newsletter_status (mail, newsletter_slug, status, last_attempt)
  VALUES (?, ?, ?, NOW())
  ON DUPLICATE KEY UPDATE
    status = ?,
    last_attempt = NOW()
  ;");
  $stmt->bind_param('ssss', $recipient, $slug, $status, $status);
  $stmt->execute();
}
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'sent_to' => count($recipients), 'success' => $successCount, 'error' => $errorCount]);