<?php
/**
 * Édition d'un itinéraire vélo existant : longueur, D+, D- et trace GPX.
 * Sélection en cascade Falaise → Gare → Variante, pré-remplissable par l'URL
 * (?falaise_id=&gare_id=&velo_id=). Cf. docs/plans/2026-08-26-edition-itineraire-velo.md.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/map-bundle.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Falaises (seul jeu de données embarqué : gares et variantes sont chargées à la
// demande via /api/fetch_velos.php selon la falaise choisie).
$result_falaises = $mysqli->query("SELECT falaise_id, falaise_nom, falaise_nomformate, falaise_latlng, falaise_fermee, falaise_bloc FROM falaises ORDER BY falaise_nom");
$falaises = [];
while ($row = $result_falaises->fetch_assoc()) {
  $falaises[] = [
    'id' => (int) $row['falaise_id'],
    'nom' => $row['falaise_nom'],
    'nomformate' => $row['falaise_nomformate'],
    'latlng' => $row['falaise_latlng'],
    'fermee' => $row['falaise_fermee'],
    'bloc' => (int) $row['falaise_bloc']
  ];
}

// Presets URL : transmis tels quels à l'app Vue, qui vérifie la cohérence de la
// combinaison contre les itinéraires réellement en base.
$preset_falaise_id = isset($_GET['falaise_id']) ? (int) $_GET['falaise_id'] : 0;
$preset_gare_id = isset($_GET['gare_id']) ? (int) $_GET['gare_id'] : 0;
$preset_velo_id = isset($_GET['velo_id']) ? (int) $_GET['velo_id'] : 0;

$admin = hash_equals((string) $config["admin_token"], (string) ($_GET['admin'] ?? ''));

// Admin : itinéraires en attente de validation (velo_public = 2), pour la
// section de sélection rapide en haut de page.
$velos_a_valider = [];
if ($admin) {
  $res = $mysqli->query("SELECT v.velo_id, v.gare_id, v.falaise_id, v.velo_variante, v.velo_contrib, v.date_creation,
      g.gare_nom, f.falaise_nom
    FROM velo v
    LEFT JOIN gares g ON g.gare_id = v.gare_id
    LEFT JOIN falaises f ON f.falaise_id = v.falaise_id
    WHERE v.velo_public = 2
    ORDER BY v.date_creation DESC, v.velo_id DESC");
  while ($row = $res->fetch_assoc()) {
    $velos_a_valider[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Modifier un itinéraire vélo - Vélogrimpe.fr</title>
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
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if ($admin): ?>
        document.getElementById('admin').value = "<?= $config["admin_token"] ?>";
        document.getElementById('nom_prenom').value = "Florent";
        document.getElementById('email').value = "<?= $config['contact_mail'] ?>";
      <?php else: ?>
        document.getElementById('admin').value = '0';
        if (window.contribStorage) {
          window.contribStorage.prefillContribInputs();
        }
      <?php endif; ?>
      if (window.contribStorage) {
        window.contribStorage.attachFormSaveListener(document.querySelector('form'));
      }
    });
  </script>
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto prose p-4">
    <h1 class="text-4xl font-bold text-wrap text-center"> Modifier un itinéraire vélo<span class="admin text-red-900">
        (version admin)</span>
    </h1>
    <div class="rounded-lg bg-base-300 p-4 my-6 border border-base-300 shadow-xs text-base-content">
      <b>Vous vous apprêtez à corriger un itinéraire Gare &rarr; Falaise existant</b> (longueur, dénivelés, description, trace
      GPS).<br>
      <i>Choisissez la falaise, puis la gare de départ, puis la variante. Pour ajouter un nouvel itinéraire,
        passez plutôt par <a id="velo-edit-ajout-link" href="/ajout/ajout_velo.php<?= $admin ? '?admin=' . htmlspecialchars($config["admin_token"]) : '' ?>">le formulaire d'ajout</a>.</i>
    </div>
    <form method="POST" action="/api/edit_velo.php" enctype="multipart/form-data" class="flex flex-col gap-4">
      <input type="hidden" id="admin" name="admin" value="0">
      <input type="hidden" id="velo_id" name="velo_id" value="">
      <?php if ($admin): ?>
        <!-- Admin : itinéraires en attente de validation -->
        <div class="relative flex items-center">
          <hr class="my-0 grow border-red-900" />
          <div class="flex items-center justify-center">
            <span class="px-2 text-red-900 italic bg-unset rounded-full">⚠️ Itinéraires à valider</span>
          </div>
          <hr class="my-0 grow border-red-900" />
        </div>
        <div class="flex flex-col gap-2 bg-base-100 p-4 rounded-lg border border-red-900 shadow-xs">
          <?php if (count($velos_a_valider) === 0): ?>
            <p class="my-0">Aucun itinéraire vélo en attente de validation 💪</p>
          <?php else: ?>
            <label class="form-control not-prose" for="select-a-valider">
              <b><?= count($velos_a_valider) ?> itinéraire<?= count($velos_a_valider) > 1 ? 's' : '' ?> en attente (velo_public = 2) :</b>
              <select id="select-a-valider" class="select select-primary select-sm w-full"
                onchange="if (this.value) window.location.href = '/ajout/edit_velo.php?admin=<?= urlencode($config["admin_token"]) ?>&' + this.value">
                <option value="">Sélectionner un itinéraire à vérifier…</option>
                <?php foreach ($velos_a_valider as $v): ?>
                  <option value="<?= http_build_query(['falaise_id' => $v['falaise_id'], 'gare_id' => $v['gare_id'], 'velo_id' => $v['velo_id']]) ?>"
                    <?= (int) $v['velo_id'] === $preset_velo_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars(($v['gare_nom'] ?? '?') . ' → ' . ($v['falaise_nom'] ?? '?') . ($v['velo_variante'] !== '' ? ' (' . $v['velo_variante'] . ')' : '') . ' — ' . substr($v['date_creation'], 0, 10) . ' — ' . $v['velo_contrib']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <i class="text-sm">En enregistrant en tant qu'admin, l'itinéraire passe automatiquement en « validé ».</i>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <!-- Partie Sélection -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Itinéraire à modifier</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div id="vue-edit-velo"
          data-admin="<?= $admin ? '1' : '0' ?>"
          data-falaises='<?= htmlspecialchars(json_encode($falaises), ENT_QUOTES, 'UTF-8') ?>'
          <?php if ($preset_falaise_id): ?>data-preset-falaise-id="<?= $preset_falaise_id ?>"<?php endif; ?>
          <?php if ($preset_gare_id): ?>data-preset-gare-id="<?= $preset_gare_id ?>"<?php endif; ?>
          <?php if ($preset_velo_id): ?>data-preset-velo-id="<?= $preset_velo_id ?>"<?php endif; ?>>
        </div>
        <div id="velo-edit-none" style="display:none" class="bg-amber-100 border border-amber-700 text-amber-900 p-2 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
            <use href="#error-warning-fill"></use>
          </svg> Aucun itinéraire vélo n'est enregistré pour cette falaise. Vous pouvez en
          <a class="font-bold" href="/ajout/ajout_velo.php">ajouter un</a>.
        </div>
      </div>

      <div id="velo-edit-fields" style="display:none" class="flex flex-col gap-4">
        <div id="velo-edit-validated" style="display:none" class="bg-blue-100 border border-blue-900 text-blue-900 p-3 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
            <use href="#error-warning-fill"></use>
          </svg> <b>Cet itinéraire est déjà validé.</b> Ses indicateurs (longueur, dénivelés) ne sont plus modifiables
          directement. Vous pouvez proposer une nouvelle description et/ou une nouvelle trace GPX : votre suggestion
          sera transmise aux administrateurs, qui l'appliqueront après vérification. La modification ne sera donc pas
          visible immédiatement.
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
          <div class="flex flex-col md:flex-row gap-4 md:items-start">
            <div class="form-control grow basis-0 not-prose flex flex-col gap-1">
              <b>Trace actuelle :</b>
              <a id="gpx_download" style="display:none" class="btn btn-outline btn-primary btn-sm w-fit" download target="_blank">
                <svg class="w-4 h-4 fill-none stroke-current">
                  <use href="#download"></use>
                </svg> Télécharger le GPX existant
              </a>
              <span id="gpx_missing" style="display:none" class="text-sm text-amber-700">Aucune trace GPX n'est enregistrée pour cet
                itinéraire.</span>
              <i class="text-sm text-slate-500">Trace en base, affichée en bleu pointillé sur la carte.</i>
            </div>
            <label class="form-control grow basis-0 not-prose flex flex-col gap-1" for="gpx_file">
              <b>Remplacer la trace <span class="text-accent opacity-50">(optionnel)</span> :</b>
              <input class="file-input file-input-primary file-input-sm w-full" type="file" id="gpx_file" name="gpx_file"
                accept=".gpx">
              <i class="text-sm text-red-400">Au format GPX !</i>
            </label>
          </div>
          <div class="not-prose">
            <i class="text-sm">Vérifiez que la gare (départ), la falaise (arrivée) et la trace sont cohérentes. La
              trace existante est en <span class="text-blue-700 font-bold">bleu pointillé</span>, la nouvelle trace
              choisie en <span class="text-[#2e8b57] font-bold">vert</span>. Les éventuels points/marqueurs du GPX
              (début, fin…) seront automatiquement retirés à l'enregistrement.</i>
            <div id="velo-map" class="mt-2 rounded-lg border border-base-300 z-0" style="height: 400px;"></div>
          </div>
        </div>
        <!-- Partie Description -->
        <div class="relative flex items-center">
          <hr class="my-0 grow border-[#2e8b57]" />
          <div class="flex items-center justify-center">
            <span class="px-2 text-primary italic bg-unset rounded-full">Description</span>
          </div>
          <hr class="my-0 grow border-[#2e8b57]" />
        </div>
        <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
          <label class="form-control" for="velo_descr">
            <b>Description de l'itinéraire, remarques <span class="text-accent opacity-50">(optionnel)</span> :</b>
            <textarea class="textarea textarea-sm leading-6" id="velo_descr" name="velo_descr" rows="5"></textarea>
            <i>On peut y détailler la surface (goudron ? Piste ?), le trafic, les montées raides, si le parcours suit
              une voie verte, s'il y a des alternatives au tracé proposé...</i>
          </label>
          <label class="form-control admin" for="velo_openrunner">
            <b class="text-gray-400">Lien Openrunner pour affichage profil en iframe <span class="text-accent opacity-50">(optionnel, admin)</span> :</b>
            <textarea class="textarea textarea-sm leading-6" id="velo_openrunner" name="velo_openrunner" rows="3"
              <?= $admin ? '' : 'disabled' ?>></textarea>
          </label>
        </div>
        <hr class="my-4">
        <h3 class="text-center">Validation de la modification</h3>
        <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
          <div class="flex flex-col md:flex-row gap-4">
            <div class="form-control grow">
              <b>Itinéraire modifié par : </b>
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
              <i>(pour expliquer la correction)</i>
            </span>
            <textarea class="textarea textarea-sm leading-6" id="message" name="message" rows="4"></textarea>
          </label>
          <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/contrib-licence-notice.php"; ?>
          <button type="submit" id="velo-edit-submit" class="btn btn-primary">Enregistrer les modifications</button>
        </div>
      </div>
    </form>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>
<script type="module" src="/dist/edit-velo.js"></script>
<script type="module">
  import { initVeloFormMap } from '/js/components/map/velo-form-map.js';
  initVeloFormMap({ mapElId: 'velo-map', gpxInputId: 'gpx_file' });
</script>

</html>
