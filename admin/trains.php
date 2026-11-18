<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_list.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
// Falaises « liées » à un trajet : celles accessibles depuis la gare d'arrivée (acces_falaise).
$falaises = [];
$res = $mysqli->query("SELECT DISTINCT af.arret_id, f.falaise_id, f.falaise_nom FROM acces_falaise af
  JOIN falaises f ON f.falaise_id = af.falaise_id ORDER BY f.falaise_nom");
while ($r = $res->fetch_assoc()) $falaises[(int) $r['arret_id']][] = ['id' => (int) $r['falaise_id'], 'nom' => $r['falaise_nom']];
$rows = [];
$res = $mysqli->query("SELECT t.train_id, t.ville_id, t.arret_id, t.train_temps, t.train_tempsmax, t.train_nbparjour, t.train_tgv,
    v.ville_nom, a.nom AS arret_nom
  FROM train t
  LEFT JOIN villes v ON v.ville_id = t.ville_id
  LEFT JOIN arrets a ON a.id = t.arret_id
  ORDER BY v.ville_nom, a.nom");
while ($r = $res->fetch_assoc()) {
  $rows[] = [
    'id' => (int) $r['train_id'], 'ville_id' => (int) $r['ville_id'], 'ville' => (string) $r['ville_nom'], 'arret' => (string) $r['arret_nom'],
    'temps' => $r['train_temps'] !== null ? (int) $r['train_temps'] : null, 'tempsmax' => $r['train_tempsmax'] ? (int) $r['train_tempsmax'] : null,
    'nbparjour' => $r['train_nbparjour'] ? (int) $r['train_nbparjour'] : null, 'tgv' => (bool) $r['train_tgv'],
    'falaises' => $falaises[(int) $r['arret_id']] ?? [],
  ];
}
admin_list_page([
  'active' => 'trains', 'kind' => 'trains', 'title' => 'Itinéraires train', 'rows' => $rows, 'token' => $token,
  'add' => [['Itinéraire train', '/ajout/ajout_train.php?admin=' . urlencode($token)]],
]);
