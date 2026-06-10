<?php
/**
 * Lier / délier un arrêt de bus existant à une falaise (table bus_arrets_falaise).
 *
 * Utilisé depuis l'éditeur de détails falaise : « Cet arrêt est pertinent pour
 * cette falaise ».
 *
 * POST /api/link_bus_falaise.php
 * { "arret_id": 1, "falaise_id": 39, "action": "link"|"unlink",
 *   "nom_prenom": "...", "email": "..." }
 */
header('Content-Type: application/json');
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  die(json_encode(["error" => "Corps de requête JSON invalide"]));
}

$arret_id = isset($input['arret_id']) && $input['arret_id'] !== '' ? (int) $input['arret_id'] : null;
$falaise_id = isset($input['falaise_id']) && $input['falaise_id'] !== '' ? (int) $input['falaise_id'] : null;
$action = trim($input['action'] ?? 'link');
$nom_prenom = trim($input['nom_prenom'] ?? '');
$email = trim($input['email'] ?? '');

if (!$arret_id || !$falaise_id) {
  http_response_code(400);
  die(json_encode(["error" => "arret_id et falaise_id sont requis"]));
}
if (!in_array($action, ['link', 'unlink'], true)) {
  http_response_code(400);
  die(json_encode(["error" => "action invalide (link|unlink attendu)"]));
}
if (empty($nom_prenom) || empty($email)) {
  http_response_code(400);
  die(json_encode(["error" => "Nom et email du contributeur obligatoires"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Lookups pour log / mail lisibles
function linkLookupName($mysqli, string $table, string $idCol, string $nameCol, int $id): string
{
  $stmt = $mysqli->prepare("SELECT $nameCol AS n FROM $table WHERE $idCol = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ? (string) $row['n'] : "#$id";
}

$arret_nom = linkLookupName($mysqli, 'bus_arrets', 'id', 'nom', $arret_id);
$falaise_nom = linkLookupName($mysqli, 'falaises', 'falaise_id', 'falaise_nom', $falaise_id);

if ($action === 'link') {
  $stmt = $mysqli->prepare(
    "INSERT IGNORE INTO bus_arrets_falaise (arret_id, falaise_id, contrib, contrib_mail)
     VALUES (?, ?, ?, ?)"
  );
  $stmt->bind_param("iiss", $arret_id, $falaise_id, $nom_prenom, $email);
  $stmt->execute();
  $stmt->close();
  $linked = true;
} else {
  $stmt = $mysqli->prepare(
    "DELETE FROM bus_arrets_falaise WHERE arret_id = ? AND falaise_id = ?"
  );
  $stmt->bind_param("ii", $arret_id, $falaise_id);
  $stmt->execute();
  $stmt->close();
  $linked = false;
}

// --- Log ---
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
logChanges(
  $nom_prenom,
  $email,
  $action === 'link' ? 'insert' : 'delete',
  'bus_arrets_falaise',
  $arret_id,
  $falaise_id,
  ['Arrêt' => $arret_nom, 'Falaise' => $falaise_nom, 'Lien' => $linked ? 'lié' : 'délié']
);

// --- Réponse (avant l'email) ---
echo json_encode(["success" => true, "linked" => $linked]);

// --- Email de notification (léger) ---
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
$verbe = $linked ? "lié à" : "délié de";
$subject = "🚌 Arrêt « $arret_nom » $verbe la falaise « $falaise_nom » par $nom_prenom";
$html = "<html><body>";
$html .= "<p>L'arrêt de bus <b>" . htmlspecialchars($arret_nom) . "</b> a été <b>" . ($linked ? "lié" : "délié") . "</b> "
  . ($linked ? "à" : "de") . " la falaise <b>" . htmlspecialchars($falaise_nom) . "</b> par "
  . htmlspecialchars($nom_prenom) . " (" . htmlspecialchars($email) . ").</p>";
$html .= "<p><a href='https://velogrimpe.fr/falaise.php?falaise_id=$falaise_id'>Voir la falaise</a></p>";
$html .= "</body></html>";

try {
  sendMail([
    'to' => $config["contact_mail"],
    'subject' => $subject,
    'html' => $html,
    'h:Reply-To' => $email,
  ]);
} catch (\Throwable $e) {
  error_log('[link_bus_falaise] sendMail failed: ' . $e->getMessage());
}
