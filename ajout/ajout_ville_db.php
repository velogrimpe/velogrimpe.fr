<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$ville_nom = trim($_POST['ville_nom'] ?? '');
$admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
if (!$admin) {
    die('Accès refusé');
}
if (empty($ville_nom)) {
    echo "Pas de nom de ville";
    exit;
}

require_once "../database/velogrimpe.php";

$stmt = $mysqli->prepare("INSERT INTO villes (ville_nom) VALUES (?)");
if (!$stmt) {
    die("Problème de préparation de la requête : " . $mysqli->error);
}

$stmt->bind_param("s", $ville_nom);
$stmt->execute();
$stmt->close();

//Store in log
require_once "../lib/edit_logs.php";
$new_comment = [
    "ville_nom" => $ville_nom
];
$collection = 'villes';
$type = 'insert';
$record_id = $mysqli->insert_id;
logChanges($nom_prenom, $email, $type, $collection, $record_id, $new_comment);


header("Location: admin/ajout_donnees_admin.php");
exit;
?>