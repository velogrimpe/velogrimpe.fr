<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/database/velogrimpe.php";

$falaisesVG = $mysqli->query("SELECT * FROM falaises WHERE falaise_public >= 1")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <meta name="description"
    content="Falaises proches d'une gare en france. 1134 falaises à moins de 10km d'une gare SNCF.">
  <meta property="og:locale" content="fr_FR">
  <meta property="og:title" content="Velogrimpe.fr - Falaises prioritaires">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Velogrimpe.fr">
  <meta property="og:url" content="https://velogrimpe.fr/">
  <meta property="og:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta property="og:description"
    content="Falaises proches d'une gare en france. 1134 falaises à moins de 10km d'une gare SNCF.">
  <meta name="twitter:image" content="https://velogrimpe.fr/images/mw/velogrimpe-social-60.webp">
  <meta name="twitter:title"
    content="<?= htmlspecialchars(mb_strtoupper($falaise_nom, 'UTF-8')) ?><?php if ($ville_id_get): ?> au départ de <?= htmlspecialchars($selected_ville_nom) ?><?php endif; ?> - Velogrimpe.fr">
  <meta name="twitter:description"
    content="Falaises proches d'une gare en france. 1134 falaises à moins de 10km d'une gare SNCF.">
  <title>Falaises prioritaires - Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js "></script>
  <link href=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css " rel="stylesheet">
  <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
  <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css'
    rel='stylesheet' />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="/index.css" />
  <link rel="manifest" href="/site.webmanifest" />
  <style>
    /* Remove leaflet popup styles */
    .leaflet-popup-content-wrapper,
    .leaflet-popup-tip {
      background: transparent !important;
      color: unset !important;
      box-shadow: unset !important;
    }

    .leaflet-popup-content-wrapper {
      padding: 0 !important;
      text-align: left !important;
      border-radius: 0 !important;
    }

    .leaflet-popup-content {
      margin: 0 !important;
    }
  </style>
</head>

<body>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/header.html"; ?>
  <main class="">
    <div class="hero min-h-[100px] bg-bottom"
      style="background-image: url(/images/mw/078-groupe-5.webp); background-position-y: 200px;">
      <div class="hero-overlay bg-opacity-60"></div>
      <div class="hero-content text-center text-base-100">
        <div class="">
          <h1 class="text-5xl font-bold">Falaises prioritaires Vélogrimpe</h1>
        </div>
      </div>
    </div>
    <p class="mx-auto max-w-4xl">Voici une carte des falaises prioritaires, situées à moins de 10 km d'une gare. Elles
      peuvent être de sérieuses candidates pour être ajoutées dans la base de données de Velogrimpe. Attention tout de
      même aux doublons.</p>
    <div class="flex flex-col gap-1 p-2">
      <div id="map" class="w-full h-[calc(100dvh-260px)]"></div>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>
<script type="module">
  import * as BaseMaps from '/js/components/map/basemap.js';
  import Falaise from "/js/components/map/falaise.js";

  const center = [45.391, 5.420]
  const zoom = 6.5;

  const id = "map";
  const map = BaseMaps.initVGMap(id, center, zoom);
  // var info = L.control({ position: 'topright' });
  // info.onAdd = function (map) {
  //   this._div = L.DomUtil.create('div', 'info p-2 bg-base-200 rounded-box shadow-md w-64 max-w-xs'); // create a div with a class "info"
  //   this.update();
  //   return this._div;
  // };

  function distanceToColor(distance) {
    // Define color thresholds based on distance (in meters)
    if (distance < 2000) return "forestgreen"; // Green for < 500m
    if (distance < 4000) return "orange"; // Yellow for 500m - 1km
    if (distance < 6000) return "tomato"; // Orange for 1km - 5km
    return undefined; // Red for > 5km
  }
  function distanceToSize(distance) {
    // 0 = 6 --> 10000 = 2
    if (distance >= 10000) return 2;
    return 6 - (4 * (distance / 10000));
  }
  const halo = "[text-shadow:-1px_-1px_0_#fff,1px_-1px_0_#fff,-1px_1px_0_#fff,1px_1px_0_#fff,0_1px_0_#fff,0_-1px_0_#fff,1px_0_0_#fff,-1px_0_0_#fff]";


  let names = [];
  let gares = new Set();
  function fetchData(url) {
    return fetch(url)
      .then(response => response.json())
      .catch(error => console.error('Error fetching data:', error));
  }
  fetchData('/bdd/ca/10km.geojson').then(data => {
    /**
     * Format example
     * { "type": "Feature", "properties": { "falaise_nom": "L'horloge", "falaise_caid": 6296, "falaise_latlng": "44.79125061350171,6.554983556270599", "lat": 44.79125061350171, "lng": 6.554983556270599, "gares": "", "gare_loc": "44.7909832093243,6.556249737151198", "gare_dist": 104.51012306 }, "geometry": { "type": "Point", "coordinates": [6.554983556270599, 44.79125061350171] } }
     */
    const geojsonLayer = L.geoJSON(data, {
      attribution: "ClimbingAway",
      onEachFeature: function (feature, layer) {

        gares.add({ nom: feature.properties.gare, latlng: feature.properties.gare_loc });
        names.push(L.marker(layer.getLatLng(), {
          icon: L.divIcon({
            className: 'relative',
            html: `<div class="absolute top-1 w-96 flex justify-center -translate-x-1/2 text-lg text-primary ${halo}">${feature.properties.falaise_nom}</div>`,
            iconSize: [0, 0],
            iconAnchor: [0, 0]
          })
        }));
      },
      pointToLayer: function (feature, latlng) {
        const fillColor = distanceToColor(parseFloat(feature.properties.gare_dist));
        if (!fillColor) return null;
        return L.circleMarker(latlng, {
          radius: distanceToSize(parseFloat(feature.properties.gare_dist)),
          fillColor,
          color: "#000",
          weight: 1,
          opacity: 1,
          fillOpacity: 0.8
        })
          .bindPopup(
            `<div class="p-2 bg-base-200 rounded-box shadow-md w-96 max-w-xs flex flex-col gap-1">`
            + `<div class="text-base text-primary">${feature.properties.falaise_nom}</div>`
            + `<div>Coordonnées: ${feature.properties.falaise_latlng.split(',').map(coord => parseFloat(coord).toFixed(6)).join(',')}</div>`
            + `<div>À ${Math.round(feature.properties.gare_dist)} m de la gare de <b>${feature.properties.gare}</b></div>`
            + `<div class="flex justify-end mt-1"><a href="/ajout/ajout_falaise.php" class="btn btn-primary btn-sm" target="_blank">Ajouter à velogrimpe</a></div>`
            + `</div>`, { closeButton: false }
          )
          .on('click', () => map.setView(latlng, 12));
      }
    });
    map.fitBounds(geojsonLayer.getBounds());
    gares.forEach(gare => {
      const latlng = gare.latlng.split(',').map(Number);
      L.circleMarker([latlng[0], latlng[1]], {
        radius: 4,
        fillColor: "#0000ff",
        color: "#000",
        weight: 1,
        opacity: 0.8,
        fillOpacity: 0.6
      }).addTo(map);
      names.push(L.marker([latlng[0], latlng[1]], {
        icon: L.divIcon({
          className: 'relative',
          html: `<div class="absolute top-1 w-96 flex justify-center -translate-x-1/2 text-base ${halo}">${gare.nom}</div>`,
          iconSize: [0, 0],
          iconAnchor: [0, 0]
        })
      }))
    });
    geojsonLayer.addTo(map)
  });

  const falaisesVG = (<?php echo json_encode($falaisesVG); ?>).map(falaise => {
    names.push(L.marker(falaise.falaise_latlng.split(',').map(Number), {
      icon: L.divIcon({
        className: 'relative',
        html: `<div class="absolute top-0 w-96 flex justify-center -translate-x-1/2 text-normal text-primary ${halo}">[VG] ${falaise.falaise_nom}</div>`,
        iconSize: [0, 0],
        iconAnchor: [0, 0]
      })
    }))
    return new Falaise(map, falaise, { visibility: { from: 11, to: 20 } })
  }
  );

  map.on('zoomend', function () {
    const currentZoom = map.getZoom();
    names.forEach(marker => {
      if (currentZoom < 12) {
        marker.remove();
      } else {
        marker.addTo(map);
      }
    });
  });

</script>

</html>