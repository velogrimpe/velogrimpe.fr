<?php

$mail = trim($_GET['mail'] ?? '');
$token = trim($_GET['token'] ?? '');

if (empty($mail) || empty($token)) {
  die("Paramètres manquants.");
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
$stmt = $mysqli->prepare("SELECT mail FROM mailing_list WHERE mail = ? AND token = ? AND desinscrit = 0");
$stmt->bind_param('ss', $mail, $token);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  die("Lien invalide ou déjà utilisé.");
}

$updateStmt = $mysqli->prepare("UPDATE mailing_list SET desinscrit = 1, date_desinscription = NOW() WHERE mail = ? AND token = ?");
$updateStmt->bind_param('ss', $mail, $token);
$updateStmt->execute();

echo "Votre désinscription a bien été prise en compte pour le mail $mail. Vous ne recevrez plus de newsletters de Velogrimpe.fr.";