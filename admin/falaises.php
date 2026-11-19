<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_list.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';
$rows = [];
$res = $mysqli->query("SELECT f.falaise_id, f.falaise_nom, f.falaise_nomformate, f.falaise_public, f.falaise_zonename, (f.falaise_fermee <> '') AS fermee,
    COALESCE(ac.n, 0) AS nb_acces
  FROM falaises f
  LEFT JOIN (SELECT falaise_id, COUNT(*) AS n FROM acces_falaise GROUP BY falaise_id) ac ON ac.falaise_id = f.falaise_id
  ORDER BY f.falaise_nom");
while ($r = $res->fetch_assoc()) {
  // Détails = GeoJSON de barres (secteurs, parkings, approches), un fichier par falaise dans public/barres/.
  $path = vg_data_path('barres/' . $r['falaise_id'] . '_' . $r['falaise_nomformate'] . '.geojson');
  $mtime = file_exists($path) ? filemtime($path) : null;
  $rows[] = [
    'mtime' => $mtime ?: null,
    'id' => (int) $r['falaise_id'], 'nom' => $r['falaise_nom'], 'public' => (int) $r['falaise_public'],
    'zone' => (string) $r['falaise_zonename'], 'fermee' => (bool) $r['fermee'],
    'nb_acces' => (int) $r['nb_acces'],
  ];
}
admin_list_page([
  'active' => 'falaises', 'kind' => 'falaises', 'title' => 'Falaises', 'rows' => $rows, 'token' => $token,
  'add' => [['Falaise', '/ajout/ajout_falaise.php?admin=' . urlencode($token)]],
]);
