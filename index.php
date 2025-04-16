<?php
require_once "./database/velogrimpe.php";

$falaises = $mysqli->query("SELECT * FROM falaises WHERE falaise_public >= 1")->fetch_all(MYSQLI_ASSOC);
$villes = $mysqli->query("SELECT * FROM villes ORDER BY ville_nom")->fetch_all(MYSQLI_ASSOC);
$gares = $mysqli->query("SELECT
  g.*,
  GROUP_CONCAT(CONCAT(t.ville_id, '|', t.train_depart, '|', t.train_temps, '|', t.train_correspmin) SEPARATOR '=|=') AS villes
  FROM gares g
  LEFT JOIN train t ON t.gare_id = g.gare_id
  GROUP BY g.gare_id;"
)->fetch_all(MYSQLI_ASSOC);
$itineraires = $mysqli->query("SELECT * FROM velo WHERE velo_public >= 1")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js "></script>
  <link href=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css " rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/2.1.2/gpx.min.js" defer></script>
  <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
  <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css'
    rel='stylesheet' />
  <script src="https://unpkg.com/protomaps-leaflet@4.0.1/dist/protomaps-leaflet.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="./index.css" />
  <link rel="manifest" href="./site.webmanifest" />

</head>

<body>
  <?php include "./components/header.html"; ?>
  <main class="pb-4 px-2 md:px-8">
    <div class="definition-box">
      <strong>VÉLOGRIMPE :</strong> <em>activité consistant à combiner train et vélo pour aller grimper en
        falaise. <span class="hidden md:inline">En plus de privilégier une mobilité douce, le vélogrimpe donne
          l'occasion de vivre de petites
          aventures. Synonyme : escaladopédalage.
        </span></em>
    </div>


    <div class="flex flex-col gap-1">
      <div class="flex flex-row gap-1 justify-end md:hidden">

        <button class="btn btn-outline btn-primary btn-xs" onclick="searchModal.showModal()">
          Chercher
          <svg class="w-4 h-4 fill-current">
            <use xlink:href="/symbols/icons.svg#ri-search-line"></use>
          </svg>
        </button>
        <dialog id="searchModal" class="modal modal-bottom sm:modal-middle">
          <div class="modal-box md:w-3/5 max-w-xl">
            <form method="dialog">
              <button tabindex="-1" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <div id="searchFormDialogContainer" class="min-h-[200px] mt-4"></div>
          </div>
          <form method="dialog" class="modal-backdrop">
            <button>close</button>
          </form>
        </dialog>
        <button class="btn btn-outline btn-primary btn-xs"
          onclick="document.getElementById('filtersModal').showModal()">
          Filtrer
          <svg class="w-4 h-4 fill-current">
            <use xlink:href="/symbols/icons.svg#ri-filter-line"></use>
          </svg>
        </button>
        <dialog id="filtersModal" class="modal modal-bottom sm:modal-middle">
          <div class="modal-box md:w-4/5 max-w-3xl m-0 p-4">
            <form method="dialog">
              <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <!-- <h3 class="font-bold text-xl">Je cherche...</h3> -->
            <div id="filtersFormDialogContainer"></div>
            <form method="dialog" class="flex justify-end mt-4 gap-2">
              <button class="btn btn-sm btn-primary" onclick="">Appliquer et Fermer</button>
              <button class="btn btn-sm btn-error text-base-100" id="filtersFormResetMobile">Réinitialiser</button>
            </form>
          </div>
          <form method="dialog" class="modal-backdrop">
            <button>close</button>
          </form>
        </dialog>
      </div>
      <div class="flex flex-row gap-4">
        <div class="hidden md:flex w-[17rem] bg-base-100 rounded-lg p-4 shadow-lg text-sm flex-col gap-6">
          <div class="flex flex-col gap-2">
            <div id="searchFormPanelContainer">
              <div id="searchForm" class="relative">
                <label class="input input-primary input-sm flex items-center gap-2 w-full">
                  <input tabindex="0" type="search" id="search" class="w-full" placeholder="falaise/gare"
                    autocomplete="off" />
                  <svg class="w-4 h-4 fill-current">
                    <use xlink:href="/symbols/icons.svg#ri-search-line"></use>
                  </svg>
                </label>
                <ul id="search-list"
                  class="autocomplete-list absolute w-full bg-white border border-primary mt-1 hidden">
                </ul>
                <datalist id="garesetfalaises">
                  <?php foreach ($falaises as $falaise): ?>
                    <option value="<?= $falaise["falaise_nom"] ?> (falaise)"></option>
                  <?php endforeach; ?>
                  <?php foreach ($gares as $gare): ?>
                    <option value="<?= $gare["gare_nom"] ?> (gare)"></option>
                  <?php endforeach; ?>
                </datalist>
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-2">
            <div class="flex flex-row items-center justify-between">
              <div class="text-lg font-bold">Filtres</div>
              <button type="button" id="filtersFormReset" class="btn btn-xs btn-ghost text-primary">
                <svg class="w-3 h-3 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-repeat-line"></use>
                </svg>
                Réinitialiser
              </button>
            </div>
            <div id="filtersFormPanelContainer">
              <form class="flex flex-col gap-2" id="filtersForm">
                <div class="flex flex-col gap-2">
                  <div><b class="text-primary text-base">Falaise</b></div>
                  <div class="flex flex-col gap-2">
                    <div class="flex flex-col gap-2">
                      <div>&bull; Je veux une falaise exposée</div>
                      <div class="flex flex-row gap-1 items-center ml-4">
                        <div class="h-20 flex items-center w-3">
                          <div class="h-full bg-base-300 rounded-full w-1 relative">
                            <div class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 left-1/2
                                        bg-base-100 rounded-full w-6 h-6 border-2 border-base-300
                                        flex items-center justify-center text-xs text-slate-600 font-bold">OU</div>
                          </div>
                        </div>
                        <div class="max-w-96 grid grid-cols-[auto_auto] md:grid-cols-[auto] gap-x-2 md:gap-y-1">
                          <label class="label cursor-pointer justify-start gap-x-2 py-0">
                            <input type="checkbox" id="filterExpoN" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">Nord
                              <br class="md:hidden">
                              <span class="text-xs text-slate-400">(NO, N, NE)</span>
                            </span>
                          </label>
                          <label class="label cursor-pointer justify-start gap-x-2 py-0">
                            <input type="checkbox" id="filterExpoE" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">Est
                              <br class="md:hidden">
                              <span class="text-xs text-slate-400">(NE, E, SE)</span>
                            </span>
                          </label>
                          <label class="label cursor-pointer justify-start gap-x-2 py-0">
                            <input type="checkbox" id="filterExpoS" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">Sud
                              <br class="md:hidden">
                              <span class="text-xs text-slate-400">(SE, S, SO)</span>
                            </span>
                          </label>
                          <label class="label cursor-pointer justify-start gap-x-2 py-0">
                            <input type="checkbox" id="filterExpoO" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">Ouest
                              <br class="md:hidden">
                              <span class="text-xs text-slate-400">(SO, O, NO)</span>
                            </span>
                          </label>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-col gap-2">
                      <div>&bull; Je veux des cotations dans le
                        <br />
                        <span class="italic text-base-300 text-sm">(5- = de 5a à 5b, 5+ = de 5b+ à 5c+)</span>
                      </div>
                      <div
                        class="flex flex-row md:flex-col gap-3 items-center md:justify-center md:items-start ml-4 md:w-fit">
                        <div class="flex items-center h-16 md:h-full md:w-full w-3">
                          <div class="h-full md:w-full bg-base-300 rounded-full md:h-1 w-1 relative">
                            <div class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 left-1/2
                                        bg-base-100 rounded-full w-6 h-6 border-2 border-base-300
                                        flex items-center justify-center text-xs text-slate-600 font-bold">ET</div>
                          </div>
                        </div>
                        <div
                          class="max-w-96 md:flex flex-row grid grid-cols-[auto_auto_auto_auto] gap-x-[10px] gap-y-2 md:justify-between md:w-full">
                          <label class="label cursor-pointer md:flex-col gap-y-2 w-12 md:w-4 py-0">
                            <input type="checkbox" id="filterCot40" value="40"
                              class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">&le;4</span>
                          </label>
                          <label class="label cursor-pointer md:flex-col gap-y-2 w-12 md:w-4 py-0">
                            <input type="checkbox" id="filterCot50" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">5-</span>
                          </label>
                          <label class="label cursor-pointer md:flex-col gap-y-2 w-12 md:w-4 py-0">
                            <input type="checkbox" id="filterCot59" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">5+</span>
                          </label>
                          <label class="label cursor-pointer md:flex-col gap-y-2 w-12 md:w-4 py-0">
                            <input type="checkbox" id="filterCot60" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">6-</span>
                          </label>
                          <label class="label cursor-pointer md:flex-col gap-y-2 w-12 md:w-4 py-0">
                            <input type="checkbox" id="filterCot69" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">6+</span>
                          </label>
                          <label class="label cursor-pointer md:flex-col gap-y-2 w-12 md:w-4 py-0">
                            <input type="checkbox" id="filterCot70" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">7-</span>
                          </label>
                          <label class="label cursor-pointer md:flex-col gap-y-2 w-12 md:w-4 py-0">
                            <input type="checkbox" id="filterCot79" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">7+</span>
                          </label>
                          <label class="label cursor-pointer md:flex-col gap-y-2 w-12 md:w-4 py-0">
                            <input type="checkbox" id="filterCot80" class="checkbox checkbox-primary checkbox-sm" />
                            <span class="label-text">&ge;8</span>
                          </label>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row gap-4 items-center">
                      <div>&bull; Avec grandes voies</div>
                      <input type="checkbox" id="avecgv" class="checkbox checkbox-primary" />
                    </div>
                  </div>
                </div>
                <hr class="border-t border-base-300 my-1" />
                <div class="flex flex-col gap-2">
                  <div class="md:mt-2 flex flex-row gap-1">
                    <div><b class="text-primary text-base">Accès</b></div>
                    <div>depuis
                      <select id="villeSelect" class="select select-primary select-xs w-32">
                        <option value="-1">Choisir Ville</option>
                        <?php foreach ($villes as $ville): ?>
                          <option value="<?= $ville["ville_id"] ?>"><?= $ville["ville_nom"] ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="flex flex-col gap-2">
                    <div class="flex flex-row gap-2 items-center villeRequired opacity-30">
                      <div class="font-bold">&bull; Train (T)</div>
                      <div class="text-normal font-bold">&le;</div>
                      <input type="number" id="tempsMaxTrain" step="1" min="0"
                        class="input input-primary input-xs w-10" />
                      <div>min.</div>
                    </div>
                    <div class="flex flex-row items-center gap-1 ml-2 villeRequired opacity-30">
                      <div>Nb. Corresp.</div>
                      <div class="flex flex-row gap-2 items-center">
                        <label class="label cursor-pointer gap-1">
                          <input value="0" type="radio" name="nbCorrespMax" id="nbCorrespMax0"
                            class="radio radio-primary radio-xs" />
                          <span class="label-text">0</span>
                        </label>
                        <label class="label cursor-pointer gap-1">
                          <input value="1" type="radio" name="nbCorrespMax" id="nbCorrespMax1"
                            class="radio radio-primary radio-xs" />
                          <span class="label-text">&le;1</span>
                        </label>
                        <label class="label cursor-pointer gap-1">
                          <input value="10" type="radio" name="nbCorrespMax" id="nbCorrespMax10"
                            class="radio radio-primary radio-xs" checked hidden />
                          <svg id="nbCorrespMax10Reset" class="w-3 h-3 fill-current hidden">
                            <use xlink:href="/symbols/icons.svg#ri-repeat-line"></use>
                          </svg>
                        </label>
                      </div>
                    </div>
                    <div class="flex flex-row gap-2 items-center">
                      <div class="font-bold">&bull; Vélo (V)</div>
                      <div class="flex flex-row gap-6 items-center ml-4">
                        <div class="h-24 bg-base-300 rounded-full w-1 relative">
                          <div class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 left-1/2
                                        bg-base-100 rounded-full w-6 h-6 border-2 border-base-300
                                        flex items-center justify-center text-xs text-slate-600 font-bold">ET</div>
                        </div>
                        <div class="flex flex-col gap-1">
                          <div class="flex flex-row gap-2 items-center">
                            <div class="text-normal font-bold">&le;</div>
                            <input type="number" id="tempsMaxVelo" step="1" min="0"
                              class="input input-primary input-xs w-10" />
                            <div>min</div>
                          </div>
                          <div class="flex flex-row gap-2 items-center">
                            <div class="text-normal font-bold">&le;</div>
                            <input type="number" id="distMaxVelo" step="1" min="0"
                              class="input input-primary input-xs w-10" />
                            <div>km</div>
                          </div>
                          <div class="flex flex-row gap-2 items-center">
                            <div class="text-normal font-bold">&le;</div>
                            <input type="number" id="denivMaxVelo" step="1" min="0"
                              class="input input-primary input-xs w-10" />
                            <div>D+</div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="flex flex-row gap-2 items-center ml-2">
                      <div class="bg-base-100 rounded-full w-6 h-6 border-2 border-base-300
                                  flex items-center justify-center text-xs text-slate-600 font-bold">OU</div>
                      <input type="checkbox" id="apieduniquement" class="checkbox checkbox-primary" />
                      <div>Accessible à pied</div>
                    </div>
                    <div class="flex flex-row gap-2 items-center">
                      <div class="font-bold">&bull; Approche (A)</div>
                      <div class="text-normal font-bold">&le;</div>
                      <input type="number" id="tempsMaxMA" step="1" min="0" class="input input-primary input-xs w-10" />
                      <div>min.</div>
                    </div>
                  </div>
                  <div
                    class="flex flex-row gap-2 items-center mt-2 border border-base-300 rounded-md p-2 villeRequired opacity-30">
                    <div class="font-bold underline uppercase">Temps Total</div>
                    <div class="flex flex-col gap-2 items-end">
                      <div class="flex flex-row gap-1 items-center">
                        <div class="">T+V</div>
                        <div class="text-normal font-bold">&le;</div>
                        <input type="number" id="tempsMaxTV" step="1" min="0"
                          class="input input-primary input-xs w-10" />
                        <div>min.</div>
                      </div>
                      <div class="flex flex-row gap-1 items-center">
                        <div class="">T+V+A</div>
                        <div class="text-normal font-bold">&le;</div>
                        <input type="number" id="tempsMaxTVA" step="1" min="0"
                          class="input input-primary input-xs w-10" />
                        <div>min.</div>
                      </div>
                    </div>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div id="map" class="w-full md:w-[calc(100%-17rem)] h-[calc(100dvh-80px)]"></div>
      </div>
    </div>
  </main>
</body>
<script>

  function isSamsungInternet() {
    return navigator.userAgent.includes("SamsungBrowser");
  }

  // Paramètres généraux
  const iconSize = 30;
  const defaultMarkerSize = iconSize;
  const selectedGareSize = iconSize * 1.5;
  const itinerairesColors = ["indianRed", "tomato", "teal", "paleVioletRed", "mediumSlateBlue", "lightSalmon", "fireBrick", "crimson", "purple", "hotPink", "mediumOrchid"]
  const falaiseIcon = (size, closed) =>
    L.icon({
      iconUrl: closed ? "/images/icone_falaisefermee_carte.png" : "/images/icone_falaise_carte.png",
      iconSize: [size, size],
      iconAnchor: [size / 2, size],
    });
  const trainIcon = (size = 24) => {
    return L.icon({
      iconUrl: "/images/icone_train_carte.png",
      className: "train-icon" + (size === 24 ? " bgwhite" : " bgblue"),
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2],
    });
  };

  function format_time(minutes) {
    if (minutes === null) {
      return "";
    }
    const hours = Math.floor(minutes / 60);
    const remaining_minutes = minutes % 60;

    if (hours > 0) {
      return `${hours}h${remaining_minutes.toString().padStart(2, "0")}`;
    } else {
      return `${remaining_minutes}&apos;`;
    }
  }
  const calculate_time = (it) => {
    const { velo_km, velo_dplus, velo_apieduniquement } = it;
    let time_in_hours;
    if (velo_apieduniquement == "1") {
      time_in_hours = parseFloat(velo_km) / 4 + parseInt(velo_dplus) / 500;
    } else {
      time_in_hours = parseFloat(velo_km) / 20 + parseInt(velo_dplus) / 500;
    }
    const time_in_minutes = Math.round(time_in_hours * 60);
    return time_in_minutes;
  }

  const gpx_path = (it) => {
    return (
      it.velo_id + "_" + it.velo_depart + "_" + it.velo_arrivee + "_" + (it.velo_varianteformate || "") + ".gpx"
    )
  }
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

  var itinerairesLines = []
  var selected = null;
  const teardown = () => {
    if (selected !== null && selected.type === "falaise" && selected.filteredOut) {
      map.removeLayer(selected.marker);
    }
    selected = null;
    itinerairesLines.forEach((line) => {
      map.removeLayer(line);
    });
    itinerairesLines = [];
    // Restore normal tooltips for gares and falaises
    gares.forEach((gare) => {
      if (gare.type === "gare") {
        gare.marker?.setIcon(trainIcon());
        // gare.marker?.closeTooltip();
        gare.marker?.unbindTooltip();
        gare.marker?.bindTooltip(gare.gare_nom, {
          className: "p-[1px]",
          direction: "right",
          offset: [iconSize / 2, 0],
        });
      }
    });
    falaises.forEach((falaise) => {
      if (falaise.type === "falaise") {
        // falaise.marker?.closeTooltip();
        falaise.marker?.unbindTooltip();
        falaise.marker?.bindTooltip(falaise.falaise_nom, {
          className: "p-[1px]",
          direction: "right",
          offset: [iconSize / 2, 0],
        });
      }
    });
  };

  function renderGpx(it, c) {
    const lopts = { weight: 5, color: c };
    const options = {
      async: true,
      markers: {
        startIcon: null,
        endIcon: null,
      },
      polyline_options: lopts,
    };
    return new L.GPX("./bdd/gpx/" + gpx_path(it), options)
      .addTo(map)
      .on('loaded', e => {
        e.target.bindTooltip(format_time(calculate_time(it)),
          {
            className: `p-[1px] bg-[${c}] text-white border-[${c}] font-bold`,
            permanent: true,
            direction: "center",
          });
        e.target.on('mouseover', e => {
          e.originalEvent.target.ownerSVGElement.appendChild(e.originalEvent.target);
          e.target.eachLayer((l) => l.setStyle({ weight: 10, color: c }))
        });
        e.target.on('mouseout', e => {
          e.target.eachLayer((l) => l.setStyle(lopts))
        });
        e.target.on('click', e => {
          L.DomEvent.stopPropagation(e);
        });
      }
      );
  }

  function setFalaiseMarker(falaise, map, mode) {
    const initMarker = () => {
      const marker = L.marker(
        falaise.falaise_latlng.split(","),
        {
          icon: falaiseIcon(defaultMarkerSize, falaise.falaise_fermee),
          riseOnHover: true,
          autoPanOnFocus: true,
        }
      ).addTo(map);
      falaise.marker = marker;
      marker.bindTooltip(falaise.falaise_nom, {
        className: "p-[1px]",
        direction: "right",
        offset: [iconSize / 2, -iconSize / 2],
      });
      marker.on("click", function (e) {
        if (selected === null || selected.falaise_id !== falaise.falaise_id) {
          map.addLayer(marker);
          e.originalEvent?.stopPropagation();
          teardown();
          selected = falaise;
          info.update();
          // use falaise coords and gare coords to set bounds
          const bounds = [
            falaise.falaise_latlng.split(",").map(parseFloat),
            ...falaise.access.map(it => it.gare.gare_latlng.split(",").map(parseFloat))
          ];
          map.flyToBounds(bounds, { paddingTopLeft: [0, 40], paddingBottomRight: [0, 200], duration: 0.5 });

          //Affichage des itinéraire vélo/à pied
          setTimeout(() => falaise.access.map((it, i) => {
            const c = itinerairesColors[i % itinerairesColors.length];
            const gpx = renderGpx(it, c);
            itinerairesLines.push(gpx);
            const station = gares.find(g => g.gare_id === it.gare.gare_id);
            // Afficher les noms des gares qui donnent accès à cette falaise
            station.marker?.unbindTooltip();
            station.marker?.bindTooltip(station.gare_nom, {
              direction: "right",
              offset: [iconSize / 2, 0],
              permanent: true,
              className: `rounded-md bg-[${c}] border-[${c}] text-white px-[1px] py-0 before:border-r-[${c}]`,
            });
          }), 0.76 * 1000);
        } else {
          map.flyTo(falaise.falaise_latlng.split(","), 15, { duration: 0.25 });
          marker.closeTooltip();
        }
        marker.unbindTooltip();
        marker.bindTooltip(falaise.falaise_nom, {
          className: "p-[1px]",
          direction: "top",
          permanent: true,
          offset: [0, -iconSize],
        });
      });
    }

    // If mode did not change : do nothing
    if (falaise.displayMode === mode) {
      return;
    }
    falaise.displayMode = mode;
    // Clear old marker when mode changed
    if (!falaise.marker) {
      initMarker();
    }
    // Depending on mode: size, opacity, tooltip, onMap (remove layer)
    const setIconAndTooltip = (size, direction, permanent = false) => {
      falaise.marker.setIcon(falaiseIcon(size, falaise.falaise_fermee));
      falaise.marker.unbindTooltip();
      falaise.marker.bindTooltip(falaise.falaise_nom, {
        className: "p-[1px]",
        direction,
        offset: direction === "right" ? [size / 4, -size / 2] : direction === "top" ? [0, -size] : [size / 2, 0],
        permanent,
      });
    };
    switch (mode) {
      case "normal":
        falaise.marker.setOpacity(1);
        setIconAndTooltip(defaultMarkerSize, "right");
        return;
      case "reduced":
        falaise.marker.setOpacity(1);
        setIconAndTooltip(20, "right");
        return;
      case "faded":
        falaise.marker.setOpacity(0.5);
        setIconAndTooltip(24, "right");
        return;
      case "hidden":
        map.removeLayer(falaise.marker);
        return;
    }
  }

  function setFalaiseHTMarker(falaise, map, mode) {
    if (falaise.displayMode === mode) return;
    if (falaise.displayMode !== undefined && falaise.marker) {
      map.removeLayer(falaise.marker);
    }
    const marker = L.marker(
      falaise.falaise_latlng.split(","),
      {
        icon: falaiseIcon(20, falaise.falaise_fermee),
        opacity: 0.75,
        riseOnHover: true,
        autoPanOnFocus: true,
      }
    ).addTo(map);
    falaise.marker = marker;
    falaise.displayMode = mode;
    marker.bindPopup(
      `<div class="flex flex-col gap-1">`
      + `<div class="uppercase text-slate-400">hors topo</div>`
      + `<div class="text-sm font-bold">${falaise.falaise_nom}</div>`
      + `${falaise.falaise_fermee ? `<div class="text-error">${falaise.falaise_fermee.replace(/\n/g, "<br>")}</div>` : ""}`,
      { offset: [0, -10] }
    );
    if (mode === "hidden") {
      falaise.displayMode = mode;
      map.removeLayer(falaise.marker);
      return;
    }
  }
  function setGareMarker(gare, map, mode) {
    if (gare.displayMode === mode) return;
    if (gare.displayMode !== undefined && gare.marker) {
      map.removeLayer(gare.marker);
    }
    const marker = L.marker(
      gare.gare_latlng.split(","),
      {
        icon: trainIcon(),
        riseOnHover: true,
        autoPanOnFocus: true,
      }
    ).addTo(map);
    marker.unbindTooltip();
    marker.bindTooltip(gare.gare_nom, {
      className: "p-[1px]",
      direction: "right",
      offset: [iconSize / 2, 0],
    });
    gare.marker = marker;
    gare.displayMode = mode;
    // Gares avec itinéraires = Gares du Topo
    marker.on("click", function (e) {
      e.originalEvent?.stopPropagation();
      e.target.openTooltip();
      teardown();
      selected = gare;
      info.update();
      // use falaise coords and gare coords to set bounds
      const bounds = [
        gare.gare_latlng.split(",").map(parseFloat),
        ...gare.access.map(it => it.falaise.falaise_latlng.split(",").map(parseFloat))
      ];
      map.flyToBounds(bounds, { maxZoom: 12, paddingTopLeft: [0, 50], paddingBottomRight: [50, 0], duration: 0.5 });
      e.target.setIcon(trainIcon(selectedGareSize));

      setTimeout(() => gare.access.map((it, i) => {
        const c = itinerairesColors[i % itinerairesColors.length];
        if (falaises.find(f => f.falaise_id === it.falaise.falaise_id)?.filteredOut) return;
        const options = {
          async: true,
          markers: {
            startIcon: null,
            endIcon: null,
          },
          polyline_options: {
            weight: 5,
            color: c,
          },
        };
        // Afficher les noms des falaises accessibles depuis cette gare
        const falaise = falaises.find(f => f.falaise_id === it.falaise.falaise_id);
        falaise.marker?.unbindTooltip();
        falaise.marker?.bindTooltip(falaise.falaise_nom, {
          direction: "right",
          permanent: true,
          offset: [iconSize / 2, -iconSize / 2],
          className: `rounded-md bg-[${c}] border-[${c}] text-white px-[1px] py-0 before:border-r-[${c}]`,
        });
        const gpx = renderGpx(it, c);
        itinerairesLines.push(gpx);
      }), 0.76 * 1000);
      marker.unbindTooltip();
      marker.bindTooltip(gare.gare_nom, {
        className: "p-[1px]",
        direction: "top",
        permanent: true,
        offset: [0, -iconSize / 2],
      });
    });
    if (mode === "hidden") {
      gare.displayMode = mode;
      map.removeLayer(gare.marker);
      return;
    }
    return marker;
  }
  function setGareHTMarker(gare, map, mode, zoom) {
    const radius = !zoom ? undefined : zoom < 9 ? 2 : zoom < 10 ? 3 : 4;
    if (gare.displayMode === mode && mode !== "zoom") return;
    if (mode === "zoom" && gare.displayMode === "zoom") {
      gare.marker.setRadius(radius);
    }
    if (gare.displayMode !== undefined && gare.marker) {
      map.removeLayer(gare.marker);
      delete gare.marker;
    }
    if (mode === "hidden") {
      gare.displayMode = mode;
      return;
    }
    const marker = L.circleMarker(
      gare.gare_latlng.split(",").map(parseFloat),
      {
        radius,
        stroke: true,
        color: "#fff",
        weight: 1,
        fill: true,
        fillColor: "black",
        fillOpacity: 1,
      }
    ).addTo(map);
    marker.bindPopup(gare.gare_nom);
    gare.displayMode = mode;
    gare.marker = marker
    return marker;
  }
</script>

<script>
  const center = [45.391, 5.420]
  const zoom = 6.5;
  // Récupération des données
  const falaisesBase = <?php echo json_encode($falaises); ?>;
  const itineraires = <?php echo json_encode($itineraires); ?>.map(it => ({ ...it, tempsVelo: calculate_time(it) }));
  const garesBase = <?php echo json_encode($gares); ?>.map(g => {
    g.villes = (g.villes || "")
      .split("=|=")
      .map(v => {
        const [ville_id, ville, durStr, nCorresp] = v.split("|"); return { ville_id, ville, temps: parseInt(durStr), nCorresp: parseInt(nCorresp) }
      });
    return g;
  });
  const falaises = falaisesBase.map(f => {
    const access = itineraires.filter(i => i.falaise_id === f.falaise_id).map(it => {
      const gare = garesBase.find(g => g.gare_id === it.gare_id);
      const villes = gare.villes.map(v => {
        const tempsTrainVelo = v.temps + it.tempsVelo;
        const tempsTotal = tempsTrainVelo + (f.maa || 0);
        return { ...v, tempsTrainVelo, tempsTotal };
      });
      return { ...it, gare, villes };
    }).sort((a, b) => a.tempsVelo - b.tempsVelo);
    return { ...f, access }
  })
  const gares = garesBase.map(g => {
    const access = itineraires.filter(i => i.gare_id === g.gare_id).map(it => {
      const falaise = falaisesBase.find(f => f.falaise_id === it.falaise_id);
      return { ...it, falaise }
    }).sort((a, b) => a.tempsVelo - b.tempsVelo);
    return { ...g, access }
  })

  var map = L.map("map", {
    layers: [landscapeTiles], center, zoom, fullscreenControl: true, zoomSnap: 0.5
  });
  var layerControl = L.control.layers(baseMaps, undefined, { position: "topleft", size: 22 }).addTo(map);
  L.control.scale({ position: "bottomright", metric: true, imperial: false, maxWidth: 125 }).addTo(map);

  // PANNEAU D'INFORMATION SUR LA FALAISE/GARE SELECTIONNEE
  var info = L.control({ position: 'topright' });

  info.onAdd = function (map) {
    this._div = L.DomUtil.create('div', 'info'); // create a div with a class "info"
    this.top = "";
    this.bot = "";
    L.DomEvent.disableClickPropagation(this._div);
    L.DomEvent.disableScrollPropagation(this._div);
    this.update();
    return this._div;
  };
  // method that we will use to update the control based on feature properties passed
  info.update = function () {
    const mode = selected === null ? undefined : selected.type;
    const nFalaises = falaises.filter(f => (f.type === "falaise")).length;
    const nFalaiseFiltered = falaises.filter(f => (f.type === "falaise") && !f.filteredOut).length;
    const updateTop = () => {
      if (nFalaises !== nFalaiseFiltered) {
        this.top = (`<div class="flex gap-1 items-center font-bold text-primary border-b border-base-300 pb-1 mb-1">`
          + `<svg class="w-4 h-4 fill-current"><use xlink:href="/symbols/icons.svg#ri-filter-line"></use></svg>`
          + ` ${nFalaiseFiltered} falaises correspondent aux filtres</div>`)
      } else {
        this.top = ""
      }
    }
    const updateBot = () => {
      switch (mode) {
        case undefined:
          this.bot = (
            `<div class="flex flex-col gap-1 max-w-96 items-center">`
            + `<div>Cliquez sur une falaise pour voir ses informations</div>`
            + (
              isSamsungInternet() ? (
                '<div>Vous utilisez Samsung Internet : '
                + 'si vous êtes en mode sombre, désactivez-le ou changez de navigateur '
                + 'pour un affichage correct de la carte.</div>')
                : ""
            )
            + `</div>`
          );
          break;
        case "falaise":
          this.bot = `<div class="flex flex-col gap-1 max-w-96">`
            + '<div class="flex flex-col md:flex-row justify-between items-center gap-4">'
            + `<h3 class="text-xl font-bold"><a href="/falaise.php?falaise_id=${selected.falaise_id}">${selected.falaise_nom}</a></h3>`
            + `<a class="btn btn-primary btn-xs text-base-100! hover:text-base-100!"
          href="/falaise.php?falaise_id=${selected.falaise_id}">Voir la fiche falaise</a>`
            + `</div>`
            + (
              selected.falaise_fermee
                ? `<p class="text-wrap text-error">${selected.falaise_fermee.replace(/\n/g, "<br>") || ""}</p>`
                : `<p class="text-wrap">${selected.falaise_voletcarto.replace(/\n/g, "<br>") || ""}</p>`
            )
            + `<details><summary><i>Liste des accès</i></summary>`
            + "<ul>"
            + selected.access.map((it, i) => (
              `<li class="relative ml-8">` +
              `<div class="absolute top-[6px] -left-2 w-6 h-1 -translate-x-full ${itinerairesColors[i % itinerairesColors.length]}"></div>` +
              `<div><b>${it.gare.gare_nom} (${format_time(calculate_time(it))})</b> : ` +
              `${it.velo_km} km, ${it.velo_dplus} D+${it.velo_apieduniquement === "1" ? " (à pied)" : ""}</div>` +
              `</li>`)).join("")
            + "</ul></details>"
            + `</div>`;
          break;
        case "gare":
          this.bot = `<div class="flex flex-col gap-1 max-w-96">`
            + `<h3 class="text-xl font-bold">Gare de ${selected.gare_nom}</h3>`
            + `<details><summary><i>Falaises accessibles depuis la gare</i></summary>`
            + "<ul>"
            + selected.access.map((it, i) => (
              `<li class="relative ml-8">`
              + `<div class="absolute top-2 -left-2 w-6 h-1 -translate-x-full ${itinerairesColors[i % itinerairesColors.length]}"></div>`
              + `<div>`
              + `<a class="link" href="/falaise.php?falaise_id=${it.falaise.falaise_id}">${it.falaise.falaise_nom}</a>`
              + ` : <b>${format_time(calculate_time(it))}</b> ${it.velo_apieduniquement === "1" ? "à pied" : "à vélo"} (${it.velo_km}
              km, ${it.velo_dplus} D+).`
              + `</div>`
              + `</li>`)
            ).join("")
            + "</ul></details>"
            + `</div>`;
          break;
      }
    }
    updateTop();
    updateBot();
    this._div.innerHTML = this.top + this.bot;
    if (window.innerWidth >= 768) {
      // details should be open by default on desktop
      this._div.querySelectorAll("details").forEach((details) => details.open = true);
    }
  };
  info.addTo(map);

  function renderFalaises() {
    const zoom = map.getZoom();
    falaises.map((falaise) => {
      if (!falaise.falaise_latlng) return;
      if (falaise.access.length === 0) {
        falaise.type = "falaise_hors_topo";
        if (zoom < 11 || falaise.filteredOut) {
          setFalaiseHTMarker(falaise, map, "hidden");
        } else {
          setFalaiseHTMarker(falaise, map, "normal");
        }
      } else {
        falaise.type = "falaise";
        if (falaise.filteredOut) {
          if (falaise === selected) {
            setFalaiseMarker(falaise, map, "faded");
          }
          else {
            setFalaiseMarker(falaise, map, "hidden");
          }
          return;
        }
        if (falaise.falaise_fermee) {
          if (zoom < 11) {
            setFalaiseMarker(falaise, map, "hidden");
          } else {
            setFalaiseMarker(falaise, map, "normal");
          }
        } else {
          if (zoom < 9) {
            setFalaiseMarker(falaise, map, "reduced");
          } else {
            setFalaiseMarker(falaise, map, "normal");
          }
        }
      }
    });
  }
  renderFalaises();

  map.on("click", function (e) {
    if (selected) {
      teardown();
      info.update();
      map.setZoom(Math.max(7, map.getZoom() - 3), { animate: true, duration: 0.5 });
    }
  });

  function renderGares() {

    const zoom = map.getZoom();
    const { _northEast: { lat: neLat, lng: neLng }, _southWest: { lat: swLat, lng: swLng } } = map.getBounds();
    gares.forEach((gare) => {
      if (!gare.gare_latlng) return;
      if (gare.access.length === 0) {
        gare.type = "gare_hors_topo";
        // setGareHTMarker(gare, map, "zoom", map.getZoom());
      } else {
        gare.type = "gare";
      }
      if (gare.type === "gare_hors_topo") {
        if (zoom >= 11) {
          const [lat, lng] = gare.gare_latlng.split(",").map(parseFloat);
          if (lat < neLat && lat > swLat && lng < neLng && lng > swLng) {
            setGareHTMarker(gare, map, "zoom", zoom);
          } else {
            setGareHTMarker(gare, map, "hidden");
          }
        } else {
          setGareHTMarker(gare, map, "hidden");
        }
      } else {
        if (zoom < 9) {
          setGareMarker(gare, map, "hidden");
        } else {
          if (!gare.marker || gare.displayMode === "hidden") {
            setGareMarker(gare, map, "normal");
          } else {
            gare.marker?.setIcon(trainIcon());
          }
        }
      }
    });
  }
  renderGares();

  map.on("zoomend", (e) => {
    renderFalaises();
    renderGares();
  });

  map.on("moveend", (e) => {
    renderGares();
  });

  //  --- Ajout des lignes de train ---
  const paintRules = [
    // {
    //   dataLayer: "fr",
    //   symbolizer: new protomapsL.LineSymbolizer({
    //     color: "#fff",
    //     width: (z) => (z <= 6 ? 1 : z < 9 ? 1.5 : 2),
    //   })
    // },
    {
      dataLayer: "fr",
      symbolizer: new protomapsL.LineSymbolizer({
        color: "#000",
        width: (z) => (z <= 6 ? 0.5 : z < 9 ? 1 : 1.5),
        // dash: [5, 5],
      })
    }
  ]
  var layer = protomapsL.leafletLayer({ url: '/bdd/trains/trainlines.pmtiles', paintRules, maxDataZoom: 16, pane: "overlayPane" })
  layer.addTo(map);

</script>

<script>
  // ============================================ RECHERCHE ============================================

  const baseList = [
    ...falaises.map(f => ({ id: f.falaise_id, type: "falaise", name: f.falaise_nom, item: f })),
    ...gares.map(g => ({ id: g.gare_id, type: "gare", name: g.gare_nom, item: g }))
  ];
  const searchByNameHandler = (value) => {
    document.getElementById("searchModal").close();
    document.getElementById("map").scrollIntoView({ behavior: "smooth", block: "nearest" });
    const q = value;
    const filtered = baseList.find(item => (
      item.name === q
      || (q.includes(" (falaise)") && item.type === "falaise" && item.name === q.replace(" (falaise)", ""))
      || (q.includes(" (gare)") && item.type === "gare" && item.name === q.replace(" (gare)", ""))
    ));
    if (filtered) {
      if (filtered.item.type === "falaise_hors_topo") {
        setFalaiseHTMarker(filtered.item, map, "normal");
        map.flyTo(filtered.item.falaise_latlng.split(",").map(parseFloat), 12, { duration: 0.5 });
        return;
      }
      else if (filtered.item.type === "gare_hors_topo") {
        map.flyTo(filtered.item.gare_latlng.split(",").map(parseFloat), 11, { duration: 0.5 });
        setTimeout(() => filtered.item.marker?.openPopup(), 1000);
        return;
      }
      filtered.item.marker?.fire("click");
    }
  }

  // ============================================ FILTRES ============================================
  const resetButton = document.getElementById("filtersFormReset");
  const resetButtonMobile = document.getElementById("filtersFormResetMobile");
  resetButton.addEventListener("click", (e) => {
    document.querySelectorAll(".villeRequired").forEach(e => e.classList.add("opacity-30"));
    document.querySelectorAll(".villeRequired input").forEach(e => e.disabled = true);
  });
  resetButtonMobile.addEventListener("click", (e) => {
    document.querySelectorAll(".villeRequired").forEach(e => e.classList.add("opacity-30"));
    document.querySelectorAll(".villeRequired input").forEach(e => e.disabled = true);
  });
  const falaisesDuTopo = falaises.filter(f => f.access.length > 0);
  const falaisesHorsTopo = falaises.filter(f => f.access.length === 0);
  const filterHandler = () => {
    const expoN = document.getElementById("filterExpoN").checked;
    const expoE = document.getElementById("filterExpoE").checked;
    const expoS = document.getElementById("filterExpoS").checked;
    const expoO = document.getElementById("filterExpoO").checked;
    const cot40 = document.getElementById("filterCot40").checked;
    const cot50 = document.getElementById("filterCot50").checked;
    const cot59 = document.getElementById("filterCot59").checked;
    const cot60 = document.getElementById("filterCot60").checked;
    const cot69 = document.getElementById("filterCot69").checked;
    const cot70 = document.getElementById("filterCot70").checked;
    const cot79 = document.getElementById("filterCot79").checked;
    const cot80 = document.getElementById("filterCot80").checked;
    const avecgv = document.getElementById("avecgv").checked;
    const apieduniquement = document.getElementById("apieduniquement").checked;
    const tempsMaxVelo = document.getElementById("tempsMaxVelo").value;
    const distMaxVelo = document.getElementById("distMaxVelo").value;
    const denivMaxVelo = document.getElementById("denivMaxVelo").value;
    const tempsMaxTrain = document.getElementById("tempsMaxTrain").value;
    const nbCorrespMax0 = document.getElementById("nbCorrespMax0").checked;
    const nbCorrespMax1 = document.getElementById("nbCorrespMax1").checked;
    const nbCorrespMax = nbCorrespMax0 ? 0 : nbCorrespMax1 ? 1 : 10;
    const tempsMaxMA = document.getElementById("tempsMaxMA").value;
    const tempsMaxTV = document.getElementById("tempsMaxTV").value;
    const tempsMaxTVA = document.getElementById("tempsMaxTVA").value;
    const ville = document.getElementById("villeSelect").value;
    const villeSelected = ville !== "-1";
    //
    const expoFiltered = [expoN, expoE, expoS, expoO].some(e => e);
    const cotFiltered = [cot40, cot50, cot59, cot60, cot69, cot70, cot79, cot80].some(e => e);

    // Case 1 : all default values --> set all falaises visible (even hors topo)
    if (
      !expoFiltered
      && !cotFiltered
      && !avecgv
      && !apieduniquement
      && tempsMaxVelo === ""
      && denivMaxVelo === ""
      && distMaxVelo === ""
      && tempsMaxMA === ""
      && !villeSelected
    ) {
      falaises.forEach(falaise => {
        falaise.filteredOut = false;
      });
      resetButtonMobile.disabled = true;
      resetButton.disabled = true;
    }
    // Case 2: At least one filter is set --> set falaises hors topo hidden and apply filters
    else {
      falaisesHorsTopo.forEach(falaise => {
        falaise.filteredOut = false;
      });
      falaisesDuTopo.forEach(falaise => {
        const estCotationsCompatible = (
          (!cot40 || ("4+".localeCompare(falaise.falaise_cotmin) >= 0))
          && (!cot50 || ("5-".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("5-") >= 0))
          && (!cot59 || ("5+".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("5+") >= 0))
          && (!cot60 || ("6-".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("6-") >= 0))
          && (!cot69 || ("6+".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("6+") >= 0))
          && (!cot70 || ("7-".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("7-") >= 0))
          && (!cot79 || ("7+".localeCompare(falaise.falaise_cotmin) >= 0 && falaise.falaise_cotmax.localeCompare("7+") >= 0))
          && (!cot80 || (falaise.falaise_cotmax.localeCompare("8-") >= 0))
        );
        const estTrainCompatible = (
          falaise.access.some(it => {
            const train = it.villes.find(v => v.ville_id === ville);
            return train && (
              (tempsMaxTrain === "" || train.temps <= parseInt(tempsMaxTrain))
              && (nbCorrespMax === 10 || train.nCorresp <= nbCorrespMax)
              && (tempsMaxTV === "" || train.tempsTrainVelo <= parseInt(tempsMaxTV))
              && (tempsMaxTVA === "" || train.tempsTotal <= parseInt(tempsMaxTVA))
            )
          }));

        // Main filter logic
        if (
          (!expoFiltered || (
            (expoN && (falaise.falaise_exposhort1.includes("'N") || falaise.falaise_exposhort2.includes("'N")))
            || (expoE && (falaise.falaise_exposhort1.match(/('E|'NE'|'SE')/) || falaise.falaise_exposhort2.match(/('E|'NE'|'SE')/)))
            || (expoS && (falaise.falaise_exposhort1.includes("'S") || falaise.falaise_exposhort2.includes("'S")))
            || (expoO && (falaise.falaise_exposhort1.match(/('O|'NO'|'SO')/) || falaise.falaise_exposhort2.match(/('O|'NO'|'SO')/)))
          ))
          && (!cotFiltered || estCotationsCompatible)
          && (tempsMaxMA === "" || parseInt(falaise.falaise_maa || 0) <= parseInt(tempsMaxMA))
          && (avecgv === false || !!falaise.falaise_gvnb)
          && (!villeSelected || estTrainCompatible)
          && falaise.access.some(it => {
            const duration = calculate_time(it);
            return (
              (tempsMaxVelo === "" || duration <= tempsMaxVelo)
              && (denivMaxVelo === "" || parseInt(it.velo_dplus) <= denivMaxVelo)
              && (distMaxVelo === "" || parseFloat(it.velo_km) <= distMaxVelo)
              && (apieduniquement === false || it.velo_apieduniquement === "1" || it.velo_apiedpossible === "1")
            );
          }
          )
        ) {
          falaise.filteredOut = false;
        } else {
          falaise.filteredOut = true;
        }
      });
      resetButton.disabled = false;
      resetButtonMobile.disabled = false;
    }
    // const nbInFilter = falaisesDuTopo.filter(f => !f.filteredOut).length;
    // if (nbInFilter === 0) {
    //   alert("Aucune falaise du topo ne correspond à vos critères de recherche.");
    // }
    info.update();
    renderFalaises();

  }
  const villeChangeHandler = () => {
    const ville = document.getElementById("villeSelect").value;
    if (ville === "-1") {
      // disable all fields that require a ville
      document.querySelectorAll(".villeRequired").forEach(e => e.classList.add("opacity-30"));
      document.querySelectorAll(".villeRequired input").forEach(e => e.disabled = true);
    } else {
      document.querySelectorAll(".villeRequired").forEach(e => e.classList.remove("opacity-30"));
      document.querySelectorAll(".villeRequired input").forEach(e => e.disabled = false);
    }

  }
  document.querySelectorAll("#filtersForm input").forEach(i => i.addEventListener("change", filterHandler));
  document.getElementById("villeSelect").addEventListener("change", villeChangeHandler);
  document.getElementById("villeSelect").addEventListener("change", filterHandler);
  document.querySelectorAll("input[type=radio][name=nbCorrespMax]").forEach(i => i.addEventListener("change", (e) => {
    const resetIcon = document.getElementById("nbCorrespMax10Reset");
    const defaultCheckbox = document.getElementById("nbCorrespMax10");
    if (!defaultCheckbox.checked) {
      resetIcon.classList.add("text-primary");
      resetIcon.classList.remove("hidden");
    } else {
      resetIcon.classList.add("hidden");
      resetIcon.classList.remove("text-primary");
    }
  }));
  const resetAll = function (event) {
    event.preventDefault();
    document.getElementById("filtersForm").reset();
    // close modal
    document.getElementById("filtersModal").close();
    falaises.forEach(falaise => {
      falaise.filteredOut = false;
    });
    resetButton.disabled = true;
    resetButtonMobile.disabled = true;
    info.update();
    renderFalaises();
  }
  resetButton.addEventListener("click", resetAll);
  resetButtonMobile.addEventListener("click", resetAll);
  document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("filtersForm").reset();
    resetButton.disabled = true;
    resetButtonMobile.disabled = true;
    filterHandler();
    villeChangeHandler();
  });

</script>

<script>
  // --------------------------------- MOVE FORM ACCORDING TO SCREEN SIZE ---------------------------------
  document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("filtersForm");
    const searchForm = document.getElementById("searchForm");
    const dialog = document.getElementById("filtersModal");
    const searchdialog = document.getElementById("searchModal");
    const dialogContainer = document.getElementById("filtersFormDialogContainer");
    const desktopContainer = document.getElementById("filtersFormPanelContainer");
    const searchDialogContainer = document.getElementById("searchFormDialogContainer");
    const searchDesktopContainer = document.getElementById("searchFormPanelContainer");

    function moveForm() {
      if (window.innerWidth >= 768) {
        desktopContainer.appendChild(form);
        dialog.close();
        searchDesktopContainer.appendChild(searchForm);
        searchdialog.close();
      } else {
        dialogContainer.appendChild(form);
        searchDialogContainer.appendChild(searchForm);
      }
    }
    // Run on load
    moveForm();
  });
</script>
<script src="/js/autocomplete.js"></script>
<script>
  setupAutocomplete("search", "search-list", "garesetfalaises", searchByNameHandler);
</script>

</html>