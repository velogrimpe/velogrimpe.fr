<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';

/**
 * Handle an admin image upload (auth, validation, GD compression, save).
 *
 * Expects POST multipart with:
 * - $_FILES['image']
 * - $_POST['slug'] (already validated by caller, also defensively here)
 *
 * Writes JSON response and exits.
 *
 * @param string $baseRel Dossier de données, relatif à la racine au sens de
 *                        lib/paths.php (ex. 'bdd/images_news'). L'URL publique
 *                        en est dérivée par vg_data_url().
 *
 *                        Volontairement RELATIF : la version précédente prenait
 *                        un chemin absolu et reconstruisait l'URL par
 *                        str_replace(DOCUMENT_ROOT, '', $baseDir). À travers le
 *                        lien symbolique du point de montage, ce str_replace ne
 *                        matche plus — et l'URL renvoyée part en base
 *                        (newsletters.sections, pages.sections). Ne jamais
 *                        revenir à une dérivation d'URL par soustraction.
 * @param string $slug    Slug used as subfolder; will be sanitized.
 */
function handleAdminImageUpload(string $baseRel, string $slug): void
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

  // Un slug entièrement composé de caractères exclus se réduit à '' et donnait
  // un chemin 'bdd/images_news//fichier.webp', donc une écriture dans le dossier
  // parent. vg_data_rel() le refuserait, mais autant traiter la cause.
  if ($safeSlug === '') {
    http_response_code(400);
    echo json_encode(['error' => 'slug invalide']);
    exit;
  }

  $relDir = rtrim($baseRel, '/') . '/' . $safeSlug;

  try {
    vg_data_prepare($relDir);
  } catch (VgDataException $e) {
    error_log('[admin_image_upload] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => "Dossier d'images indisponible"]);
    exit;
  }

  $basename = time() . '-' . bin2hex(random_bytes(4));

  if (function_exists('imagewebp')) {
    $filename = $basename . '.webp';
    $ecrit = imagewebp($img, vg_data_path($relDir . '/' . $filename), 80);
  } else {
    $filename = $basename . '.jpg';
    $ecrit = imagejpeg($img, vg_data_path($relDir . '/' . $filename), 80);
  }
  imagedestroy($img);

  // Le retour était ignoré : on renvoyait une URL vers un fichier inexistant,
  // qui partait ensuite en base.
  if (!$ecrit) {
    error_log("[admin_image_upload] échec de l'écriture de $relDir/$filename");
    http_response_code(500);
    echo json_encode(['error' => "L'image n'a pas pu être enregistrée"]);
    exit;
  }

  echo json_encode(['url' => vg_data_url($relDir . '/' . $filename)]);
}
