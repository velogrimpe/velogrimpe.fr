<?php

/**
 * Migration unique : convertit les champs texte des falaises (affichés sans
 * htmlspecialchars) en HTML fidèle pour l'éditeur TipTap (rt_migrate_value) :
 *   - texte brut          => échappé + \n => <br>, dans un <p> ;
 *   - HTML déjà présent    => <br> redonnés (comme l'ancien nl2br) + assaini.
 *
 * Idempotent : relancer ne re-modifie pas une valeur déjà migrée.
 *
 * Usage :
 *   /admin/migrate_richtext.php?admin=TOKEN            (dry-run : aperçu)
 *   /admin/migrate_richtext.php?admin=TOKEN&apply=1    (écriture en base)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/richtext.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['admin'] ?? '') !== $config['admin_token']) {
    http_response_code(403);
    die("Accès refusé (token admin requis).\n");
}

$apply = ($_GET['apply'] ?? '') === '1';

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

// UPDATE préparé : 14 colonnes + id
$setClause = implode(', ', array_map(fn($f) => "$f = ?", $fields));
$stmt = $mysqli->prepare("UPDATE falaises SET $setClause WHERE falaise_id = ?");

echo $apply ? "=== MODE ÉCRITURE (apply=1) ===\n\n" : "=== DRY-RUN (aucune écriture) ===\n\n";

$rowsChanged = 0;
$fieldsChanged = 0;
$samplesLeft = 8;

while ($row = $res->fetch_assoc()) {
    $newValues = [];
    $changedHere = [];

    foreach ($fields as $f) {
        $old = $row[$f];
        $new = rt_migrate_value($old);
        $newValues[$f] = $new;
        if ((string) $old !== (string) $new && trim((string) $old) !== '') {
            $changedHere[] = $f;
            $fieldsChanged++;
        }
    }

    if (!$changedHere) {
        continue;
    }
    $rowsChanged++;

    if ($samplesLeft > 0) {
        $samplesLeft--;
        $f0 = $changedHere[0];
        echo "falaise #{$row['falaise_id']} — champs : " . implode(', ', $changedHere) . "\n";
        echo "    ex. $f0 :\n";
        echo "      avant : " . str_replace("\n", "\\n", (string) $row[$f0]) . "\n";
        echo "      après : " . $newValues[$f0] . "\n\n";
    }

    if ($apply) {
        $vals = array_map(fn($f) => $newValues[$f], $fields);
        $vals[] = $row['falaise_id'];
        $types = str_repeat('s', count($fields)) . 'i';
        $stmt->bind_param($types, ...$vals);
        if (!$stmt->execute()) {
            echo "  !! échec UPDATE #{$row['falaise_id']} : " . $stmt->error . "\n";
        }
    }
}

echo "----------------------------------------\n";
echo "Lignes à modifier  : $rowsChanged\n";
echo "Champs à convertir : $fieldsChanged\n";
echo $apply
    ? "Migration appliquée.\n"
    : "Dry-run terminé. Relancer avec &apply=1 pour écrire.\n";
