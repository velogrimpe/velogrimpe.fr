<?php
require_once "../../database/velogrimpe.php";
$config = require $_SERVER['DOCUMENT_ROOT'] . '/../config.php';
$token = $config["admin_token"];

$falaises = $mysqli->query("SELECT
  f.falaise_id,
  f.falaise_nom,
  f.falaise_latlng,
  group_concat(distinct fl.site_id SEPARATOR ',') as site_ids
  FROM falaises f
  LEFT JOIN falaises_liens fl on fl.falaise_id = f.falaise_id and fl.site = 'oblyk'
  LEFT JOIN velo v on v.falaise_id = f.falaise_id
  GROUP BY f.falaise_id
  HAVING count(v.velo_id) >= 1
")->fetch_all(MYSQLI_ASSOC);
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
  <title>Correspondances Oblyk - Vélogrimpe.fr</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js "></script>
  <link href=" https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css " rel="stylesheet">
  <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/Leaflet.fullscreen.min.js'></script>
  <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-fullscreen/v1.0.1/leaflet.fullscreen.css'
    rel='stylesheet' />
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>

  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <link rel="stylesheet" href="/index.css" />
  <link rel="manifest" href="/site.webmanifest" />

</head>

<body>
  <?php include "../../components/header.html"; ?>
  <main class="py-4 px-2 md:px-8">
    <div class="flex flex-col gap-1">
      <div id="map" class="w-full h-[calc(100dvh-120px)]"></div>
    </div>
  </main>
  <datalist id="falaises">
    <?php foreach ($falaises as $falaise): ?>
      <option value="<?= $falaise["falaise_nom"] ?> (falaise)"></option>
    <?php endforeach; ?>
  </datalist>
  <?php include "../../components/footer.html"; ?>
</body>
<script>

  function isSamsungInternet() {
    return navigator.userAgent.includes("SamsungBrowser");
  }

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
  const oblykIcon = (size, className) =>
    L.icon({
      iconUrl: "https://oblyk.org/favicon.ico",
      iconSize: [size, size],
      iconAnchor: [size / 2, size],
      className: "rounded-full" + (className ? ` ${className}` : ""),
    });
  const iconOblyk = oblykIcon(iconSize);

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
    if (falaisesOblyk) {
      falaisesOblyk.forEach(falaise => {
        falaise.marker.remove();
        if (falaise.button) falaise.button.remove();
      });
      falaisesOblyk = [];
    }
    info.update();
  };

  function renderFalaisesOblyk(falaises, map) {
    if (selected)
      falaises.forEach(falaise => {
        const isAlreadyLinked = selected.site_ids && selected.site_ids.includes(falaise.id);
        if (isAlreadyLinked) {
          console.log("Already linked falaise:", falaise.name);
        }
        const marker = L.marker(
          falaise.latlng,
          {
            icon: isAlreadyLinked ? oblykIcon(iconSize, "invert") : iconOblyk,
            riseOnHover: true,
            autoPanOnFocus: true,
          }
        ).addTo(map);
        falaise.marker = marker;
        falaise.button = isAlreadyLinked ? null : L.marker(
          falaise.latlng,
          {
            icon: L.divIcon({
              className: "btn btn-accent btn-xs text-base-100! hover:text-base-100! p-[1px] rounded-full",
              iconSize: [24, 24],
              iconAnchor: [24 / 2, 0],
              html: `<div id="linkFalaises_${selected.falaise_id}_${falaise.id}"><svg class="w-3 h-3 fill-current"><use xlink:href="/symbols/icons.svg#ri-link"></use></svg></div>`,
            }),
            riseOnHover: true,
            autoPanOnFocus: true,
          }
        ).addTo(map);
        marker.bindTooltip(falaise.name, {
          className: "p-[1px]",
          direction: "left",
          offset: [-iconSize / 2, -iconSize / 2],
          permanent: true,
        }
        );
        console.log("Falaise marker:", `linkFalaises_${selected.falaise_id}_${falaise.id}`);
        if (!isAlreadyLinked) {
          document.getElementById(`linkFalaises_${selected.falaise_id}_${falaise.id}`).addEventListener("click", (e) => {
            const linkFalaises = () => {
              const falaiseId = selected.falaise_id;
              const slug = falaise.slug;
              const id = falaise.id;
              const oblykUrl = `https://oblyk.org/crags/${id}/${slug}`
              const url = `/ajout/link_falaise.php`;
              fetch(url, {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                  "Authorization": "<?= $token ?>",
                },
                body: JSON.stringify({
                  falaise_id: falaiseId,
                  site_url: oblykUrl,
                  site_id: id,
                  site: "oblyk",
                  site_name: falaise.name,
                }),
              })
                .then(response => response.json())
                .then(data => {
                  if (!data) {
                    alert("Error linking falaise.");
                  } else {
                    console.log("Falaise linked successfully:", data);
                    falaise.button.remove();
                    falaise.marker.setIcon(oblykIcon(iconSize, "invert"));
                    selected.site_ids = selected.site_ids ? selected.site_ids + "," + falaise.id : falaise.id;
                    selected.marker.setIcon(falaiseIcon(iconSize, "sepia"));
                    info.update();
                  }
                })
                .catch(error => {
                  console.error("Error:", error);
                });
            }
            e.stopPropagation();
            L.DomEvent.stopPropagation(e);
            e.preventDefault();
            linkFalaises();
          });
        }
      });
  }
  function setFalaiseMarker(falaise, map, mode) {
    const initMarker = () => {
      const marker = L.marker(
        falaise.falaise_latlng.split(","),
        {
          icon: falaise.site_ids ? falaiseIcon(iconSize, "sepia") : iconFalaise,
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
          e.originalEvent?.stopPropagation();
          teardown();
          selected = falaise;
          const [lat, lng] = falaise.falaise_latlng.split(",").map(parseFloat)
          console.log("Selected falaise:", [lat, lng]);
          map.flyTo([lat, lng], 14, { duration: 0.5 });
          fetch(`https://api.oblyk.org/api/v1/public/crags/crags_around?latitude=${lat}&longitude=${lng}&distance=${3}`, {
            method: "GET",
            headers: {
              "Accept": "application/json",
              "HttpApiAccessToken": "nEp8Tge6PgTTkyoPzNza9xxp",
            },
          })
            .then((response) => response.json())
            .then((data) => {
              console.log("Fetched falaises:", data);
              falaisesOblyk = data.map(({ id, slug_name, name, latitude, longitude }) => ({
                id,
                name,
                slug: slug_name,
                latlng: [latitude, longitude],
              }));
              info.update();
              renderFalaisesOblyk(falaisesOblyk, map);
            })
            .catch((error) => {
              console.error("Error fetching falaises:", error);
            });
        }
      });
    }
    initMarker();
  }
</script>

<script>
  const center = [45.391, 5.420]
  const zoom = 6.5;
  // Récupération des données
  const falaises = <?php echo json_encode($falaises); ?>;
  let falaisesOblyk = []

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
    const updateTop = () => {

      const mode = selected === null ? undefined : "falaise";
      switch (mode) {
        case undefined:
          this.top = (
            `<div class="flex flex-col gap-1 max-w-96 items-center border-b-1 border-b-base-300 mb-2">`
            + `<div>Cliquez sur une falaise pour voir ses informations</div>`
            + `</div>`
          );
          break;
        case "falaise":
          this.top = `<div class="flex flex-col gap-1 max-w-96 border-b-1 border-b-base-300 mb-2">`
            + '<div class="flex flex-col md:flex-row justify-between items-center gap-4">'
            + `<h3 class="text-normal text-primary font-bold">${selected.falaise_nom}</h3>`
            + `</div>`
            + `</div>`;
          break;
      }
    }
    const updateBot = () => {
      this.bot = "";
      if (selected && falaisesOblyk.length > 0) {
        this.bot = `<div>`
          + `<div>Falaise(s) Oblyk</div>`
          + `<div>`
          + `<div class="flex flex-col gap-1">`;
        falaisesOblyk.forEach(falaise => {
          const linked = selected.site_ids && selected.site_ids?.includes(falaise.id);
          this.bot += `<div class="ml-4 flex flex-row gap-4 justify-between items-center">`
            + `<div>&bull; <a href="https://oblyk.org/crags/${falaise.id}/${falaise.slug}" target="_blank" class="text-sm font-normal text-accent!">${falaise.name}</a></div>`
            + (linked
              ? `<span class="text-sm text-success">Déjà lié</span>`
              : ``
            )
            + `</div>`;
        });
        this.bot += `</div></div></div>`;
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

  falaises.forEach(falaise => {
    setFalaiseMarker(falaise, map, "faded");
  });

  map.on("click", function (e) {
    if (selected) {
      teardown();
      info.update();
    }
  });

</script>

<script>
  // ============================================ RECHERCHE ============================================

  const baseList = [
    ...falaises.map(f => ({ id: f.falaise_id, type: "falaise", name: f.falaise_nom, item: f })),
  ];
  const searchByNameHandler = (value) => {
    document.getElementById("searchModal").close();
    document.getElementById("map").scrollIntoView({ behavior: "smooth", block: "nearest" });
    const filtered = baseList.find(item => item.name === value);
    if (filtered) {
      if (filtered.item.type === "falaise_hors_topo") {
        setFalaiseHTMarker(filtered.item, map, "faded");
        map.flyTo(filtered.item.falaise_latlng.split(",").map(parseFloat), 12, { duration: 0.5 });
        return;
      }
    }
  }
</script>

<script src="/js/autocomplete.js"></script>
<script>
  // setupAutocomplete("search", "search-list", "falaises", searchByNameHandler);
</script>

</html>