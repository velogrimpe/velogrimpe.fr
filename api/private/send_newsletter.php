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

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/fetch_mail_template.php';

$template = fetchMailTemplate("/actualites/$slug.php");
$title = $template['title'];
$mailBody = $template['html'];

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$recipientsStmt = $mysqli->prepare("SELECT
  ml.mail, ml.token
  FROM mailing_list ml
  LEFT JOIN newsletter_status ns ON ml.mail = ns.mail AND ns.newsletter_slug = ?
  WHERE ml.desinscrit = 0 AND ml.confirme = 1
    AND (ns.mail IS NULL OR ns.status != 'sent')");
// add slug parameter to the query
$recipientsStmt->bind_param('s', $slug);
$recipientsStmt->execute();
$recipients = $recipientsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$successCount = 0;
$errorCount = 0;

// send one by one
foreach ($recipients as $recipient) {

  $unsubscribeLink = $hostWithPort . "/actualites/gestion/unsubscribe.php?mail=" . urlencode($recipient['mail']) . "&token=" . urlencode($recipient['token']);

  $mailBodyForRecipient = $mailBody;
  $mailBodyForRecipient = str_replace(
    '<span data-placeholder></span>',
    "<a style=\"text-align: center; display: block; width: 100%; font-size: 10px; color: #ccc; margin-bottom: 20px; font-weight: normal;\" href=\"$unsubscribeLink\">Se désinscrire</a>",
    $mailBodyForRecipient
  );
  $data = [
    'from' => 'Velogrimpe.fr <contact@velogrimpe.fr>',
    'to' => $recipient['mail'],
    'subject' => $title,
    'html' => $mailBodyForRecipient,
    'h:List-Unsubscribe' => "<$unsubscribeLink>",
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
    status = VALUES(status),
    last_attempt = NOW()
  ;");
  $stmt->bind_param('sss', $recipient["mail"], $slug, $status);
  $stmt->execute();
}
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'sent_to' => count($recipients), 'success' => $successCount, 'error' => $errorCount]);