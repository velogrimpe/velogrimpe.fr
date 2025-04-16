<?php
include "../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
  $gare_id = $_POST['gare_id'] ?? null;
  $falaise_id = $_POST['falaise_id'] ?? null;
  $velo_depart = $_POST['velo_depart'] ?? null;
  $velo_arrivee = $_POST['velo_arrivee'] ?? null;
  $velo_km = (isset($_POST['velo_km']) && $_POST['velo_km'] !== '') ? floatval($_POST['velo_km']) : null;
  $velo_dplus = (isset($_POST['velo_dplus']) && $_POST['velo_dplus'] !== '') ? intval($_POST['velo_dplus']) : null;
  $velo_dmoins = (isset($_POST['velo_dmoins']) && $_POST['velo_dmoins'] !== '') ? intval($_POST['velo_dmoins']) : null;
  $velo_descr = $_POST['velo_descr'] ?? null;
  $nom_prenom = trim($_POST['nom_prenom'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $velo_contrib = trim("'" . $nom_prenom . "','" . $email . "'");

  // Vérification des champs obligatoires
  $champs_obligatoires = [
    'gare_id' => $gare_id,
    'falaise_id' => $falaise_id,
    'velo_depart' => $velo_depart,
    'velo_arrivee' => $velo_arrivee,
    'velo_km' => $velo_km,
    'velo_dplus' => $velo_dplus,
    'velo_dmoins' => $velo_dmoins,
  ];

  foreach ($champs_obligatoires as $champ => $valeur) {
    if (empty($valeur) && !is_numeric($valeur)) {
      die("Il manque une info obligatoire : " . $champ);
    }
  }

  $velo_descr = $_POST['velo_descr'] ?? null;
  $velo_variante = $_POST['velo_variante'] ?? null;
  $velo_varianteformate = $_POST['velo_varianteformate'] ?? null;
  $velo_openrunner = $_POST['velo_openrunner'] ?? null;
  $velo_apieduniquement = isset($_POST['velo_apieduniquement']) ? 1 : 0;
  $velo_apiedpossible = isset($_POST['velo_apiedpossible']) ? 1 : 0;
  $velo_public = isset($_POST['velo_public']) ? intval($_POST['velo_public']) : 0;

  // Gestion des fichiers GPX
  if (!empty($_FILES['gpx_file']['tmp_name'])) {
    $dom = new DOMDocument();
    $dom->loadXML(file_get_contents($_FILES['gpx_file']['tmp_name']));
    // Vérifier que le fichier GPX est valide
    $has_gpx_root = ($dom->getElementsByTagName('gpx')->length > 0) && ($dom->getElementsByTagName('gpx')->item(0)->getNodePath() === '/*');
    if (!$has_gpx_root) {
      die("Le fichier GPX n'est pas valide.");
    }
  } else {
    die("Il manque le fichier GPX.");
  }


  // Préparer la requête
  $stmt = $mysqli->prepare("INSERT INTO velo 
        (gare_id, falaise_id, velo_depart, velo_arrivee, velo_km, velo_dplus, velo_dmoins,
        velo_descr, velo_public, velo_variante, velo_varianteformate, velo_openrunner,
        velo_apieduniquement, velo_apiedpossible, velo_contrib) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

  if ($stmt) {
    $stmt->bind_param(
      "iissdiisisssiis",
      $gare_id,
      $falaise_id,
      $velo_depart,
      $velo_arrivee,
      $velo_km,
      $velo_dplus,
      $velo_dmoins,
      $velo_descr,
      $velo_public,
      $velo_variante,
      $velo_varianteformate,
      $velo_openrunner,
      $velo_apieduniquement,
      $velo_apiedpossible,
      $velo_contrib
    );

    $stmt->execute();
    $velo_id = $stmt->insert_id;

    // Déplacer + Renommer le fichier GPX
    $gpx_target_dir = $_SERVER['DOCUMENT_ROOT'] . "/bdd/gpx/";
    $gpx_target_file = $gpx_target_dir . "{$velo_id}_{$velo_depart}_{$velo_arrivee}_{$velo_varianteformate}.gpx";
    move_uploaded_file($_FILES['gpx_file']['tmp_name'], $gpx_target_file);

    $stmt->close();

    // Envoi du mail de confirmation seulement si admin = 0
    if ($admin == 0) {
      $to = $config["contact_mail"];
      $subject = "Ajout d'un itinéraire vélo par $nom_prenom : $velo_depart - $velo_arrivee";
      $body = "L'itinéraire de $velo_depart à $velo_arrivee a été ajouté par $nom_prenom (mail : $email), avec le message additionnel suivant : $message.";
      $headers = "From: noreply@velogrimpe.fr\r\n";

      mail($to, $subject, $body, $headers);
      header("Location: /contribuer.php");
      exit;
    } else {
      header("Location: admin/ajout_donnees_admin.php");
      exit;
    }

  } else {
    die("Erreur lors de l'insertion : " . $mysqli->error);
  }
}
?>