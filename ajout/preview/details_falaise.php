<?php

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
        onchange="window.location.href = '/ajout/preview/details_falaise.php?falaise_id=' + this.value">
        <?php foreach ($falaises as $f): ?>
          <option value="<?= $f['falaise_id'] ?>" <?= $falaise_id == $f["falaise_id"] ? "selected" : "" ?>>
            <?= $f['falaise_nom'] ?> - <?= $f['falaise_id'] ?>
          </option>
        <?php endforeach; ?>
      </select>
      <a class="btn btn-sm" href="/falaise.php?falaise_id=<?php echo $falaise['falaise_id']; ?>">Voir la
        falaise</a>
      <button class="btn btn-sm" id="downloadGeoJSON">Télécharger le GeoJSON</button>
      <button class="btn btn-primary btn-sm" id="saveGeoJSON">Enregistrer</button>
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
        console.log(e);
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
        console.log(currentRoutingPoints);
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
    // console.log("Création de la forme :", shape, layer);
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
    obj.layer.openPopup();
    featureMap[obj.layer._leaflet_id] = obj;
  })


  // Save function in global scope for simplicity
  window.updateLayer = function (id) {
    console.log("Updating layer with ID:", id);
    const feature = featureMap[id];
    const layer = feature.layer;
    document.querySelectorAll(`.input-${id}`).forEach(input => {
      const propertyName = input.name;
      if (propertyName && layer) {
        layer.properties[propertyName] = input.value;
      }
    });
    layer.closePopup();
    createAndBindPopup(layer);
    updateAssociations();
    feature.highlight();
    feature.unhighlight();
  };

  window.invertLine = function (id) {
    const feature = featureMap[id];
    const layer = feature.layer;
    const coords = layer.getLatLngs();
    if (coords.length > 1) {
      coords.reverse();
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
      return `<label for="${name}" class="w-full flex gap-2 items-center"><span class="flex-1 text-right">${label}: </span><input type="text" name="${name}" ${name === "name" ? "autofocus" : ""} class="input-${id} input input-xs input-primary w-48" value="${targetLayer.properties[name] || ''}" placeholder="${placeholder}"></label>`
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
    if ((targetLayer.properties.type === "secteur" || targetLayer.properties.type === undefined) && targetLayer instanceof L.Polyline) {
      popupHtml += `<button class="flex-1 btn btn-xs btn-secondary" onclick="invertLine(${id})">Inverser</button>`;
    }
    popupHtml += `<button class="flex-1 btn btn-xs btn-primary" onclick="updateLayer(${id})">Save</button>`
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

  const featureMap = {};
  fetch(`/api/private/falaise_details.php?falaise_id=${falaise.falaise_id}`).then(response => {
    if (!response.ok) {
      throw new Error("Erreur lors de la récupération des détails de la falaise");
    }
    return response.json();
  }).then(data => {
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
          if (obj instanceof Secteur) {
            createAndBindPopup(obj.label.layer, obj.layer);
          }
        }
      });
      updateAssociations();
    }
  }).catch(error => {
    console.error("Erreur lors du chargement des données de falaise :", error);
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
  document.getElementById("saveGeoJSON").addEventListener("click", () => {
    alert("Cette fonctionnalité n'est pas encore implémentée. Vous pouvez télécharger le GeoJSON pour l'enregistrer localement.");
    // if (confirm("Êtes-vous sûr de vouloir enregistrer les données ? Cela écrasera les données existantes.")) {
    //   const geojson = toGeoJSON();
    //   fetch(`/api/admin/falaise_details.php?falaise_id=${falaise.falaise_id}`, {
    //     method: "POST",
    //     headers: {
    //       "Content-Type": "application/json",
    //     },
    //     body: JSON.stringify(geojson),
    //   }).then(response => {
    //     if (!response.ok) {
    //       throw new Error("Erreur lors de l'enregistrement des données");
    //     }
    //     return response.json();
    //   }).then(data => {
    //     alert("Données enregistrées avec succès !");
    //   }).catch(error => {
    //     console.error("Erreur lors de l'enregistrement des données :", error);
    //     alert("Erreur lors de l'enregistrement des données : " + error.message);
    //   });
    // }
  });

</script>


</html>