<?php
/**
 * Données embarquées par les cartes globales (carte.php, carte_leaflet.php).
 */
require_once __DIR__ . '/arrets_lib.php';
require_once __DIR__ . '/velo_lib.php';

/**
 * Arrêts (toutes modalités) avec leurs temps de train depuis chaque ville.
 * Clés renvoyées : arret_id, arret_nom, arret_latlng, types, lignes (numéros), villes (brut), enfants.
 * Seuls les parents et orphelins sont des points de la carte ; `enfants` liste les arrêts regroupés sous un
 * parent (arret_id, arret_nom, arret_latlng, types : []  pour un orphelin) et `lignes` d'un parent réunit
 * celles de ses enfants.
 *
 * @param bool $garesHorsTopo false : omet les gares (ter/tgv) sans itinéraire ni
 *   accès piéton publiés — MapLibre les rend depuis un PMTiles.
 */
function carte_charger_arrets(mysqli $mysqli, bool $garesHorsTopo = true): array
{
  $filtre = $garesHorsTopo ? '' : "AND (
      (a.ter = 0 AND a.tgv = 0)
      OR a.id IN (SELECT arret_id FROM acces_falaise WHERE public >= 1)
    )";
  $rows = $mysqli->query("SELECT
    a.id AS arret_id, a.nom AS arret_nom,
    CONCAT(ST_Y(a.loc), ',', ST_X(a.loc)) AS arret_latlng,
    a.ter, a.tgv, a.bus, a.tram, a.teleph,
    GROUP_CONCAT(CONCAT(t.ville_id, '|', t.train_depart, '|', t.train_temps, '|', t.train_correspmin, '|', COALESCE(t.train_tgv, 0)) SEPARATOR '=|=') AS villes
    FROM arrets a
    LEFT JOIN train t ON t.arret_id = a.id
    WHERE a.deleted = 0 AND a.parent_id IS NULL $filtre
    GROUP BY a.id")->fetch_all(MYSQLI_ASSOC);
  // Enfants actifs des parents (regroupement d'arrêts) : positions pour l'enveloppe affichée à fort zoom.
  $enfantsParParent = [];
  $parentDe = []; // enfant => parent
  foreach ($mysqli->query("SELECT id, parent_id, nom, ter, tgv, bus, tram, teleph,
      CONCAT(ST_Y(loc), ',', ST_X(loc)) AS latlng
      FROM arrets WHERE parent_id IS NOT NULL AND deleted = 0 ORDER BY id")->fetch_all(MYSQLI_ASSOC) as $e) {
    $parentDe[(int) $e['id']] = (int) $e['parent_id'];
    $enfantsParParent[(int) $e['parent_id']][] = [
      'arret_id' => (int) $e['id'],
      'arret_nom' => $e['nom'],
      'arret_latlng' => $e['latlng'],
      'types' => arret_types($e),
    ];
  }
  // Numéros des lignes de transport desservant chaque arrêt (panneau d'info des cartes) ; les lignes
  // desservant un enfant remontent sur son parent. Les lignes sans numéro renseigné sont omises.
  $lignesParArret = [];
  foreach ($mysqli->query("SELECT alt.arret_id, l.numero
      FROM arrets_lignes_transport alt JOIN lignes_transport l ON l.id = alt.ligne_id
      WHERE l.numero IS NOT NULL AND l.numero <> ''
      ORDER BY l.numero")->fetch_all(MYSQLI_ASSOC) as $l) {
    $cle = $parentDe[(int) $l['arret_id']] ?? (int) $l['arret_id'];
    if (!in_array($l['numero'], $lignesParArret[$cle] ?? [], true)) $lignesParArret[$cle][] = $l['numero'];
  }
  return array_map(function ($r) use ($lignesParArret, $enfantsParParent) {
    $r['arret_id'] = (int) $r['arret_id'];
    $r['types'] = arret_types($r);
    $r['lignes'] = $lignesParArret[$r['arret_id']] ?? [];
    $r['enfants'] = $enfantsParParent[$r['arret_id']] ?? [];
    unset($r['ter'], $r['tgv'], $r['bus'], $r['tram'], $r['teleph']);
    return $r;
  }, $rows);
}

/** Itinéraires vélo publiés, depuis n'importe quel arrêt. */
function carte_charger_itineraires(mysqli $mysqli): array
{
  // Envoyé tel quel au JS : liste explicite de colonnes, jamais de SELECT *
  // (contrib / contrib_mail portent un email). Les accès « à pied » (type = 'pied')
  // sont servis par carte_charger_acces_pieton(). Les clés JSON gardent leurs noms
  // d'avant le Lot 8quater (front) ; velo_apiedpossible est calculé (≤ 5 km).
  $rows = $mysqli->query("SELECT
    v.falaise_id, v.arret_id, " . acces_a_pied_sql('v') . " AS velo_apiedpossible, v.arrivee_slug AS velo_arrivee,
    v.depart_slug AS velo_depart, v.dplus AS velo_dplus, v.acces_id AS velo_id, v.km AS velo_km,
    v.variante_formate AS velo_varianteformate
    FROM acces_falaise v
    JOIN arrets a ON a.id = v.arret_id AND a.deleted = 0
    WHERE v.type = 'velo' AND v.public >= 1")->fetch_all(MYSQLI_ASSOC);
  return array_map(function ($r) {
    $r['arret_id'] = (int) $r['arret_id'];
    return $r;
  }, $rows);
}

/**
 * Accès à pied arrêt → falaise (falaises publiées, arrêts non supprimés).
 *
 * Un accès à pied peut porter km / D+ / D- et une trace GPX (Lot 8quater). Les
 * clés `velo_*` + `has_gpx` permettent aux cartes de réutiliser leur rendu GPX
 * des itinéraires vélo ; colonnes explicites, jamais de contributeur (email).
 */
function carte_charger_acces_pieton(mysqli $mysqli): array
{
  $rows = $mysqli->query("SELECT p.acces_id, p.arret_id, p.falaise_id, p.km, p.dplus, p.dmoins,
      p.depart_slug, p.arrivee_slug, p.variante_formate
    FROM acces_falaise p
    JOIN arrets a ON a.id = p.arret_id AND a.deleted = 0
    JOIN falaises f ON f.falaise_id = p.falaise_id AND f.falaise_public >= 1
    WHERE p.type = 'pied' AND p.public >= 1")->fetch_all(MYSQLI_ASSOC);
  return array_map(function ($r) {
    $rel = velo_gpx_rel((int) $r['acces_id'], $r['depart_slug'], $r['arrivee_slug'], (string) $r['variante_formate']);
    return [
      'arret_id' => (int) $r['arret_id'],
      'falaise_id' => $r['falaise_id'],
      'acces_id' => (int) $r['acces_id'],
      'km' => $r['km'] !== null ? (float) $r['km'] : null,
      'dplus' => $r['dplus'] !== null ? (int) $r['dplus'] : null,
      'dmoins' => $r['dmoins'] !== null ? (int) $r['dmoins'] : null,
      // Clés de gpx_path() côté carte (même nommage que les itinéraires vélo)
      'velo_id' => (int) $r['acces_id'],
      'velo_depart' => $r['depart_slug'],
      'velo_arrivee' => $r['arrivee_slug'],
      'velo_varianteformate' => $r['variante_formate'],
      'has_gpx' => vg_data_exists($rel),
    ];
  }, $rows);
}
