<?php

$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["contrib_token"];

$falaise_id = $_GET['falaise_id'] ?? null;
if (empty($falaise_id)) {
  echo 'Pas de falaise renseignée.';
  exit;
}

require_once "../../database/velogrimpe.php";

$falaises = $mysqli->query("SELECT falaise_id, falaise_nom
                                  FROM falaises
                                  ORDER BY falaise_id DESC
                                  ")->fetch_all(MYSQLI_ASSOC);

$stmtF = $mysqli->prepare("SELECT
  f.falaise_id,
  f.falaise_nom,
  f.falaise_nomformate,
  f.falaise_latlng,
  v.velo_id,
  v.gare_id,
  v.velo_depart,
  v.velo_arrivee,
  v.velo_variante,
  v.velo_varianteformate
  FROM falaises f
  LEFT JOIN velo v ON v.falaise_id = f.falaise_id
  WHERE f.falaise_id = ?");
if (!$stmtF) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$stmtF->bind_param("i", $falaise_id);
$stmtF->execute();
$resF = $stmtF->get_result();

$falaise = $resF->fetch_assoc();
$stmtF->close();

$stmtIt = $mysqli->prepare("
  SELECT *
  FROM velo
  LEFT JOIN gares ON velo.gare_id = gares.gare_id
  WHERE velo.falaise_id = ?");
$stmtIt->bind_param("i", $falaise_id);
$stmtIt->execute();
$result = $stmtIt->get_result();
$velos = [];
while ($row = $result->fetch_assoc()) {
  $velos[] = $row;
}
$stmtIt->close();

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Editeur détails falaise - Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js "></script>
  <link href=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css " rel="stylesheet">
  <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
  <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css'
    rel='stylesheet' />
  <link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.css" />
  <script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@latest/dist/leaflet-geoman.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/2.1.2/gpx.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@turf/turf@7/turf.min.js"></script>
  <script src="/js/vendor/leaflet-textpath.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="/index.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <style>
    .vg-icon {
      width: 24px;
      height: 24px;
      background-size: cover;
    }

    .vg-draw-approche {
      background-image: url('/images/pm_walking.png');
    }

    .vg-draw-parking {
      background-image: url('/images/pm_parking.png');
    }

    .vg-draw-secteur {
      background-image: url('/images/pm_rock-climbing.png');
    }

    .vg-draw-ext-falaise {
      background-image: url('/images/pm_link.png');
    }

    .vg-draw-velo {
      background-image: url('/images/pm_bicycle.png');
    }
  </style>
</head>

<body>
  <?php include "../../components/header.html"; ?>
  <main class="py-4 px-2 md:px-8 flex flex-col gap-4">
    <div class="flex gap-2 justify-end items-center">
      <select id="selectFalaise1" name="selectFalaise1" class="select select-primary select-sm"
        onchange="window.location.href = '/ajout/contrib/details_falaise.php?falaise_id=' + this.value">
        <?php foreach ($falaises as $f): ?>
          <option value="<?= $f['falaise_id'] ?>" <?= $falaise_id == $f["falaise_id"] ? "selected" : "" ?>>
            <?= $f['falaise_nom'] ?> - <?= $f['falaise_id'] ?>
          </option>
        <?php endforeach; ?>
      </select>
      <a class="btn btn-sm" href="/falaise.php?falaise_id=<?php echo $falaise['falaise_id']; ?>">Voir la
        falaise</a>
      <input type="file" hidden accept=".geojson" id="uploadGeoJSONInput"
        class="file-input file-input-sm file-input-bordered w-24" />
      <button class="btn btn-sm" id="uploadGeoJSONButton">
        <svg class="w-5 h-5 fill-current">
          <use xlink:href="/symbols/icons.svg#ri-file-upload-line"></use>
        </svg> Import
      </button>
      <button class="btn btn-sm" id="downloadGeoJSON">Télécharger le GeoJSON</button>
      <div class="tooltip tooltip-left" data-tip="Cmd/Ctrl + S">
        <button class="btn btn-primary btn-sm" id="saveGeoJSON">Enregistrer</button>
      </div>
    </div>
    <div class="flex relative flex-col gap-1">
      <div id="map" class="w-full h-[calc(100vh-180px)]"></div>
      <div class="absolute bottom-3 left-3 z-[10000] flex gap-1">
        <input class="input input-sm input-bordered rounded-none" type="text" id="falaise_latlng" name="falaise_latlng"
          placeholder="ex: 45.1234,6.2355" required>
        <button class="btn btn-sm btn-primary px-1" id="setFalaiseLatLng"><svg class="w-5 h-5 fill-current">
            <use xlink:href="/symbols/icons.svg#ri-arrow-right-line"></use>
          </svg></button>
      </div>
      <dialog id="tableau_modal" class="modal modal-bottom">
        <div class="modal-box w-screen max-w-full h-full">
          <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
          </form>
          <h3 class="font-bold text-xl">Tableau récapitulatif</h3>
          <p class="text-error text-sm">
            N'oubliez pas de sauvegarder le GeoJSON après avoir modifié les données dans le tableau.
          </p>
          <div id="tableauRecap" class="flex flex-col gap-1">
          </div>
        </div>
      </dialog>
  </main>
  <?php include "../../components/footer.html"; ?>
</body>
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

</script>

<script type="module">
  import Falaise from "/js/components/map/falaise.js";
  import Velo from "/js/components/map/velo.js";
  import AccesVelo from "/js/components/map/acces-velo.js";
  import Secteur from "/js/components/map/secteur.js";
  import Approche from "/js/components/map/approche.js";
  import Parking from "/js/components/map/parking.js";
  import { getValhallaRoute } from "/js/services/valhalla.js";

  const zoom = 15;
  // Récupération des données
  const falaise = <?php echo json_encode($falaise); ?>;
  const velos = <?php echo json_encode($velos); ?>;
  const center = falaise.falaise_latlng.split(",").map(parseFloat);

  const parkingIcon = (size) => L.divIcon({
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    className: "bg-none flex flex-row justify-center items-start",
    html: (
      `<div class="text-white bg-blue-600 text-[${size / 2 + 1}px] rounded-full aspect-square w-[${size}px] h-[${size}px] flex justify-center items-center font-bold border border-white">P</div>`
    ),
  })

  var map = L.map("map", {
    layers: [landscapeTiles], center, zoom, fullscreenControl: true, zoomSnap: 0.5
  });
  var layerControl = L.control.layers(baseMaps, undefined, { position: "topleft", size: 22 }).addTo(map);

  /* Add the following button in a control on the right

      <div class="absolute top-36 right-3 z-[10000] flex gap-1">
        <button class="btn btn-sm px-1" onclick="tableau_modal.showModal()">
          <svg class="w-5 h-5 fill-current">
            <use xlink:href="/symbols/icons.svg#ri-table-line"></use>
          </svg>
        </button>
      </div>
  */
  const TableControl = L.Control.extend({
    onAdd: function (map) {
      const div = L.DomUtil.create('div');
      div.innerHTML = `
        <div class="leaflet-control-zoom leaflet-bar">
          <a onclick="showTableau()" class="p-1" title="Tableau récapitulatif des éléments">
            <svg class="w-5 h-5 fill-current">
              <use xlink:href="/symbols/icons.svg#ri-table-line"></use>
            </svg>
          </a>
        </div>
    `;
      return div;
    },

    onRemove: function (map) {
      // Nothing to clean up
    }
  });

  // Add to the map
  const tableControl = new TableControl({ position: 'topright' });
  map.addControl(tableControl);


  const falaiseObject = new Falaise(map, falaise);
  const veloObjects = velos.map((velo, index) => new Velo(map, velo, { index }));
  L.control.scale({ position: "bottomright", metric: true, imperial: false, maxWidth: 125 }).addTo(map);
  map.pm.addControls({
    position: 'topright',
    drawCircle: false,
    drawMarker: false,
    drawPolyline: false,
    drawPolygon: false,
    drawRectangle: false,
    drawText: false,
    drawCircleMarker: false,
    cutPolygon: false,
    rotateMode: false,
    dragMode: false,
    editMode: false,
    removalMode: false,
  });
  map.pm.Toolbar.createCustomControl({
    name: "Accès vélo",
    block: "draw",
    title: "Ajouter un accès vélo",
    className: "vg-icon vg-draw-velo",
    toggle: false,
    onClick: () => {
      map.pm.enableDraw("Line", {
        snappable: true,
        snapDistance: 20,
        pathOptions: AccesVelo.style,
        templineStyle: AccesVelo.style,
        hintlineStyle: AccesVelo.style,
        type: "acces_velo",
      });
    },
  });

  let currentRoute = []
  let currentRoutingPoints = []
  map.pm.Toolbar.createCustomControl({
    name: "Approche auto",
    block: "draw",
    title: "Ajouter un itinéraire d'approche",
    className: "vg-icon vg-draw-approche",
    // toggle: false,
    actions: [
      "cancel",
      {
        text: "Point à Point",
        title: "Point à point : Ligne droite d'un point à l'autre",
        name: "line",
        onClick: () => {
          map.pm.enableDraw("Line", {
            snappable: true,
            snapDistance: 20,
            pathOptions: Approche.style,
            templineStyle: { ...Approche.style, type: "approche" },
            hintlineStyle: Approche.style,
            type: "approche",
          });
        }
      },
      {
        text: "Semi-auto (beta)",
        title: "Points de passages : Routage d'un point à l'autre",
        name: "line-auto",
        onClick: () => {
          map.pm.enableDraw("Line", {
            snappable: true,
            snapDistance: 20,
            pathOptions: Approche.style,
            templineStyle: { ...Approche.style, type: "approche-auto" },
            hintlineStyle: Approche.style,
            type: "approche",
          });
        }
      },
    ]
  });
  map.on("pm:drawstart", ({ shape, workingLayer }) => {
    currentRoute = [];
    currentRoutingPoints = [];
    if (workingLayer.options.type === "approche-auto") {
      workingLayer.on("pm:vertexadded", (e) => {
        // if clicked on existing routing point stop tracing
        if (currentRoutingPoints.includes([e.latlng.lat, e.latlng.lng])) {
          map.pm.disableDraw('Line');
        }

        if (currentRoutingPoints.length > 0) {
          const lastPoint = currentRoutingPoints.slice(-1)[0]
          getValhallaRoute([
            { lat: lastPoint[0], lon: lastPoint[1] },
            { lat: e.latlng.lat, lon: e.latlng.lng }
          ]).then((segment) => {
            if (segment && segment.length > 0) {
              currentRoute = [...currentRoute, ...segment];
              workingLayer.setLatLngs(currentRoute);
            }
          })
        }
        currentRoutingPoints.push([e.latlng.lat, e.latlng.lng]);
      });
    }
  });
  const markerIcon = parkingIcon(18);
  map.pm.Toolbar.createCustomControl({
    name: "Parking",
    block: "draw",
    title: "Ajouter un parking",
    className: "vg-icon vg-draw-parking",
    actions: ["cancel", {
      text: "Nouveau parking",
      name: "marker",
      onClick: () => {
        map.pm.enableDraw("Marker", {
          snappable: true,
          snapDistance: 20,
          continueDrawing: false,
          markerStyle: {
            draggable: true,
            icon: markerIcon,
          },
          type: "parking",
        });
      },
    }],
  });
  map.pm.Toolbar.createCustomControl({
    name: "Secteur",
    block: "draw",
    title: "Ajouter un secteur",
    className: "vg-icon vg-draw-secteur",
    actions: [
      "cancel",
      {
        text: "Secteur Linéaire",
        title: "Secteur linéaire : Le vide est à droite dans le sens du tracé",
        name: "line",
        onClick: () => {
          map.pm.enableDraw("Line", {
            snappable: true,
            snapDistance: 20,
            pathOptions: Secteur.lineStyle,
            templineStyle: Secteur.lineStyle,
            hintlineStyle: Secteur.lineStyle,
            type: "secteur",
          });
        },
      },
      {
        text: "Secteur Polygonal",
        name: "polygon",
        onClick: () => {
          map.pm.enableDraw("Polygon", {
            snappable: true,
            snapDistance: 20,
            pathOptions: Secteur.polygonStyle,
            templineStyle: Secteur.polygonStyle,
            hintlineStyle: Secteur.polygonStyle,
            type: "secteur",
          });
        },
      },
    ],
  });


  map.on("pm:create", e => {
    const { layer, shape } = e;
    const type = layer.pm.options.type;
    layer.properties = { type };
    let obj;
    if ((type === "secteur" || type === undefined)) {
      obj = Secteur.fromLayer(map, layer);
    } else if (type === "approche") {
      obj = Approche.fromLayer(map, layer);
    } else if (type === "parking") {
      obj = Parking.fromLayer(map, layer);
    } else if (type === "acces_velo") {
      obj = AccesVelo.fromLayer(map, layer);
    }
    createAndBindPopup(obj.layer);
    if (obj instanceof Secteur && obj.label) {
      createAndBindPopup(obj.label.layer, obj.layer);
    }
    obj.layer.openPopup();
    featureMap[obj.layer._leaflet_id] = obj;
  })

  window.editLayer = function (id) {
    map.eachLayer((layer) => {
      if (layer.pm && layer.pm.enabled()) {
        layer.pm.disable();
      }
    });
    const feature = featureMap[id];
    const layer = feature.layer;
    if (layer.pm) {
      layer.pm.enable();
    }
    layer.closePopup();
    feature.label?.layer?.closePopup();
  };
  // On click on the map if a layer is pm.enabled, disable it
  map.on("click", (e) => {
    map.eachLayer((layer) => {
      if (layer.pm && layer.pm.enabled()) {
        layer.pm.disable();
      }
    });
  });


  // Save function in global scope for simplicity
  window.updateLayer = function (id) {
    const feature = featureMap[id];
    const layer = feature.layer;
    let needsLabelUpdate = false;
    document.querySelectorAll(`.input-${id}`).forEach(input => {
      const propertyName = input.name;
      if (propertyName && layer) {
        if (propertyName === "name" && layer.properties.name !== input.value && feature.type === "secteur") {
          needsLabelUpdate = true;
        }
        layer.properties[propertyName] = input.value;
      }
    });
    if (needsLabelUpdate) {
      feature?.updateLabel();
    }
    layer.closePopup();
    createAndBindPopup(layer);
    if (feature instanceof Secteur && feature.label) {
      createAndBindPopup(feature.label.layer, layer);
    }
    updateAssociations();
    feature.highlight();
    feature.unhighlight();
  };

  window.invertLine = function (id) {
    const feature = featureMap[id];
    const layer = feature.layer;
    const coords = layer.getLatLngs();
    if (coords.length > 1) {
      if (coords[0] instanceof L.LatLng) {
        coords.reverse();
      } else if (coords[0] instanceof Array) {
        coords.forEach((c) => c.reverse());
      }
      layer.setLatLngs(coords);
    }
  }

  window.deleteFeature = (id) => {
    if (confirm("Êtes-vous sur de vouloir supprimer cet élément ?")) {
      const feature = featureMap[id];
      const layer = feature.layer;
      map.removeLayer(layer);
      feature.cleanUp();
      delete featureMap[id];
    }
  }
  window.createAndBindPopup = (layer, _targetLayer) => {
    const targetLayer = _targetLayer || layer;
    const id = targetLayer._leaflet_id;
    let popupHtml = "";
    const field = (name, label, placeholder) => {
      return `
        <label for="${name}" class="w-full flex gap-2 items-center">
          <span class="flex-1 text-right">${label}: </span>
          <input
            type="text"
            name="${name}"
            ${name === "name" ? "autofocus" : ""}
            class="input-${id} input input-xs input-primary w-48" value="${targetLayer.properties[name] || ''}"
            placeholder="${placeholder}"
          />
        </label>`
    }
    popupHtml += `<div class="w-[300px] flex flex-col gap-1 justify-stretch mx-auto">`;
    popupHtml += field("name", "Nom", "Nom");
    popupHtml += field("description", "Description", "optionnel");
    if (targetLayer.properties.type === "secteur" || targetLayer.properties.type === undefined) {
      popupHtml += field("parking", "Parkings", "p1, p2, ...");
      popupHtml += field("approche", "Approches", "a1, a2, ...");
      popupHtml += field("gv", "Grandes Voies", "0 = non, 1 = uniq. GV, 2 = mixte");
    } else if (targetLayer.properties.type === "approche") {
      popupHtml += field("parking", "Parkings", "p1, p2, ...");
    } else if (targetLayer.properties.type === "parking") {
      popupHtml += field("itineraire_acces", "Accès vélo", "v1, ...");
    } else if (targetLayer.properties.type === "acces_velo") {
    }
    popupHtml += `<div class="flex flex-row gap-1 justify-between">`;
    popupHtml += `<button class="flex-1 btn btn-xs btn-error text-base-100" onclick="deleteFeature(${id})">Suppr.</button>`;
    popupHtml += `<button class="flex-1 btn btn-xs btn-accent" onclick="editLayer(${id})">${targetLayer.pm.enabled() ? "OK" : "Modif."}</button>`
    if ((targetLayer.properties.type === "secteur" || targetLayer.properties.type === undefined) && targetLayer instanceof L.Polyline) {
      popupHtml += `<button class="flex-1 btn btn-xs btn-secondary" onclick="invertLine(${id})">Inverser</button>`;
    }
    popupHtml += `<button class="flex-1 btn btn-xs btn-primary" onclick="updateLayer(${id})">Enreg.</button>`
    popupHtml += `</div>`;
    popupHtml += `</div>`;
    layer.bindTooltip(JSON.stringify(targetLayer.properties));
    layer.bindPopup(popupHtml, {
      className: "w-[350px]",
      minWidth: 300,
      maxWidth: 350,
    });
  }

  window.updateAssociations = () => {
    const features = Object.values(featureMap);
    features.forEach(feature => {
      feature.updateAssociations(features);
    })
  }

  window.importData = (data) => {
    if (data.features && data.features.length > 0) {
      data.features.forEach(feature => {
        let obj;
        if (feature.properties.type === "secteur" || feature.properties.type === undefined) {
          if (Secteur.isInvalidSecteur(feature)) return;
          obj = new Secteur(map, feature);
        } else if (feature.properties.type === "approche") {
          obj = new Approche(map, feature);
        } else if (feature.properties.type === "acces_velo") {
          obj = new AccesVelo(map, feature);
        } else if (feature.properties.type === "parking") {
          obj = new Parking(map, feature);
        }
        if (obj) {
          featureMap[obj.layer._leaflet_id] = obj;
          createAndBindPopup(obj.layer);
          if (obj instanceof Secteur && obj.label) {
            createAndBindPopup(obj.label.layer, obj.layer);
          }
        }
      });
      updateAssociations();
    }
  }

  const featureMap = {};
  fetch(`/api/private/falaise_details.php?falaise_id=${falaise.falaise_id}`).then(response => {
    if (!response.ok) {
      throw new Error("Erreur lors de la récupération des détails de la falaise");
    }
    return response.json();
  })
    .then(data => importData(data))
    .catch(error => {
      console.error("Erreur lors du chargement des données de falaise :", error);
    });

  const uploadInput = document.getElementById('uploadGeoJSONInput');
  document.getElementById('uploadGeoJSONButton').addEventListener('click', () => {
    uploadInput.click();
  });
  uploadInput.addEventListener('change', function (event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();

    reader.onload = function (e) {
      try {
        const geojson = JSON.parse(e.target.result);
        if (!geojson.type || !geojson.features) {
          throw new Error("This doesn't look like a valid GeoJSON file.");
        }
        Object.keys(featureMap).forEach(key => {
          const feature = featureMap[key];
          map.removeLayer(feature.layer);
          feature.cleanUp();
          delete featureMap[key];
        });
        importData(geojson);

        // You can call a displayGeoJSON(geojson) here if you're using Leaflet
      } catch (err) {
        alert("Erreur lors du chargement du fichier GeoJSON : " + err.message);
      }
    };
    reader.readAsText(file);

  });

  const toGeoJSON = () => ({
    type: "FeatureCollection",
    features: Object.values(featureMap).map(feature => ({ ...feature.layer.toGeoJSON(), properties: feature.layer.properties })),
  })

  document.getElementById("downloadGeoJSON").addEventListener("click", () => {
    const geojson = toGeoJSON();
    const blob = new Blob([JSON.stringify(geojson, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${falaise.falaise_id}_${falaise.falaise_nomformate}.geojson`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });
  const saveBtn = document.getElementById("saveGeoJSON");
  const saveFn = () => {
    if (confirm("Êtes-vous sûr de vouloir enregistrer les données ? Cela écrasera les données existantes.")) {
      saveBtn.classList.add("btn-disabled");
      const geojson = toGeoJSON();
      fetch(`/api/private/falaise_details.php?falaise_id=${falaise.falaise_id}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer <?= $token ?>`,
        },
        body: JSON.stringify(geojson),
      }).then(response => {
        if (!response.ok) {
          throw new Error("Erreur lors de l'enregistrement des données");
        }
        return response.json();
      }).then(data => {
        saveBtn.classList.remove("btn-disabled");
        saveBtn.classList.add("btn-accent");
        const content = saveBtn.textContent;
        saveBtn.textContent = "Enregistré !";
        setTimeout(() => {
          saveBtn.classList.remove("btn-accent");
          saveBtn.textContent = content;
        }, 2000);
      }).catch(error => {
        alert("Erreur lors de l'enregistrement des données : " + error.message);
      });
    }
  }
  saveBtn.addEventListener("click", saveFn);
  document.addEventListener("keydown", (event) => {
    if (event.key === "s" && (event.ctrlKey || event.metaKey)) {
      event.preventDefault();
      saveFn();
    }
  });

  document.getElementById("setFalaiseLatLng").addEventListener("click", () => {
    const latlngInput = document.getElementById("falaise_latlng").value;
    if (!latlngInput) {
      return;
    }
    const [lat, lng] = latlngInput.split(",").map(parseFloat);
    if (isNaN(lat) || isNaN(lng)) {
      alert("Latitude et longitude invalides.");
      return;
    }
    map.setView([lat, lng], 17);
    const marker = L.circle([lat, lng], {
      icon: L.divIcon({
        iconSize: [24, 24],
        iconAnchor: [12, 12],
      }),
    }).addTo(map);
    map.on("click", () => {
      map.removeLayer(marker);
    });
  });

  window.showTableau = () => {
    const tableauModal = document.getElementById("tableau_modal");
    tableauModal.showModal();
    const tableauRecap = document.getElementById("tableauRecap");
    tableauRecap.innerHTML = "";
    const features = Object.values(featureMap);
    if (features.length === 0) {
      tableauRecap.innerHTML = "<p>Aucun élément à afficher.</p>";
      return;
    }
    features.sort((a, b) => {
      const typeA = a.layer.properties.type || "secteur";
      const typeB = b.layer.properties.type || "secteur";
      return typeB.localeCompare(typeA);
    });
    let lastType = null;
    features.forEach(feature => {
      const field = (key, label) => {
        return `
            <input
              type="text"
              name="${key}"
              class="input-${feature.layer._leaflet_id} input input-xs input-bordered"
              value="${(feature.layer.properties[key] || '').replace(/"/g, "&quot;")}"
            />
          `
      }
      if (lastType !== (feature.layer.properties.type || "secteur")) {
        if (lastType) {
          tableauRecap.innerHTML += `<hr class="my-2">`;
        }
        lastType = feature.layer.properties.type || "secteur";
        tableauRecap.innerHTML += `<h4 class="text-lg font-bold capitalize">${lastType || "Secteur"}</h4>`;
        switch (feature.layer.properties.type) {
          case "secteur":
          case undefined:
            tableauRecap.innerHTML += `
            <div class="grid grid-cols-[1fr_1fr_1fr_1fr_1fr_1fr_48px] items-center gap-2">
              <div class="text-sm">Nom</div>
              <div class="text-sm">Description</div>
              <div class="text-sm">Parkings</div>
              <div class="text-sm">Approches</div>
              <div class="text-sm">GV</div>
              <div class="text-sm">Type</div>
              <div></div>
            </div>
            `;
            break;
          case "approche":
            tableauRecap.innerHTML += `
            <div class="grid grid-cols-[1fr_1fr_1fr_1fr_48px] items-center gap-2">
              <div class="text-sm">Nom</div>
              <div class="text-sm">Description</div>
              <div class="text-sm">Parkings</div>
              <div class="text-sm">Type</div>
              <div></div>
            </div>
            `;
            break;
          case "parking":
            tableauRecap.innerHTML += `
            <div class="grid grid-cols-[1fr_1fr_1fr_1fr_48px] items-center gap-2">
              <div class="text-sm">Nom</div>
              <div class="text-sm">Description</div>
              <div class="text-sm">Accès Vélo</div>
              <div class="text-sm">Type</div>
              <div></div>
            </div>
            `;
            break;
          case "acces_velo":
            tableauRecap.innerHTML += `
            <div class="grid grid-cols-[1fr_1fr_1fr_48px] items-center gap-2">
              <div class="text-sm">Nom</div>
              <div class="text-sm">Description</div>
              <div class="text-sm">Type</div>
              <div></div>
            </div>
            `;
            break;
        }
      }
      switch (feature.layer.properties.type) {
        case "secteur":
        case undefined:
          tableauRecap.innerHTML += `
            <div class="grid grid-cols-[1fr_1fr_1fr_1fr_1fr_1fr_48px] items-center gap-2">
              ${field("name", "Nom")}
              ${field("description", "Description")}
              ${field("parking", "Parkings")}
              ${field("approche", "Approches")}
              ${field("gv", "GV")}
              ${field("type", "Type")}
              <button class="btn btn-xs btn-primary" onclick="updateLayer(${feature.layer._leaflet_id})">
                <svg class="w-5 h-5 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-save-3-fill"></use>
                </svg>
              </button>
            </div>
            `;
          break;
        case "approche":
          tableauRecap.innerHTML += `
            <div class="grid grid-cols-[1fr_1fr_1fr_1fr_48px] items-center gap-2">
              ${field("name", "Nom")}
              ${field("description", "Description")}
              ${field("parking", "Parkings")}
              ${field("type", "Type")}
              <button class="btn btn-xs btn-primary" onclick="updateLayer(${feature.layer._leaflet_id})">
                <svg class="w-5 h-5 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-save-3-fill"></use>
                </svg>
              </button>
            </div>
            `;
          break;
        case "parking":
          tableauRecap.innerHTML += `
            <div class="grid grid-cols-[1fr_1fr_1fr_1fr_48px] items-center gap-2">
              ${field("name", "Nom")}
              ${field("description", "Description")}
              ${field("itineraire_acces", "Accès Vélo")}
              ${field("type", "Type")}
              <button class="btn btn-xs btn-primary" onclick="updateLayer(${feature.layer._leaflet_id})">
                <svg class="w-5 h-5 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-save-3-fill"></use>
                </svg>
              </button>
            </div>
            `;
          break;
        case "acces_velo":
          tableauRecap.innerHTML += `
            <div class="grid grid-cols-[1fr_1fr_1fr_48px] items-center gap-2">
              ${field("name", "Nom")}
              ${field("description", "Description")}
              ${field("type", "Type")}
              <button class="btn btn-xs btn-primary" onclick="updateLayer(${feature.layer._leaflet_id})">
                <svg class="w-5 h-5 fill-current">
                  <use xlink:href="/symbols/icons.svg#ri-save-3-fill"></use>
                </svg>
              </button>
            </div>
            `;
          break;
      }
    });
  }
</script>


</html>