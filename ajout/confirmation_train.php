<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Paramètres
$ville_id = isset($_GET['ville_id']) ? (int) $_GET['ville_id'] : null;
$gare_id = isset($_GET['gare_id']) ? (int) $_GET['gare_id'] : null;
$admin = isset($_GET['admin']) && $_GET['admin'] == $config["admin_token"];

// Récupérer les infos de la ville
$ville = null;
if ($ville_id) {
  $stmt = $mysqli->prepare("SELECT ville_nom FROM villes WHERE ville_id = ?");
  $stmt->bind_param("i", $ville_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $ville = $result->fetch_assoc();
  $stmt->close();
}

// Récupérer les infos de la gare
$gare = null;
if ($gare_id) {
  $stmt = $mysqli->prepare("SELECT gare_nom FROM gares WHERE gare_id = ?");
  $stmt->bind_param("i", $gare_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $gare = $result->fetch_assoc();
  $stmt->close();
}

$ville_nom = $ville ? htmlspecialchars($ville['ville_nom']) : 'Ville';
$gare_nom = $gare ? htmlspecialchars($gare['gare_nom']) : 'Gare';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Confirmation d'ajout d'itinéraire train - Vélogrimpe.fr">
  <title>Itinéraire train ajouté - <?= $ville_nom ?> → <?= $gare_nom ?> - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <div class="grow flex justify-center items-center p-4">
    <div class="card bg-base-100 shadow-xl max-w-md w-full">
      <div class="card-body items-center text-center">
        <!-- Icône succès -->
        <div class="text-success mb-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 fill-none stroke-current" viewBox="0 0 24 24"
            stroke-width="1.5">
            <use href="#checkbox-circle-fill"></use>
          </svg>
        </div>
        <!-- Message -->
        <h2 class="card-title text-2xl">Itinéraire train ajouté !</h2>
        <p class="text-base-content/70 mb-4">
          <?= $ville_nom ?> → <?= $gare_nom ?>
        </p>
        <!-- Boutons principaux -->
        <div class="flex flex-col gap-2 w-full">
          <!-- Nouvel itinéraire depuis la même ville -->
          <a href="/ajout/ajout_train.php?ville_id=<?= $ville_id ?><?= $admin ? '&admin=' . $config["admin_token"] : '' ?>"
            class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24"
              stroke-width="2">
              <use href="#plus"></use>
            </svg> Autre gare depuis <?= $ville_nom ?>
          </a>
          <!-- Nouvel itinéraire vers la même gare -->
          <a href="/ajout/ajout_train.php?gare_id=<?= $gare_id ?><?= $admin ? '&admin=' . $config["admin_token"] : '' ?>"
            class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24"
              stroke-width="2">
              <use href="#plus"></use>
            </svg> Autre ville vers <?= $gare_nom ?>
          </a>
        </div>
        <!-- Séparateur -->
        <div class="divider my-2">ou</div>
        <!-- Boutons secondaires -->
        <div class="flex flex-wrap justify-center gap-2">
          <a href="/" class="btn btn-outline btn-sm"> Accueil </a>
          <a href="/ajout/ajout_falaise.php<?= $admin ? '?admin=' . $config["admin_token"] : '' ?>"
            class="btn btn-outline btn-sm"> + Falaise </a>
          <a href="/ajout/ajout_velo.php<?= $admin ? '?admin=' . $config["admin_token"] : '' ?>"
            class="btn btn-outline btn-sm"> + Vélo </a>
          <?php if ($admin): ?>
            <a href="/ajout/ajout_train.php?admin=<?= $config["admin_token"] ?>" class="btn btn-outline btn-sm"> + Train
            </a>
            <a href="/admin/" class="btn btn-outline btn-sm"> Admin </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>