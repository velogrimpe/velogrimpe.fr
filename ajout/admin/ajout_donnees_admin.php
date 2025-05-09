<?php
require_once "../../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

$falaises = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_public
                                  FROM falaises
                                  ORDER BY falaise_public + mod(falaise_public, 3) * 3 DESC, falaise_nom ASC
                                  ")->fetch_all(MYSQLI_ASSOC);

$falaises_contrib = array_values(array_filter(
  $falaises,
  fn($falaise) => $falaise['falaise_public'] === "2"
));
$falaises_ht = array_values(array_filter(
  $falaises,
  fn($falaise) => $falaise['falaise_public'] === "3"
));
$falaises_topo = array_values(array_filter(
  $falaises,
  fn($falaise) => $falaise['falaise_public'] === "1"
));

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter des données (admin)</title>
  <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png" />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <link rel="stylesheet" href="/global.css" />
  <link rel="manifest" href="/site.webmanifest" />
</head>

<body class="flex flex-col min-h-screen">
  <?php include "../../components/header.html"; ?>
  <main class="w-full flex-grow max-w-screen-md mx-auto p-10 flex flex-col gap-8">
    <h1 class="text-4xl font-bold text-wrap text-center">
      <span class="text-red-900">PANNEAU D'ADMINISTRATION</span>
    </h1>
    <h2 class="text-4xl font-bold text-wrap text-center">
      Ajouter des données
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_ville.php?admin=<?= $token ?>">Ajouter une ville de
        départ</a>
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_zone.php?admin=<?= $token ?>">Ajouter une zone</a>
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_falaise.php?admin=<?= $token ?>">Ajouter une falaise</a>
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_train.php?admin=<?= $token ?>">Ajouter un itinéraire
        train (ville - gare)</a>
      <a class="btn btn-primary btn-lg" href="/ajout/ajout_velo.php?admin=<?= $token ?>">Ajouter un itinéraire vélo
        (gare - falaise)</a>
      <a class="btn btn-primary btn-lg" href="/ajout/admin/oblyk.php?admin=<?= $token ?>">Créer les liens Oblyk</a>
    </div>

    <h2 class="text-4xl font-bold text-wrap text-center">
      Modifier des données
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <label class="flex flex-col gap-1 items-center bg-base-100 p-2 rounded-lg bg-primary" for="selectFalaise1">
        <b class="text-base-100 text-lg">⚠️ Falaises à vérifier</b>
        <?php if (count($falaises_contrib) === 0): ?>
          <p class="text-base-100 text-lg">Aucune falaise à vérifier 💪</p>
        <?php else: ?>
          <select id="selectFalaise1" name="selectFalaise1" class="select select-primary select-sm"
            onchange="window.location.href = '/ajout/ajout_falaise.php?admin=<?= $token ?>&falaise_id=' + this.value">
            <option value="">Sélectionner une falaise</option>
            <?php foreach ($falaises_contrib as $falaise): ?>
              <option value="<?= $falaise['falaise_id'] ?>"><?= $falaise['falaise_nom'] ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </label>

      <label class="flex flex-col gap-1 items-center bg-base-100 p-2 rounded-lg bg-primary" for="selectFalaise3">
        <b class="text-base-100 text-lg">✅ Falaises du Topo</b>
        <select id="selectFalaise3" name="selectFalaise3" class="select select-primary select-sm"
          onchange="window.location.href = '/ajout/ajout_falaise.php?admin=<?= $token ?>&falaise_id=' + this.value">
          <option value="">Sélectionner une falaise</option>
          <?php foreach ($falaises_topo as $falaise): ?>
            <option value="<?= $falaise['falaise_id'] ?>"><?= $falaise['falaise_nom'] ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="flex flex-col gap-1 items-center bg-base-100 p-2 rounded-lg bg-primary" for="selectFalaise2">
        <b class="text-base-100 text-lg">❌ Falaises Hors Topo</b>
        <select id="selectFalaise2" name="selectFalaise2" class="select select-primary select-sm"
          onchange="window.location.href = '/ajout/ajout_falaise.php?admin=<?= $token ?>&falaise_id=' + this.value">
          <option value="">Sélectionner une falaise</option>
          <?php foreach ($falaises_ht as $falaise): ?>
            <option value="<?= $falaise['falaise_id'] ?>"><?= $falaise['falaise_nom'] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </main>
  <?php include "../../components/footer.html"; ?>
</body>

</html>