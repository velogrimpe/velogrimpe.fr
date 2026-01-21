<?php
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  die("Erreur : Ce service doit être appelé via une requête GET.");
}
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$email = filter_var($_GET["email"], FILTER_SANITIZE_EMAIL);
$token = $_GET["token"];
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

// Verify email and token
$stmt = $mysqli->prepare("SELECT mail FROM mailing_list WHERE mail = ? AND token = ? AND confirme = 0");
$stmt->bind_param('ss', $email, $token);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
  // Invalid token or email
  http_response_code(400);
  $stmt->close();
  $ret = false;
} else {
  $stmt->close();
  // Update confirme to 1
  $stmt = $mysqli->prepare("UPDATE mailing_list SET confirme = 1 WHERE mail = ? and token = ?");
  $stmt->bind_param('ss', $email, $token);
  $stmt->execute();
  $stmt->close();
  $ret = true;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inscription Newsletter - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <div class="grow flex justify-center items-center">
    <?php if ($ret): ?>
      <div class="max-w-(--breakpoint-lg) alert alert-success text-base-100" role="alert">
        <span>
          <svg class="w-4 h-4 fill-current">
            <use xlink:href="/symbols/icons.svg#checkbox-circle-fill"></use>
          </svg>
        </span>
        <span>Inscription confirmée.</span>
        <a class="btn btn-sm btn-primary" href="/">Retour à l'accueil</a>
      </div>
    <?php else: ?>
      <div class="max-w-(--breakpoint-lg) alert alert-error text-base-100" role="alert">
        <span>
          <svg class="w-4 h-4 fill-current">
            <use xlink:href="/symbols/icons.svg#error-warning-fill"></use>
          </svg>
        </span>
        <span>Lien de confirmation invalide. Veuillez réessayer ou contactez nous directement.</span>
      </div>
    <?php endif; ?>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

</html>