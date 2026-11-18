<?php
/**
 * Modifie les informations de base d'une ligne (admin) : nom, numéro, couleur, référence, opérateur, site de
 * l'opérateur, horaires. Contrairement à api/add_ligne.php (qui remplace aussi la géométrie et les arrêts liés),
 * seuls ces champs sont touchés ; un champ vide est remis à NULL (sauf le nom, obligatoire).
 * POST JSON { "ligne_id": int, "nom": string, "numero"?, "couleur"?, "ref"?, "operateur"?, "operateur_url"?, "horaire_url"? },
 * en-tête `Authorization: Bearer <admin_token>`.
 * Réponse : { success, ligne }. 400 champ invalide (nom vide, URL non http(s), texte trop long), 404 introuvable.
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/lignes_lib.php';

$input = json_decode(file_get_contents('php://input'), true);
$ligne_id = (int) ($input['ligne_id'] ?? 0);
$nom = trim((string) ($input['nom'] ?? ''));
if ($ligne_id <= 0 || $nom === '') {
  http_response_code(400);
  die(json_encode(["error" => "Un id de ligne et un nom sont requis"]));
}

// Longueurs des colonnes varchar(64) ; les URL doivent être http(s)
const UPDATE_LIGNE_MAX = ['numero' => 64, 'operateur' => 64, 'couleur' => 64];
$fields = [];
foreach (LIGNE_TEXT_FIELDS as $f) {
  $v = trim((string) ($input[$f] ?? ''));
  if (isset(UPDATE_LIGNE_MAX[$f]) && mb_strlen($v) > UPDATE_LIGNE_MAX[$f]) {
    http_response_code(400);
    die(json_encode(["error" => "Champ « $f » trop long (" . UPDATE_LIGNE_MAX[$f] . " caractères au plus)"]));
  }
  if (str_ends_with($f, '_url') && $v !== '' && !preg_match('#^https?://\S+$#i', $v)) {
    http_response_code(400);
    die(json_encode(["error" => "Champ « $f » : une URL http(s) est attendue"]));
  }
  $fields[$f] = $v !== '' ? $v : null;
}

$stmt = $mysqli->prepare("SELECT id FROM lignes_transport WHERE id = ?");
$stmt->bind_param("i", $ligne_id);
$stmt->execute();
$existe = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$existe) {
  http_response_code(404);
  die(json_encode(["error" => "Ligne introuvable"]));
}

$stmt = $mysqli->prepare(
  "UPDATE lignes_transport SET nom = ?, numero = ?, operateur = ?, operateur_url = ?, horaire_url = ?, ref = ?, couleur = ? WHERE id = ?"
);
$stmt->bind_param(
  "sssssssi",
  $nom, $fields['numero'], $fields['operateur'], $fields['operateur_url'], $fields['horaire_url'], $fields['ref'], $fields['couleur'], $ligne_id
);
$stmt->execute();
$stmt->close();

echo json_encode(["success" => true, "ligne" => ["id" => $ligne_id, "nom" => $nom] + $fields]);
