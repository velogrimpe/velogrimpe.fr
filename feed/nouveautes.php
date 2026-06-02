<?php
/**
 * Flux RSS « Nouveautés » : dernières falaises et itinéraires vélo ajoutés.
 * Les itinéraires n'ayant pas d'URL propre, leur lien pointe vers la page falaise associée.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/rss.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$items = [];

// 1) Falaises récemment ajoutées.
$resF = $mysqli->query(
    "SELECT falaise_id, falaise_nom, falaise_zonename, falaise_deptname,
            falaise_cotmin, falaise_cotmax, falaise_nbvoies, date_creation
     FROM falaises
     WHERE falaise_public >= 1 AND date_creation IS NOT NULL
     ORDER BY date_creation DESC
     LIMIT 20"
);
if ($resF) {
    while ($r = $resF->fetch_assoc()) {
        $parts = [];
        $loc = trim((string) ($r['falaise_zonename'] ?? ''));
        if (!empty($r['falaise_deptname'])) {
            $loc = trim($loc . ' (' . $r['falaise_deptname'] . ')');
        }
        if ($loc !== '') {
            $parts[] = $loc;
        }
        $cotmin = trim((string) $r['falaise_cotmin']);
        $cotmax = trim((string) $r['falaise_cotmax']);
        $cot = $cotmin . (($cotmin !== '' && $cotmax !== '') ? '–' : '') . $cotmax;
        if ($cot !== '') {
            $parts[] = 'Cotations ' . $cot;
        }
        if (!empty($r['falaise_nbvoies'])) {
            $parts[] = $r['falaise_nbvoies'] . ' voies';
        }
        $items[] = [
            'title' => 'Nouvelle falaise : ' . $r['falaise_nom'],
            'link' => '/falaise.php?falaise_id=' . $r['falaise_id'],
            'guid' => 'falaise-' . $r['falaise_id'],
            'description' => implode(' · ', $parts),
            'date' => strtotime($r['date_creation']),
        ];
    }
}

// 2) Itinéraires vélo récemment ajoutés (lien vers la page falaise d'arrivée).
$resV = $mysqli->query(
    "SELECT v.velo_id, v.falaise_id, v.velo_km, v.velo_dplus, v.date_creation,
            g.gare_nom, f.falaise_nom
     FROM velo v
     LEFT JOIN gares g ON v.gare_id = g.gare_id AND g.deleted = 0
     LEFT JOIN falaises f ON v.falaise_id = f.falaise_id
     WHERE v.velo_public = 1 AND v.date_creation IS NOT NULL
     ORDER BY v.date_creation DESC
     LIMIT 20"
);
if ($resV) {
    while ($r = $resV->fetch_assoc()) {
        $depart = $r['gare_nom'] ?: 'gare';
        $arrivee = $r['falaise_nom'] ?: 'falaise';
        $parts = [];
        if ($r['velo_km'] !== null) {
            $parts[] = round((float) $r['velo_km'], 1) . ' km';
        }
        if (!empty($r['velo_dplus'])) {
            $parts[] = $r['velo_dplus'] . ' m D+';
        }
        $items[] = [
            'title' => 'Nouvel itinéraire vélo : ' . $depart . ' → ' . $arrivee,
            'link' => '/falaise.php?falaise_id=' . $r['falaise_id'],
            'guid' => 'velo-' . $r['velo_id'],
            'description' => implode(' · ', $parts),
            'date' => strtotime($r['date_creation']),
        ];
    }
}

// Tri par date décroissante, limité aux 20 items les plus récents.
usort($items, fn($a, $b) => $b['date'] <=> $a['date']);
$items = array_slice($items, 0, 20);

render_rss([
    'title' => 'Vélogrimpe.fr — Nouveautés (falaises & itinéraires)',
    'description' => 'Dernières falaises et itinéraires vélo ajoutés sur Vélogrimpe.fr.',
    'link' => '/carte.php',
    'self' => '/feed/nouveautes.xml',
], $items);
