<?php
// This endpoint has moved to /api/private/batch-geocode.php and is no longer available publicly.
header('Content-Type: application/json; charset=utf-8');
http_response_code(410); // Gone
echo json_encode([
  'error' => 'Gone',
  'message' => 'Use /api/private/batch-geocode.php?admin=...'
]);
