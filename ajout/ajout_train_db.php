<!--Pour la version admin : nettoyer les trois champs nom_prenom, email, message, et l'envoi de mail-->

<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
  $ville_id = (int) ($_POST['ville_id'] ?? 0);
  $gare_id = (int) ($_POST['gare_id'] ?? 0);
  $train_temps = isset($_POST['train_temps']) && $_POST['train_temps'] !== '' ? (int) $_POST['train_temps'] : null;
  $train_correspmin = isset($_POST['train_correspmin']) && $_POST['train_correspmin'] !== '' ? (int) $_POST['train_correspmin'] : null;
  $train_correspmax = isset($_POST['train_correspmax']) && $_POST['train_correspmax'] !== '' ? (int) $_POST['train_correspmax'] : null;
  $train_public = isset($_POST['train_public']) && $_POST['train_public'] !== '' ? (int) $_POST['train_public'] : null;

  $train_descr = trim($_POST['train_descr'] ?? '');
  $train_depart = trim($_POST['train_depart'] ?? '');
  $train_arrivee = trim($_POST['train_arrivee'] ?? '');

  $nom_prenom = trim($_POST['nom_prenom'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $train_contrib = trim("'" . $nom_prenom . "','" . $email . "'");


  // Vérification des informations obligatoires
  $champs_obligatoires = [
    'ville_id' => $ville_id,
    'gare_id' => $gare_id,
    'train_temps' => $train_temps,
    'train_correspmin' => $train_correspmin,
    'train_correspmax' => $train_correspmax,
    'train_public' => $train_public,
    'train_descr' => $train_descr,
    'train_depart' => $train_depart,
    'train_arrivee' => $train_arrivee
  ];

  foreach ($champs_obligatoires as $champ => $valeur) {
    if (empty($valeur) && !is_numeric($valeur)) {
      die("Il manque une info obligatoire : " . $champ);
    }
  }

  require_once "../database/velogrimpe.php";

  $stmt = $mysqli->prepare("INSERT INTO train
        (ville_id, gare_id, train_temps, train_correspmin, train_correspmax, train_public,
        train_descr, train_depart, train_arrivee, train_contrib)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  if (!$stmt) {
    die("Problème de préparation de la requête : " . $mysqli->error);
  }

  // Bind des paramètres avec les valeurs, les valeurs null sont gérées comme NULL dans la base de données
  $stmt->bind_param("iiiiiissss", $ville_id, $gare_id, $train_temps, $train_correspmin, $train_correspmax, $train_public, $train_descr, $train_depart, $train_arrivee, $train_contrib);
  if (!$stmt->execute()) {
    die("Erreur lors de l'insertion dans la base de données : " . $stmt->error);
  }

  $stmt->close();

  if ($admin == 0) {
    $to = $config["contact_mail"];
    $subject = "Ajout d'un itinéraire train par $nom_prenom: $train_depart - $train_arrivee";
    $body = "L'itinéraire de $train_depart à $train_arrivee a été ajouté par $nom_prenom (mail : $email), avec le message additionnel suivant : $message.";
    $headers = "From: noreply@velogrimpe.fr\r\n";

    mail($to, $subject, $body, $headers);
    header("Location: /contribuer.php");
    exit;
  } else {
    header("Location: admin/ajout_donnees_admin.html");
    exit;
  }
}
?>