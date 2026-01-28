<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  die("Method not allowed");
}

$sortie_id = isset($_GET['sortie_id']) && !empty($_GET['sortie_id']) ? intval($_GET['sortie_id']) : null;
$token = trim($_GET['token'] ?? '');

if ($sortie_id === null || empty($token)) {
  http_response_code(400);
  die("Paramètres manquants");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Fetch sortie with delete token
$stmt = $mysqli->prepare(
  "SELECT * FROM sorties WHERE sortie_id = ? AND delete_token = ?"
);
$stmt->bind_param("is", $sortie_id, $token);
$stmt->execute();
$result = $stmt->get_result();
$sortie = $result->fetch_assoc();
$stmt->close();

if (!$sortie) {
  http_response_code(404);
  die("Sortie non trouvée ou token invalide");
}

// Delete sortie (cascade will delete participation_requests)
$stmt = $mysqli->prepare("DELETE FROM sorties WHERE sortie_id = ?");
$stmt->bind_param("i", $sortie_id);
$stmt->execute();
$stmt->close();

// Send notification email to organizer
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';

$date_display = date('d/m/Y', strtotime($sortie['date_debut']));
if (!empty($sortie['date_fin'])) {
  $date_display .= ' au ' . date('d/m/Y', strtotime($sortie['date_fin']));
}

$organizer_subject = "Votre sortie a été supprimée par l'admin - Velogrimpe.fr";
$organizer_html = "<html><body>";
$organizer_html .= "<h1>Votre sortie a été supprimée</h1>";
$organizer_html .= "<p>Bonjour {$sortie['organisateur_nom']},</p>";
$organizer_html .= "<p>Votre sortie d'escalade sur Velogrimpe.fr a été supprimée par l'administrateur.</p>";
$organizer_html .= "<h2>Détails de la sortie supprimée</h2>";
$organizer_html .= "<ul>";
$organizer_html .= "<li><strong>Falaise :</strong> {$sortie['falaise_principale_nom']}</li>";
$organizer_html .= "<li><strong>Date :</strong> $date_display</li>";
$organizer_html .= "<li><strong>Ville de départ :</strong> {$sortie['ville_depart']}</li>";
$organizer_html .= "</ul>";
$organizer_html .= "<p>Si vous pensez qu'il s'agit d'une erreur ou si vous avez des questions, n'hésitez pas à nous contacter.</p>";
$organizer_html .= "<p>Cordialement,<br>L'équipe Velogrimpe.fr</p>";
$organizer_html .= "</body></html>";

sendMail([
  'to' => $sortie['organisateur_email'],
  'subject' => $organizer_subject,
  'html' => $organizer_html
]);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sortie supprimée</title>
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

    .success {
      color: #10b981;
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
    <div class="success">✓</div>
    <h1>Sortie supprimée</h1>
    <p>La sortie a été supprimée avec succès (anti-spam).</p>

    <div class="info">
      <strong>Détails de la sortie supprimée :</strong>
      <p>
        <strong>Falaise :</strong> <?= htmlspecialchars($sortie['falaise_principale_nom']) ?><br>
        <strong>Date :</strong> <?= $date_display ?><br>
        <strong>Organisateur :</strong> <?= htmlspecialchars($sortie['organisateur_nom']) ?> (<?= htmlspecialchars($sortie['organisateur_email']) ?>)
      </p>
    </div>

    <p>Une notification a été envoyée à l'organisateur.</p>

    <div class="center">
      <a href="https://velogrimpe.fr/sorties.php" class="btn">Retour aux sorties</a>
    </div>
  </div>
</body>

</html>
