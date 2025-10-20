<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Détecteur de falaises intéressantes - Vélogrimpe.fr</title>
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
  <main class="py-4 px-2 md:px-8">
    <div class="flex flex-col gap-1">
      <div id="map" class="w-full h-[calc(100dvh-130px)]"></div>
    </div>
  </main>
  <?php include $_SERVER['DOCUMENT_ROOT'] . "/components/footer.html"; ?>
</body>
<script type="module">
  import * as BaseMaps from '/js/components/map/basemap.js';

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
    if (distance < 1000) return "#00ff00"; // Green for < 500m
    if (distance < 4000) return "#88ff00"; // Yellow for 500m - 1km
    if (distance < 7000) return "#ffa500"; // Orange for 1km - 5km
    return "#ff4400"; // Red for > 5km
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
        return L.circleMarker(latlng, {
          radius: distanceToSize(parseFloat(feature.properties.gare_dist)),
          fillColor: distanceToColor(parseFloat(feature.properties.gare_dist)),
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