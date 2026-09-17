<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_upload.php';

/**
 * Handle an admin image upload (auth, validation, GD compression, save).
 *
 * Expects POST multipart with:
 * - $_FILES['image']
 * - $_POST['slug'] (nettoyé par vg_admin_upload_guard())
 *
 * Writes JSON response and exits.
 *
 * @param string $baseRel Dossier de données, relatif à la racine au sens de
 *                        lib/paths.php (ex. 'images_news'). L'URL publique
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
  $safeSlug = vg_admin_upload_guard($slug);

  if (!isset($_FILES['image'])) {
    vg_admin_upload_fail(400, 'No image uploaded');
  }
  if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    vg_admin_upload_fail(400, vg_admin_upload_error_message($_FILES['image']['error']));
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
    vg_admin_upload_fail(400, 'Unsupported image format: ' . $mime);
  }

  $relDir = rtrim($baseRel, '/') . '/' . $safeSlug;

  try {
    vg_data_prepare($relDir);
  } catch (VgDataException $e) {
    error_log('[admin_image_upload] ' . $e->getMessage());
    vg_admin_upload_fail(500, "Dossier d'images indisponible");
  }

  $basename = time() . '-' . bin2hex(random_bytes(4));

  if (function_exists('imagewebp')) {
    $filename = $basename . '.webp';
    $ecrit = imagewebp($img, vg_data_path($relDir . '/' . $filename), 80);
  } else {
    $filename = $basename . '.jpg';
    $ecrit = imagejpeg($img, vg_data_path($relDir . '/' . $filename), 80);
  }

  // Le retour était ignoré : on renvoyait une URL vers un fichier inexistant,
  // qui partait ensuite en base.
  if (!$ecrit) {
    error_log("[admin_image_upload] échec de l'écriture de $relDir/$filename");
    vg_admin_upload_fail(500, "L'image n'a pas pu être enregistrée");
  }

  echo json_encode(['url' => vg_data_url($relDir . '/' . $filename)]);
}
