<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
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

    //Store in log
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
    $new_comment = [
      "gare_id" => $gare_id,
      "falaise_id" => $falaise_id,
      "velo_depart" => $velo_depart,
      "velo_arrivee" => $velo_arrivee,
      "velo_km" => $velo_km,
      "velo_dplus" => $velo_dplus,
      "velo_dmoins" => $velo_dmoins,
      "velo_descr" => $velo_descr,
      "velo_public" => $velo_public,
      "velo_variante" => $velo_variante,
      "velo_varianteformate" => $velo_varianteformate,
      "velo_openrunner" => $velo_openrunner,
      "velo_apieduniquement" => $velo_apieduniquement,
      "velo_apiedpossible" => $velo_apiedpossible,
      "velo_contrib" => $velo_contrib
    ];
    $collection = 'velo';
    $type = 'insert';
    $record_id = $mysqli->insert_id;
    logChanges(
      $nom_prenom,
      $email,
      $type,
      $collection,
      $record_id,
      $falaise_id,
      $new_comment
    );


    // Envoi du mail de confirmation seulement si admin = 0
    if ($admin == 0) {
      require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
      $to = $config["contact_mail"];

      $subject = "🚲 Itinéraire $velo_depart ⇢ $velo_arrivee ajouté par $nom_prenom";

      $html = "<html><body>";
      $html .= "<h1>L'itinéraire de $velo_depart à $velo_arrivee a été ajouté par $nom_prenom</h1>";
      $html .= "<p>email : <a href='mailto:$email'>$email</a></p>";
      $html .= "<p><a href='https://velogrimpe.fr/falaise.php?falaise_id=$falaise_id'>Voir la falaise</a><br/><br/></p>";
      if ($message) {
        $html .= "<p>Message additionnel : " . htmlspecialchars(nl2br(trim($message))) . "<br/><br/></p>";
      }
      $html .= "<p>Détails de l'itinéraire :</p>";
      $html .= "<ul>";
      $html .= "<li><b>Départ</b>: $velo_depart</li>";
      $html .= "<li><b>Arrivée</b>: $velo_arrivee</li>";
      $html .= "<li><b>Variante</b>: $velo_variante</li>";
      $html .= "<li><b>Distance</b>: $velo_km km</li>";
      $html .= "<li><b>D+</b>: $velo_dplus m</li>";
      $html .= "<li><b>D-</b>: $velo_dmoins m</li>";
      $html .= "<li><b>A pied uniquement</b>: " . ($velo_apieduniquement ? 'Oui' : 'Non') . "</li>";
      $html .= "<li><b>A pied possible</b>: " . ($velo_apiedpossible ? 'Oui' : 'Non') . "</li>";
      $html .= "<li><b>Description</b>: " . htmlspecialchars(nl2br(trim($velo_descr))) . "</li>";
      $html .= "</ul>";
      $html .= "</body></html>";

      $data = [
        'to' => $to,
        'subject' => $subject,
        'html' => $html,
        'h:Reply-To' => $email
      ];

      sendMail($data);
      header("Location: /contribuer.php");
      exit;
    } else {
      header("Location: /admin/");
      exit;
    }

  } else {
    die("Erreur lors de l'insertion : " . $mysqli->error);
  }
}
?>