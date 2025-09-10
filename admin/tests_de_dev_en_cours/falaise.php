<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$res_z = $mysqli->query("SELECT * FROM zones ORDER BY zone_nom");
$zones = [];
while ($zone = $res_z->fetch_assoc()) {
  $zones[] = $zone;
}
// Read the admin search parameter
$admin = ($_GET['admin'] ?? false) == $config["admin_token"];
$falaise_id = $_GET['falaise_id'] ?? null;

if ($falaise_id) {
  $falaises = [];
  if (!$admin) {
    $is_locked_stmt = $mysqli->prepare("SELECT falaise_id FROM falaises WHERE falaise_id = ? AND falaise_public = 1");
    $is_locked_stmt->bind_param("i", $falaise_id);
    $is_locked_stmt->execute();
    $is_locked = $is_locked_stmt->get_result()->num_rows > 0;
    if ($is_locked) {
      http_response_code(403);
      echo "<h1>Cette falaise est verrouillée</h1>";
      exit;
    }
  }
} else {
  $result_falaises = $mysqli->query("SELECT
  falaise_id, falaise_nom, falaise_latlng, falaise_public = 1 as in_topo,
  falaise_nomformate
  FROM falaises f
  ORDER BY falaise_nom");
  $falaises = [];
  while ($row = $result_falaises->fetch_assoc()) {
    $falaises[] = [
      'nom' => $row['falaise_nom'],
      'id' => $row['falaise_id'],
      'latlng' => $row['falaise_latlng'],
      'in_topo' => $row['in_topo'],
      'nomformate' => $row['falaise_nomformate'],
    ];
  }
}

?>

<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <title><?= $falaise_id ? "Modifier" : "Ajouter" ?> une falaise - Vélogrimpe.fr</title>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/common-head.html"; ?>

</head>

<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main id="app">
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

<script type="module">
  import { render } from 'preact';
  import html from '/js/components/app/render.mjs';

  const falaises = <?= json_encode($falaises) ?>;

  render(html`
  <h1>Falaises</h1>
  ${falaises.map(falaise => html`<div>
      <h2>${falaise.nom}</h2>
      <p>ID: ${falaise.id}</p>
      <p>Coordonnées: ${falaise.latlng}</p>
      <p>Dans le topo: ${falaise.in_topo ? "Oui" : "Non"}</p>
      <p>Nom formaté: ${falaise.nomformate}</p>
    </div >
    `)}
  `, document.getElementById('app'));
</script>

</html>