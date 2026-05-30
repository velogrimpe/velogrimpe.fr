<?php
/**
 * Helper de génération de flux RSS 2.0.
 *
 * Usage :
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/rss.php';
 *   render_rss(
 *     ['title' => '...', 'description' => '...', 'link' => '/page', 'self' => '/feed/x.xml'],
 *     [ ['title' => '...', 'link' => '/...', 'guid' => '...', 'description' => '...', 'date' => $ts], ... ]
 *   );
 *
 * Réutilise VG_BASE / vg_url() de lib/schema.php pour fabriquer les URLs absolues.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/schema.php';

/**
 * Émet un flux RSS 2.0 complet (headers + XML).
 *
 * @param array $meta  ['title','description','link','self'] :
 *                      - link : URL HTML de la page source (relative ou absolue)
 *                      - self : URL du flux lui-même (relative ou absolue)
 * @param array $items Liste ordonnée (la plus récente en premier) de :
 *                      [ 'title' => string, 'link' => string, 'guid' => string,
 *                        'description' => string, 'date' => int|string ]
 *                      - link : relatif ou absolu (préfixé par VG_BASE si relatif)
 *                      - guid : identifiant stable, non-permalink
 *                      - description : texte/HTML, encapsulé en CDATA
 *                      - date : timestamp Unix ou date parsable par strtotime()
 */
function render_rss(array $meta, array $items): void
{
    header('Content-Type: application/rss+xml; charset=UTF-8');

    $esc = fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $toTs = function ($d): int {
        if (is_int($d)) {
            return $d;
        }
        $ts = strtotime((string) $d);
        return $ts !== false ? $ts : 0;
    };
    // CDATA-safe : on découpe une éventuelle séquence de fermeture "]]>".
    $cdata = fn(string $s): string => '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $s) . ']]>';

    $selfUrl = vg_url($meta['self'] ?? '');
    $lastBuild = !empty($items) ? $toTs($items[0]['date']) : 0;

    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . PHP_EOL;
    echo '<channel>' . PHP_EOL;
    echo '  <title>' . $esc($meta['title'] ?? '') . '</title>' . PHP_EOL;
    echo '  <link>' . $esc(vg_url($meta['link'] ?? '/')) . '</link>' . PHP_EOL;
    echo '  <description>' . $esc($meta['description'] ?? '') . '</description>' . PHP_EOL;
    echo '  <language>fr-FR</language>' . PHP_EOL;
    echo '  <atom:link href="' . $esc($selfUrl) . '" rel="self" type="application/rss+xml" />' . PHP_EOL;
    if ($lastBuild) {
        echo '  <lastBuildDate>' . date('r', $lastBuild) . '</lastBuildDate>' . PHP_EOL;
    }

    foreach ($items as $it) {
        $link = vg_url($it['link']);
        echo '  <item>' . PHP_EOL;
        echo '    <title>' . $esc($it['title']) . '</title>' . PHP_EOL;
        echo '    <link>' . $esc($link) . '</link>' . PHP_EOL;
        echo '    <guid isPermaLink="false">' . $esc($it['guid'] ?? $link) . '</guid>' . PHP_EOL;
        $ts = $toTs($it['date'] ?? 0);
        if ($ts) {
            echo '    <pubDate>' . date('r', $ts) . '</pubDate>' . PHP_EOL;
        }
        echo '    <description>' . $cdata($it['description'] ?? '') . '</description>' . PHP_EOL;
        echo '  </item>' . PHP_EOL;
    }

    echo '</channel>' . PHP_EOL;
    echo '</rss>';
}
