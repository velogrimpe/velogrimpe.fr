<?php
/**
 * Supprime une ligne (admin). POST JSON { "ligne_id": int, "supprimer_arrets"?: bool }, Bearer admin_token.
 * Avec `supprimer_arrets`, les arrêts de la ligne sont aussi supprimés DÉFINITIVEMENT (références GTFS et liens
 * aux lignes en cascade, exclusions de villes nettoyées) s'ils ne sont reliés à aucune falaise ni à aucun trajet
 * train (ni eux ni leur parent) et ne desservent aucune autre ligne ; un parent dont tous les enfants sont
 * supprimés l'est aussi. Les autres sont conservés.
 * Réponse : { success, arrets_supprimes, arrets_conserves }.
 */
header('Content-Type: application/json');
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if ($authHeader !== 'Bearer ' . $config["admin_token"]) {
  http_response_code(401);
  die(json_encode(["error" => "Non autorisé"]));
}

$input = json_decode(file_get_contents('php://input'), true);
$ligne_id = (int) ($input['ligne_id'] ?? 0);
$supprimer_arrets = ($input['supprimer_arrets'] ?? false) === true;
if ($ligne_id <= 0) {
  http_response_code(400);
  die(json_encode(["error" => "Un id de ligne est requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_parents_lib.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli->begin_transaction();
try {
  $stmt = $mysqli->prepare("SELECT id FROM lignes_transport WHERE id = ? FOR UPDATE");
  $stmt->bind_param("i", $ligne_id);
  $stmt->execute();
  $existe = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$existe) {
    $mysqli->rollback();
    http_response_code(404);
    die(json_encode(["error" => "Ligne introuvable"]));
  }

  // Arrêts de la ligne à supprimer : à évaluer AVANT la suppression de la ligne (les liens partent en cascade)
  $a_supprimer = [];
  $nb_arrets = 0;
  if ($supprimer_arrets) {
    $stmt = $mysqli->prepare(
      "SELECT a.id, a.parent_id,
         NOT EXISTS (SELECT 1 FROM acces_falaise af WHERE af.arret_id = COALESCE(a.parent_id, a.id))
         AND NOT EXISTS (SELECT 1 FROM train t WHERE t.arret_id = COALESCE(a.parent_id, a.id))
         AND NOT EXISTS (SELECT 1 FROM arrets_lignes_transport o WHERE o.arret_id = a.id AND o.ligne_id <> alt.ligne_id)
         AND NOT EXISTS (SELECT 1 FROM arrets c WHERE c.parent_id = a.id AND c.deleted = 0) AS supprimable
       FROM arrets_lignes_transport alt JOIN arrets a ON a.id = alt.arret_id
       WHERE alt.ligne_id = ? AND a.deleted = 0"
    );
    $stmt->bind_param("i", $ligne_id);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
      $nb_arrets++;
      if ($r['supprimable']) $a_supprimer[(int) $r['id']] = $r['parent_id'] !== null ? (int) $r['parent_id'] : null;
    }
    $stmt->close();
  }

  // arrets_lignes_transport est en ON DELETE CASCADE
  $stmt = $mysqli->prepare("DELETE FROM lignes_transport WHERE id = ?");
  $stmt->bind_param("i", $ligne_id);
  $stmt->execute();
  $stmt->close();

  $supprimes = 0;
  if ($a_supprimer) {
    $ids = array_keys($a_supprimer);
    // Enfants d'abord (clé parent_id sans cascade) ; acces_falaise / train n'en portent pas (vérifié ci-dessus)
    $mysqli->query("DELETE FROM arrets WHERE id IN (" . implode(',', $ids) . ")");
    $supprimes = count($ids);
    foreach (array_unique(array_filter($a_supprimer)) as $parent) {
      if (arret_nb_enfants($mysqli, $parent) === 0) {
        // Plus d'enfant actif : on détache d'éventuels enfants déjà supprimés logiquement, puis le parent part
        $mysqli->query("UPDATE arrets SET parent_id = NULL WHERE parent_id = $parent");
        $mysqli->query("DELETE FROM arrets WHERE id = $parent");
        $ids[] = $parent;
        $supprimes++;
      } else {
        arret_recalculer_parent($mysqli, $parent);
      }
    }
    // Pas de clé étrangère sur ces tables : on retire les lignes des arrêts disparus
    $in = implode(',', $ids);
    $mysqli->query("DELETE FROM exclusions_villes_gares WHERE gare_id IN ($in)");
    $mysqli->query("DELETE FROM exclusions_villes_gares_falaises WHERE gare_id IN ($in)");
  }
  $mysqli->commit();
  echo json_encode(["success" => true, "arrets_supprimes" => $supprimes, "arrets_conserves" => $nb_arrets - count($a_supprimer)]);
} catch (Throwable $e) {
  $mysqli->rollback();
  error_log('[delete_ligne] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(["error" => "Suppression impossible"]);
}
