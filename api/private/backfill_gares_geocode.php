<?php
// GET /api/private/backfill_gares_geocode.php?admin=...[&limit=N]
// Renseigne code_insee/commune/cp (table `arrets`) pour les arrêts qui n'ont
// pas encore de commune connue, via l'API batch de géocodage inverse
// data.gouv.fr (reverse/csv) : un seul appel HTTP pour tout le lot, plutôt
// que N appels unitaires (cf. lib/altitude_lib.php + backfill_altitudes.php
// pour le pattern unitaire, utilisé pour les falaises).
//
// Équivalent, pour une gare, de :
//   curl -X POST -F data=@gares.csv -F lat=lat -F lon=lon \
//     https://api-adresse.data.gouv.fr/reverse/csv/
// où gares.csv contient les colonnes gare_id,lat,lon. La colonne gare_id est
// un passthrough : l'API la renvoie telle quelle dans le CSV de sortie, ce
// qui permet de réassocier chaque résultat à sa gare sans avoir à rapprocher
// des coordonnées (fragile aux arrondis).

header('Content-Type: application/json; charset=utf-8');

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$adminToken = $config['admin_token'] ?? null;
$adminParam = $_GET['admin'] ?? null;
if (!$adminToken || $adminParam !== $adminToken || $_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
if ($mysqli->connect_errno) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed', 'details' => $mysqli->connect_error]);
  exit;
}
$mysqli->set_charset('utf8mb4');

// Limite optionnelle du nombre de gares traitées par run (0 ou absent = toutes).
$limit = isset($_GET['limit']) ? max(0, (int) $_GET['limit']) : 0;

$sql = "SELECT id AS gare_id, CONCAT(ST_Y(loc), ',', ST_X(loc)) AS gare_latlng FROM arrets WHERE code_insee IS NULL AND loc IS NOT NULL AND deleted = 0";
if ($limit > 0) {
  $sql .= " LIMIT " . $limit;
}
$res = $mysqli->query($sql);
if (!$res) {
  http_response_code(500);
  echo json_encode(['error' => 'Query failed', 'details' => $mysqli->error]);
  exit;
}

$gares = [];
while ($row = $res->fetch_assoc()) {
  $parts = array_map('trim', explode(',', (string) $row['gare_latlng']));
  if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
    continue;
  }
  $gares[] = ['gare_id' => (int) $row['gare_id'], 'lat' => $parts[0], 'lon' => $parts[1]];
}

if (empty($gares)) {
  echo json_encode(['success' => true, 'traites' => 0, 'renseignes' => 0, 'echecs' => []]);
  exit;
}

// --- Construction du CSV d'entrée ---
$csvPath = tempnam(sys_get_temp_dir(), 'gares_geocode_');
$fh = fopen($csvPath, 'w');
fputcsv($fh, ['gare_id', 'lat', 'lon']);
foreach ($gares as $g) {
  fputcsv($fh, [$g['gare_id'], $g['lat'], $g['lon']]);
}
fclose($fh);

// --- Appel API batch data.gouv.fr ---
$ch = curl_init('https://api-adresse.data.gouv.fr/reverse/csv/');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
  'data' => new CURLFile($csvPath, 'text/csv', 'gares.csv'),
  'lat' => 'lat',
  'lon' => 'lon',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$responseCsv = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);
unlink($csvPath);

if ($responseCsv === false || $httpCode !== 200) {
  http_response_code(502);
  echo json_encode(['error' => 'API de géocodage indisponible', 'details' => $curlErr ?: "HTTP $httpCode"]);
  exit;
}

// --- Parsing du CSV de réponse ---
$lines = preg_split('/\r\n|\r|\n/', trim($responseCsv));
$headers = str_getcsv(array_shift($lines));
$colIndex = array_flip($headers);
foreach (['gare_id', 'result_citycode', 'result_city', 'result_postcode'] as $required) {
  if (!isset($colIndex[$required])) {
    http_response_code(502);
    echo json_encode(['error' => "Colonne '$required' absente de la réponse API", 'headers' => $headers]);
    exit;
  }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$updateStmt = $mysqli->prepare(
  "UPDATE arrets SET code_insee = ?, commune = ?, cp = ? WHERE id = ?"
);

$traites = 0;
$renseignes = 0;
$echecs = [];
$contactMail = $config['contact_mail'] ?? 'contact@velogrimpe.fr';

foreach ($lines as $line) {
  if (trim($line) === '') {
    continue;
  }
  $traites++;
  $fields = str_getcsv($line);
  $gareId = (int) ($fields[$colIndex['gare_id']] ?? 0);
  $citycode = $fields[$colIndex['result_citycode']] ?? '';
  $city = $fields[$colIndex['result_city']] ?? '';
  $postcode = $fields[$colIndex['result_postcode']] ?? '';

  if (!$gareId || $citycode === '') {
    $echecs[] = ['gare_id' => $gareId, 'raison' => 'aucun résultat de géocodage'];
    continue;
  }

  $updateStmt->bind_param('sssi', $citycode, $city, $postcode, $gareId);
  if (!$updateStmt->execute()) {
    $echecs[] = ['gare_id' => $gareId, 'raison' => 'update échoué: ' . $updateStmt->error];
    continue;
  }
  logChanges(
    'admin',
    $contactMail,
    'update',
    'arrets',
    $gareId,
    $gareId,
    ['code_insee' => $citycode, 'commune' => $city, 'cp' => $postcode],
    []
  );
  $renseignes++;
}

http_response_code(200);
echo json_encode([
  'success' => true,
  'traites' => $traites,
  'renseignes' => $renseignes,
  'echecs' => $echecs,
], JSON_UNESCAPED_UNICODE);
