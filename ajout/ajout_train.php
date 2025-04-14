<?php
// Connexion à la base de données
include "../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

// Récupération des villes
$result_villes = $mysqli->query("SELECT ville_id, ville_nom FROM villes ORDER BY ville_nom");
$villes = [];
while ($row = $result_villes->fetch_assoc()) {
  $villes[$row['ville_id']] = $row['ville_nom'];
}

// Récupération des gares
$result_gares = $mysqli->query("SELECT gare_id, gare_nom, gare_codeuic FROM gares ORDER BY gare_nom");
$gares = [];
while ($row = $result_gares->fetch_assoc()) {
  $gares[$row['gare_id']] = [
    'id' => $row['gare_id'],
    'nom' => $row['gare_nom'],
    'codeuic' => $row['gare_codeuic'],
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
  <title>Ajouter un itinéraire train - Vélogrimpe.fr</title>
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
        document.getElementById('train_public').value = '1';
        document.getElementById('admin').value = "<?= $config["admin_token"] ?>";
        document.getElementById('nom_prenom').value = "Florent";
        document.getElementById('email').value = "<?= $config['contact_mail'] ?>";
      <?php else: ?>
        document.getElementById('train_public').value = '2';
        document.getElementById('admin').value = '0';
      <?php endif; ?>
      document.querySelectorAll(".input-disabled").forEach(e => { e.value = "" });
    });
  </script>
</head>

<body class="min-h-screen">
  <div class="max-w-screen-md mx-auto prose p-4 prose-a:text-[oklch(var(--p)/1)]
              prose-a:font-bold prose-a:no-underline hover:prose-a:underline
              prose-li:mt-0 prose-li:mb-0 prose-ul:mt-0 prose-ul:mb-0
              hover:prose-a:text-[oklch(var(--pf)/1)]">
    <h1 class="text-4xl font-bold text-wrap text-center">
      Ajouter un itinéraire train<span class="admin text-red-900"> (version admin)</span>
    </h1>
    <div class="rounded-lg bg-base-300 p-4 my-6 border border-base-300 shadow-sm text-base-content">
      <b>Vous vous apprêtez à décrire un itinéraire Ville &rarr; Gare.</b><br>
      Commencez par vérifier que votre ville de départ est dans le menu déroulant ci-dessous.
      Si ce n'est pas le cas, l'ajout de données n'est pas possible : envoyez-nous un mail.
    </div>
    <form method="POST" action="ajout_train_db.php" enctype="multipart/form-data" class="flex flex-col gap-4">
      <input type="hidden" class="input input-primary input-sm" id="train_public" name="train_public" value="2">
      <input class="input input-primary input-sm" type="hidden" id="admin" name="admin" value="0">

      <datalist id="gares">
        <?php foreach ($gares as $gare_id => $gare): ?>
          <option value="<?= $gare['nom']; ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <!-- Menu déroulant des villes -->
      <div class="flex flex-col gap-1 w-1/2">
        <label class="form-control" for="ville_id">
          <b>Ville de départ :</b>
          <select class="select select-primary select-sm" id="ville_id" name="ville_id" required>
            <option value="">Sélectionnez une ville</option>
            <?php foreach ($villes as $ville_id => $ville_nom): ?>
              <option value="<?= $ville_id; ?>"><?= $ville_nom; ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="flex flex-row gap-4 items-center">
        <!-- Menu déroulant des gares -->
        <div class="flex flex-col gap-1 w-1/2">
          <div class="relative not-prose">
            <label class="form-control" for="train_depart">
              <b>Gare de départ :</b>
              <div class="input input-primary input-sm flex items-center gap-2 w-full">
                <input class="grow" type="text" id="train_depart" name="train_depart" required autocomplete="off" />
                <svg class="w-4 h-4 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-search-line"></use>
                </svg>
              </div>
            </label>
            <ul id="depart-search-list"
              class="autocomplete-list absolute w-full bg-white border border-primary mt-1 hidden">
            </ul>
          </div>
          <input tabindex="-1" type="text" class="input input-disabled input-xs w-1/2 admin" id="train_depart_id"
            name="train_depart_id" readonly required>
          <input tabindex="-1" type="text" class="input input-disabled input-xs w-1/2 admin" id="train_depart_uic"
            name="train_depart_uic" readonly required>
        </div>

        <!-- Menu déroulant des gares -->
        <div class="flex flex-col gap-1 w-1/2">
          <div class="relative not-prose">
            <label class="form-control" for="train_arrivee">
              <b>Gare d'arrivée :</b>
              <div class="input input-primary input-sm flex items-center gap-2 w-full">
                <input class="grow" type="text" id="train_arrivee" name="train_arrivee" required autocomplete="off" />
                <svg class="w-4 h-4 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-search-line"></use>
                </svg>
              </div>
            </label>
            <ul id="arrivee-search-list"
              class="autocomplete-list absolute w-full bg-white border border-primary mt-1 hidden">
            </ul>
          </div>
          <input tabindex="-1" type="text" class="input input-disabled input-xs w-1/2 admin" id="gare_id" name="gare_id"
            readonly required>
          <input tabindex="-1" type="text" class="input input-disabled input-xs w-1/2 admin" id="train_arrivee_uic"
            name="train_arrivee_uic" readonly required>
        </div>
      </div>

      <div id="itineraireExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
        <svg class="w-4 h-4 mb-1 fill-current inline-block">
          <use xlink:href="/symbols/icons.svg#ri-error-warning-fill"></use>
        </svg> Un itinéraire existe déjà entre cette ville et cette gare. Si vous avez besoin de modifier les
        informations, contactez nous par mail à l'addresse <a
          href="mailto:contact@velogrimpe.fr">contact@velogrimpe.fr</a>.
      </div>

      <div class="flex flex-col gap-4">
        <div class="flex flex-row justify-start">
          <button class="btn btn-secondary btn-sm" type="button" id="fetchTrains">Consulter les horaires
            <div class="hidden loading loading-spinner"></div>
          </button>
        </div>
        <div class="border rounded-lg border-slate-400 hidden p-4 shadow-lg bg-base-100 max-h-[400px] overflow-y-auto"
          id="tableTrains">
          <table class="table table-xs table-zebra table-nowrap my-0">
            <thead>
              <tr>
                <td>Départ</td>
                <td>Arrivée</td>
                <td>Via (durée Cor.)</td>
                <td>Durée</td>
                <td>Lu</td>
                <td>Ma</td>
                <td>Me</td>
                <td>Je</td>
                <td>Ve</td>
                <td>Sa</td>
                <td>Di</td>
              </tr>
            </thead>
            <tbody id="tableTrainsBody">
            </tbody>
          </table>
        </div>
      </div>

      <details>
        <summary>Ancien protocole de relevé de données <i>(cliquez pour développer)</i></summary>
        <div class="prose-p:mb-0 prose-p:mt-0">
          <p>
            Pour la suite, suivez le protocole ci-dessous ; c'est galère, mais on n'a pas trouvé mieux pour l'instant !
          </p>
          <ul>
            <li>Allez sur <a href="https://www.b-europe.com/FR">le site de la SNCB.</a></li>
            <li>Lancez une recherche AVANCÉE entre votre ville de départ et la gare d'arrivée, et dans les options,
              cochez "pas de trains à grande vitesse".</li>
            <li>Affichez tous les trains sur une période de Samedi à Lundi (on fait ça car les fréquences varient entre
              la semaine, le Samedi et le Dimanche).</li>
            <li>Synthétisez les résultats obtenus dans les champs ci-dessous.</li>
          </ul>
        </div>
      </details>

      <div class="flex flex-row gap-4">
        <label class="form-control" for="train_temps">
          <b>Temps minimal de trajet (en minutes) :</b>
          <input type="number" class="input input-primary input-sm" id="train_temps" name="train_temps" placeholder="52"
            min="0" required>
        </label>

        <label class="form-control" for="train_correspmin">
          <b>Nombre minimal de correspondances :</b>
          <input type="number" class="input input-primary input-sm" id="train_correspmin" name="train_correspmin"
            placeholder="0" min="0" required>
        </label>

        <label class="form-control" for="train_correspmax">
          <b>Nombre maximal de correspondances :</b>
          <input type="number" class="input input-primary input-sm" id="train_correspmax" name="train_correspmax"
            placeholder="1" min="0" required>
        </label>
      </div>

      <label class="form-control" for="train_descr">
        <b>Description de l'itinéraire train :</b>
        <textarea class="textarea textarea-primary textarea-sm" id="train_descr" name="train_descr" rows="5"
          required></textarea>
        <i>Ici, on donne le nombre de trains par jours (dire si la fréquence change selon les jours),
          le nombre de correspondances et les gares de correspondances, les différentes possibilités s'il y en a,
          le prix d'un billet plein tarif,...<br>
          Exemple : <br>
          "22 TER/jour le Samedi, 13 TER/jour le Dimanche, 34 TER/jour en semaine.<br>
          La plupart des trains sont directs (8 à 10 minutes - plein tarif 4€), quelques trains avec correspondance à
          Moirans (19 à 57' - plein tarif 6,30€) ou rarement à Voiron."</i>
      </label>

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

      <button type="submit" class="btn btn-primary">AJOUTER L'ITINÉRAIRE TRAIN</button>

    </form>
  </div>
</body>

<script>
  const villes = <?php echo json_encode($villes); ?>;
  const gares = <?php echo json_encode($gares); ?>;

  const verifierExistenceItineraire = () => {
    const gareId = document.getElementById('gare_id').value;
    const villeId = document.getElementById('ville_id').value;
    if (!gareId || !villeId) {
      document.getElementById("itineraireExistsAlert").classList.add("hidden");
      return;
    }
    fetch(`/ajout/check_train.php?gare_id=${gareId}&ville_id=${villeId}`)
      .then(response => response.json())
      .then(exists => {
        if (exists) {
          document.getElementById("itineraireExistsAlert").classList.remove("hidden");
        } else {
          document.getElementById("itineraireExistsAlert").classList.add("hidden");
        }
      });
  };


  const arriveeCallback = (gareNom) => {
    const gare = gareNom ? Object.values(gares).find(g => g.nom === gareNom) : {};
    document.getElementById('gare_id').value = gare.id;
    document.getElementById('train_arrivee_uic').value = gare.codeuic;
  };
  const departCallback = (gareNom) => {
    const gare = gareNom ? Object.values(gares).find(g => g.nom === gareNom) : {};
    document.getElementById('train_depart_id').value = gare.id;
    document.getElementById('train_depart_uic').value = gare.codeuic;
  };

  document.getElementById("fetchTrains").addEventListener('click', () => {
    // Add loader in the button and disable it
    document.querySelector("#fetchTrains .loading").classList.remove("hidden");
    document.getElementById("fetchTrains").disabled = true;
    const gareDepart = document.getElementById('train_depart_uic').value;
    const gareArrivee = document.getElementById('train_arrivee_uic').value;
    if (!gareDepart || !gareArrivee) {
      alert("Veuillez sélectionner une gare de départ et une gare d'arrivée.");
      return;
    }
    fetch(`/ajout/fetch_trains.php?depart_uic=${gareDepart}&arrivee_uic=${gareArrivee}`)
      .then(response => response.json())
      .then(data => {
        const tableBody = document.getElementById('tableTrainsBody');
        tableBody.innerHTML = '';
        data.forEach(row => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${row.depart.replace(/:00$/g, "")}</td>
            <td>${row.arrivee.replace(/:00$/g, "")}</td>
            <td>${row.via === "Direct" ? row.via : row.via + " (" + row.co_dur + ")"}</td>
            <td>${row.duree} min</td>
            <td class="w-12">${row.lundi}</td>
            <td class="w-12">${row.mardi}</td>
            <td class="w-12">${row.mercredi}</td>
            <td class="w-12">${row.jeudi}</td>
            <td class="w-12">${row.vendredi}</td>
            <td class="w-12">${row.samedi}</td>
            <td class="w-12">${row.dimanche}</td>
          `;
          tableBody.appendChild(tr);
        });
        const tempsMin = Math.min(...data.map(row => row.duree));
        const minCorresp = data.find(row => row.via === 'Direct') ? 0 : 1;
        const nbTrainsPerDay = data.reduce((acc, row) => {
          acc.lundi += (row.lundi === '✅' || row.lundi >= "50") ? 1 : 0;
          acc.mardi += (row.mardi === '✅' || row.mardi >= "50") ? 1 : 0;
          acc.mercredi += (row.mercredi === '✅' || row.mercredi >= "50") ? 1 : 0;
          acc.jeudi += (row.jeudi === '✅' || row.jeudi >= "50") ? 1 : 0;
          acc.vendredi += (row.vendredi === '✅' || row.vendredi >= "50") ? 1 : 0;
          acc.samedi += (row.samedi === '✅' || row.samedi >= "50") ? 1 : 0;
          acc.dimanche += (row.dimanche === '✅' || row.dimanche >= "50") ? 1 : 0;
          return acc;
        }, { lundi: 0, mardi: 0, mercredi: 0, jeudi: 0, vendredi: 0, samedi: 0, dimanche: 0 });
        const avgWeekDay = (nbTrainsPerDay.lundi + nbTrainsPerDay.mardi + nbTrainsPerDay.mercredi + nbTrainsPerDay.jeudi + nbTrainsPerDay.vendredi) / 5;
        const nDirects = data.filter(row => row.via === 'Direct').length;
        const nCorresp = data.length - nDirects;
        const ratioDirects = nDirects > 15
          ? `<i>Les trajets directs étant suffisament nombreux, les trajets avec correspondance n'ont pas été vérifiés</i>`
          : nDirects > 2 * nCorresp
            ? 'Une grande majorité de directs'
            : nDirects > nCorresp
              ? "Une majorité de directs"
              : nDirects === 0
                ? "Aucun trajet direct"
                : "Une majorité de trains avec correspondance";
        const maxCoDur = data.filter(r => r.via !== "Direct").reduce((acc, row) => Math.max(acc, row.co_dur), 0);
        const minCoDur = data.filter(r => r.via !== "Direct").reduce((acc, row) => Math.min(acc, row.co_dur), 1000);
        const maxDur = Math.max(...data.map(row => row.duree));
        const minDur = Math.min(...data.map(row => row.duree));
        const comment = (
          `De ${minDur} à ${maxDur} minutes de trajet.\n`
          + `En moyenne, ${nbTrainsPerDay.samedi} TER/jour le Samedi, ${nbTrainsPerDay.dimanche} TER/jour`
          + ` le Dimanche et ${Math.round(avgWeekDay)} TER/jour en semaine.\n`
          + `${ratioDirects}.\n`
          + `${nCorresp > 0 ? `Durée de correspondance : ${minCoDur} à ${maxCoDur} minutes.` : ''}`
        );
        document.getElementById('train_descr').value = comment;
        document.getElementById('train_temps').value = tempsMin;
        document.getElementById('train_correspmin').value = minCorresp;
        document.querySelector("#fetchTrains .loading").classList.add("hidden");
        document.getElementById("fetchTrains").disabled = false;
        document.getElementById("tableTrains").classList.remove("hidden");
      });
  });
</script>
<script src="/js/autocomplete.js"></script>
<script>
  setupAutocomplete("train_depart", "depart-search-list", "gares", departCallback);
  setupAutocomplete("train_arrivee", "arrivee-search-list", "gares", arriveeCallback);
</script>

</html>