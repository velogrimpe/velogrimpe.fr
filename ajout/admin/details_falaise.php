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
  </style>
</head>

<body>
  <?php include "../../components/header.html"; ?>
  <main class="py-4 px-2 md:px-8">
    <div class="flex flex-col gap-1">
      <div id="map" class="w-full h-[calc(100dvh-120px)]"></div>
    </div>
  </main>
  <?php include "../../components/footer.html"; ?>
</body>
<script>
  // Paramètres généraux
  const iconSize = 24;
  const falaiseIcon = (size, className) =>
    L.icon({
      iconUrl: "/images/icone_falaise_carte.png",
      iconSize: [size, size],
      iconAnchor: [size / 2, size],
      className,
    });
  const iconFalaise = falaiseIcon(iconSize);

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

  var selected = null;
  const teardown = () => {
    selected = null;
  };

</script>
<script>
  class Falaise {
    constructor(falaise, map, className = "faded") {
      this.falaise = falaise;
      this.map = map;
      this.className = className;
      this.marker = L.marker(
        falaise.falaise_latlng.split(",").map(parseFloat),
        { icon: iconFalaise, pmIgnore: true }
      ).addTo(map);
      this.marker.bindPopup(this.getPopupContent());
      this.marker.on("click", () => {
        if (selected) {
          teardown();
        }
        selected = this;
      });
    }

    getPopupContent() {
      return `<strong>${this.falaise.falaise_nom}</strong><br>
              <a href="/falaise.php?falaise_id=${this.falaise.falaise_id}">Voir la falaise</a>`;
    }
  }
</script>

<script>
  const zoom = 15;
  // Récupération des données
  const falaise = <?php echo json_encode($falaise); ?>;
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
  L.control.scale({ position: "bottomright", metric: true, imperial: false, maxWidth: 125 }).addTo(map);
  // enable global Edit Mode
  // const pmOptions = {
  //   position: 'topleft',
  //   drawMarker: true,
  //   drawPolyline: true,
  //   drawPolygon: true,
  //   drawCircle: false,
  //   drawRectangle: false,
  //   cutPolygon: false,
  //   editMode: true,
  //   dragMode: true,
  //   removalMode: true,
  // }
  // map.pm.enableGlobalEditMode(pmOptions);
  // map.pm.enableDraw("Polygon", {
  //   snappable: true,
  //   snapDistance: 20,
  // });
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

  // PANNEAU D'INFORMATION SUR LA FALAISE/GARE SELECTIONNEE
  const falaiseObject = new Falaise(falaise, map);

  map.on("click", function (e) {
    if (selected) {
      teardown();
    }
  });

  map.on("pm:create", e => {
    const { layer, shape } = e;
    // console.log("Création de la forme :", shape, layer);
    console.log("Type", shape, layer.pm.options.type)
    layer.properties = { type: layer.pm.options.type };
  })


  fetch(`/api/private/falaise_details.php?falaise_id=${falaise.falaise_id}`).then(response => {
    if (!response.ok) {
      throw new Error("Erreur lors de la récupération des détails de la falaise");
    }
    return response.json();
  }).then(data => {
    // console.log("Données de falaise :", data);
    if (data.features && data.features.length > 0) {
      data.features.forEach(feature => {
        let layer;
        if ((feature.properties.type === "secteur" || feature.properties.type === undefined) && feature.geometry.type === "Polygon") {
          layer = L.polygon(feature.geometry.coordinates[0], barresStyles("Polygon"));
        } else if ((feature.properties.type === "secteur" || feature.properties.type === undefined) && feature.geometry.type === "LineString") {
          layer = L.polyline(feature.geometry.coordinates.map(coord => [coord[1], coord[0]]), barresStyles("Line"));
        } else if (feature.properties.type === "approche") {
          layer = L.polyline(feature.geometry.coordinates.map(coord => [coord[1], coord[0]]), approcheStyle);
        } else if (feature.geometry.type === "Point") {
          layer = L.marker([feature.geometry.coordinates[1], feature.geometry.coordinates[0]], { icon: markerIcon });
        }
        if (layer) {
          layer.properties = feature.properties;
          layer.addTo(map);
          layer.bindPopup(`<div><strong>${feature.properties.name || "Unnamed"}</strong>${JSON.stringify(feature.properties)}</div>`);
        }
      });
    }
  }).catch(error => {
    console.error("Erreur lors du chargement des données de falaise :", error);
  });
</script>


</html>