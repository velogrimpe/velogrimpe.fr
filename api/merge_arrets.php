<?php
/**
 * Fusion a posteriori de deux arrêts (admin) : `source_id` est absorbé par `cible_id`.
 * POST JSON { "source_id": int, "cible_id": int }, en-tête `Authorization: Bearer <admin_token>`.
 *
 * Reportés sur la cible : accès aux falaises (acces_falaise), liens vers les lignes
 * (arrets_lignes_transport), références externes, trajets train, types d'arrêt (OU des
 * drapeaux ter/tgv/bus/tram/teleph). La source est ensuite supprimée logiquement.
 * Si les deux arrêts ont un accès identique (même falaise, type et variante), la fusion est
 * refusée (409) avec la liste des accès en cause : rien n'est modifié.
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
$source = (int) ($input['source_id'] ?? 0);
$cible = (int) ($input['cible_id'] ?? 0);
if ($source <= 0 || $cible <= 0 || $source === $cible) {
  http_response_code(400);
  die(json_encode(["error" => "Deux arrêts distincts sont requis"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

function fail(int $code, string $msg): never
{
  http_response_code($code);
  die(json_encode(["error" => $msg]));
}

$mysqli->begin_transaction();
try {
  $stmt = $mysqli->prepare("SELECT id, nom, deleted FROM arrets WHERE id IN (?, ?) FOR UPDATE");
  $stmt->bind_param("ii", $source, $cible);
  $stmt->execute();
  $rows = [];
  foreach ($stmt->get_result() as $r) $rows[(int) $r['id']] = $r;
  $stmt->close();
  if (count($rows) !== 2) fail(404, "Arrêt introuvable");
  if ($rows[$cible]['deleted']) fail(409, "L'arrêt à conserver est supprimé");
  $nom_source = $rows[$source]['nom'];

  // Accès aux falaises : un accès de la source identique (falaise, type, variante) à un accès de la
  // cible ne peut pas être reporté (unicité) → fusion refusée, à l'admin de trancher d'abord.
  $stmt = $mysqli->prepare(
    "SELECT DISTINCT f.falaise_nom, s.type, s.variante FROM acces_falaise s
     JOIN acces_falaise c ON c.arret_id = ? AND c.falaise_id = s.falaise_id AND c.type = s.type AND c.variante = s.variante
     JOIN falaises f ON f.falaise_id = s.falaise_id
     WHERE s.arret_id = ? ORDER BY f.falaise_nom",
  );
  $stmt->bind_param("ii", $cible, $source);
  $stmt->execute();
  $conflits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  if ($conflits) {
    $mysqli->rollback();
    $liste = array_map(
      fn($c) => $c['falaise_nom'] . ' (' . $c['type'] . ($c['variante'] !== '' ? ', variante « ' . $c['variante'] . ' »' : '') . ')',
      $conflits,
    );
    fail(409, "Fusion refusée : les deux arrêts ont déjà un accès identique vers " . implode(', ', $liste)
      . ". Supprimez ou renommez (variante) l'un des accès doublons, puis recommencez.");
  }
  $stmt = $mysqli->prepare("UPDATE acces_falaise SET arret_id = ? WHERE arret_id = ?");
  $stmt->bind_param("ii", $cible, $source);
  $stmt->execute();
  $nb_acces = $stmt->affected_rows;
  $stmt->close();

  // Lignes et références externes : unicité (arret, ligne) / (arret, source, ref) → on ignore les doublons.
  foreach (["arrets_lignes_transport", "references_externes"] as $table) {
    $stmt = $mysqli->prepare("UPDATE IGNORE $table SET arret_id = ? WHERE arret_id = ?");
    $stmt->bind_param("ii", $cible, $source);
    $stmt->execute();
    $stmt->close();
    $stmt = $mysqli->prepare("DELETE FROM $table WHERE arret_id = ?");
    $stmt->bind_param("i", $source);
    $stmt->execute();
    $stmt->close();
  }

  $stmt = $mysqli->prepare("UPDATE train SET arret_id = ? WHERE arret_id = ?");
  $stmt->bind_param("ii", $cible, $source);
  $stmt->execute();
  $stmt->close();

  $stmt = $mysqli->prepare(
    "UPDATE arrets t JOIN arrets s ON s.id = ?
     SET t.ter = GREATEST(t.ter, s.ter), t.tgv = GREATEST(t.tgv, s.tgv), t.bus = GREATEST(t.bus, s.bus),
         t.tram = GREATEST(t.tram, s.tram), t.teleph = GREATEST(t.teleph, s.teleph)
     WHERE t.id = ?",
  );
  $stmt->bind_param("ii", $source, $cible);
  $stmt->execute();
  $stmt->close();

  $stmt = $mysqli->prepare("UPDATE arrets SET deleted = 1 WHERE id = ?");
  $stmt->bind_param("i", $source);
  $stmt->execute();
  $stmt->close();

  $mysqli->commit();
  echo json_encode(["success" => true, "acces_deplaces" => $nb_acces]);
} catch (Throwable $e) {
  $mysqli->rollback();
  fail(500, "Fusion impossible : " . $e->getMessage());
}
