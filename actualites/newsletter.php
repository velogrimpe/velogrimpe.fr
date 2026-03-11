<?php
$slug = trim($_GET['slug'] ?? '');

if (empty($slug)) {
  http_response_code(400);
  echo 'slug is required';
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/newsletter_renderer.php';

$stmt = $mysqli->prepare("SELECT * FROM newsletters WHERE slug = ? AND status IN ('published', 'sent')");
$stmt->bind_param('s', $slug);
$stmt->execute();
$newsletter = $stmt->get_result()->fetch_assoc();

if (!$newsletter) {
  http_response_code(404);
  echo 'Newsletter not found';
  exit;
}

$newsletter['sections'] = json_decode($newsletter['sections'], true);

// Add pageview script before </head>
$html = renderNewsletterWeb($newsletter);
$html = str_replace('</head>', '<script async defer src="/js/pv.js"></script></head>', $html);

echo $html;
