<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/admin_list.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/paths.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/velo_lib.php';
// Tous les accès arrêt → falaise : vélo et à pied.
$rows = [];
$res = $mysqli->query("SELECT af.acces_id, af.type, af.arret_id, af.falaise_id, af.variante, af.km, af.dplus, af.public, af.contrib, af.depart_slug, af.arrivee_slug, af.variante_formate,
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
    // Trace GPX présente sur disque (public/gpx/, cf. velo_gpx_chemin)
    'gpx' => file_exists(velo_gpx_chemin((int) $r['acces_id'], (string) $r['depart_slug'], (string) $r['arrivee_slug'], (string) $r['variante_formate'])),
    // URL de téléchargement de la trace (null sans GPX), cf. velo_gpx_url
    'gpx_url' => file_exists(velo_gpx_chemin((int) $r['acces_id'], (string) $r['depart_slug'], (string) $r['arrivee_slug'], (string) $r['variante_formate']))
      ? velo_gpx_url((int) $r['acces_id'], (string) $r['depart_slug'], (string) $r['arrivee_slug'], (string) $r['variante_formate']) : null,
  ];
}
admin_list_page([
  'active' => 'acces', 'kind' => 'acces', 'title' => 'Accès falaises', 'rows' => $rows, 'token' => $token,
  'add' => [['Accès', '/ajout/ajout_velo.php?admin=' . urlencode($token)]],
]);
