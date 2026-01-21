<?php
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';

// Paramètres
$falaise_id = isset($_GET['falaise_id']) ? (int) $_GET['falaise_id'] : null;
$type = $_GET['type'] ?? 'insert'; // insert ou update
$step = isset($_GET['step']) ? (int) $_GET['step'] : 1; // 1 = après formulaire, 2 = après éditeur
$admin = isset($_GET['admin']) ? (int) $_GET['admin'] : 0;

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

// Messages selon le contexte
$messages = [
  'insert' => [
    1 => 'Falaise ajoutée avec succès !',
    2 => 'Détails enregistrés avec succès !'
  ],
  'update' => [
    1 => 'Falaise modifiée avec succès !',
    2 => 'Détails enregistrés avec succès !'
  ]
];

$message = $messages[$type][$step] ?? 'Opération réussie !';
$falaise_nom = $falaise ? htmlspecialchars($falaise['falaise_nom']) : 'Falaise';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Confirmation d'ajout de falaise - Vélogrimpe.fr">
  <title>Confirmation - <?= $falaise_nom ?> - Vélogrimpe.fr</title>
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
        <h2 class="card-title text-2xl"><?= $message ?></h2>
        <p class="text-base-content/70 mb-4"><?= $falaise_nom ?></p>

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

          <?php if ($step === 1): ?>
            <!-- Éditer les détails (uniquement après le formulaire initial) -->
            <a href="/ajout/edit_details_falaise.php?falaise_id=<?= $falaise_id ?>&admin=<?= $admin ?>" class="btn btn-secondary">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
              </svg>
              Éditer les détails (secteurs, parking...)
            </a>
          <?php endif; ?>
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
