/**
 * Éditeur de détails falaise - Module ES6
 *
 * Gère l'édition des éléments géographiques d'une falaise :
 * secteurs, parkings, approches, accès vélo, arrêts bus, falaises voisines.
 *
 * @module falaise-details-editor
 */

import Falaise from "/js/components/map/falaise.js";
import Secteur from "/js/components/map/secteur.js";
import Approche from "/js/components/map/approche.js";
import Parking from "/js/components/map/parking.js";
import BusStop from "/js/components/map/bus-stop.js";
import AccesVelo from "/js/components/map/acces-velo.js";
import FalaiseVoisine from "/js/components/map/falaise-voisine.js";
import { getValhallaRoute } from "/js/services/valhalla.js";
import { fetchBusStops } from "/js/components/utils/fetch-bus-stops.js";

// Use global contribStorage (loaded via script tag)
const { getContribInfo, saveContribInfo } = window.contribStorage || {};

/**
 * @typedef {Object} FalaiseData
 * @property {number} falaise_id
 * @property {string} falaise_nom
 * @property {string} falaise_nomformate
 * @property {string} falaise_latlng
 */

/**
 * @typedef {Object} EditorInstance
 * @property {L.Map} map
 * @property {Object<number, Object>} featureMap
 * @property {function} importData
 * @property {function} exportData
 * @property {function} save
 */

// Base map layers factory
const createBaseMaps = () => ({
  Landscape: L.tileLayer(
    "https://{s}.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    {
      maxZoom: 19,
      minZoom: 0,
      attribution:
        '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      crossOrigin: true,
    },
  ),
  IGNv2: L.tileLayer(
    "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&FORMAT=image/png&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}",
    {
      maxZoom: 19,
      minZoom: 0,
      attribution: "IGN-F/Geoportail",
      crossOrigin: true,
    },
  ),
  Satellite: L.tileLayer(
    "https://data.geopf.fr/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}",
    {
      maxZoom: 18,
      minZoom: 0,
      tileSize: 256,
      attribution: "IGN-F/Geoportail",
      crossOrigin: true,
    },
  ),
  Outdoors: L.tileLayer(
    "https://{s}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    {
      maxZoom: 19,
      minZoom: 0,
      attribution:
        '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>/<a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
      crossOrigin: true,
    },
  ),
});

/**
 * Initialise l'éditeur de détails falaise
 * @param {string} containerId - ID du conteneur HTML
 * @returns {EditorInstance} Instance de l'éditeur
 */
export function initFalaiseDetailsEditor(containerId) {
  const container = document.getElementById(containerId);
  if (!container) {
    console.error(
      `[falaise-details-editor] Container #${containerId} not found`,
    );
    return null;
  }

  const falaise = JSON.parse(container.dataset.falaise || "{}");
  const token = container.dataset.token || "";
  const apiEndpoint =
    container.dataset.apiEndpoint || "/api/private/falaise_details.php";
  let contribNom = container.dataset.contribNom || "";
  let contribEmail = container.dataset.contribEmail || "";

  // Fallback to localStorage if not provided via data attributes
  if (!contribNom || !contribEmail) {
    const stored = getContribInfo();
    if (!contribNom && stored.nom) contribNom = stored.nom;
    if (!contribEmail && stored.email) contribEmail = stored.email;
  }

  const mapEl = container.querySelector(".editor-map");
  const center = falaise.falaise_latlng.split(",").map(parseFloat);

  // Initialize map
  const baseMaps = createBaseMaps();
  const map = L.map(mapEl, {
    layers: [baseMaps.Landscape],
    center,
    zoom: 15,
    fullscreenControl: true,
    zoomSnap: 0.5,
  });

  L.control
    .layers(baseMaps, undefined, { position: "topleft", size: 22 })
    .addTo(map);
  L.control
    .scale({
      position: "bottomright",
      metric: true,
      imperial: false,
      maxWidth: 125,
    })
    .addTo(map);

  // Feature map and state
  let featureId = 0;
  const featureMap = {};
  let currentRoute = [];
  let currentRoutingPoints = [];
  let currentMultiSecteur = null;
  let awaitingMultiSecteurSelection = false;
  const multiAppendClickHandlers = new Map();
  let multiAppendInfoControl = null;

  // Falaise marker
  new Falaise(map, falaise);

  // External search layers
  const searchLayers = {
    busStops: L.layerGroup().addTo(map),
  };

  // Table control
  const TableControl = L.Control.extend({
    onAdd: function () {
      const div = L.DomUtil.create("div");
      div.innerHTML = `
        <div class="leaflet-control-zoom leaflet-bar">
          <a class="p-1 cursor-pointer" title="Tableau récapitulatif des éléments">
            <svg class="w-5 h-5 fill-none stroke-current">
              <use href="#table"></use>
            </svg>
          </a>
        </div>
      `;
      div.querySelector("a").addEventListener("click", showTableau);
      return div;
    },
  });
  map.addControl(new TableControl({ position: "topright" }));

  // Multi-secteur helpers
  const clearMultiSecteurAppendHandlers = () => {
    for (const [layer, handler] of multiAppendClickHandlers.entries()) {
      try {
        layer.off("click", handler);
      } catch (e) {}
    }
    multiAppendClickHandlers.clear();
  };

  const showMultiSecteurInfo = () => {
    if (multiAppendInfoControl) return;
    const InfoControl = L.Control.extend({
      onAdd: function () {
        const div = L.DomUtil.create("div", "leaflet-bar");
        div.style.cssText =
          "padding:6px 8px;background:white;box-shadow:0 1px 5px rgba(0,0,0,0.4);border-radius:4px";
        div.innerHTML =
          "<strong>Multibarres :</strong><br/>Cliquez sur un secteur existant pour y ajouter une nouvelle barre.";
        return div;
      },
    });
    multiAppendInfoControl = new InfoControl({ position: "topright" });
    map.addControl(multiAppendInfoControl);
  };

  const hideMultiSecteurInfo = () => {
    if (multiAppendInfoControl) {
      try {
        map.removeControl(multiAppendInfoControl);
      } catch (e) {}
      multiAppendInfoControl = null;
    }
  };

  const stopMultiSecteurAppend = () => {
    awaitingMultiSecteurSelection = false;
    currentMultiSecteur = null;
    clearMultiSecteurAppendHandlers();
    hideMultiSecteurInfo();
  };

  const startMultiSecteurAppend = () => {
    awaitingMultiSecteurSelection = true;
    currentMultiSecteur = null;
    clearMultiSecteurAppendHandlers();
    showMultiSecteurInfo();

    Object.values(featureMap).forEach((f) => {
      const isSecteur =
        f.layer?.properties?.type === "secteur" ||
        f.layer?.properties?.type === undefined;
      const isLine = f.layer instanceof L.Polyline;
      if (!isSecteur || !isLine) return;

      const handler = (e) => {
        try {
          if (e?.originalEvent) {
            e.originalEvent.preventDefault();
            e.originalEvent.stopPropagation();
          }
        } catch (_) {}
        try {
          f.layer.closePopup?.();
        } catch (_) {}
        try {
          f.label?.layer?.closePopup?.();
        } catch (_) {}

        currentMultiSecteur = f;
        awaitingMultiSecteurSelection = false;
        clearMultiSecteurAppendHandlers();
        hideMultiSecteurInfo();

        map.pm.enableDraw("Line", {
          snappable: true,
          snapDistance: 10,
          pathOptions: Secteur.lineStyle,
          templineStyle: Secteur.lineStyle,
          hintlineStyle: Secteur.lineStyle,
          type: "secteur-multi-append",
        });
      };

      f.layer.on("click", handler);
      multiAppendClickHandlers.set(f.layer, handler);
      if (f.label?.layer) {
        f.label.layer.on("click", handler);
        multiAppendClickHandlers.set(f.label.layer, handler);
      }
    });
  };

  // Setup Geoman controls
  map.pm.addControls({
    position: "topright",
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

  // Custom controls
  map.pm.Toolbar.createCustomControl({
    name: "Accès vélo",
    block: "draw",
    title: "Ajouter un accès vélo",
    className: "vg-icon vg-draw-velo",
    actions: [
      "cancel",
      {
        text: "Point à Point",
        title: "Ligne droite d'un point à l'autre",
        name: "line",
        onClick: () => {
          map.pm.enableDraw("Line", {
            snappable: true,
            snapDistance: 10,
            pathOptions: AccesVelo.style,
            templineStyle: { ...AccesVelo.style, type: "acces_velo" },
            hintlineStyle: AccesVelo.style,
            type: "acces_velo",
          });
        },
      },
      {
        text: "Semi-auto (beta)",
        title: "Routage d'un point à l'autre",
        name: "line-auto",
        onClick: () => {
          map.pm.enableDraw("Line", {
            snappable: true,
            snapDistance: 10,
            pathOptions: AccesVelo.style,
            templineStyle: { ...AccesVelo.style, type: "acces_velo-auto" },
            hintlineStyle: AccesVelo.style,
            type: "acces_velo",
          });
        },
      },
    ],
  });

  map.pm.Toolbar.createCustomControl({
    name: "Approche auto",
    block: "draw",
    title: "Ajouter un itinéraire d'approche",
    className: "vg-icon vg-draw-approche",
    actions: [
      "cancel",
      {
        text: "Point à Point",
        title: "Ligne droite d'un point à l'autre",
        name: "line",
        onClick: () => {
          map.pm.enableDraw("Line", {
            snappable: true,
            snapDistance: 10,
            pathOptions: Approche.style,
            templineStyle: { ...Approche.style, type: "approche" },
            hintlineStyle: Approche.style,
            type: "approche",
          });
        },
      },
      {
        text: "Semi-auto (beta)",
        title: "Routage d'un point à l'autre",
        name: "line-auto",
        onClick: () => {
          map.pm.enableDraw("Line", {
            snappable: true,
            snapDistance: 10,
            pathOptions: Approche.style,
            templineStyle: { ...Approche.style, type: "approche-auto" },
            hintlineStyle: Approche.style,
            type: "approche",
          });
        },
      },
    ],
  });

  map.pm.Toolbar.createCustomControl({
    name: "Parking",
    block: "draw",
    title: "Ajouter un parking",
    className: "vg-icon vg-draw-parking",
    actions: [
      "cancel",
      {
        text: "Nouveau parking",
        name: "marker",
        onClick: () => {
          map.pm.enableDraw("Marker", {
            snappable: true,
            snapDistance: 10,
            continueDrawing: false,
            markerStyle: {
              draggable: true,
              icon: Parking.parkingIcon(Parking.iconSize),
            },
            type: "parking",
          });
        },
      },
    ],
  });

  map.pm.Toolbar.createCustomControl({
    name: "Arrêt de bus",
    block: "draw",
    title: "Ajouter un arrêt de bus",
    className: "vg-icon vg-draw-bus-stop",
    actions: [
      "cancel",
      {
        text: "Nouvel arrêt",
        name: "marker",
        onClick: () => {
          map.pm.enableDraw("Marker", {
            snappable: true,
            snapDistance: 10,
            continueDrawing: false,
            markerStyle: {
              draggable: true,
              icon: BusStop.busStopIcon(BusStop.iconSize),
            },
            type: "bus_stop",
          });
        },
      },
    ],
  });

  map.pm.Toolbar.createCustomControl({
    name: "Secteur",
    block: "draw",
    title: "Ajouter un secteur",
    className: "vg-icon vg-draw-secteur",
    actions: [
      {
        text: "Annuler",
        name: "annuler",
        onClick: () => {
          stopMultiSecteurAppend();
          try {
            map.pm.disableDraw("Line");
          } catch (_) {}
        },
      },
      {
        text: "Secteur Linéaire",
        title: "Le vide est à droite dans le sens du tracé",
        name: "line",
        onClick: () => {
          stopMultiSecteurAppend();
          map.pm.enableDraw("Line", {
            snappable: true,
            snapDistance: 10,
            pathOptions: Secteur.lineStyle,
            templineStyle: Secteur.lineStyle,
            hintlineStyle: Secteur.lineStyle,
            type: "secteur",
          });
        },
      },
      {
        text: "Ajout barre",
        title:
          "Sélectionnez un secteur existant, puis tracez une nouvelle barre",
        name: "line-multi",
        onClick: startMultiSecteurAppend,
      },
      {
        text: "Secteur Polygonal",
        name: "polygon",
        onClick: () => {
          stopMultiSecteurAppend();
          map.pm.enableDraw("Polygon", {
            snappable: true,
            snapDistance: 10,
            pathOptions: Secteur.polygonStyle,
            templineStyle: Secteur.polygonStyle,
            hintlineStyle: Secteur.polygonStyle,
            type: "secteur",
          });
        },
      },
    ],
  });

  map.pm.Toolbar.createCustomControl({
    name: "Falaise Voisine",
    block: "draw",
    title: "Ajouter un lien vers une falaise voisine",
    className: "vg-icon vg-draw-ext-falaise",
    actions: [
      "cancel",
      {
        text: "Nouveau lien",
        name: "polygon",
        onClick: () => {
          map.pm.enableDraw("Polygon", {
            snappable: true,
            snapDistance: 10,
            pathOptions: FalaiseVoisine.style,
            templineStyle: FalaiseVoisine.style,
            hintlineStyle: FalaiseVoisine.style,
            type: "falaise_voisine",
          });
        },
      },
    ],
  });

  // Valhalla routing handler
  map.on("pm:drawstart", ({ workingLayer }) => {
    currentRoute = [];
    currentRoutingPoints = [];

    if (
      workingLayer.options.type === "approche-auto" ||
      workingLayer.options.type === "acces_velo-auto"
    ) {
      workingLayer.on("pm:vertexadded", (e) => {
        if (currentRoutingPoints.includes([e.latlng.lat, e.latlng.lng])) {
          map.pm.disableDraw("Line");
        }

        if (currentRoutingPoints.length > 0) {
          const lastPoint = currentRoutingPoints.slice(-1)[0];
          getValhallaRoute([
            { lat: lastPoint[0], lon: lastPoint[1] },
            { lat: e.latlng.lat, lon: e.latlng.lng },
          ]).then((segment) => {
            if (segment && segment.length > 0) {
              currentRoute = [...currentRoute, ...segment];
              workingLayer.setLatLngs(currentRoute);
            }
          });
        }
        currentRoutingPoints.push([e.latlng.lat, e.latlng.lng]);
      });
    }
  });

  // Feature creation handler
  map.on("pm:create", (e) => {
    const { layer } = e;
    const type = layer.pm.options.type;
    layer.properties = { type };

    let obj;

    // Multi-secteur append
    if (type === "secteur-multi-append") {
      if (
        currentMultiSecteur &&
        currentMultiSecteur.layer instanceof L.Polyline
      ) {
        const target = currentMultiSecteur;
        const newSegment = layer.getLatLngs();
        let existing = target.layer.getLatLngs();

        if (existing.length > 0 && existing[0] instanceof L.LatLng) {
          existing = [existing, newSegment];
        } else {
          existing = [...existing, newSegment];
        }
        target.layer.setLatLngs(existing);

        try {
          map.removeLayer(layer);
        } catch (_) {}

        createAndBindPopup(target.layer, target._element_id);
        if (target.label) {
          createAndBindPopup(
            target.label.layer,
            target._element_id,
            target.layer,
          );
        }

        try {
          map.pm.disableDraw("Line");
        } catch (_) {}
        hideMultiSecteurInfo();
        currentMultiSecteur = null;
        return;
      } else {
        obj = Secteur.fromLayer(map, layer);
      }
    } else if (type === "secteur" || type === undefined) {
      obj = Secteur.fromLayer(map, layer);
    } else if (type === "approche") {
      obj = Approche.fromLayer(map, layer);
    } else if (type === "parking") {
      obj = Parking.fromLayer(map, layer);
    } else if (type === "bus_stop") {
      obj = BusStop.fromLayer(map, layer);
    } else if (type === "acces_velo") {
      obj = AccesVelo.fromLayer(map, layer);
    } else if (type === "falaise_voisine") {
      obj = FalaiseVoisine.fromLayer(map, layer);
    }

    obj._element_id = layer._leaflet_id || `feature_${featureId++}`;
    attachInvertIndexHandler(obj.layer);
    createAndBindPopup(obj.layer, obj._element_id);
    if (obj.label) {
      createAndBindPopup(obj.label.layer, obj._element_id, obj.layer);
    }
    obj.layer.openPopup();
    featureMap[obj._element_id] = obj;
  });

  map.on("pm:globaldrawmodetoggled", ({ enabled }) => {
    if (!enabled) {
      currentMultiSecteur = null;
      awaitingMultiSecteurSelection = false;
      clearMultiSecteurAppendHandlers();
      hideMultiSecteurInfo();
    }
  });

  // Click to disable edit mode
  map.on("click", () => {
    map.eachLayer((layer) => {
      if (layer.pm && layer.pm.enabled()) {
        layer.pm.disable();
      }
    });
  });

  // Helpers
  const getClosestSublineIndex = (polylineLayer, latlng) => {
    const all = polylineLayer.getLatLngs();
    if (all.length > 0 && all[0] instanceof L.LatLng) return 0;
    if (!(all.length > 0 && all[0] instanceof Array)) return null;

    const p = map.latLngToLayerPoint(latlng);
    let bestIdx = 0;
    let bestDist = Infinity;

    for (let i = 0; i < all.length; i++) {
      const line = all[i];
      const pts = line.map((ll) => map.latLngToLayerPoint(ll));
      for (let j = 0; j < pts.length - 1; j++) {
        const d = L.LineUtil.pointToSegmentDistance(p, pts[j], pts[j + 1]);
        if (d < bestDist) {
          bestDist = d;
          bestIdx = i;
        }
      }
    }
    return bestIdx;
  };

  const attachInvertIndexHandler = (layer) => {
    if (!(layer instanceof L.Polyline)) return;
    layer.on("click", (e) => {
      layer._invertTargetIndex = getClosestSublineIndex(layer, e.latlng);
    });
  };

  function createAndBindPopup(layer, id, _targetLayer) {
    const targetLayer = _targetLayer || layer;
    const field = (name, label, placeholder) => `
      <label for="${name}" class="w-full flex gap-2 items-center">
        <span class="flex-1 text-right">${label}: </span>
        <input
          type="text"
          name="${name}"
          ${name === "name" ? "autofocus" : ""}
          class="input-${id} input input-xs input-primary w-48"
          value="${targetLayer.properties[name] || ""}"
          placeholder="${placeholder}"
        />
      </label>`;

    let popupHtml = `<div class="w-[300px] flex flex-col gap-1 justify-stretch mx-auto">`;
    popupHtml += field("name", "Nom", "Nom");
    popupHtml += field("description", "Description", "optionnel");

    if (
      targetLayer.properties.type === "secteur" ||
      targetLayer.properties.type === undefined
    ) {
      popupHtml += field("parking", "Parkings", "p1, p2, ...");
      popupHtml += field("approche", "Approches", "a1, a2, ...");
      popupHtml += field(
        "gv",
        "Grandes Voies",
        "0 = non, 1 = uniq. GV, 2 = mixte",
      );
    } else if (targetLayer.properties.type === "approche") {
      popupHtml += field("parking", "Parkings", "p1, p2, ...");
      popupHtml += field("bus_stop", "Arrêts Bus", "b1, b2, ...");
    } else if (targetLayer.properties.type === "parking") {
      popupHtml += field("itineraire_acces", "Accès vélo", "v1, ...");
    } else if (targetLayer.properties.type === "falaise_voisine") {
      popupHtml += field("falaise_id", "ID Falaise", "247");
    }

    popupHtml += `<div class="flex flex-row gap-1 justify-between">`;
    popupHtml += `<button class="flex-1 btn btn-xs btn-error text-base-100" onclick="window._fde_deleteFeature('${containerId}', ${id})">Suppr.</button>`;
    popupHtml += `<button class="flex-1 btn btn-xs btn-accent" onclick="window._fde_editLayer('${containerId}', ${id})">${targetLayer.pm.enabled() ? "OK" : "Modif."}</button>`;

    if (
      (targetLayer.properties.type === "secteur" ||
        targetLayer.properties.type === undefined) &&
      layer instanceof L.Polyline
    ) {
      popupHtml += `<button class="flex-1 btn btn-xs btn-secondary" onclick="window._fde_invertLine('${containerId}', ${id})">Inverser</button>`;
    }

    popupHtml += `<button class="flex-1 btn btn-xs btn-primary" onclick="window._fde_updateLayer('${containerId}', ${id})">Enreg.</button>`;
    popupHtml += `</div></div>`;

    layer.bindTooltip(JSON.stringify(targetLayer.properties));
    layer.bindPopup(popupHtml, {
      className: "w-[350px]",
      minWidth: 300,
      maxWidth: 350,
    });
  }

  function updateAssociations() {
    const features = Object.values(featureMap);
    features.forEach((feature) => {
      feature.updateAssociations(features);
    });
  }

  function importData(data) {
    if (!data.features || data.features.length === 0) return;

    data.features.forEach((feature) => {
      let obj;
      if (
        feature.properties.type === "secteur" ||
        feature.properties.type === undefined
      ) {
        if (Secteur.isInvalidSecteur(feature)) return;
        obj = new Secteur(map, feature);
      } else if (feature.properties.type === "approche") {
        obj = new Approche(map, feature);
      } else if (feature.properties.type === "acces_velo") {
        obj = new AccesVelo(map, feature);
      } else if (feature.properties.type === "parking") {
        obj = new Parking(map, feature);
      } else if (feature.properties.type === "bus_stop") {
        obj = new BusStop(map, feature);
      } else if (feature.properties.type === "falaise_voisine") {
        obj = new FalaiseVoisine(map, feature);
      }

      if (obj) {
        obj._element_id = featureId++;
        featureMap[obj._element_id] = obj;
        attachInvertIndexHandler(obj.layer);
        createAndBindPopup(obj.layer, obj._element_id);
        if (obj.label) {
          createAndBindPopup(obj.label.layer, obj._element_id, obj.layer);
        }
      }
    });
    updateAssociations();
  }

  function exportData() {
    return {
      type: "FeatureCollection",
      features: Object.values(featureMap).map((feature) => ({
        ...feature.layer.toGeoJSON(),
        properties: feature.layer.properties,
      })),
    };
  }

  function clearAllFeatures() {
    Object.keys(featureMap).forEach((key) => {
      const feature = featureMap[key];
      map.removeLayer(feature.layer);
      feature.cleanUp();
      delete featureMap[key];
    });
  }

  // Global functions for popup buttons
  window._fde_editors = window._fde_editors || {};
  window._fde_editors[containerId] = {
    featureMap,
    map,
    createAndBindPopup,
    updateAssociations,
  };

  window._fde_editLayer = (cid, id) => {
    const editor = window._fde_editors[cid];
    editor.map.eachLayer((layer) => {
      if (layer.pm && layer.pm.enabled()) {
        layer.pm.disable();
      }
    });
    const feature = editor.featureMap[id];
    if (feature?.layer?.pm) {
      feature.layer.pm.enable();
    }
    feature?.layer?.closePopup();
    feature?.label?.layer?.closePopup();
  };

  window._fde_updateLayer = (cid, id) => {
    const editor = window._fde_editors[cid];
    const feature = editor.featureMap[id];
    const layer = feature.layer;
    let needsLabelUpdate = false;

    document.querySelectorAll(`.input-${id}`).forEach((input) => {
      const propertyName = input.name;
      if (propertyName && layer) {
        if (
          propertyName === "name" &&
          layer.properties.name !== input.value &&
          ["secteur", "falaise_voisine"].includes(feature.type)
        ) {
          needsLabelUpdate = true;
        }
        layer.properties[propertyName] = input.value;
      }
    });

    if (needsLabelUpdate) {
      feature?.updateLabel();
    }
    layer.closePopup();
    editor.createAndBindPopup(layer, feature._element_id);
    if (feature instanceof Secteur && feature.label) {
      editor.createAndBindPopup(
        feature.label.layer,
        feature._element_id,
        layer,
      );
    }
    editor.updateAssociations();
    feature.highlight();
    feature.unhighlight();
  };

  window._fde_invertLine = (cid, id) => {
    const editor = window._fde_editors[cid];
    const feature = editor.featureMap[id];
    const layer = feature.layer;
    const coords = layer.getLatLngs();

    if (coords.length > 0) {
      if (coords[0] instanceof L.LatLng) {
        coords.reverse();
        layer.setLatLngs(coords);
        return;
      }
      if (coords[0] instanceof Array) {
        const idx = layer._invertTargetIndex;
        if (typeof idx === "number" && coords[idx]) {
          coords[idx].reverse();
          layer.setLatLngs(coords);
        }
      }
    }
  };

  window._fde_deleteFeature = (cid, id) => {
    if (!confirm("Êtes-vous sûr de vouloir supprimer cet élément ?")) return;
    const editor = window._fde_editors[cid];
    const feature = editor.featureMap[id];
    editor.map.removeLayer(feature.layer);
    feature.cleanUp();
    delete editor.featureMap[id];
  };

  // Tableau récapitulatif
  function showTableau() {
    const modal = container.querySelector(".tableau-modal");
    modal.showModal();

    const tableauRecap = container.querySelector(".tableau-recap");
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

    const field = (featureId, key) => `
      <input
        type="text"
        name="${key}"
        class="input-${featureId} input input-xs"
        value="${(featureMap[featureId]?.layer?.properties[key] || "").replace(/"/g, "&quot;")}"
      />`;

    const saveBtn = (featureId) => `
      <button class="btn btn-xs btn-primary" onclick="window._fde_updateLayer('${containerId}', ${featureId})">
        <svg class="w-5 h-5 fill-none stroke-current">
          <use href="#save"></use>
        </svg>
      </button>`;

    let lastType = null;
    features.forEach((feature) => {
      const type = feature.layer.properties.type || "secteur";
      const fid = feature._element_id;

      if (lastType !== type) {
        if (lastType) tableauRecap.innerHTML += `<hr class="my-2">`;
        lastType = type;
        tableauRecap.innerHTML += `<h4 class="text-lg font-bold capitalize">${type}</h4>`;

        const headers = {
          secteur: [
            "Nom",
            "Description",
            "Parkings",
            "Approches",
            "GV",
            "Type",
            "",
          ],
          approche: [
            "Nom",
            "Description",
            "Parkings",
            "Arrêts Bus",
            "Type",
            "",
          ],
          parking: ["Nom", "Description", "Accès Vélo", "Type", ""],
          bus_stop: ["Nom", "Description", "Type", ""],
          acces_velo: ["Nom", "Description", "Type", ""],
          falaise_voisine: ["Nom", "Description", "ID Falaise", "Type", ""],
        };

        const cols = headers[type] || headers.secteur;
        tableauRecap.innerHTML += `<div class="grid items-center gap-2" style="grid-template-columns: repeat(${cols.length}, 1fr)">${cols.map((h) => `<div class="text-sm">${h}</div>`).join("")}</div>`;
      }

      const rows = {
        secteur: [
          field(fid, "name"),
          field(fid, "description"),
          field(fid, "parking"),
          field(fid, "approche"),
          field(fid, "gv"),
          field(fid, "type"),
          saveBtn(fid),
        ],
        approche: [
          field(fid, "name"),
          field(fid, "description"),
          field(fid, "parking"),
          field(fid, "bus_stop"),
          field(fid, "type"),
          saveBtn(fid),
        ],
        parking: [
          field(fid, "name"),
          field(fid, "description"),
          field(fid, "itineraire_acces"),
          field(fid, "type"),
          saveBtn(fid),
        ],
        bus_stop: [
          field(fid, "name"),
          field(fid, "description"),
          field(fid, "type"),
          saveBtn(fid),
        ],
        acces_velo: [
          field(fid, "name"),
          field(fid, "description"),
          field(fid, "type"),
          saveBtn(fid),
        ],
        falaise_voisine: [
          field(fid, "name"),
          field(fid, "description"),
          field(fid, "falaise_id"),
          field(fid, "type"),
          saveBtn(fid),
        ],
      };

      const row = rows[type] || rows.secteur;
      tableauRecap.innerHTML += `<div class="grid items-center gap-2" style="grid-template-columns: repeat(${row.length}, 1fr)">${row.join("")}</div>`;
    });
  }

  // Request contributor info via modal
  function requestContribInfo() {
    return new Promise((resolve) => {
      const modal = container.querySelector(".contrib-modal");
      const nomInput = modal.querySelector(".contrib-nom-input");
      const emailInput = modal.querySelector(".contrib-email-input");
      const confirmBtn = modal.querySelector(".contrib-confirm-btn");
      const cancelBtn = modal.querySelector(".contrib-cancel-btn");

      // Pre-fill if we have partial data
      if (contribNom) nomInput.value = contribNom;
      if (contribEmail) emailInput.value = contribEmail;

      modal.showModal();

      const cleanup = () => {
        confirmBtn.removeEventListener("click", handleConfirm);
        cancelBtn.removeEventListener("click", handleCancel);
        modal.removeEventListener("close", handleClose);
      };

      const handleConfirm = () => {
        if (!nomInput.value.trim() || !emailInput.value.trim()) {
          alert("Veuillez remplir tous les champs");
          return;
        }
        contribNom = nomInput.value.trim();
        contribEmail = emailInput.value.trim();
        // Save to localStorage for future use
        saveContribInfo(contribNom, contribEmail);
        modal.close();
        cleanup();
        resolve(true);
      };

      const handleCancel = () => {
        modal.close();
        cleanup();
        resolve(false);
      };

      const handleClose = () => {
        cleanup();
        resolve(false);
      };

      confirmBtn.addEventListener("click", handleConfirm);
      cancelBtn.addEventListener("click", handleCancel);
      modal.addEventListener("close", handleClose);
    });
  }

  // Perform the actual save
  async function doSave() {
    const saveBtn = container.querySelector(".save-geojson-btn");
    saveBtn?.classList.add("btn-disabled");

    try {
      const response = await fetch(
        `${apiEndpoint}?falaise_id=${falaise.falaise_id}`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({
            ...exportData(),
            author: contribNom,
            author_email: contribEmail,
          }),
        },
      );

      if (!response.ok) throw new Error("Erreur lors de l'enregistrement");
      await response.json();

      saveBtn?.classList.remove("btn-disabled");
      saveBtn?.classList.add("btn-accent");
      const content = saveBtn?.textContent;
      if (saveBtn) saveBtn.textContent = "Enregistré !";

      setTimeout(() => {
        saveBtn?.classList.remove("btn-accent");
        if (saveBtn) saveBtn.textContent = content;
      }, 2000);

      return true;
    } catch (error) {
      alert("Erreur lors de l'enregistrement : " + error.message);
      saveBtn?.classList.remove("btn-disabled");
      return false;
    }
  }

  // Save function - asks for contrib info if not available
  async function save() {
    // If no contrib info, request it via modal
    if (!contribNom || !contribEmail) {
      const confirmed = await requestContribInfo();
      if (!confirmed) return false;
    }

    if (
      !confirm(
        "Êtes-vous sûr de vouloir enregistrer les données ? Cela écrasera les données existantes.",
      )
    ) {
      return false;
    }

    return doSave();
  }

  // Wire up UI buttons
  const uploadInput = container.querySelector(".upload-geojson-input");
  const uploadAddInput = container.querySelector(".upload-add-geojson-input");

  container
    .querySelector(".upload-geojson-btn")
    ?.addEventListener("click", () => uploadInput?.click());
  container
    .querySelector(".upload-add-geojson-btn")
    ?.addEventListener("click", () => uploadAddInput?.click());

  const handleUpload = (isAdd) => (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const geojson = JSON.parse(e.target.result);
        if (!geojson.type || !geojson.features) {
          throw new Error("Invalid GeoJSON file");
        }
        if (!isAdd) clearAllFeatures();
        importData(geojson);
      } catch (err) {
        alert("Erreur lors du chargement du fichier GeoJSON : " + err.message);
      }
    };
    reader.readAsText(file);
  };

  uploadInput?.addEventListener("change", handleUpload(false));
  uploadAddInput?.addEventListener("change", handleUpload(true));

  container
    .querySelector(".download-geojson-btn")
    ?.addEventListener("click", () => {
      const geojson = exportData();
      const blob = new Blob([JSON.stringify(geojson, null, 2)], {
        type: "application/json",
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = `${falaise.falaise_id}_${falaise.falaise_nomformate}.geojson`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    });

  container.querySelector(".save-geojson-btn")?.addEventListener("click", save);

  // Fetch bus stops button
  container
    .querySelector(".fetch-bus-stops-btn")
    ?.addEventListener("click", async () => {
      try {
        // Clear previous
        searchLayers.busStops.clearLayers();

        const stops = await fetchBusStops(map);

        stops.forEach((s) => {
          const descLines = (s.routes || [])
            .map((r) => {
              const network = (r.network || "").trim();
              const ref = (r.ref || "").trim();
              const name = (r.name || "").trim();
              if (network && ref) return `${network} : Ligne ${ref}`;
              if (ref) return `Ligne ${ref}`;
              return name;
            })
            .filter((t) => t && t.length > 0);

          const escapeHtml = (str) =>
            String(str)
              .replaceAll("&", "&amp;")
              .replaceAll("<", "&lt;")
              .replaceAll(">", "&gt;")
              .replaceAll('"', "&quot;");

          const description = descLines.length > 0 ? descLines.join("\n") : "";
          const marker = L.circleMarker([s.lat, s.lon], {
            radius: 6,
            weight: 2,
            color: "#2563eb",
            fillColor: "#60a5fa",
            fillOpacity: 0.7,
          });

          const formHtml = `
          <div class="flex flex-col gap-2 w-[260px]">
            <div class="text-sm opacity-70">Arrêt proposé via Overpass</div>
            <label class="flex flex-col gap-1">
              <span class="text-sm">Nom</span>
              <input type=\"text\" class=\"input input-xs w-full\" value=\"${escapeHtml(s.name || "")}\">
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-sm">Description</span>
              <textarea class=\"textarea textarea-xs w-full\" rows=\"4\">${escapeHtml(description || s.network)}</textarea>
            </label>
          </div>`;

          marker.bindPopup(formHtml, { minWidth: 260, maxWidth: 300 });
          marker.on("popupopen", (e) => {
            const root = e?.popup?.getElement?.() || document;
            const content = root.querySelector?.(".leaflet-popup-content");
            // Append action button if not already present
            if (content && !root.querySelector?.(".add-bus-stop-btn")) {
              const footer = document.createElement("div");
              footer.className = "flex justify-end";
              footer.innerHTML =
                '<button class="btn btn-xs btn-primary add-bus-stop-btn" type="button">Ajouter cet arrêt</button>';
              content.appendChild(footer);
            }
            const addBtn = root.querySelector?.(".add-bus-stop-btn");
            const nameInput = root.querySelector?.("input");
            const descInput = root.querySelector?.("textarea");
            if (!addBtn) return;
            addBtn.addEventListener(
              "click",
              () => {
                const name = (nameInput?.value || s.name || "").trim();
                const description = (descInput?.value || "").trim();

                const feature = {
                  type: "Feature",
                  geometry: { type: "Point", coordinates: [s.lon, s.lat] },
                  properties: { name, description },
                };

                const obj = new BusStop(map, feature);
                obj._element_id = featureId++;
                featureMap[obj._element_id] = obj;
                attachInvertIndexHandler(obj.layer);
                createAndBindPopup(obj.layer, obj._element_id);
                if (obj.label) {
                  createAndBindPopup(
                    obj.label.layer,
                    obj._element_id,
                    obj.layer,
                  );
                }
                updateAssociations();

                try {
                  marker.closePopup();
                } catch (_) {}
                try {
                  searchLayers.busStops.removeLayer(marker);
                } catch (_) {}
                try {
                  obj.layer.openPopup();
                } catch (_) {}
              },
              { once: true },
            );
          });
          searchLayers.busStops.addLayer(marker);
        });
      } catch (e) {
        console.error("Erreur récupération arrêts bus:", e);
        alert(
          "Impossible de récupérer les arrêts de bus pour la zone visible.",
        );
      }
    });

  // Save and navigate to next step
  const saveAndNextBtn = container.querySelector(".save-and-next-btn");
  saveAndNextBtn?.addEventListener("click", async () => {
    const nextUrl = saveAndNextBtn.dataset.nextUrl;
    if (!nextUrl) return;

    const success = await save();
    if (success) {
      window.location.href = nextUrl;
    }
  });

  // Keyboard shortcut
  document.addEventListener("keydown", (event) => {
    if (event.key === "s" && (event.ctrlKey || event.metaKey)) {
      event.preventDefault();
      save();
    }
  });

  // Go to coords
  container.querySelector(".goto-coords-btn")?.addEventListener("click", () => {
    const input = container.querySelector(".coords-input");
    const latlngInput = input?.value;
    if (!latlngInput) return;

    const [lat, lng] = latlngInput.split(",").map(parseFloat);
    if (isNaN(lat) || isNaN(lng)) {
      alert("Latitude et longitude invalides.");
      return;
    }

    map.setView([lat, lng], 17);
    const marker = L.circle([lat, lng], { radius: 20 }).addTo(map);
    map.once("click", () => map.removeLayer(marker));
  });

  // Load initial data
  fetch(`${apiEndpoint}?falaise_id=${falaise.falaise_id}`)
    .then((response) => {
      if (!response.ok)
        throw new Error("Erreur lors de la récupération des détails");
      return response.json();
    })
    .then((data) => importData(data))
    .catch((error) => console.error("Erreur lors du chargement :", error));

  // Return instance
  return {
    map,
    featureMap,
    importData,
    exportData,
    save,
    clearAllFeatures,
  };
}
