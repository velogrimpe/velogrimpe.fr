<?php
// Allow CORS from all origins
header('Access-Control-Allow-Origin: localhost:4002, https://velogrimpe.fr, https://www.velogrimpe.fr');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}
// Allow only GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

$falaise_id = trim($_GET['falaise_id'] ?? '');
if (empty($falaise_id)) {
  die("falaise_id is required");
}
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';

function summarizeFalaiseDetailsChanges($oldFeatures, $newFeatures)
{
  $typeOf = fn($f) => $f['properties']['type'] ?? 'secteur';
  $idOf = fn($f) => $f['properties']['id'] ?? null;

  $contentSignature = function ($f) {
    $props = $f['properties'] ?? [];
    ksort($props);
    return json_encode(['p' => $props, 'g' => $f['geometry'] ?? null]);
  };

  $labelOf = function ($f) {
    $name = trim((string)($f['properties']['name'] ?? ''));
    if ($name !== '') return $name;
    $id = $f['properties']['id'] ?? null;
    if (is_string($id) && $id !== '') return '#' . substr($id, 0, 8);
    return '?';
  };

  $perType = [];
  $touch = function ($type) use (&$perType) {
    if (!isset($perType[$type])) {
      $perType[$type] = ['old' => 0, 'new' => 0, 'edited' => []];
    }
  };
  foreach ($oldFeatures as $f) {
    $t = $typeOf($f);
    $touch($t);
    $perType[$t]['old']++;
  }
  foreach ($newFeatures as $f) {
    $t = $typeOf($f);
    $touch($t);
    $perType[$t]['new']++;
  }
  ksort($perType);

  // Pre-migration files have no ids yet — the first save after the migration
  // assigns them client-side. We fall back to the initial simple heuristic:
  // match by (type, name) for named features, bag-count by type for the rest.
  $oldHasIds = false;
  foreach ($oldFeatures as $f) {
    if ($idOf($f) !== null) { $oldHasIds = true; break; }
  }
  if (!$oldHasIds && !empty($oldFeatures)) {
    $nameOf = fn($f) => trim((string)($f['properties']['name'] ?? ''));
    $partition = function ($features) use ($typeOf, $nameOf) {
      $named = [];
      $unnamed = [];
      foreach ($features as $f) {
        $name = $nameOf($f);
        if ($name !== '') {
          $key = $typeOf($f) . '|' . mb_strtolower($name);
          $named[$key] = $f;
        } else {
          $unnamed[$typeOf($f)] = ($unnamed[$typeOf($f)] ?? 0) + 1;
        }
      }
      return [$named, $unnamed];
    };
    [$oldNamed, $oldUnnamed] = $partition($oldFeatures);
    [$newNamed, $newUnnamed] = $partition($newFeatures);

    $added = 0; $removed = 0; $modified = 0;
    foreach ($oldNamed as $key => $oldFeature) {
      if (isset($newNamed[$key])) {
        if ($contentSignature($oldFeature) !== $contentSignature($newNamed[$key])) {
          $modified++;
          $perType[$typeOf($oldFeature)]['edited'][] = $labelOf($newNamed[$key]);
        }
        unset($newNamed[$key]);
      } else {
        $removed++;
      }
    }
    $added += count($newNamed);
    $types = array_unique(array_merge(array_keys($oldUnnamed), array_keys($newUnnamed)));
    foreach ($types as $t) {
      $o = $oldUnnamed[$t] ?? 0;
      $n = $newUnnamed[$t] ?? 0;
      if ($n > $o) $added += $n - $o;
      elseif ($o > $n) $removed += $o - $n;
    }
    return [
      'preMigration' => true,
      'added' => $added,
      'removed' => $removed,
      'modified' => $modified,
      'perType' => $perType,
    ];
  }

  $oldById = [];
  foreach ($oldFeatures as $f) {
    $id = $idOf($f);
    if ($id !== null) $oldById[$id] = $f;
  }
  $newById = [];
  foreach ($newFeatures as $f) {
    $id = $idOf($f);
    if ($id !== null) $newById[$id] = $f;
  }

  $added = 0;
  $removed = 0;
  $modified = 0;

  foreach ($oldById as $id => $oldFeature) {
    if (isset($newById[$id])) {
      if ($contentSignature($oldFeature) !== $contentSignature($newById[$id])) {
        $modified++;
        $perType[$typeOf($newById[$id])]['edited'][] = $labelOf($newById[$id]);
      }
      unset($newById[$id]);
    } else {
      $removed++;
    }
  }
  $added += count($newById);

  return [
    'preMigration' => false,
    'added' => $added,
    'removed' => $removed,
    'modified' => $modified,
    'perType' => $perType,
  ];
}

function renderChangeSummaryHtml($summary, $isUpdate)
{
  $typeLabels = [
    'secteur' => 'Secteurs',
    'approche' => 'Approches',
    'parking' => 'Parkings',
    'bus_stop' => 'Arrêts de bus',
    'acces_velo' => 'Accès vélo',
    'falaise_voisine' => 'Falaises voisines',
  ];

  $html = '<h2>Bilan des modifications</h2>';

  if (!$isUpdate) {
    $html .= '<p><em>Première création des détails de cette falaise.</em></p>';
  } elseif (!empty($summary['preMigration'])) {
    $html .= '<p><em>Premier enregistrement après attribution des identifiants — bilan détaillé non disponible.</em></p>';
  } else {
    $html .= '<p>';
    $html .= '<strong>' . (int)$summary['added'] . '</strong> ajoutée(s), ';
    $html .= '<strong>' . (int)$summary['removed'] . '</strong> supprimée(s), ';
    $html .= '<strong>' . (int)$summary['modified'] . '</strong> modifiée(s).';
    $html .= '</p>';
  }

  if (!empty($summary['perType'])) {
    $html .= '<table style="border-collapse:collapse" cellpadding="6">';
    $html .= '<thead><tr>'
      . '<th style="border:1px solid #ccc;text-align:left">Type</th>'
      . '<th style="border:1px solid #ccc;text-align:right">Avant</th>'
      . '<th style="border:1px solid #ccc;text-align:right">Après</th>'
      . '<th style="border:1px solid #ccc;text-align:right">Δ</th>'
      . '<th style="border:1px solid #ccc;text-align:left">Édités</th>'
      . '</tr></thead><tbody>';
    foreach ($summary['perType'] as $type => $counts) {
      $label = $typeLabels[$type] ?? $type;
      $delta = $counts['new'] - $counts['old'];
      $deltaStr = $delta > 0 ? '+' . $delta : (string)$delta;
      $edited = $counts['edited'] ?? [];
      $editedHtml = empty($edited)
        ? '<span style="color:#999">—</span>'
        : htmlspecialchars(implode(', ', $edited));
      $html .= '<tr>'
        . '<td style="border:1px solid #ccc">' . htmlspecialchars($label) . '</td>'
        . '<td style="border:1px solid #ccc;text-align:right">' . (int)$counts['old'] . '</td>'
        . '<td style="border:1px solid #ccc;text-align:right">' . (int)$counts['new'] . '</td>'
        . '<td style="border:1px solid #ccc;text-align:right">' . htmlspecialchars($deltaStr) . '</td>'
        . '<td style="border:1px solid #ccc">' . $editedHtml . '</td>'
        . '</tr>';
    }
    $html .= '</tbody></table>';
  }

  return $html;
}

// Prepare the SQL statement
$stmt = $mysqli->prepare("SELECT
falaise_id, falaise_nomformate, falaise_nom
FROM falaises
WHERE falaise_id = ?"
);
if (!$stmt) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
// Bind the parameter
$stmt->bind_param("s", $falaise_id);
if (!$stmt) {
  die("Problème de liaison des paramètres : " . $mysqli->error);
}
// Execute the statement
$stmt->execute();
if ($stmt->error) {
  die("Erreur lors de l'exécution de la requête : " . $stmt->error);
}
// Get the result
$result = $stmt->get_result();
if ($stmt->error) {
  die("Erreur lors de la récupération du résultat : " . $stmt->error);
}
// Fetch the results
$falaise = $result->fetch_assoc();
if (!$falaise) {
  http_response_code(404);
  echo json_encode(['error' => 'Falaise not found']);
  exit;
}
// Close the statement
$stmt->close();
// Close the database connection
$mysqli->close();

// Check existance of falaise details geojson file and load it if exists
$geojson_file = $_SERVER['DOCUMENT_ROOT'] . "/bdd/barres/" . $falaise["falaise_id"] . "_" . $falaise["falaise_nomformate"] . ".geojson";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

  if (file_exists($geojson_file)) {
    $geojson_content = file_get_contents($geojson_file);
    $geojson = json_decode($geojson_content, true);
  } else {
    $geojson = ["type" => "FeatureCollection", "features" => []];
  }

  // Return the result as JSON
  echo json_encode($geojson);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Get Authorization header
  $headers = getallheaders();
  $authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;

  $config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
  if ($authHeader !== "Bearer " . $config['contrib_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
  }
  // Handle POST request to update falaise details
  $data = json_decode(file_get_contents('php://input'), true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
  }

  // Extract and validate contributor info
  $author = trim($data['author'] ?? '');
  $author_email = trim($data['author_email'] ?? '');

  if (empty($author) || empty($author_email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing contributor information']);
    exit;
  }

  // Remove author/author_email from GeoJSON before saving
  unset($data['author'], $data['author_email']);

  // Track if this is an update or new file
  $isUpdate = file_exists($geojson_file);

  // Load previous features (for change summary) and create backup before saving
  $previousFeatures = [];
  if (file_exists($geojson_file)) {
    $previousContent = file_get_contents($geojson_file);
    $previousData = json_decode($previousContent, true);
    if (is_array($previousData) && isset($previousData['features']) && is_array($previousData['features'])) {
      $previousFeatures = $previousData['features'];
    }

    $backup_dir = $_SERVER['DOCUMENT_ROOT'] . "/bdd/barres-historique";
    if (!is_dir($backup_dir)) {
      mkdir($backup_dir, 0755, true);
    }
    $date_suffix = date('Y-m-d-H\Hi');
    $base_name = $falaise["falaise_id"] . "_" . $falaise["falaise_nomformate"];
    $backup_file = $backup_dir . "/" . $base_name . "-" . $date_suffix . ".geojson";
    copy($geojson_file, $backup_file);
  }

  // Save the updated geojson content
  if (file_put_contents($geojson_file, json_encode($data, JSON_PRETTY_PRINT))) {
    // Log the modification
    logChanges(
      $author,
      $author_email,
      $isUpdate ? 'update' : 'insert',
      'falaise_details',
      $falaise['falaise_id'],
      $falaise['falaise_id'],
      ['geojson' => 'updated'],
      []
    );

    $summaryHtml = '';
    try {
      $changeSummary = summarizeFalaiseDetailsChanges($previousFeatures, $data['features'] ?? []);
      $summaryHtml = renderChangeSummaryHtml($changeSummary, $isUpdate);
    } catch (\Throwable $e) {
      error_log('[falaise_details] change summary failed: ' . $e->getMessage());
    }

    // Send notification email
    $falaise_nom = $falaise['falaise_nom'];
    $subject = "🧗 Détails falaise '$falaise_nom' modifiés par $author";
    $html = "<html><body>";
    $html .= "<h1>Les détails de la falaise $falaise_nom ont été modifiés</h1>";
    $html .= "<p>Contributeur : " . htmlspecialchars($author) . "</p>";
    $html .= "<p>Email : <a href='mailto:" . htmlspecialchars($author_email) . "'>" . htmlspecialchars($author_email) . "</a></p>";
    $html .= $summaryHtml;
    $html .= "<p><a href='https://velogrimpe.fr/falaise.php?falaise_id=" . $falaise['falaise_id'] . "'>Voir la falaise</a></p>";
    $html .= "</body></html>";

    try {
      sendMail([
        'to' => $config["contact_mail"],
        'subject' => $subject,
        'html' => $html,
        'h:Reply-To' => $author_email
      ]);
    } catch (\Throwable $e) {
      error_log('[falaise_details] sendMail failed: ' . $e->getMessage());
    }

    echo json_encode(['success' => 'Falaise details updated successfully']);
  } else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save falaise details']);
  }
}
