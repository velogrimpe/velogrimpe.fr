<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_upload.php';

/** Taille maximale acceptée, indépendamment de la limite php.ini. */
const VG_FILE_UPLOAD_MAX_BYTES = 20 * 1024 * 1024;

/**
 * Extensions téléversables depuis l'éditeur riche.
 *
 * Allowlist, jamais blocklist : le nom du fichier est reconstruit à partir de
 * cette liste (cf. vg_file_upload_name()), donc rien ne peut arriver sur le
 * disque avec une autre extension — y compris les doubles extensions du type
 * `rapport.php.pdf`, dont seul le dernier segment survit.
 *
 * Volontairement sans `svg` ni `html` : servis depuis l'origine du site, ils
 * exécutent du JavaScript (XSS stockée). Les images passent par
 * lib/admin_image_upload.php, qui les réencode.
 */
function vg_file_upload_allowed_extensions(): array
{
  return [
    'pdf',
    'doc', 'docx', 'odt', 'rtf',
    'xls', 'xlsx', 'ods', 'csv',
    'ppt', 'pptx', 'odp',
    'txt', 'md',
    'zip',
    'gpx', 'kml', 'geojson',
    'jpg', 'jpeg', 'png', 'webp', 'gif',
  ];
}

/**
 * Accents remplacés dans le nom de fichier.
 *
 * Table explicite plutôt qu'iconv('ASCII//TRANSLIT') : selon la locale du
 * serveur, iconv rend « è » en "`e" ou en "?", ce qui donnait des noms du type
 * « notice-d-acc-es » — et le résultat variait entre la machine de dev et la
 * production.
 */
const VG_FILE_UPLOAD_ACCENTS = [
  'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
  'ç' => 'c',
  'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
  'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
  'ñ' => 'n',
  'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
  'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
  'ý' => 'y', 'ÿ' => 'y',
  'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss',
];

/**
 * Nom de fichier sûr et lisible : radical du nom d'origine désaccentué, suivi
 * d'un suffixe aléatoire et de l'extension validée.
 *
 * Le suffixe évite qu'un second téléversement du même nom écrase le premier —
 * l'ancien lien, déjà enregistré en base, pointerait alors sur un autre contenu.
 */
function vg_file_upload_name(string $originalName, string $ext): string
{
  $stem = mb_strtolower(pathinfo($originalName, PATHINFO_FILENAME), 'UTF-8');
  $stem = strtr($stem, VG_FILE_UPLOAD_ACCENTS);
  $stem = preg_replace('/[^a-z0-9]+/', '-', $stem);
  $stem = trim($stem, '-');
  if ($stem === '') {
    $stem = 'fichier';
  }
  $stem = trim(substr($stem, 0, 60), '-');

  return $stem . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
}

/**
 * Traite un téléversement de fichier joint (auth, validation, écriture).
 *
 * Attend un POST multipart avec :
 * - $_FILES['file']
 * - $_POST['slug'] (sous-dossier, nettoyé par vg_admin_upload_guard())
 *
 * Écrit la réponse JSON — { url, name } — et termine la requête.
 *
 * @param string $baseRel Dossier de données, relatif à la racine au sens de
 *                        lib/paths.php (ex. 'fichiers_pages'). L'URL publique
 *                        en est dérivée par vg_data_url(), jamais par
 *                        soustraction de DOCUMENT_ROOT sur un chemin disque :
 *                        à travers le lien symbolique du point de montage la
 *                        cible est hors DOCUMENT_ROOT, et l'URL renvoyée part
 *                        en base (pages.sections, newsletters.sections).
 */
function handleAdminFileUpload(string $baseRel, string $slug): void
{
  $safeSlug = vg_admin_upload_guard($slug);

  if (!isset($_FILES['file'])) {
    vg_admin_upload_fail(400, 'Aucun fichier envoyé');
  }
  if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    vg_admin_upload_fail(400, vg_admin_upload_error_message($_FILES['file']['error']));
  }

  $tmp = $_FILES['file']['tmp_name'];
  $originalName = (string) ($_FILES['file']['name'] ?? '');

  // Garde-fou : tmp_name doit être un fichier réellement téléversé, sinon un
  // chemin arbitraire posté dans $_FILES ferait lire n'importe quoi du serveur.
  if (!is_uploaded_file($tmp)) {
    vg_admin_upload_fail(400, 'Téléversement invalide');
  }

  if (filesize($tmp) > VG_FILE_UPLOAD_MAX_BYTES) {
    $mo = (int) (VG_FILE_UPLOAD_MAX_BYTES / 1024 / 1024);
    vg_admin_upload_fail(400, "Fichier trop volumineux (maximum $mo Mo)");
  }

  $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
  if (!in_array($ext, vg_file_upload_allowed_extensions(), true)) {
    vg_admin_upload_fail(400, 'Type de fichier non autorisé : ' . ($ext === '' ? 'sans extension' : '.' . $ext));
  }

  // Seconde barrière, sur le contenu : un fichier renommé en .pdf mais reconnu
  // comme HTML ou SVG s'exécuterait dans l'origine du site s'il était servi.
  $mime = (string) mime_content_type($tmp);
  if (in_array($mime, ['text/html', 'image/svg+xml', 'application/xhtml+xml'], true)) {
    vg_admin_upload_fail(400, 'Contenu de fichier non autorisé : ' . $mime);
  }

  $relDir = rtrim($baseRel, '/') . '/' . $safeSlug;

  try {
    vg_data_prepare($relDir);
  } catch (VgDataException $e) {
    error_log('[admin_file_upload] ' . $e->getMessage());
    vg_admin_upload_fail(500, 'Dossier de fichiers indisponible');
  }

  $filename = vg_file_upload_name($originalName, $ext);

  if (!move_uploaded_file($tmp, vg_data_path($relDir . '/' . $filename))) {
    error_log("[admin_file_upload] échec de l'écriture de $relDir/$filename");
    vg_admin_upload_fail(500, "Le fichier n'a pas pu être enregistré");
  }

  echo json_encode([
    'url'  => vg_data_url($relDir . '/' . $filename),
    // Nom d'origine : sert de libellé au lien inséré dans le texte.
    'name' => $originalName,
  ]);
}
