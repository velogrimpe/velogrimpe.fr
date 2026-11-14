<?php
require_once __DIR__ . '/arrets_lib.php';
// Helpers des lignes BTT (tables `lignes_transport` / `arrets_lignes_transport`).

const LIGNE_TEXT_FIELDS = ['numero', 'operateur', 'operateur_url', 'horaire_url', 'ref', 'couleur'];

/**
 * Normalise une ligne SQL en tableau JSON : champs texte vides → null,
 * géométrie décodée (GeoJSON) si présente dans la ligne SQL (`geojson`).
 */
function ligne_format(array $row, bool $withGeometry): array
{
  $ligne = ["id" => (int) $row["id"], "nom" => $row["nom"]];
  foreach (LIGNE_TEXT_FIELDS as $f) {
    $ligne[$f] = $row[$f] !== null && $row[$f] !== '' ? $row[$f] : null;
  }
  if ($withGeometry) {
    $ligne["geometry"] = $row["geojson"] !== null ? json_decode($row["geojson"], true) : null;
  }
  return $ligne;
}

/** Ids des arrêts liés à une ligne. @return int[] */
function ligne_arret_ids(mysqli $mysqli, int $ligne_id): array
{
  $stmt = $mysqli->prepare("SELECT arret_id FROM arrets_lignes_transport WHERE ligne_id = ? ORDER BY arret_id");
  $stmt->bind_param("i", $ligne_id);
  $stmt->execute();
  $ids = array_map(fn($r) => (int) $r["arret_id"], $stmt->get_result()->fetch_all(MYSQLI_ASSOC));
  $stmt->close();
  return $ids;
}

const LIGNE_SELECT = "SELECT id, nom, numero, operateur, operateur_url, horaire_url, ref, couleur, ST_AsGeoJSON(geometry) AS geojson FROM lignes_transport";

/** Arrêts liés à une ligne avec leurs coordonnées (rendu du halo sans catalogue d'arrêts) ; `parent_id` : parent de l'arrêt s'il est regroupé. @return array<int, array{id:int, nom:string, types:string[], lat:float, lng:float, parent_id:?int}> */
function ligne_arrets(mysqli $mysqli, int $ligne_id): array
{
  $stmt = $mysqli->prepare(
    "SELECT a.id, a.nom, a.ter, a.tgv, a.bus, a.tram, a.teleph, a.parent_id, ST_Y(a.loc) AS lat, ST_X(a.loc) AS lng
     FROM arrets_lignes_transport alt JOIN arrets a ON a.id = alt.arret_id
     WHERE alt.ligne_id = ? ORDER BY a.id"
  );
  $stmt->bind_param("i", $ligne_id);
  $stmt->execute();
  $rows = array_map(
    fn($r) => ["id" => (int) $r["id"], "nom" => $r["nom"], "types" => arret_types($r), "lat" => (float) $r["lat"], "lng" => (float) $r["lng"],
      "parent_id" => $r["parent_id"] !== null ? (int) $r["parent_id"] : null],
    $stmt->get_result()->fetch_all(MYSQLI_ASSOC)
  );
  $stmt->close();
  return $rows;
}

/**
 * Identifiants GTFS (`stop_id`) mémorisés pour les arrêts liés à une ligne
 * (références externes de source `gtfs`) : sert à rapprocher un GTFS rechargé
 * des arrêts existants avant de retomber sur la proximité.
 * @return array<int, string[]> arret_id => stop_ids
 */
function ligne_gtfs_refs(mysqli $mysqli, int $ligne_id): array
{
  $stmt = $mysqli->prepare(
    "SELECT re.arret_id, re.ref
     FROM references_externes re
     JOIN arrets_lignes_transport alt ON alt.arret_id = re.arret_id
     WHERE alt.ligne_id = ? AND re.source = 'gtfs' ORDER BY re.id"
  );
  $stmt->bind_param("i", $ligne_id);
  $stmt->execute();
  $refs = [];
  foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $refs[(int) $r["arret_id"]][] = $r["ref"];
  }
  $stmt->close();
  return $refs;
}
