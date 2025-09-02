<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$falaise_id = $_GET['falaise_id'] ?? '';
if (empty($falaise_id)) {
  die("Un id de falaise est requis");
}

$stmt = $mysqli->prepare("SELECT *  FROM falaises WHERE falaise_id = ?");

if (!$stmt) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$stmt->bind_param("i", $falaise_id);
$stmt->execute();
$res = $stmt->get_result();

$falaise = $res->fetch_assoc();
$stmt->close();

// return the list of trains
echo json_encode($falaise);
return;
