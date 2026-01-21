<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';

$falaises = $mysqli->query("SELECT * FROM falaises WHERE falaise_public >= 1")->fetch_all(MYSQLI_ASSOC);
$villes = $mysqli->query("SELECT * FROM villes ORDER BY ville_nom")->fetch_all(MYSQLI_ASSOC);
$gares = $mysqli->query("SELECT
  g.*,
  GROUP_CONCAT(CONCAT(t.ville_id, '|', t.train_depart, '|', t.train_temps, '|', t.train_correspmin, '|', COALESCE(t.train_tgv, 0)) SEPARATOR '=|=') AS villes
  FROM gares g
  LEFT JOIN train t ON t.gare_id = g.gare_id
  WHERE g.deleted = 0
  GROUP BY g.gare_id;"
)->fetch_all(MYSQLI_ASSOC);
$itineraires = $mysqli->query("SELECT * FROM velo WHERE velo_public >= 1")->fetch_all(MYSQLI_ASSOC);

$highlight = $_GET['h'] ?? '';

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://velogrimpe.fr/" />
  <meta name="description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Velogrimpe.fr - Carte des falaises accessibles en vélo et train">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title"
    content="<?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">
  <!-- Map libraries bundle (Leaflet, GPX, Fullscreen, Locate) -->
  <script src="/dist/map.js"></script>
  <link rel="stylesheet" href="/dist/map.css" />
  <!-- <script src="https://unpkg.com/protomaps-leaflet@5.1.0/dist/protomaps-leaflet.js"></script> -->
  <script src="/js/vendor/protomaps-leaflet.js"></script>
  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Shared utilities -->
  <script src="/js/utils-global.js"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <!-- Vue Component Styles -->
  <?php vite_css('carte-info'); ?>
  <link rel="stylesheet" href="./index.css" />
  <link rel="manifest" href="./site.webmanifest" />
  <style>
    /* #map .leaflet-top.leaflet-right {
      top: 88px;
    } */
    /* @media (min-width: 640px) {
      #map .leaflet-top.leaflet-right {
        top: 44px;
      }
    } */
  </style>
</head>

<body>
  <?php include "./components/header.html"; ?>
  <main class="pb-2 px-2 md:px-8 pt-2">
    <!-- <h1 class="text-4xl font-bold text-center mb-1">Carte Vélogrimpe</h1> -->
    <!-- <div class="flex flex-col gap-1">
      <div id="map" class="w-full --md:w-[calc(100%-17rem)] h-[calc(100dvh-160px)] relative">
      </div>
    </div> -->
    <div class="flex flex-col gap-1">
      <div class="flex flex-row gap-4">
        <div
          class="hidden md:flex w-68 bg-base-100 rounded-lg p-4 shadow-lg text-sm flex-col gap-6 h-[calc(100dvh-115px)] overflow-y-auto">
          <div class="flex flex-col gap-2">
            <div id="searchFormPanelContainer">
              <div class="text-lg font-bold">Recherche</div>
              <div id="searchForm">
                <div id="vue-search"
                  data-falaises='<?= htmlspecialchars(json_encode(array_map(fn($f) => ["falaise_id" => $f["falaise_id"], "falaise_nom" => $f["falaise_nom"]], $falaises)), ENT_QUOTES) ?>'
                  data-gares='<?= htmlspecialchars(json_encode(array_map(fn($g) => ["gare_id" => $g["gare_id"], "gare_nom" => $g["gare_nom"]], $gares)), ENT_QUOTES) ?>'>
                </div>
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-2">
            <div class="flex flex-row items-center justify-between">
              <div class="text-lg font-bold">Filtres</div>
            </div>
            <div id="filtersFormPanelContainer">
              <!-- Vue Filter Panel -->
              <div id="vue-filters" data-villes='<?= htmlspecialchars(json_encode($villes), ENT_QUOTES) ?>'>
              </div>
            </div>
          </div>
        </div>
        <div id="map" class="w-full md:w-[calc(100%-17rem)] h-[calc(100dvh-115px)]"></div>
      </div>
    </div>
  </main>
  <div class="hidden">
    <div class="flex flex-row gap-1 justify-end md:hidden" id="searchAndFilter">
      <button class="btn btn-sm border-2 border-solid border-[rgba(0,0,0,.2)] rounded-md"
        onclick="searchModal.showModal()"> Chercher <svg class="w-4 h-4 fill-current">
          <use xlink:href="/symbols/icons.svg#search"></use>
        </svg>
      </button>
      <dialog id="searchModal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box md:w-3/5 max-w-xl">
          <form method="dialog">
            <button tabindex="-1" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
          </form>
          <div id="searchFormDialogContainer" class="min-h-50 mt-4"></div>
        </div>
        <form method="dialog" class="modal-backdrop">
          <button>close</button>
        </form>
      </dialog>
      <button class="btn btn-sm border-2 border-solid border-[rgba(0,0,0,.2)] rounded-md"
        onclick="document.getElementById('filtersModal').showModal()"> Filtrer <svg class="w-4 h-4 fill-current">
          <use xlink:href="/symbols/icons.svg#filter"></use>
        </svg>
      </button>
      <dialog id="filtersModal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box md:w-4/5 max-w-3xl m-0 p-4">
          <div class="flex justify-between items-center pb-3 border-b border-base-300 mb-4 ">
            <div>
              <span class="font-bold text-lg">Filtres</span>
              <span class="text-sm text-base-content/70" id="mobile-filter-stats"></span>
            </div>
            <form method="dialog">
              <button class="btn btn-sm btn-primary">OK</button>
            </form>
          </div>
          <div id="filtersFormDialogContainer"></div>
        </div>
        <form method="dialog" class="modal-backdrop">
          <button>close</button>
        </form>
      </dialog>
    </div>
  </div>
  <?php include "./components/footer.html"; ?>
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
  const falaiseIcon = (size, closed, bloc) =>
    L.icon({
      iconUrl: closed
        ? "/images/map/icone_falaisefermee_carte.png"
        : bloc === "1"
          ? "/images/map/icone_falaise_carte_bloc.png"
          : bloc === "2"
            ? "/images/map/icone_falaise_carte_psychobloc.png"
            : "/images/map/icone_falaise_carte.png",
      iconSize: [size, size],
      iconAnchor: [size / 2, size],
    });
  const trainIcon = (tgv, size = 24) => {
    return L.icon({
      iconUrl: "/images/map/icone_train_carte.png",
      className: "train-icon bgwhite" + (tgv ? " filterred" : ""),
      iconSize: [size, size],
      iconAnchor: [size / 2, size / 2],
    });
  };

  // format_time and calculate_time are loaded from /js/utils-global.js

  const halo = "[text-shadow:-1px_-1px_0_#fff,1px_-1px_0_#fff,-1px_1px_0_#fff,1px_1px_0_#fff,0_1px_0_#fff,0_-1px_0_#fff,1px_0_0_#fff,-1px_0_0_#fff]";

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
    attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
    crossOrigin: true,
  })
  const outdoorsTiles = L.tileLayer(
    "https://{s}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", {
    maxZoom: 19,
    minZoom: 0,
    attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
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
        gare.marker?.setIcon(trainIcon(gare.gare_tgv === "1", 24));
        // gare.marker?.closeTooltip();
        gare.marker?.unbindTooltip();
        gare.marker?.bindTooltip(gare.gare_nom, {
          className: "p-px",
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
          className: "p-px",
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
        e.target.bindTooltip(
          format_time(calculate_time(it))
          + (it.velo_apieduniquement === "1"
            ? '<svg class="w-4 h-4 fill-current inline"><use xlink:href="/symbols/icons.svg#footprint"></use></svg>'
            : ""
          ),
          {
            className: `vg-velo-tooltip vg-color-${c}`,
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
          icon: falaiseIcon(defaultMarkerSize, falaise.falaise_fermee, falaise.falaise_bloc),
          riseOnHover: true,
          autoPanOnFocus: true,
          title: "Clic pour voir les accès, puis second clic pour accéder à la fiche complète"
        }
      ).addTo(map);
      falaise.marker = marker;
      const labelMarker = L.marker(
        falaise.falaise_latlng.split(","),
        {
          icon: L.divIcon({
            className: "relative",
            html: `<div class="absolute top-0 left-1/2 text-center -translate-x-1/2 w-max max-w-[150px] text-primary font-bold ${halo} text-sm">${falaise.falaise_nom}</div>`,
            iconSize: [0, 0],
          }),
          riseOnHover: true,
          autoPanOnFocus: true,
        }
      ).addTo(map);
      falaise.labelMarker = labelMarker;
      if (falaise.highlighted) {
        const hmarker = L.marker(
          falaise.falaise_latlng.split(","),
          {
            icon: L.divIcon({
              iconSize: [0, 0],
              iconAnchor: [0, 0],
              className: "relative",
              html: `<div
                class="absolute z-1 top-0 left-1/2 w-fit text-nowrap -translate-x-1/2
                bg-linear-to-r from-primary to-secondary border-2 border-white text-white text-xs p-[2px] leading-none rounded-md"
                >
              ${falaise.falaise_nom}
            </div>`,
            }),
            riseOnHover: true,
            autoPanOnFocus: true,
          }
        ).addTo(map);
        falaise.hmarker = hmarker;
      }
      marker.bindTooltip(falaise.falaise_nom, {
        className: "p-px",
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
              className: `vg-station-tooltip vg-color-${c}`,
            });
          }), 0.76 * 1000);
        } else {
          // map.flyTo(falaise.falaise_latlng.split(","), 15, { duration: 0.25 });
          // navigate to falaise page
          window.location.href = `/falaise.php?falaise_id=${falaise.falaise_id}`;
          // marker.closeTooltip();
        }
        marker.unbindTooltip();
        marker.bindTooltip(falaise.falaise_nom, {
          className: "p-px",
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
    // Clear old marker when mode changed
    if (!falaise.marker || !falaise.labelMarker || falaise.displayMode === "hidden") {
      initMarker();
    }
    // Set new mode
    falaise.displayMode = mode;
    // Depending on mode: size, opacity, tooltip, onMap (remove layer)
    const setIconAndTooltip = (size, direction, permanent = false) => {
      falaise.marker.setIcon(falaiseIcon(size, falaise.falaise_fermee, falaise.falaise_bloc));
      falaise.marker.unbindTooltip();
      falaise.marker.bindTooltip(falaise.falaise_nom, {
        className: "p-px",
        direction,
        offset: direction === "right" ? [size / 4, -size / 2] : direction === "top" ? [0, -size] : [size / 2, 0],
        permanent,
      });
    };
    if (mode === "normal+label") {
      falaise.labelMarker?.addTo(map);
    } else {
      map.removeLayer(falaise.labelMarker);
    }
    switch (mode) {
      case "normal":
      case "normal+label":
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
    falaise.displayMode = mode;
    const size = 20;
    const init = () => {
      const marker = L.marker(
        falaise.falaise_latlng.split(","),
        {
          icon: falaiseIcon(size, falaise.falaise_fermee, falaise.falaise_bloc),
          opacity: 0.75,
          riseOnHover: true,
          autoPanOnFocus: true,
        }
      ).addTo(map);
      const labelMarker = L.marker(
        falaise.falaise_latlng.split(","),
        {
          icon: L.divIcon({
            className: "relative",
            html: `<div class="absolute top-0 left-1/2 text-center -translate-x-1/2 w-max max-w-[150px] text-primary font-bold ${halo}">${falaise.falaise_nom}</div>`,
            iconSize: [0, 0],
          }),
          opacity: 0.75,
          riseOnHover: true,
          autoPanOnFocus: true,
        }
      ).addTo(map);
      falaise.labelMarker = labelMarker;
      falaise.marker = marker;
      marker.bindPopup(
        `<div class="flex flex-col gap-1">`
        + `<div class="text-slate-400"><span class="uppercase">hors topo</span> (aucun accès 🚲 décrit)</div>`
        + `<div class="text-sm font-bold">${falaise.falaise_nom}</div>`
        + `${falaise.falaise_fermee ? `<div class="text-error">${falaise.falaise_fermee.replace(/\n/g, "<br>")}</div>` : ""}`
        + `<div class="flex gap-2 w-full justify-end">`
        + `  <a href="/ajout/ajout_falaise.php?falaise_id=${falaise.falaise_id}" class="btn btn-xs btn-primary">Renseigner la falaise</a>`
        + `  <a href="/ajout/ajout_velo.php?falaise_id=${falaise.falaise_id}"class="btn btn-xs btn-primary">Ajouter accès</a>`
        + `</div>`
        + `</div>`,
        { offset: [0, -10] }
      );
    };
    if (!falaise.marker || !falaise.labelMarker) {
      init();
    }
    switch (mode) {
      case "normal":
      case "normal+label":
        falaise.marker.addTo(map);
        if (mode === "normal+label") {
          if (!falaise.labelMarker._map) {
            falaise.labelMarker.addTo(map);
          }
        } else {
          map.removeLayer(falaise.labelMarker);
        }
        return;
      case "hidden":
        map.removeLayer(falaise.marker);
        map.removeLayer(falaise.labelMarker);
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
        icon: trainIcon(gare.gare_tgv === "1", 24),
        riseOnHover: true,
        autoPanOnFocus: true,
      }
    ).addTo(map);
    marker.unbindTooltip();
    marker.bindTooltip(gare.gare_nom, {
      className: "p-px",
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
      e.target.setIcon(trainIcon(gare.gare_tgv === "1", selectedGareSize));

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
          className: `vg-station-tooltip vg-color-${c}`,
        });
        const gpx = renderGpx(it, c);
        itinerairesLines.push(gpx);
      }), 0.76 * 1000);
      marker.unbindTooltip();
      marker.bindTooltip(gare.gare_nom, {
        className: "p-px",
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
        fillColor: gare.gare_tgv === "0" ? "black" : "#a00",
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
  const highlightedFalaiseIds = "<?= $highlight ?>".split(",");
  const itineraires = <?php echo json_encode($itineraires); ?>.map(it => ({ ...it, tempsVelo: calculate_time(it) }));
  const garesBase = <?php echo json_encode($gares); ?>.map(g => {
    g.villes = (g.villes || "")
      .split("=|=")
      .map(v => {
        const [ville_id, ville, durStr, nCorresp, tgv] = v.split("|"); return { ville_id, ville, temps: parseInt(durStr), nCorresp: parseInt(nCorresp), train_tgv: parseInt(tgv) }
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
    if (highlightedFalaiseIds.includes(f.falaise_id)) {
      f.highlighted = true;
    }
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
    layers: [landscapeTiles], center, zoom, fullscreenControl: true
  });
  var layerControl = L.control.layers(baseMaps, undefined, { position: "topleft", size: 22 }).addTo(map);
  L.control.scale({ position: "bottomleft", metric: true, imperial: false, maxWidth: 125 }).addTo(map);
  L.control.locate().addTo(map);

  var searchAndFilter = L.control({ position: 'topright' });

  searchAndFilter.onAdd = function (map) {
    this._div = L.DomUtil.create('div', 'w-[calc(100%-50px)]'); // create a div with a class "info"
    L.DomEvent.disableClickPropagation(this._div);
    L.DomEvent.disableScrollPropagation(this._div);
    const form = document.getElementById("searchAndFilter")
    this._div.appendChild(form)
    return this._div;
  };
  searchAndFilter.addTo(map);

  // PANNEAU D'INFORMATION SUR LA FALAISE/GARE SELECTIONNEE (Vue.js)
  var info = L.control({ position: 'bottomright' });
  info.onAdd = function (map) {
    this._div = L.DomUtil.create('div', 'info w-[calc(100%-20px)]');
    this._div.id = 'vue-info-panel';
    L.DomEvent.disableClickPropagation(this._div);
    L.DomEvent.disableScrollPropagation(this._div);
    return this._div;
  };
  // Update Vue store instead of regenerating HTML
  info.update = function () {
    const nFalaises = falaises.filter(f => (f.type === "falaise")).length;
    const nFalaiseFiltered = falaises.filter(f => (f.type === "falaise") && !f.filteredOut).length;

    // Wait for Vue to be ready, then update
    if (window.velogrimpe?.carteInfo) {
      window.velogrimpe.carteInfo.updateStats(nFalaises, nFalaiseFiltered);
      window.velogrimpe.carteInfo.setSelected(selected);
    }

    // Update mobile filter stats
    const mobileStats = document.getElementById('mobile-filter-stats');
    if (mobileStats) {
      const hasFilters = nFalaises !== nFalaiseFiltered;
      mobileStats.textContent = hasFilters
        ? `${nFalaiseFiltered} / ${nFalaises} falaises`
        : `${nFalaises} falaises`;
    }

    // Open details on desktop after Vue renders
    setTimeout(() => {
      if (window.innerWidth >= 768) {
        this._div.querySelectorAll("details").forEach((details) => details.open = true);
      }
    }, 50);
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
        } else if (zoom < 14) {
          setFalaiseHTMarker(falaise, map, "normal");
        } else {
          setFalaiseHTMarker(falaise, map, "normal+label");
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
          } else if (zoom < 12) {
            setFalaiseMarker(falaise, map, "normal");
          } else {
            setFalaiseMarker(falaise, map, "normal+label");
          }
        } else {
          if (zoom < 9) {
            setFalaiseMarker(falaise, map, "reduced");
          } else if (zoom < 12) {
            setFalaiseMarker(falaise, map, "normal");
          } else {
            setFalaiseMarker(falaise, map, "normal+label");
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
            gare.marker?.setIcon(trainIcon(gare.gare_tgv === "1", 24));
          }
        }
      }
    });
  }
  renderGares();

  map.on("zoomend", (e) => {
    // console.log("zoomend", map.getZoom());
    renderFalaises();
    renderGares();
  });

  map.on("moveend", (e) => {
    renderGares();
  });

</script>
<script type="module">
  import { campingLayer, giteLayer, trainlinesLayer, tgvLayer, biodivLayer } from "/js/components/map/load-vector-tiles.js";
  campingLayer.addTo(map);
  trainlinesLayer.addTo(map);
  tgvLayer.addTo(map);
  layerControl.addOverlay(tgvLayer, 'Lignes et Gares TGV');
  layerControl.addOverlay(campingLayer, 'Campings');
  layerControl.addOverlay(giteLayer, 'Gîtes');
  layerControl.addOverlay(biodivLayer, 'Aires de protections de la biodiversité (escalade réglementée ou interdite)');
</script>
<script>
  // ============================================ RECHERCHE (Vue.js) ============================================

  // Listen for Vue search selection event
  window.addEventListener('velogrimpe:search-select', (e) => {
    const { id, type, name } = e.detail;
    document.getElementById("searchModal")?.close();
    document.getElementById("map").scrollIntoView({ behavior: "smooth", block: "nearest" });

    let item = null;
    if (type === "falaise") {
      item = falaises.find(f => f.falaise_id === id);
    } else if (type === "gare") {
      item = gares.find(g => g.gare_id === id);
    }

    if (item) {
      if (item.type === "falaise_hors_topo") {
        setFalaiseHTMarker(item, map, "normal");
        map.flyTo(item.falaise_latlng.split(",").map(parseFloat), 12, { duration: 0.5 });
        setTimeout(() => item.marker?.openPopup(), 600);
        return;
      }
      else if (item.type === "gare_hors_topo") {
        map.flyTo(item.gare_latlng.split(",").map(parseFloat), 11, { duration: 0.5 });
        setTimeout(() => item.marker?.openPopup(), 600);
        return;
      }
      item.marker?.fire("click");
      setTimeout(() => item.marker?.openPopup(), 600);
    }
  });

  // ============================================ FILTRES (Vue.js) ============================================
  const falaisesDuTopo = falaises.filter(f => f.access.length > 0);
  const falaisesHorsTopo = falaises.filter(f => f.access.length === 0);

  // Vue filter handler - receives filter state from Vue component
  const applyVueFilters = (filters) => {
    const expoN = filters.exposition.includes('N');
    const expoE = filters.exposition.includes('E');
    const expoS = filters.exposition.includes('S');
    const expoO = filters.exposition.includes('O');
    const cot40 = filters.cotations.includes('40');
    const cot50 = filters.cotations.includes('50');
    const cot59 = filters.cotations.includes('59');
    const cot60 = filters.cotations.includes('60');
    const cot69 = filters.cotations.includes('69');
    const cot70 = filters.cotations.includes('70');
    const cot79 = filters.cotations.includes('79');
    const cot80 = filters.cotations.includes('80');
    const couenne = filters.typeVoies.couenne;
    const avecgv = filters.typeVoies.grandeVoie;
    const bloc = filters.typeVoies.bloc;
    const psychobloc = filters.typeVoies.psychobloc;
    const apieduniquement = filters.velo.apiedPossible;
    const tempsMaxVelo = filters.velo.tempsMax;
    const distMaxVelo = filters.velo.distMax;
    const denivMaxVelo = filters.velo.denivMax;
    const tempsMaxTrain = filters.train.tempsMax;
    const nbCorrespMax = filters.train.correspMax !== null ? filters.train.correspMax : 10;
    const terOnly = filters.train.terOnly;
    const tempsMaxMA = filters.approche.tempsMax;
    const tempsMaxTV = filters.total.tempsTV;
    const tempsMaxTVA = filters.total.tempsTVA;
    const ville = filters.villeId;
    const villeSelected = ville !== null;
    const nbVoies = filters.nbVoiesMin;

    const expoFiltered = [expoN, expoE, expoS, expoO].some(e => e);
    const cotFiltered = [cot40, cot50, cot59, cot60, cot69, cot70, cot79, cot80].some(e => e);
    const typeVoiesFiltered = couenne || avecgv || bloc || psychobloc;

    // Case 1: all default values --> set all falaises visible
    if (
      !expoFiltered
      && !cotFiltered
      && !typeVoiesFiltered
      && nbVoies === 0
      && !apieduniquement
      && tempsMaxVelo === null
      && denivMaxVelo === null
      && distMaxVelo === null
      && tempsMaxMA === null
      && !villeSelected
    ) {
      falaises.forEach(falaise => {
        falaise.filteredOut = false;
      });
    }
    // Case 2: At least one filter is set --> apply filters
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
              (tempsMaxTrain === null || train.temps <= tempsMaxTrain)
              && (nbCorrespMax === 10 || train.nCorresp <= nbCorrespMax)
              && (!terOnly || parseInt(train.train_tgv || 0) === 0)
              && (tempsMaxTV === null || train.tempsTrainVelo <= tempsMaxTV)
              && (tempsMaxTVA === null || train.tempsTotal <= tempsMaxTVA)
            )
          }));
        const estNbVoiesCompatible = (parseInt(falaise.falaise_nbvoies) >= nbVoies) || nbVoies === 0;
        const estTypeVoiesCompatible = (
          (couenne && !!!parseInt(falaise.falaise_bloc))
          || (avecgv && !!falaise.falaise_gvnb)
          || (bloc && parseInt(falaise.falaise_bloc) === 1)
          || (psychobloc && parseInt(falaise.falaise_bloc) === 2)
        );

        // Main filter logic
        if (
          (!expoFiltered || (
            (expoN && (falaise.falaise_exposhort1.includes("'N") || falaise.falaise_exposhort2.includes("'N")))
            || (expoE && (falaise.falaise_exposhort1.match(/('E|'NE'|'SE')/) || falaise.falaise_exposhort2.match(/('E|'NE'|'SE')/)))
            || (expoS && (falaise.falaise_exposhort1.includes("'S") || falaise.falaise_exposhort2.includes("'S")))
            || (expoO && (falaise.falaise_exposhort1.match(/('O|'NO'|'SO')/) || falaise.falaise_exposhort2.match(/('O|'NO'|'SO')/)))
          ))
          && (!cotFiltered || estCotationsCompatible)
          && (tempsMaxMA === null || parseInt(falaise.falaise_maa || 0) <= tempsMaxMA)
          && estNbVoiesCompatible
          && (!typeVoiesFiltered || estTypeVoiesCompatible)
          && (!villeSelected || estTrainCompatible)
          && falaise.access.some(it => {
            const duration = calculate_time(it);
            return (
              (tempsMaxVelo === null || duration <= tempsMaxVelo)
              && (denivMaxVelo === null || parseInt(it.velo_dplus) <= denivMaxVelo)
              && (distMaxVelo === null || parseFloat(it.velo_km) <= distMaxVelo)
              && (apieduniquement === false || it.velo_apieduniquement === "1" || it.velo_apiedpossible === "1")
            );
          })
        ) {
          falaise.filteredOut = false;
        } else {
          falaise.filteredOut = true;
        }
      });
    }
    info.update();
    renderFalaises();
  }

  // Listen for Vue filter changes
  window.addEventListener('velogrimpe:filters', (e) => {
    applyVueFilters(e.detail);
  });

  // Initial render - wait for Vue info panel to be ready
  window.addEventListener('velogrimpe:info-ready', function () {
    info.update();
  });

</script>
<script>
  // --------------------------------- MOVE FORM ACCORDING TO SCREEN SIZE ---------------------------------
  document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("vue-filters");
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
<!-- Vue.js Search Autocomplete -->
<script type="module" src="/dist/carte-search.js"></script>
<!-- Vue.js Filter Panel -->
<script type="module" src="/dist/carte-filters.js"></script>
<!-- Vue.js Info Panel -->
<script type="module" src="/dist/carte-info.js"></script>

</html>