/**
 * Carte « lier des arrêts à une falaise » (formulaire d'ajout de falaise).
 *
 * Seconde carte, distincte de la carte de placement de la falaise : centrée sur
 * la falaise (zoom 14) dès qu'elle est placée, elle affiche les arrêts du
 * catalogue de la zone visible (icône colorée par type s'ils sont liés, grisée
 * sinon). Un clic ouvre « Lier cet arrêt » / « Délier cet arrêt ». L'état des
 * arrêts liés appartient à l'appelant (composant Vue du formulaire) :
 *
 *   - entrée  : événement `velogrimpe:arrets-pietons-changed` { detail: { ids } }
 *   - sortie  : `setLinked(id, linked)` (callback)
 *   - position de la falaise : événement `velogrimpe:falaise-position`
 *     { detail: { lat, lng } } ; visibilité du bloc : `velogrimpe:arrets-pietons-toggle`
 *     { detail: { visible } } (la carte doit recalculer sa taille à l'affichage).
 *
 * Carte COMMUNE aux accès du formulaire : elle montre aussi l'accès à vélo en cours de saisie (Lot 8quater) :
 *   - `velogrimpe:acces-velo-changed` { detail: { id } } : arrêt de départ (marqueur vert, toujours dessiné) ;
 *   - `velogrimpe:acces-velo-gpx` { detail: { xml } } : aperçu de la trace GPX choisie (null : effacée) ;
 *   - `velogrimpe:arrets-pietons-active` { detail: { active } } : « accessible sans vélo » coché, donc les
 *     arrêts sont cliquables pour les lier ; sinon la carte est en lecture seule.
 *
 * Repères DOM : chaque marqueur porte `data-arret-id` (+ `data-linked="true"`, `data-velo-depart="true"`).
 *
 * @module arrets-lies-map
 */
import { createAjoutMap } from "/js/components/map/ajout-map.js";
import { arretDivIcon } from "/js/components/map/arret-icon.js";

const FALAISE_ZOOM = 14;
const MIN_ZOOM = 11; // en deçà, trop d'arrêts : on n'en dessine aucun
const MAX_MARKERS = 300;
const ICON_SIZE = 26;
const UNLINKED_COLOR = "#9ca3af";
const VELO_COLOR = "#2e8b57";
const GPX_STYLE = { weight: 4, color: VELO_COLOR };

const escapeHtml = (str) =>
  String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");

/**
 * @param {Object} opts
 * @param {string} opts.elId id du conteneur de la carte
 * @param {HTMLElement|null} opts.instructionsEl message affiché tant que la falaise n'est pas placée
 * @param {Array<{id:number, nom:string, lat:number, lng:number, types:string[]}>} opts.arrets catalogue
 * @param {function(number, boolean): void} opts.setLinked demande de lier/délier un arrêt
 * @returns {{ map: L.Map }}
 */
export function initArretsLiesMap({ elId, instructionsEl, arrets, setLinked }) {
  const { map } = createAjoutMap(elId);
  const layer = L.layerGroup().addTo(map);
  let linked = new Set();
  let falaisePos = null;
  let falaisePin = null;
  let visible = false;
  let liaisonActive = false; // « accessible sans vélo » coché : arrêts cliquables pour lier / délier
  let veloArretId = null; // arrêt de départ de l'accès à vélo en cours de saisie
  let gpxLayer = null; // aperçu de sa trace

  const popupFor = (arret) => {
    const estLie = linked.has(arret.id);
    const estDepartVelo = arret.id === veloArretId;
    const div = document.createElement("div");
    div.className = "flex flex-col gap-2 w-[220px]";
    div.innerHTML = `<div class="font-bold">${escapeHtml(arret.nom)}${
      estLie ? ' <span class="badge badge-success badge-xs">lié</span>' : ""
    }${estDepartVelo ? ' <span class="badge badge-primary badge-xs">départ vélo</span>' : ""}</div>`;
    if (!liaisonActive) return div; // lecture seule : pas de lien sans vélo à gérer
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = `btn btn-xs ${estLie ? "btn-error" : "btn-primary"}`;
    btn.textContent = estLie ? "Délier cet arrêt" : "Lier cet arrêt";
    btn.addEventListener("click", () => {
      setLinked(arret.id, !estLie);
      map.closePopup();
    });
    div.appendChild(btn);
    return div;
  };

  const render = () => {
    layer.clearLayers();
    if (!visible) return;
    const horsZoom = map.getZoom() < MIN_ZOOM; // trop d'arrêts : aucun, sauf le départ de l'accès à vélo
    const bounds = map.getBounds().pad(0.2);
    // Les arrêts liés sont toujours dessinés (même hors vue) pour garder le lien visible.
    const aDessiner = arrets
      .filter((a) => a.id === veloArretId || (!horsZoom && (linked.has(a.id) || bounds.contains([a.lat, a.lng]))))
      .slice(0, MAX_MARKERS + linked.size + 1);
    for (const arret of aDessiner) {
      const estLie = linked.has(arret.id);
      const estDepartVelo = arret.id === veloArretId;
      // Départ de l'accès à vélo : vert, un peu plus grand ; arrêts sans vélo liés : couleur de leur type
      const options = estDepartVelo ? { color: VELO_COLOR } : estLie ? {} : { color: UNLINKED_COLOR };
      const marker = L.marker([arret.lat, arret.lng], {
        icon: arretDivIcon(arret.types, estDepartVelo ? ICON_SIZE + 4 : ICON_SIZE, options),
        keyboard: false,
        zIndexOffset: estDepartVelo ? 1000 : 0,
      }).addTo(layer);
      const el = marker.getElement();
      if (el) {
        el.setAttribute("data-arret-id", String(arret.id));
        if (estLie) el.setAttribute("data-linked", "true");
        if (estDepartVelo) el.setAttribute("data-velo-depart", "true");
      }
      marker.bindTooltip(escapeHtml(arret.nom) + (estDepartVelo ? " 🚲" : ""), { direction: "right", offset: [ICON_SIZE / 2, 0] });
      marker.on("click", () => {
        L.popup({ offset: [0, -ICON_SIZE / 2] }).setLatLng([arret.lat, arret.lng]).setContent(popupFor(arret)).openOn(map);
      });
    }
  };

  const recentrer = () => {
    if (!falaisePos) return;
    if (falaisePin) map.removeLayer(falaisePin);
    // Repère de la falaise, non interactif : ne masque pas les arrêts voisins.
    falaisePin = L.marker([falaisePos.lat, falaisePos.lng], {
      interactive: false,
      keyboard: false,
      icon: L.icon({ iconUrl: "/images/map/icone_falaise_carte.png", iconSize: [24, 24], iconAnchor: [12, 24] }),
    }).addTo(map);
    map.setView([falaisePos.lat, falaisePos.lng], FALAISE_ZOOM);
    if (instructionsEl) instructionsEl.classList.add("hidden");
    render();
  };

  map.on("moveend", render);

  document.addEventListener("velogrimpe:falaise-position", (e) => {
    falaisePos = { lat: e.detail.lat, lng: e.detail.lng };
    if (visible) recentrer();
  });
  document.addEventListener("velogrimpe:arrets-pietons-changed", (e) => {
    linked = new Set((e.detail?.ids || []).map(Number));
    render();
  });
  document.addEventListener("velogrimpe:arrets-pietons-active", (e) => {
    liaisonActive = !!e.detail?.active;
  });

  // --- Accès à vélo en cours de saisie : arrêt de départ + aperçu de la trace -------------------------------
  const cadrerAcces = () => {
    const points = [];
    if (falaisePos) points.push([falaisePos.lat, falaisePos.lng]);
    const depart = arrets.find((a) => a.id === veloArretId);
    if (depart) points.push([depart.lat, depart.lng]);
    if (gpxLayer?.getBounds?.().isValid?.()) {
      map.fitBounds(gpxLayer.getBounds().extend(points), { padding: [30, 30], maxZoom: FALAISE_ZOOM });
    } else if (points.length > 1) {
      map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: FALAISE_ZOOM });
    } else if (depart) {
      map.setView([depart.lat, depart.lng], FALAISE_ZOOM - 1); // falaise pas encore placée : on montre l'arrêt
    }
  };
  document.addEventListener("velogrimpe:acces-velo-changed", (e) => {
    veloArretId = e.detail?.id ?? null;
    render();
    if (visible) cadrerAcces();
  });
  document.addEventListener("velogrimpe:acces-velo-gpx", (e) => {
    if (gpxLayer) {
      map.removeLayer(gpxLayer);
      gpxLayer = null;
    }
    const xml = e.detail?.xml;
    if (!xml || !String(xml).includes("<")) return;
    gpxLayer = new L.GPX(String(xml), {
      async: true,
      parseElements: ["track", "route"],
      markers: { startIcon: null, endIcon: null },
      polyline_options: GPX_STYLE,
    })
      .on("loaded", () => cadrerAcces())
      .addTo(map);
  });

  document.addEventListener("velogrimpe:arrets-pietons-toggle", (e) => {
    visible = !!e.detail?.visible;
    if (!visible) return;
    // Le conteneur vient d'être affiché : taille à recalculer avant tout recadrage.
    requestAnimationFrame(() => {
      map.invalidateSize();
      recentrer();
    });
  });

  window.arretsLiesMap = map; // repère de debug / tests, comme window.map sur la page falaise
  return { map };
}
