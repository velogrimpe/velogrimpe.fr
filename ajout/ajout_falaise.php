<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
$config = require $_SERVER['DOCUMENT_ROOT']
  . '/../config.php';
// Read the admin search parameter
$admin = ($_GET['admin'] ?? false) == $config["admin_token"];
$falaise_id = $_GET['falaise_id'] ?? null;

if ($falaise_id) {
  $falaises = [];
  if (!$admin) {
    $is_locked_stmt = $mysqli->prepare("SELECT falaise_id FROM falaises WHERE falaise_id = ? AND falaise_public = 4");
    $is_locked_stmt->bind_param("i", $falaise_id);
    $is_locked_stmt->execute();
    $is_locked = $is_locked_stmt->get_result()->num_rows > 0;
    if ($is_locked) {
      http_response_code(403);
      echo "<h1>Cette falaise est verrouillée</h1>";
      exit;
    }
  }
} else {
  $result_falaises = $mysqli->query("SELECT
  falaise_id, falaise_nom, falaise_latlng,
  case
  when falaise_public = 1 or falaise_public = 2 then 'existante'
  when falaise_public = 3 then 'à décrire'
  when falaise_public = 4 then 'verrouillée'
  else 'à compléter'
  end as status,
  falaise_nomformate
  FROM falaises f
  ORDER BY falaise_nom");
  $falaises = [];
  while ($row = $result_falaises->fetch_assoc()) {
    $falaises[] = [
      'nom' => $row['falaise_nom'],
      'id' => $row['falaise_id'],
      'latlng' => $row['falaise_latlng'],
      'status' => $row['status'],
      'nomformate' => $row['falaise_nomformate'],
    ];
  }
}

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $falaise_id ? "Modifier" : "Ajouter" ?> une falaise - Vélogrimpe.fr</title>
  <!-- Map libraries bundle (Leaflet, Fullscreen, Locate) -->
  <script src="/dist/map.js"></script>
  <link rel="stylesheet" href="/dist/map.css" />
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="stylesheet" href="/global.css" />
  <?php vite_css('ajout-falaise'); ?>
  <style>
    .admin {
      <?= !$admin ? 'display: none !important;' : '' ?>
    }

    .notadmin {
      <?= $admin ? 'display: none !important;' : '' ?>
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
        document.getElementById('nom_prenom').value = "<?= isset($_SERVER["REMOTE_USER"]) ? $_SERVER["REMOTE_USER"] : "Florent" ?>";
        document.getElementById('email').value = "<?= $config['contact_mail'] ?>";
      <?php else: ?>
        document.getElementById('falaise_public').value = '2';
        document.getElementById('admin').value = '0';
      <?php endif; ?>
      document.querySelectorAll(".input-disabled").forEach(e => e.value = "");
    });
  </script>
  <script>
    function formatNomFalaise(_nom) {
      const nom = _nom ?? document.getElementById("falaise_nom").value;
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
      document.getElementById("falaise_id").value = undefined;
    }
  </script>
</head>

<body class="min-h-screen flex flex-col">
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="w-full grow max-w-(--breakpoint-md) mx-auto prose p-4
              prose-pre:my-0 prose-pre:text-center prose-img:my-0">
    <h1 class="text-4xl font-bold text-wrap text-center">
      <?= $falaise_id ? "Modifier" : "Ajouter" ?> une falaise<span class='text-red-900 admin'> (version admin)</span>
    </h1>
    <div class="notadmin rounded-lg bg-base-300 p-4 my-6 border border-base-300 shadow-xs text-base-content">
      <b>Il s'agit ici d'ajouter une falaise au site web.</b><br> Commencez par vérifier qu'elle n'est pas déjà sur le
      site !<br> Vous allez avoir besoin de certaines infos, les plus fiables possibles : il est donc préférable d'avoir
      un topo sous la main. Il n'est pas question de le recopier de fond en comble, <span class="text-red-700">ce site
        ne remplace pas un topo</span>.<br> Vous pouvez consulter les fiches falaises déjà présentes sur le site pour
      avoir des modèles, comme par exemple celle de <a href="/falaise.php?falaise_id=39">Pont de Barret</a>. <br>
      <span class="text-red-700">Les champs obligatoires sont en noir, les champs optionnels en gris.</span>
    </div>
    <form method="post" action="/api/add_falaise.php" enctype="multipart/form-data" class="flex flex-col gap-4"
      id="form">
      <input type="hidden" id="admin" name="admin" value="0" />
      <!-- Partie Nom / Position / Zone (admin) -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Nom et localisation</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="flex flex-col gap-1">
          <div class="flex flex-col md:flex-row gap-2 justify-center md:items-center">
            <div class="relative not-prose z-11000 flex-1">
              <label class="form-control">
                <b>Nom de la falaise</b>
                <div id="vue-ajout-falaise"
                  data-falaises="<?= htmlspecialchars(json_encode($falaises), ENT_QUOTES, 'UTF-8') ?>"
                  data-admin="<?= $admin ? 'true' : 'false' ?>" <?php if ($falaise_id): ?>data-preset-falaise-id="<?= $falaise_id ?>" <?php endif; ?>>
                </div>
              </label>
            </div>
            <label class="admin form-control flex-1">
              <b>Statut</b>
              <select class="select select-primary select-sm" id="falaise_public" name="falaise_public">
                <option value="1">Validée (1)</option>
                <option value="2">À valider (2)</option>
                <option value="3">Hors Topo (3)</option>
                <option value="4">Verrouillée (4)</option>
              </select>
            </label>
          </div>
          <div class="flex flex-col md:flex-row gap-2 md:items-center admin">
            <div class="grow flex flex-row justify-between w-full gap-2">
              <div class="text-sm text-gray-400">Nom formaté:</div>
              <input tabindex="-1" class="input input-disabled input-xs w-48" type="text" id="falaise_nomformate"
                name="falaise_nomformate" readonly>
            </div>
            <div class="grow flex flex-row justify-between w-full gap-2">
              <div class="text-sm text-gray-400">ID:</div>
              <input tabindex="-1" class="input input-disabled input-xs w-48" type="text" id="falaise_id"
                name="falaise_id" readonly>
            </div>
          </div>
        </div>
        <div id="falaiseExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-current inline-block">
            <use xlink:href="/symbols/icons.svg#error-warning-fill"></use>
          </svg> Une falaise avec ce nom existe déjà (<a id="linkSelectedFalaise" class="inline-flex items-center gap-1"
            target="_blank">
            <span> consulter la page de cette falaise </span>
            <svg class="w-4 h-4 fill-current">
              <use xlink:href="/symbols/icons.svg#external-link"></use>
            </svg></a>) dans la base de données et a été vérouillée pour éviter la dégradation du topo. Si vous vous
          souhaitez modifier les données de la fiche falaise, merci de <a href="mailto:contact@velogrimpe.fr">contacter
            l'équipe velogrimpe</a> qui pourra vous ouvrir l'accès à la modification.
        </div>
        <div id="falaiseEditInfo" class="hidden bg-blue-100 border border-blue-900 text-blue-900 p-2 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-current inline-block">
            <use xlink:href="/symbols/icons.svg#error-warning-fill"></use>
          </svg> Une falaise avec ce nom existe déjà. Les données connues sont pré-remplies ci-dessous, libre à vous de
          les modifier / compléter. Attention toutefois aux homonymes, vérifiez sa localisation. En cas d'erreur,
          recharger la page pour éviter de remplacer la falaise existante.
        </div>
        <div class="flex flex-col gap-2">
          <label class="form-control" for="falaise_latlng">
            <b>Coordonnées GPS <span class="text-error">(format : "latitude,longitude" (degrés décimaux))</span></b>
            <input class="input input-primary input-sm" type="text" id="falaise_latlng" name="falaise_latlng"
              placeholder="ex: 45.1234,6.2355" required>
          </label>
          <div id="map" class="w-full h-64 rounded-lg relative" title="Cliquez pour placer la falaise">
            <div id="mapinstructions" class="h-full w-full bg-[#3333] flex items-center justify-center
              pointer-events-none z-10000 absolute top-0 left-0 rounded-lg text-black text-xl">
              <span class="bg-[#fff8] rounded-lg px-2 py-1 max-w-50 sm:max-w-full">Cliquez pour placer la falaise</span>
            </div>
          </div>
          <i class="text-slate-400 text-sm"> Cliquez sur la carte pour placer la position. Les coordonnées doivent être
            sous la forme "45.1234,6.2355" par exemple (au moins 4 décimales).<br> Pour trouver les coordonnées GPS :
            sur la fiche Climbing Away de la falaise (bas de page, "plus de coordonnées", degrés décimaux), ou clic
            droit sur Google Maps, puis cliquer sur les coordonnées qui s'affichent pour les copier.</i>
        </div>
        <script>

          const ignTiles = L.tileLayer(
            "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&FORMAT=image/png&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}", {
            maxZoom: 19,
            minZoom: 0,
            attribution: "IGN-F/Geoportail",
            crossOrigin: true,
          })
          const ignOrthoTiles = L.tileLayer(
            "https://data.geopf.fr/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}", {
            maxZoom: 18,
            minZoom: 0,
            tileSize: 256,
            attribution: "IGN-F/Geoportail",
            crossOrigin: true,
          })
          const landscapeTiles = L.tileLayer(
            "https://{s}.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
            maxZoom: 19,
            minZoom: 0,
            attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
            crossOrigin: true,
          })
          const outdoorsTiles = L.tileLayer(
            "https://{s}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
            maxZoom: 19,
            minZoom: 0,
            attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
            crossOrigin: true,
          })
          var baseMaps = {
            "Landscape": landscapeTiles,
            'IGNv2': ignTiles,
            'Satellite': ignOrthoTiles,
            'Outdoors': outdoorsTiles,
          };
          const map = L.map("map", {
            layers: [landscapeTiles], center: [45.1234, 3.2355], zoom: 5, fullscreenControl: true, zoomSnap: 0.5
          });
          L.control.locate().addTo(map);
          var layerControl = L.control.layers(baseMaps, undefined, { position: "topright", size: 22 }).addTo(map);
          L.control.scale({ position: "bottomright", metric: true, imperial: false, maxWidth: 125 }).addTo(map);
          fetch("/bdd/zones/zones.geojson")
            .then(r => r.ok ? r.json() : Promise.reject(new Error('zones load failed')))
            .then(zonesData => {
              const zonesLayer = L.geoJSON(zonesData, {
                style: {
                  color: "#ff7800",
                  dashArray: '3',
                  weight: 1,
                  opacity: 0.35,
                  fillOpacity: 0.2
                },
              });
              zonesLayer.eachLayer(function (layer) {
                if (layer.feature && layer.feature.properties && layer.feature.properties.name) {
                  layer.bindTooltip(`<b>${layer.feature.properties.name}</b>`, { sticky: true });
                }
              });
              layerControl.addOverlay(zonesLayer, "Zones");
            })
            .catch(() => { /* silent */ });
          <?= json_encode($falaises) ?>.map(f => {
            const coords = f.latlng.split(',');
            if (coords.length === 2) {
              const lat = parseFloat(coords[0]);
              const lng = parseFloat(coords[1]);
              if (!isNaN(lat) && !isNaN(lng)) {
                L.marker([lat, lng], {
                  icon: L.icon({
                    iconUrl: "/images/map/icone_falaise_carte.png",
                    iconSize: [18, 18],
                    iconAnchor: [9, 18],
                    className: "opacity-50"
                  }),
                }).addTo(map).bindPopup(f.nom, { offset: [0, -9] });
              }
            }
          })

          var marker = undefined;
          const size = 24;

          function createMarker(lat, lng) {
            mapinstructions.style.display = "none";
            if (marker) {
              map.removeLayer(marker);
            }
            marker = L.marker([lat, lng], {
              drag: true, icon: L.icon({
                iconUrl: "/images/map/icone_falaise_carte.png",
                iconSize: [size, size],
                iconAnchor: [size / 2, size],
              })
            }).addTo(map);
          }

          function updateZoneAndDepartment(lat, lng) {
            fetch(`/api/geocode.php?lat=${lat}&lng=${lng}`)
              .then(r => r.ok ? r.json() : Promise.reject(new Error('geocode failed')))
              .then(data => {
                const z = (data?.zone || '').trim();
                const dn = (data?.dept_name || '').trim();
                const dc = (data?.dept_code || '').trim();
                const zHidden = document.getElementById('falaise_zonename');
                if (zHidden) zHidden.value = z;
                const dnHidden = document.getElementById('falaise_deptname');
                if (dnHidden) dnHidden.value = dn;
                const dcHidden = document.getElementById('falaise_deptcode');
                if (dcHidden) dcHidden.value = dc;
              })
              .catch(() => { /* silent */ });
          }

          function updateMarker() {
            const coords = document.getElementById("falaise_latlng").value.split(',');
            if (coords.length === 2) {
              const lat = parseFloat(coords[0]);
              const lng = parseFloat(coords[1]);
              if (!isNaN(lat) && !isNaN(lng)) {
                createMarker(lat, lng);
                map.setView([lat, lng], 11);
                // Auto-fill zone and department via geocoding
                updateZoneAndDepartment(lat, lng);
              }
            } else {
              if (marker) {
                map.removeLayer(marker);
                marker = undefined;
              }
              mapinstructions.style.display = "flex";
            }
          }
          map.on("click", function (e) {
            createMarker(e.latlng.lat, e.latlng.lng);
            updateZoneAndDepartment(e.latlng.lat, e.latlng.lng);
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
          <div class="form-control">
            <b class="text-sm">Zone de la falaise (nom)</b>
            <input class="input input-disabled input-sm" type="text" id="falaise_zonename" name="falaise_zonename"
              readonly tabindex="-1" placeholder=" " />
            <i class="text-slate-400 text-sm">Renseigné automatiquement à partir des coordonnées GPS.</i>
          </div>
          <div class="form-control">
            <b class="text-sm">Département (code)</b>
            <input class="input input-disabled input-sm" type="text" id="falaise_deptcode" name="falaise_deptcode"
              readonly tabindex="-1" placeholder=" " />
          </div>
          <div class="form-control">
            <b class="text-sm">Département (nom)</b>
            <input class="input input-disabled input-sm" type="text" id="falaise_deptname" name="falaise_deptname"
              readonly tabindex="-1" placeholder=" " />
          </div>
        </div>
      </div>
      <!-- Partie Cotation / Nombre de voies -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Cotations et voies</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div>
          <div class="flex flex-col md:flex-row gap-4">
            <label class="form-control flex-1" for="falaise_cotmin">
              <b>Cotation minimum</b>
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
            <label class="form-control flex-1" for="falaise_cotmax">
              <b>Cotation maximum</b>
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
            <label class="form-control flex-1" for="falaise_nbvoies">
              <b>Nombre de voies</b>
              <select class="select select-primary select-sm" required name="falaise_nbvoies" id="falaise_nbvoies">
                <option value="" disabled selected></option>
                <option value="10">0 à 20 voies</option>
                <option value="20">environ 20 voies</option>
                <option value="35">20 à 50 voies</option>
                <option value="50">environ 50 voies</option>
                <option value="75">entre 50 et 100 voies</option>
                <option value="100">environ 100 voies</option>
                <option value="150">entre 100 et 200 voies</option>
                <option value="200">environ 200 voies</option>
                <option value="350">entre 200 et 500 voies</option>
                <option value="500">environ 500 voies</option>
                <option value="1000">plus de 500 voies</option>
              </select>
            </label>
          </div>
          <i class="text-slate-400 text-sm"> Remarques :<br> - Dans ce topo, on utilise la notation "6-" pour désigner
            les voies de 6a à 6b, et "6+" pour les voies de 6b+ à 6c+.<br> - Ne pas mettre "8-" comme cotation max s'il
            n'y a que des voies dans le 6, et une seule voie dans le 8a par exemple. </i>
        </div>
        <label class="form-control" for="falaise_cottxt">
          <b class="">Précisions sur les cotations <span class="text-accent opacity-50">(optionnel)</span></b>
          <textarea class="textarea textarea-sm leading-6" id="falaise_cottxt" name="falaise_cottxt" rows="2"
            placeholder="ex : Falaise surtout interessante pour les voies dans le 6-7. On compte 2 voies dans le 5, 15 dans le 6, et 12 dans le 7."></textarea>
          <i class="text-slate-400 text-sm"> Texte optionnel pour préciser les cotations (ex : "Falaise surtout
            interessante pour les voies dans le 6-7. On compte 2 voies dans le 5, 15 dans le 6, et 12 dans le 7").</i>
        </label>
        <label class="form-control" for="falaise_voies">
          <b>Précisions sur la falaise et les voies</b>
          <textarea class="textarea textarea-primary textarea-sm leading-6" id="falaise_voies" name="falaise_voies"
            rows="2" placeholder="ex : un secteur principal avec 54 voies et un secteur initiation avec 12 voies.
            Hauteur max 30 mètres. Pied des voies à l'ombre, beaucoup de voies sur réglettes." required></textarea>
          <i class="text-slate-400 text-sm"> Exemple d'infos que vous pouvez rentrer ici : <br> - La présence ou non de
            différents secteurs espacés.<br> - Nombre exact de voies.<br> - Hauteur max de la falaise.<br> - Pied des
            voies (confortable, à l'ombre...).<br> - Style des voies (dévers, réglettes...).<br> - ...</i>
        </label>
      </div>
      <!-- Partie Exposition -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Exposition</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <!-- Mobile: rose on top, Desktop: rose on right -->
        <div class="flex flex-col md:flex-row gap-4">
          <!-- Rose preview - first on mobile (order-first), last on desktop (md:order-last) -->
          <div class="flex flex-col items-center gap-1 md:order-last md:justify-center md:px-4">
            <div class="text-sm text-slate-500">Aperçu</div>
            <div id="vue-rose-preview"></div>
          </div>
          <!-- Exposition selects - stacked vertically -->
          <div class="flex flex-col gap-3 grow">
            <label class="form-control w-full relative">
              <div><b>Exposition(s) principale(s)</b></div>
              <div id="vue-exposhort1"></div>
            </label>
            <label class="form-control w-full relative">
              <div><b>Exposition(s) secondaire(s) <span class="text-accent opacity-50">(optionnel)</span></b></div>
              <div id="vue-exposhort2"></div>
            </label>
          </div>
        </div>
        <i class="text-slate-400 text-sm"> Ces deux champs apparaitront dans la rose des vents sur la fiche falaise, et
          sont utilisés pour les filtres. Le champ "exposition(s) secondaire(s)" est prévu pour le cas où il existe un
          petit nombre de voies avec une orientation différente. </i>
        <!-- Text description -->
        <label class="form-control" for="falaise_expotxt">
          <b>Précisions sur l'exposition</b>
          <textarea class="textarea textarea-primary textarea-sm leading-6" id="falaise_expotxt" name="falaise_expotxt"
            rows="1" placeholder="ex : majoritairement orienté Sud, quelques faces à l'Ouest" required></textarea>
          <i class="text-slate-400 text-sm"> Ecrivez un court texte décrivant l'exposition. Ex : "falaise orientée Sud à
            Sud-Est", "la plupart des voies orientées Ouest, quelques voies orientées Nord". </i>
        </label>
      </div>
      <!-- Partie Types de voies -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Type de grimpe</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="form-control" for="falaise_gvtxt">
          <span class="flex flex-col md:flex-row justify-center md:items-center gap-2">
            <b class="">Grandes voies - Texte descriptif <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="text-red-600">champ à laisser vide s'il n'y a pas de grandes voies !</span>
          </span>
          <textarea class="textarea textarea-sm leading-6" id="falaise_gvtxt" name="falaise_gvtxt" rows="2"
            placeholder="ex : 10 grandes voies, de PD+ à AD+."></textarea>
          <i class="text-slate-400 text-sm"> Indiquez s'il y a des grandes voies, et si oui, combien environ, de combien
            à combien de longueurs, jusqu'à quelle hauteur max, éventuellement donner les cotations... </i>
        </label>
        <label class="form-control" for="falaise_gvnb">
          <b class="">Grandes voies - Texte très court pour le tableau <span
              class="text-accent opacity-50">(optionnel)</span></b>
          <input class="input input-sm" type="text" id="falaise_gvnb" name="falaise_gvnb"
            placeholder="ex : Plusieurs GV, 3 à 4 longueurs" maxlength="40">
          <i class="text-slate-400 text-sm">Texte très court pour le tableau "falaises proches de...".<br> Exemples :
            "Nombreuses GV - 2 à 10 longueurs" ; "GV en 2 à 3 longueurs" ; "12 GV - 4 à 9 longueurs". </i>
        </label>
        <label class="form-control" for="falaise_bloc">
          <b class="">Falaise de bloc <span class="text-accent opacity-50">(optionnel)</span></b>
          <select id="falaise_bloc" name="falaise_bloc" class="select select-primary select-sm">
            <option value="0" selected>Non</option>
            <option value="1">Bloc</option>
            <option value="2">PsychoBloc 🌊</option>
          </select>
          <i class="text-slate-400 text-sm">À saisir uniquement si la falaise est un site de bloc ou de psychobloc
            (grimpe sans corde au dessus de l'eau, deep water solo) </i>
        </label>
      </div>
      <!-- Partie Marche d'approche -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Marche d'approche</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="form-control" for="falaise_matxt">
          <b>Marche d'approche - Texte descriptif</b>
          <textarea class="textarea textarea-primary textarea-sm leading-6" id="falaise_matxt" name="falaise_matxt"
            rows="3" placeholder="ex : 10' aller, 15' retour, montée raide." required></textarea>
          <i class="text-slate-400 text-sm"> Petit texte décrivant la marche d'approche. Ex : "10' en montée", "10'
            aller, 7' retour",... </i>
        </label>
        <div>
          <b>Temps minimal de marche d'approche (minutes)</b>
          <div class="flex flex-col md:flex-row gap-4">
            <label class="form-control w-full md:w-1/2" for="falaise_maa">
              <b> Aller</b>
              <input class="input input-primary input-sm" type="number" id="falaise_maa" name="falaise_maa"
                placeholder="ex : 10" required>
            </label>
            <label class="form-control w-full md:w-1/2" for="falaise_mar">
              <b>Retour</b>
              <input class="input input-primary input-sm" type="number" id="falaise_mar" name="falaise_mar"
                placeholder="ex : 5" required>
            </label>
          </div>
          <i class="text-slate-400 text-sm"> Donner le temps de marche d'approche pour arriver au secteur le plus proche
            du parking vélo, aller et retour. </i>
        </div>
      </div>
      <!-- Partie Infos supplémentaires -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Infos supplémentaires</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <label class="form-control" for="falaise_topo">
          <b>Topo(s)</b>
          <textarea class="textarea textarea-primary textarea-sm leading-6" id="falaise_topo" name="falaise_topo"
            rows="2" required></textarea>
          <i class="text-slate-400 text-sm"> Lister les différents topos présentant la falaise.<br> Optionnel : ajouter
            un lien vers la fiche Climbing Away de la falaise. Pour cela, copiez le code &lt;a href="URL"&gt;Fiche
            Climbing Away&lt;/a&gt;, en remplaçant "URL" par l'URL de la fiche.<br> Exemple : "Escalade dans le Jura -
            &lt;a href="https://climbingaway.fr/fr/site-escalade/le-trou-de-la-lune"&gt;Fiche Climbing Away&lt;/a&gt;"
          </i>
        </label>
        <label class="form-control" for="falaise_rq">
          <b class="">Remarque(s) falaise <span class="text-accent opacity-50">(optionnel)</span></b>
          <textarea class="textarea textarea-primary textarea-sm leading-6" id="falaise_rq" name="falaise_rq" rows="2"
            placeholder="ex : falaise abritée de la pluie."></textarea>
          <i class="text-slate-400 text-sm">A compléter si vous avez des informations additionnelles sur la falaise.</i>
        </label>
        <label class="form-control" for="falaise_voletcarto">
          <b>Résumé de la fiche falaise</b>
          <textarea class="textarea textarea-primary textarea-sm leading-6" id="falaise_voletcarto"
            name="falaise_voletcarto" rows="3"
            placeholder="ex : Falaise exposée Sud, avec 120 voies de 6a à 7c. Quelques grandes voies en 2 ou 3 longueurs."
            required maxlength="200"></textarea>
          <i class="text-slate-400 text-sm">Résumé court et synthétique sur la falaise, qui apparaitra dans le volet qui
            s'ouvre quand on clique sur une falaise de la carte.<br> Ex : "Falaise exposée Sud, avec 120 voies de 6a à
            7c. Quelques grandes voies en 2 ou 3 longueurs."</i>
        </label>
      </div>
      <!-- Partie Remarques et images -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Images et remarques optionnelles</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <p>Ces remarques et images s'afficheront en bas des fiches falaises, dans le même ordre que les champs suivants
          (voir par exemple la fiche de <a href="/falaise.php?falaise_id=32">Cessens</a> pour avoir une idée) :</p>
        <div class="admin flex flex-col gap-4">
          <pre>NOM FALAISE</pre>
          <label class="form-control" for="falaise_fermee">
            <b class="">Si la falaise est fermée / interdite, explication <span
                class="text-accent opacity-50">(optionnel)</span></b>
            <textarea class="textarea textarea-sm leading-6" id="falaise_fermee" name="falaise_fermee" rows="2"
              placeholder="ex : Falaise interdite, en cours de conventionnement."></textarea>
            <i class="text-slate-400 text-sm">A compléter si vous avez des informations sur la cause de l'interdiction
              ou les perspectives de réouverture.</i>
          </label>
          <pre>TABLEAU DESCRIPTIF FALAISE</pre>
          <label class="form-control" for="falaise_txt2">
            <span>
              <b class="">Remarques diverses <span class="text-accent opacity-50">(optionnel)</span></b>
              <span class="admin text-xs text-accent">[falaise_txt2]</span></span>
            <textarea class="textarea textarea-sm leading-6" id="falaise_txt2" name="falaise_txt2" rows="3"></textarea>
            <i class="text-slate-400 text-sm">Remarques non incluses dans le tableau descriptif. Typiquement utilisé
              pour décrire les différents secteurs, les modalités de bivouac, camping.</i>
          </label>
          <pre>Menu déroulant des villes</pre>
          <pre>TABLEAUX DYNAMIQUES ITINERAIRES VILLE->FALAISE</pre>
          <label class="form-control" for="falaise_txt1">
            <span>
              <b class="">Remarque sur les itinéraires <span class="text-accent opacity-50">(optionnel)</span></b>
              (apparaitra entre le tableau des itinéraires et celui de la falaise). <span
                class="admin text-xs text-accent">[falaise_txt1]</span>
            </span>
            <textarea class="textarea textarea-sm leading-6" id="falaise_txt1" name="falaise_txt1" rows="3"></textarea>
            <i class="text-slate-400 text-sm">Exemple: remarque optionnelle générale sur l’accès falaise, qui s’affiche
              quelle que soit la ville de départ</i>
          </label>
          <pre>Remarque optionnelle sur l’accès depuis la ville V (s’affiche si V est sélectionnée ;
champ rqvillefalaise_txt de la table rqvillefalaise).</pre>
          <pre>CARTE</pre>
        </div>
        <label class="form-control" for="falaise_img1">
          <b class="">Image 1 <span class="text-accent opacity-50">(optionnel)</span></b>
          <input class="file-input file-file-input-sm" type="file" id="falaise_img1" name="falaise_img1"
            accept="image/*">
        </label>
        <img class="hidden w-full h-auto" id="falaise_img1_preview" src="" alt="Pas d'image 1" />
        <input hidden id="falaise_img1_webp" name="falaise_img1_webp" type="file" accept="image/*" />
        <label class="form-control" for="falaise_leg1">
          <span>
            <b class="">Légende image 1 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent"> [falaise_leg1] </span>
          </span>
          <textarea class="textarea textarea-sm leading-6" id="falaise_leg1" name="falaise_leg1" rows="2"></textarea>
        </label>
        <label class="form-control" for="falaise_txt3">
          <span>
            <b class="">Texte 1 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent">[falaise_txt3]</span></span>
          <textarea class="textarea textarea-sm leading-6" id="falaise_txt3" name="falaise_txt3" rows="5"></textarea>
        </label>
        <label class="form-control" for="falaise_img2">
          <b class="">Image 2 <span class="text-accent opacity-50">(optionnel)</span></b>
          <input class="file-input file-file-input-sm" type="file" id="falaise_img2" name="falaise_img2"
            accept="image/*">
        </label>
        <img class="hidden w-full h-auto" id="falaise_img2_preview" src="" alt="Pas d'image 2" />
        <input hidden id="falaise_img2_webp" name="falaise_img2_webp" type="file" accept="image/*" />
        <label class="form-control" for="falaise_leg2">
          <span>
            <b class="">Légende image 2 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent">[falaise_leg2]</span></span>
          <textarea class="textarea textarea-sm leading-6" id="falaise_leg2" name="falaise_leg2" rows="2"></textarea>
        </label>
        <label class="form-control" for="falaise_txt4">
          <span>
            <b class="">Texte 2 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent">[falaise_txt4]</span></span>
          <textarea class="textarea textarea-sm leading-6" id="falaise_txt4" name="falaise_txt4" rows="5"></textarea>
        </label>
        <label class="form-control" for="falaise_img3">
          <b class="">Image 3 <span class="text-accent opacity-50">(optionnel)</span></b>
          <input class="file-input file-file-input-sm" type="file" id="falaise_img3" name="falaise_img3"
            accept="image/*">
        </label>
        <img class="hidden w-full h-auto" id="falaise_img3_preview" src="" alt="Pas d'image 3" />
        <input hidden id="falaise_img3_webp" name="falaise_img3_webp" type="file" accept="image/*" />
        <label class="form-control" for="falaise_leg3">
          <span>
            <b class="">Légende image 3 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent">[falaise_leg3]</span></span>
          <textarea class="textarea textarea-sm leading-6" id="falaise_leg3" name="falaise_leg3" rows="2"></textarea>
        </label>
      </div>
      <hr class="my-4">
      <?php if ($falaise_id): ?>
        <h3 class="text-center">Validation de la modification</h3>
      <?php else: ?>
        <h3 class="text-center">Validation de l'ajout de données</h3>
      <?php endif; ?>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="form-control grow">
            <?php if ($falaise_id): ?>
              <div><b>Modification par :</b><i class="text-sm text-slate-400">(ne figurera pas sur la fiche)</i></div>
            <?php else: ?>
              <b>Falaise ajoutée par</b>
            <?php endif; ?>
            <label for="nom_prenom" class="input input-primary input-sm flex items-center gap-2 w-full">
              <input class="grow" type="text" id="nom_prenom" name="nom_prenom"
                placeholder="Prénom (et/ou nom, surnom...)" required>
              <svg class="w-4 h-4 fill-current">
                <use xlink:href="/symbols/icons.svg#user"></use>
              </svg>
            </label>
          </div>
          <div class="form-control grow">
            <b>Mail</b>
            <label for="email" class="input input-primary input-sm flex items-center gap-2 w-full">
              <input class="grow" type="email" id="email" name="email" required>
              <svg class="w-4 h-4 fill-current">
                <use xlink:href="/symbols/icons.svg#mail"></use>
              </svg>
            </label>
          </div>
        </div>
        <label class="form-control" for="message">
          <span class="">
            <b>Message <span class="text-accent opacity-50">(optionnel)</span></b>
            <i>(si vous voulez commenter votre ajout de données)</i>
          </span>
          <textarea class="textarea textarea-sm leading-6" id="message" name="message" rows="4"></textarea>
        </label>
        <button type="submit" id="confirmButton" class="btn btn-primary"><?= $falaise_id ? "Modifier" : "Ajouter" ?> la
          falaise</button>
      </div>
    </form>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>
<script>
  function fetchAndPrefillData(id) {
    fetch(`/api/fetch_falaise.php?falaise_id=${id}`)
      .then(response => response.json())
      .then(falaise => {
        console.log('[prefill] falaise data:', falaise);
        console.log('[prefill] cotmin value:', falaise.falaise_cotmin);
        // Prefill zone and department when editing an existing falaise
        const zHidden = document.getElementById("falaise_zonename");
        if (zHidden) zHidden.value = falaise.falaise_zonename || '';
        const dnHidden = document.getElementById("falaise_deptname");
        if (dnHidden) dnHidden.value = falaise.falaise_deptname || '';
        const dcHidden = document.getElementById("falaise_deptcode");
        if (dcHidden) dcHidden.value = falaise.falaise_deptcode || '';
        document.getElementById("falaise_cottxt").value = falaise.falaise_cottxt;
        document.getElementById("falaise_cotmin").value = falaise.falaise_cotmin;
        document.getElementById("falaise_cotmax").value = falaise.falaise_cotmax;
        document.getElementById("falaise_expotxt").value = falaise.falaise_expotxt;
        // Use Vue setters for exposition multi-selects
        if (window.setExpo1Value) window.setExpo1Value(falaise.falaise_exposhort1 || '');
        if (window.setExpo2Value) window.setExpo2Value(falaise.falaise_exposhort2 || '');
        document.getElementById("falaise_voies").value = falaise.falaise_voies;
        document.getElementById("falaise_nbvoies").value = falaise.falaise_nbvoies;
        document.getElementById("falaise_topo").value = falaise.falaise_topo;
        document.getElementById("falaise_matxt").value = falaise.falaise_matxt;
        document.getElementById("falaise_maa").value = falaise.falaise_maa;
        document.getElementById("falaise_mar").value = falaise.falaise_mar;
        document.getElementById("falaise_gvtxt").value = falaise.falaise_gvtxt;
        document.getElementById("falaise_gvnb").value = falaise.falaise_gvnb;
        document.getElementById("falaise_rq").value = falaise.falaise_rq;
        document.getElementById("falaise_txt1").value = falaise.falaise_txt1;
        document.getElementById("falaise_txt2").value = falaise.falaise_txt2;
        document.getElementById("falaise_leg1").value = falaise.falaise_leg1;
        document.getElementById("falaise_txt3").value = falaise.falaise_txt3;
        document.getElementById("falaise_leg2").value = falaise.falaise_leg2;
        document.getElementById("falaise_txt4").value = falaise.falaise_txt4;
        document.getElementById("falaise_leg3").value = falaise.falaise_leg3;
        document.getElementById("falaise_fermee").value = falaise.falaise_fermee;
        document.getElementById("falaise_voletcarto").value = falaise.falaise_voletcarto;
        document.getElementById("falaise_bloc").value = falaise.falaise_bloc;
        document.getElementById("falaise_img1_preview").src = `https://www.velogrimpe.fr/bdd/images_falaises/${falaise.falaise_id}_${falaise.falaise_nomformate}_img1.webp`;
        document.getElementById("falaise_img2_preview").src = `https://www.velogrimpe.fr/bdd/images_falaises/${falaise.falaise_id}_${falaise.falaise_nomformate}_img2.webp`;
        document.getElementById("falaise_img3_preview").src = `https://www.velogrimpe.fr/bdd/images_falaises/${falaise.falaise_id}_${falaise.falaise_nomformate}_img3.webp`;
        document.getElementById("falaise_img1_preview").classList.remove("hidden");
        document.getElementById("falaise_img2_preview").classList.remove("hidden");
        document.getElementById("falaise_img3_preview").classList.remove("hidden");
        document.getElementById("falaise_latlng").value = falaise.falaise_latlng;
        document.getElementById("falaise_nomformate").value = falaise.falaise_nomformate;
        document.getElementById("falaise_id").value = falaise.falaise_id;
        // Use Vue setter for falaise name
        if (window.setFalaiseNom) window.setFalaiseNom(falaise.falaise_nom);
        document.getElementById("confirmButton").textContent = "Modifier la falaise";
        updateMarker();
      });
  }
  <?php if ($falaise_id): ?>
    fetchAndPrefillData(<?= $falaise_id ?>);
  <?php endif ?>
</script>
<script>
  const images = ["falaise_img1", "falaise_img2", "falaise_img3"];
  const resizeAndConvertImage = async (image) => {
    const file = document.getElementById(image).files[0];
    // Convert to webp and resize to maxwitdh = 1200px
    const reader = new FileReader();
    reader.onload = function (event) {
      const img = new Image();
      img.onload = function () {
        const canvas = document.createElement('canvas');
        const maxWidth = 1200;
        const scaleSize = maxWidth < img.width ? maxWidth / img.width : 1;
        canvas.width = maxWidth < img.width ? maxWidth : img.width;
        canvas.height = img.height * scaleSize;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
          const url = URL.createObjectURL(blob);
          document.getElementById(image + "_preview").src = url;
          document.getElementById(image + "_preview").classList.remove("hidden");
          const webpFile = new File([blob], file.name.split('.').slice(0, -1).join('.') + '.webp', {
            type: 'image/webp',
            lastModified: Date.now()
          });
          const dataTransfer = new DataTransfer();
          dataTransfer.items.add(webpFile);
          document.getElementById(image + "_webp").files = dataTransfer.files;
        }, 'image/webp', 0.8);
      }
      img.src = event.target.result;
    }
    reader.readAsDataURL(file);
  }
  images.forEach((image) => {
    document.getElementById(image).addEventListener("change", () => resizeAndConvertImage(image));
    // if image field already has a value, resize and show the preview
    if (document.getElementById(image).files.length > 0) {
      resizeAndConvertImage(image);
    }
  })

</script>
<script type="module" src="/dist/ajout-falaise.js"></script>

</html>