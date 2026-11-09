<!--Pour la version admin : ajouter le champ velo_openrunner, mettre "1" dans le champ velo_public, et afficher les noms formatés dans le formulaire pour les vérifier.
Il faudra aussi changer ajout_velo_db pour ajouter le champ openrunner, et nettoyer l'envoi mail automatique--> <?php
// Connexion à la base de données
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/map-bundle.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Arrêts de départ possibles : tous types (gares, mais aussi cars qui prennent les vélos…)
$result_arrets = $mysqli->query("SELECT id, nom, slug, commune, ter, tgv, bus, tram, teleph, ST_Y(loc) AS lat, ST_X(loc) AS lng FROM arrets WHERE deleted = 0 ORDER BY nom");
$arrets = [];
while ($row = $result_arrets->fetch_assoc()) {
  $commune = trim((string) $row['commune']);
  // La valeur affichée identifie l'arrêt : on ajoute la commune si le nom ne la contient pas déjà.
  $label = ($commune !== '' && mb_stripos($row['nom'], $commune) === false) ? $row['nom'] . ' (' . $commune . ')' : $row['nom'];
  $types = array_keys(array_filter(['ter' => $row['ter'], 'tgv' => $row['tgv'], 'bus' => $row['bus'], 'tram' => $row['tram'], 'teleph' => $row['teleph']]));
  $arrets[$row['id']] = [
    'id' => (int) $row['id'],
    'nom' => $label,
    'nomformate' => $row['slug'],
    'latlng' => $row['lat'] . ',' . $row['lng'],
    'types' => $types,
  ];
}

// Récupération des falaises
$result_falaises = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_nomformate, falaise_latlng, falaise_fermee, falaise_bloc FROM falaises ORDER BY falaise_nom");
$falaises = [];
while ($row = $result_falaises->fetch_assoc()) {
  $falaises[$row['falaise_id']] = [
    'id' => (int) $row['falaise_id'],
    'nom' => $row['falaise_nom'],
    'nomformate' => $row['falaise_nomformate'],
    'latlng' => $row['falaise_latlng'],
    'fermee' => $row['falaise_fermee'],
    'bloc' => (int) $row['falaise_bloc']
  ];
}

$falaise_id = isset($_GET['falaise_id']) ? $_GET['falaise_id'] : null;
$falaisePreset = $falaises[$falaise_id] ?? null;

// `gare_id` reste accepté : anciens liens (confirmation_train, etc.). Pour les gares, arrets.id == gare_id.
$arret_id = $_GET['arret_id'] ?? $_GET['gare_id'] ?? null;
$arretPreset = $arrets[$arret_id] ?? null;

// Read the admin search parameter
$admin = ($_GET['admin'] ?? false) == $config["admin_token"];
// ?mode=pied : ouvre le formulaire sur l'accès à pied (lien « Détailler l'accès »)
$mode_pied = ($_GET['mode'] ?? '') === 'pied';

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Ajouter un accès - Vélogrimpe.fr</title>
  <?php map_bundle_js('map'); ?>
  <?php map_bundle_css('map'); ?>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Contrib storage -->
  <script src="/js/contrib-storage.js"></script>
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
        // Pre-fill contributor info from localStorage
        if (window.contribStorage) {
          window.contribStorage.prefillContribInputs();
        }
      <?php endif; ?>
      document.querySelectorAll(".input-disabled").forEach(e => { e.value = "" });
      // Attach form save listener for contrib info
      if (window.contribStorage) {
        window.contribStorage.attachFormSaveListener(document.querySelector('form'));
      }
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
    <h1 class="text-4xl font-bold text-wrap text-center"> Ajouter un accès à une falaise<span class="admin text-red-900">
        (version admin)</span>
    </h1>
    <div class="rounded-lg bg-base-300 p-4 my-6 border border-base-300 shadow-xs text-base-content">
      <b>Vous vous apprêtez à décrire un accès Arrêt (Gare, arrêt de bus) &rarr; Falaise, à vélo ou à pied.</b><br>
      <i>Les champs obligatoires sont en noir, les optionnels en gris.</i>
    </div>
    <form method="POST" action="/api/add_velo.php" enctype="multipart/form-data" class="flex flex-col gap-4">
      <input class="input input-primary input-sm" type="hidden" id="velo_public" name="velo_public" value="2">
      <input class="input input-primary input-sm" type="hidden" id="admin" name="admin" value="0">
      <!-- Type d'accès : discrimine les champs affichés et l'écriture côté serveur -->
      <div class="flex flex-col md:flex-row gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="flex items-center gap-2 cursor-pointer">
          <input class="radio radio-primary" type="radio" name="mode" value="velo" <?= $mode_pied ? '' : 'checked' ?>>
          <b>Itinéraire à vélo</b> <span class="text-sm"></span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input class="radio radio-primary" type="radio" name="mode" value="pied" <?= $mode_pied ? 'checked' : '' ?>>
          <b>Accès sans vélo</b> <span class="text-sm">(depuis un arrêt de bus/tram, sans vélo)</span>
        </label>
      </div>
      <!-- Partie Départ / Arrivées -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Arrêt et Falaise</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div id="vue-ajout-velo"
          data-arrets='<?= htmlspecialchars(json_encode(array_values($arrets)), ENT_QUOTES, 'UTF-8') ?>'
          data-falaises='<?= htmlspecialchars(json_encode(array_values($falaises)), ENT_QUOTES, 'UTF-8') ?>'
          <?php if ($falaisePreset): ?>data-preset-falaise-id="<?= $falaisePreset['id'] ?>"<?php endif; ?>
          <?php if ($arretPreset): ?>data-preset-arret-id="<?= $arretPreset['id'] ?>"<?php endif; ?>>
        </div>
        <a class="text-sm" href="/ajout/ajout_arret.php<?= $admin ? '?admin=' . $config["admin_token"] : '' ?>" target="_blank">Mon arrêt n'existe pas : l'ajouter</a>
        <!-- Hidden fields for form submission -->
        <div class="admin flex flex-row gap-4">
          <input tabindex="-1" class="input input-disabled input-xs w-1/4" type="text" id="velo_depart"
            name="velo_depart" readonly required>
          <input tabindex="-1" class="input input-disabled input-xs w-1/4" type="text" id="arret_id" name="arret_id"
            readonly required>
          <input tabindex="-1" class="input input-disabled input-xs w-1/4" type="text" id="velo_arrivee"
            name="velo_arrivee" required readonly />
          <input tabindex="-1" class="input input-disabled input-xs w-1/4" type="text" id="falaise_id"
            name="falaise_id" required readonly />
        </div>
        <!-- Un accès du MÊME type existe déjà (cf. api/verify_velo_dup.php) : le texte suit le mode choisi -->
        <div id="itineraireExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
            <use href="#error-warning-fill"></use>
          </svg>
          <span data-alert-type="velo">Un itinéraire à vélo existe déjà entre cet arrêt et cette falaise. Vérifiez que
            vous ne faites pas de doublon ; s'il s'agit d'une autre option, donnez-lui un nom de variante.</span>
          <span data-alert-type="pied" class="hidden">Un accès sans vélo existe déjà entre cet arrêt et cette falaise.
            Vous pouvez le compléter : seules les informations qui lui manquent (description, distance, dénivelés,
            trace GPS) seront ajoutées. S'il s'agit d'une autre option, donnez-lui un nom de variante.</span>
        </div>
        <!-- Variante (les deux modes) : sous l'avertissement de doublon, pour pouvoir la renseigner une fois
             l'alerte affichée. Un accès de même type et de même variante = doublon (cf. api/verify_velo_dup.php). -->
        <label class="form-control" for="velo_variante">
          <b class="">Nom de la variante <span class="text-accent opacity-50">(optionnel)</span> :</b>
          <input class="input input-sm" type="text" id="velo_variante" name="velo_variante" maxlength="100"
            oninput="formatVariante()">
          <i class=""> Dans le cas où il existe plusieurs accès de même type reliant un même arrêt à une même falaise,
            donner un nom aux différentes possibilités. Ex : "Option par le Nord" et "Option par le Sud".</i>
        </label>
        <label class="form-control" for="velo_varianteformate" style="display: none;">
          <b class="">Nom de la variante (formatée) <span class="text-accent opacity-50">(optionnel)</span> :</b>
          <input class="input input-sm" type="text" id="velo_varianteformate" name="velo_varianteformate"
            readonly style="display: none;">
        </label>
      </div>
      <div id="section-velo">
<div class="flex flex-col gap-4">
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
        <div class="not-prose">
          <i class="text-sm">Vérifiez que la gare (départ), la falaise (arrivée) et la trace GPX sont
            cohérentes. Les éventuels points/marqueurs du GPX (début, fin…) seront automatiquement retirés à
            l'enregistrement.</i>
          <div id="velo-map" class="mt-2 rounded-lg border border-base-300 z-0" style="height: 400px;"></div>
        </div>
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
      <!-- « Faisable à pied » n'est plus saisi : un accès de 5 km ou moins l'est d'office (acces_a_pied_possible). -->
      </div>
      </div>
      <!-- Accès à pied uniquement -->
      <div id="section-pied" class="hidden">
      <div class="flex flex-col gap-4">
        <div class="relative flex items-center">
          <hr class="my-0 grow border-[#2e8b57]" />
          <div class="flex items-center justify-center">
            <span class="px-2 text-primary italic bg-unset rounded-full">Accès à pied</span>
          </div>
          <hr class="my-0 grow border-[#2e8b57]" />
        </div>
        <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
          <label class="form-control" for="description">
            <b>Description de l'accès à pied <span class="text-accent opacity-50">(optionnel)</span> :</b>
            <textarea class="textarea textarea-sm leading-6" id="description" name="description" rows="5" disabled></textarea>
            <i> Durée, sentier balisé ou non, dénivelé, passages délicats…</i>
          </label>
          <div class="flex flex-col md:flex-row gap-4">
            <label class="form-control w-full md:w-1/3" for="pied_km">
              <b>Distance (km) <span class="text-accent opacity-50">(optionnel)</span> :</b>
              <input class="input input-primary input-sm" type="number" id="pied_km" name="velo_km" placeholder="1.5"
                step="0.1" min="0" disabled>
            </label>
            <label class="form-control w-full md:w-1/3" for="pied_dplus">
              <b>D+ (m) <span class="text-accent opacity-50">(optionnel)</span> :</b>
              <input class="input input-primary input-sm" type="number" id="pied_dplus" name="velo_dplus"
                placeholder="120" min="0" disabled>
            </label>
            <label class="form-control w-full md:w-1/3" for="pied_dmoins">
              <b>D- (m) <span class="text-accent opacity-50">(optionnel)</span> :</b>
              <input class="input input-primary input-sm" type="number" id="pied_dmoins" name="velo_dmoins"
                placeholder="20" min="0" disabled>
            </label>
          </div>
          <label class="form-control" for="pied_gpx_file">
            <b>Trace GPS <span class="text-accent opacity-50">(optionnel)</span> :</b>
            <input class="file-input file-input-primary file-input-sm" type="file" id="pied_gpx_file" name="gpx_file"
              accept=".gpx" disabled>
            <i class="text-red-400">Au format GPX ! Si l'accès existe déjà, seules les informations manquantes sont ajoutées.</i>
          </label>
          <div class="not-prose">
            <i class="text-sm">Vérifiez que l'arrêt (départ), la falaise (arrivée) et la trace GPX sont cohérents. Les
              éventuels points/marqueurs du GPX (début, fin…) seront automatiquement retirés à l'enregistrement.</i>
            <div id="pied-map" class="mt-2 rounded-lg border border-base-300 z-0" style="height: 400px;"></div>
          </div>
        </div>
      </div>
      </div>
      <hr class="my-4">
      <h3 class="text-center">Validation de l'ajout de données</h3>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="form-control grow">
            <b>Itinéraire ajouté par : </b>
            <label for="nom_prenom" class="input input-primary input-sm flex items-center gap-2 w-full">
              <input class="grow" type="text" id="nom_prenom" name="nom_prenom" autocomplete="name"
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
        <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/contrib-licence-notice.php"; ?>
        <button type="submit" class="btn btn-primary">Ajouter l'accès</button>
      </div>
    </form>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>
<script type="module" src="/dist/ajout-velo.js"></script>
<script>
  // Bascule vélo / à pied : les champs de la section masquée sont désactivés
  // (pas de `required` bloquant, rien d'envoyé au serveur).
  function appliquerMode() {
    const pied = document.querySelector('input[name=mode]:checked').value === 'pied';
    [['section-velo', !pied], ['section-pied', pied]].forEach(([id, visible]) => {
      const section = document.getElementById(id);
      section.classList.toggle('hidden', !visible);
      section.querySelectorAll('input, textarea, select').forEach((el) => { el.disabled = !visible; });
    });
  }
  document.querySelectorAll('input[name=mode]').forEach((radio) => radio.addEventListener('change', appliquerMode));
  appliquerMode(); // état initial (?mode=pied)
</script>
<script type="module">
  import { initVeloFormMap } from '/js/components/map/velo-form-map.js';
  initVeloFormMap({ mapElId: 'velo-map', gpxInputId: 'gpx_file' });
  // Mode à pied : même carte de vérification, avec la trace GPX facultative de cette section
  initVeloFormMap({ mapElId: 'pied-map', gpxInputId: 'pied_gpx_file' });
</script>

</html>
