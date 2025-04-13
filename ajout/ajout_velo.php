<!--Pour la version admin : ajouter le champ velo_openrunner, mettre "1" dans le champ velo_public, et afficher les noms formatés dans le formulaire pour les vérifier.
Il faudra aussi changer ajout_velo_db pour ajouter le champ openrunner, et nettoyer l'envoi mail automatique-->

<?php
// Connexion à la base de données
include "../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Récupération des gares
$result_gares = $mysqli->query("SELECT gare_id, gare_nom, gare_nomformate FROM gares ORDER BY gare_nom");
$gares = [];
while ($row = $result_gares->fetch_assoc()) {
  $gares[$row['gare_id']] = [
    'id' => $row['gare_id'],
    'nom' => $row['gare_nom'],
    'nomformate' => $row['gare_nomformate']
  ];
}

// Récupération des falaises
$result_falaises = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_nomformate FROM falaises ORDER BY falaise_nom");
$falaises = [];
while ($row = $result_falaises->fetch_assoc()) {
  $falaises[$row['falaise_id']] = [
    'id' => $row['falaise_id'],
    'nom' => $row['falaise_nom'],
    'nomformate' => $row['falaise_nomformate']
  ];
}

// Read the admin search parameter
$admin = ($_GET['admin'] ?? false) == $config["admin_token"];

?>

<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter un itinéraire vélo - Vélogrimpe.fr</title>
  <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />

  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
  <style>
    .admin {
      <?= !$admin ? 'display: none !important;' : '' ?>
    }

    :not(span).admin {
      <?= $admin ? 'border-left: solid 1px darkred; padding-left: 4px;' : '' ?>
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if ($admin): ?>
        document.getElementById('velo_public').value = '1';
        document.getElementById('admin').value = $config["admin_token"];
        document.getElementById('nom_prenom').value = "Florent";
        document.getElementById('email').value = $config["contact_mail"];
      <?php else: ?>
        document.getElementById('velo_public').value = '2';
        document.getElementById('admin').value = '0';
      <?php endif; ?>
      document.querySelectorAll(".input-disabled").forEach(e => { console.log(e.name); e.value = "" });
    });
  </script>
  <script>
    function formatVariante() {
      const variante = document.getElementById('velo_variante').value.trim();

      const varianteFormate = variante
        .toLowerCase() // Convertir en minuscules
        .normalize("NFD") // Supprimer les accents
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9\s-]/g, "") // Supprimer les caractères non alphanumériques sauf espaces et tirets
        .replace(/\s+/g, "-") // Remplacer les espaces par des tirets
        .replace(/-+/g, "-") // Remplacer les tirets multiples par un seul
        .replace(/^-|-$/g, "") // Supprimer les tirets en début/fin
        .substring(0, 255); // Limiter à 255 caractères

      document.getElementById('velo_varianteformate').value = varianteFormate;
    }
  </script>
  <style>
    validated:invalid {
      border-color: var(--color-error);
    }

    validated:valid {
      border-color: var(--color-success);
    }
  </style>
</head>

<body class="min-h-screen">
  <div
    class="max-w-screen-md mx-auto prose p-4 prose-a:text-[oklch(var(--p)/1)] prose-a:font-bold prose-a:no-underline hover:prose-a:underline hover:prose-a:text-[oklch(var(--pf)/1)]">
    <h1 class="text-4xl font-bold text-wrap text-center">
      Ajouter un itinéraire vélo<span class="admin text-red-900"> (version admin)</span>
    </h1>

    <div class="rounded-lg bg-base-300 p-4 my-6 border border-base-300 shadow-sm text-base-content">
      <b>Vous vous apprêtez à décrire un itinéraire Gare &rarr; Falaise, en vélo ou à pied.</b><br>
      <i>Les champs obligatoires sont en noir, les optionnels en gris.</i>
    </div>
    <form method="POST" action="ajout_velo_db.php" enctype="multipart/form-data" class="flex flex-col gap-4">
      <datalist id="gares">
        <?php foreach ($gares as $gare_id => $gare): ?>
          <option value="<?= $gare['nom']; ?>"></option>
        <?php endforeach; ?>
      </datalist>
      <datalist id="falaises">
        <?php foreach ($falaises as $falaise_id => $falaise): ?>
          <option value="<?= $falaise['nom']; ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <input class="input input-primary input-sm" type="hidden" id="velo_public" name="velo_public" value="2">
      <input class="input input-primary input-sm" type="hidden" id="admin" name="admin" value="0">

      <div class="flex flex-row gap-4 items-center flex-1">
        <div class="flex flex-col gap-1 w-1/2">
          <div class="relative not-prose">
            <label class="form-control" for="gare_nom">
              <b>Gare de départ de l'itinéraire vélo :</b>
              <div class="input input-primary input-sm flex items-center gap-2">
                <input class="grow" type="text" id="gare_nom" name="gare_nom" required autocomplete="off" />
                <svg class="w-4 h-4 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-search-line"></use>
                </svg>
              </div>
            </label>
            <ul id="gares-search-list"
              class="autocomplete-list absolute w-full bg-white border border-primary mt-1 hidden">
            </ul>
          </div>
          <div class="admin flex flex-row gap-4">
            <input tabindex="-1" class="input input-disabled input-xs w-1/2" type="text" id="velo_depart"
              name="velo_depart" readonly required>
            <input tabindex="-1" class="input input-disabled input-xs w-1/2" type="text" id="gare_id" name="gare_id"
              readonly required>
          </div>
        </div>

        <div class="flex flex-col gap-1 w-1/2">
          <div class="relative not-prose">
            <label class="form-control" for="falaise_nom">
              <b>Falaise d'arrivée de l'itinéraire vélo :</b>
              <div class="input input-primary input-sm flex items-center gap-2">
                <input class="grow" type="text" id="falaise_nom" name="falaise_nom" required autocomplete="off" />
                <svg class="w-4 h-4 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-search-line"></use>
                </svg>
              </div>
            </label>
            <ul id="falaises-search-list"
              class="autocomplete-list absolute w-full bg-white border border-primary mt-1 hidden">
            </ul>
          </div>
          <div class="admin flex flex-row gap-4">
            <input tabindex="-1" class="input input-disabled input-xs w-1/2" type="text" id="velo_arrivee"
              name="velo_arrivee" readonly required>
            <input tabindex="-1" class="input input-disabled input-xs w-1/2" type="text" id="falaise_id"
              name="falaise_id" readonly required>
          </div>
        </div>
      </div>

      <div id="itineraireExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
        <svg class="w-4 h-4 mb-1 fill-current inline-block">
          <use xlink:href="/symbols/icons.svg#ri-error-warning-fill"></use>
        </svg> Un itinéraire existe déjà entre cette gare et cette falaise. Vérifiez que vous ne faites pas de doublon.
      </div>

      <div>
        <div class="flex flex-row gap-4">
          <label class="form-control w-1/3" for="velo_km">
            <b>Longueur de l'itinéraire (km) :</b>
            <input class="input input-primary input-sm" type="number" id="velo_km" name="velo_km" placeholder="12.5"
              step="0.01" min="0" required>
          </label>
          <label class="form-control w-1/3" for="velo_dplus">
            <b>Dénivelé positif (mètres) :</b>
            <input class="input input-primary input-sm" type="number" id="velo_dplus" name="velo_dplus"
              placeholder="650" min="0" required>
          </label>
          <label class="form-control w-1/3" for="velo_dmoins">
            <b>Dénivelé négatif (mètres) :</b>
            <input class="input input-primary input-sm" type="number" id="velo_dmoins" name="velo_dmoins"
              placeholder="650" min="0" required>
          </label>
        </div>
        <i>Le nombre de km peut être un nombre décimal (<span class="text-red-600">avec un point et pas une virgule
            !</span>),
          le dénivelé un entier.</i>
      </div>

      <label class="form-control" for="velo_descr">
        <b class="text-gray-400 opacity-70">Description de l'itinéraire, remarques :</b>
        <textarea class="textarea textarea-bordered textarea-sm" id="velo_descr" name="velo_descr" rows="5"
          cols="100"></textarea>
        <i class="text-gray-400 opacity-70">
          On peut y détailler la surface (goudron ? Piste ?), le trafic (beaucoup de voitures ?), s'il y a des
          montées raides, si le parcours suit une voie verte, s'il y a des alternatives au tracé proposé...
        </i>
      </label>

      <label class="form-control admin" for="velo_openrunner">
        <b class="text-gray-400 opactity-70">Lien Openrunner pour affichage profil en iframe :</b>
        <textarea type="text" class="textarea textarea-bordered textarea-sm" id="velo_openrunner" rows="3"
          name="velo_openrunner"></textarea>
      </label>

      <label class="form-control" for="gpx_file">
        <b>Trace GPS :</b>
        <input class="file-input file-input-primary file-input-sm" type="file" id="gpx_file" name="gpx_file"
          accept=".gpx" required>
        <i class="text-red-400">Au format GPX !</i>
      </label>

      <label class="form-control" for="velo_variante">
        <b class="text-gray-400 opacity-70">Nom de la variante :</b>
        <input class="input input-bordered input-sm" type="text" id="velo_variante" name="velo_variante"
          oninput="formatVariante()">
        <i class="text-gray-400 opacity-70">
          Dans le cas où il existe plusieurs itinéraires reliant une même gare à une même falaise, donner un nom aux
          différentes possibilités.
          Ex : "Option par le Nord" et "Option par le Sud".</i>
      </label>

      <label class="form-control" for="velo_varianteformate" style="display: none;">
        <b class="text-gray-400 opacity-70">Nom de la variante (formatée) :</b>
        <input class="input input-bordered input-sm" type="text" id="velo_varianteformate" name="velo_varianteformate"
          readonly style="display: none;">
      </label>

      <div class="flex flex-row gap-4">
        <label class="form-control w-1/2" for="velo_apieduniquement">
          <div class="flex items-center gap-4">
            <input class="checkbox checkbox-primary checkbox" type="checkbox" id="velo_apieduniquement"
              name="velo_apieduniquement">
            <b class="text-gray-400 opacity-70">Itinéraire conçu pour la marche uniquement ?</b>
          </div>
          <i class="text-gray-400 opacity-70">
            Cocher si l'itinéraire peut se faire à pied, mais pas à vélo.
          </i>
        </label>

        <label class="form-control w-1/2" for="velo_apiedpossible">
          <div class="flex items-center gap-4">
            <input class="checkbox checkbox-primary checkbox" type="checkbox" id="velo_apiedpossible"
              name="velo_apiedpossible">
            <b class="text-gray-400 opacity-70">Itinéraire conçu pour le vélo, mais faisable à pied ?</b>
          </div>
          <i class="text-gray-400 opacity-70">
            Cocher si l'itinéraire peut se faire à vélo, mais est suffisamment court pour se faire
            aussi à pied (< 1h). </i>
        </label>
      </div>

      <hr class="my-4">
      <h3 class="text-center">VALIDATION DE L'AJOUT DE DONNÉES</h3>

      <div class="flex flex-row gap-4">
        <div class="form-control w-1/2">
          <b>Falaise ajoutée par : </b>
          <label for="nom_prenom" class="input input-primary input-sm flex items-center gap-2 w-full">
            <input class="grow" type="text" id="nom_prenom" name="nom_prenom"
              placeholder="Prénom (et/ou nom, surnom...)" required>
            <svg class="w-4 h-4 fill-current">
              <use xlink:href="/symbols/icons.svg#ri-user-line"></use>
            </svg>
          </label>
        </div>
        <div class="form-control w-1/2" for="email">
          <b>Mail :</b>
          <label for="email" class="input input-primary input-sm flex items-center gap-2 w-full">
            <input class="grow" type="email" id="email" name="email" required>
            <svg class="w-4 h-4 fill-current">
              <use xlink:href="/symbols/icons.svg#ri-mail-line"></use>
            </svg>
          </label>
        </div>
      </div>

      <label class="form-control" for="message">
        <span class="text-gray-400 opacity-70">
          <b>Message optionnel :</b>
          <i>(si vous voulez commenter votre ajout de données)</i>
        </span>
        <textarea class="textarea textarea-bordered textarea-sm" id="message" name="message" rows="4"></textarea>
      </label>

      <button type="submit" class="btn btn-primary">AJOUTER L'ITINÉRAIRE VÉLO</button>

    </form>
  </div>
</body>
<script>
  const gares = <?php echo json_encode($gares); ?>;
  const falaises = <?php echo json_encode($falaises); ?>;

  const verifierExistenceItineraire = () => {
    const gareId = document.getElementById('gare_id').value;
    const falaiseId = document.getElementById('falaise_id').value;
    if (!gareId || !falaiseId) {
      document.getElementById("itineraireExistsAlert").classList.add("hidden");
      return;
    }
    fetch(`/ajout/check_velo.php?gare_id=${gareId}&falaise_id=${falaiseId}`)
      .then(response => response.json())
      .then(exists => {
        if (exists) {
          document.getElementById("itineraireExistsAlert").classList.remove("hidden");
        } else {
          document.getElementById("itineraireExistsAlert").classList.add("hidden");
        }
      });
  };

  const gareCallback = (gareNom) => {
    const gareId = gareNom ? Object.values(gares).find(g => g.nom === gareNom)?.id : '';
    const gareNomFormate = gareNom ? Object.values(gares).find(g => g.nom === gareNom)?.nomformate : '';
    document.getElementById('gare_id').value = gareId;
    document.getElementById('velo_depart').value = gareNomFormate;
    verifierExistenceItineraire();
  };
  const falaiseCallback = (falaiseNom) => {
    const falaiseId = falaiseNom ? Object.values(falaises).find(f => f.nom === falaiseNom)?.id : '';
    const falaiseNomFormate = falaiseNom ? Object.values(falaises).find(f => f.nom === falaiseNom)?.nomformate : '';
    document.getElementById('velo_arrivee').value = falaiseNomFormate;
    document.getElementById('falaise_id').value = falaiseId;
    verifierExistenceItineraire();
  };
</script>
<script src="/js/autocomplete.js"></script>
<script>
  setupAutocomplete("gare_nom", "gares-search-list", "gares", gareCallback);
  setupAutocomplete("falaise_nom", "falaises-search-list", "falaises", falaiseCallback);
</script>

</html>