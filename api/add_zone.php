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

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$stmt = $mysqli->prepare("INSERT INTO zones (zone_nom) VALUES (?)");
if (!$stmt) {
    die("Problème de préparation de la requête : " . $mysqli->error);
}

$stmt->bind_param("s", $zone_nom);
$stmt->execute();
$stmt->close();

//Store in log
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$new_comment = [
    "zone_nom" => $zone_nom
];
$collection = 'zones';
$type = 'insert';
$record_id = $mysqli->insert_id;
logChanges(
    $_SERVER['PHP_AUTH_USER'] ?? 'admin',
    $config['contact_mail'],
    $type,
    $collection,
    $record_id,
    null,
    $new_comment
);

header("Location: /admin/");
exit;
?>