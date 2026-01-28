<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  die("Method not allowed");
}

$request_id = isset($_GET['request_id']) && !empty($_GET['request_id']) ? intval($_GET['request_id']) : null;
$token = trim($_GET['token'] ?? '');

if ($request_id === null || empty($token)) {
  http_response_code(400);
  die("Paramètres manquants");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Fetch participation request with validation token
$stmt = $mysqli->prepare(
  "SELECT pr.*, s.*
   FROM participation_requests pr
   JOIN sorties s ON pr.sortie_id = s.sortie_id
   WHERE pr.request_id = ? AND pr.validation_token = ?"
);
$stmt->bind_param("is", $request_id, $token);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

if (!$data) {
  http_response_code(404);
  die("Demande non trouvée ou token invalide");
}

// Check if already rejected
if ($data['request_status'] == 3) {
  die("Cette demande a déjà été rejetée");
}

// Check if already validated
if ($data['request_status'] == 1) {
  die("Cette demande a déjà été validée et ne peut plus être rejetée");
}

// Update status to rejected (3)
$stmt = $mysqli->prepare("UPDATE participation_requests SET request_status = 3 WHERE request_id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->close();

// Decrement nb_interesses count if it was pending
if ($data['request_status'] == 2) {
  $mysqli->query("UPDATE sorties SET nb_interesses = GREATEST(0, nb_interesses - 1) WHERE sortie_id = {$data['sortie_id']}");
}

$date_display = date('d/m/Y', strtotime($data['date_debut']));
if (!empty($data['date_fin'])) {
  $date_display .= ' au ' . date('d/m/Y', strtotime($data['date_fin']));
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Participation rejetée</title>
  <style>
    body {
      font-family: system-ui, -apple-system, sans-serif;
      max-width: 600px;
      margin: 50px auto;
      padding: 20px;
      background: #f5f5f5;
    }

    .card {
      background: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .warning {
      color: #f59e0b;
      font-size: 48px;
      text-align: center;
      margin-bottom: 20px;
    }

    h1 {
      text-align: center;
      color: #333;
    }

    p {
      color: #666;
      line-height: 1.6;
    }

    .btn {
      display: inline-block;
      background: #3b82f6;
      color: white;
      padding: 12px 24px;
      text-decoration: none;
      border-radius: 6px;
      margin-top: 20px;
    }

    .center {
      text-align: center;
    }

    .info {
      background: #f3f4f6;
      padding: 15px;
      border-radius: 6px;
      margin: 20px 0;
    }

    .info strong {
      display: block;
      margin-bottom: 5px;
    }
  </style>
</head>

<body>
  <div class="card">
    <div class="warning">⚠</div>
    <h1>Participation rejetée</h1>
    <p>La demande de participation a été rejetée.</p>

    <div class="info">
      <strong>Détails de la sortie :</strong>
      <p>
        <strong>Falaise :</strong> <?= htmlspecialchars($data['falaise_principale_nom']) ?><br>
        <strong>Date :</strong> <?= $date_display ?><br>
        <strong>Participant :</strong> <?= htmlspecialchars($data['participant_nom']) ?>
      </p>
    </div>

    <p>Aucune notification n'a été envoyée au participant ni à l'organisateur.</p>

    <div class="center">
      <a href="https://velogrimpe.fr/sorties.php" class="btn">Retour aux sorties</a>
    </div>
  </div>
</body>

</html>
