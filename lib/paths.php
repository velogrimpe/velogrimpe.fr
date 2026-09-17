<?php

/**
 * Source unique de vérité des chemins de données : contenus téléversés (images
 * de falaises, images d'articles et de newsletters, traces GPX) et fichiers
 * générés (GeoJSON de barres, exports open data).
 *
 * Toute lecture et toute écriture d'un fichier de données passe par ici.
 *
 * Les chemins manipulés sont relatifs à la racine des données — 'gpx/12_x_y_.gpx'
 * — et non des chemins disque absolus. vg_data_path() et vg_data_url() en
 * dérivent respectivement l'emplacement sur disque et l'URL publique.
 */

/**
 * Racine des données, relative à DOCUMENT_ROOT — et préfixe de leurs URL.
 *
 * Les données vivent hors du dossier déployé, atteintes par le lien symbolique
 * versionné `public_html/public -> ../public`. C'est ce qui les met hors de
 * portée du `rsync --delete` de déploiement : la branche `deploy` ne contient
 * que du code, et rien de ce qu'elle écrase n'est une donnée.
 *
 * `/public/…` est aussi la forme CANONIQUE de leurs URL : chemin disque et URL
 * coïncident, il n'y a qu'une adresse par fichier. Les anciennes URL `/bdd/…`
 * sont redirigées par `public_html/.htaccess`.
 */
const VG_DATA_MOUNT = '/public';

/** Échec d'une opération sur le stockage de données. */
class VgDataException extends RuntimeException {}

/**
 * Valide et normalise un chemin de données relatif.
 *
 * Refuse : chemin vide, octet nul, segment vide (cas réel quand un slug se
 * réduit à la chaîne vide : 'images_news//x.webp' écrirait dans le dossier
 * parent), et tout segment commençant par un point.
 *
 * Ce rejet des segments en `.` fait trois choses d'un coup : il couvre `.` et
 * `..`, donc aucun chemin ne peut sortir du point de montage ; et il met
 * `public/.htaccess` hors d'atteinte — ce fichier est la barrière qui interdit
 * l'exécution de scripts dans les données, un helper d'écriture n'a aucune
 * raison de pouvoir l'écraser.
 *
 * Il n'y a volontairement PAS de liste blanche de dossiers : tout ce qui vit
 * sous le point de montage est de la donnée, et l'isolement est assuré par les
 * contrôles ci-dessus. Un nouveau dossier de données ne demande donc aucune
 * modification ici.
 */
function vg_data_rel(string $rel): string
{
  $rel = ltrim($rel, '/');

  if ($rel === '' || str_contains($rel, "\0")) {
    throw new VgDataException("Chemin de données invalide : « $rel »");
  }
  foreach (explode('/', $rel) as $segment) {
    if ($segment === '' || str_starts_with($segment, '.')) {
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
 * URL publique canonique : '/public/gpx/12_x_y_.gpx'.
 *
 * Ces URL sont stockées en base (newsletters.sections, pages.sections,
 * pages.banner_img), publiées dans les exports open data et envoyées par mail.
 *
 * Toujours passer par cette fonction, jamais reconstruire une URL en
 * soustrayant DOCUMENT_ROOT d'un chemin disque : à travers le lien symbolique,
 * la cible est hors DOCUMENT_ROOT et la soustraction ne matche pas.
 */
function vg_data_url(string $rel): string
{
  return VG_DATA_MOUNT . '/' . vg_data_rel($rel);
}

/** Le fichier de données existe-t-il ? */
function vg_data_exists(string $rel): bool
{
  return file_exists(vg_data_path($rel));
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
