<?php
// Proxy de téléchargement des exports open data, destiné à tracer les
// téléchargements : les liens directs vers les .geojson ne laissent de trace
// que dans les access logs Apache. On logge un événement analytics (même canal
// que le cron d'export) puis on sert le fichier en pièce jointe
// (Content-Disposition: attachment) pour forcer le téléchargement plutôt qu'un
// affichage inline. mod_deflate compresse la sortie (cf. .htaccess).

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/pv.php';

// Whitelist slug => fichier réel : interdit tout path traversal et borne le
// tracking aux exports officiels.
$exports = [
  'falaises' => 'falaises.geojson',
  'falaises-details' => 'falaises-details.geojson',
];

$slug = $_GET['f'] ?? '';
if (!isset($exports[$slug])) {
  http_response_code(404);
  echo 'Export inconnu';
  exit;
}

$file = $exports[$slug];
$path = $_SERVER['DOCUMENT_ROOT'] . '/open-data/' . $file;

if (!is_file($path)) {
  http_response_code(404);
  echo 'Export introuvable';
  exit;
}

// Trace le téléchargement (source/canal distincts du cron, pour les filtrer).
sendEvent('/open-data/' . $file, 'vg', 'vg-open-data', 'event: download-open-data', $_SERVER['HTTP_USER_AGENT'] ?? null);

// Sert le fichier en pièce jointe pour forcer le téléchargement.
// Pas de Content-Length : mod_deflate compresse la sortie, la taille finale
// diffère de la taille sur disque.
// CORS ouvert : indispensable pour que des apps Leaflet/MapLibre tierces
// puissent charger l'export en cross-origin.
header('Access-Control-Allow-Origin: *');
// Cache 2h : évite de re-télécharger (et donc de re-tracer) la même machine à
// chaque chargement. 2h est un bon compromis car les exports sont quotidiens.
header('Cache-Control: public, max-age=7200');
header('Content-Type: application/geo+json');
header('Content-Disposition: attachment; filename="' . $file . '"');
readfile($path);
