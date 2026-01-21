<!--Pour la version admin : ajouter le champ velo_openrunner, mettre "1" dans le champ velo_public, et afficher les noms formatés dans le formulaire pour les vérifier.
Il faudra aussi changer ajout_velo_db pour ajouter le champ openrunner, et nettoyer l'envoi mail automatique--> <?php
// Connexion à la base de données
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Récupération des gares
$result_gares = $mysqli->query("SELECT gare_id, gare_nom, gare_nomformate FROM gares WHERE deleted = 0 ORDER BY gare_nom");
$gares = [];
while ($row = $result_gares->fetch_assoc()) {
  $gares[$row['gare_id']] = [
    'id' => (int) $row['gare_id'],
    'nom' => $row['gare_nom'],
    'nomformate' => $row['gare_nomformate']
  ];
}

// Récupération des falaises
$result_falaises = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_nomformate FROM falaises ORDER BY falaise_nom");
$falaises = [];
while ($row = $result_falaises->fetch_assoc()) {
  $falaises[$row['falaise_id']] = [
    'id' => (int) $row['falaise_id'],
    'nom' => $row['falaise_nom'],
    'nomformate' => $row['falaise_nomformate']
  ];
}

$falaise_id = isset($_GET['falaise_id']) ? $_GET['falaise_id'] : null;
$falaisePreset = $falaises[$falaise_id] ?? null;

$gare_id = isset($_GET['gare_id']) ? $_GET['gare_id'] : null;
$garePreset = $gares[$gare_id] ?? null;

// Read the admin search parameter
$admin = ($_GET['admin'] ?? false) == $config["admin_token"];

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter un itinéraire vélo - Vélogrimpe.fr</title>
  <?php vite_css('main'); ?>
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
        document.getElementById('admin').value = "<?= $config["admin_token"] ?>";
        document.getElementById('nom_prenom').value = "Florent";
        document.getElementById('email').value = "<?= $config['contact_mail'] ?>";
      <?php else: ?>
        document.getElementById('velo_public').value = '2';
        document.getElementById('admin').value = '0';
      <?php endif; ?>
      document.querySelectorAll(".input-disabled").forEach(e => { e.value = "" });
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

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main
    class="w-full grow max-w-(--breakpoint-md) mx-auto prose p-4">
    <h1 class="text-4xl font-bold text-wrap text-center"> Ajouter un itinéraire vélo<span class="admin text-red-900">
        (version admin)</span>
    </h1>
    <div class="rounded-lg bg-base-300 p-4 my-6 border border-base-300 shadow-xs text-base-content">
      <b>Vous vous apprêtez à décrire un itinéraire Gare &rarr; Falaise, en vélo ou à pied.</b><br>
      <i>Les champs obligatoires sont en noir, les optionnels en gris.</i>
    </div>
    <form method="POST" action="/api/add_velo.php" enctype="multipart/form-data" class="flex flex-col gap-4">
      <input class="input input-primary input-sm" type="hidden" id="velo_public" name="velo_public" value="2">
      <input class="input input-primary input-sm" type="hidden" id="admin" name="admin" value="0">
      <!-- Partie Départ / Arrivées -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Gare et Falaise</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div id="vue-ajout-velo"
          data-gares='<?= htmlspecialchars(json_encode(array_values($gares)), ENT_QUOTES, 'UTF-8') ?>'
          data-falaises='<?= htmlspecialchars(json_encode(array_values($falaises)), ENT_QUOTES, 'UTF-8') ?>'
          <?php if ($falaisePreset): ?>data-preset-falaise-id="<?= $falaisePreset['id'] ?>"<?php endif; ?>
          <?php if ($garePreset): ?>data-preset-gare-id="<?= $garePreset['id'] ?>"<?php endif; ?>>
        </div>
        <!-- Hidden fields for form submission -->
        <div class="admin flex flex-row gap-4">
          <input tabindex="-1" class="input input-disabled input-xs w-1/4" type="text" id="velo_depart"
            name="velo_depart" readonly required>
          <input tabindex="-1" class="input input-disabled input-xs w-1/4" type="text" id="gare_id" name="gare_id"
            readonly required>
          <input tabindex="-1" class="input input-disabled input-xs w-1/4" type="text" id="velo_arrivee"
            name="velo_arrivee" required readonly />
          <input tabindex="-1" class="input input-disabled input-xs w-1/4" type="text" id="falaise_id"
            name="falaise_id" required readonly />
        </div>
        <div id="itineraireExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
            <use href="#error-warning-fill"></use>
          </svg> Un itinéraire existe déjà entre cette gare et cette falaise. Vérifiez que vous ne faites pas de
          doublon.
        </div>
      </div>
      <!-- Partie Indicateurs -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Indicateurs</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <label class="form-control w-full md:w-1/3" for="velo_km">
            <b>Longueur de l'itinéraire (km)</b>
            <input class="input input-primary input-sm" type="number" id="velo_km" name="velo_km" placeholder="12.5"
              step="0.01" min="0" required>
          </label>
          <label class="form-control w-full md:w-1/3" for="velo_dplus">
            <b>Dénivelé positif (mètres)</b>
            <input class="input input-primary input-sm" type="number" id="velo_dplus" name="velo_dplus"
              placeholder="650" min="0" required>
          </label>
          <label class="form-control w-full md:w-1/3" for="velo_dmoins">
            <b>Dénivelé négatif (mètres)</b>
            <input class="input input-primary input-sm" type="number" id="velo_dmoins" name="velo_dmoins"
              placeholder="650" min="0" required>
          </label>
        </div>
        <i>Le nombre de km peut être un nombre décimal (<span class="text-red-600">avec un point et pas une virgule
            !</span>), le dénivelé un entier.</i>
      </div>
      <!-- Partie GPX -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Trace GPS</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="form-control" for="gpx_file">
          <b>Trace GPS :</b>
          <input class="file-input file-input-primary file-input-sm" type="file" id="gpx_file" name="gpx_file"
            accept=".gpx" required>
          <i class="text-red-400">Au format GPX !</i>
        </label>
        <label class="form-control" for="velo_variante">
          <b class="">Nom de la variante <span class="text-accent opacity-50">(optionnel)</span> :</b>
          <input class="input input-sm" type="text" id="velo_variante" name="velo_variante"
            oninput="formatVariante()">
          <i class=""> Dans le cas où il existe plusieurs itinéraires reliant une même gare à une même falaise, donner
            un nom aux différentes possibilités. Ex : "Option par le Nord" et "Option par le Sud".</i>
        </label>
        <label class="form-control" for="velo_varianteformate" style="display: none;">
          <b class="">Nom de la variante (formatée) <span class="text-accent opacity-50">(optionnel)</span> :</b>
          <input class="input input-sm" type="text" id="velo_varianteformate" name="velo_varianteformate"
            readonly style="display: none;">
        </label>
      </div>
      <!-- Partie Remarques -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Description</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="form-control" for="velo_descr">
          <b class="">Description de l'itinéraire, remarques <span class="text-accent opacity-50">(optionnel)</span>
            :</b>
          <textarea class="textarea textarea-sm leading-6" id="velo_descr" name="velo_descr" rows="5"
            cols="100"></textarea>
          <i class=""> On peut y détailler la surface (goudron ? Piste ?), le trafic (beaucoup de voitures ?), s'il y a
            des montées raides, si le parcours suit une voie verte, s'il y a des alternatives au tracé proposé... </i>
        </label>
      </div>
      <label class="form-control admin" for="velo_openrunner">
        <b class="text-gray-400 opactity-70">Lien Openrunner pour affichage profil en iframe :</b>
        <textarea type="text" class="textarea textarea-sm leading-6" id="velo_openrunner" rows="3"
          name="velo_openrunner"></textarea>
      </label>
      <!-- Partie Piéton -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Accessibilité Piéton</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <label class="form-control grow" for="velo_apieduniquement">
            <div class="flex items-center gap-4">
              <input class="checkbox-primary checkbox" type="checkbox" id="velo_apieduniquement"
                name="velo_apieduniquement">
              <b class="">Itinéraire conçu pour la marche uniquement ?</b>
            </div>
            <i class=""> Cocher si l'itinéraire peut se faire à pied, mais pas à vélo. </i>
          </label>
          <label class="form-control grow" for="velo_apiedpossible">
            <div class="flex items-center gap-4">
              <input class="checkbox-primary checkbox" type="checkbox" id="velo_apiedpossible"
                name="velo_apiedpossible">
              <b class="">Itinéraire conçu pour le vélo, mais faisable à pied ?</b>
            </div>
            <i class=""> Cocher si l'itinéraire peut se faire à vélo, mais est suffisamment court pour se faire aussi à
              pied (< 1h). </i>
          </label>
        </div>
      </div>
      <hr class="my-4">
      <h3 class="text-center">Validation de l'ajout de données</h3>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="form-control grow">
            <b>Itinéraire ajouté par : </b>
            <label for="nom_prenom" class="input input-primary input-sm flex items-center gap-2 w-full">
              <input class="grow" type="text" id="nom_prenom" name="nom_prenom"
                placeholder="Prénom (et/ou nom, surnom...)" required>
              <svg class="w-4 h-4 fill-none stroke-current">
                <use href="#user"></use>
              </svg>
            </label>
          </div>
          <div class="form-control grow">
            <b>Mail :</b>
            <label for="email" class="input input-primary input-sm flex items-center gap-2 w-full">
              <input class="grow" type="email" id="email" name="email" required>
              <svg class="w-4 h-4 fill-none stroke-current">
                <use href="#mail-line"></use>
              </svg>
            </label>
          </div>
        </div>
        <label class="form-control" for="message">
          <span class="">
            <b>Message <span class="text-accent opacity-50">(optionnel)</span> :</b>
            <i>(si vous voulez commenter votre ajout de données)</i>
          </span>
          <textarea class="textarea textarea-sm leading-6" id="message" name="message"
            rows="4"></textarea>
        </label>
        <button type="submit" class="btn btn-primary">Ajouter l'itinéraire vélo</button>
      </div>
    </form>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>
<script type="module" src="/dist/ajout-velo.js"></script>

</html>