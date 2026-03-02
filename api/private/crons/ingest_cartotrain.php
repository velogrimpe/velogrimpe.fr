<?php

/**
 * Cron: Ingestion du fichier cartotrain/tableau.xlsx -> table cartotrain_emport
 * Lit le fichier XLSX, parse les données et les stocke en BDD.
 * Préserve la mise en forme (gras, italique, couleur) des cellules de règles en HTML.
 *
 * Table créée automatiquement si inexistante :
 *   CREATE TABLE cartotrain_emport (
 *     emport_id smallint(6) PRIMARY KEY AUTO_INCREMENT,
 *     type_train varchar(255) NOT NULL,
 *     compagnie_region varchar(255) NOT NULL,
 *     regle_demonte text,
 *     regle_nondemonte text,
 *     source1 text,
 *     source2 text
 *   );
 */

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Auth
$headers = getallheaders();
$authHeader = $headers['authorization'] ?? $headers['Authorization'] ?? null;
if (!$authHeader || $authHeader !== 'Bearer ' . $config['vg_token']) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

// GET only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['error' => 'Method Not Allowed']);
  exit;
}

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Créer la table si elle n'existe pas
$mysqli->query("CREATE TABLE IF NOT EXISTS cartotrain_emport (
  emport_id smallint(6) PRIMARY KEY AUTO_INCREMENT,
  type_train varchar(255) NOT NULL,
  compagnie_region varchar(255) NOT NULL,
  regle_demonte text,
  regle_nondemonte text,
  source1 text,
  source2 text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Parser le fichier XLSX
$xlsxPath = $_SERVER['DOCUMENT_ROOT'] . '/bdd/cartotrain/tableau.xlsx';
if (!file_exists($xlsxPath)) {
  http_response_code(404);
  echo json_encode(['success' => false, 'error' => 'Fichier tableau.xlsx introuvable']);
  exit;
}

// --- Parsing XLSX avec préservation du rich text ---

$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== true) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Impossible d\'ouvrir le ZIP XLSX']);
  exit;
}

// 1) Parser les shared strings (texte riche → HTML)
$sharedStrings = [];
$ssXml = $zip->getFromName('xl/sharedStrings.xml');
if ($ssXml) {
  $xml = new SimpleXMLElement($ssXml);
  foreach ($xml->si as $si) {
    if ($si->r->count() > 0) {
      // Rich text : plusieurs runs avec formatage
      $html = '';
      foreach ($si->r as $run) {
        $text = (string)$run->t;
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = nl2br($text);

        if ($run->rPr) {
          $open = '';
          $close = '';

          if ($run->rPr->b) {
            $open .= '<b>';
            $close = '</b>' . $close;
          }
          if ($run->rPr->i) {
            $open .= '<i>';
            $close = '</i>' . $close;
          }
          if ($run->rPr->u) {
            $open .= '<u>';
            $close = '</u>' . $close;
          }
          if ($run->rPr->color && $run->rPr->color['rgb']) {
            $rgb = (string)$run->rPr->color['rgb'];
            if (strlen($rgb) === 8) $rgb = substr($rgb, 2); // ARGB → RGB
            $open .= '<span style="color:#' . $rgb . '">';
            $close = '</span>' . $close;
          }

          $text = $open . $text . $close;
        }

        $html .= $text;
      }
      $sharedStrings[] = $html;
    } else {
      // Texte simple
      $text = (string)$si->t;
      $sharedStrings[] = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }
  }
}

// 2) Parser la feuille de calcul
$wsXml = $zip->getFromName('xl/worksheets/sheet1.xml');
if (!$wsXml) {
  $zip->close();
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Feuille de calcul introuvable dans le XLSX']);
  exit;
}

$ws = new SimpleXMLElement($wsXml);
$zip->close();

// Convertir ref colonne (A, B, ..., AA) en index 0-based
function colLetterToIndex(string $cellRef): int {
  preg_match('/^([A-Z]+)/', $cellRef, $m);
  $col = $m[1];
  $index = 0;
  for ($i = 0; $i < strlen($col); $i++) {
    $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
  }
  return $index - 1;
}

// Extraire les lignes : chaque cellule est résolue via sharedStrings
$rows = [];
foreach ($ws->sheetData->row as $xmlRow) {
  $rowData = [];
  foreach ($xmlRow->c as $cell) {
    $colIdx = colLetterToIndex((string)$cell['r']);
    $type = (string)$cell['t'];
    $value = (string)$cell->v;

    if ($type === 's') {
      // Référence shared string
      $rowData[$colIdx] = $sharedStrings[(int)$value] ?? '';
    } elseif ($type === 'inlineStr') {
      $rowData[$colIdx] = nl2br(htmlspecialchars((string)$cell->is->t, ENT_QUOTES, 'UTF-8'));
    } else {
      $rowData[$colIdx] = $value;
    }
  }
  $rows[] = $rowData;
}

// --- Insertion en BDD ---

$mysqli->query("TRUNCATE TABLE cartotrain_emport");

$stmt = $mysqli->prepare("INSERT INTO cartotrain_emport
  (type_train, compagnie_region, regle_demonte, regle_nondemonte, source1, source2)
  VALUES (?, ?, ?, ?, ?, ?)");

$lastTypeTrain = '';
$inserted = 0;
$skipped = 0;

// Colonnes (0-indexées) :
// 0: type_train (cellules fusionnées → reporter la dernière valeur non vide)
// 1: compagnie_region
// 2: regle_demonte (opt) — HTML
// 3: regle_nondemonte (opt) — HTML
// 4: lien Source (ignoré)
// 5: vide (ignoré)
// 6: url source 1 (opt)
// 7: url source 2 (opt)

// Sauter la ligne d'en-tête (row 0)
for ($i = 1; $i < count($rows); $i++) {
  $row = $rows[$i];

  $typeTrain = trim(strip_tags($row[0] ?? ''));
  $compagnieRegion = trim(strip_tags($row[1] ?? ''));

  // Cellules fusionnées : reporter le dernier type_train non vide
  if ($typeTrain !== '') {
    $lastTypeTrain = $typeTrain;
  } else {
    $typeTrain = $lastTypeTrain;
  }

  // Ignorer les lignes sans compagnie
  if ($compagnieRegion === '') {
    $skipped++;
    continue;
  }

  // Règles : garder le HTML (rich text)
  $regleDemonte = trim($row[2] ?? '') ?: null;
  $regleNondemonte = trim($row[3] ?? '') ?: null;

  // Sources : texte brut (URLs)
  $source1 = trim(strip_tags($row[6] ?? '')) ?: null;
  $source2 = trim(strip_tags($row[7] ?? '')) ?: null;

  $stmt->bind_param('ssssss', $typeTrain, $compagnieRegion, $regleDemonte, $regleNondemonte, $source1, $source2);
  $stmt->execute();
  $inserted++;
}

$stmt->close();

echo json_encode([
  'success' => true,
  'message' => 'Ingestion cartotrain terminée',
  'inserted' => $inserted,
  'skipped' => $skipped,
]);
