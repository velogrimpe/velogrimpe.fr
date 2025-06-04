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
  GROUP_CONCAT(DISTINCT evf.ville_id SEPARATOR ',') AS excluded_falaise_ville_ids,
  GROUP_CONCAT(DISTINCT evgf.ville_id SEPARATOR ',') AS excluded_falaise_gare_ville_ids
  FROM falaises f
  LEFT JOIN velo on velo.falaise_id = f.falaise_id
  LEFT JOIN gares g ON g.gare_id = velo.gare_id
  LEFT JOIN train t ON t.gare_id = g.gare_id
  LEFT JOIN villes v ON v.ville_id = t.ville_id
  LEFT JOIN exclusions_villes_gares evg ON evg.gare_id = g.gare_id
  LEFT JOIN exclusions_villes_falaises evf on evf.falaise_id = f.falaise_id
  LEFT JOIN exclusions_villes_gares_falaises evgf on evgf.falaise_id = f.falaise_id and evgf.gare_id = g.gare_id
  WHERE velo.velo_id IS NOT NULL
  GROUP BY f.falaise_id, g.gare_id
  ORDER BY f.falaise_id DESC, g.gare_nom ASC;
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
      <table class="table table-pin-rows table-pin-cols table-zebra min-w-max" data-sort-col="id"
        data-sort-order="desc">
        <!-- head -->
        <thead>
          <tr class="bg-base-200 text-center">
            <th class="w-48 bg-base-200 text-lg">Falaises
              <button class="btn btn-ghost btn-sm px-0" title="Changer l'ordre de tri" onclick="toggleSortOrder()">
                <svg class="inline w-4 h-4 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-sort-desc"></use>
                </svg>
              </button>
            </th>
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
            <tr class="text-center relative" data-id="<?= $gares[0]['falaise_id'] ?>" data-nom="<?= $falaise_nom ?>">
              <th class="w-48 z-10000"><?= $falaise_nom ?><br>(<?= $gares[0]['falaise_id'] ?>)</th>
              <td class="w-48"><?= join("<br />", array_map(fn($gare) => $gare["gare_nom"], $gares)) ?></td>
              <?php foreach ($villes as $ville): ?>
                <td class="w-48">
                  <div class="flex flex-row items-stretch justify-start gap-2">
                    <?php if (in_array($ville['ville_id'], explode(',', $gares[0]['excluded_falaise_ville_ids']))): ?>
                      <div class="flex justify-center w-full">
                        <span><svg class="inline w-5 h-5 fill-current">
                            <use xlink:href="/symbols/icons.svg#ri-close-line"></use>
                          </svg></span>
                      </div>
                    <?php else: ?>
                      <div>
                        <button class="btn btn-ghost text-error btn-sm h-full px-0" title="Exclure ce couple Falaise - Ville"
                          onclick="excludeVilleFalaise(<?= $ville['ville_id'] ?>, <?= $gare['falaise_id'] ?>, this)">
                          <!-- onclick="excludeTriplet(<?= $ville['ville_id'] ?>, <?= $gare['gare_id'] ?>, <?= $gare['falaise_id'] ?>, this)"> -->
                          <span><svg class="inline w-3 h-3 fill-current">
                              <use xlink:href="/symbols/icons.svg#ri-close-line"></use>
                            </svg></span>
                        </button>
                      </div>
                      <div class="flex flex-col items-start gap-2 justify-center">
                        <?php foreach ($gares as $gare): ?>
                          <div
                            class="w-48 flex items-center gap-1 gareElem gare<?= $gare['gare_id'] ?>-ville<?= $ville['ville_id'] ?>">
                            <?php if (
                              in_array($ville['ville_id'], explode(',', $gare['excluded_falaise_gare_ville_ids']))
                              or in_array($ville['ville_id'], explode(',', $gare['excluded_gare_ville_ids']))
                            ): ?>
                              -
                            <?php else: ?>
                              <?php if (in_array($ville['ville_id'], explode(',', $gare['ville_ids']))): ?>
                                <span class="text-nowrap overflow-hidden text-ellipsis shrink-1 grow text-left">
                                  <?= $gare["gare_nom"] ?>
                                </span>
                              <?php else: ?>
                                <span class="text-nowrap overflow-hidden text-ellipsis shrink-1 grow text-left text-error">
                                  <?= $gare["gare_nom"] ?>
                                </span>
                                <button
                                  class="badge badge-error badge-outline text-base-100 badge-xs h-5 w-5 rounded-full text-sm shrink-0"
                                  title="Exclure ce triplet Gare - Ville - Falaise"
                                  onclick="excludeTriplet(<?= $ville['ville_id'] ?>, <?= $gare['gare_id'] ?>, <?= $gare['falaise_id'] ?>, this)">
                                  -
                                </button>
                              <?php endif; ?>
                              <?php if (in_array($ville['ville_id'], explode(',', $gare['ville_ids']))): ?>
                              <?php else: ?>
                                <button
                                  class="badge badge-error badge-outline text-base-100 badge-xs h-5 w-5 rounded-full text-sm shrink-0"
                                  title="Toujours exclure ce couple Gare - Ville"
                                  onclick="excludeVilleGare(<?= $ville['ville_id'] ?>, <?= $gare['gare_id'] ?>, this)">
                                  --
                                </button>
                              <?php endif; ?>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
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
  function excludeVilleGare(villeId, gareId, thisElement) {
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
          document.querySelectorAll(`.gare${gareId}-ville${villeId}`).forEach(el => el.innerHTML = "-");
        } else {
          alert("Erreur lors de la suppression de l'itinéraire train.");
        }
      })
      .catch(error => console.error("Erreur:", error));
  }
  function excludeVilleFalaise(villeId, falaiseId, thisElement) {
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
  function excludeTriplet(villeId, gareId, falaiseId, thisElement) {
    fetch("/api/private/exclude_vgf.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": "Bearer <?= $token ?>"
      },
      body: JSON.stringify({ ville_id: villeId, falaise_id: falaiseId, gare_id: gareId })
    })
      .then(response => response.json())
      .then(data => {
        if (data === true) {
          thisElement.closest('.gareElem').innerHTML = '-';
        } else {
          alert("Erreur lors de l'exclusion de la falaise.");
        }
      })
      .catch(error => console.error("Erreur:", error));
  }

  function toggleSortOrder() {
    const table = document.querySelector('table');
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    let isAsc = (table.dataset.sortOrder || 'desc') === 'asc';
    let isId = (table.dataset.sortCol || 'id') === 'id';
    console.log("isAsc", isAsc, "isId", isId);
    // cylcle trhough the sort orders : Id desc => id asc -> name asc -> name desc
    if (isId && !isAsc) {
      table.dataset.sortOrder = 'asc';
    } else if (isId && isAsc) {
      table.dataset.sortCol = 'name';
      table.dataset.sortOrder = 'asc';
    } else if (table.dataset.sortCol === 'name' && isAsc) {
      table.dataset.sortOrder = 'desc';
    } else {
      table.dataset.sortCol = 'id';
      table.dataset.sortOrder = 'desc';
    }
    isAsc = (table.dataset.sortOrder || 'desc') === 'asc';
    isId = (table.dataset.sortCol || 'id') === 'id';

    rows.sort((a, b) => {
      if (isId) {
        const aId = parseInt(a.dataset.id, 10);
        const bId = parseInt(b.dataset.id, 10);
        return isAsc ? aId - bId : bId - aId;
      }
      const aName = a.dataset.nom.toLowerCase();
      const bName = b.dataset.nom.toLowerCase();
      return isAsc ? aName.localeCompare(bName) : bName.localeCompare(aName);
    });

    rows.forEach(row => table.querySelector('tbody').appendChild(row));

  }
</script>

</html>