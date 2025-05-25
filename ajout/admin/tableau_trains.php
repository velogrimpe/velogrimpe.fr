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
  GROUP_CONCAT(v.ville_id SEPARATOR ',') AS ville_ids
  FROM falaises f
  LEFT JOIN velo on velo.falaise_id = f.falaise_id
  LEFT JOIN gares g ON g.gare_id = velo.gare_id
  LEFT JOIN train t ON t.gare_id = g.gare_id
  LEFT JOIN villes v ON v.ville_id = t.ville_id
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
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
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
      <table class="table table-pin-rows table-zebra min-w-max">
        <!-- head -->
        <thead>
          <tr class="bg-base-300 ">
            <th class="w-48 text-lg">Falaises</th>
            <th class="w-48 text-lg">Gares</th>
            <?php foreach ($villes as $ville): ?>
              <th class="w-48 text-lg"><?= $ville['ville_nom'] ?></th>
            <?php endforeach; ?>
            <th> </th>
          </tr>
        </thead>
        <tbody>
          <!-- row 1 -->
          <?php foreach ($falaises as $falaise_nom => $gares): ?>
            <tr>
              <th class="w-48"><?= $falaise_nom ?></th>
              <td class="w-48"><?= join(" / ", array_map(fn($gare) => $gare["gare_nom"], $gares)) ?></td>
              <?php foreach ($villes as $ville): ?>
                <td class="w-48">
                  <?php foreach ($gares as $i => $gare): ?>
                    <?php if ($i > 0): ?>
                      /
                    <?php endif; ?>
                    <?php if (in_array($ville['ville_id'], explode(',', $gare['ville_ids']))): ?>
                      <span class="text-success"><?= $gare["gare_nom"] ?></span>
                    <?php else: ?>
                      <span class="text-error"><?= $gare["gare_nom"] ?></span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </td>
              <?php endforeach; ?>
              <td></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
  <?php include "../../components/footer.html"; ?>
</body>

</html>