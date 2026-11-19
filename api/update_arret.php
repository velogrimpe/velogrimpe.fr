<?php
/**
 * Modifie le nom et les modalités d'un arrêt (admin), sans passer par le formulaire d'ajout : met à jour `nom` et
 * `slug` (comme l'édition de ajout/add_arret.php) et `ter/tgv/bus/tram/metro/teleph`.
 * Un parent se renomme comme n'importe quel arrêt (ses enfants gardent leur nom) mais ses modalités sont celles de
 * ses enfants : les changer est refusé (409). Changer celles d'un enfant recalcule son parent.
 * POST JSON { "arret_id": int, "nom": string, "types": ["ter"|"tgv"|"bus"|"tram"|"metro"|"telepherique", …] },
 * en-tête `Authorization: Bearer <admin_token>`.
 * Réponse : { success, nom, types }. 400 nom vide / trop long ou modalités invalides, 404 introuvable, 409 supprimé
 * ou modalités d'un parent.
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

const UPDATE_ARRET_COLONNES = ['ter' => 'ter', 'tgv' => 'tgv', 'bus' => 'bus', 'tram' => 'tram', 'metro' => 'metro', 'telepherique' => 'teleph'];

$input = json_decode(file_get_contents('php://input'), true);
$arret_id = (int) ($input['arret_id'] ?? 0);
$nom = trim((string) ($input['nom'] ?? ''));
$types = $input['types'] ?? null;
if ($arret_id <= 0 || $nom === '' || mb_strlen($nom) > 255) {
  http_response_code(400);
  die(json_encode(["error" => "Un id d'arrêt et un nom (255 caractères au plus) sont requis"]));
}
if (!is_array($types) || !$types || array_diff($types, array_keys(UPDATE_ARRET_COLONNES))) {
  http_response_code(400);
  die(json_encode(["error" => "Au moins une modalité valide (ter, tgv, bus, tram, metro, telepherique) est requise"]));
}
$types = array_values(array_unique($types));
$flags = [];
foreach (UPDATE_ARRET_COLONNES as $type => $col) $flags[$col] = (int) in_array($type, $types, true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/arrets_parents_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$fin = function (int $http, string $erreur) use ($mysqli): never {
  $mysqli->rollback();
  http_response_code($http);
  die(json_encode(["error" => $erreur]));
};

try {
  $mysqli->begin_transaction();
  $old = arrets_charger_verrouilles($mysqli, [$arret_id])[$arret_id] ?? null;
  if (!$old) $fin(404, "Arrêt introuvable");
  if ($old['deleted']) $fin(409, "Cet arrêt est supprimé");

  $changeTypes = false;
  foreach ($flags as $col => $v) $changeTypes = $changeTypes || (int) $old[$col] !== $v;
  if ($changeTypes) {
    if (arret_nb_enfants($mysqli, $arret_id) > 0) {
      $fin(409, "Les modalités d'un arrêt parent sont celles de ses enfants : modifiez celles des enfants");
    }
  }

  $slug = $nom !== $old['nom'] ? arret_slugify($nom) : $old['slug'];
  if ($nom !== $old['nom'] || $changeTypes) {
    $stmt = $mysqli->prepare("UPDATE arrets SET nom = ?, slug = ?, ter = ?, tgv = ?, bus = ?, tram = ?, metro = ?, teleph = ? WHERE id = ?");
    $stmt->bind_param("ssiiiiiii", $nom, $slug, $flags['ter'], $flags['tgv'], $flags['bus'], $flags['tram'], $flags['metro'], $flags['teleph'], $arret_id);
    $stmt->execute();
    $stmt->close();
    if ($changeTypes && $old['parent_id'] !== null) arret_recalculer_parent($mysqli, (int) $old['parent_id']);
  }
  $mysqli->commit();
} catch (Throwable $e) {
  error_log('[update_arret] ' . $e->getMessage());
  try { $mysqli->rollback(); } catch (Throwable $e2) {}
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'enregistrement de l'arrêt"]));
}

if ($nom !== $old['nom'] || $changeTypes) {
  $nouveau = ["nom" => $nom, "slug" => $slug] + $flags;
  $ancien = ["nom" => $old["nom"], "slug" => $old["slug"], "ter" => $old["ter"], "tgv" => $old["tgv"], "bus" => $old["bus"], "tram" => $old["tram"], "metro" => $old["metro"], "teleph" => $old["teleph"]];
  logChanges("Admin", "", "update", "arrets", $arret_id, null, $nouveau, $ancien);
}
echo json_encode(["success" => true, "nom" => $nom, "types" => $types]);
