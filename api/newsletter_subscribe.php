<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  die("Erreur : Ce service doit être appelé via une requête POST.");
}
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

// Check if email already exists
$stmt = $mysqli->prepare("SELECT mail FROM mailing_list WHERE mail = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
  // Email already subscribed
  http_response_code(400);
  $stmt->close();
  die("Cette adresse email est déjà inscrite à la newsletter.");
}

// Insert new email with confirme = 0, it will generate a token
$stmt->close();
$stmt = $mysqli->prepare("INSERT INTO
    mailing_list (mail, confirme)
  VALUES (?, 0)
  ;");
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->error) {
  http_response_code(500);
  die("Erreur lors de l'inscription à la newsletter : " . $stmt->error);
}
$stmt->close();
// retrieve token
$stmt = $mysqli->prepare("SELECT token FROM mailing_list WHERE mail = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$token = $result['token'];
$stmt->close();

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/fetch_mail_template.php';

$template = fetchMailTemplate("/actualites/gestion/confirm_subscription.php?email=" . urlencode($email) . "&token=" . urlencode($token));
$title = $template['title'];
$html = $template['html'];

$data = [
  'to' => $email,
  'subject' => $title,
  'html' => $html,
  'from' => 'Vélogrimpe.fr <' . $config['contact_mail'] . '>',
];

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';

$ret = sendMail($data);

$adminData = [
  'to' => $config['contact_mail'],
  'subject' => "Nouvelle inscription à la newsletter: $email",
  'html' => "L'adresse email $email vient de s'inscrire à la newsletter.",
];
sendMail($adminData);
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
          <svg class="w-4 h-4 fill-none stroke-current">
            <use href="#checkbox-circle-fill"></use>
          </svg>
        </span>
        <span>Demande d'inscription prise en compte. Vous allez recevoir un email pour confirmer votre inscription.</span>
        <a class="btn btn-sm btn-primary" href="/">Retour à l'accueil</a>
      </div>
    <?php else: ?>
      <div class="max-w-(--breakpoint-lg) alert alert-error text-base-100" role="alert">
        <span>
          <svg class="w-4 h-4 fill-none stroke-current">
            <use href="#error-warning-fill"></use>
          </svg>
        </span>
        <span>Une erreur est survenue lors de l'inscription. Veuillez réessayer ou contactez nous directement.</span>
      </div>
    <?php endif; ?>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>