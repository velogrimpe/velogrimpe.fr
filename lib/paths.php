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
 * et le stockage peut se déplacer sans qu'une seule URL publique change.
 *
 * Voir docs/plans/2026-08-25-migration-dossier-public.md.
 */

/**
 * Racine des données, relative à DOCUMENT_ROOT.
 *
 *   ''         -> dossier déployé (public_html/bdd, public_html/images, …)
 *   '/public'  -> point de montage hors dépôt, via le lien symbolique
 *                 public_html/public -> ../public
 *
 * C'EST LA SEULE LIGNE À CHANGER POUR BASCULER, dans un sens comme dans
 * l'autre. Aucun site d'appel n'est concerné, y compris pour un rollback.
 *
 * Le repli déclaré dans public_html/.htaccess sert les fichiers depuis l'un ou
 * l'autre emplacement, donc les URL publiques fonctionnent pendant et après la
 * bascule. Attention : ce repli est conditionné par `!-f`, donc un fichier resté
 * dans le dossier déployé GAGNE sur son homologue du point de montage. Le
 * déplacement des données doit être un déplacement, pas une copie.
 */
const VG_DATA_MOUNT = '';

/**
 * Racines de premier niveau autorisées.
 *
 * Porte la garantie d'isolement : tant que VG_DATA_MOUNT vaut '', la base est
 * DOCUMENT_ROOT et un contrôle d'évasion ancré sur elle ne prouverait rien — il
 * laisserait passer 'api/add_velo.php'. C'est cette liste qui borne réellement.
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
 * URL publique. INVARIANTE sur toute la migration : '/bdd/gpx/12_x_y_.gpx'.
 *
 * Ne consulte volontairement PAS VG_DATA_MOUNT. C'est cette propriété qui fait
 * que la bascule ne réécrit ni le code front, ni les URL stockées en base
 * (newsletters.sections, pages.sections, pages.banner_img), ni les mails déjà
 * envoyés. Ne jamais la rendre dépendante de la constante.
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
 * Ancré sur le premier segment et non sur la base, pour garder le même pouvoir
 * avant et après la bascule (cf. VG_DATA_PREFIXES).
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
 * écriture. Remplace les mkdir dispersés dans les endpoints.
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
