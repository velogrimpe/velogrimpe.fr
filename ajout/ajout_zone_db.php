<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$zone_nom = trim($_POST['zone_nom'] ?? '');
$admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
if (!$admin) {
    die('Accès refusé');
}
if (empty($zone_nom)) {
    echo "Pas de nom de zone";
    exit;
}

require_once "../database/velogrimpe.php";

$stmt = $mysqli->prepare("INSERT INTO zones (zone_nom) VALUES (?)");
if (!$stmt) {
    die("Problème de préparation de la requête : " . $mysqli->error);
}

$stmt->bind_param("s", $zone_nom);
$stmt->execute();
$stmt->close();

header("Location: admin/ajout_donnees_admin.php");
exit;
?>