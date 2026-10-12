<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$falaise_id = $_GET['falaise_id'] ?? '';
if (empty($falaise_id)) {
  die("Un id de falaise est requis");
}

// Endpoint public, lu par le préremplissage de ajout/ajout_falaise.php : n'y
// mettre que les champs du formulaire. Pas de SELECT * : la ligne contient
// l'email du contributeur (falaise_contrib).
$stmt = $mysqli->prepare("
  SELECT
    falaise_id, falaise_nom, falaise_nomformate, falaise_latlng,
    falaise_zonename, falaise_deptname, falaise_deptcode,
    falaise_cottxt, falaise_cotmin, falaise_cotmax,
    falaise_expotxt, falaise_exposhort1, falaise_exposhort2,
    falaise_voies, falaise_nbvoies, falaise_topo, falaise_matxt, falaise_maa, falaise_mar,
    falaise_gvtxt, falaise_gvnb, falaise_bloc, falaise_rq, falaise_hebergement, falaise_acces_bus,
    falaise_txt1, falaise_txt2, falaise_txt3, falaise_txt4,
    falaise_leg1, falaise_leg2, falaise_leg3, falaise_fermee, falaise_voletcarto
  FROM falaises
  WHERE falaise_id = ?");

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
