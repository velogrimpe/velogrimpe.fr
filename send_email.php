<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Validation formulaire - Vélogrimpe.fr</title>
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
    <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png" />

    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>

    <link rel="manifest" href="/site.webmanifest" />
    <link rel="stylesheet" href="/global.css" />
</head>

<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Erreur : Ce script doit être appelé via une requête POST.");
}
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
$phone = trim($_POST["phone"]);
$message = htmlspecialchars(trim($_POST["message"]));

$admin_email = $config['contact_mail'];
$headers = "From: no-reply@velogrimpe.fr\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$subject_admin = "Demande d'ajout au groupe Signal - $phone";
$message_admin = "Email : $email\nTéléphone : $phone\n\nMessage :\n$message";

if (mail($admin_email, $subject_admin, $message_admin, $headers)) {
    echo "<p><br>Votre demande d'ajout au groupe Signal 'Vélogrimpe' a bien été envoyée. 
    <br>Lorsque vous intégrerez le groupe, l'usage est d'écrire un petit mot pour se présenter aux autres :)<br></p>";
    echo "<p><a href='/'>Retour à l'accueil</a></p>";
} else {
    echo "<p style='color: red;'>Une erreur est survenue lors de l'envoi de votre demande. Veuillez réessayer.</p>";
    echo "<p><a href='communaute.php'>Retour au formulaire</a></p>";
}
?>