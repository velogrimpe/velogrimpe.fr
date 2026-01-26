<?php
// Connexion à la base de données
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$preset_ville_id = isset($_GET['ville_id']) ? (int) $_GET['ville_id'] : null;
$preset_gare_id = isset($_GET['gare_id']) ? (int) $_GET['gare_id'] : null;

// Récupération des villes
$result_villes = $mysqli->query("SELECT ville_id, ville_nom FROM villes ORDER BY ville_nom");
$villes = [];
while ($row = $result_villes->fetch_assoc()) {
  $villes[$row['ville_id']] = $row['ville_nom'];
}

// Récupération des gares
$result_gares = $mysqli->query("SELECT gare_id, gare_nom, gare_codeuic FROM gares WHERE deleted = 0 ORDER BY gare_nom");
$gares = [];
while ($row = $result_gares->fetch_assoc()) {
  $gares[$row['gare_id']] = [
    'id' => $row['gare_id'],
    'nom' => $row['gare_nom'],
    'codeuic' => $row['gare_codeuic'],
  ];
}
$preset_gare_nom = $preset_gare_id !== null && isset($gares[$preset_gare_id]) ? $gares[$preset_gare_id]['nom'] : null;

// Read the admin search parameter
$admin = ($_GET['admin'] ?? false) == $config["admin_token"];

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter un itinéraire train - Vélogrimpe.fr</title>
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
        document.getElementById('train_public').value = '1';
        document.getElementById('admin').value = "<?= $config["admin_token"] ?>";
        document.getElementById('nom_prenom').value = "Florent";
        document.getElementById('email').value = "<?= $config['contact_mail'] ?>";
      <?php else: ?>
        document.getElementById('train_public').value = '2';
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
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto prose p-4
              prose-li:mt-0 prose-li:mb-0 prose-ul:mt-0 prose-ul:mb-0">
    <h1 class="text-4xl font-bold text-wrap text-center"> Ajouter un itinéraire train<span class="admin text-red-900">
        (version admin)</span>
    </h1>
    <div class="rounded-lg bg-base-300 p-4 my-6 border border-base-300 shadow-xs text-base-content">
      <b>Vous vous apprêtez à décrire un itinéraire Ville &rarr; Gare.</b><br> Commencez par vérifier que votre ville de
      départ est dans le menu déroulant ci-dessous. Si ce n'est pas le cas, l'ajout de données n'est pas possible :
      envoyez-nous un mail.
    </div>
    <form method="POST" action="/api/add_train.php" enctype="multipart/form-data" class="flex flex-col gap-4">
      <input type="hidden" class="input input-primary input-sm" id="train_public" name="train_public" value="2">
      <input class="input input-primary input-sm" type="hidden" id="admin" name="admin" value="0">
      <div class="flex flex-row gap-4 items-center">
        <!-- Menu déroulant des villes -->
        <div class="flex flex-col gap-1 w-1/2">
          <label class="form-control" for="ville_id">
            <b>Ville de départ :</b>
            <select class="select select-primary select-sm" id="ville_id" name="ville_id" required
              value="<?= $preset_ville_id ?? '' ?>">
              <option value="">Sélectionnez une ville</option>
              <?php foreach ($villes as $ville_id => $ville_nom): ?>
                <option value="<?= $ville_id; ?>" <?= (isset($preset_ville_id) && $preset_ville_id == $ville_id) ? 'selected' : '' ?>>
                  <?= $ville_nom; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <!-- Menu déroulant des gares -->
        <div class="flex flex-col gap-1 w-1/2">
          <label class="form-control" for="train_arrivee">
            <b>Gare d'arrivée :</b>
            <div id="vue-ajout-train"
              data-gares='<?= htmlspecialchars(json_encode(array_values($gares)), ENT_QUOTES, 'UTF-8') ?>'
              <?php if ($preset_gare_nom): ?>data-preset-gare-nom="<?= htmlspecialchars($preset_gare_nom) ?>"<?php endif; ?>>
            </div>
          </label>
          <input tabindex="-1" type="text" class="input input-disabled input-xs w-1/2 admin" id="gare_id" name="gare_id"
            value="<?= $preset_gare_id ?? '' ?>" readonly required>
          <input type="hidden" id="train_arrivee" name="train_arrivee" value="<?= $preset_gare_nom ?? '' ?>">
        </div>
      </div>
      <!-- <div class="flex flex-row gap-4 items-center"> -->
      <!-- Menu déroulant des gares -->
      <!-- <div class="flex flex-col gap-1 w-1/2">
          <div class="relative not-prose">
            <label class="form-control" for="train_depart">
              <b>Gare de départ :</b>
              <div class="input input-primary input-sm flex items-center gap-2 w-full">
                <input class="grow" type="text" id="train_depart" name="train_depart" required autocomplete="off" />
                <svg class="w-4 h-4 fill-none stroke-current">
                  <use href="#search"></use>
                </svg>
              </div>
            </label>
            <ul id="depart-search-list"
              class="autocomplete-list absolute w-full bg-white border border-primary mt-1 hidden">
            </ul>
          </div>
          <input tabindex="-1" type="text" class="input input-disabled input-xs w-1/2 admin" id="train_depart_id"
            name="train_depart_id" readonly required>
        </div> -->
      <!-- </div> -->
      <label class="form-control" for="train_tgv">
        <div class="flex flex-row items-center gap-2">
          <span>TER uniquement</span>
          <input type="checkbox" class="toggle toggle-primary toggle-sm" id="train_tgv" name="train_tgv">
          <span>Itinéraire TGV</span>
        </div>
      </label>
      <div id="itineraireExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
        <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
          <use href="#error-warning-fill"></use>
        </svg> Un itinéraire <span id="itineraireExistsType">train</span> existe déjà entre cette ville et cette gare.
        Si vous avez besoin de modifier les informations, contactez nous par mail à l'addresse <a
          href="mailto:contact@velogrimpe.fr">contact@velogrimpe.fr</a>.
      </div>
      <div class="p-4 border-2 border-secondary rounded-lg pt-2">
        <div class="text-center font-bold text-lg mb-2 text-secondary">Recherche itinéraires</div>
        <!-- Champs libres pour la recherche d'horaires (non envoyés au serveur) -->
        <div class="flex flex-col gap-2">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-control">
              <b>Gare de départ</b>
              <div id="vue-station-depart"></div>
              <span class="text-xs opacity-70">Recherche via Transitous. Non transmis lors de l'envoi.</span>
            </div>
            <div class="form-control">
              <b>Gare d'arrivée</b>
              <div id="vue-station-arrivee"></div>
              <span class="text-xs opacity-70">Recherche via Transitous. Non transmis lors de l'envoi.</span>
            </div>
          </div>
        </div>
        <div class="flex flex-col gap-4 mt-2">
          <div class="flex flex-row justify-end">
            <button class="btn btn-secondary btn-sm" type="button" id="fetchTrains">Consulter les horaires <div
                class="hidden loading loading-spinner"></div>
            </button>
          </div>
          <div class="border rounded-lg border-slate-400 hidden p-4 shadow-lg bg-base-100 max-h-100 overflow-y-auto"
            id="tableTrains">
            <table class="table table-xs table-zebra table-nowrap my-0">
              <thead>
                <tr>
                  <td>Départ</td>
                  <td>Durée</td>
                  <td>Nb Corresp.</td>
                  <td>N° Trains</td>
                  <td>Détails Trains</td>
                  <td>Inclure ?</td>
                </tr>
              </thead>
              <tbody id="tableTrainsBody">
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="flex flex-col gap-2 flex-wrap">
        <div class="grid grid-cols-1 gap-4 items-end flex-wrap md:grid-cols-3">
          <label class="form-control" for="train_temps">
            <b>Temps de trajet min. (min)</b>
            <input type="number" class="input input-primary input-sm" id="train_temps" name="train_temps"
              placeholder="52" min="0" required>
          </label>
          <label class="form-control" for="train_tempsmax">
            <b>Temps de trajet max. (min)</b>
            <input type="number" class="input input-primary input-sm" id="train_tempsmax" name="train_tempsmax"
              placeholder="125" min="0" required>
          </label>
          <label class="form-control" for="train_nbtrains">
            <b>Nb de trains par jour</b>
            <input type="number" class="input input-primary input-sm" id="train_nbtrains" name="train_nbtrains"
              placeholder="10" min="0" required>
          </label>
        </div>
        <div class="grid grid-cols-1 gap-x-4 items-end flex-wrap md:grid-cols-3">
          <label class="form-control grow-0" for="train_correspmin">
            <b>Nb min de corresp.</b>
            <input type="number" class="input input-primary input-sm" id="train_correspmin" name="train_correspmin"
              placeholder="0" min="0" required>
          </label>
          <label class="form-control grow-0" for="train_correspmax">
            <b>Nb max de corresp.</b>
            <input type="number" class="input input-primary input-sm" id="train_correspmax" name="train_correspmax"
              placeholder="1" min="0" required>
          </label>
        </div>
      </div>
      <label class="form-control" for="train_descr">
        <b>Description de l'itinéraire train :</b>
        <textarea class="textarea textarea-primary textarea-sm leading-6" id="train_descr" name="train_descr" rows="5"
          required></textarea>
        <i>Ici, on donne le nombre de trains par jours (dire si la fréquence change selon les jours), le nombre de
          correspondances et les gares de correspondances, les différentes possibilités s'il y en a, le prix d'un billet
          plein tarif,...<br> Exemple : <br> "22 TER/jour le Samedi, 13 TER/jour le Dimanche, 34 TER/jour en
          semaine.<br> La plupart des trains sont directs (8 à 10 minutes - plein tarif 4€), quelques trains avec
          correspondance à Moirans (19 à 57' - plein tarif 6,30€) ou rarement à Voiron."</i>
      </label>
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
        <button type="submit" class="btn btn-primary" id="submitBtn">Ajouter l'itinéraire train</button>
      </div>
    </form>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
</body>
<script src="/js/services/horaires-trains.js"></script>
<script>
  const villes = <?php echo json_encode($villes); ?>;
  const gares = <?php echo json_encode($gares); ?>;

  const verifierExistenceItineraire = () => {
    const gareId = document.getElementById('gare_id').value;
    const villeId = document.getElementById('ville_id').value;
    const isTgv = document.getElementById('train_tgv')?.checked ?? false;
    if (!gareId || !villeId) {
      document.getElementById("itineraireExistsAlert").classList.add("hidden");
      return;
    }
    fetch(`/api/verify_train_dup.php?gare_id=${gareId}&ville_id=${villeId}&train_tgv=${isTgv ? 1 : 0}`)
      .then(response => response.json())
      .then(exists => {
        if (exists) {
          document.getElementById("itineraireExistsAlert").classList.remove("hidden");
          const typeSpan = document.getElementById("itineraireExistsType");
          if (typeSpan) {
            typeSpan.textContent = isTgv ? "TGV" : "TER";
          }
          document.getElementById("submitBtn").disabled = true;
        } else {
          document.getElementById("itineraireExistsAlert").classList.add("hidden");
          document.getElementById("submitBtn").disabled = false;
        }
      });
  };


  // Callbacks are now handled by Vue component in /dist/ajout-train.js

  // Update only the description from API-provided fields; numeric fields are derived from selected trips
  const updateFields = (fields) => {
    if (fields?.train_descr !== undefined) {
      document.getElementById("train_descr").value = fields.train_descr;
    }
  }

  // Format helpers for table rendering
  const pad2 = (n) => (n < 10 ? `0${n}` : `${n}`);
  const formatDepartureTime = (startTime) => {
    const d = new Date(startTime);
    return `${d.getHours()}h${pad2(d.getMinutes())}`;
  };
  const toHMShort = (seconds) => {
    const minutes = Math.round(seconds / 60);
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h > 0 && m > 0) return `${h}h${pad2(m)}`;
    if (h > 0 && m === 0) return `${h}h`;
    return `${m} min`;
  };
  let currentTrips = [];
  const computeSelectionDerivedFields = () => {
    const checkboxes = Array.from(document.querySelectorAll('input[name="include_train"]'));
    const selectedIndexes = checkboxes
      .map(cb => ({ checked: cb.checked, idx: parseInt(cb.getAttribute('data-trip-index')) }))
      .filter(x => x.checked)
      .map(x => x.idx);
    const selectedTrips = selectedIndexes.map(i => currentTrips[i]).filter(Boolean);
    if (selectedTrips.length === 0) {
      // No selection: set minimal values to 0 to keep required fields valid
      document.getElementById("train_temps").value = 0;
      document.getElementById("train_correspmin").value = 0;
      document.getElementById("train_correspmax").value = 0;
      const nbTrainsInput = document.getElementById("train_nbtrains");
      if (nbTrainsInput) nbTrainsInput.value = 0;
      const tempsMaxInput = document.getElementById("train_tempsmax");
      if (tempsMaxInput) tempsMaxInput.value = 0;
      return;
    }
    const durationsMin = Math.min(...selectedTrips.map(t => Math.round(t.duration / 60)));
    const durationsMax = Math.max(...selectedTrips.map(t => Math.round(t.duration / 60)));
    const transfersMin = Math.min(...selectedTrips.map(t => t.transfers ?? 0));
    const transfersMax = Math.max(...selectedTrips.map(t => t.transfers ?? 0));
    document.getElementById("train_temps").value = durationsMin;
    document.getElementById("train_correspmin").value = transfersMin;
    document.getElementById("train_correspmax").value = transfersMax;
    const nbTrainsInput = document.getElementById("train_nbtrains");
    if (nbTrainsInput) nbTrainsInput.value = selectedTrips.length;
    const tempsMaxInput = document.getElementById("train_tempsmax");
    if (tempsMaxInput) tempsMaxInput.value = durationsMax;
  };

  const renderItineraries = (uniqueTrips = []) => {
    const tbody = document.getElementById("tableTrainsBody");
    tbody.innerHTML = "";
    currentTrips = uniqueTrips;
    uniqueTrips.forEach((trip, idx) => {
      const dep = formatDepartureTime(trip.startTime);
      const dur = toHMShort(trip.duration);
      const nbCorresp = trip.transfers ?? 0;
      const lineNamesHtml = `<div class="flex flex-col gap-1">${(trip.segments || [])
        .map(seg => {
          const badge = seg.tgv ? '<span class=\"badge badge-accent badge-xs text-[8px]\">TGV</span>' : '';
          return `<div class=\"flex items-center gap-1\">${badge}<span>${seg.mode || ''}</span></div>`
        })
        .join("")}</div>`;
      const detailsHtml = `<div class="flex flex-col gap-1">${(trip.segments || [])
        .map(seg => {
          return `<span>${seg.from} - ${seg.to} (${toHMShort(seg.duration)})</span>`;
        })
        .join("")}</div>`;

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${dep}</td>
        <td>${dur}</td>
        <td>${nbCorresp}</td>
        <td>${lineNamesHtml}</td>
        <td>${detailsHtml}</td>
        <td><input type="checkbox" class="checkbox checkbox-primary checkbox-sm" name="include_train" data-trip-index="${idx}" checked /></td>
      `;
      tbody.appendChild(tr);
    });
    // Attach change listeners to update numeric fields when selection changes
    Array.from(document.querySelectorAll('input[name="include_train"]')).forEach(cb => {
      cb.addEventListener('change', () => computeSelectionDerivedFields());
    });
    // reveal table container if we have content
    const tableWrapper = document.getElementById("tableTrains");
    if (uniqueTrips.length > 0) {
      tableWrapper.classList.remove("hidden");
    } else {
      tableWrapper.classList.add("hidden");
    }
    // Initialize numeric fields from the default (all checked) selection
    computeSelectionDerivedFields();
  };

  document.getElementById("fetchTrains").addEventListener('click', async () => {
    // Check if stations are selected from Vue
    const stations = window.__transitousStations__;
    if (!stations?.depart || !stations?.arrivee) {
      alert('Veuillez sélectionner une gare de départ et une gare d\'arrivée dans les listes.');
      return;
    }

    // Add loader in the button and disable it
    document.querySelector("#fetchTrains .loading").classList.remove("hidden");
    document.getElementById("fetchTrains").disabled = true;

    // Use coordinates from selected stations (already geocoded)
    const { stats, fields } = await horairesTrains.fetchRouteByCoords(
      stations.depart.lat, stations.depart.lon,
      stations.arrivee.lat, stations.arrivee.lon
    );
    renderItineraries(stats.uniqueTrips || []);
    // Update only description from fields; numeric fields already derived from selected trips
    updateFields(fields);
    document.querySelector("#fetchTrains .loading").classList.add("hidden");
    document.getElementById("fetchTrains").disabled = false;
  });
</script>
<script type="module" src="/dist/ajout-train.js"></script>

</html>