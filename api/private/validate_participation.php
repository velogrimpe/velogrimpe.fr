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

// Check if already validated
if ($data['request_status'] == 1) {
  die("Cette demande a déjà été validée");
}

// Check if already rejected
if ($data['request_status'] == 3) {
  die("Cette demande a été rejetée");
}

// Update status to validated (1)
$stmt = $mysqli->prepare("UPDATE participation_requests SET request_status = 1 WHERE request_id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$stmt->close();

// Send email to organizer
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';

$date_display = date('d/m/Y', strtotime($data['date_debut']));
if (!empty($data['date_fin'])) {
  $date_display .= ' au ' . date('d/m/Y', strtotime($data['date_fin']));
}

// Build contact preferences display
$preferences = json_decode($data['preferences_contact'], true);
$prefs = [];
if ($preferences['signal'])
  $prefs[] = 'Signal';
if ($preferences['whatsapp'])
  $prefs[] = 'WhatsApp';
if ($preferences['email'])
  $prefs[] = 'Email';
if ($preferences['telephone'])
  $prefs[] = 'Téléphone';
$prefs_display = implode(', ', $prefs);

$organizer_subject = "Nouvelle participation à votre sortie : {$data['falaise_principale_nom']} ($date_display)";
$organizer_html = "<html><body>";
$organizer_html .= "<h1>Nouvelle participation à votre sortie !</h1>";
$organizer_html .= "<p>Bonjour {$data['organisateur_nom']},</p>";
$organizer_html .= "<p>Une personne souhaite participer à votre sortie d'escalade.</p>";
$organizer_html .= "<h2>Détails de la sortie</h2>";
$organizer_html .= "<ul>";
$organizer_html .= "<li><strong>Falaise :</strong> {$data['falaise_principale_nom']}</li>";
$organizer_html .= "<li><strong>Date :</strong> $date_display</li>";
$organizer_html .= "</ul>";
$organizer_html .= "<h2>Coordonnées du participant</h2>";
$organizer_html .= "<ul>";
$organizer_html .= "<li><strong>Nom :</strong> {$data['participant_nom']}</li>";
$organizer_html .= "<li><strong>Email :</strong> <a href='mailto:{$data['participant_email']}'>{$data['participant_email']}</a></li>";
if (!empty($data['participant_telephone'])) {
  $organizer_html .= "<li><strong>Téléphone :</strong> {$data['participant_telephone']}</li>";
}
$organizer_html .= "<li><strong>Moyens de contact préférés :</strong> $prefs_display</li>";
$organizer_html .= "</ul>";
if (!empty($data['message'])) {
  $organizer_html .= "<p><strong>Message du participant :</strong><br>" . nl2br(htmlspecialchars($data['message'])) . "</p>";
}
$organizer_html .= "<hr>";
$organizer_html .= "<p>Vous pouvez maintenant contacter cette personne pour organiser la sortie.</p>";
$organizer_html .= "<p><strong>Rappel du lien de votre groupe :</strong> <a href=\"{$data['lien_groupe']}\">{$data['lien_groupe']}</a></p>";
$organizer_html .= "</body></html>";

sendMail([
  'to' => $data['organisateur_email'],
  'subject' => $organizer_subject,
  'html' => $organizer_html,
  'h:Reply-To' => $data['participant_email']
]);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Participation validée</title>
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
  </style>
</head>

<body>
  <div class="card">
    <div class="success">✓</div>
    <h1>Participation validée</h1>
    <p>La demande de participation a été validée avec succès.</p>
    <p>Les coordonnées du participant ont été envoyées à l'organisateur
      (<strong><?= htmlspecialchars($data['organisateur_nom']) ?></strong>).</p>
    <div class="center">
      <a href="/sorties.php" class="btn">Retour aux sorties</a>
    </div>
  </div>
</body>

</html>