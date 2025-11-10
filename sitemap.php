<?php
header('Content-Type: application/xml');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

// URLs des pages statiques
echo '  <url><loc>https://velogrimpe.fr/</loc></url>' . PHP_EOL;
echo '  <url><loc>https://velogrimpe.fr/carte.php</loc></url>' . PHP_EOL;
echo '  <url><loc>https://velogrimpe.fr/logistique.php</loc></url>' . PHP_EOL;
echo '  <url><loc>https://velogrimpe.fr/infos.php</loc></url>' . PHP_EOL;
echo '  <url><loc>https://velogrimpe.fr/contribuer.php</loc></url>' . PHP_EOL;
echo '  <url><loc>https://velogrimpe.fr/communaute.php</loc></url>' . PHP_EOL;

// URLs des pages tableau
$queryV = "SELECT DISTINCT v.ville_id FROM villes v
    INNER JOIN train t ON t.ville_id = v.ville_id";
$resultV = $mysqli->query($queryV);
if ($resultV) {
    while ($rowV = $resultV->fetch_assoc()) {
        $villeId = $rowV['ville_id'];
        $url = "https://velogrimpe.fr/tableau.php?ville_id=$villeId";
        echo "  <url><loc>$url</loc></url>" . PHP_EOL;
    }
}

// URLs des pages falaises sans villes sélectionnées
$queryF = "SELECT DISTINCT f.falaise_id FROM falaises f
    INNER JOIN velo v ON v.falaise_id = f.falaise_id
    WHERE falaise_public >= 0";
$resultF = $mysqli->query($queryF);
if ($resultF) {
    while ($rowF = $resultF->fetch_assoc()) {
        $falaiseId = $rowF['falaise_id'];
        $url = "https://velogrimpe.fr/falaise.php?falaise_id=$falaiseId";
        echo "  <url><loc>$url</loc></url>" . PHP_EOL;
    }
}

// URLs des pages falaises avec villes sélectionnées
$query = "SELECT DISTINCT
    f.falaise_id,
    villes.ville_id
FROM falaises f
LEFT JOIN velo v ON v.falaise_id = f.falaise_id
LEFT JOIN gares g ON g.gare_id = v.gare_id AND g.deleted = 0
LEFT JOIN train t ON t.gare_id = g.gare_id
LEFT JOIN villes ON villes.ville_id = t.ville_id
WHERE
    v.velo_id IS NOT NULL
    AND t.train_id IS NOT NULL
    AND f.falaise_public >= 0
    AND v.velo_public >= 0
    AND t.train_public >= 0
ORDER BY f.falaise_id
";

$result = $mysqli->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $falaiseId = $row['falaise_id'];
        $villeId = $row['ville_id'];
        $url = "https://velogrimpe.fr/falaise.php?falaise_id=$falaiseId&amp;ville_id=$villeId";
        echo "  <url><loc>$url</loc></url>" . PHP_EOL;
    }
}

echo '  <url><loc>https://velogrimpe.fr/actualites</loc></url>' . PHP_EOL;

$article_files = glob($_SERVER['DOCUMENT_ROOT'] . '/articles/20*.php');
foreach ($article_files as $file) {
    $url = 'https://velogrimpe.fr/articles/' . basename($file);
    echo "  <url><loc>$url</loc></url>" . PHP_EOL;
}
$newsletters_files = glob($_SERVER['DOCUMENT_ROOT'] . '/actualites/20*.php');
foreach ($newsletters_files as $file) {
    $url = 'https://velogrimpe.fr/actualites/' . basename($file);
    echo "  <url><loc>$url</loc></url>" . PHP_EOL;
}
echo '</urlset>';
