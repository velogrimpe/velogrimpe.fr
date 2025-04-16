<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Erreur : Ce script doit être appelé via une requête POST.");
}
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
$name = trim($_POST["name"]);
$message = htmlspecialchars(trim($_POST["message"]));

$admin_email = $config['contact_mail'];
$headers = "From: no-reply@velogrimpe.fr\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$subject_admin = "Prise de contact depuis le site - $name ($email)";
$message_admin = "Nom : $name\nEmail : $email\n\nMessage :\n$message";

$ret = mail($admin_email, $subject_admin, $message_admin, $headers)
    ?>

<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Validation formulaire - Vélogrimpe.fr</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png" />

    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>

    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen flex flex-col">
    <?php include "../components/header.html"; ?>

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
    <?php include "../components/footer.html"; ?>
</body>

</html>