<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
  $gare_id = $_POST['gare_id'] ?? null;
  $falaise_id = $_POST['falaise_id'] ?? null;
  $velo_depart = $_POST['velo_depart'] ?? null;
  $velo_arrivee = $_POST['velo_arrivee'] ?? null;
  [$velo_km, $velo_dplus, $velo_dmoins] = velo_lire_indicateurs($_POST);
  $velo_descr = $_POST['velo_descr'] ?? null;
  $nom_prenom = trim($_POST['nom_prenom'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');
  $velo_contrib = velo_contrib_string($nom_prenom, $email);

  // Vérification des champs obligatoires
  velo_verifier_obligatoires([
    'gare_id' => $gare_id,
    'falaise_id' => $falaise_id,
    'velo_depart' => $velo_depart,
    'velo_arrivee' => $velo_arrivee,
    'velo_km' => $velo_km,
    'velo_dplus' => $velo_dplus,
    'velo_dmoins' => $velo_dmoins,
  ]);

  $velo_variante = $_POST['velo_variante'] ?? null;
  $velo_varianteformate = $_POST['velo_varianteformate'] ?? null;
  $velo_openrunner = $_POST['velo_openrunner'] ?? null;
  $velo_apieduniquement = isset($_POST['velo_apieduniquement']) ? 1 : 0;
  $velo_apiedpossible = isset($_POST['velo_apiedpossible']) ? 1 : 0;
  $velo_public = isset($_POST['velo_public']) ? intval($_POST['velo_public']) : 0;

  // Ces trois champs composent le nom du fichier GPX écrit plus bas (cf. D002).
  // velo_depart et velo_arrivee reprennent les nomformate de la gare et de la
  // falaise, velo_varianteformate vient de formatVariante() côté formulaire.
  velo_verifier_slugs([
    'velo_depart' => $velo_depart,
    'velo_arrivee' => $velo_arrivee,
    'velo_varianteformate' => $velo_varianteformate,
  ]);

  // Gestion du fichier GPX (obligatoire à l'ajout)
  $dom = velo_charger_gpx_upload('gpx_file');
  if ($dom === null) {
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

    // Sans cette garde, un insert_id à 0 ferait écrire la trace sous « 0_… », en
    // écrasant celle d'une précédente contribution ratée.
    if (!$velo_id) {
      die("Erreur lors de l'insertion : identifiant d'itinéraire non attribué.");
    }

    // Enregistrer le fichier GPX nettoyé. velo_id vient d'insert_id et les trois
    // autres segments sont validés en amont : le chemin ne peut pas sortir du dossier.
    $gpx_target_file = velo_gpx_chemin((int) $velo_id, $velo_depart, $velo_arrivee, (string) $velo_varianteformate);
    if ($dom->save($gpx_target_file) === false) {
      error_log("add_velo: échec de l'écriture du GPX nettoyé pour velo_id=$velo_id");
    }

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
    logChanges(
      $nom_prenom,
      $email,
      'insert',
      'velo',
      $velo_id,
      $falaise_id,
      $new_comment
    );

    // Mail de notification : admin_mail en mode admin, contact_mail sinon
    // (même schéma que add_falaise.php / add_bus.php).
    {
      require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
      $to = $admin ? $config["admin_mail"] : $config["contact_mail"];

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
      $html .= "<h2>Actions</h2>";
      if ($velo_public !== 1) {
        $html .= "<p>Pour valider cet itinéraire, cliquez sur le lien suivant :</p>";
        $html .= "<p><a href='" . velo_lien_validation($config, (int) $velo_id) . "'>Valider l'itinéraire</a></p>";
      }
      $html .= "<p>Pour le vérifier / modifier :</p>";
      $html .= "<p><a href='" . velo_lien_edition_admin($config, ['falaise_id' => $falaise_id, 'gare_id' => $gare_id, 'velo_id' => $velo_id]) . "'>Modifier l'itinéraire</a></p>";
      $html .= "</body></html>";

      $data = [
        'to' => $to,
        'subject' => $subject,
        'html' => $html,
        'h:Reply-To' => $email
      ];

      sendMail($data);
    }

    // Rediriger vers la page de confirmation
    $redirect_params = http_build_query([
      'falaise_id' => $falaise_id,
      'gare_id' => $gare_id,
      'admin' => $admin ? $config["admin_token"] : ''
    ]);
    header("Location: /ajout/confirmation_velo.php?$redirect_params");
    exit;

  } else {
    die("Erreur lors de l'insertion : " . $mysqli->error);
  }
}
?>
