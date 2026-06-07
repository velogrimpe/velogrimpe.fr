<?php
header('Content-Type: application/json');
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

// Payload JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  die(json_encode(["error" => "Corps de requête JSON invalide"]));
}

$admin = trim($input['admin'] ?? '') == $config["admin_token"];
$nom_prenom = trim($input['nom_prenom'] ?? '');
$email = trim($input['email'] ?? '');
$message = trim($input['message'] ?? '');

$arret_id = isset($input['arret_id']) && $input['arret_id'] !== null && $input['arret_id'] !== ''
  ? (int) $input['arret_id'] : null;
$isEdition = $arret_id !== null;

$nom = trim($input['nom'] ?? '');
$loc = trim($input['loc'] ?? '');
$osm_id = trim($input['osm_id'] ?? '');
$osm_id = $osm_id !== '' ? $osm_id : null;
$osm_data = isset($input['osm_data']) && $input['osm_data'] !== null && $input['osm_data'] !== ''
  ? (is_string($input['osm_data']) ? $input['osm_data'] : json_encode($input['osm_data']))
  : null;
$falaise_ids = is_array($input['falaise_ids'] ?? null) ? array_map('intval', $input['falaise_ids']) : [];
$lignes = is_array($input['lignes'] ?? null) ? $input['lignes'] : [];
$liaisons = is_array($input['liaisons'] ?? null) ? $input['liaisons'] : [];

// Validation
if (empty($nom)) {
  http_response_code(400);
  die(json_encode(["error" => "Le nom de l'arrêt est obligatoire"]));
}
$coords = array_map('trim', explode(',', $loc));
if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
  http_response_code(400);
  die(json_encode(["error" => "Coordonnées GPS invalides (format attendu : lat,lng)"]));
}
$lat = (float) $coords[0];
$lng = (float) $coords[1];
$point_wkt = sprintf('POINT(%.7f %.7f)', $lng, $lat); // WKT = (lng lat)

if (empty($nom_prenom) || empty($email)) {
  http_response_code(400);
  die(json_encode(["error" => "Nom et email du contributeur obligatoires"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/richtext.php';
$description = rt_sanitize_html($input['description'] ?? '');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$oldArret = [];
// Entrées de log collectées pendant la transaction, enregistrées après le commit
// (logChanges utilise sa propre connexion, hors transaction).
$logEntries = [];

try {
  $mysqli->begin_transaction();

  // --- 1. Upsert arrêt ---
  if ($isEdition) {
    $stmt = $mysqli->prepare(
      "SELECT nom, description, osm_id, ST_Y(loc) AS lat, ST_X(loc) AS lng FROM bus_arrets WHERE id = ?"
    );
    $stmt->bind_param("i", $arret_id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$old) {
      throw new Exception("Arrêt introuvable");
    }
    $oldArret = [
      "nom" => $old["nom"],
      "description" => $old["description"],
      "osm_id" => $old["osm_id"],
      "loc" => $old["lat"] . "," . $old["lng"],
    ];

    $stmt = $mysqli->prepare(
      "UPDATE bus_arrets
       SET loc = ST_GeomFromText(?, 4326), nom = ?, description = ?, osm_id = ?, osm_data = ?,
           contrib = ?, contrib_mail = ?
       WHERE id = ?"
    );
    $stmt->bind_param("sssssssi", $point_wkt, $nom, $description, $osm_id, $osm_data, $nom_prenom, $email, $arret_id);
    $stmt->execute();
    $stmt->close();
  } else {
    $stmt = $mysqli->prepare(
      "INSERT INTO bus_arrets (loc, nom, description, osm_id, osm_data, contrib, contrib_mail)
       VALUES (ST_GeomFromText(?, 4326), ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sssssss", $point_wkt, $nom, $description, $osm_id, $osm_data, $nom_prenom, $email);
    $stmt->execute();
    $arret_id = $mysqli->insert_id;
    $stmt->close();
  }

  // --- 2. Upsert lignes -> map key => ligne_id ---
  $ligneKeyToId = [];
  foreach ($lignes as $ligne) {
    $key = (string) ($ligne['key'] ?? '');
    $ligne_nom = trim($ligne['nom'] ?? '');
    if ($ligne_nom === '') {
      continue; // ligne sans nom : ignorée
    }
    $ligne_descr = rt_sanitize_html($ligne['description'] ?? '');
    $ligne_lien = trim($ligne['lien'] ?? '');
    $ligne_lien = $ligne_lien !== '' ? $ligne_lien : null;
    $ligne_id = isset($ligne['id']) && $ligne['id'] !== null && $ligne['id'] !== '' ? (int) $ligne['id'] : null;

    $oldLigne = [];
    if ($ligne_id !== null) {
      $sel = $mysqli->prepare("SELECT nom, description, lien FROM bus_lignes WHERE id = ?");
      $sel->bind_param("i", $ligne_id);
      $sel->execute();
      $oldLigne = $sel->get_result()->fetch_assoc() ?: [];
      $sel->close();

      $stmt = $mysqli->prepare(
        "UPDATE bus_lignes SET nom = ?, description = ?, lien = ?, contrib = ?, contrib_mail = ? WHERE id = ?"
      );
      $stmt->bind_param("sssssi", $ligne_nom, $ligne_descr, $ligne_lien, $nom_prenom, $email, $ligne_id);
      $stmt->execute();
      $stmt->close();
    } else {
      $stmt = $mysqli->prepare(
        "INSERT INTO bus_lignes (nom, description, lien, contrib, contrib_mail) VALUES (?, ?, ?, ?, ?)"
      );
      $stmt->bind_param("sssss", $ligne_nom, $ligne_descr, $ligne_lien, $nom_prenom, $email);
      $stmt->execute();
      $ligne_id = $mysqli->insert_id;
      $stmt->close();
    }
    if ($key !== '') {
      $ligneKeyToId[$key] = $ligne_id;
    }
    $logEntries[] = [
      'type' => $oldLigne ? 'update' : 'insert',
      'collection' => 'bus_lignes',
      'id' => $ligne_id,
      'new' => ['nom' => $ligne_nom, 'description' => $ligne_descr, 'lien' => $ligne_lien],
      'old' => $oldLigne,
    ];
  }

  // --- 3. Liaisons (upsert) ---
  foreach ($liaisons as $liaison) {
    $arret_2_id = isset($liaison['arret_2_id']) && $liaison['arret_2_id'] !== '' ? (int) $liaison['arret_2_id'] : null;
    $ligne_key = (string) ($liaison['ligne_key'] ?? '');
    $resolved_ligne_id = $ligneKeyToId[$ligne_key] ?? null;
    // fallback : ligne_id direct fourni
    if ($resolved_ligne_id === null && isset($liaison['ligne_id']) && $liaison['ligne_id'] !== '' && $liaison['ligne_id'] !== null) {
      $resolved_ligne_id = (int) $liaison['ligne_id'];
    }
    if (!$arret_2_id || !$resolved_ligne_id || $arret_2_id === $arret_id) {
      continue; // liaison incomplète ou auto-référence : ignorée
    }
    $liaison_descr = rt_sanitize_html($liaison['description'] ?? '');
    $liaison_id = isset($liaison['id']) && $liaison['id'] !== null && $liaison['id'] !== '' ? (int) $liaison['id'] : null;

    $oldLiaison = [];
    if ($liaison_id !== null) {
      $sel = $mysqli->prepare(
        "SELECT arret_1_id, arret_2_id, ligne_id, description FROM bus_liaisons WHERE id = ?"
      );
      $sel->bind_param("i", $liaison_id);
      $sel->execute();
      $oldLiaison = $sel->get_result()->fetch_assoc() ?: [];
      $sel->close();

      $stmt = $mysqli->prepare(
        "UPDATE bus_liaisons SET arret_1_id = ?, arret_2_id = ?, ligne_id = ?, description = ?, contrib = ?, contrib_mail = ? WHERE id = ?"
      );
      $stmt->bind_param("iiisssi", $arret_id, $arret_2_id, $resolved_ligne_id, $liaison_descr, $nom_prenom, $email, $liaison_id);
      $stmt->execute();
      $stmt->close();
    } else {
      $stmt = $mysqli->prepare(
        "INSERT INTO bus_liaisons (arret_1_id, arret_2_id, ligne_id, description, contrib, contrib_mail)
         VALUES (?, ?, ?, ?, ?, ?)"
      );
      $stmt->bind_param("iiisss", $arret_id, $arret_2_id, $resolved_ligne_id, $liaison_descr, $nom_prenom, $email);
      $stmt->execute();
      $liaison_id = $mysqli->insert_id;
      $stmt->close();
    }
    $logEntries[] = [
      'type' => $oldLiaison ? 'update' : 'insert',
      'collection' => 'bus_liaisons',
      'id' => $liaison_id,
      'new' => [
        'arret_1_id' => $arret_id,
        'arret_2_id' => $arret_2_id,
        'ligne_id' => $resolved_ligne_id,
        'description' => $liaison_descr,
      ],
      'old' => $oldLiaison,
    ];
  }

  // --- 4. Liens falaises (remplacement du set) ---
  // Ancien set (pour le log)
  $oldFalaiseIds = [];
  $sel = $mysqli->prepare("SELECT falaise_id FROM bus_arrets_falaise WHERE arret_id = ? ORDER BY falaise_id");
  $sel->bind_param("i", $arret_id);
  $sel->execute();
  $r = $sel->get_result();
  while ($row = $r->fetch_assoc()) {
    $oldFalaiseIds[] = (int) $row['falaise_id'];
  }
  $sel->close();

  $stmt = $mysqli->prepare("DELETE FROM bus_arrets_falaise WHERE arret_id = ?");
  $stmt->bind_param("i", $arret_id);
  $stmt->execute();
  $stmt->close();
  $newFalaiseIds = array_values(array_unique($falaise_ids));
  if (!empty($newFalaiseIds)) {
    $stmt = $mysqli->prepare(
      "INSERT INTO bus_arrets_falaise (arret_id, falaise_id, contrib, contrib_mail) VALUES (?, ?, ?, ?)"
    );
    foreach ($newFalaiseIds as $fid) {
      $stmt->bind_param("iiss", $arret_id, $fid, $nom_prenom, $email);
      $stmt->execute();
    }
    $stmt->close();
  }
  // Log uniquement si le set a changé
  sort($oldFalaiseIds);
  $sortedNew = $newFalaiseIds;
  sort($sortedNew);
  if ($oldFalaiseIds !== $sortedNew) {
    $logEntries[] = [
      'type' => empty($oldFalaiseIds) ? 'insert' : 'update',
      'collection' => 'bus_arrets_falaise',
      'id' => $arret_id,
      'new' => ['falaise_ids' => implode(',', $sortedNew)],
      'old' => ['falaise_ids' => implode(',', $oldFalaiseIds)],
    ];
  }

  $mysqli->commit();
} catch (Throwable $e) {
  $mysqli->rollback();
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'enregistrement : " . $e->getMessage()]));
}

// --- Logging ---
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$newArret = [
  "nom" => $nom,
  "description" => $description,
  "osm_id" => $osm_id,
  "osm_data" => $osm_data,
  "loc" => $lat . "," . $lng,
];
// Résolution id -> nom (avec cache) pour rendre les logs/mails lisibles.
function busLookupName($mysqli, string $table, string $idCol, string $nameCol, $id): string
{
  static $cache = [];
  if ($id === null || $id === '' || (int) $id === 0) {
    return '';
  }
  $id = (int) $id;
  $ck = "$table.$id";
  if (isset($cache[$ck])) {
    return $cache[$ck];
  }
  $stmt = $mysqli->prepare("SELECT $nameCol AS n FROM $table WHERE $idCol = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $cache[$ck] = $row ? (string) $row['n'] : "#$id";
}

// Remplace les ids par des libellés lisibles dans un jeu de valeurs (new/old) à logger.
function busHumanizeValues($mysqli, string $collection, array $values): array
{
  if (empty($values)) {
    return $values;
  }
  if ($collection === 'bus_liaisons') {
    $out = [];
    if (array_key_exists('arret_2_id', $values)) {
      $out['Arrêt relié'] = busLookupName($mysqli, 'bus_arrets', 'id', 'nom', $values['arret_2_id']);
    }
    if (array_key_exists('ligne_id', $values)) {
      $out['Ligne'] = busLookupName($mysqli, 'bus_lignes', 'id', 'nom', $values['ligne_id']);
    }
    if (array_key_exists('description', $values)) {
      $out['Description'] = $values['description'];
    }
    return $out;
  }
  if ($collection === 'bus_arrets_falaise') {
    $out = [];
    if (array_key_exists('falaise_ids', $values)) {
      $ids = array_filter(array_map('trim', explode(',', (string) $values['falaise_ids'])), 'strlen');
      $names = array_map(fn($id) => busLookupName($mysqli, 'falaises', 'falaise_id', 'falaise_nom', $id), $ids);
      $out['Falaises'] = implode(', ', $names);
    }
    return $out;
  }
  return $values;
}

$type = $isEdition ? "update" : "insert";
$arretChangesJson = logChanges($nom_prenom, $email, $type, 'bus_arrets', $arret_id, null, $newArret, $oldArret);

// Log des entités liées (lignes, liaisons, liens falaises) + capture des diffs pour le mail
$loggedEntries = [];
foreach ($logEntries as $entry) {
  $newVals = busHumanizeValues($mysqli, $entry['collection'], $entry['new']);
  $oldVals = busHumanizeValues($mysqli, $entry['collection'], $entry['old']);
  $changesJson = logChanges($nom_prenom, $email, $entry['type'], $entry['collection'], $entry['id'], null, $newVals, $oldVals);
  $loggedEntries[] = ['entry' => $entry, 'changesJson' => $changesJson];
}

// --- Réponse (avant l'email) ---
echo json_encode(["success" => true, "arret_id" => $arret_id]);

// --- Email de notification ---
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
$to = $admin ? $config["admin_mail"] : $config["contact_mail"];
$subject = "🚌 Arrêt de bus '$nom' " . ($isEdition ? "modifié" : "ajouté") . " par $nom_prenom";

// Rend un bloc <ul> des changements (field : ancien → nouveau) à partir d'un JSON de logChanges.
function renderBusChanges(?string $changesJson): string
{
  $changes = $changesJson ? json_decode($changesJson, true) : [];
  if (empty($changes)) {
    return "<p><i>Aucune modification.</i></p>";
  }
  $h = "<ul>";
  foreach ($changes as $c) {
    $field = htmlspecialchars($c['field']);
    $old = htmlspecialchars((string) $c['old']);
    $new = htmlspecialchars((string) $c['new']);
    $h .= "<li><b>$field</b> : <span style='color:red;'>" . ($old === '' ? '∅' : $old)
      . "</span> → <span style='color:green;'>" . ($new === '' ? '∅' : $new) . "</span></li>";
  }
  $h .= "</ul>";
  return $h;
}

// Libellés lisibles par collection
$collectionLabels = [
  'bus_lignes' => 'Ligne',
  'bus_liaisons' => 'Liaison',
  'bus_arrets_falaise' => 'Falaises liées',
];

$html = "<html><body>";
$html .= "<h1>L'arrêt de bus « " . htmlspecialchars($nom) . " » a été " . ($isEdition ? "modifié" : "ajouté") . " par " . htmlspecialchars($nom_prenom) . "</h1>";
$html .= "<p>email : <a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></p>";
$html .= "<p><b>Coordonnées</b> : " . htmlspecialchars($lat . "," . $lng);
if ($osm_id) {
  $html .= " — <b>OSM</b> : " . htmlspecialchars($osm_id);
}
$html .= "</p>";

// Détail des modifications de l'arrêt
$html .= "<h2>Arrêt</h2>";
$html .= renderBusChanges($arretChangesJson);

// Détail des modifications des entités liées (lignes, liaisons, falaises)
if (!empty($loggedEntries)) {
  $html .= "<h2>Lignes, liaisons et falaises</h2>";
  foreach ($loggedEntries as $logged) {
    $entry = $logged['entry'];
    $label = $collectionLabels[$entry['collection']] ?? $entry['collection'];
    // Précision du libellé selon le type d'entité
    if ($entry['collection'] === 'bus_lignes') {
      $label .= " « " . htmlspecialchars($entry['new']['nom'] ?? '') . " »";
    } elseif ($entry['collection'] === 'bus_liaisons') {
      $label .= " vers « " . htmlspecialchars(busLookupName($mysqli, 'bus_arrets', 'id', 'nom', $entry['new']['arret_2_id'] ?? null)) . " »";
    }
    $verb = $entry['type'] === 'insert' ? 'ajouté(e)' : 'modifié(e)';
    $html .= "<h3>$label <span style='color:#888;font-weight:normal;'>($verb)</span></h3>";
    $html .= renderBusChanges($logged['changesJson']);
  }
}

if ($message) {
  $html .= "<p>Message additionnel : " . nl2br(htmlspecialchars(trim($message))) . "</p>";
}
$html .= "<h2>Actions</h2>";
$html .= "<p><a href='https://velogrimpe.fr/ajout/ajout_bus.php?admin=" . urlencode($config["admin_token"]) . "&arret_id=$arret_id'>Modifier l'arrêt</a></p>";
$html .= "</body></html>";

sendMail([
  'to' => $to,
  'subject' => $subject,
  'html' => $html,
  'h:Reply-To' => $email,
]);
