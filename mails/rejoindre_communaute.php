<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Erreur : Ce script doit être appelé via une requête POST.");
}
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
$phone = trim($_POST["phone"]);
$message = htmlspecialchars(nl2br(trim($_POST["message"])));

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';

$subject = "Demande d'ajout au groupe Signal - $phone ($email)";

$html = "<html><body>";
$html .= "<h1>Nouveau message depuis le site</h1>";
$html .= "<ul>";
$html .= "<li><strong>Téléphone:</strong> $phone</li>";
$html .= "<li><strong>Email:</strong> <a href='mailto:$email'>$email</a></li>";
$html .= "<li><strong>Commentaire:</strong> $message</li>";
$html .= "</ul>";
$html .= "</body></html>";

$data = [
    'to' => $config['contact_mail'],
    'subject' => $subject,
    'html' => $html,
    'h:Reply-To' => $email
];

$ret = sendMail($data);
?>

<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Validation formulaire - Vélogrimpe.fr</title>

    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>

    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen flex flex-col">
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>

    <div class="flex-grow flex justify-center items-center">
        <?php if ($ret): ?>
            <div class="max-w-screen-lg alert alert-success text-base-100" role="alert">
                <span>
                    <svg class="w-4 h-4 fill-current">
                        <use xlink:href="/symbols/icons.svg#ri-checkbox-circle-fill"></use>
                    </svg>
                </span>
                <span>Votre message a bien été envoyé.</span>
                <a class="btn btn-sm btn-primary" href="/">Retour à l'accueil</a>
            </div>
        <?php else: ?>
            <div class="max-w-screen-lg alert alert-error text-base-100" role="alert">
                <span>
                    <svg class="w-4 h-4 fill-current">
                        <use xlink:href="/symbols/icons.svg#ri-error-warning-fill"></use>
                    </svg>
                </span>
                <span>Une erreur est survenue lors de l'envoi de votre message. Veuillez réessayer.</span>
            </div>
        <?php endif; ?>
    </div>
    <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

</html>