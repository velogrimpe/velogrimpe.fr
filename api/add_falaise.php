<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Détection d'une soumission AJAX (fetch depuis le formulaire). Si c'est le cas,
// on répond en JSON pour que le front conserve les données saisies en cas d'échec.
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
  && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * Termine la requête sur une erreur : JSON si appel AJAX, sinon texte + retour formulaire.
 */
function respondError(string $message, int $code = 400): void
{
  global $isAjax;
  http_response_code($code);
  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
  } else {
    echo "<p style='color:red;'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<a href='/ajout/ajout_falaise.php'>Retour au formulaire</a>";
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  respondError("Method not allowed", 405);
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
    respondError("Il manque une info obligatoire : " . $champ);
  }
}

// Remplissage par défaut des champs non obligatoires
$champs = [
  'falaise_exposhort2' => null,
  'falaise_gvtxt' => null,
  'falaise_gvnb' => null,
  'falaise_rq' => null,
  'falaise_hebergement' => null,
  'falaise_acces_bus' => null,
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

// Sanitisation HTML des champs riches (éditeur TipTap), affichés sans htmlspecialchars.
// Allowlist stricte : p, br, strong/b, em/i, s, u, a[href], ul, li (voir lib/richtext.php).
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/richtext.php';

$falaise_topo = rt_sanitize_html($falaise_topo);
$falaise_matxt = rt_sanitize_html($falaise_matxt);

foreach ([
  'falaise_fermee',
  'falaise_gvtxt',
  'falaise_rq',
  'falaise_hebergement',
  'falaise_acces_bus',
  'falaise_txt1',
  'falaise_txt2',
  'falaise_txt3',
  'falaise_txt4',
  'falaise_leg1',
  'falaise_leg2',
  'falaise_leg3',
] as $champ) {
  if (array_key_exists($champ, $champs)) {
    $champs[$champ] = rt_sanitize_html($champs[$champ]);
  }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

if ($mysqli->connect_error) {
  respondError("Erreur de connexion à la base de données : " . $mysqli->connect_error, 500);
}


// get old record
$stmt = $mysqli->prepare("SELECT * FROM falaises WHERE falaise_id = ?");
$stmt->bind_param("i", $falaise_id);
$stmt->execute();
$result = $stmt->get_result();
$oldFalaise = $result->fetch_assoc();
$stmt->close();


$falaise_gvnb = $champs['falaise_gvnb'];
$falaise_exposhort2 = $champs['falaise_exposhort2'];
$falaise_gvtxt = $champs['falaise_gvtxt'];
$falaise_rq = $champs['falaise_rq'];
$falaise_hebergement = $champs['falaise_hebergement'];
$falaise_acces_bus = $champs['falaise_acces_bus'];
$falaise_fermee = $champs['falaise_fermee'];
$falaise_txt1 = $champs['falaise_txt1'];
$falaise_txt2 = $champs['falaise_txt2'];
$falaise_txt3 = $champs['falaise_txt3'];
$falaise_txt4 = $champs['falaise_txt4'];
$falaise_leg1 = $champs['falaise_leg1'];
$falaise_leg2 = $champs['falaise_leg2'];
$falaise_leg3 = $champs['falaise_leg3'];

// Préparation de la requête d'insertion
$stmt = $mysqli->prepare("INSERT INTO falaises (
    falaise_id,
    -- integers
    falaise_bloc,
    falaise_maa,
    falaise_mar,
    falaise_nbvoies,
    falaise_public,
    -- strings
    falaise_acces_bus,
    falaise_contrib,
    falaise_cotmax,
    falaise_cotmin,
    falaise_cottxt,
    falaise_deptcode,
    falaise_deptname,
    falaise_exposhort1,
    falaise_exposhort2,
    falaise_expotxt,
    falaise_fermee,
    falaise_gvnb,
    falaise_gvtxt,
    falaise_hebergement,
    falaise_latlng,
    falaise_leg1,
    falaise_leg2,
    falaise_leg3,
    falaise_matxt,
    falaise_nom,
    falaise_nomformate,
    falaise_rq,
    falaise_topo,
    falaise_txt1,
    falaise_txt2,
    falaise_txt3,
    falaise_txt4,
    falaise_voies,
    falaise_voletcarto,
    falaise_zonename
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
    ?,
    ?,
    ?
  )
  ON DUPLICATE KEY UPDATE
  -- falaise_nomformate = VALUES(falaise_nomformate), -- ne pas modifier le nom formate en edition
  -- falaise_contrib = VALUES(falaise_contrib), -- ne pas modifier le contributeur en edition
  -- falaise_nom = VALUES(falaise_nom), -- ne pas modifier le nom
  falaise_acces_bus = VALUES(falaise_acces_bus),
  falaise_bloc = VALUES(falaise_bloc),
  falaise_cotmax = VALUES(falaise_cotmax),
  falaise_cotmin = VALUES(falaise_cotmin),
  falaise_cottxt = VALUES(falaise_cottxt),
  falaise_deptcode = VALUES(falaise_deptcode),
  falaise_deptname = VALUES(falaise_deptname),
  falaise_exposhort1 = VALUES(falaise_exposhort1),
  falaise_exposhort2 = VALUES(falaise_exposhort2),
  falaise_expotxt = VALUES(falaise_expotxt),
  falaise_fermee = VALUES(falaise_fermee),
  falaise_gvnb = VALUES(falaise_gvnb),
  falaise_gvtxt = VALUES(falaise_gvtxt),
  falaise_hebergement = VALUES(falaise_hebergement),
  falaise_latlng = VALUES(falaise_latlng),
  falaise_leg1 = VALUES(falaise_leg1),
  falaise_leg2 = VALUES(falaise_leg2),
  falaise_leg3 = VALUES(falaise_leg3),
  falaise_maa = VALUES(falaise_maa),
  falaise_mar = VALUES(falaise_mar),
  falaise_matxt = VALUES(falaise_matxt),
  falaise_nbvoies = VALUES(falaise_nbvoies),
  falaise_public = VALUES(falaise_public),
  falaise_rq = VALUES(falaise_rq),
  falaise_topo = VALUES(falaise_topo),
  falaise_txt1 = VALUES(falaise_txt1),
  falaise_txt2 = VALUES(falaise_txt2),
  falaise_txt3 = VALUES(falaise_txt3),
  falaise_txt4 = VALUES(falaise_txt4),
  falaise_voies = VALUES(falaise_voies),
  falaise_voletcarto = VALUES(falaise_voletcarto),
  falaise_zonename = VALUES(falaise_zonename),
  date_modification = NOW()
  ");

if (!$stmt) {
  respondError("Problème de préparation de la requête : " . $mysqli->error, 500);
}

$stmt->bind_param(
  "iiiiiissssssssssssssssssssssssssssss",
  $falaise_id,
  // integers
  $falaise_bloc,
  $falaise_maa,
  $falaise_mar,
  $falaise_nbvoies,
  $falaise_public,
  // strings
  $falaise_acces_bus,
  $falaise_contrib,
  $falaise_cotmax,
  $falaise_cotmin,
  $falaise_cottxt,
  $falaise_deptcode,
  $falaise_deptname,
  $falaise_exposhort1,
  $falaise_exposhort2,
  $falaise_expotxt,
  $falaise_fermee,
  $falaise_gvnb,
  $falaise_gvtxt,
  $falaise_hebergement,
  $falaise_latlng,
  $falaise_leg1,
  $falaise_leg2,
  $falaise_leg3,
  $falaise_matxt,
  $falaise_nom,
  $falaise_nomformate,
  $falaise_rq,
  $falaise_topo,
  $falaise_txt1,
  $falaise_txt2,
  $falaise_txt3,
  $falaise_txt4,
  $falaise_voies,
  $falaise_voletcarto,
  $falaise_zonename,
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
  respondError("Le dossier cible $targetDir n'existe pas ou est introuvable.", 500);
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

$errors = [];
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
  $msg = "La falaise a bien été " . ($isEdition ? "modifiée" : "ajoutée")
    . " (ID $falaise_id), mais certaines images n'ont pas pu être téléversées :\n"
    . implode("\n", $errors)
    . "\nVous pouvez les ajouter à nouveau en modifiant la falaise.";
  respondError($msg, 500);
}
if (!$res) {
  respondError("Erreur lors de l'ajout de la falaise : " . $stmt->error, 500);
}

////// DEBUT GESTION DES CHANGEMENTS
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$newFalaise = [
  "falaise_id" => $falaise_id,
  "falaise_nom" => $falaise_nom,
  "falaise_latlng" => $falaise_latlng,
  "falaise_exposhort1" => $falaise_exposhort1,
  "falaise_exposhort2" => $falaise_exposhort2,
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
  "falaise_gvtxt" => $falaise_gvtxt,
  "falaise_gvnb" => $falaise_gvnb,
  "falaise_rq" => $falaise_rq,
  "falaise_hebergement" => $falaise_hebergement,
  "falaise_acces_bus" => $falaise_acces_bus,
  "falaise_fermee" => $falaise_fermee,
  "falaise_txt1" => $falaise_txt1,
  "falaise_txt2" => $falaise_txt2,
  "falaise_leg1" => $falaise_leg1,
  "falaise_txt3" => $falaise_txt3,
  "falaise_txt4" => $falaise_txt4,
  "falaise_leg2" => $falaise_leg2,
  "falaise_leg3" => $falaise_leg3,
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

// Envoi du mail de confirmation seulement
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/sendmail.php';
$to = $admin == 0 ? $config["contact_mail"] : $config["admin_mail"];
$subject = "🧗 Falaise '$falaise_nom' " . ($isEdition ? "modifiée" : "ajoutée") . " par $nom_prenom";

$html = "<html><body>";
$html .= "<h1>La falaise de " . htmlspecialchars($falaise_nom) . " a été " . ($isEdition ? "modifiée" : "ajoutée") . " par " . htmlspecialchars($nom_prenom) . "</h1>";
$html .= "<p>email : <a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></p>";
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
  $html .= "<li><b>Nom</b>: " . htmlspecialchars($falaise_nom) . "</li>";
  $html .= "<li><b>Zone</b>: " . htmlspecialchars($falaise_zonename) . "</li>";
  $html .= "<li><b>Département</b>: " . htmlspecialchars($falaise_deptcode) . " - " . htmlspecialchars($falaise_deptname) . "</li>";
  $html .= "<li><b>Topo</b>: " . htmlspecialchars($falaise_topo) . "</li>";
  $html .= "<li><b>Nb Voies</b>: " . htmlspecialchars($falaise_nbvoies) . "</li>";
  $html .= "<li><b>Voies</b>: " . htmlspecialchars($falaise_voies) . "</li>";
  $html .= "<li><b>Volet carto</b>: " . htmlspecialchars($falaise_voletcarto) . "</li>";
  $html .= "<li><b>Expositions</b>: " . htmlspecialchars($falaise_exposhort1) . "</li>";
  $html .= "<li><b>Exposition</b>: " . htmlspecialchars($falaise_expotxt) . "</li>";
  $html .= "<li><b>Cotations min/max</b>: " . htmlspecialchars($falaise_cotmin) . "/" . htmlspecialchars($falaise_cotmax) . "</li>";
  $html .= "<li><b>Cotations</b>: " . htmlspecialchars($falaise_cottxt) . "</li>";
  $html .= "<li><b>Approche A/R</b>: " . htmlspecialchars($falaise_maa) . "/" . htmlspecialchars($falaise_mar) . "</li>";
  $html .= "<li><b>Approche</b>: " . htmlspecialchars($falaise_matxt) . "</li>";
  $html .= "<li><b>Grandes voies</b>: " . htmlspecialchars($champs['falaise_gvtxt']) . "</li>";
  $html .= "<li><b>Nombre de GV</b>: " . htmlspecialchars($champs['falaise_gvnb']) . "</li>";
  $html .= "<li><b>Bloc</b>: " . htmlspecialchars($falaise_bloc) . "</li>";
  $html .= "<li><b>Remarque</b>: " . htmlspecialchars($champs['falaise_rq']) . "</li>";
  $html .= "<li><b>Hébergement</b>: " . htmlspecialchars($champs['falaise_hebergement']) . "</li>";
  $html .= "<li><b>Accès bus</b>: " . htmlspecialchars($champs['falaise_acces_bus']) . "</li>";
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


// Redirect vers la page de confirmation
$params = http_build_query([
  'falaise_id' => $falaise_id,
  'type' => $isEdition ? 'update' : 'insert',
  'step' => 1,
  'admin' => $admin ? 1 : 0,
  'nom_prenom' => $nom_prenom,
  'email' => $email
]);
$redirect = "/ajout/confirmation_falaise.php?" . $params;
if ($isAjax) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['redirect' => $redirect], JSON_UNESCAPED_UNICODE);
} else {
  header("Location: " . $redirect);
}
exit;


?>