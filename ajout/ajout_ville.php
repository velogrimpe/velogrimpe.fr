<?php
include "../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$result_villes = $mysqli->query("SELECT ville_nom FROM villes ORDER BY ville_nom");
$villes = [];
while ($row = $result_villes->fetch_assoc()) {
  $villes[] = $row['ville_nom'];
}

$admin = ($_GET['admin'] ?? false) == $config["admin_token"];
if (!$admin) {
  die('Accès refusé');
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter une ville (admin)</title>
  <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />

  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if ($admin): ?>
        document.getElementById('admin').value = "<?= $config["admin_token"] ?>";
      <?php else: ?>
        document.getElementById('admin').value = '0';
      <?php endif; ?>
    });
  </script>
</head>

<body class="min-h-screen">
  <div class="max-w-screen-md mx-auto prose p-4 prose-a:text-[oklch(var(--p)/1)]
    prose-a:font-bold prose-a:no-underline hover:prose-a:underline
    hover:prose-a:text-[oklch(var(--pf)/1)] prose-pre:my-0
    prose-pre:text-center">
    <h1 class="text-4xl font-bold text-wrap text-center">
      AJOUTER UNE VILLE (ADMIN)
    </h1>
    <form method="post" action="ajout_ville_db.php" class="flex flex-col gap-4">
      <input type="hidden" id="admin" name="admin" value="0" />
      <label class="form-control" for="ville_nom">
        <b>Ville :</b>
        <input type="text" class="input input-primary input-sm" id="ville_nom" name="ville_nom"
          oninput="verifierExistenceVille()" required />
      </label>

      <div id="villeExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
        <svg class="w-4 h-4 mb-1 fill-current inline-block">
          <use xlink:href="/symbols/icons.svg#ri-error-warning-fill"></use>
        </svg>
        Une ville avec ce nom existe déjà dans la base de données. Vérifiez
        que vous ne faites pas de doublon.
      </div>

      <button class="btn btn-primary" type="submit">AJOUTER LA VILLE</button>
    </form>
  </div>
</body>
<script>
  const villes = <?= json_encode($villes) ?>.map(n => n.toLowerCase().normalize("NFD"));;
  const verifierExistenceVille = () => {
    const villeNom = document.getElementById("ville_nom").value;
    if (!villeNom) {
      document.getElementById("villeExistsAlert").classList.add("hidden");
      return;
    }
    const exists = villes.includes(villeNom.toLowerCase().normalize("NFD").trim());
    if (exists) {
      document
        .getElementById("villeExistsAlert")
        .classList.remove("hidden");
    } else {
      document.getElementById("villeExistsAlert").classList.add("hidden");
    }
  };
</script>

</html>