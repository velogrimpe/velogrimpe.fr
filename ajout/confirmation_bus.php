<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

$arret_id = isset($_GET['arret_id']) ? (int) $_GET['arret_id'] : null;
$type = $_GET['type'] ?? 'insert'; // insert ou update
$admin = isset($_GET['admin']) ? (int) $_GET['admin'] : 0;

$arret = null;
if ($arret_id) {
  $stmt = $mysqli->prepare("SELECT nom FROM bus_arrets WHERE id = ?");
  $stmt->bind_param("i", $arret_id);
  $stmt->execute();
  $arret = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

$message = $type === 'update' ? 'Arrêt de bus modifié avec succès !' : 'Arrêt de bus ajouté avec succès !';
$arret_nom = $arret ? htmlspecialchars($arret['nom']) : 'Arrêt de bus';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="description" content="Confirmation d'ajout d'arrêt de bus - Vélogrimpe.fr">
  <title>Confirmation - <?= $arret_nom ?> - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <div class="grow flex justify-center items-center p-4">
    <div class="card bg-base-100 shadow-xl max-w-md w-full">
      <div class="card-body items-center text-center">
        <div class="text-success mb-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 fill-none stroke-current" viewBox="0 0 24 24"
            stroke-width="1.5">
            <use href="#checkbox-circle-fill"></use>
          </svg>
        </div>
        <h2 class="card-title text-2xl"><?= $message ?></h2>
        <p class="text-base-content/70"><?= $arret_nom ?></p>

        <!-- Boutons principaux -->
        <div class="flex flex-col gap-2 w-full mt-2">
          <a href="/ajout/ajout_bus.php?arret_id=<?= $arret_id ?><?= $admin ? '&admin=' . $config["admin_token"] : '' ?>"
            class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24"
              stroke-width="2">
              <use href="#pencil"></use>
            </svg> Modifier cet arrêt
          </a>
          <a href="/ajout/ajout_bus.php<?= $admin ? '?admin=' . $config["admin_token"] : '' ?>" class="btn btn-accent">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 stroke-none fill-current" viewBox="0 0 24 24">
              <use href="#bus-stop"></use>
            </svg> Créer un nouvel arrêt de bus
          </a>
        </div>

        <div class="divider my-2">ou</div>
        <div class="flex flex-wrap justify-center gap-2">
          <a href="/" class="btn btn-outline btn-sm">Accueil</a>
          <a href="/carte.php" class="btn btn-outline btn-sm">Voir la carte</a>
          <?php if ($admin): ?>
            <a href="/admin/" class="btn btn-outline btn-sm">Admin</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>

</html>