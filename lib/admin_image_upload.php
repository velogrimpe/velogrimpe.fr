<?php

/**
 * Handle an admin image upload (auth, validation, GD compression, save).
 *
 * Expects POST multipart with:
 * - $_FILES['image']
 * - $_POST['slug'] (already validated by caller, also defensively here)
 *
 * Writes JSON response and exits.
 *
 * @param string $baseDir Absolute filesystem path under which to create the slug folder
 *                        (e.g. $_SERVER['DOCUMENT_ROOT'] . '/bdd/images_news').
 *                        The corresponding public URL prefix is derived by stripping
 *                        DOCUMENT_ROOT from $baseDir.
 * @param string $slug    Slug used as subfolder; will be sanitized.
 */
function handleAdminImageUpload(string $baseDir, string $slug): void
{
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

  $slug = trim($slug);
  if (empty($slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'slug is required']);
    exit;
  }

  if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No image uploaded']);
    exit;
  }

  $tmp = $_FILES['image']['tmp_name'];
  $mime = mime_content_type($tmp);

  $img = match ($mime) {
    'image/jpeg' => imagecreatefromjpeg($tmp),
    'image/png'  => imagecreatefrompng($tmp),
    'image/webp' => imagecreatefromwebp($tmp),
    'image/gif'  => imagecreatefromgif($tmp),
    default      => false,
  };

  if (!$img) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported image format: ' . $mime]);
    exit;
  }

  $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace('/', '_', $slug));
  $dir = rtrim($baseDir, '/') . '/' . $safeSlug;

  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }

  $basename = time() . '-' . bin2hex(random_bytes(4));

  if (function_exists('imagewebp')) {
    $filename = $basename . '.webp';
    $destPath = $dir . '/' . $filename;
    imagewebp($img, $destPath, 80);
  } else {
    $filename = $basename . '.jpg';
    $destPath = $dir . '/' . $filename;
    imagejpeg($img, $destPath, 80);
  }
  imagedestroy($img);

  $urlPrefix = str_replace($_SERVER['DOCUMENT_ROOT'], '', rtrim($baseDir, '/'));
  $url = $urlPrefix . '/' . $safeSlug . '/' . $filename;

  echo json_encode(['url' => $url]);
}
