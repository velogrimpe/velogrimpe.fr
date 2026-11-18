<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_list.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
$rows = [];
$res = $mysqli->query("SELECT v.ville_id, v.ville_nom, v.ville_tableau, COUNT(t.train_id) AS nb_trains
  FROM villes v LEFT JOIN train t ON t.ville_id = v.ville_id
  GROUP BY v.ville_id, v.ville_nom, v.ville_tableau ORDER BY v.ville_nom");
while ($r = $res->fetch_assoc()) {
  $rows[] = ['id' => (int) $r['ville_id'], 'nom' => $r['ville_nom'], 'tableau' => (bool) $r['ville_tableau'], 'nb_trains' => (int) $r['nb_trains']];
}
admin_list_page([
  'active' => 'villes', 'kind' => 'villes', 'title' => 'Villes de départ', 'rows' => $rows, 'token' => $token,
  'add' => [['Ville de départ', '/ajout/ajout_ville.php?admin=' . urlencode($token)]],
]);
