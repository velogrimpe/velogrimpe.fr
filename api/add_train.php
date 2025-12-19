<!--Pour la version admin : nettoyer les trois champs nom_prenom, email, message, et l'envoi de mail-->
<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
  $ville_id = (int) ($_POST['ville_id'] ?? 0);
  $gare_id = (int) ($_POST['gare_id'] ?? 0);
  $train_temps = isset($_POST['train_temps']) && $_POST['train_temps'] !== '' ? (int) $_POST['train_temps'] : null;
  $train_tgv = isset($_POST['train_tgv']) && $_POST['train_tgv'] !== '' ? (int) $_POST['train_tgv'] : 0;
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

  require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

  // Ensure not a duplicate
  $stmt = $mysqli->prepare("SELECT train_id FROM train WHERE ville_id = ? AND gare_id = ? AND train_tgv = ?");
  if (!$stmt) {
    die("Problème de préparation de la requête : " . $mysqli->error);
  }
  $stmt->bind_param("iii", $ville_id, $gare_id, $train_tgv);
  $stmt->execute();
  $res = $stmt->get_result();
  $train = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if ($train) {
    die("Un itinéraire " . ($train_tgv ? "TGV" : "TER") . " existe déjà entre cette ville et cette gare.");
  }

  $stmt = $mysqli->prepare("INSERT INTO train
        (ville_id, gare_id, train_temps, train_correspmin, train_correspmax, train_public,
        train_descr, train_depart, train_arrivee, train_contrib, train_tgv)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  if (!$stmt) {
    die("Problème de préparation de la requête : " . $mysqli->error);
  }

  // Bind des paramètres avec les valeurs, les valeurs null sont gérées comme NULL dans la base de données
  $stmt->bind_param(
    "iiiiiissssi",
    $ville_id,
    $gare_id,
    $train_temps,
    $train_correspmin,
    $train_correspmax,
    $train_public,
    $train_descr,
    $train_depart,
    $train_arrivee,
    $train_contrib,
    $train_tgv
  );
  if (!$stmt->execute()) {
    die("Erreur lors de l'insertion dans la base de données : " . $stmt->error);
  }

  $stmt->close();

  // Récupérer le nom de la ville pour l'email
  $stmt = $mysqli->prepare("SELECT ville_nom FROM villes WHERE ville_id = ?");
  $stmt->execute([$ville_id]);
  $res = $stmt->get_result();
  if (!$res) {
    $ville = 'Ville inconnue';
  } else {
    $ville = $res->fetch_assoc();
    $ville = $ville ? $ville['ville_nom'] : 'Ville inconnue';
  }

  //Store in log
  require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
  $new_comment = [
    "ville_id" => $ville_id,
    "gare_id" => $gare_id,
    "train_tgv" => $train_tgv,
    "train_temps" => $train_temps,
    "train_correspmin" => $train_correspmin,
    "train_correspmax" => $train_correspmax,
    "train_public" => $train_public,
    "train_descr" => $train_descr,
    "train_depart" => $train_depart,
    "train_arrivee" => $train_arrivee,
  ];
  $collection = 'train';
  $type = 'insert';
  $record_id = $mysqli->insert_id;
  logChanges(
    $nom_prenom,
    $email,
    $type,
    $collection,
    $record_id,
    null,
    $new_comment
  );



  if ($admin == 0) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
    $to = $config["contact_mail"];
    $subject = "🚃 Itinéraire train $ville ($train_depart) ⇢ $train_arrivee ajouté par $nom_prenom";

    $html = "<html><body>";
    $html .= "<h1>L'itinéraire de $ville ($train_depart) à $train_arrivee a été ajouté par $nom_prenom</h1>";
    $html .= "<p>email : <a href='mailto:$email'>$email</a></p>";
    if ($message) {
      $html .= "<p>Message additionnel : " . htmlspecialchars(nl2br(trim($message))) . "</p>";
    }
    $html .= "<p>Détails de l'itinéraire :</p>";
    $html .= "<ul>";
    $html .= "<li><b>Ville</b>: $ville ($ville_id)</li>";
    $html .= "<li><b>Départ</b>: $train_depart</li>";
    $html .= "<li><b>Arrivée</b>: $train_arrivee</li>";
    $html .= "<li><b>Temps</b>: $train_temps min</li>";
    $html .= "<li><b>Correspondance min</b>: $train_correspmin</li>";
    $html .= "<li><b>Correspondance max</b>: $train_correspmax</li>";
    $html .= "<li><b>Type</b>: " . ($train_tgv ? "TGV (trajet nécessitant un TGV)" : "TER / trains régionaux") . "</li>";
    $html .= "<li><b>Public</b>: " . ($train_public ? 'Oui' : 'Non') . "</li>";
    $html .= "<li><b>Description</b>: " . htmlspecialchars(nl2br(trim($train_descr))) . "</li>";
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
}