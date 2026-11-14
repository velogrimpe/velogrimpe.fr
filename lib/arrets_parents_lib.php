<?php
/**
 * Arrêts parents / enfants (docs/plan-arrets-parents.md).
 *
 * Deux niveaux au plus. Un arrêt est un parent s'il a des enfants actifs, un enfant s'il a un `parent_id`,
 * un orphelin sinon. Le parent (ou l'orphelin) porte `acces_falaise` et `train` ; les enfants gardent
 * `arrets_lignes_transport` et `references_externes`. Position d'un parent = barycentre de ses enfants
 * (sauf `position_manuelle = 1`) ; modalités = OU de celles des enfants.
 *
 * Toutes les fonctions supposent une transaction ouverte par l'appelant (lever une ArretFusionException
 * annule la transaction côté appelant) et mysqli en mode exceptions.
 */

/** Refus métier d'une opération de regroupement : `$http` = code de réponse HTTP à renvoyer. */
class ArretFusionException extends RuntimeException
{
  public function __construct(public readonly int $http, string $message)
  {
    parent::__construct($message);
  }
}

const ARRET_MODES = ['ter', 'tgv', 'bus', 'tram', 'teleph'];

/** Familles de modalité d'un arrêt : TER et TGV ne forment qu'une famille (gares). @return string[] */
function arret_familles(array $row): array
{
  $familles = [];
  foreach (ARRET_MODES as $mode) {
    if (!empty($row[$mode])) $familles[] = ($mode === 'ter' || $mode === 'tgv') ? 'rail' : $mode;
  }
  return array_values(array_unique($familles));
}

/** Deux arrêts sont fusionnables s'ils partagent une famille de modalité (règle intra-mode). */
function arrets_meme_mode(array $a, array $b): bool
{
  return count(array_intersect(arret_familles($a), arret_familles($b))) > 0;
}

/** Nombre d'enfants actifs d'un arrêt. */
function arret_nb_enfants(mysqli $mysqli, int $id): int
{
  $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM arrets WHERE parent_id = ? AND deleted = 0");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $n = (int) $stmt->get_result()->fetch_assoc()['n'];
  $stmt->close();
  return $n;
}

/** Charge (et verrouille) des arrêts par id. @return array<int, array> */
function arrets_charger_verrouilles(mysqli $mysqli, array $ids): array
{
  $in = implode(',', array_map('intval', $ids));
  $rows = [];
  $res = $mysqli->query(
    "SELECT id, nom, slug, commune, code_insee, cp, description, parent_id, deleted, position_manuelle,
            ter, tgv, bus, tram, teleph, ST_Y(loc) AS lat, ST_X(loc) AS lng
     FROM arrets WHERE id IN ($in) FOR UPDATE"
  );
  foreach ($res as $r) $rows[(int) $r['id']] = $r;
  return $rows;
}

/**
 * Recalcule un parent : modalités (OU) et, sauf position manuelle, position (barycentre) de ses enfants actifs.
 * Sans enfant actif, le parent n'est pas modifié.
 */
function arret_recalculer_parent(mysqli $mysqli, int $parentId): void
{
  $stmt = $mysqli->prepare(
    "SELECT AVG(ST_Y(loc)) AS lat, AVG(ST_X(loc)) AS lng, MAX(ter) AS ter, MAX(tgv) AS tgv, MAX(bus) AS bus,
            MAX(tram) AS tram, MAX(teleph) AS teleph, COUNT(*) AS n
     FROM arrets WHERE parent_id = ? AND deleted = 0"
  );
  $stmt->bind_param("i", $parentId);
  $stmt->execute();
  $agg = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ((int) $agg['n'] === 0) return;

  $stmt = $mysqli->prepare("SELECT position_manuelle FROM arrets WHERE id = ?");
  $stmt->bind_param("i", $parentId);
  $stmt->execute();
  $manuelle = (int) ($stmt->get_result()->fetch_assoc()['position_manuelle'] ?? 0);
  $stmt->close();

  $ter = (int) $agg['ter']; $tgv = (int) $agg['tgv']; $bus = (int) $agg['bus']; $tram = (int) $agg['tram']; $teleph = (int) $agg['teleph'];
  if ($manuelle) {
    $stmt = $mysqli->prepare("UPDATE arrets SET ter = ?, tgv = ?, bus = ?, tram = ?, teleph = ? WHERE id = ?");
    $stmt->bind_param("iiiiii", $ter, $tgv, $bus, $tram, $teleph, $parentId);
  } else {
    $wkt = sprintf('POINT(%.7f %.7f)', (float) $agg['lng'], (float) $agg['lat']); // WKT = (lng lat)
    $stmt = $mysqli->prepare(
      "UPDATE arrets SET ter = ?, tgv = ?, bus = ?, tram = ?, teleph = ?, loc = ST_GeomFromText(?, 4326) WHERE id = ?"
    );
    $stmt->bind_param("iiiiisi", $ter, $tgv, $bus, $tram, $teleph, $wkt, $parentId);
  }
  $stmt->execute();
  $stmt->close();
}

/**
 * Accès (falaise, type, variante) présents à la fois sur `$a` et sur `$b` : ils ne peuvent pas être
 * réunis sur un même arrêt (unicité). @return array<int, array{falaise_nom:string, type:string, variante:string}>
 */
function arrets_acces_conflits(mysqli $mysqli, int $a, int $b): array
{
  $stmt = $mysqli->prepare(
    "SELECT DISTINCT f.falaise_nom, s.type, s.variante FROM acces_falaise s
     JOIN acces_falaise c ON c.arret_id = ? AND c.falaise_id = s.falaise_id AND c.type = s.type AND c.variante = s.variante
     JOIN falaises f ON f.falaise_id = s.falaise_id
     WHERE s.arret_id = ? ORDER BY f.falaise_nom"
  );
  $stmt->bind_param("ii", $a, $b);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  return $rows;
}

/** Reporte accès aux falaises et trajets train de `$de` vers `$vers`. @return int nombre d'accès déplacés */
function arret_reporter_acces_et_trains(mysqli $mysqli, int $de, int $vers): int
{
  $stmt = $mysqli->prepare("UPDATE acces_falaise SET arret_id = ? WHERE arret_id = ?");
  $stmt->bind_param("ii", $vers, $de);
  $stmt->execute();
  $n = $stmt->affected_rows;
  $stmt->close();
  $stmt = $mysqli->prepare("UPDATE train SET arret_id = ? WHERE arret_id = ?");
  $stmt->bind_param("ii", $vers, $de);
  $stmt->execute();
  $stmt->close();
  return $n;
}

/**
 * Regroupe deux arrêts. Cas : deux orphelins → nouveau parent (attributs de `$cible`) ; un parent et un
 * orphelin → l'orphelin devient enfant du parent ; deux parents → `$cible` absorbe les enfants de `$source`
 * (aplatissement, `$source` supprimé logiquement). Refus (ArretFusionException) : arrêt introuvable (404),
 * supprimé, enfant, de modes différents, ou accès identiques (409).
 * @return array{parent_id:int, acces_deplaces:int}
 */
function arret_fusionner(mysqli $mysqli, int $source, int $cible): array
{
  if ($source === $cible) throw new ArretFusionException(400, "Deux arrêts distincts sont requis");
  $rows = arrets_charger_verrouilles($mysqli, [$source, $cible]);
  if (count($rows) !== 2) throw new ArretFusionException(404, "Arrêt introuvable");
  [$s, $c] = [$rows[$source], $rows[$cible]];
  foreach ([$s, $c] as $r) {
    if ($r['deleted']) throw new ArretFusionException(409, "« {$r['nom']} » est supprimé : fusion impossible");
    if ($r['parent_id'] !== null) {
      throw new ArretFusionException(409, "« {$r['nom']} » est déjà regroupé sous un parent : détachez-le d'abord, ou fusionnez son parent");
    }
  }
  if (!arrets_meme_mode($s, $c)) {
    throw new ArretFusionException(409, "Fusion refusée : « {$s['nom']} » et « {$c['nom']} » n'ont pas de mode de transport en commun");
  }

  $sParent = arret_nb_enfants($mysqli, $source) > 0;
  $cParent = arret_nb_enfants($mysqli, $cible) > 0;
  // Qui garde les accès : le parent existant (la cible si les deux le sont), sinon un parent à créer.
  $porteur = $cParent ? $cible : ($sParent ? $source : null);
  $absorbe = $porteur === null ? [$source, $cible] : [$porteur === $cible ? $source : $cible];

  $conflits = $porteur === null
    ? arrets_acces_conflits($mysqli, $cible, $source)
    : arrets_acces_conflits($mysqli, $porteur, $absorbe[0]);
  if ($conflits) {
    $liste = array_map(
      fn($x) => $x['falaise_nom'] . ' (' . $x['type'] . ($x['variante'] !== '' ? ', variante « ' . $x['variante'] . ' »' : '') . ')',
      $conflits,
    );
    throw new ArretFusionException(409, "Fusion refusée : les deux arrêts ont déjà un accès identique vers " . implode(', ', $liste)
      . ". Supprimez ou renommez (variante) l'un des accès doublons, puis recommencez.");
  }

  if ($porteur === null) {
    $wkt = sprintf('POINT(%.7f %.7f)', ((float) $s['lng'] + (float) $c['lng']) / 2, ((float) $s['lat'] + (float) $c['lat']) / 2);
    $contrib = 'Fusion';
    $mail = '';
    $stmt = $mysqli->prepare(
      "INSERT INTO arrets (loc, nom, slug, commune, code_insee, cp, description, contrib, contrib_mail)
       VALUES (ST_GeomFromText(?, 4326), ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssssssss", $wkt, $c['nom'], $c['slug'], $c['commune'], $c['code_insee'], $c['cp'], $c['description'], $contrib, $mail);
    $stmt->execute();
    $porteur = $mysqli->insert_id;
    $stmt->close();
  }

  $deplaces = 0;
  foreach ($absorbe as $id) {
    $deplaces += arret_reporter_acces_et_trains($mysqli, $id, $porteur);
  }
  $stmt = $mysqli->prepare("UPDATE arrets SET parent_id = ? WHERE id = ?");
  if ($sParent && $cParent) {
    // Aplatissement : les enfants de la source passent sous la cible, la source disparaît (ses références de station suivent)
    $stmt2 = $mysqli->prepare("UPDATE arrets SET parent_id = ? WHERE parent_id = ?");
    $stmt2->bind_param("ii", $porteur, $source);
    $stmt2->execute();
    $stmt2->close();
    $stmt2 = $mysqli->prepare("UPDATE IGNORE references_externes SET arret_id = ? WHERE arret_id = ?");
    $stmt2->bind_param("ii", $porteur, $source);
    $stmt2->execute();
    $stmt2->close();
    $stmt2 = $mysqli->prepare("UPDATE arrets SET deleted = 1 WHERE id = ?");
    $stmt2->bind_param("i", $source);
    $stmt2->execute();
    $stmt2->close();
  } else {
    foreach ([$source, $cible] as $id) {
      if ($id === $porteur) continue;
      $stmt->bind_param("ii", $porteur, $id);
      $stmt->execute();
    }
  }
  $stmt->close();

  arret_recalculer_parent($mysqli, $porteur);
  return ['parent_id' => $porteur, 'acces_deplaces' => $deplaces];
}

/** Détache un enfant : il redevient orphelin ; le parent est recalculé. Refusé pour le dernier enfant (→ défusionner). */
function arret_detacher(mysqli $mysqli, int $id): void
{
  $rows = arrets_charger_verrouilles($mysqli, [$id]);
  if (!$rows) throw new ArretFusionException(404, "Arrêt introuvable");
  $parentId = $rows[$id]['parent_id'];
  if ($parentId === null) throw new ArretFusionException(409, "Cet arrêt n'est pas un enfant");
  $parentId = (int) $parentId;
  if (arret_nb_enfants($mysqli, $parentId) <= 1) {
    throw new ArretFusionException(409, "C'est le dernier enfant de son parent : défusionnez le parent à la place");
  }
  $stmt = $mysqli->prepare("UPDATE arrets SET parent_id = NULL WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
  arret_recalculer_parent($mysqli, $parentId);
}

/**
 * Dissout un parent : ses enfants redeviennent orphelins, le parent est supprimé logiquement. Ses accès et
 * trajets train reviennent à `$heritierId` (un de ses enfants ; déduit s'il n'y a qu'un enfant ; requis sinon
 * si le parent porte des accès ou des trajets).
 */
function arret_defusionner(mysqli $mysqli, int $parentId, ?int $heritierId = null): void
{
  $rows = arrets_charger_verrouilles($mysqli, [$parentId]);
  if (!$rows) throw new ArretFusionException(404, "Arrêt introuvable");
  if ($rows[$parentId]['deleted'] || arret_nb_enfants($mysqli, $parentId) === 0) {
    throw new ArretFusionException(409, "Cet arrêt n'est pas un parent");
  }
  $enfants = array_map(
    fn($r) => (int) $r['id'],
    $mysqli->query("SELECT id FROM arrets WHERE parent_id = $parentId AND deleted = 0 ORDER BY id")->fetch_all(MYSQLI_ASSOC)
  );
  $nbRelations = (int) $mysqli->query(
    "SELECT (SELECT COUNT(*) FROM acces_falaise WHERE arret_id = $parentId) + (SELECT COUNT(*) FROM train WHERE arret_id = $parentId) AS n"
  )->fetch_assoc()['n'];

  if ($heritierId === null && count($enfants) === 1) $heritierId = $enfants[0];
  if ($heritierId !== null && !in_array($heritierId, $enfants, true)) {
    throw new ArretFusionException(400, "L'héritier doit être un enfant de ce parent");
  }
  if ($heritierId === null && $nbRelations > 0) {
    throw new ArretFusionException(400, "Ce parent porte des accès / trajets train : indiquez l'enfant héritier (heritier_id)");
  }

  if ($heritierId !== null && $nbRelations > 0) arret_reporter_acces_et_trains($mysqli, $parentId, $heritierId);
  $mysqli->query("UPDATE arrets SET parent_id = NULL WHERE parent_id = $parentId");
  $mysqli->query("UPDATE arrets SET deleted = 1 WHERE id = $parentId");
}
