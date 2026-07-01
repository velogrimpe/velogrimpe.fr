<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/map-bundle.php';
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
  <meta name="robots" content="noindex, nofollow" />
  <title><?= $falaise_id ? "Modifier" : "Ajouter" ?> une falaise - Vélogrimpe.fr</title>
  <!-- Map libraries bundle (Leaflet, Fullscreen, Locate) -->
  <?php map_bundle_js('map'); ?>
  <?php map_bundle_css('map'); ?>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Contrib storage -->
  <script src="/js/contrib-storage.js"></script>
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
        // Pre-fill contributor info from localStorage
        if (window.contribStorage) {
          window.contribStorage.prefillContribInputs();
        }
      <?php endif; ?>
      document.querySelectorAll(".input-disabled").forEach(e => e.value = "");
      // Attach form save listener for contrib info
      if (window.contribStorage) {
        window.contribStorage.attachFormSaveListener(document.getElementById('form'));
      }
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
      autocomplete="off" id="form">
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
                <b aria-label="intitulé du site d'escalade">Nom de la falaise</b>
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
        <div id="falaiseDuplicateAlert" class="hidden bg-warning/20 border border-red-900 text-red-900 p-2 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
            <use href="#error-warning-fill"></use>
          </svg> Une falaise avec ce nom existe déjà (<a id="linkDuplicatedFalaise"
            class="inline-flex items-center gap-1" target="_blank">
            <span> consulter la page de cette falaise </span>
            <svg class="w-4 h-4 fill-none stroke-current">
              <use href="#external-link"></use>
            </svg></a>) dans la base de données. Assurez vous de ne pas créer un doublon en vérifiant sa localisation
          sur la carte. Vous pouvez aussi <a id="linkEditFalaise" target="_blank"
            class="inline-flex items-center gap-1">la modifier en cliquant ici <svg
              class="w-4 h-4 fill-none stroke-current">
              <use href="#external-link"></use>
            </svg></a>.
        </div>
        <div id="falaiseExistsAlert" class="hidden bg-red-200 border border-red-900 text-red-900 p-2 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
            <use href="#error-warning-fill"></use>
          </svg> Une falaise avec ce nom existe déjà (<a id="linkSelectedFalaise" class="inline-flex items-center gap-1"
            target="_blank">
            <span> consulter la page de cette falaise </span>
            <svg class="w-4 h-4 fill-none stroke-current">
              <use href="#external-link"></use>
            </svg></a>) dans la base de données et a été vérouillée pour éviter la dégradation du topo. Si vous vous
          souhaitez modifier les données de la fiche falaise, merci de <a href="mailto:contact@velogrimpe.fr">contacter
            l'équipe velogrimpe</a> qui pourra vous ouvrir l'accès à la modification.
        </div>
        <div id="falaiseEditInfo" class="hidden bg-blue-100 border border-blue-900 text-blue-900 p-2 rounded-lg">
          <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
            <use href="#error-warning-fill"></use>
          </svg> Une falaise avec ce nom existe déjà. Les données connues sont pré-remplies ci-dessous, libre à vous de
          les modifier / compléter. Attention toutefois aux homonymes, vérifiez sa localisation. En cas d'erreur,
          recharger la page pour éviter de remplacer la falaise existante.
        </div>
        <div class="flex flex-col gap-2">
          <label class="form-control" for="falaise_latlng">
            <b>Coordonnées GPS <span class="text-error">(format : "latitude,longitude" (degrés décimaux))</span></b>
            <input class="input input-primary input-sm" type="text" id="falaise_latlng" name="falaise_latlng"
              placeholder="ex: 45.1234,6.2355" required autocomplete="off">
          </label>
          <div id="map" class="w-full h-64 rounded-lg relative" title="Cliquez pour placer la falaise">
            <div id="mapinstructions" class="h-full w-full bg-[#3333] flex items-center justify-center
              pointer-events-none z-[10000] absolute top-0 left-0 rounded-lg text-black text-xl">
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
          const opencyclemapTiles = L.tileLayer(
            "https://{s}.tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
            maxZoom: 19,
            minZoom: 0,
            attribution: '<a href="http://www.thunderforest.com/opencyclemap/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
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
            'OpenCycleMap': opencyclemapTiles,
            'IGNv2': ignTiles,
            'Satellite': ignOrthoTiles,
            'Outdoors': outdoorsTiles,
          };
          const map = L.map("map", {
            layers: [landscapeTiles], center: [45.1234, 3.2355], zoom: 5, fullscreenControl: true, zoomSnap: 0.5
          });
          L.control.locate().addTo(map);
          var layerControl = L.control.layers(baseMaps, undefined, { position: "topleft", size: 22 }).addTo(map);
          L.control.scale({ position: "bottomright", metric: true, imperial: false, maxWidth: 125 }).addTo(map);

          // Contrôle de recherche d'une localité via Nominatim (instance publique OSM).
          // Permet de centrer la carte sur une commune/lieu sans poser de marqueur.
          (function () {
            const SearchControl = L.Control.extend({
              options: { position: "topright" },
              onAdd: function () {
                const container = L.DomUtil.create("div", "leaflet-bar bg-base-100 rounded-md p-1 not-prose border-0");
                container.style.width = "230px";
                container.style.boxShadow = "0 1px 5px rgba(0,0,0,0.4)";
                // Positionnement et clipping en styles inline : les classes Tailwind
                // utilisées ici ne sont pas générées (chaîne JS dans un fichier PHP non scanné).
                container.innerHTML = `
                  <div style="position:relative;">
                    <input type="text" autocomplete="off" placeholder="Rechercher une localité…"
                      class="input input-bordered input-xs w-full" style="padding-right:1.5rem;"
                      aria-label="Centre la carte sur" />
                    <span data-role="spinner" class="text-slate-400"
                      style="position:absolute;right:.4rem;top:50%;transform:translateY(-50%);display:none;">
                      <span class="loading loading-spinner loading-xs"></span>
                    </span>
                    <div data-role="results" class="bg-base-100 border border-base-300"
                      style="position:absolute;left:0;right:0;top:100%;margin-top:.25rem;max-height:13rem;
                        overflow-y:auto;overflow-x:hidden;z-index:11000;display:none;border-radius:.375rem;
                        box-shadow:0 4px 12px rgba(0,0,0,.25);"></div>
                  </div>`;
                // Empêche les interactions sur le contrôle de se propager à la carte
                L.DomEvent.disableClickPropagation(container);
                L.DomEvent.disableScrollPropagation(container);

                const input = container.querySelector("input");
                const results = container.querySelector('[data-role="results"]');
                const spinner = container.querySelector('[data-role="spinner"]');
                let debounce = null;
                let lastController = null;
                let activeIndex = -1;

                function hideResults() {
                  results.style.display = "none";
                  results.innerHTML = "";
                  activeIndex = -1;
                }

                function showResults() {
                  results.style.display = "block";
                }

                function getOptions() {
                  return Array.from(results.querySelectorAll("button"));
                }

                function setActive(idx) {
                  const options = getOptions();
                  if (options.length === 0) return;
                  // Boucle sur les bornes
                  activeIndex = (idx + options.length) % options.length;
                  options.forEach((opt, i) => {
                    opt.style.backgroundColor = i === activeIndex ? "rgba(0,0,0,.08)" : "";
                    if (i === activeIndex) opt.scrollIntoView({ block: "nearest" });
                  });
                }

                function doSearch(query) {
                  if (lastController) lastController.abort();
                  lastController = new AbortController();
                  spinner.style.display = "inline-block";
                  const url = "https://nominatim.openstreetmap.org/search?format=jsonv2"
                    + "&limit=6&countrycodes=fr&addressdetails=1&q=" + encodeURIComponent(query);
                  fetch(url, {
                    signal: lastController.signal,
                    headers: { "Accept-Language": "fr" },
                  })
                    .then(r => r.ok ? r.json() : Promise.reject(new Error("nominatim failed")))
                    .then(items => {
                      spinner.style.display = "none";
                      if (!Array.isArray(items) || items.length === 0) {
                        results.innerHTML = '<div style="padding:.375rem .5rem;font-size:.75rem;" class="text-slate-400">Aucun résultat</div>';
                        showResults();
                        return;
                      }
                      results.innerHTML = "";
                      activeIndex = -1;
                      items.forEach(item => {
                        const a = document.createElement("button");
                        a.type = "button";
                        a.className = "text-left cursor-pointer";
                        a.style.cssText = "display:block;width:100%;max-width:100%;padding:.375rem .5rem;"
                          + "font-size:.75rem;line-height:1.25;white-space:nowrap;overflow:hidden;"
                          + "text-overflow:ellipsis;border:0;background:transparent;";
                        a.addEventListener("mouseenter", () => { a.style.backgroundColor = "rgba(0,0,0,.08)"; });
                        a.addEventListener("mouseleave", () => { a.style.backgroundColor = ""; });
                        a.textContent = item.display_name;
                        a.title = item.display_name;
                        a.addEventListener("click", function () {
                          const lat = parseFloat(item.lat);
                          const lng = parseFloat(item.lon);
                          if (!isNaN(lat) && !isNaN(lng)) {
                            if (item.boundingbox && item.boundingbox.length === 4) {
                              const bb = item.boundingbox.map(parseFloat);
                              map.fitBounds([[bb[0], bb[2]], [bb[1], bb[3]]], { maxZoom: 14 });
                            } else {
                              map.setView([lat, lng], 13);
                            }
                          }
                          input.value = "";
                          hideResults();
                        });
                        results.appendChild(a);
                      });
                      showResults();
                    })
                    .catch(err => {
                      spinner.style.display = "none";
                      if (err.name !== "AbortError") hideResults();
                    });
                }

                input.addEventListener("input", function () {
                  const query = input.value.trim();
                  if (debounce) clearTimeout(debounce);
                  if (query.length < 3) {
                    hideResults();
                    return;
                  }
                  // Débounce pour respecter la politique d'usage de Nominatim (1 req/s max)
                  debounce = setTimeout(() => doSearch(query), 600);
                });

                input.addEventListener("keydown", function (e) {
                  const isOpen = results.style.display !== "none" && getOptions().length > 0;
                  switch (e.key) {
                    case "ArrowDown":
                      if (!isOpen) return;
                      e.preventDefault();
                      setActive(activeIndex + 1);
                      break;
                    case "ArrowUp":
                      if (!isOpen) return;
                      e.preventDefault();
                      setActive(activeIndex - 1);
                      break;
                    case "Enter":
                      // Toujours empêcher la soumission du formulaire parent depuis ce champ
                      e.preventDefault();
                      if (!isOpen) return;
                      // Sélectionne l'option active, ou la première à défaut
                      (getOptions()[activeIndex >= 0 ? activeIndex : 0]).click();
                      break;
                    case "Escape":
                      input.value = "";
                      hideResults();
                      break;
                  }
                });

                return container;
              },
            });
            map.addControl(new SearchControl());
          })();
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
        <div class="form-control">
          <b>Type d'escalade</b>
          <div class="flex flex-wrap gap-x-4 gap-y-2">
            <div class="tooltip" data-tip="et grandes voies < 3 longueurs">
              <label class="label cursor-pointer flex items-center gap-2 p-0">
                <input type="radio" name="falaise_type_grimpe" class="radio radio-primary radio-sm" value="couenne"
                  id="falaise_type_couenne" checked />
                <span>Couenne</span>
              </label>
            </div>
            <label class="label cursor-pointer flex items-center gap-2 p-0">
              <input type="radio" name="falaise_type_grimpe" class="radio radio-primary radio-sm" value="bloc"
                id="falaise_type_bloc" />
              <span>Bloc</span>
            </label>
            <div class="tooltip" data-tip="Psychobloc : blocs engagés avec chute dans l'eau">
              <label class="label cursor-pointer flex items-center gap-2 p-0">
                <input type="radio" name="falaise_type_grimpe" class="radio radio-primary radio-sm" value="psychobloc"
                  id="falaise_type_psychobloc" />
                <span>Psychobloc</span>
              </label>
            </div>
          </div>

          <i class="text-slate-400 text-sm"> Remarque : Si plusieurs type d'escalade sur la falaise, indiquer le style
            prépondérant et préciser la répartition dans les descriptions.</i>

          <label class="label cursor-pointer flex items-center gap-2 p-0 mt-3">
            <input type="checkbox" class="checkbox checkbox-primary checkbox-sm" id="falaise_has_gv" />
            <span>Y a-t-il des grandes voies &ge; 3 longueurs ?</span>
          </label>
        </div>
        <input type="hidden" id="falaise_bloc" name="falaise_bloc" value="0" />
        <div>
          <div class="flex flex-col md:flex-row gap-4">
            <label class="form-control flex-1" for="falaise_cotmin">
              <b>Cotation minimum</b>
              <select class="select select-primary select-sm" required name="falaise_cotmin" id="falaise_cotmin">
                <option value="" disabled selected></option>
                <option value="4">4 et -</option>
                <option value="5-">5a à 5b</option>
                <option value="5+">5c à 5c+</option>
                <option value="6-">6a à 6b</option>
                <option value="6+">6b+ à 6c+</option>
                <option value="7-">7a à 7b</option>
                <option value="7+">7b+ à 7c+</option>
                <option value="8-">8a à 8b</option>
                <option value="8+">8b+ à 8c+</option>
                <option value="9-">9a à 9b</option>
                <option value="9+">9b+ et +</option>
              </select>
            </label>
            <label class="form-control flex-1" for="falaise_cotmax">
              <b>Cotation maximum</b>
              <select class="select select-primary select-sm" required name="falaise_cotmax" id="falaise_cotmax">
                <option value="" disabled selected></option>
                <option value="4">4 et -</option>
                <option value="5-">5a à 5b</option>
                <option value="5+">5c à 5c+</option>
                <option value="6-">6a à 6b</option>
                <option value="6+">6b+ à 6c+</option>
                <option value="7-">7a à 7b</option>
                <option value="7+">7b+ à 7c+</option>
                <option value="8-">8a à 8b</option>
                <option value="8+">8b+ à 8c+</option>
                <option value="9-">9a à 9b</option>
                <option value="9+">9b+ et +</option>
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
        <div id="falaise_gv_fields" class="hidden flex-col gap-4">
          <div class="bg-amber-100 border border-amber-700 text-amber-900 p-2 rounded-lg text-sm">
            <svg class="w-4 h-4 mb-1 fill-none stroke-current inline-block">
              <use href="#error-warning-fill"></use>
            </svg>
            Pour que la mention « Grandes voies » soit effective sur la fiche falaise, merci de bien renseigner les deux
            champs ci-dessous.
          </div>
          <div class="form-control">
            <b class="">Grandes voies - Texte descriptif. <span class="text-error">Important pour les GV !</span></b>
            <div class="vue-richtext" data-name="falaise_gvtxt"></div>
            <i class="text-slate-400 text-sm"> Indiquez s'il y a des grandes voies, et si oui, combien environ, de
              combien à combien de longueurs, jusqu'à quelle hauteur max, éventuellement donner les cotations... </i>
          </div>
          <label class="form-control" for="falaise_gvnb">
            <b class="">Grandes voies - Texte très court pour le tableau. <span class="text-error">Important pour les GV
                !</span></b>
            <input class="input input-primary input-sm" type="text" id="falaise_gvnb" name="falaise_gvnb"
              placeholder="ex : Plusieurs GV, 3 à 4 longueurs" maxlength="40" autocomplete="off">
            <i class="text-slate-400 text-sm">Texte très court pour le tableau "falaises proches de...".<br> Exemples :
              "Nombreuses GV - 2 à 10 longueurs" ; "GV en 2 à 3 longueurs" ; "12 GV - 4 à 9 longueurs". </i>
          </label>
        </div>
      </div>
      <script>
        (function () {
          const radios = document.querySelectorAll('input[name="falaise_type_grimpe"]');
          const blocInput = document.getElementById('falaise_bloc');
          const gvFields = document.getElementById('falaise_gv_fields');
          const gvCheckbox = document.getElementById('falaise_has_gv');
          const gvnb = document.getElementById('falaise_gvnb');
          const form = document.getElementById('form');

          function getSelectedType() {
            const r = Array.from(radios).find(r => r.checked);
            return r ? r.value : 'couenne';
          }

          function applyState() {
            const type = getSelectedType();
            const hasGv = gvCheckbox.checked;
            gvFields.classList.toggle('hidden', !hasGv);
            gvFields.classList.toggle('flex', hasGv);
            blocInput.value = type === 'psychobloc' ? '2'
              : type === 'bloc' ? '1'
                : '0';
          }

          radios.forEach(r => r.addEventListener('change', applyState));
          gvCheckbox.addEventListener('change', applyState);

          form.addEventListener('submit', () => {
            if (!gvCheckbox.checked) {
              if (window.setRichText) window.setRichText('falaise_gvtxt', '');
              gvnb.value = '';
            }
          });

          window.setFalaiseTypeGrimpe = function (type) {
            const radio = document.getElementById('falaise_type_' + type);
            if (radio) {
              radio.checked = true;
              applyState();
            }
          };

          window.setFalaiseHasGv = function (hasGv) {
            gvCheckbox.checked = !!hasGv;
            applyState();
          };
        })();
      </script>
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
      <!-- Partie Marche d'approche -->
      <div class="relative flex items-center">
        <hr class="my-0 grow border-[#2e8b57]" />
        <div class="flex items-center justify-center">
          <span class="px-2 text-primary italic bg-unset rounded-full">Marche d'approche</span>
        </div>
        <hr class="my-0 grow border-[#2e8b57]" />
      </div>
      <div class="flex flex-col gap-4 bg-base-100 p-4 rounded-lg border border-base-200 shadow-xs">
        <div class="form-control">
          <b>Marche d'approche - Texte descriptif</b>
          <div class="vue-richtext" data-name="falaise_matxt" <?= $admin ? '' : 'data-required="true"' ?>></div>
          <i class="text-slate-400 text-sm"> Petit texte décrivant la marche d'approche. Ex : "10' en montée", "10'
            aller, 7' retour",... </i>
        </div>
        <div>
          <b>Temps minimal de marche d'approche (minutes)</b>
          <div class="flex flex-col md:flex-row gap-4">
            <label class="form-control w-full md:w-1/2" for="falaise_maa">
              <b> Aller</b>
              <input class="input input-primary input-sm" type="number" id="falaise_maa" name="falaise_maa"
                placeholder="ex : 10" required autocomplete="off">
            </label>
            <label class="form-control w-full md:w-1/2" for="falaise_mar">
              <b>Retour</b>
              <input class="input input-primary input-sm" type="number" id="falaise_mar" name="falaise_mar"
                placeholder="ex : 5" required autocomplete="off">
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
        <div class="form-control">
          <b>Topo(s)</b>
          <div class="vue-richtext" data-name="falaise_topo" <?= $admin ? '' : 'data-required="true"' ?>></div>
          <i class="text-slate-400 text-sm"> Lister les différents topos présentant la falaise.<br> Optionnel : ajouter
            un lien vers la fiche Climbing Away de la falaise.
          </i>
        </div>
        <div class="form-control">
          <b class="">Remarque(s) falaise <span class="text-accent opacity-50">(optionnel)</span></b>
          <div class="vue-richtext" data-name="falaise_rq"></div>
          <i class="text-slate-400 text-sm">A compléter si vous avez des informations additionnelles sur la falaise.</i>
        </div>
        <div class="form-control">
          <b class="">Infos Hébergements <span class="text-accent opacity-50">(optionnel)</span></b>
          <div class="vue-richtext" data-name="falaise_hebergement"></div>
          <i class="text-slate-400 text-sm">Informations sur les possibilités d'hébergement à proximité (campings,
            refuges, gîtes...).</i>
        </div>
        <div class="form-control">
          <b class="">Accès en bus <span class="text-accent opacity-50">(optionnel)</span></b>
          <div class="vue-richtext" data-name="falaise_acces_bus"></div>
          <i class="text-slate-400 text-sm">Informations sur les accès en transports en commun (bus, navettes...) depuis
            la gare ou les villes proches.</i>
        </div>
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
          <div class="form-control">
            <b class="">Si la falaise est fermée / interdite, explication <span
                class="text-accent opacity-50">(optionnel)</span></b>
            <div class="vue-richtext" data-name="falaise_fermee"></div>
            <i class="text-slate-400 text-sm">A compléter si vous avez des informations sur la cause de l'interdiction
              ou les perspectives de réouverture.</i>
          </div>
          <pre>TABLEAU DESCRIPTIF FALAISE</pre>
          <div class="form-control">
            <span>
              <b class="">Remarques diverses <span class="text-accent opacity-50">(optionnel)</span></b>
              <span class="admin text-xs text-accent">[falaise_txt2]</span></span>
            <div class="vue-richtext" data-name="falaise_txt2"></div>
            <i class="text-slate-400 text-sm">Remarques non incluses dans le tableau descriptif. Typiquement utilisé
              pour décrire les différents secteurs, les modalités de bivouac, camping.</i>
          </div>
          <pre>Menu déroulant des villes</pre>
          <pre>TABLEAUX DYNAMIQUES ITINERAIRES VILLE->FALAISE</pre>
          <div class="form-control">
            <span>
              <b class="">Remarque sur les itinéraires <span class="text-accent opacity-50">(optionnel)</span></b>
              (apparaitra entre le tableau des itinéraires et celui de la falaise). <span
                class="admin text-xs text-accent">[falaise_txt1]</span>
            </span>
            <div class="vue-richtext" data-name="falaise_txt1"></div>
            <i class="text-slate-400 text-sm">Exemple: remarque optionnelle générale sur l’accès falaise, qui s’affiche
              quelle que soit la ville de départ</i>
          </div>
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
        <div class="form-control">
          <span>
            <b class="">Légende image 1 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent"> [falaise_leg1] </span>
          </span>
          <div class="vue-richtext" data-name="falaise_leg1"></div>
        </div>
        <div class="form-control">
          <span>
            <b class="">Texte 1 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent">[falaise_txt3]</span></span>
          <div class="vue-richtext" data-name="falaise_txt3"></div>
        </div>
        <label class="form-control" for="falaise_img2">
          <b class="">Image 2 <span class="text-accent opacity-50">(optionnel)</span></b>
          <input class="file-input file-file-input-sm" type="file" id="falaise_img2" name="falaise_img2"
            accept="image/*">
        </label>
        <img class="hidden w-full h-auto" id="falaise_img2_preview" src="" alt="Pas d'image 2" />
        <input hidden id="falaise_img2_webp" name="falaise_img2_webp" type="file" accept="image/*" />
        <div class="form-control">
          <span>
            <b class="">Légende image 2 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent">[falaise_leg2]</span></span>
          <div class="vue-richtext" data-name="falaise_leg2"></div>
        </div>
        <div class="form-control">
          <span>
            <b class="">Texte 2 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent">[falaise_txt4]</span></span>
          <div class="vue-richtext" data-name="falaise_txt4"></div>
        </div>
        <label class="form-control" for="falaise_img3">
          <b class="">Image 3 <span class="text-accent opacity-50">(optionnel)</span></b>
          <input class="file-input file-file-input-sm" type="file" id="falaise_img3" name="falaise_img3"
            accept="image/*">
        </label>
        <img class="hidden w-full h-auto" id="falaise_img3_preview" src="" alt="Pas d'image 3" />
        <input hidden id="falaise_img3_webp" name="falaise_img3_webp" type="file" accept="image/*" />
        <div class="form-control">
          <span>
            <b class="">Légende image 3 <span class="text-accent opacity-50">(optionnel)</span></b>
            <span class="admin text-xs text-accent">[falaise_leg3]</span></span>
          <div class="vue-richtext" data-name="falaise_leg3"></div>
        </div>
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
              <input class="grow" type="text" id="nom_prenom" name="nom_prenom" autocomplete="name"
                placeholder="Prénom (et/ou nom, surnom...)" required>
              <svg class="w-4 h-4 fill-none stroke-current">
                <use href="#user"></use>
              </svg>
            </label>
          </div>
          <div class="form-control grow">
            <b>Mail</b>
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
            <b>Message <span class="text-accent opacity-50">(optionnel)</span></b>
            <i>(si vous voulez commenter votre ajout de données)</i>
          </span>
          <textarea class="textarea textarea-sm leading-6" id="message" name="message" rows="4"></textarea>
        </label>
        <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/contrib-licence-notice.php"; ?>
        <div id="submitError"
          class="hidden items-start gap-2 bg-red-200 border border-red-900 text-red-900 p-3 rounded-lg">
          <svg class="w-5 h-5 mt-0.5 shrink-0 fill-none stroke-current">
            <use href="#error-warning-fill"></use>
          </svg>
          <span id="submitErrorMessage" class="whitespace-pre-line"></span>
        </div>
        <button type="submit" id="confirmButton" class="btn btn-primary"><?= $falaise_id ? "Modifier" : "Ajouter" ?> la
          falaise</button>
      </div>
    </form>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.php"; ?>
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
        if (window.setRichText) window.setRichText('falaise_topo', falaise.falaise_topo || '');
        if (window.setRichText) window.setRichText('falaise_matxt', falaise.falaise_matxt || '');
        document.getElementById("falaise_maa").value = falaise.falaise_maa;
        document.getElementById("falaise_mar").value = falaise.falaise_mar;
        if (window.setRichText) window.setRichText('falaise_gvtxt', falaise.falaise_gvtxt || '');
        document.getElementById("falaise_gvnb").value = falaise.falaise_gvnb || '';
        const blocVal = String(falaise.falaise_bloc ?? '0');
        const hasGv = (falaise.falaise_gvtxt && String(falaise.falaise_gvtxt).trim() !== '')
          || (falaise.falaise_gvnb && String(falaise.falaise_gvnb).trim() !== '');
        const typeGrimpe = blocVal === '1' ? 'bloc'
          : blocVal === '2' ? 'psychobloc'
            : 'couenne';
        if (window.setFalaiseTypeGrimpe) window.setFalaiseTypeGrimpe(typeGrimpe);
        if (window.setFalaiseHasGv) window.setFalaiseHasGv(hasGv);
        if (window.setRichText) window.setRichText('falaise_rq', falaise.falaise_rq || '');
        if (window.setRichText) window.setRichText('falaise_hebergement', falaise.falaise_hebergement || '');
        if (window.setRichText) window.setRichText('falaise_acces_bus', falaise.falaise_acces_bus || '');
        if (window.setRichText) window.setRichText('falaise_txt1', falaise.falaise_txt1 || '');
        if (window.setRichText) window.setRichText('falaise_txt2', falaise.falaise_txt2 || '');
        if (window.setRichText) window.setRichText('falaise_leg1', falaise.falaise_leg1 || '');
        if (window.setRichText) window.setRichText('falaise_txt3', falaise.falaise_txt3 || '');
        if (window.setRichText) window.setRichText('falaise_leg2', falaise.falaise_leg2 || '');
        if (window.setRichText) window.setRichText('falaise_txt4', falaise.falaise_txt4 || '');
        if (window.setRichText) window.setRichText('falaise_leg3', falaise.falaise_leg3 || '');
        if (window.setRichText) window.setRichText('falaise_fermee', falaise.falaise_fermee || '');
        document.getElementById("falaise_voletcarto").value = falaise.falaise_voletcarto;
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
  // fetchAndPrefillData is now called from the Vue module after mount
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
<script>
    // Soumission AJAX : en cas d'échec, le formulaire reste en place avec toutes
    // les données saisies (texte, rich-text, images, position). Pas de perte de données.
    (function () {
      const form = document.getElementById('form');
      const errorBox = document.getElementById('submitError');
      const errorMsg = document.getElementById('submitErrorMessage');
      let submitting = false;

      function clearError() {
        errorBox.classList.add('hidden');
        errorBox.classList.remove('flex');
        errorMsg.textContent = '';
      }

      function showError(message) {
        errorMsg.textContent = message;
        errorBox.classList.remove('hidden');
        errorBox.classList.add('flex');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      // Validation des champs custom obligatoires (rich-text + autocomplete + expo) :
      // chaque champ porte son propre `required` et affiche son erreur (cf. Vue).
      function validateRequiredFields() {
        const validator = window.validateFalaiseForm;
        if (typeof validator !== 'function') return true;
        if (validator()) return true;
        showError('Merci de remplir les champs obligatoires signalés en rouge ci-dessus.');
        return false;
      }

      form.addEventListener('submit', (e) => {
        e.preventDefault();
        // queueMicrotask : laisse les autres listeners "submit" synchrones (sauvegarde
        // contrib, reset GV...) mettre à jour les champs avant de construire le FormData.
        queueMicrotask(submitFalaise);
      });

      async function submitFalaise() {
        if (submitting) return;
        if (!validateRequiredFields()) return;
        submitting = true;
        clearError();
        // Bouton désactivé + spinner « Envoi en cours… » via l'utilitaire global.
        window.formSubmitUI?.setSubmitting(form);
        let succeeded = false;
        try {
          const resp = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          let data = null;
          try { data = await resp.json(); } catch (_) { /* réponse non-JSON */ }
          // Toute réponse 2xx est un succès : on ne doit jamais afficher d'erreur.
          if (resp.ok) {
            succeeded = true;
            // succès : redirection vers la confirmation (ou rechargement en dernier recours)
            window.location.href = (data && data.redirect) ? data.redirect : window.location.href;
            return; // on quitte en gardant le spinner pendant la navigation
          }
          const msg = (data && data.error)
            || `Une erreur est survenue (code ${resp.status}). Vos données sont conservées, vous pouvez réessayer.`;
          showError(msg);
        } catch (err) {
          showError("Erreur réseau : impossible d'envoyer le formulaire. Vos données sont conservées, vérifiez votre connexion et réessayez.");
        } finally {
          if (!succeeded) {
            submitting = false;
            window.formSubmitUI?.resetSubmitting(form);
          }
        }
      }
    })();
</script>
<script type="module" src="/dist/ajout-falaise.js"></script>

</html>