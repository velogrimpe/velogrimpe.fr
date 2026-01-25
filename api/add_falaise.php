<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die(json_encode(["error" => "Method not allowed"]));
}
// Remplissage des champs obligatoires de la table
$admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
$falaise_id = json_decode(trim($_POST['falaise_id'] ?? null));
$falaise_nom = trim($_POST['falaise_nom'] ?? '');
$falaise_nomformate = trim($_POST['falaise_nomformate'] ?? '');
$falaise_latlng = trim($_POST['falaise_latlng'] ?? '');
$falaise_exposhort1 = trim($_POST['falaise_exposhort1'] ?? '');
$falaise_cotmin = trim($_POST['falaise_cotmin'] ?? '');
$falaise_cotmax = trim($_POST['falaise_cotmax'] ?? '');
$falaise_zonename = trim($_POST['falaise_zonename'] ?? '');
$falaise_deptname = trim($_POST['falaise_deptname'] ?? '');
$falaise_deptcode = trim($_POST['falaise_deptcode'] ?? '');
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
$falaise_nbvoies = trim($_POST['falaise_nbvoies'] ?? null);
$nom_prenom = trim($_POST['nom_prenom'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
$isEdition = $falaise_id !== null;
$falaise_contrib = trim("'" . $nom_prenom . "','" . $email . "'");

$champs_obligatoires = [
  'falaise_nom' => $falaise_nom,
  'falaise_nomformate' => $falaise_nomformate,
  'falaise_latlng' => $falaise_latlng,
  'falaise_exposhort1' => $falaise_exposhort1,
  'falaise_cotmin' => $falaise_cotmin,
  'falaise_cotmax' => $falaise_cotmax,
  'falaise_maa' => $falaise_maa,
  'falaise_mar' => $falaise_mar,
  'falaise_public' => $falaise_public,
  'falaise_topo' => $falaise_topo,
  'falaise_expotxt' => $falaise_expotxt,
  'falaise_matxt' => $falaise_matxt,
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

if ($mysqli->connect_error) {
  die("Erreur de connexion à la base de données : " . $mysqli->connect_error);
}


// get old record
$stmt = $mysqli->prepare("SELECT * FROM falaises WHERE falaise_id = ?");
$stmt->bind_param("i", $falaise_id);
$stmt->execute();
$result = $stmt->get_result();
$oldFalaise = $result->fetch_assoc();
$stmt->close();

// Préparation de la requête d'insertion
$stmt = $mysqli->prepare("INSERT INTO falaises (
    falaise_id,
    falaise_nom,
    falaise_zonename,
    falaise_deptcode,
    falaise_deptname,
    falaise_nomformate,
    falaise_public,
    falaise_latlng,
    falaise_exposhort1,
    falaise_exposhort2,
    falaise_cotmin,
    falaise_cotmax,
    falaise_maa,
    falaise_mar,
    falaise_topo,
    falaise_expotxt,
    falaise_matxt,
    falaise_cottxt,
    falaise_voletcarto,
    falaise_voies,
    falaise_gvtxt,
    falaise_gvnb,
    falaise_rq,
    falaise_fermee,
    falaise_txt1,
    falaise_txt2,
    falaise_leg1,
    falaise_txt3,
    falaise_txt4,
    falaise_leg2,
    falaise_leg3,
    falaise_contrib,
    falaise_bloc,
    falaise_nbvoies
  )
  VALUES (
    COALESCE(?, NULL),
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?,
    ?
  )
  ON DUPLICATE KEY UPDATE
  falaise_nom = VALUES(falaise_nom),
  falaise_zonename = VALUES(falaise_zonename),
  falaise_deptcode = VALUES(falaise_deptcode),
  falaise_deptname = VALUES(falaise_deptname),
  -- falaise_nomformate = VALUES(falaise_nomformate), -- ne pas modifier le nom formate en edition
  -- falaise_contrib = VALUES(falaise_contrib), -- ne pas modifier le contributeur en edition
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
  falaise_bloc = VALUES(falaise_bloc),
  falaise_nbvoies = VALUES(falaise_nbvoies),
  date_modification = NOW()
  ");

if (!$stmt) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}

$stmt->bind_param(
  "isssssisssssiissssssssssssssssssii",
  $falaise_id,
  $falaise_nom,
  $falaise_zonename,
  $falaise_deptcode,
  $falaise_deptname,
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
  $falaise_bloc,
  $falaise_nbvoies
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

  $targetFileName = "{$falaiseId}_{$falaiseNomformate}_{$suffix}.{$fileExtension}";
  $targetFilePath = $targetDir . DIRECTORY_SEPARATOR . $targetFileName;

  // store original and webp without format conversion
  if (!move_uploaded_file($fileTmpName, $targetFilePath)) {
    return "Erreur lors de l'upload de $fileInputName.";
  }

  return null;
}

foreach ([
  'falaise_img1' => 'img1',
  'falaise_img2' => 'img2',
  'falaise_img3' => 'img3',
  'falaise_img1_webp' => 'img1',
  'falaise_img2_webp' => 'img2',
  'falaise_img3_webp' => 'img3'
] as $fileInputName => $suffix) {
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
if (!$res) {
  die("Erreur lors de l'ajout de la falaise : " . $stmt->error);
}

////// DEBUT GESTION DES CHANGEMENTS
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$newFalaise = [
  "falaise_id" => $falaise_id,
  "falaise_nom" => $falaise_nom,
  "falaise_latlng" => $falaise_latlng,
  "falaise_exposhort1" => $falaise_exposhort1,
  "falaise_exposhort2" => $champs['falaise_exposhort2'],
  "falaise_cotmin" => $falaise_cotmin,
  "falaise_cotmax" => $falaise_cotmax,
  "falaise_zonename" => $falaise_zonename,
  "falaise_deptcode" => $falaise_deptcode,
  "falaise_deptname" => $falaise_deptname,
  "falaise_maa" => $falaise_maa,
  "falaise_mar" => $falaise_mar,
  "falaise_public" => $falaise_public,
  "falaise_topo" => $falaise_topo,
  "falaise_expotxt" => $falaise_expotxt,
  "falaise_matxt" => $falaise_matxt,
  "falaise_cottxt" => $falaise_cottxt,
  "falaise_voletcarto" => $falaise_voletcarto,
  "falaise_voies" => $falaise_voies,
  "falaise_bloc" => $falaise_bloc,
  "falaise_nbvoies" => $falaise_nbvoies,
  "falaise_gvtxt" => $champs['falaise_gvtxt'],
  "falaise_gvnb" => $champs['falaise_gvnb'],
  "falaise_rq" => $champs['falaise_rq'],
  "falaise_fermee" => $champs['falaise_fermee'],
  "falaise_txt1" => $champs['falaise_txt1'],
  "falaise_txt2" => $champs['falaise_txt2'],
  "falaise_leg1" => $champs['falaise_leg1'],
  "falaise_txt3" => $champs['falaise_txt3'],
  "falaise_txt4" => $champs['falaise_txt4'],
  "falaise_leg2" => $champs['falaise_leg2'],
  "falaise_leg3" => $champs['falaise_leg3'],
];
foreach (["falaise_img1", "falaise_img2", "falaise_img3"] as $img) {
  if (isset($_FILES[$img]) && $_FILES[$img]['error'] === UPLOAD_ERR_OK) {
    $newFalaise[$img] = "image modifiée";
  }
}
$record_id = $falaise_id;
$type = $isEdition ? "update" : "insert";
$collection = 'falaises';
$changes_json = logChanges(
  $nom_prenom,
  $email,
  $type,
  $collection,
  $record_id,
  $falaise_id,
  $newFalaise,
  $oldFalaise
);
////// FIN GESTION DES CHANGEMENTS

// Envoi du mail de confirmation seulement si admin = 0
if ($admin == 0) {
  require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
  $to = $config["contact_mail"];
  $subject = "🧗 Falaise '$falaise_nom' " . ($isEdition ? "modifiée" : "ajoutée") . " par $nom_prenom";

  $html = "<html><body>";
  $html .= "<h1>La falaise de $falaise_nom a été " . ($isEdition ? "modifiée" : "ajoutée") . " par $nom_prenom</h1>";
  $html .= "<p>email : <a href='mailto:$email'>$email</a></p>";
  $html .= "<p><a href='https://velogrimpe.fr/falaise.php?falaise_id=$falaise_id'>Voir la falaise</a></p>";
  if ($message) {
    $html .= "<p>Message additionnel : " . htmlspecialchars(nl2br(trim($message))) . "</p>";
  }
  if ($isEdition && $changes_json) {
    $html .= "<h2>Modifications apportées :</h2>";
    $changes = json_decode($changes_json, true);
    $html .= "<ul>";
    foreach ($changes as $change) {
      $field = htmlspecialchars($change['field']);
      $old = htmlspecialchars($change['old']);
      $new = htmlspecialchars($change['new']);
      $html .= "<li><b>$field</b> : <ul>";
      $html .= "<li><span style='color: red;'>$old</span></li>";
      $html .= "<li> → <span style='color: green;'>$new</span></li></ul></li>";
    }
    $html .= "</ul>";
  } else if ($isEdition) {
    $html .= "<p>Aucune modification détectée.</p>";
  } else {
    $html .= "<h2>Détails de la falaise</h2>";
    $html .= "<ul>";
    $html .= "<li><b>Nom</b>: $falaise_nom</li>";
    $html .= "<li><b>Zone</b>: $falaise_zonename</li>";
    $html .= "<li><b>Département</b>: $falaise_deptcode - $falaise_deptname</li>";
    $html .= "<li><b>Topo</b>: $falaise_topo</li>";
    $html .= "<li><b>Nb Voies</b>: $falaise_nbvoies</li>";
    $html .= "<li><b>Voies</b>: $falaise_voies</li>";
    $html .= "<li><b>Volet carto</b>: $falaise_voletcarto</li>";
    $html .= "<li><b>Expositions</b>: $falaise_exposhort1</li>";
    $html .= "<li><b>Exposition</b>: $falaise_expotxt</li>";
    $html .= "<li><b>Cotations min/max</b>: $falaise_cotmin/$falaise_cotmax</li>";
    $html .= "<li><b>Cotations</b>: $falaise_cottxt</li>";
    $html .= "<li><b>Approche A/R</b>: $falaise_maa/$falaise_mar</li>";
    $html .= "<li><b>Approche</b>: $falaise_matxt</li>";
    $html .= "<li><b>Grandes voies</b>: " . $champs['falaise_gvtxt'] . "</li>";
    $html .= "<li><b>Nombre de GV</b>: " . $champs['falaise_gvnb'] . "</li>";
    $html .= "<li><b>Bloc</b>: $falaise_bloc</li>";
    $html .= "<li><b>Remarque</b>: " . $champs['falaise_rq'] . "</li>";
    $html .= "</ul>";
  }
  $html .= "<h2>Actions</h2>";
  $html .= "<p>Pour valider cette falaise, cliquez sur le lien suivant :</p>";
  $html .= "<p><a href='https://velogrimpe.fr/api/private/accept_falaise.php?admin=" . urlencode($config["admin_token"]) . "&falaise_id=$falaise_id'>Valider la falaise</a></p>";
  $html .= "<p>Pour modifier cette falaise, cliquez sur le lien suivant :</p>";
  $html .= "<p><a href='https://velogrimpe.fr/ajout/ajout_falaise.php?admin=" . urlencode($config["admin_token"]) . "&falaise_id=$falaise_id'>Modifier la falaise</a></p>";
  $html .= "</body></html>";

  $data = [
    'to' => $to,
    'subject' => $subject,
    'html' => $html,
    'h:Reply-To' => $email
  ];
  sendMail($data);

  // mail($to, $subject, $body, $headers);
}

// Redirect vers la page de confirmation
$params = http_build_query([
  'falaise_id' => $falaise_id,
  'type' => $isEdition ? 'update' : 'insert',
  'step' => 1,
  'admin' => $admin ? 1 : 0,
  'nom_prenom' => $nom_prenom,
  'email' => $email
]);
header("Location: /ajout/confirmation_falaise.php?" . $params);
exit;


?>