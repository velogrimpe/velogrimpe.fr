<?php

/**
 * Source unique de vérité des chemins de données : contenus téléversés (images
 * de falaises, images d'articles et de newsletters, traces GPX) et fichiers
 * générés (GeoJSON de barres, exports open data).
 *
 * Toute lecture et toute écriture d'un fichier de données passe par ici.
 *
 * Les chemins manipulés sont ceux de l'ESPACE D'URL — 'bdd/gpx/12_x_y_.gpx' —
 * et non des chemins disque. La conversion chemin <-> URL en devient triviale,
 * et l'emplacement de stockage n'a aucune influence sur les URL publiques.
 */

/**
 * Racine des données, relative à DOCUMENT_ROOT.
 *
 * Les données vivent hors du dossier déployé, atteintes par le lien symbolique
 * versionné `public_html/public -> ../public`. C'est ce qui les met hors de
 * portée du `rsync --delete` de déploiement : la branche `deploy` ne contient
 * que du code, et rien de ce qu'elle écrase n'est une donnée.
 *
 * Les URL publiques, elles, restent `/bdd/…` et `/images/…` : la règle de repli
 * de `public_html/.htaccess` les résout vers le point de montage.
 */
const VG_DATA_MOUNT = '/public';

/**
 * Racines de premier niveau autorisées.
 *
 * Porte la garantie d'isolement : seuls ces trois sous-arbres sont accessibles
 * par ce helper, ce qui borne ce qu'un nom de fichier issu de la base ou d'un
 * formulaire peut atteindre.
 */
const VG_DATA_PREFIXES = ['bdd', 'images', 'open-data'];

/** Échec d'une opération sur le stockage de données. */
class VgDataException extends RuntimeException {}

/**
 * Valide et normalise un chemin de données relatif.
 *
 * Refuse : chemin vide, octet nul, segment '.' ou '..', segment vide (cas réel
 * quand un slug se réduit à la chaîne vide : 'bdd/images_news//x.webp' écrirait
 * dans le dossier parent), racine hors VG_DATA_PREFIXES.
 */
function vg_data_rel(string $rel): string
{
  $rel = ltrim($rel, '/');
  $segments = explode('/', $rel);

  if ($rel === '' || str_contains($rel, "\0") || !in_array($segments[0], VG_DATA_PREFIXES, true)) {
    throw new VgDataException("Chemin de données invalide : « $rel »");
  }
  foreach ($segments as $segment) {
    if ($segment === '' || $segment === '.' || $segment === '..') {
      throw new VgDataException("Chemin de données invalide : « $rel »");
    }
  }
  return $rel;
}

/** Chemin absolu sur disque. $rel vide = la racine des données elle-même. */
function vg_data_path(string $rel = ''): string
{
  $base = $_SERVER['DOCUMENT_ROOT'] . VG_DATA_MOUNT;
  return $rel === '' ? $base : $base . '/' . vg_data_rel($rel);
}

/**
 * URL publique : '/bdd/gpx/12_x_y_.gpx'.
 *
 * Ne dépend volontairement PAS de VG_DATA_MOUNT, et ne doit jamais en dépendre.
 * Ces URL sont stockées en base (newsletters.sections, pages.sections,
 * pages.banner_img), publiées dans les exports open data et envoyées par mail :
 * elles doivent survivre à tout déplacement du stockage.
 *
 * Corollaire : ne jamais reconstruire une URL en soustrayant DOCUMENT_ROOT d'un
 * chemin disque. À travers le lien symbolique, la cible est hors DOCUMENT_ROOT
 * et la soustraction ne matche pas.
 */
function vg_data_url(string $rel): string
{
  return '/' . vg_data_rel($rel);
}

/** Le fichier de données existe-t-il ? */
function vg_data_exists(string $rel): bool
{
  return file_exists(vg_data_path($rel));
}

/**
 * Chemin réel, ou null si le chemin résolu sort de sa racine de données (lien
 * symbolique piégé, remontée) ou si le fichier n'existe pas.
 *
 * L'ancrage se fait sur le premier segment (bdd/, images/, open-data/) et non
 * sur le point de montage : c'est le sous-arbre qui borne, pas la racine.
 */
function vg_data_realpath(string $rel): ?string
{
  $rel = vg_data_rel($rel);
  $base = realpath(vg_data_path(explode('/', $rel)[0]));
  $target = realpath(vg_data_path($rel));

  if ($base === false || $target === false) {
    return null;
  }
  return str_starts_with($target . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR) ? $target : null;
}

/**
 * Crée le dossier de données s'il manque et vérifie qu'il est accessible en
 * écriture.
 *
 * À appeler AVANT la première mutation de la requête — avant l'INSERT, avant
 * move_uploaded_file. C'est le seul moment où l'on peut échouer sans laisser
 * d'état partiel : une ligne en base sans son fichier n'est pas rattrapable
 * automatiquement, un échec avant l'INSERT l'est par un renvoi du formulaire.
 */
function vg_data_prepare(string $relDir): void
{
  $dir = vg_data_path(rtrim($relDir, '/'));

  // Idiome anti-course : un autre process a pu créer le dossier entre-temps.
  if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    throw new VgDataException("Dossier de données impossible à créer : $dir");
  }
  if (!is_writable($dir)) {
    throw new VgDataException("Dossier de données non accessible en écriture : $dir");
  }
}
