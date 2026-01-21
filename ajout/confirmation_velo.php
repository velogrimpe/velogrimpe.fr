<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Paramètres
$falaise_id = isset($_GET['falaise_id']) ? (int) $_GET['falaise_id'] : null;
$gare_id = isset($_GET['gare_id']) ? (int) $_GET['gare_id'] : null;
$admin = isset($_GET['admin']) && $_GET['admin'] == $config["admin_token"];

// Récupérer les infos de la falaise
$falaise = null;
if ($falaise_id) {
  $stmt = $mysqli->prepare("SELECT falaise_nom FROM falaises WHERE falaise_id = ?");
  $stmt->bind_param("i", $falaise_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $falaise = $result->fetch_assoc();
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

$falaise_nom = $falaise ? htmlspecialchars($falaise['falaise_nom']) : 'Falaise';
$gare_nom = $gare ? htmlspecialchars($gare['gare_nom']) : 'Gare';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Confirmation d'ajout d'itinéraire vélo - Vélogrimpe.fr">
  <title>Itinéraire ajouté - <?= $gare_nom ?> → <?= $falaise_nom ?> - Vélogrimpe.fr</title>
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
          <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>

        <!-- Message -->
        <h2 class="card-title text-2xl">Itinéraire ajouté avec succès !</h2>
        <p class="text-base-content/70 mb-4">
          <?= $gare_nom ?> → <?= $falaise_nom ?>
        </p>

        <!-- Boutons principaux -->
        <div class="flex flex-col gap-2 w-full">
          <!-- Voir la falaise -->
          <a href="/falaise.php?falaise_id=<?= $falaise_id ?>" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Voir la falaise
          </a>

          <!-- Ajouter un autre itinéraire pour la même falaise -->
          <a href="/ajout/ajout_velo.php?falaise_id=<?= $falaise_id ?><?= $admin ? '&admin=' . $config["admin_token"] : '' ?>" class="btn btn-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Autre itinéraire pour <?= $falaise_nom ?>
          </a>
        </div>

        <!-- Séparateur -->
        <div class="divider my-2">ou</div>

        <!-- Boutons secondaires -->
        <div class="flex flex-wrap justify-center gap-2">
          <a href="/" class="btn btn-outline btn-sm">
            Accueil
          </a>
          <a href="/ajout/ajout_falaise.php<?= $admin ? '?admin=' . $config["admin_token"] : '' ?>" class="btn btn-outline btn-sm">
            + Falaise
          </a>
          <a href="/ajout/ajout_velo.php<?= $admin ? '?admin=' . $config["admin_token"] : '' ?>" class="btn btn-outline btn-sm">
            + Vélo
          </a>
          <?php if ($admin): ?>
            <a href="/ajout/ajout_train.php?admin=<?= $config["admin_token"] ?>" class="btn btn-outline btn-sm">
              + Train
            </a>
            <a href="/admin/" class="btn btn-outline btn-sm">
              Admin
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>

</html>
