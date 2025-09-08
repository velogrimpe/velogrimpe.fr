<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$ville_nom = trim($_POST['ville_nom'] ?? '');
$ville_tableau = isset($_POST['ville_tableau']) && $_POST['ville_tableau'] === 'on' ? 1 : 0;
$admin = trim($_POST['admin'] ?? '') == $config["admin_token"];
if (!$admin) {
    die('Accès refusé');
}
if (empty($ville_nom)) {
    echo "Pas de nom de ville";
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$stmt = $mysqli->prepare("INSERT INTO 
    villes (ville_nom, ville_tableau)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE ville_nom = ville_nom, ville_tableau = ville_tableau
    ");
if (!$stmt) {
    die("Problème de préparation de la requête : " . $mysqli->error);
}

$stmt->bind_param("si", $ville_nom, $ville_tableau);
$stmt->execute();
$stmt->close();

//Store in log
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/edit_logs.php';
$new_comment = [
    "ville_nom" => $ville_nom,
    "ville_tableau" => $ville_tableau
];
$collection = 'villes';
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
