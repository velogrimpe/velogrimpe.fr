/**
 * Carte de vérification visuelle des formulaires d'itinéraire vélo (ajout et
 * édition) : gare de départ, falaise d'arrivée, trace GPX existante ou en cours
 * d'upload. Utilise le bundle map global (`L`, chargé via map_bundle_js).
 *
 * Pilotée par des CustomEvent sur `document`, émis par l'app Vue de la page :
 *   - velogrimpe:velo-form:gare     detail: { latlng, nom } | null
 *   - velogrimpe:velo-form:falaise  detail: { latlng, nom, fermee, bloc } | null
 *   - velogrimpe:velo-form:gpx-url  detail: { url } | null   (trace existante)
 *
 * Et par l'input file `gpxInputId` : prévisualisation du GPX choisi (sans les
 * waypoints, cohérent avec le nettoyage côté serveur), qui remplace la trace
 * existante à l'écran.
 *
 * Usage :
 *   initVeloFormMap({ mapElId: 'velo-map', gpxInputId: 'gpx_file' });
 */
import { createAjoutMap } from "/js/components/map/ajout-map.js";
import Gare from "/js/components/map/gare.js";
import Falaise from "/js/components/map/falaise.js";

const GPX_STYLE = { weight: 4, color: "#2e8b57" };
const GPX_EXISTING_STYLE = { weight: 4, color: "#1d4ed8", dashArray: "6 6" };

export function initVeloFormMap({ mapElId = "velo-map", gpxInputId = "gpx_file" } = {}) {
  const { map } = createAjoutMap(mapElId);

  let gareLayer = null;
  let falaiseLayer = null;
  let gpxLayer = null; // trace uploadée (prioritaire à l'affichage)
  let existingGpxLayer = null; // trace déjà en base

  // Recadre la carte sur l'union des couches présentes.
  function refitBounds() {
    let bounds = null;
    const extend = (b) => {
      if (!b || !b.isValid()) return;
      bounds = bounds ? bounds.extend(b) : L.latLngBounds(b.getSouthWest(), b.getNorthEast());
    };
    const pointBounds = (layer) =>
      layer.getBounds ? layer.getBounds() : L.latLngBounds([layer.getLatLng(), layer.getLatLng()]);
    if (gareLayer) extend(pointBounds(gareLayer));
    if (falaiseLayer) extend(pointBounds(falaiseLayer));
    if (gpxLayer && gpxLayer.getBounds) extend(gpxLayer.getBounds());
    else if (existingGpxLayer && existingGpxLayer.getBounds) extend(existingGpxLayer.getBounds());
    if (bounds && bounds.isValid()) {
      map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });
    }
  }

  // Petit marqueur ponctuel à partir d'une chaîne "lat,lng".
  function pointMarker(latlng, icon) {
    const [lat, lng] = String(latlng).split(",").map(parseFloat);
    if (isNaN(lat) || isNaN(lng)) return null;
    return L.marker([lat, lng], icon ? { icon } : {});
  }

  function removeLayer(layer) {
    if (layer) map.removeLayer(layer);
    return null;
  }

  function gpxFromXml(xml, style) {
    if (!xml || !String(xml).includes("<")) return null;
    return new L.GPX(String(xml), {
      async: true,
      parseElements: ["track", "route"],
      markers: { startIcon: null, endIcon: null },
      polyline_options: style,
    }).on("loaded", () => refitBounds());
  }

  document.addEventListener("velogrimpe:velo-form:gare", (e) => {
    gareLayer = removeLayer(gareLayer);
    const d = e.detail;
    if (d && d.latlng) {
      gareLayer = pointMarker(d.latlng, Gare.gareIcon(Gare.iconSize));
      if (gareLayer) gareLayer.addTo(map);
    }
    refitBounds();
  });

  document.addEventListener("velogrimpe:velo-form:falaise", (e) => {
    falaiseLayer = removeLayer(falaiseLayer);
    const d = e.detail;
    if (d && d.latlng) {
      const icon = Falaise.falaiseIcon(Falaise.iconSize, d.fermee === "1", d.bloc, "falaise-icon");
      falaiseLayer = pointMarker(d.latlng, icon);
      if (falaiseLayer) falaiseLayer.addTo(map);
    }
    refitBounds();
  });

  // Trace existante (édition) : chargée depuis son URL publique.
  let existingRequest = 0;
  document.addEventListener("velogrimpe:velo-form:gpx-url", (e) => {
    existingGpxLayer = removeLayer(existingGpxLayer);
    // Un changement d'itinéraire invalide aussi la prévisualisation d'upload.
    gpxLayer = removeLayer(gpxLayer);
    const gpxInput = document.getElementById(gpxInputId);
    if (gpxInput) gpxInput.value = "";
    const url = e.detail && e.detail.url;
    const requestId = ++existingRequest;
    if (!url) {
      refitBounds();
      return;
    }
    fetch(url)
      .then((r) => (r.ok ? r.text() : Promise.reject(new Error("gpx fetch failed"))))
      .then((xml) => {
        if (requestId !== existingRequest) return; // sélection changée entre-temps
        existingGpxLayer = gpxFromXml(xml, GPX_EXISTING_STYLE);
        if (existingGpxLayer) existingGpxLayer.addTo(map);
        refitBounds();
      })
      .catch(() => refitBounds());
  });

  // Prévisualisation de la trace GPX uploadée.
  const gpxInput = document.getElementById(gpxInputId);
  if (gpxInput) {
    gpxInput.addEventListener("change", () => {
      gpxLayer = removeLayer(gpxLayer);
      const file = gpxInput.files && gpxInput.files[0];
      if (!file) {
        if (existingGpxLayer) existingGpxLayer.addTo(map);
        refitBounds();
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        gpxLayer = gpxFromXml(reader.result, GPX_STYLE);
        if (gpxLayer) {
          // La trace uploadée remplace visuellement l'existante.
          if (existingGpxLayer) map.removeLayer(existingGpxLayer);
          gpxLayer.addTo(map);
        }
      };
      reader.readAsText(file);
    });
  }

  // La carte peut être créée dans un conteneur masqué (section révélée après
  // sélection) : Leaflet a alors une taille nulle, ne charge qu'une partie des
  // tuiles et calcule mal les bounds. On recalcule dès que le conteneur change
  // de taille / devient visible.
  const mapEl = document.getElementById(mapElId);
  if (mapEl && "ResizeObserver" in window) {
    let lastW = 0;
    let lastH = 0;
    new ResizeObserver(() => {
      const { width, height } = mapEl.getBoundingClientRect();
      if (width === lastW && height === lastH) return;
      lastW = width;
      lastH = height;
      if (width > 0 && height > 0) {
        map.invalidateSize();
        refitBounds();
      }
    }).observe(mapEl);
  }

  return { map };
}
