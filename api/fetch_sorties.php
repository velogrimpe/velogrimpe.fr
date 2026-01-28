<?php
header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}

// Get query parameters
$ville_id = isset($_GET['ville_id']) && !empty($_GET['ville_id']) ? intval($_GET['ville_id']) : null;
$mois = isset($_GET['mois']) && !empty($_GET['mois']) ? trim($_GET['mois']) : null; // Format: YYYY-MM

// Validate mois format if provided
if ($mois !== null && !preg_match('/^\d{4}-\d{2}$/', $mois)) {
  http_response_code(400);
  die(json_encode(["error" => "Format de mois invalide (attendu: YYYY-MM)"]));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Build query with filters
$query = "SELECT
    sortie_id,
    organisateur_nom,
    ville_depart,
    ville_id,
    falaise_principale_nom,
    falaise_principale_id,
    falaises_alternatives,
    velo_nom,
    velo_id,
    lien_groupe,
    description,
    date_debut,
    date_fin,
    nb_interesses,
    date_creation,
    date_modification
  FROM sorties
  WHERE sortie_public = 1";

$params = [];
$types = "";

// Add ville_id filter
if ($ville_id !== null) {
  $query .= " AND ville_id = ?";
  $params[] = $ville_id;
  $types .= "i";
}

// Add month filter
if ($mois !== null) {
  // Filter by month: date_debut is in the specified month OR date_fin is in the month
  // OR the sortie spans across the month
  $query .= " AND (
    DATE_FORMAT(date_debut, '%Y-%m') = ? OR
    DATE_FORMAT(date_fin, '%Y-%m') = ? OR
    (date_debut <= LAST_DAY(CONCAT(?, '-01')) AND
     (date_fin IS NULL OR date_fin >= CONCAT(?, '-01')))
  )";
  $params[] = $mois;
  $params[] = $mois;
  $params[] = $mois;
  $params[] = $mois;
  $types .= "ssss";
}

// Order by date
$query .= " ORDER BY date_debut ASC, date_creation DESC";

// Prepare and execute
$stmt = $mysqli->prepare($query);

if (!$stmt) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur de préparation de la requête : " . $mysqli->error]));
}

// Bind parameters if any
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
  http_response_code(500);
  die(json_encode(["error" => "Erreur lors de l'exécution : " . $stmt->error]));
}

$result = $stmt->get_result();
$sorties = [];

while ($row = $result->fetch_assoc()) {
  // Decode JSON fields
  $row['falaises_alternatives'] = json_decode($row['falaises_alternatives'], true);

  // Add computed fields
  $row['is_past'] = strtotime($row['date_debut']) < strtotime('today');

  // Check if multi-day
  $row['is_multi_day'] = !empty($row['date_fin']) && $row['date_fin'] !== $row['date_debut'];

  $sorties[] = $row;
}

$stmt->close();

// Return results
echo json_encode([
  'success' => true,
  'sorties' => $sorties,
  'count' => count($sorties)
]);
