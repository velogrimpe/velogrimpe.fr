<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database/velogrimpe.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/vite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/map-bundle.php';

$falaises = $mysqli->query("SELECT falaise_bloc, falaise_cotmax, falaise_cotmin, falaise_exposhort1, falaise_exposhort2, falaise_fermee, falaise_gvnb, falaise_id, falaise_latlng, falaise_maa, falaise_nbvoies, falaise_nom FROM falaises WHERE falaise_public >= 1")->fetch_all(MYSQLI_ASSOC);
$villes = $mysqli->query("SELECT ville_id, ville_nom FROM villes ORDER BY ville_nom")->fetch_all(MYSQLI_ASSOC);
$gares = $mysqli->query("SELECT
  g.gare_id, g.gare_latlng, g.gare_nom, g.gare_tgv,
  GROUP_CONCAT(CONCAT(t.ville_id, '|', t.train_depart, '|', t.train_temps, '|', t.train_correspmin, '|', COALESCE(t.train_tgv, 0)) SEPARATOR '=|=') AS villes
  FROM gares g
  LEFT JOIN train t ON t.gare_id = g.gare_id
  WHERE g.deleted = 0 and g.gare_id in (SELECT DISTINCT gare_id FROM velo WHERE velo_public >= 1)
  GROUP BY g.gare_id;"
)->fetch_all(MYSQLI_ASSOC);
$itineraires = $mysqli->query("SELECT * FROM velo WHERE velo_public >= 1")->fetch_all(MYSQLI_ASSOC);

$highlight = $_GET['h'] ?? '';

?>
<!DOCTYPE html>
<html lang="fr" data-theme="velogrimpe">

<head>
  <meta charset="UTF-8" />
  <title>Vélogrimpe.fr — MapLibre</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Meta tags for SEO and Social Networks -->
  <meta name="robots" content="noindex, nofollow">
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
  <meta name="twitter:title" content="Velogrimpe.fr - Carte des falaises accessibles en vélo et train">
  <meta name="twitter:description"
    content="Escalade en mobilité douce à vélo et en train. Découvrez les accès aux falaises, les topos et les informations pratiques pour une sortie vélo-grimpe.">

  <!-- MapLibre GL JS + PMTiles + GPX protocol (self-hosted bundle, built
       par frontend/build-map.ts). Bundles maplibre-gl + pmtiles +
       maplibre-gl-vector-text-protocol et pré-enregistre les protocols
       `pmtiles://` et `gpx://`. Le filename inclut la version maplibre
       (résolu via dist/map-bundles.json) → cache long-terme safe. -->
  <!-- <script src="https://unpkg.com/maplibre-contour@0.1.0/dist/index.min.js"></script> -->
  <?php map_bundle_css('map-maplibre'); ?>
  <?php map_bundle_js('map-maplibre'); ?>

  <?php vite_css('main'); ?>
  <!-- Pageviews -->
  <script async defer src="/js/pv.js"></script>
  <!-- Shared utilities -->
  <script src="/js/utils-global.js?v=2"></script>
  <!-- Velogrimpe Styles -->
  <link rel="stylesheet" href="/global.css" />
  <!-- Vue Component Styles -->
  <?php vite_css('carte-info'); ?>
  <?php vite_css('carte-map-filters'); ?>
  <link rel="stylesheet" href="./index.css" />
  <link rel="manifest" href="./site.webmanifest" />
  <style>
    /* DOM markers: ancres et hover.
       NE PAS définir `position` ici — MapLibre applique `position: absolute`
       via .maplibregl-marker et le pilote via `transform: translate(...)`.
       Override = markers figés. Les .vg-tip enfants restent en position: absolute,
       le parent marker est déjà positioned (containing block OK). */
    .vg-marker {
      cursor: pointer;
    }

    .vg-marker img {
      display: block;
      pointer-events: none;
    }

    .vg-label {
      pointer-events: none;
      z-index: 5;
      /* labels above plain icon markers by default */
    }

    /* Pour que le tooltip déborde au-dessus des autres markers, on remonte
       le marker entier (chaque marker = stacking context isolé). */
    .vg-marker.vg-marker-focus {
      z-index: 1001;
    }

    /* MapLibre place les corners de controls à z-index:2 par défaut,
       donc nos markers focus (z:1001) leur passent devant. Les markers
       sont des frères des corners dans .maplibregl-map, donc on ne peut
       pas les contenir avec isolation:isolate. Solution : remonter
       explicitement tous les corners au-dessus du focus marker. */
    .maplibregl-ctrl-top-left,
    .maplibregl-ctrl-top-right,
    .maplibregl-ctrl-bottom-left,
    .maplibregl-ctrl-bottom-right {
      z-index: 1500;
    }

    /* .maplibregl-ctrl applique `transform: translate(0)` qui crée un
       containing block pour les descendants absolus → les dropdowns daisyUI
       à l'intérieur du mount Vue se positionnent par rapport à ce mount au
       lieu de leur propre .dropdown parent (gros décalage visible). On
       neutralise le transform + on force position:relative sur les .dropdown
       comme ceinture-bretelles. */
    #vue-map-filters.maplibregl-ctrl {
      transform: none;
      position: relative;
    }

    #vue-map-filters .dropdown {
      position: relative;
    }

    /* MapLibre applique `background-color: rgba(0,0,0,.05)` au :hover de
       tous les <button> dans un .maplibregl-ctrl (specificity 0,3,1) — ça
       masque le fond des boutons daisyUI dans les mounts Vue. On le rend
       inopérant en revertant la valeur sur la cascade. */
    #vue-map-filters button:not(:disabled):hover,
    #vue-info-panel button:not(:disabled):hover {
      background-color: revert-layer;
    }

    .vg-tip {
      position: absolute;
      white-space: nowrap;
      pointer-events: none;
      font-size: 12px;
      z-index: 1000;
    }

    /* Habillage par défaut, ignoré quand un className métier prend le relais
       (vg-station-tooltip / vg-velo-tooltip ont leur propre fond coloré). */
    .vg-tip-default {
      background: white;
      padding: 1px 3px;
      border-radius: 3px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .vg-tip.dir-right {
      left: 100%;
      top: 50%;
      transform: translateY(-50%);
      margin-left: 4px;
    }

    .vg-tip.dir-top {
      left: 50%;
      bottom: 100%;
      transform: translateX(-50%);
      margin-bottom: 4px;
    }

    .vg-tip.dir-center {
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
    }

    .train-icon.filterred {
      filter: hue-rotate(140deg) saturate(2);
    }

    /* Tooltips d'itinéraires vélo : .vg-velo-tooltip (global.css) ne définit que
       padding/couleurs/font-weight. L'original Leaflet héritait du reste via
       .leaflet-tooltip (border-radius, box-shadow, white-space, font-size).
       On le restitue ici pour matcher le rendu d'origine. */
    .vg-velo-tooltip {
      border-radius: 3px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
      white-space: nowrap;
      font-size: 12px;
      line-height: 1.2;
      padding: 2px 4px;
    }

    /* Sélecteur de couches MapLibre */
    .vg-layer-switcher {
      background: white;
      border-radius: 4px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
      font-size: 13px;
      max-width: 260px;
    }

    .vg-layer-switcher summary {
      list-style: none;
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 5px;
      cursor: pointer;
      user-select: none;
      color: #333;
    }

    .vg-layer-switcher summary::-webkit-details-marker {
      display: none;
    }

    .vg-layer-switcher summary::marker {
      content: "";
    }

    /* État fermé : juste l'icône comme un bouton carré */
    .vg-layer-switcher details:not([open]) .vg-layer-switcher-label {
      display: none;
    }

    /* État ouvert : padding autour de la liste */
    .vg-layer-switcher details[open]>div {
      padding: 0 8px 6px;
    }

    .vg-layer-switcher label {
      display: block;
      padding: 2px 0;
      cursor: pointer;
    }

    .vg-layer-switcher hr {
      margin: 6px 0;
      border: none;
      border-top: 1px solid #eee;
    }
  </style>
</head>

<body>
  <?php include "./components/header.html"; ?>
  <main class="">
    <div id="map" class="w-full h-[calc(100dvh-100px)]"></div>
  </main>
  <?php include "./components/footer.php"; ?>
</body>

<script>
  function isSamsungInternet() { return navigator.userAgent.includes("SamsungBrowser"); }

  // ============================================================
  // Constantes
  // ============================================================
  const iconSize = 30;
  const defaultMarkerSize = iconSize;
  const itinerairesColors = ["indianRed", "tomato", "teal", "paleVioletRed", "mediumSlateBlue", "lightSalmon", "fireBrick", "crimson", "purple", "hotPink", "mediumOrchid"];
  const halo = "[text-shadow:-1px_-1px_0_#fff,1px_-1px_0_#fff,-1px_1px_0_#fff,1px_1px_0_#fff,0_1px_0_#fff,0_-1px_0_#fff,1px_0_0_#fff,-1px_0_0_#fff]";

  const falaiseIconUrl = (closed, bloc) =>
    closed ? "/images/map/icone_falaisefermee_carte.png"
      : bloc === "1" ? "/images/map/icone_falaise_carte_bloc.png"
        : bloc === "2" ? "/images/map/icone_falaise_carte_psychobloc.png"
          : "/images/map/icone_falaise_carte.png";

  const gpx_path = (it) => it.velo_id + "_" + it.velo_depart + "_" + it.velo_arrivee + "_" + (it.velo_varianteformate || "") + ".gpx";

  // [lat,lng] string from DB -> [lng,lat] tuple for MapLibre
  const lngLat = (s) => { const [la, ln] = s.split(",").map(parseFloat); return [ln, la]; };
  const bboxOf = (pts) => {
    let w = Infinity, s = Infinity, e = -Infinity, n = -Infinity;
    pts.forEach(([ln, la]) => { if (ln < w) w = ln; if (ln > e) e = ln; if (la < s) s = la; if (la > n) n = la; });
    return [[w, s], [e, n]];
  };

  // ============================================================
  // Basemaps (raster)
  // ============================================================
  const BASEMAPS = {
    Landscape: { url: "https://a.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", maxzoom: 19, attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>' },
    OpenCycleMap: { url: "https://a.tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", maxzoom: 19, attribution: '<a href="http://www.thunderforest.com/opencyclemap/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>' },
    IGNv2: { url: "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&FORMAT=image/png&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}", maxzoom: 19, attribution: "IGN-F/Geoportail" },
    Satellite: { url: "https://data.geopf.fr/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}", maxzoom: 18, attribution: "IGN-F/Geoportail" },
    Outdoors: { url: "https://a.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6", maxzoom: 19, attribution: '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>' },
  };
  // Fond cartographique par défaut : le style vectoriel MapLibre. Les entrées
  // de BASEMAPS sont les fonds raster "alternatifs" qui le remplacent quand on
  // les sélectionne dans le sélecteur de couches.
  const MAPLIBRE_BASEMAP = "Carte vélogrimpe";
  const DEFAULT_RASTER_BASEMAP = "Landscape"; // tuiles initiales de la source `basemap`
  let currentBasemap = MAPLIBRE_BASEMAP;

  // Les protocoles `pmtiles://` et `gpx://` sont enregistrés en amont par
  // /dist/map-maplibre.js (cf. frontend/src/apps/map-maplibre.ts).



  // php import cartes-app-style.json as a JS object, to inject the correct demSource URL in the style's hillshade layer.
  const style = <?php echo json_encode(json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/bdd/styles/ign-plan.json'), true)); ?>;

  // Visibilité d'origine de chaque couche du style vectoriel : permet de la
  // masquer quand on bascule sur un fond raster, puis de la restaurer telle
  // quelle (certaines couches — rando/VTT/ski — sont masquées par défaut).
  const maplibreLayerVisibility = Object.fromEntries(
    style.layers.map((l) => [l.id, (l.layout && l.layout.visibility) || "visible"])
  );

  const terrainSources = {
    "ign-estompage": {
      "type": "raster",
      "tiles": [
        "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=IGNF_ELEVATION.ELEVATIONGRIDCOVERAGE.SHADOW&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/png&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}"
      ],
      "tileSize": 256,
      "minzoom": 6,
      "maxzoom": 17,
      "attribution": "© IGN"
    },
    "ign-contours": {
      "type": "raster",
      "tiles": [
        "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=ELEVATION.CONTOUR.LINE&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/png&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}"
      ],
      "tileSize": 256,
      "minzoom": 6,
      "maxzoom": 18,
      "attribution": "© IGN"
    }
  }

  const styleWithTerrain = {
    ...style,
    sources: {
      ...style.sources,
      ...terrainSources,
      basemap: { type: "raster", tiles: [BASEMAPS[DEFAULT_RASTER_BASEMAP].url], tileSize: 256, maxzoom: BASEMAPS[DEFAULT_RASTER_BASEMAP].maxzoom, attribution: BASEMAPS[DEFAULT_RASTER_BASEMAP].attribution },
    },
  };

  const map = new maplibregl.Map({
    container: "map",
    // style: {
    //   version: 8,
    //   sources: {
    //     basemap: { type: "raster", tiles: [BASEMAPS[currentBasemap].url], tileSize: 256, maxzoom: BASEMAPS[currentBasemap].maxzoom, attribution: BASEMAPS[currentBasemap].attribution },
    //   },
    //   layers: [{ id: "basemap", type: "raster", source: "basemap" }],
    // },
    style: styleWithTerrain,
    center: [5.420, 45.391],
    zoom: 6.5,
  });

  const contourLayers = [
    {
      "id": "estompage",
      "type": "raster",
      "source": "ign-estompage",
      "paint": { "raster-opacity": 0.5 }
    },
    {
      "id": "contours",
      "type": "raster",
      "source": "ign-contours",
      "paint": { "raster-opacity": 0.8 }
    },
  ];

  map.on("load", () => {
    // Couche raster des fonds alternatifs (Landscape, Satellite, IGN…), tout en
    // bas de la pile (avant "Background") et masquée par défaut : le fond actif
    // au démarrage est le style vectoriel MapLibre.
    map.addLayer({ id: "basemap", type: "raster", source: "basemap", layout: { visibility: "none" } }, "Background");
    // Les sources hillshade et contourSource sont déclarées en amont (avant map.on('load')) pour éviter les problèmes de timing liés à l'ajout de sources dynamiques après le chargement de la carte.
    contourLayers.forEach((layer) => map.addLayer(layer, 'River'));
  });


  // Built-in controls
  map.addControl(new maplibregl.NavigationControl({ showCompass: false }), "top-left");
  map.addControl(new maplibregl.FullscreenControl(), "top-left");
  map.addControl(new maplibregl.GeolocateControl({ positionOptions: { enableHighAccuracy: true }, trackUserLocation: true }), "top-left");
  map.addControl(new maplibregl.ScaleControl({ maxWidth: 125, unit: "metric" }), "bottom-left");

  function stopAllPropagation(el) {
    ["mousedown", "dblclick", "wheel", "touchstart", "click"].forEach((ev) =>
      el.addEventListener(ev, (e) => e.stopPropagation())
    );
  }

  // ============================================================
  // Tooltip helper (remplace L.Tooltip)
  // ============================================================
  function attachTooltip(marker, html, opts = {}) {
    if (!marker) return;
    const { permanent = false, direction = "right", className = "" } = opts;
    const el = marker.getElement();
    if (!el) return;
    // Remove existing tip and listeners
    el.querySelectorAll(":scope > .vg-tip").forEach((n) => n.remove());
    if (el._vgTipListeners) {
      el.removeEventListener("mouseenter", el._vgTipListeners.enter);
      el.removeEventListener("mouseleave", el._vgTipListeners.leave);
      el._vgTipListeners = null;
    }
    el.classList.remove("vg-marker-focus");

    const tip = document.createElement("div");
    // Apply default look only when no custom className overrides the chrome
    const useDefaultLook = !className;
    tip.className = `vg-tip dir-${direction}${useDefaultLook ? " vg-tip-default" : ""}${className ? " " + className : ""}`;
    tip.innerHTML = html;
    tip.style.display = permanent ? "block" : "none";
    el.appendChild(tip);

    if (permanent) {
      el.classList.add("vg-marker-focus");
    } else {
      const enter = () => { tip.style.display = "block"; el.classList.add("vg-marker-focus"); };
      const leave = () => { tip.style.display = "none"; el.classList.remove("vg-marker-focus"); };
      el.addEventListener("mouseenter", enter);
      el.addEventListener("mouseleave", leave);
      el._vgTipListeners = { enter, leave };
    }
    marker._vgTip = tip;
  }
  function removeTooltip(marker) {
    if (!marker) return;
    const el = marker.getElement?.();
    if (!el) return;
    el.querySelectorAll(":scope > .vg-tip").forEach((n) => n.remove());
    if (el._vgTipListeners) {
      el.removeEventListener("mouseenter", el._vgTipListeners.enter);
      el.removeEventListener("mouseleave", el._vgTipListeners.leave);
      el._vgTipListeners = null;
    }
    el.classList.remove("vg-marker-focus");
  }
</script>

<script>
  // ============================================================
  // Données injectées par PHP (identique à carte.php)
  // ============================================================
  const falaisesBase = <?php echo json_encode($falaises); ?>;
  const highlightedFalaiseIds = <?= json_encode(explode(',', $highlight)) ?>;
  const itineraires = <?php echo json_encode($itineraires); ?>.map(it => ({ ...it, tempsVelo: calculate_time(it) }));
  const garesBase = <?php echo json_encode($gares); ?>.map(g => {
    g.villes = (g.villes || "")
      .split("=|=")
      .map(v => {
        const [ville_id, ville, durStr, nCorresp, tgv] = v.split("|");
        return { ville_id, ville, temps: parseInt(durStr), nCorresp: parseInt(nCorresp), train_tgv: parseInt(tgv) };
      });
    return g;
  });
  const falaises = falaisesBase.map(f => {
    const access = itineraires.filter(i => i.falaise_id === f.falaise_id).map(it => {
      const gare = garesBase.find(g => g.gare_id === it.gare_id);
      const villes = gare?.villes.map(v => {
        const tempsTrainVelo = v.temps + it.tempsVelo;
        const tempsTotal = tempsTrainVelo + (f.maa || 0);
        return { ...v, tempsTrainVelo, tempsTotal };
      }) || [];
      return { ...it, gare, villes };
    }).sort((a, b) => a.tempsVelo - b.tempsVelo);
    if (highlightedFalaiseIds.includes(f.falaise_id)) f.highlighted = true;
    // `type` calculé en amont (au lieu d'attendre renderFalaises) : sinon
    // info_update() compte 0 si le Vue info-ready arrive avant map.on('load').
    return { ...f, access, type: access.length > 0 ? "falaise" : "falaise_hors_topo" };
  });
  const gares = garesBase.map(g => {
    const access = itineraires.filter(i => i.gare_id === g.gare_id).map(it => {
      const falaise = falaisesBase.find(f => f.falaise_id === it.falaise_id);
      return { ...it, falaise };
    }).sort((a, b) => a.tempsVelo - b.tempsVelo);
    return { ...g, access, type: access.length > 0 ? "gare" : "gare_hors_topo" };
  });

  // ============================================================
  // Sélecteur de couches (custom IControl)
  // ============================================================
  const OVERLAYS = [
    { id: "tgv", label: "Lignes et Gares TGV", layers: ["tgv-line", "tgv-pt", "tgv-label"] },
    { id: "camping", label: "Campings", layers: ["camping"] },
    { id: "gite", label: "Gîtes", layers: ["gite"] },
    { id: "biodiv", label: "Aires de protections de la biodiversité (escalade réglementée ou interdite)", layers: ["biodiv-regulated", "biodiv-forbidden", "biodiv-label"] },
  ];
  const overlayChecked = { tgv: true, camping: false, gite: false, biodiv: false };

  // Affiche/masque l'ensemble des couches du style vectoriel MapLibre (fond
  // cartographique + relief/courbes IGN). Au retour, on restitue la visibilité
  // d'origine de chaque couche (rando/VTT/ski restent masquées).
  function setMaplibreStyleVisible(visible) {
    Object.keys(maplibreLayerVisibility).forEach((id) => {
      if (!map.getLayer(id)) return;
      map.setLayoutProperty(id, "visibility", visible ? maplibreLayerVisibility[id] : "none");
    });
    contourLayers.forEach(({ id }) => {
      if (map.getLayer(id)) map.setLayoutProperty(id, "visibility", visible ? "visible" : "none");
    });
  }

  function setBasemap(name) {
    currentBasemap = name;
    if (name === MAPLIBRE_BASEMAP) {
      // Retour au style vectoriel : on masque le fond raster et on réaffiche le style.
      if (map.getLayer("basemap")) map.setLayoutProperty("basemap", "visibility", "none");
      setMaplibreStyleVisible(true);
      return;
    }
    if (!BASEMAPS[name]) return;
    // Fond raster alternatif : on masque le style vectoriel et on affiche la
    // couche `basemap` avec les tuiles choisies.
    const src = map.getSource("basemap");
    if (src && src.setTiles) src.setTiles([BASEMAPS[name].url]);
    setMaplibreStyleVisible(false);
    if (map.getLayer("basemap")) map.setLayoutProperty("basemap", "visibility", "visible");
  }
  function setOverlayVisibility(overlayId, visible) {
    const ov = OVERLAYS.find(o => o.id === overlayId);
    if (!ov) return;
    overlayChecked[overlayId] = visible;
    ov.layers.forEach(lid => {
      if (map.getLayer(lid)) map.setLayoutProperty(lid, "visibility", visible ? "visible" : "none");
    });
  }

  const layerSwitcher = {
    onAdd(m) {
      const div = document.createElement("div");
      div.className = "vg-layer-switcher maplibregl-ctrl maplibregl-ctrl-group";
      const det = document.createElement("details");
      const sum = document.createElement("summary");
      // Icône "stacked layers" (style Lucide / leaflet) + label, montrés ensemble une fois ouvert.
      sum.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg><span class="vg-layer-switcher-label">Couches</span>';
      sum.title = "Couches";
      det.appendChild(sum);
      const body = document.createElement("div");
      // Basemaps (radio) : le style vectoriel MapLibre en tête, puis les fonds raster.
      [MAPLIBRE_BASEMAP, ...Object.keys(BASEMAPS)].forEach((name) => {
        const lab = document.createElement("label");
        const r = document.createElement("input");
        r.type = "radio"; r.name = "vg-basemap"; r.value = name;
        if (name === currentBasemap) r.checked = true;
        r.addEventListener("change", () => { if (r.checked) setBasemap(name); });
        lab.appendChild(r); lab.appendChild(document.createTextNode(" " + name));
        body.appendChild(lab);
      });
      body.appendChild(document.createElement("hr"));
      // Overlays (checkbox)
      OVERLAYS.forEach((ov) => {
        const lab = document.createElement("label");
        const c = document.createElement("input");
        c.type = "checkbox"; c.checked = overlayChecked[ov.id];
        c.addEventListener("change", () => setOverlayVisibility(ov.id, c.checked));
        lab.appendChild(c); lab.appendChild(document.createTextNode(" " + ov.label));
        body.appendChild(lab);
      });
      det.appendChild(body);
      div.appendChild(det);
      stopAllPropagation(div);
      return div;
    },
    onRemove() { },
  };
  map.addControl(layerSwitcher, "top-left");

  // ============================================================
  // Mounts Vue (filtres top-right, panneau info bottom-right)
  // ============================================================
  const filtersControl = {
    onAdd() {
      const div = document.createElement("div");
      div.className = "leaflet-filters-control maplibregl-ctrl";
      div.id = "vue-map-filters";
      div.dataset.villes = <?= json_encode(json_encode($villes), JSON_HEX_APOS) ?>;
      div.dataset.falaises = <?= json_encode(json_encode(array_map(fn($f) => ["falaise_id" => $f["falaise_id"], "falaise_nom" => $f["falaise_nom"]], $falaises)), JSON_HEX_APOS) ?>;
      div.dataset.gares = <?= json_encode(json_encode(array_map(fn($g) => ["gare_id" => $g["gare_id"], "gare_nom" => $g["gare_nom"]], $gares)), JSON_HEX_APOS) ?>;
      stopAllPropagation(div);
      return div;
    },
    onRemove() { },
  };
  map.addControl(filtersControl, "top-right");

  const infoControl = {
    onAdd() {
      const div = document.createElement("div");
      div.className = "info w-[calc(100%-20px)] maplibregl-ctrl";
      div.id = "vue-info-panel";
      stopAllPropagation(div);
      return div;
    },
    onRemove() { },
  };
  map.addControl(infoControl, "bottom-right");

  // ============================================================
  // État global
  // ============================================================
  let selected = null;
  const itinerairesLines = []; // [{ id, marker }]

  function teardown() {
    if (selected !== null && selected.type === "falaise" && selected.filteredOut) {
      // hide previously-shown filtered-out selected falaise
      selected.marker?.remove(); selected.labelMarker?.remove();
      selected.marker = null; selected.labelMarker = null;
      selected.displayMode = "hidden";
    }
    selected = null;
    itinerairesLines.forEach(({ id, marker }) => {
      if (map.getLayer(id)) map.removeLayer(id);
      if (map.getSource(id)) map.removeSource(id);
      marker?.remove();
    });
    itinerairesLines.length = 0;
    gares.forEach((gare) => {
      if (gare.type === "gare" && gare.marker) {
        attachTooltip(gare.marker, escapeHtml(gare.gare_nom), { direction: "right" });
      }
    });
    falaises.forEach((falaise) => {
      if (falaise.type === "falaise" && falaise.marker) {
        attachTooltip(falaise.marker, escapeHtml(falaise.falaise_nom), { direction: "right" });
      }
    });
  }

  // ============================================================
  // GPX -> GeoJSON via maplibre-gl-vector-text-protocol
  // ============================================================
  function extractCoords(geojson) {
    if (!geojson) return [];
    const feats = geojson.type === "FeatureCollection" ? geojson.features : [geojson];
    for (const f of feats) {
      const g = f.geometry;
      if (!g) continue;
      if (g.type === "LineString") return g.coordinates;
      if (g.type === "MultiLineString") return g.coordinates.flat();
    }
    return [];
  }

  async function renderGpx(it, color) {
    const id = `gpx-${it.velo_id}-${Math.random().toString(36).slice(2, 7)}`;
    if (map.getSource(id)) { try { map.removeLayer(id); map.removeSource(id); } catch (e) { } }
    map.addSource(id, { type: "geojson", data: "gpx://./bdd/gpx/" + gpx_path(it) });
    map.addLayer({
      id, type: "line", source: id,
      layout: { "line-cap": "round", "line-join": "round" },
      paint: { "line-color": color, "line-width": 5 },
    });
    map.on("mouseenter", id, () => { map.getCanvas().style.cursor = "pointer"; map.setPaintProperty(id, "line-width", 10); try { map.moveLayer(id); } catch (e) { } });
    map.on("mouseleave", id, () => { map.getCanvas().style.cursor = ""; map.setPaintProperty(id, "line-width", 5); });
    map.on("click", id, (e) => { e.preventDefault?.(); });

    // Récup des coordonnées parsées par le plugin pour positionner le tooltip
    let coords = [];
    try {
      const src = map.getSource(id);
      const data = await src.getData();
      coords = extractCoords(data);
    } catch (err) {
      console.warn("GPX load failed", id, err);
    }
    // Track id without marker so teardown can clean up even if tooltip is skipped
    const entry = { id, marker: null };
    itinerairesLines.push(entry);
    if (!coords.length) return entry;

    const mid = coords[Math.floor(coords.length / 2)];
    const tipEl = document.createElement("div");
    tipEl.className = `vg-velo-tooltip vg-color-${color}`;
    tipEl.style.pointerEvents = "none";
    tipEl.innerHTML = format_time(calculate_time(it))
      + (it.velo_apieduniquement === "1"
        ? '<svg class="w-4 h-4 fill-none stroke-current inline"><use href="#footprint"></use></svg>'
        : "");
    entry.marker = new maplibregl.Marker({ element: tipEl }).setLngLat(mid).addTo(map);
    return entry;
  }

  // ============================================================
  // Marqueurs falaises (DOM markers)
  // ============================================================
  function buildFalaiseEl(falaise, size) {
    const el = document.createElement("div");
    el.className = "vg-marker";
    el.style.width = size + "px";
    el.style.height = size + "px";
    const img = document.createElement("img");
    img.src = falaiseIconUrl(falaise.falaise_fermee, falaise.falaise_bloc);
    img.width = size; img.height = size;
    img.alt = "";
    el.appendChild(img);
    return el;
  }
  function buildLabelEl(name) {
    const el = document.createElement("div");
    el.className = "vg-label";
    el.innerHTML = `<div class="absolute top-0 left-1/2 text-center -translate-x-1/2 w-max max-w-[150px] text-primary font-bold ${halo} text-sm">${escapeHtml(name)}</div>`;
    el.style.width = "0";
    el.style.height = "0";
    return el;
  }
  function buildHighlightEl(name) {
    const el = document.createElement("div");
    el.className = "vg-label";
    el.innerHTML = `<div class="absolute z-1000 top-0 left-1/2 w-fit text-nowrap -translate-x-1/2 bg-linear-to-r from-primary to-secondary border-2 border-white text-white text-xs p-[2px] leading-none rounded-md">${escapeHtml(name)}</div>`;
    el.style.width = "0";
    el.style.height = "0";
    return el;
  }

  function setFalaiseMarker(falaise, mode) {
    const initMarker = () => {
      const el = buildFalaiseEl(falaise, defaultMarkerSize);
      el.title = "Clic pour voir les accès, puis second clic pour accéder à la fiche complète";
      const marker = new maplibregl.Marker({ element: el, anchor: "bottom" })
        .setLngLat(lngLat(falaise.falaise_latlng))
        .addTo(map);
      falaise.marker = marker;

      if (!falaise.highlighted) {
        const labelMarker = new maplibregl.Marker({ element: buildLabelEl(falaise.falaise_nom), anchor: "bottom" })
          .setLngLat(lngLat(falaise.falaise_latlng))
          .addTo(map);
        falaise.labelMarker = labelMarker;
      } else {
        const hmarker = new maplibregl.Marker({ element: buildHighlightEl(falaise.falaise_nom), anchor: "bottom" })
          .setLngLat(lngLat(falaise.falaise_latlng))
          .addTo(map);
        falaise.hmarker = hmarker;
      }
      attachTooltip(marker, escapeHtml(falaise.falaise_nom), { direction: "right" });

      el.addEventListener("click", (ev) => {
        ev.stopPropagation();
        if (selected === null || selected.falaise_id !== falaise.falaise_id) {
          teardown();
          selected = falaise;
          info_update();
          const pts = [lngLat(falaise.falaise_latlng), ...falaise.access.map(it => lngLat(it.gare.gare_latlng))];
          map.fitBounds(bboxOf(pts), { padding: { top: 50, right: 100, bottom: 200, left: 40 }, duration: 500, maxZoom: 14 });

          setTimeout(() => {
            // Une gare peut être l'origine de plusieurs itinéraires vers cette
            // falaise (variantes vélo). On ne tagge le tooltip qu'au 1er passage
            // (= itinéraire le plus court car access est trié par tempsVelo),
            // sinon la couleur du dernier itinéraire écrase les précédentes.
            const tagged = new Set();
            falaise.access.forEach((it, i) => {
              const c = itinerairesColors[i % itinerairesColors.length];
              renderGpx(it, c);
              if (tagged.has(it.gare.gare_id)) return;
              tagged.add(it.gare.gare_id);
              const station = gares.find(g => g.gare_id === it.gare.gare_id);
              if (station && station.marker) {
                attachTooltip(station.marker, escapeHtml(station.gare_nom), {
                  direction: "right", permanent: true,
                  className: `vg-station-tooltip vg-color-${c}`,
                });
              }
            });
          }, 760);
          attachTooltip(marker, escapeHtml(falaise.falaise_nom), { direction: "top", permanent: true });
        } else {
          window.location.href = `/falaise.php?falaise_id=${falaise.falaise_id}`;
        }
      });
    };

    if (falaise.displayMode === mode) return;
    if (!falaise.marker || !falaise.labelMarker || falaise.displayMode === "hidden") {
      initMarker();
    }
    falaise.displayMode = mode;

    const applyIconSize = (size) => {
      const img = falaise.marker.getElement().querySelector("img");
      if (img) { img.width = size; img.height = size; }
      falaise.marker.getElement().style.width = size + "px";
      falaise.marker.getElement().style.height = size + "px";
    };

    if (mode === "normal+label") {
      falaise.labelMarker?.addTo(map);
    } else if (falaise.labelMarker) {
      falaise.labelMarker.remove();
    }

    switch (mode) {
      case "normal":
      case "normal+label":
        falaise.marker.getElement().style.opacity = 1;
        applyIconSize(defaultMarkerSize);
        return;
      case "reduced":
        falaise.marker.getElement().style.opacity = 1;
        applyIconSize(20);
        return;
      case "faded":
        falaise.marker.getElement().style.opacity = 0.5;
        applyIconSize(24);
        return;
      case "hidden":
        falaise.marker.remove();
        falaise.labelMarker?.remove();
        // Garder les refs pour ré-init si besoin
        return;
    }
  }

  function setFalaiseHTMarker(falaise, mode) {
    if (falaise.displayMode === mode) return;
    if (falaise.displayMode !== undefined && falaise.marker) {
      falaise.marker.remove();
      falaise.labelMarker?.remove();
    }
    falaise.displayMode = mode;
    const size = 20;
    const init = () => {
      const el = buildFalaiseEl(falaise, size);
      el.style.opacity = 0.75;
      const marker = new maplibregl.Marker({ element: el, anchor: "bottom" })
        .setLngLat(lngLat(falaise.falaise_latlng))
        .addTo(map);
      const labelMarker = new maplibregl.Marker({ element: buildLabelEl(falaise.falaise_nom), anchor: "bottom" })
        .setLngLat(lngLat(falaise.falaise_latlng))
        .addTo(map);
      labelMarker.getElement().style.opacity = "0.75";
      falaise.marker = marker;
      falaise.labelMarker = labelMarker;

      const popupHtml = `<div class="flex flex-col gap-1">`
        + `<div class="text-slate-400"><span class="uppercase">hors topo</span> (aucun accès 🚲 décrit)</div>`
        + `<div class="text-sm font-bold">${escapeHtml(falaise.falaise_nom)}</div>`
        + (falaise.falaise_fermee ? `<div class="text-error">${escapeHtml(falaise.falaise_fermee).replace(/\n/g, "<br>")}</div>` : "")
        + `<div class="flex gap-2 w-full justify-end">`
        + `  <a href="/ajout/ajout_falaise.php?falaise_id=${falaise.falaise_id}" class="btn btn-xs btn-primary">Renseigner la falaise</a>`
        + `  <a href="/ajout/ajout_velo.php?falaise_id=${falaise.falaise_id}" class="btn btn-xs btn-primary">Ajouter accès</a>`
        + `</div></div>`;
      falaise._popup = new maplibregl.Popup({ offset: 20, closeButton: true });
      falaise._popup.setHTML(popupHtml);
      el.addEventListener("click", (ev) => {
        ev.stopPropagation();
        falaise._popup.setLngLat(lngLat(falaise.falaise_latlng)).addTo(map);
      });
    };
    if (!falaise.marker) init();
    switch (mode) {
      case "normal":
      case "normal+label":
        falaise.marker.addTo(map);
        if (mode === "normal+label") falaise.labelMarker?.addTo(map);
        else falaise.labelMarker?.remove();
        return;
      case "hidden":
        falaise.marker?.remove();
        falaise.labelMarker?.remove();
        return;
    }
  }

  // ============================================================
  // Marqueurs gares
  // ============================================================
  function buildGareEl(tgv, size) {
    const el = document.createElement("div");
    el.className = "vg-marker train-icon bgwhite" + (tgv ? " filterred" : "");
    // Pas de padding/boxSizing : .train-icon (border-radius 50%) + .bgwhite suffisent.
    // L'img remplit exactement le div pour rester aligné avec l'ancre center.
    el.style.width = size + "px";
    el.style.height = size + "px";
    const img = document.createElement("img");
    img.src = "/images/map/icone_train_carte.png";
    img.width = size; img.height = size;
    img.alt = "";
    el.appendChild(img);
    return el;
  }

  function setGareMarker(gare, mode) {
    if (gare.displayMode === mode) return;
    if (gare.marker) gare.marker.remove();
    const el = buildGareEl(gare.gare_tgv === "1", 24);
    const marker = new maplibregl.Marker({ element: el, anchor: "center" })
      .setLngLat(lngLat(gare.gare_latlng))
      .addTo(map);
    gare.marker = marker;
    gare.displayMode = mode;
    attachTooltip(marker, escapeHtml(gare.gare_nom), { direction: "right" });

    el.addEventListener("click", (ev) => {
      ev.stopPropagation();
      teardown();
      selected = gare;
      info_update();
      const pts = [lngLat(gare.gare_latlng), ...gare.access.map(it => lngLat(it.falaise.falaise_latlng))];
      map.fitBounds(bboxOf(pts), { padding: { top: 50, right: 100, bottom: 200, left: 40 }, duration: 500, maxZoom: 12 });

      setTimeout(() => {
        // Idem côté gare : plusieurs itinéraires peuvent desservir la même
        // falaise — on ne tagge le tooltip falaise qu'au 1er passage.
        const tagged = new Set();
        gare.access.forEach((it, i) => {
          const c = itinerairesColors[i % itinerairesColors.length];
          if (falaises.find(f => f.falaise_id === it.falaise.falaise_id)?.filteredOut) return;
          renderGpx(it, c);
          if (tagged.has(it.falaise.falaise_id)) return;
          tagged.add(it.falaise.falaise_id);
          const falaise = falaises.find(f => f.falaise_id === it.falaise.falaise_id);
          if (falaise && falaise.marker) {
            attachTooltip(falaise.marker, escapeHtml(falaise.falaise_nom), {
              direction: "right", permanent: true,
              className: `vg-station-tooltip vg-color-${c}`,
            });
          }
        });
      }, 760);
      attachTooltip(marker, escapeHtml(gare.gare_nom), { direction: "top", permanent: true });
    });

    if (mode === "hidden") {
      gare.marker.remove();
    }
  }

  // ============================================================
  // Rendus zoom-adaptifs
  // ============================================================
  function renderFalaises() {
    const zoom = map.getZoom();
    falaises.forEach((falaise) => {
      if (!falaise.falaise_latlng) return;
      if (falaise.access.length === 0) {
        falaise.type = "falaise_hors_topo";
        if (zoom < 11 || falaise.filteredOut) {
          setFalaiseHTMarker(falaise, "hidden");
        } else if (zoom < 14) {
          setFalaiseHTMarker(falaise, "normal");
        } else {
          setFalaiseHTMarker(falaise, "normal+label");
        }
      } else {
        falaise.type = "falaise";
        if (falaise.filteredOut) {
          if (falaise === selected) setFalaiseMarker(falaise, "faded");
          else setFalaiseMarker(falaise, "hidden");
          return;
        }
        if (falaise.falaise_fermee) {
          if (zoom < 11) setFalaiseMarker(falaise, "hidden");
          else if (zoom < 12) setFalaiseMarker(falaise, "normal");
          else setFalaiseMarker(falaise, "normal+label");
        } else {
          if (zoom < 9) setFalaiseMarker(falaise, "reduced");
          else if (zoom < 12) setFalaiseMarker(falaise, "normal");
          else setFalaiseMarker(falaise, "normal+label");
        }
      }
    });
  }
  function renderGares() {
    const zoom = map.getZoom();
    gares.forEach((gare) => {
      if (!gare.gare_latlng) return;
      if (gare.access.length === 0) {
        gare.type = "gare_hors_topo";
        // géré par le layer PMTiles "gares-pm-circle/-label" (zoom 10+/11+)
        return;
      }
      gare.type = "gare";
      // Carte.php utilise `zoom < 9` mais à visuel équivalent MapLibre est
      // ~1 niveau en dessous — on aligne en pratique en baissant à 8.
      if (zoom < 8) setGareMarker(gare, "hidden");
      else {
        if (!gare.marker || gare.displayMode === "hidden") setGareMarker(gare, "normal");
      }
    });
  }

  // Click sur la carte = teardown
  map.on("click", (e) => {
    // Ignore si on a cliqué sur un layer GPX (déjà géré)
    if (e.defaultPrevented) return;
    if (selected) {
      teardown();
      info_update();
    }
  });

  map.on("zoomend", () => { renderFalaises(); renderGares(); });
  map.on("moveend", () => { renderGares(); });

  // ============================================================
  // Panneau info Vue
  // ============================================================
  function info_update() {
    const nFalaises = falaises.filter(f => f.type === "falaise").length;
    const nFalaiseFiltered = falaises.filter(f => f.type === "falaise" && !f.filteredOut).length;
    if (window.velogrimpe?.carteInfo) {
      window.velogrimpe.carteInfo.updateStats(nFalaises, nFalaiseFiltered);
      window.velogrimpe.carteInfo.setSelected(selected);
    }
    setTimeout(() => {
      if (window.innerWidth >= 768) {
        document.querySelectorAll("#vue-info-panel details").forEach((d) => d.open = true);
      }
    }, 50);
  }
</script>

<script>
  // ============================================================
  // Overlays PMTiles (au map.load)
  // ============================================================
  // SVG icônes campings & gîtes (extraits de load-vector-tiles.js, allégés)
  const CAMPING_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 393 395">
<path d="M183 354 c0 -1 -0.5 -1.3 -2 -2.5 -4 -0.2 -2 -0.8 -68 -0.8 -78 0 -70.4 7 -71.7 -19.7 -0.8 -17.3 -15.8 -12.8 140 -12.8 127.2 0 152.2 -0.6 152.9 1.1 6.2 5.3 8 25.8 3.2 30.4 -0.6 1.6 -23.8 1 -73.4 1.3 l -54.3 0.3 -1.2 0.8 c -1.6 1.2 -0.5 0.1 -0.5 0.9 0 1.3 -5.3 1 -13.2 1z M191 96 c1.2 1.3 130.5 220.8 129.7 221 -5.8 1.3 -48 -0.5 -56 -1.1 l -36.9 -64.6 c -8.2 -8.3 -26.4 -49.4 -32.4 -52.7 -1 0.6 -13.6 20.9 -31.8 52.5 L 127 316 64 314.7 C 79.7 278.5 190.4 94.7 191 96z M163 54 c-1 -2.7 2.5 -6.8 5.2 -7.8 4.6 -1.8 8.8 -1.1 14.4 11.8 l 11.5 20 c 0 0 -7.6 16.7 -9.2 14z" fill="#228b22"/>
<path d="M196 198 l -70.6 119 142.3 0.7z" fill="#fff" stroke="#000" stroke-width="7"/>
<path d="M61 316 c0 0 118.7 -208.4 151.5 -265.2 5.9 -10.3 22.6 -1.9 16.2 9.5 L 208 97 334 317" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M183 353 H 45 c -5.6 0 -9 -36.5 1.4 -36.5 40.6 0 228.5 1.1 300.5 1.1 4.9 0 8.8 35.6 -0.3 35.8 -21.5 0.4 -138.1 0.6 -138.1 0.6" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
<path d="M184 96.5 c0 0 -8.2 -13.7 -21.7 -37.1 -7.8 -13.4 8.5 -23.4 16.7 -9.2 11.1 19.3 16.6 27.7 16.6 27.7" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round"/>
</svg>`;
  const GITE_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 656 622">
<g transform="translate(76,55)">
<path d="M91 499 c-7 -1 -12 -4 -17 -10 -8 -9 -8 -7 -8 -71 0 -34 0.4 -57 1 -58 0.5 -1 2.3 -2.4 4 -3 V 356 c 0 -8.4 -0.2 -9.4 -1.8 -9.4 -1 0 -2.8 -1 -4 -2.3 C 66 332 66 331 66 313 v -19 l -9 10 c -8 8 -10 10 -14 11 -9 2 -13 0 -27 -13 C 3 288 0 283 0 276 c 0 -3 0.7 -7 1.5 -8 0.8 -1.6 54 -57.9 119 -125 130.4 -136 122.3 -128 135.6 -128 13.3 0 5.1 -7.7 135.6 128 64.6 67.3 118.1 123.6 119 125 0.8 1.6 1.5 5.3 1.5 8.2 0 7.1 -2.9 11.4 -16 24.4 -13.6 13.3 -17.9 15.4 -26.6 13.3 -4.1 -1 -6 -2.5 -14 -10.8 l -9.2 -9.6 -0 91 c 0 102 0.5 95.9 -8 105 -6.4 7 -12.2 9.5 -24.2 10.2 -10.7 0.7 -14.1 -0.3 -16.5 -4.6 -1.7 -3 -1.8 -3 -10.7 -3 -9 0 -9 0 -10.7 3 -0.9 1.6 -2.9 3.4 -4.3 4 -3.1 1.2 -273.5 1.2 -280.5 -0z" fill="#228b22"/>
<path d="M191 484 l 0.4 -48 c 0.4 -53.5 -0.1 -51.4 7.5 -66.9 3.3 -6.8 4.9 -9.7 11.8 -16.6 13.8 -13.8 25.9 -18.5 45.4 -18.6 19.4 0 31.1 4.6 45.2 18.6 6.9 6.8 8.9 8.3 12.3 15.2 7.6 15.5 7.5 14.7 7.9 68.3 l 0.4 47.7 s -77 9.3 -93 9.3 c -32.4 -6.5 -38 -9.2 -38 -9.2z" fill="#fff"/>
<path d="M88 498 C 79 495 73 489 68 479 c -1.7 -3.7 -1.5 -8.3 -1.5 -61.3 V 360 l 2.4 -1.9 c 3.2 -2.6 6.7 -2.4 9.7 0.5 L 81 362 v 55 c 0 52.8 0.1 55.2 2 59 1.1 2.1 3.5 4.8 5.4 6 3.3 2 4.4 2.1 51.6 2.1 H 188 v -46.8 c 0 -43.3 0.2 -47.4 2 -54.5 7.8 -30.5 34.9 -51.6 66 -51.6 31.1 0 58.3 21.2 66 51.6 1.8 7.1 2 11.2 2 54.5 v 46.8 h 23.4 c 22.1 0 23.5 0.1 26 2.1 3.6 2.9 3.6 8 0 10.9 -2.6 2.1 -2.6 2.1 -142.3 2 C 122 499 91 498 88 498z M 309 437 c 0 -39.9 -0.2 -47.6 -1.6 -52.2 -5.4 -18.5 -20.5 -33.2 -38.5 -37.4 -28.3 -6.7 -56.1 9.5 -64.3 37.4 -1.4 4.7 -1.6 12.3 -1.6 52.2 v 46.8 h 53 53 z" fill="#000"/>
</g>
</svg>`;

  async function loadIcon(name, svg) {
    return new Promise((resolve) => {
      const img = new Image(40, 40);
      img.onload = () => {
        try {
          // pixelRatio: 2 → l'image source 40x40 est traitée comme du 2x,
          // soit 20x20 pixels logiques à l'écran (équivalent du devicePixelRatio:2
          // utilisé par protomaps-leaflet dans l'original).
          if (!map.hasImage(name)) map.addImage(name, img, { pixelRatio: 2 });
        } catch (e) { }
        resolve();
      };
      img.onerror = () => { console.warn("Icon load failed", name); resolve(); };
      img.src = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
    });
  }

  function addOverlays() {
    // --- TER lines ---
    map.addSource("trainlines", { type: "vector", url: "pmtiles:///bdd/trains/ter.pmtiles" });
    map.addLayer({
      id: "trainlines", type: "line", source: "trainlines", "source-layer": "ter",
      paint: {
        "line-color": "#000",
        "line-width": ["interpolate", ["linear"], ["zoom"], 6, 0.5, 9, 1, 12, 1.5],
      },
    });

    // --- TGV (line + circle + label) ---
    map.addSource("tgv", { type: "vector", url: "pmtiles:///bdd/trains/tgv.pmtiles" });
    map.addLayer({
      id: "tgv-line", type: "line", source: "tgv", "source-layer": "tgv",
      filter: ["==", ["geometry-type"], "LineString"],
      paint: {
        "line-color": "#c00",
        "line-width": ["interpolate", ["linear"], ["zoom"], 6, 0.5, 9, 1, 12, 1.5],
      },
    });
    map.addLayer({
      id: "tgv-pt", type: "circle", source: "tgv", "source-layer": "tgv",
      filter: ["==", ["geometry-type"], "Point"],
      paint: {
        "circle-radius": 3,
        "circle-color": "#c00",
        "circle-stroke-color": "#fff",
        "circle-stroke-width": 1,
      },
    });
    map.addLayer({
      id: "tgv-label", type: "symbol", source: "tgv", "source-layer": "tgv", minzoom: 14,
      filter: ["==", ["geometry-type"], "Point"],
      layout: {
        "text-field": ["get", "name"],
        "text-size": 14, "text-anchor": "top", "text-offset": [0, 0.6],
        "text-font": ["Open Sans Bold", "Arial Unicode MS Bold"],
      },
      paint: { "text-color": "#c00", "text-halo-color": "#fff", "text-halo-width": 2 },
    });
    // Apply visibility per current state
    OVERLAYS.find(o => o.id === "tgv").layers.forEach(lid => map.setLayoutProperty(lid, "visibility", overlayChecked.tgv ? "visible" : "none"));

    // --- Campings + Gîtes (même PMTiles, filtre category) ---
    map.addSource("camping_src", { type: "vector", url: "pmtiles:///bdd/datatourisme/camping_2.pmtiles" });
    map.addLayer({
      id: "camping", type: "symbol", source: "camping_src", "source-layer": "camping", minzoom: 12,
      filter: ["==", ["get", "category"], "Camping"],
      layout: {
        "icon-image": "camping", "icon-size": 1, "icon-allow-overlap": true,
        "text-field": ["get", "name"], "text-size": 14, "text-offset": [0, 1.8], "text-anchor": "top",
        "text-font": ["Open Sans Bold", "Arial Unicode MS Bold"],
        "visibility": overlayChecked.camping ? "visible" : "none",
      },
      paint: {
        "text-color": "forestgreen", "text-halo-color": "#fff", "text-halo-width": 2,
        "text-opacity": ["step", ["zoom"], 0, 14, 1],
      },
    });
    map.addLayer({
      id: "gite", type: "symbol", source: "camping_src", "source-layer": "camping", minzoom: 12,
      filter: ["==", ["get", "category"], "Gite"],
      layout: {
        "icon-image": "gite", "icon-size": 1, "icon-allow-overlap": true,
        "text-field": ["get", "name"], "text-size": 14, "text-offset": [0, 1.8], "text-anchor": "top",
        "text-font": ["Open Sans Bold", "Arial Unicode MS Bold"],
        "visibility": overlayChecked.gite ? "visible" : "none",
      },
      paint: {
        "text-color": "forestgreen", "text-halo-color": "#fff", "text-halo-width": 2,
        "text-opacity": ["step", ["zoom"], 0, 14, 1],
      },
    });

    // --- Biodiv ---
    // Note: practices/rules sont stockés en JSON-string dans le tile. Filtres approximatifs par substring.
    map.addSource("biodiv", { type: "vector", url: "pmtiles:///bdd/biodiv/biodiv.pmtiles" });
    map.addLayer({
      id: "biodiv-regulated", type: "fill", source: "biodiv", "source-layer": "biodiv",
      filter: ["all",
        ["==", ["geometry-type"], "Polygon"],
        ["!", ["in", "CLIMBING-FORBIDDEN", ["coalesce", ["get", "rules"], ""]]],
      ],
      paint: { "fill-color": "tomato", "fill-opacity": 0.6 },
      layout: { "visibility": overlayChecked.biodiv ? "visible" : "none" },
    });
    map.addLayer({
      id: "biodiv-forbidden", type: "fill", source: "biodiv", "source-layer": "biodiv",
      filter: ["all",
        ["==", ["geometry-type"], "Polygon"],
        ["in", "CLIMBING-FORBIDDEN", ["coalesce", ["get", "rules"], ""]],
      ],
      paint: { "fill-color": "darkred", "fill-opacity": 0.7 },
      layout: { "visibility": overlayChecked.biodiv ? "visible" : "none" },
    });
    map.addLayer({
      id: "biodiv-label", type: "symbol", source: "biodiv", "source-layer": "biodiv", minzoom: 14,
      layout: {
        "text-field": ["get", "name"],
        "text-size": 14,
        "text-font": ["Open Sans Bold", "Arial Unicode MS Bold"],
        "visibility": overlayChecked.biodiv ? "visible" : "none",
      },
      paint: { "text-color": "tomato", "text-halo-color": "#fff", "text-halo-width": 2 },
    });

    // --- Gares hors topo depuis PMTiles (même source que falaise.php).
    // Filtre : on exclut les noms des gares "topo" (déjà rendues comme DOM
    // markers cliquables avec icône train). Ne reste que le complément.
    const topoGareNames = gares
      .filter(g => g.access && g.access.length > 0 && g.gare_nom)
      .map(g => g.gare_nom);
    const horsTopoFilter = ["!", ["in", ["get", "name"], ["literal", topoGareNames]]];

    map.addSource("gares-pm", { type: "vector", url: "pmtiles:///bdd/trains/gares.pmtiles" });
    // Cercle dès zoom 10 (Leaflet montre la même chose à zoom 11, mais à
    // visuel égal MapLibre est ~1 niveau plus bas — voir comparaison
    // d'icônes falaises entre les deux captures). Label à zoom 11.
    map.addLayer({
      id: "gares-pm-circle", type: "circle", source: "gares-pm", "source-layer": "gares", minzoom: 10,
      filter: horsTopoFilter,
      paint: {
        "circle-radius": 4,
        "circle-color": "#000",
        "circle-stroke-color": "#fff",
        "circle-stroke-width": 1,
      },
    });
    map.addLayer({
      id: "gares-pm-label", type: "symbol", source: "gares-pm", "source-layer": "gares", minzoom: 11,
      filter: horsTopoFilter,
      layout: {
        "text-field": ["get", "name"],
        "text-size": 12,
        "text-offset": [0, 0.6],
        "text-anchor": "top",
        "text-font": ["Open Sans Bold", "Arial Unicode MS Bold"],
      },
      paint: { "text-color": "#000", "text-halo-color": "#fff", "text-halo-width": 2 },
    });
    map.on("click", "gares-pm-circle", (e) => {
      const f = e.features[0];
      new maplibregl.Popup()
        .setLngLat(f.geometry.coordinates)
        .setHTML(escapeHtml(f.properties.name || ""))
        .addTo(map);
      e.preventDefault?.();
    });
    map.on("mouseenter", "gares-pm-circle", () => { map.getCanvas().style.cursor = "pointer"; });
    map.on("mouseleave", "gares-pm-circle", () => { map.getCanvas().style.cursor = ""; });
  }

  map.on("load", async () => {
    // Charger les icônes
    await Promise.all([loadIcon("camping", CAMPING_SVG), loadIcon("gite", GITE_SVG)]);
    addOverlays();
    // Premier rendu des marqueurs DOM
    renderFalaises();
    renderGares();
  });
</script>

<script>
  // ============================================================
  // Recherche, filtres, info-ready (Vue events)
  // ============================================================
  window.addEventListener('velogrimpe:search-select', (e) => {
    const { id, type } = e.detail;
    document.getElementById("map").scrollIntoView({ behavior: "smooth", block: "nearest" });
    let item = null;
    if (type === "falaise") item = falaises.find(f => f.falaise_id === id);
    else if (type === "gare") item = gares.find(g => g.gare_id === id);
    if (!item) return;

    if (item.type === "falaise_hors_topo") {
      setFalaiseHTMarker(item, "normal");
      map.flyTo({ center: lngLat(item.falaise_latlng), zoom: 12, duration: 500 });
      setTimeout(() => item._popup?.setLngLat(lngLat(item.falaise_latlng)).addTo(map), 600);
      return;
    }
    if (item.type === "gare_hors_topo") {
      map.flyTo({ center: lngLat(item.gare_latlng), zoom: 11, duration: 500 });
      setTimeout(() => {
        new maplibregl.Popup()
          .setLngLat(lngLat(item.gare_latlng))
          .setHTML(escapeHtml(item.gare_nom))
          .addTo(map);
      }, 600);
      return;
    }
    // Simulate click
    if (item.marker) {
      const el = item.marker.getElement();
      el.dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true }));
    }
  });

  // Filtres Vue
  const falaisesDuTopo = falaises.filter(f => f.access.length > 0);
  const falaisesHorsTopo = falaises.filter(f => f.access.length === 0);

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

    if (!expoFiltered && !cotFiltered && !typeVoiesFiltered && nbVoies === 0 && !apieduniquement
      && tempsMaxVelo === null && denivMaxVelo === null && distMaxVelo === null && tempsMaxMA === null && !villeSelected) {
      falaises.forEach(f => { f.filteredOut = false; });
    } else {
      falaisesHorsTopo.forEach(f => { f.filteredOut = false; });
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
        const estTrainCompatible = falaise.access.some(it => {
          const train = it.villes.find(v => v.ville_id === ville);
          return train && (
            (tempsMaxTrain === null || train.temps <= tempsMaxTrain)
            && (nbCorrespMax === 10 || train.nCorresp <= nbCorrespMax)
            && (!terOnly || parseInt(train.train_tgv || 0) === 0)
            && (tempsMaxTV === null || train.tempsTrainVelo <= tempsMaxTV)
            && (tempsMaxTVA === null || train.tempsTotal <= tempsMaxTVA)
          );
        });
        const estNbVoiesCompatible = (parseInt(falaise.falaise_nbvoies) >= nbVoies) || nbVoies === 0;
        const estTypeVoiesCompatible = (
          (couenne && !!!parseInt(falaise.falaise_bloc))
          || (avecgv && !!falaise.falaise_gvnb)
          || (bloc && parseInt(falaise.falaise_bloc) === 1)
          || (psychobloc && parseInt(falaise.falaise_bloc) === 2)
        );

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
    info_update();
    renderFalaises();
  };

  window.addEventListener('velogrimpe:filters', (e) => applyVueFilters(e.detail));
  window.addEventListener('velogrimpe:info-ready', () => info_update());
</script>

<!-- Vue.js Map Filters (search + filters control) -->
<script type="module" src="/dist/carte-map-filters.js"></script>
<!-- Vue.js Info Panel -->
<script type="module" src="/dist/carte-info.js"></script>

</html>