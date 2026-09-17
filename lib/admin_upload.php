<?php

/**
 * Socle commun aux téléversements de l'admin (images d'articles et de
 * newsletters, fichiers joints).
 *
 * Les deux points d'entrée — lib/admin_image_upload.php et
 * lib/admin_file_upload.php — partagent l'authentification par jeton, la
 * méthode acceptée et le nettoyage du slug qui sert de sous-dossier.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';

/** Réponse JSON d'erreur, puis fin de la requête. */
function vg_admin_upload_fail(int $status, string $message): never
{
  http_response_code($status);
  echo json_encode(['error' => $message]);
  exit;
}

/**
 * Vérifie la requête (préflight, jeton admin, méthode) et renvoie le slug
 * nettoyé, utilisable tel quel comme segment de chemin.
 *
 * Sort de la requête (JSON + code HTTP) dès que l'une des conditions manque.
 */
function vg_admin_upload_guard(string $slug): string
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
    vg_admin_upload_fail(403, 'Forbidden');
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vg_admin_upload_fail(405, 'Method Not Allowed');
  }

  $slug = trim($slug);
  if ($slug === '') {
    vg_admin_upload_fail(400, 'slug is required');
  }

  $safeSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace('/', '_', $slug));

  // Un slug entièrement composé de caractères exclus se réduit à '' et donnait
  // un chemin 'images_news//fichier.webp', donc une écriture dans le dossier
  // parent. vg_data_rel() le refuserait, mais autant traiter la cause.
  if ($safeSlug === '') {
    vg_admin_upload_fail(400, 'slug invalide');
  }

  return $safeSlug;
}

/**
 * Message lisible pour un code d'erreur de $_FILES.
 *
 * UPLOAD_ERR_INI_SIZE / FORM_SIZE sont les cas courants en production : la
 * limite vient de php.ini (upload_max_filesize, post_max_size) et pas du code,
 * un message générique « aucun fichier » enverrait chercher au mauvais endroit.
 */
function vg_admin_upload_error_message(int $code): string
{
  return match ($code) {
    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux pour le serveur',
    UPLOAD_ERR_PARTIAL                        => 'Téléversement interrompu',
    UPLOAD_ERR_NO_FILE                        => 'Aucun fichier envoyé',
    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Stockage temporaire indisponible',
    UPLOAD_ERR_EXTENSION                      => 'Téléversement refusé par le serveur',
    default                                   => 'Téléversement impossible',
  };
}
