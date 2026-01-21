<?php
/**
 * Éditeur de détails falaise - Page du workflow d'ajout
 *
 * Utilise le composant réutilisable falaise-details-editor.php avec navigation
 */

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["contrib_token"];

$falaise_id = isset($_GET['falaise_id']) ? (int) $_GET['falaise_id'] : null;
$admin = isset($_GET['admin']) ? (int) $_GET['admin'] : 0;
$nom_prenom = $_GET['nom_prenom'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($falaise_id)) {
  header("Location: /ajout/ajout_falaise.php");
  exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/components/falaise-details-editor.php';

// Données de la falaise courante
$stmtF = $mysqli->prepare("SELECT
  f.falaise_id,
  f.falaise_nom,
  f.falaise_nomformate,
  f.falaise_latlng
  FROM falaises f
  WHERE f.falaise_id = ?");
if (!$stmtF) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$stmtF->bind_param("i", $falaise_id);
$stmtF->execute();
$falaise = $stmtF->get_result()->fetch_assoc();
$stmtF->close();

if (!$falaise) {
  header("Location: /ajout/ajout_falaise.php");
  exit;
}

// URLs de navigation
$backUrl = "/ajout/confirmation_falaise.php?" . http_build_query([
  'falaise_id' => $falaise_id,
  'type' => 'insert',
  'step' => 1,
  'admin' => $admin
]);

$nextUrl = "/ajout/confirmation_falaise.php?" . http_build_query([
  'falaise_id' => $falaise_id,
  'type' => 'insert',
  'step' => 2,
  'admin' => $admin
]);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Éditer les détails - <?= htmlspecialchars($falaise['falaise_nom']) ?> - Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Éditeur de détails de falaise - secteurs, parkings, approches">
  <!-- Map libraries bundle (Leaflet, Fullscreen, Geoman, GPX, Turf) -->
  <script src="/dist/map.js"></script>
  <link rel="stylesheet" href="/dist/map.css" />
  <script src="/js/vendor/leaflet-textpath.js"></script>
  <?php vite_css('main'); ?>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="/index.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>

  <main class="grow py-4 px-2 md:px-8 flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">
        Éditer les détails : <?= htmlspecialchars($falaise['falaise_nom']) ?>
      </h1>
    </div>

    <div class="alert alert-info">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
      <div>
        <p class="font-semibold">Ajoutez des détails à votre falaise (optionnel)</p>
        <p class="text-sm">Utilisez les outils de la carte pour ajouter : secteurs, parkings, approches, arrêts de bus, accès vélo...</p>
      </div>
    </div>

    <?php
    render_falaise_details_editor($falaise, $token, [
      'height' => 'calc(100vh - 320px)',
      'showToolbar' => true,
      'showNavigation' => true,
      'backUrl' => $backUrl,
      'nextUrl' => $nextUrl,
      'contribNom' => $nom_prenom,
      'contribEmail' => $email,
    ]);
    ?>
  </main>

  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

</html>
