<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_list.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
// Tous les accès arrêt → falaise : vélo et à pied.
$rows = [];
$res = $mysqli->query("SELECT af.acces_id, af.type, af.arret_id, af.falaise_id, af.variante, af.km, af.dplus, af.public, af.contrib,
    a.nom AS arret_nom, f.falaise_nom
  FROM acces_falaise af
  LEFT JOIN arrets a ON a.id = af.arret_id
  LEFT JOIN falaises f ON f.falaise_id = af.falaise_id
  ORDER BY f.falaise_nom, a.nom, af.variante");
while ($r = $res->fetch_assoc()) {
  $rows[] = [
    'id' => (int) $r['acces_id'], 'type' => $r['type'], 'arret_id' => (int) $r['arret_id'], 'falaise_id' => (int) $r['falaise_id'],
    'arret' => (string) $r['arret_nom'], 'falaise' => (string) $r['falaise_nom'], 'variante' => (string) $r['variante'],
    'km' => $r['km'] !== null ? round((float) $r['km'], 1) : null, 'dplus' => $r['dplus'] !== null ? (int) $r['dplus'] : null,
    'public' => (int) $r['public'], 'contrib' => (string) $r['contrib'],
  ];
}
admin_list_page([
  'active' => 'acces', 'kind' => 'acces', 'title' => 'Accès falaises', 'rows' => $rows, 'token' => $token,
  'add' => [['Accès', '/ajout/ajout_velo.php?admin=' . urlencode($token)]],
]);
