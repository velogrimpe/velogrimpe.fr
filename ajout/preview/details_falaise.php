<?php

$falaise_id = $_GET['falaise_id'] ?? null;
if (empty($falaise_id)) {
  echo 'Pas de falaise renseignée.';
  exit;
}

require_once "../../database/velogrimpe.php";

$stmtF = $mysqli->prepare("SELECT
  f.falaise_id,
  f.falaise_nom,
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
  <!-- <script src="https://cdn.jsdelivr.net/npm/@turf/turf@7/turf.min.js"></script> -->
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
      <a class="btn btn-sm" href="/falaise.php?falaise_id=<?php echo $falaise['falaise_id']; ?>">Voir la
        falaise</a>
      <button class="btn btn-sm" onclick="alert('Pas encore implémenté')">Télécharger le
        GeoJSON</button>
      <button class="btn btn-primary btn-sm" onclick="alert('Pas encore implémenté')">Enregistrer</button>
    </div>
    <div class="flex flex-col gap-1">
      <div id="map" class="w-full h-[calc(100vh-180px)]"></div>
    </div>
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

  const zoom = 15;
  // Récupération des données
  const falaise = <?php echo json_encode($falaise); ?>;
  const velos = <?php echo json_encode($velos); ?>;
  const center = falaise.falaise_latlng.split(",").map(parseFloat);

  const barresStyles = (type) => ({
    color: "#333",
    weight: type === "Polygon" ? 1 : 6,
    className: "cursor-grab",
  });
  const approcheStyle = {
    color: "blue",
    weight: 2,
    dashArray: "5 5",
  };
  const textPathOptions = { repeat: true, offset: 8, below: false };

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
  map.pm.Toolbar.createCustomControl({
    name: "Approche",
    block: "draw",
    title: "Ajouter un itinéraire d'approche",
    className: "vg-icon vg-draw-approche",
    toggle: false,
    onClick: () => {
      map.pm.enableDraw("Line", {
        snappable: true,
        snapDistance: 20,
        pathOptions: approcheStyle,
        templineStyle: approcheStyle,
        hintlineStyle: approcheStyle,
        type: "approche",
      });
    },
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
            pathOptions: barresStyles("Line"),
            templineStyle: barresStyles("Line"),
            hintlineStyle: barresStyles("Line"),
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
            pathOptions: barresStyles("Polygon"),
            templineStyle: barresStyles("Polygon"),
            hintlineStyle: barresStyles("Polygon"),
            type: "secteur",
          });
        },
      },
    ],
  });


  map.on("pm:create", e => {
    const { layer, shape } = e;
    // console.log("Création de la forme :", shape, layer);
    const type = layer.pm.options.type;
    layer.properties = { type };
    if ((type === "secteur" || type === undefined) && shape === "Line") {
      layer.setText("-", textPathOptions)
    } else if (type === "acces_velo") {
      AccesVelo.fromLayer(map, layer);
    }
    createAndBindPopup(layer);
  })


  // Save function in global scope for simplicity
  window.updateLayer = function (id) {
    console.log("Updating layer with ID:", id);
    const layer = layersMap[id];
    document.querySelectorAll(`.input-${id}`).forEach(input => {
      const propertyName = input.name;
      if (propertyName && layer) {
        layer.properties[propertyName] = input.value;
      }
    });
    layer.closePopup();
    createAndBindPopup(layer);
  };

  window.invertLine = function (id) {
    const layer = layersMap[id];
    const coords = layer.getLatLngs();
    if (coords.length > 1) {
      coords.reverse();
      layer.setLatLngs(coords);
    }
  }

  window.deleteFeature = (id) => {
    if (confirm("Êtes-vous sur de vouloir supprimer cet élément ?")) {
      const layer = layersMap[id];
      map.removeLayer(layer);
    }
  }
  window.createAndBindPopup = (layer) => {
    const id = layer._leaflet_id;
    let popupHtml = "";
    const field = (name, placeholder) => {
      return `<label for="${name}" class="w-full flex gap-2 items-center">${placeholder}: <input type="text" name="${name}" class="input-${id} input input-xs input-primary flex-1" value="${layer.properties[name] || ''}" placeholder="${placeholder}"></label>`
    }
    popupHtml += `<div class="w-[300px] flex flex-col gap-1 justify-stretch mx-auto">`;
    popupHtml += field("name", "Nom");
    popupHtml += field("description", "Description");
    if (layer.properties.type === "secteur" || layer.properties.type === undefined) {
      popupHtml += field("parking", "Parkings");
      popupHtml += field("approche", "Approches");
    } else if (layer.properties.type === "approche") {
      popupHtml += field("parking", "Parkings");
    } else if (layer.properties.type === "parking") {
      popupHtml += field("itineraire_acces", "Accès vélo");
    } else if (layer.properties.type === "acces_velo") {
    }
    popupHtml += `<div class="flex flex-row gap-1 justify-between">`;
    popupHtml += `<button class="flex-1 btn btn-xs btn-error text-base-100" onclick="deleteFeature(${id})">Suppr.</button>`;
    if ((layer.properties.type === "secteur" || layer.properties.type === undefined) && layer instanceof L.Polyline) {
      popupHtml += `<button class="flex-1 btn btn-xs btn-secondary" onclick="invertLine(${id})">Inverser</button>`;
    }
    popupHtml += `<button class="flex-1 btn btn-xs btn-primary" onclick="updateLayer(${id})">Save</button>`
    popupHtml += `</div>`;
    popupHtml += `</div>`;
    layer.bindTooltip(JSON.stringify(layer.properties));
    layer.bindPopup(popupHtml, {
      className: "w-[350px]",
      minWidth: 300,
      maxWidth: 350,
    });
  }

  const layersMap = {};
  let features = {};
  fetch(`/api/private/falaise_details.php?falaise_id=${falaise.falaise_id}`).then(response => {
    if (!response.ok) {
      throw new Error("Erreur lors de la récupération des détails de la falaise");
    }
    return response.json();
  }).then(data => {
    if (data.features && data.features.length > 0) {
      data.features.forEach(feature => {
        let layer;
        if (feature.properties.type === "secteur" || feature.properties.type === undefined) {
          if (feature.geometry.type === "Polygon") {
            layer = L.polygon(feature.geometry.coordinates.map(rings => {
              return rings.map(coord => [coord[1], coord[0]]);
            }), barresStyles("Polygon"));
          } else if (feature.geometry.type === "LineString") {
            layer = L.polyline(feature.geometry.coordinates.map(coord => [coord[1], coord[0]]), barresStyles("Line"));
            layer.setText("-", textPathOptions)
          }
        } else if (feature.properties.type === "approche") {
          layer = L.polyline(feature.geometry.coordinates.map(coord => [coord[1], coord[0]]), approcheStyle);
        } else if (feature.properties.type === "acces_velo") {
          new AccesVelo(map, feature);
        } else if (feature.properties.type === "parking") {
          layer = L.marker([feature.geometry.coordinates[1], feature.geometry.coordinates[0]], { icon: markerIcon });
        }
        if (layer) {
          layer.properties = feature.properties;
          layer.addTo(map);
          layersMap[layer._leaflet_id] = layer;
          createAndBindPopup(layer);
        }
      });
    }
  }).catch(error => {
    console.error("Erreur lors du chargement des données de falaise :", error);
  });
</script>


</html>