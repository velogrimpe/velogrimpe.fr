<?php
/**
 * Fonctions partagées pour la table `arrets` multimodale (docs/conception-bus.md).
 */

/** Fragment SELECT commun, avec extraction lat/lng depuis la géométrie `loc`. */
const ARRET_SELECT_FIELDS = "id, nom, slug, commune, code_insee, cp, ter, tgv, bus, tram, teleph, deleted, description, ST_Y(loc) AS lat, ST_X(loc) AS lng";

/**
 * Type dominant d'un arrêt pour le choix de l'icône, priorité
 * ter > tgv > tram > telepherique > bus (cf. doc Étape 2).
 */
function arret_type_dominant(array $arret): string
{
  foreach (['ter', 'tgv', 'tram', 'teleph'] as $type) {
    if (!empty($arret[$type])) {
      return $type === 'teleph' ? 'telepherique' : $type;
    }
  }
  return 'bus';
}

/** Liste des modalités desservies par l'arrêt (sous-ensemble de ter/tgv/bus/tram/telepherique). */
function arret_types(array $arret): array
{
  $types = [];
  foreach (['ter', 'tgv', 'bus', 'tram', 'teleph'] as $type) {
    if (!empty($arret[$type])) {
      $types[] = $type === 'teleph' ? 'telepherique' : $type;
    }
  }
  return $types;
}

/**
 * Slug best-effort à partir du nom libre d'un arrêt (minuscules, accents
 * translittérés, ponctuation/espaces -> tirets). `arrets.slug` est NOT NULL
 * mais n'est pas encore saisi par le formulaire (cf. Lot 3) : généré ici pour
 * rester cohérent avec le slug déjà calculé en SQL pur par la migration `sql_migrations/1_unification_arrets.sql`.
 */
function arret_slugify(string $nom): string
{
  $accents = [
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
  $slug = strtr(mb_strtolower($nom), $accents);
  $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
  return trim($slug, '-');
}

/** Normalise une ligne issue d'une requête `SELECT ARRET_SELECT_FIELDS` en tableau prêt pour du JSON. */
function arret_row_to_array(array $row): array
{
  return [
    'id' => (int) $row['id'],
    'nom' => $row['nom'],
    'slug' => $row['slug'],
    'commune' => $row['commune'],
    'code_insee' => $row['code_insee'],
    'cp' => $row['cp'],
    'ter' => (bool) $row['ter'],
    'tgv' => (bool) $row['tgv'],
    'bus' => (bool) $row['bus'],
    'tram' => (bool) $row['tram'],
    'teleph' => (bool) $row['teleph'],
    'deleted' => (bool) $row['deleted'],
    'description' => $row['description'],
    'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
    'lng' => isset($row['lng']) ? (float) $row['lng'] : null,
    'type_dominant' => arret_type_dominant($row),
    'types' => arret_types($row),
  ];
}
