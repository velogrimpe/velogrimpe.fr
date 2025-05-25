<?php
require_once "../../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

$falaises = $mysqli->query("SELECT
DISTINCT
  f.falaise_id,
  f.falaise_nom,
  g.gare_nom,
  g.gare_id,
  GROUP_CONCAT(DISTINCT v.ville_id SEPARATOR ',') AS ville_ids,
  GROUP_CONCAT(DISTINCT evg.ville_id SEPARATOR ',') AS excluded_gare_ville_ids,
  GROUP_CONCAT(DISTINCT evf.ville_id SEPARATOR ',') AS excluded_falaise_ville_ids
  FROM falaises f
  LEFT JOIN velo on velo.falaise_id = f.falaise_id
  LEFT JOIN gares g ON g.gare_id = velo.gare_id
  LEFT JOIN train t ON t.gare_id = g.gare_id
  LEFT JOIN villes v ON v.ville_id = t.ville_id
  LEFT JOIN exclusions_villes_gares evg ON evg.gare_id = g.gare_id
  LEFT JOIN exclusions_villes_falaises evf on evf.falaise_id = f.falaise_id
  WHERE velo.velo_id IS NOT NULL
  GROUP BY f.falaise_id, g.gare_id
  ORDER BY f.falaise_nom, g.gare_nom;
")->fetch_all(MYSQLI_ASSOC);
$villes = $mysqli->query("SELECT * FROM villes ORDER BY ville_nom")->fetch_all(MYSQLI_ASSOC);

// Group falaises by falaise_nom
$falaises = array_reduce($falaises, function ($carry, $item) {
  $carry[$item['falaise_nom']][] = $item;
  return $carry;
}, []);

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Tableau accès trains - Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="/index.css" />
  <link rel="manifest" href="/site.webmanifest" />

</head>

<body class="h-full">
  <?php include "../../components/header.html"; ?>
  <main class="py-4 px-2 md:px-8">
    <div class="overflow-auto max-h-[90vh] bg-base-100 rounded-md">
      <table class="table table-pin-rows table-pin-cols table-zebra min-w-max">
        <!-- head -->
        <thead>
          <tr class="bg-base-200 text-center">
            <th class="w-48 text-lg">Falaises</th>
            <td class="w-48 text-lg">Gares</td>
            <?php foreach ($villes as $ville): ?>
              <td class="w-48 text-lg"><?= $ville['ville_nom'] ?></td>
            <?php endforeach; ?>
            <th class="w-0 p-0"></th>
          </tr>
        </thead>
        <tbody>
          <!-- row 1 -->
          <?php foreach ($falaises as $falaise_nom => $gares): ?>
            <tr class="text-center relative">
              <th class="w-48 z-10"><?= $falaise_nom ?></th>
              <td class="w-48"><?= join("<br />", array_map(fn($gare) => $gare["gare_nom"], $gares)) ?></td>
              <?php foreach ($villes as $ville): ?>
                <td class="w-48">
                  <?php if (in_array($ville['ville_id'], explode(',', $gares[0]['excluded_falaise_ville_ids']))): ?>
                    <span><svg class="inline w-5 h-5 fill-current">
                        <use xlink:href="/symbols/icons.svg#ri-close-line"></use>
                      </svg></span>
                  <?php else: ?>
                    <?php foreach ($gares as $i => $gare): ?>
                      <?php if ($i > 0): ?>
                        <br />
                      <?php endif; ?>
                      <?php if (in_array($ville['ville_id'], explode(',', $gare['ville_ids']))): ?>
                        <span><?= $gare["gare_nom"] ?></span>
                      <?php else: ?>
                        <?php if (in_array($ville['ville_id'], explode(',', $gare['excluded_gare_ville_ids']))): ?>
                          <span>-</span>
                        <?php else: ?>
                          <div class="dropdown dropdown-end">
                            <span class="text-error cursor-pointer" tabindex="1"><?= $gare["gare_nom"] ?></span>
                            <div class="dropdown-content gap-1 menu bg-base-200 rounded-box z-[1] m-1 w-64 p-2 shadow-lg">
                              <a class="btn btn-primary btn-sm py-1 h-fit"
                                href="/ajout_train.php?gare_id=<?= $gare["gare_id"] ?>&ville_id=<?= $ville["ville_id"] ?>&admin=<?= $token ?>">
                                Créer itinéraire train
                              </a>
                              <button class="btn btn-error btn-sm text-base-100 py-1 h-fit"
                                onclick="excludeTrain(<?= $ville['ville_id'] ?>, <?= $gare['gare_id'] ?>, this)">
                                Supprimer itinéraire train
                              </button>
                              <button class="btn btn-error btn-sm text-base-100 py-1 h-fit"
                                onclick="excludeFalaise(<?= $ville['ville_id'] ?>, <?= $gare['falaise_id'] ?>, this)">
                                Couple ville / falaise sans intérêt
                              </button>
                            </div>
                          </div>
                        <?php endif; ?>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <th class="w-0 p-0"></th>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
  <?php include "../../components/footer.html"; ?>
</body>
<script>
  function excludeTrain(villeId, gareId, thisElement) {
    fetch("/api/private/exclude_train.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": "Bearer <?= $token ?>"
      },
      body: JSON.stringify({ ville_id: villeId, gare_id: gareId })
    })
      .then(response => response.json())
      .then(data => {
        if (data === true) {
          // remove the dropdown element
          const span = document.createElement('span');
          span.textContent = '-';
          thisElement.closest('.dropdown').replaceWith(span);
        } else {
          alert("Erreur lors de la suppression de l'itinéraire train.");
        }
      })
      .catch(error => console.error("Erreur:", error));
  }
  function excludeFalaise(villeId, falaiseId, thisElement) {
    fetch("/api/private/exclude_falaise.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": "Bearer <?= $token ?>"
      },
      body: JSON.stringify({ ville_id: villeId, falaise_id: falaiseId })
    })
      .then(response => response.json())
      .then(data => {
        if (data === true) {
          thisElement.closest('td').innerHTML = '<svg class="inline w-5 h-5 fill-current"><use xlink:href="/symbols/icons.svg#ri-close-line"></use></svg>';
        } else {
          alert("Erreur lors de l'exclusion de la falaise.");
        }
      })
      .catch(error => console.error("Erreur:", error));
  }
</script>

</html>