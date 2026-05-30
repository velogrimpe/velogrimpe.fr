<?php
/**
 * Flux RSS « Actualités » : articles (fichiers), newsletters (BDD) et pages /p/... (BDD).
 * Reprend l'ensemble agrégé par /actualites/index.php + les pages CMS.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/fetch_mail_template.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/rss.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$items = [];

// 1) Articles (fichiers /articles/20*.php) + anciennes newsletters-fichiers (legacy /actualites/20*.php).
//    Méta (titre/description) extraites du HTML rendu via fetchMailTemplate(), comme dans actualites/index.php.
$article_files = glob($_SERVER['DOCUMENT_ROOT'] . '/articles/20*.php') ?: [];
$newsletter_files = glob($_SERVER['DOCUMENT_ROOT'] . '/actualites/20*.php') ?: [];
foreach (array_merge($article_files, $newsletter_files) as $file) {
    $filename = basename($file);
    $type = strpos($file, '/actualites/') !== false ? 'actualites' : 'articles';
    // Articles : date complète dans le nom (YYYY-MM-DD). Legacy newsletters : YYYY-MM → 1er du mois.
    $date_str = $type === 'actualites' ? substr($filename, 0, 7) . '-01' : substr($filename, 0, 10);
    $date = DateTime::createFromFormat('Y-m-d', $date_str);
    if (!$date) {
        continue;
    }
    $template = fetchMailTemplate('/' . $type . '/' . $filename);
    $items[] = [
        'title' => $template['title'],
        'link' => '/' . $type . '/' . $filename,
        'guid' => 'article-' . $filename,
        'description' => $template['description'],
        'date' => $date->getTimestamp(),
    ];
}

// 2) Newsletters (table newsletters) — mode courant.
$resN = $mysqli->query(
    "SELECT slug, title, description, date_creation
     FROM newsletters
     WHERE status IN ('published', 'sent')
     ORDER BY date_creation DESC"
);
if ($resN) {
    while ($row = $resN->fetch_assoc()) {
        $items[] = [
            'title' => $row['title'],
            'link' => '/actualites/' . rawurlencode($row['slug']),
            'guid' => 'news-' . $row['slug'],
            'description' => $row['description'] ?: '',
            'date' => $row['date_creation'] ? strtotime($row['date_creation']) : 0,
        ];
    }
}

// 3) Pages CMS (table pages) — slugs multi-segments encodés segment par segment (cf. sitemap.php).
$resP = $mysqli->query(
    "SELECT slug, title, description, date_modification
     FROM pages
     WHERE status = 'published'
     ORDER BY date_modification DESC"
);
if ($resP) {
    while ($row = $resP->fetch_assoc()) {
        $segments = array_map('rawurlencode', explode('/', $row['slug']));
        $items[] = [
            'title' => $row['title'],
            'link' => '/p/' . implode('/', $segments),
            'guid' => 'page-' . $row['slug'],
            'description' => $row['description'] ?: '',
            'date' => $row['date_modification'] ? strtotime($row['date_modification']) : 0,
        ];
    }
}

// Tri par date décroissante, limité aux 50 items les plus récents.
usort($items, fn($a, $b) => $b['date'] <=> $a['date']);
$items = array_slice($items, 0, 50);

render_rss([
    'title' => 'Vélogrimpe.fr — Actualités',
    'description' => 'Articles, newsletters et nouvelles pages de Vélogrimpe.fr — escalade en mobilité douce à vélo et en train.',
    'link' => '/actualites/',
    'self' => '/feed/actualites.xml',
], $items);
