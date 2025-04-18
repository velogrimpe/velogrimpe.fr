<?php
require_once "../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';

$res_z = $mysqli->query("SELECT * FROM zones ORDER BY zone_nom");
$zones = [];
while ($zone = $res_z->fetch_assoc()) {
  $zones[] = $zone;
}
$result_falaises = $mysqli->query("SELECT falaise_nom FROM falaises ORDER BY falaise_nom");
$falaises = [];
while ($row = $result_falaises->fetch_assoc()) {
  $falaises[] = $row['falaise_nom'];
}
// Read the admin search parameter
$admin = ($_GET['admin'] ?? false) == $config["admin_token"];

?>

<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ajouter une falaise - Vélogrimpe.fr</title>
  <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png" />
  <link rel="icon" type="image/png" sizes="96x96" href="/images/favicon-96x96.png" />

  <link href=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css " rel="stylesheet">
  <script src=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js "></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Multi-select -->
  <script src="/js/multi-select.min.js"></script>

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
        document.querySelectorAll("input").forEach(el => { el.required = false });
        document.querySelectorAll("input#falaise_nom").forEach(el => { el.required = true });
        document.querySelectorAll("input#falaise_coords").forEach(el => { el.required = true });
        document.querySelectorAll("textarea").forEach(el => { el.required = false });
        document.querySelectorAll("select").forEach(el => { el.required = false });
        document.getElementById('falaise_public').value = '1';
        document.getElementById('admin').value = "<?= $config["admin_token"] ?>";
        document.getElementById('nom_prenom').value = "Florent";
        document.getElementById('email').value = "<?= $config['contact_mail'] ?>";
      <?php else: ?>
        document.getElementById('falaise_public').value = '2';
        document.getElementById('admin').value = '0';
      <?php endif; ?>
      document.querySelectorAll(".input-disabled").forEach(e => e.value = "");
    });
  </script>
  <script>
    function formatNomFalaise() {
      const nom = document.getElementById("falaise_nom").value;
      const nomFormate = nom
        .toLowerCase() // Convertit en minuscules
        .normalize("NFD") // Sépare les caractères et leurs accents
        .replace(/[\u0300-\u036f]/g, "") // Supprime les accents
        .replace(/[^a-z0-9\s-]/g, "") // Supprime les caractères spéciaux sauf les espaces et tirets
        .replace(/\s+/g, "-") // Remplace les espaces par des tirets
        .replace(/-+/g, "-") // Remplace les tirets multiples par un seul
        .replace(/^-|-$/g, "") // Supprime les tirets en début/fin
        .substring(0, 255); // Limite à 255 caractères
      document.getElementById("falaise_nomformate").value = nomFormate;
    }
  </script>

</head>

<body class="min-h-screen flex flex-col">
  <?php include "../components/header.html"; ?>
  <main class="w-full flex-grow max-w-screen-md mx-auto prose p-4
              prose-a:text-[oklch(var(--p)/1)] prose-a:font-bold prose-a:no-underline
              hover:prose-a:underline hover:prose-a:text-[oklch(var(--pf)/1)]
              prose-pre:my-0 prose-pre:text-center">
    <h1 class="text-4xl font-bold text-wrap text-center">
      Ajouter une falaise<span class='text-red-900 admin'> (version admin)</span>
    </h1>
    <div class="rounded-lg bg-base-300 p-4 my-6 border border-base-300 shadow-sm text-base-content">
      <b>Il s'agit ici d'ajouter une falaise au site web.</b><br>
      Commencez par vérifier qu'elle n'est pas déjà sur le site !<br>
      Vous allez avoir besoin de certaines infos, les plus fiables possibles : il est donc préférable d'avoir un topo
      sous la main.
      Il n'est pas question de le recopier de fond en comble,
      <span class="text-red-700">ce site ne remplace pas un topo</span>.<br>
      Vous pouvez consulter les fiches falaises déjà présentes sur le site pour
      avoir des modèles, comme par exemple celle de <a href="/falaise.php?falaise_id=39">Pont de Barret</a>. <br>
      <i>Les champs obligatoires sont en noir, les champs optionnels en gris.</i>
    </div>

    <form method="post" action="ajout_falaise_db.php" enctype="multipart/form-data" class="flex flex-col gap-4"
      id="form">
      <input type="hidden" id="falaise_public" name="falaise_public" value="2" />
      <input type="hidden" id="admin" name="admin" value="0" />

      <div class="flex flex-col gap-1">
        <label class="form-control">
          <b>Nom de la falaise : </b>
          <input class="input input-primary input-sm" type="text" id="falaise_nom" name="falaise_nom" required
            oninput="formatNomFalaise(); verifierExistencefalaise();" />
        </label>
        <div class="flex flex-row gap-2 items-center admin">
          <div class="text-sm text-gray-400">Nom formaté:</div>
          <input tabindex="-1" class="input input-disabled input-xs" type="text" id="falaise_nomformate"
            name="falaise_nomformate" readonly>
        </div>
      </div>

      <div id="falaiseExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
        <svg class="w-4 h-4 mb-1 fill-current inline-block">
          <use xlink:href="/symbols/icons.svg#ri-error-warning-fill"></use>
        </svg>
        Une falaise avec ce nom existe déjà dans la base de données. Vérifiez
        que vous ne faites pas de doublon.
      </div>

      <div class="flex flex-col gap-2">
        <label class="form-control" for="falaise_latlng">
          <b>Coordonnées GPS ("latitude,longitude" - degrés décimaux) :</b>
          <input class="input input-primary input-sm" type="text" id="falaise_latlng" name="falaise_latlng"
            placeholder="ex: 45.1234,6.2355" required>
        </label>
        <div id="map" class="w-full h-64 rounded-lg relative" title="Cliquez pour placer la falaise">
          <div id="mapinstructions" class="h-full w-full bg-[#3333] flex items-center justify-center
            pointer-events-none z-[10000] absolute top-0 left-0 rounded-lg text-black text-xl">
            <span class="bg-[#fff8] rounded-lg px-2 py-1">Cliquez pour placer la falaise</span>
          </div>
        </div>
        <i class="text-slate-400 text-sm">
          Cliquez sur la carte pour placer la position. Les coordonnées doivent être sous la forme "45.1234,6.2355"
          par
          exemple (au moins 4 décimales).<br>
          Pour trouver les coordonnées GPS : sur la fiche Climbing Away de la falaise (bas de page, "plus de
          coordonnées", degrés décimaux), ou clic droit sur Google Maps, puis cliquer sur les coordonnées qui
          s'affichent pour les copier.<br>
          Il est conseillé de vérifier que les coordonnées correspondent bien à la falaise (par exemple en les copiant
          dans Google Maps, avec la couche "photos satellites").
        </i>
      </div>
      <script>
        const map = L.map('map').setView([45.1234, 3.2355], 5);
        L.tileLayer(
          "https://{s}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
          maxZoom: 19,
          minZoom: 0,
          attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
          crossOrigin: true,
        }).addTo(map);

        var marker = undefined;
        const size = 24;

        function createMarker(lat, lng) {
          mapinstructions.style.display = "none";
          if (marker) {
            map.removeLayer(marker);
          }
          marker = L.marker([lat, lng], {
            drag: true, icon: L.icon({
              iconUrl: "/images/icone_falaise_carte.png",
              iconSize: [size, size],
              iconAnchor: [size / 2, size],
            })
          }).addTo(map);
        }

        function updateMarker() {
          const coords = document.getElementById("falaise_latlng").value.split(',');
          if (coords.length === 2) {
            const lat = parseFloat(coords[0]);
            const lng = parseFloat(coords[1]);
            if (!isNaN(lat) && !isNaN(lng)) {
              createMarker(lat, lng);
              map.setView([lat, lng], 11);
            }
          }
        }
        map.on("click", function (e) {
          createMarker(e.latlng.lat, e.latlng.lng);
          document.getElementById("falaise_latlng").value = String(e.latlng.lat).slice(0, 8) + "," + String(e.latlng.lng).slice(0, 8);
        });

        document.getElementById("falaise_latlng").addEventListener("input", updateMarker);
        document.addEventListener("DOMContentLoaded", function () {
          const coords = document.getElementById("falaise_latlng").value.split(',');
          if (coords.length === 2) {
            const lat = parseFloat(coords[0]);
            const lng = parseFloat(coords[1]);
            if (!isNaN(lat) && !isNaN(lng)) {
              createMarker(lat, lng);
              map.flyTo([lat, lng], 11);
            }
          }
        });
      </script>

      <label class="form-control admin" for="falaise_zone">
        <b>Zone de la falaise :</b>
        <select class="select select-primary select-sm" name="falaise_zone" id="falaise_zone">
          <option value="-1" disabled selected>Choisis une zone</option>
          <?php foreach ($zones as $zone): ?>
            <option value="<?= $zone['zone_id'] ?>"><?= $zone['zone_nom'] ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="form-control" for="falaise_voies">
        <b>Voies - Texte descriptif :</b>
        <textarea class="textarea textarea-primary textarea-sm" id="falaise_voies" name="falaise_voies" rows="2"
          placeholder="ex : beaucoup de 6 et 7, quelques 5." required></textarea>
        <i class="text-slate-400 text-sm">
          Texte pour décrire les voies : nombre de voies, hauteur maximale, dire ici s'il y a plusieurs secteurs
          espacés ou non...<br>
          Exemple : "Environ 150 voies, hauteur maximale 35 mètres. Un secteur "grande face", et un secteur
          "initiation"
          assez distants".</i>
      </label>

      <label class="form-control" for="falaise_cottxt">
        <b>Cotations - Texte descriptif :</b>
        <textarea class="textarea textarea-primary textarea-sm" id="falaise_cottxt" name="falaise_cottxt" rows="2"
          placeholder="ex : Falaise intéressante pour les voies de 5a à 6b, quelques 4." required></textarea>
        <i class="text-slate-400 text-sm">
          Ecrivez un court texte décrivant les cotations (ex : "Falaise intéressante pour les voies de 5+ à 7-"). Vous
          pouvez ajouter des détails (ex : "10 voies dans le 5, 20 dans le 6,...").<br>
          Dans ce topo, on utilise la notation "6-" pour désigner les voies de 6a à 6b, et "6+" pour les voies de 6b+
          à
          6c+.
        </i>
      </label>

      <div>
        <div class="flex flex-row gap-4">
          <label class="form-control w-1/2" for="falaise_cotmin">
            <b>Cotation minimum :</b>
            <select class="select select-primary select-sm" required name="falaise_cotmin" id="falaise_cotmin">
              <option value="" disabled selected></option>
              <option value="4">4 et -</option>
              <option value="5-">5-</option>
              <option value="5+">5+</option>
              <option value="6-">6-</option>
              <option value="6+">6+</option>
              <option value="7-">7-</option>
              <option value="7+">7+</option>
              <option value="8-">8-</option>
              <option value="8+">8+</option>
              <option value="9-">9-</option>
              <option value="9+">9+</option>
            </select>
          </label>
          <label class="form-control w-1/2" for="falaise_cotmax">
            <b>Cotation maximum : </b>
            <select class="select select-primary select-sm" required name="falaise_cotmax" id="falaise_cotmax">
              <option value="" disabled selected></option>
              <option value="4">4 et -</option>
              <option value="5-">5-</option>
              <option value="5+">5+</option>
              <option value="6-">6-</option>
              <option value="6+">6+</option>
              <option value="7-">7-</option>
              <option value="7+">7+</option>
              <option value="8-">8-</option>
              <option value="8+">8+</option>
              <option value="9-">9-</option>
              <option value="9+">9+</option>
            </select>
          </label>
        </div>
        <i class="text-slate-400 text-sm">
          Ces deux champs n'apparaitront pas sur la fiche falaise, mais sont utilisés pour les filtres.<br>
          Ne pas mettre "8-" comme cotation max s'il n'y a que des voies dans le 6, et une seule voie dans le 8a par
          exemple.
        </i>
      </div>

      <label class="form-control" for="falaise_expotxt">
        <b>Exposition - Texte descriptif : </b>
        <textarea class="textarea textarea-primary textarea-sm" id="falaise_expotxt" name="falaise_expotxt" rows="1"
          placeholder="ex : surtout S, quelques O." required></textarea>
        <i class="text-slate-400 text-sm">
          Ecrivez un court texte décrivant l'exposition. Ex : "falaise orientée Sud à Sud-Est", "la plupart des voies
          orientées Ouest, quelques voies orientées Nord".<br>
          Option : ajouter ici si la falaise est abritée de la pluie, si le pied des voies est à l'ombre...
        </i>
      </label>

      <div>

        <div class="flex flex-row gap-4">
          <label class="form-control w-1/2 relative" for="falaise_exposhort1">
            <div><b>Exposition(s) principale(s) : </b></div>
            <multi-select id="falaise_exposhort1" name="falaise_exposhort1" required
              class="input input-primary input-sm mb-1" selected="items-center"
              selecteditem="p-1 badge badge-info text-white w-10 badge-sm m-1 cursor-pointer"
              dropdownitem="p-1 badge badge-info text-white m-1 w-12 cursor-pointer"
              dropdown="border bg-white w-96 text-xs flex flex-row flew-wrap items-center rounded-lg shadow-lg"
              selectallbutton="false" clearbutton="btn btn-xs p-1">
              <option value="'N'">N</option>
              <option value="'S'">S</option>
              <option value="'E'">E</option>
              <option value="'O'">O</option>
              <option value="'NE'">NE</option>
              <option value="'NO'">NO</option>
              <option value="'SE'">SE</option>
              <option value="'SO'">SO</option>
              <option value="'NNE'">NNE</option>
              <option value="'NNO'">NNO</option>
              <option value="'SSE'">SSE</option>
              <option value="'SSO'">SSO</option>
              <option value="'ENE'">ENE</option>
              <option value="'ESE'">ESE</option>
              <option value="'OSO'">OSO</option>
              <option value="'ONO'">ONO</option>
            </multi-select>
          </label>
          <label class="form-control w-1/2 relative" for="falaise_exposhort2">
            <div><b class="text-gray-400 opacity-70">Exposition(s) secondaire(s) [code] : </b></div>
            <multi-select id="falaise_exposhort2" name="falaise_exposhort2" class="input input-primary input-sm mb-1"
              selected="items-center" selecteditem="p-1 badge badge-info text-white w-10 badge-sm m-1 cursor-pointer"
              dropdownitem="p-1 badge badge-info text-white m-1 w-12 cursor-pointer"
              dropdown="border bg-white w-96 text-xs flex flex-row flew-wrap items-center rounded-lg shadow-lg"
              selectallbutton="false" clearbutton="btn btn-xs p-1">
              <option value="'N'">N</option>
              <option value="'S'">S</option>
              <option value="'E'">E</option>
              <option value="'O'">O</option>
              <option value="'NE'">NE</option>
              <option value="'NO'">NO</option>
              <option value="'SE'">SE</option>
              <option value="'SO'">SO</option>
              <option value="'NNE'">NNE</option>
              <option value="'NNO'">NNO</option>
              <option value="'SSE'">SSE</option>
              <option value="'SSO'">SSO</option>
              <option value="'ENE'">ENE</option>
              <option value="'ESE'">ESE</option>
              <option value="'OSO'">OSO</option>
              <option value="'ONO'">ONO</option>
            </multi-select>
          </label>
        </div>

        <div class="flex flex-row gap-6 items-center">
          <div class="w-1/2">
            <i class="text-slate-400 text-sm">
              Ces deux champs apparaitront dans la rose des vents sur la fiche falaise, et sont utilisés pour les
              filtres.<br>
              Le champ "exposition(s) secondaire(s)" est prévu pour le cas où il existe un petit nombre de voies avec
              une orientation différente des autres.
            </i>
          </div>

          <div class="w-1/2 flex justify-center">
            <img src="/images/rosedesvents.png" alt="Rose des vents" class="max-w-[200px]">
          </div>
        </div>
      </div>

      <label class="form-control" for="falaise_gvtxt">
        <span class="flex items-center gap-2">
          <b class="text-gray-400 opacity-70">Grandes voies - Texte descriptif :</b>
          <span class="text-red-600">champ à laisser vide s'il n'y a pas de grandes voies !</span>
        </span>
        <textarea class="textarea textarea-bordered textarea-sm" id="falaise_gvtxt" name="falaise_gvtxt" rows="2"
          placeholder="ex : 10 grandes voies, de PD+ à AD+."></textarea>
        <i class="text-slate-400 text-sm">
          Indiquez s'il y a des grandes voies, et si oui, combien environ, de combien à combien de longueurs, jusqu'à
          quelle hauteur max, éventuellement donner les cotations...
        </i>
      </label>

      <label class="form-control" for="falaise_gvnb">
        <b class="text-gray-400 opacity-70">Grandes voies - Texte très court pour le tableau :</b>
        <input class="input input-bordered input-sm" type="text" id="falaise_gvnb" name="falaise_gvnb"
          placeholder="ex : Plusieurs GV, 3 à 4 longueurs" maxlength="40">
        <i class="text-slate-400 text-sm">Texte très court pour le tableau "falaises proches de...".<br>
          Exemples : "Nombreuses GV - 2 à 10 longueurs" ; "GV en 2 à 3 longueurs" ; "12 GV - 4 à 9
          longueurs".
        </i>
      </label>

      <label class="form-control" for="falaise_bloc">
        <b class="text-gray-400 opacity-70">Falaise de bloc</b>
        <select id="falaise_bloc" name="falaise_bloc" class="select select-primary select-sm">
          <option value="0" selected>Non</option>
          <option value="1">Bloc</option>
          <option value="2">PsychoBloc 🌊</option>
        </select>
        <i class="text-slate-400 text-sm">À saisir uniquement si la falaise est un site de bloc ou de psychobloc (grimpe
          sans corde au dessus de l'eau, deep water solo)
        </i>
      </label>

      <label class="form-control" for="falaise_matxt">
        <b>Marche d'approche - Texte descriptif :</b>
        <textarea class="textarea textarea-primary textarea-sm" id="falaise_matxt" name="falaise_matxt" rows="3"
          placeholder="ex : 10' aller, 15' retour, montée raide." required></textarea>
        <i class="text-slate-400 text-sm">
          Petit texte décrivant la marche d'approche. Ex : "10' en montée", "10' aller, 7' retour",...
        </i>
      </label>

      <div>
        <b>Temps minimal de marche d'approche (minutes) :</b>
        <div class="flex flex-row gap-4">
          <label class="form-control w-1/2" for="falaise_maa">
            <b> Aller : </b>
            <input class="input input-primary input-sm" type="number" id="falaise_maa" name="falaise_maa"
              placeholder="ex : 10" required>
          </label>
          <label class="form-control w-1/2" for="falaise_mar">
            <b>Retour : </b>
            <input class="input input-primary input-sm" type="number" id="falaise_mar" name="falaise_mar"
              placeholder="ex : 5" required>
          </label>
        </div>
        <i class="text-slate-400 text-sm">
          Donner le temps de marche d'approche pour arriver au secteur le plus proche du parking vélo, aller et
          retour.
        </i>
      </div>

      <label class="form-control" for="falaise_topo">
        <b>Topo(s) :</b>
        <textarea class="textarea textarea-primary textarea-sm" id="falaise_topo" name="falaise_topo" rows="2"
          required></textarea>
        <i class="text-slate-400 text-sm">
          Lister les différents topos présentant la falaise.<br>
          Optionnel : ajouter un lien vers la fiche Climbing Away de la falaise. Pour cela, copiez le code &lt;a
          href="URL"&gt;Fiche Climbing Away&lt;/a&gt;, en remplaçant "URL" par l'URL de la fiche.<br>
          Exemple : "Escalade dans le Jura - &lt;a
          href="https://climbingaway.fr/fr/site-escalade/le-trou-de-la-lune"&gt;Fiche Climbing Away&lt;/a&gt;"
        </i>
      </label>


      <label class="form-control" for="falaise_rq">
        <b class="text-gray-400 opacity-70">Remarque(s) falaise :</b>
        <textarea class="textarea textarea-primary textarea-sm" id="falaise_rq" name="falaise_rq" rows="2"
          placeholder="ex : falaise abritée de la pluie."></textarea>
        <i class="text-slate-400 text-sm">A compléter si vous avez des informations additionnelles sur la falaise.</i>
      </label>


      <label class="form-control" for="falaise_voletcarto">
        <b>Bref descriptif de la falaise :</b>
        <textarea class="textarea textarea-primary textarea-sm" id="falaise_voletcarto" name="falaise_voletcarto"
          rows="3" placeholder="ex : falaise Sud avec des voies en dalle dans le 6-7." required
          maxlength="200"></textarea>
        <i class="text-slate-400 text-sm">Texte court et synthétique sur la falaise, qui apparaitra dans le volet qui
          s'ouvre quand on clique sur une
          falaise de la carte.<br>
          Ex : "Falaise exposée Sud, avec 120 voies de 6a à 7c. Quelques grandes voies en 2 ou 3 longueurs."</i>
      </label>

      <hr class="my-4">
      <h3 class="text-center">REMARQUES ET IMAGES OPTIONNELLES POUR LA FICHE FALAISE :</h3>

      <p>Ces remarques et images s'afficheront en bas des fiches falaises, dans le même ordre que les champs suivants
        (voir par exemple la fiche de <a href="/falaise.php?falaise_id=32">Cessens</a> pour avoir une idée) :</p>

      <div class="admin flex flex-col gap-4">

        <pre>NOM FALAISE</pre>

        <label class="form-control" for="falaise_fermee">
          <b class="text-gray-400 opacity-70">Si la falaise est fermée / interdite, explication :</b>
          <textarea class="textarea textarea-bordered textarea-sm" id="falaise_fermee" name="falaise_fermee" rows="2"
            placeholder="ex : Falaise interdite, en cours de conventionnement."></textarea>
          <i class="text-slate-400 text-sm">A compléter si vous avez des informations sur la cause de l'interdiction
            ou les perspectives de réouverture.</i>
        </label>

        <pre>TABLEAU DESCRIPTIF FALAISE</pre>

        <label class="form-control" for="falaise_txt2">
          <span>
            <b class="text-gray-400 opacity-70">Remarques diverses</b>.
            <span class="admin text-xs text-accent">[falaise_txt2]</span></span>
          <textarea class="textarea textarea-bordered textarea-sm" id="falaise_txt2" name="falaise_txt2"
            rows="3"></textarea>
          <i class="text-slate-400 text-sm">Remarques non incluses dans le tableau descriptif. Typiquement utilisé
            pour décrire les différents secteurs, les modalités de bivouac, camping.</i>
        </label>

        <pre>Menu déroulant des villes</pre>
        <pre>TABLEAUX DYNAMIQUES ITINERAIRES VILLE->FALAISE</pre>

        <label class="form-control" for="falaise_txt1">
          <span>
            <b class="text-gray-400 opacity-70">Remarque sur les itinéraires</b> (apparaitra entre le tableau des
            itinéraires et celui de la falaise). <span class="admin text-xs text-accent">[falaise_txt1]</span>
          </span>
          <textarea class="textarea textarea-bordered textarea-sm" id="falaise_txt1" name="falaise_txt1"
            rows="3"></textarea>
          <i class="text-slate-400 text-sm">Exemple: remarque optionnelle générale sur l’accès falaise, qui
            s’affiche quelle que soit la ville de
            départ</i>
        </label>

        <pre>Remarque optionnelle sur l’accès depuis la ville V (s’affiche si V est sélectionnée ;
champ rqvillefalaise_txt de la table rqvillefalaise).</pre>

        <pre>CARTE</pre>
      </div>

      <label class="form-control" for="falaise_img1">
        <b class="text-gray-400 opacity-70">Image optionnelle 1 :</b>
        <input class="file-input file-input-bordered file-input-sm" type="file" id="falaise_img1" name="falaise_img1"
          accept="image/*">
      </label>

      <label class="form-control" for="falaise_leg1">
        <span>
          <b class="text-gray-400 opacity-70">Légende image optionnelle 1</b>.
          <span class="admin text-xs text-accent">
            [falaise_leg1]
          </span>
        </span>
        <textarea class="textarea textarea-bordered textarea-sm" id="falaise_leg1" name="falaise_leg1"
          rows="2"></textarea>
      </label>

      <label class="form-control" for="falaise_txt3">
        <span>
          <b class="text-gray-400 opacity-70">Texte optionnel 1</b>.
          <span class="admin text-xs text-accent">[falaise_txt3]</span></span>
        <textarea class="textarea textarea-bordered textarea-sm" id="falaise_txt3" name="falaise_txt3"
          rows="5"></textarea>
      </label>

      <label class="form-control" for="falaise_img2">
        <b class="text-gray-400 opacity-70">Image optionnelle 2 :</b>
        <input class="file-input file-input-bordered file-input-sm" type="file" id="falaise_img2" name="falaise_img2"
          accept="image/*">
      </label>

      <label class="form-control" for="falaise_leg2">
        <span>
          <b class="text-gray-400 opacity-70">Légende image optionnelle 2</b>.
          <span class="admin text-xs text-accent">[falaise_leg2]</span></span>
        <textarea class="textarea textarea-bordered textarea-sm" id="falaise_leg2" name="falaise_leg2"
          rows="2"></textarea>
      </label>

      <label class="form-control" for="falaise_txt4">
        <span>
          <b class="text-gray-400 opacity-70">Texte optionnel 2</b>.
          <span class="admin text-xs text-accent">[falaise_txt4]</span></span>
        <textarea class="textarea textarea-bordered textarea-sm" id="falaise_txt4" name="falaise_txt4"
          rows="5"></textarea>
      </label>

      <label class="form-control" for="falaise_img3">
        <b class="text-gray-400 opacity-70">Image optionnelle 3 :</b>
        <input class="file-input file-input-bordered file-input-sm" type="file" id="falaise_img3" name="falaise_img3"
          accept="image/*">
      </label>

      <label class="form-control" for="falaise_leg3">
        <span>
          <b class="text-gray-400 opacity-70">Légende image optionnelle 3</b>.
          <span class="admin text-xs text-accent">[falaise_leg3]</span></span>
        <textarea class="textarea textarea-bordered textarea-sm" id="falaise_leg3" name="falaise_leg3"
          rows="2"></textarea>
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

      <button type="submit" class="btn btn-primary">AJOUTER LA FALAISE</button>
    </form>
  </main>
  <?php include "../components/footer.html"; ?>
</body>

<script>
  const falaises = <?= json_encode($falaises) ?>.map(n => n.toLowerCase().normalize("NFD"));
  const verifierExistencefalaise = () => {
    const falaiseNom = document.getElementById("falaise_nom").value;
    if (!falaiseNom) {
      document.getElementById("falaiseExistsAlert").classList.add("hidden");
      return;
    }
    const exists = falaises.includes(falaiseNom.toLowerCase().normalize("NFD").trim());
    if (exists) {
      document
        .getElementById("falaiseExistsAlert")
        .classList.remove("hidden");
    } else {
      document.getElementById("falaiseExistsAlert").classList.add("hidden");
    }
  };
</script>
<script>window.customElements.define('multi-select', MultiselectWebcomponent);</script>

</html>