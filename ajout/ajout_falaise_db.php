<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Remplissage des champs obligatoires de la table
  $admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
  $falaise_id = trim($_POST['falaise_id'] ?? null);
  $falaise_nom = trim($_POST['falaise_nom'] ?? '');
  $falaise_nomformate = trim($_POST['falaise_nomformate'] ?? '');
  $falaise_latlng = trim($_POST['falaise_latlng'] ?? '');
  $falaise_exposhort1 = trim($_POST['falaise_exposhort1'] ?? '');
  $falaise_cotmin = trim($_POST['falaise_cotmin'] ?? '');
  $falaise_cotmax = trim($_POST['falaise_cotmax'] ?? '');
  $falaise_zone = trim($_POST['falaise_zone'] ?? -1);
  $falaise_maa = isset($_POST['falaise_maa']) ? (int) $_POST['falaise_maa'] : null;
  $falaise_mar = isset($_POST['falaise_mar']) ? (int) $_POST['falaise_mar'] : null;
  $falaise_public = isset($_POST['falaise_public']) ? (int) $_POST['falaise_public'] : null;
  $falaise_topo = trim($_POST['falaise_topo'] ?? '');
  $falaise_expotxt = trim($_POST['falaise_expotxt'] ?? '');
  $falaise_matxt = trim($_POST['falaise_matxt'] ?? '');
  $falaise_cottxt = trim($_POST['falaise_cottxt'] ?? '');
  $falaise_voletcarto = trim($_POST['falaise_voletcarto'] ?? '');
  $falaise_voies = trim($_POST['falaise_voies'] ?? '');
  $falaise_bloc = trim($_POST['falaise_bloc'] ?? null);
  $nom_prenom = trim($_POST['nom_prenom'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $falaise_contrib = trim("'" . $nom_prenom . "','" . $email . "'");

  $champs_obligatoires = [
    'falaise_nom' => $falaise_nom,
    'falaise_nomformate' => $falaise_nomformate,
    'falaise_latlng' => $falaise_latlng,
    'falaise_exposhort1' => $falaise_exposhort1,
    'falaise_cotmin' => $falaise_cotmin,
    'falaise_cotmax' => $falaise_cotmax,
    'falaise_zone' => $falaise_zone,
    'falaise_maa' => $falaise_maa,
    'falaise_mar' => $falaise_mar,
    'falaise_public' => $falaise_public,
    'falaise_topo' => $falaise_topo,
    'falaise_expotxt' => $falaise_expotxt,
    'falaise_matxt' => $falaise_matxt,
    'falaise_cottxt' => $falaise_cottxt,
    'falaise_voletcarto' => $falaise_voletcarto,
    'falaise_voies' => $falaise_voies
  ];

  foreach ($champs_obligatoires as $champ => $valeur) {
    if (empty($valeur) && !is_numeric($valeur)) {
      if ($admin == 1 && $champ != 'falaise_nom' && $champ != 'falaise_latlng') {
        continue;
      }
      die("Il manque une info obligatoire : " . $champ);
    }
  }

  // Remplissage par défaut des champs non obligatoires
  $champs = [
    'falaise_exposhort2' => null,
    'falaise_gvtxt' => null,
    'falaise_gvnb' => null,
    'falaise_rq' => null,
    'falaise_fermee' => null,
    'falaise_txt1' => null,
    'falaise_txt2' => null,
    'falaise_leg1' => null,
    'falaise_txt3' => null,
    'falaise_txt4' => null,
    'falaise_leg2' => null,
    'falaise_leg3' => null,
  ];

  foreach ($champs as $key => &$val) {
    $val = trim($_POST[$key] ?? $val);
  }

  require_once "../database/velogrimpe.php";

  if ($mysqli->connect_error) {
    die("Erreur de connexion à la base de données : " . $mysqli->connect_error);
  }

  // Préparation de la requête d'insertion
  $stmt = $mysqli->prepare("INSERT INTO falaises (
    falaise_id,
    falaise_nom, falaise_zone, falaise_nomformate, falaise_public, falaise_latlng, falaise_exposhort1, falaise_exposhort2, 
    falaise_cotmin, falaise_cotmax, falaise_maa, falaise_mar, falaise_topo, falaise_expotxt, falaise_matxt, falaise_cottxt,
    falaise_voletcarto, falaise_voies, falaise_gvtxt, falaise_gvnb, falaise_rq, falaise_fermee, falaise_txt1, falaise_txt2,
    falaise_leg1, falaise_txt3, falaise_txt4, falaise_leg2, falaise_leg3, falaise_contrib, falaise_bloc
    )
    VALUES (COALESCE(?, NULL), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    falaise_nom = VALUES(falaise_nom),
    falaise_zone = VALUES(falaise_zone),
    falaise_nomformate = VALUES(falaise_nomformate),
    falaise_public = VALUES(falaise_public),
    falaise_latlng = VALUES(falaise_latlng),
    falaise_exposhort1 = VALUES(falaise_exposhort1),
    falaise_exposhort2 = VALUES(falaise_exposhort2),
    falaise_cotmin = VALUES(falaise_cotmin),
    falaise_cotmax = VALUES(falaise_cotmax),
    falaise_maa = VALUES(falaise_maa),
    falaise_mar = VALUES(falaise_mar),
    falaise_topo = VALUES(falaise_topo),
    falaise_expotxt = VALUES(falaise_expotxt),
    falaise_matxt = VALUES(falaise_matxt),
    falaise_cottxt = VALUES(falaise_cottxt),
    falaise_voletcarto = VALUES(falaise_voletcarto),
    falaise_voies = VALUES(falaise_voies),
    falaise_gvtxt = VALUES(falaise_gvtxt),
    falaise_gvnb = VALUES(falaise_gvnb),
    falaise_rq = VALUES(falaise_rq),
    falaise_fermee = VALUES(falaise_fermee),
    falaise_txt1 = VALUES(falaise_txt1),
    falaise_txt2 = VALUES(falaise_txt2),
    falaise_leg1 = VALUES(falaise_leg1),
    falaise_txt3 = VALUES(falaise_txt3),
    falaise_txt4 = VALUES(falaise_txt4),
    falaise_leg2 = VALUES(falaise_leg2),
    falaise_leg3 = VALUES(falaise_leg3),
    falaise_contrib = VALUES(falaise_contrib)
    falaise_bloc = VALUES(falaise_bloc)
    ");

  if (!$stmt) {
    die("Problème de préparation de la requête : " . $mysqli->error);
  }

  $stmt->bind_param(
    "isisisssssiissssssssssssssssss",
    $falaise_id,
    $falaise_nom,
    $falaise_zone,
    $falaise_nomformate,
    $falaise_public,
    $falaise_latlng,
    $falaise_exposhort1,
    $champs['falaise_exposhort2'],
    $falaise_cotmin,
    $falaise_cotmax,
    $falaise_maa,
    $falaise_mar,
    $falaise_topo,
    $falaise_expotxt,
    $falaise_matxt,
    $falaise_cottxt,
    $falaise_voletcarto,
    $falaise_voies,
    $champs['falaise_gvtxt'],
    $champs['falaise_gvnb'],
    $champs['falaise_rq'],
    $champs['falaise_fermee'],
    $champs['falaise_txt1'],
    $champs['falaise_txt2'],
    $champs['falaise_leg1'],
    $champs['falaise_txt3'],
    $champs['falaise_txt4'],
    $champs['falaise_leg2'],
    $champs['falaise_leg3'],
    $falaise_contrib,
    $falaise_bloc
  );
  $res = $stmt->execute();
  // get falaise_id from last insert
  $falaise_id = $mysqli->insert_id;
  $stmt->close();
  $mysqli->close();

  $targetDir = '../bdd/images_falaises/'; // Chemin relatif au script PHP
  $fullTargetDir = realpath($targetDir); // Chemin absolu

  // Vérifiez si le dossier existe
  if (!$fullTargetDir) {
    die("Le dossier cible $targetDir n'existe pas ou est introuvable.");
  }

  function uploadImage($fileInputName, $targetDir, $falaiseId, $falaiseNomformate, $suffix)
  {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
      return null;
    }

    $fileTmpName = $_FILES[$fileInputName]['tmp_name'];
    $fileExtension = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (!in_array($fileExtension, $allowedExtensions)) {
      return "Extension non autorisée pour $fileInputName.";
    }

    $targetFileName = "{$falaiseId}_{$falaiseNomformate}_{$suffix}.png";
    $targetFilePath = $targetDir . DIRECTORY_SEPARATOR . $targetFileName;

    if (!imagepng(imagecreatefromstring(file_get_contents($fileTmpName)), $targetFilePath)) {
      // if (!move_uploaded_file($fileTmpName, $targetFilePath)) {
      return "Erreur lors de l'upload de $fileInputName.";
    }

    return null;
  }

  foreach (['falaise_img1' => 'img1', 'falaise_img2' => 'img2', 'falaise_img3' => 'img3'] as $fileInputName => $suffix) {
    $uploadError = uploadImage($fileInputName, $targetDir, $falaise_id, $falaise_nomformate, $suffix);
    if ($uploadError) {
      $errors[] = $uploadError;
    }
  }

  if ($errors) {
    foreach ($errors as $error) {
      echo "<p style='color:red;'>$error</p>";
    }
    echo "<a href='add_falaise.html'>Retour au formulaire</a>";
    exit;
  }


  if ($res) {
    // Envoi du mail de confirmation seulement si admin = 0
    if ($admin == 0) {
      $to = $config['contact_mail'];
      $subject = "Ajout d'une falaise par $nom_prenom: $falaise_nom";
      $body = "La falaise de $falaise_nom a été ajoutée par $nom_prenom (mail : $email).";
      if ($message) {
        $body .= "Message additionnel : $message\n";
      }
      $body .= "\n\nDétails de la falaise :\n";
      $body .= "Nom : $falaise_nom\n";
      $body .= "Topo : $falaise_topo\n";
      $body .= "Voies : $falaise_voies\n";
      $body .= "Volet carto : $falaise_voletcarto\n";
      $body .= "Expositions : $falaise_exposhort1\n";
      $body .= "Exposition : $falaise_expotxt\n";
      $body .= "Cotations min/max : $falaise_cotmin/$falaise_cotmax\n";
      $body .= "Cotations : $falaise_cottxt\n";
      $body .= "Approche A/R : $falaise_maa/$falaise_mar\n";
      $body .= "Approche : $falaise_matxt\n";
      $body .= "Grandes voies : " . $champs['falaise_gvtxt'] . "\n";
      $body .= "Nombre de GV : " . $champs['falaise_gvnb'] . "\n";
      $body .= "Bloc: " . $falaise_bloc . "\n";
      $body .= "Remarque : " . $champs['falaise_rq'] . "\n";
      $headers = "From: noreply@velogrimpe.fr\r\n";

      mail($to, $subject, $body, $headers);
      header("Location: /contribuer.php");
    } else {
      header("Location: admin/ajout_donnees_admin.php");
    }
    exit;
  } else {
    die("Erreur lors de l'ajout de la falaise : " . $stmt->error);
  }
}

?>