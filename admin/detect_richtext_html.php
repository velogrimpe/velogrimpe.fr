<?php

/**
 * Détection (lecture seule) du HTML déjà présent dans les champs falaises
 * destinés à l'éditeur riche. Sert à auditer l'existant AVANT/APRÈS migration :
 *   - quelles balises ont été écrites à la main par les contributeurs,
 *   - quelles lignes mélangent HTML + retours à la ligne (cas à surveiller :
 *     leurs \n seraient perdus à l'affichage une fois traitées comme HTML).
 *
 * N'écrit rien. Usage : /admin/detect_richtext_html.php?admin=TOKEN
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['admin'] ?? '') !== $config['admin_token']) {
    http_response_code(403);
    die("Accès refusé (token admin requis).\n");
}

$fields = [
    'falaise_fermee',
    'falaise_topo',
    'falaise_matxt',
    'falaise_gvtxt',
    'falaise_rq',
    'falaise_hebergement',
    'falaise_acces_bus',
    'falaise_txt1',
    'falaise_txt2',
    'falaise_txt3',
    'falaise_txt4',
    'falaise_leg1',
    'falaise_leg2',
    'falaise_leg3',
];

$cols = implode(', ', $fields);
$res = $mysqli->query("SELECT falaise_id, $cols FROM falaises");
if (!$res) {
    die("Erreur SELECT : " . $mysqli->error . "\n");
}

$tagTotals = [];   // tag => nb d'occurrences
$tagPerField = [];  // field => [tag => count]
$rows = [];        // détail par ligne/champ
$mixedCount = 0;   // champs avec HTML + \n

while ($row = $res->fetch_assoc()) {
    foreach ($fields as $f) {
        $val = (string) $row[$f];
        if ($val === '') {
            continue;
        }
        if (!preg_match_all('#</?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>#', $val, $m)) {
            continue;
        }

        $tags = array_map('strtolower', $m[1]);
        $uniqueTags = array_count_values($tags);
        foreach ($uniqueTags as $tag => $n) {
            $tagTotals[$tag] = ($tagTotals[$tag] ?? 0) + $n;
            $tagPerField[$f][$tag] = ($tagPerField[$f][$tag] ?? 0) + $n;
        }

        $hasNewline = strpos($val, "\n") !== false;
        if ($hasNewline) {
            $mixedCount++;
        }

        $rows[] = [
            'id' => $row['falaise_id'],
            'field' => $f,
            'tags' => array_keys($uniqueTags),
            'mixed' => $hasNewline,
            'excerpt' => mb_substr(str_replace("\n", '\n', $val), 0, 160),
        ];
    }
}

echo "=== AUDIT HTML existant (lecture seule) ===\n\n";

echo "Balises trouvées (toutes lignes confondues) :\n";
if (!$tagTotals) {
    echo "  (aucune)\n";
} else {
    arsort($tagTotals);
    foreach ($tagTotals as $tag => $n) {
        echo "  <$tag> : $n\n";
    }
}

echo "\nRépartition par champ :\n";
if (!$tagPerField) {
    echo "  (aucune)\n";
} else {
    foreach ($tagPerField as $f => $tags) {
        arsort($tags);
        $parts = [];
        foreach ($tags as $t => $n) {
            $parts[] = "<$t>:$n";
        }
        echo "  $f : " . implode(', ', $parts) . "\n";
    }
}

echo "\n⚠️  Lignes mélangeant HTML + retour à la ligne (\\n) : $mixedCount\n";
echo "    (leurs \\n seraient perdus si traitées comme HTML à l'affichage)\n";

echo "\n=== Détail par ligne/champ (" . count($rows) . ") ===\n";
foreach ($rows as $r) {
    $flag = $r['mixed'] ? ' [MIXTE \\n]' : '';
    echo "falaise #{$r['id']} · {$r['field']} · " . implode(',', $r['tags']) . "$flag\n";
    echo "    {$r['excerpt']}\n";
}

if (!$rows) {
    echo "Aucun champ ne contient de balise HTML. Migration sans risque.\n";
}
