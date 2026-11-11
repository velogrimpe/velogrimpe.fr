/**
 * Admin « Arrêts et lignes » (admin/arrets_lignes.php) : liste filtrable + carte.
 * Données initiales injectées par PHP dans #page-data ; la géométrie des lignes
 * est chargée à la première ouverture de la carte (/api/fetch_lignes.php?geometry=1).
 */
import { createAjoutMap } from "/js/components/map/ajout-map.js";
import { arretDivIcon, arretTypeDominant } from "/js/components/map/arret-icon.js";
import { ligneColor } from "/js/components/map/ligne-view.js";

const { arrets, lignes, falaises, contrib, token } = JSON.parse(document.getElementById("page-data").textContent);
const $ = (id) => document.getElementById(id);

const TYPE_LABELS = { ter: "TER", tgv: "TGV", bus: "Bus", tram: "Tram", telepherique: "Téléph." };
const esc = (s) =>
  String(s ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;");
const norm = (s) => String(s ?? "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

// Bouton-icône (symbole du sprite /symbols/icons.svg) ; le libellé reste en title / aria-label.
const ico = (name) =>
  `<svg class="w-4 h-4 fill-none stroke-current" stroke-width="1.5" aria-hidden="true"><use href="#${name}"></use></svg>`;
const iconBtn = (name, label, cls, attrs) =>
  `<button type="button" class="btn btn-xs btn-square ${cls}" title="${label}" aria-label="${label}" ${attrs}>${ico(name)}</button>`;
const iconLink = (name, label, cls, href) =>
  `<a class="btn btn-xs btn-square ${cls}" style="${BTN_STYLE}" title="${label}" aria-label="${label}" href="${href}">${ico(name)}</a>`;

// Les liens des popups Leaflet héritent de la couleur bleue de `a` : on force la couleur du bouton.
const BTN_STYLE = "color:var(--color-primary-content,#fff)";
const editArretUrl = (id) => `/ajout/ajout_arret.php?admin=${encodeURIComponent(token)}&arret_id=${id}`;
const editLigneUrl = (id) => `/ajout/ajout_ligne.php?admin=${encodeURIComponent(token)}&ligne_id=${id}`;

// --- État dans l'URL (?q=…&types=bus,tram&falaises=1&deleted=1&view=carte) ---
// Seules les valeurs différentes des défauts sont écrites ; les autres paramètres sont préservés.
const DEFAULT_TYPES = ["bus", "tram", "telepherique"];
const typeBoxes = () => [...document.querySelectorAll("#type-filters [data-type]")];

// Position de la carte : ?map=zoom/lat/lng
let savedMap = null;
function parseMapParam(v) {
  const [z, lat, lng] = String(v ?? "").split("/").map(Number);
  return [z, lat, lng].every(Number.isFinite) ? { zoom: z, center: [lat, lng] } : null;
}

function readUrl() {
  const p = new URLSearchParams(location.search);
  savedMap = parseMapParam(p.get("map"));
  $("search").value = p.get("q") ?? "";
  if (p.has("types")) {
    const wanted = new Set(p.get("types").split(",").filter(Boolean));
    typeBoxes().forEach((c) => (c.checked = wanted.has(c.dataset.type)));
  }
  $("only-falaises").checked = p.get("falaises") === "1";
  $("show-deleted").checked = p.get("deleted") === "1";
  return p.get("view") === "carte" ? "carte" : "liste";
}

function writeUrl() {
  const p = new URLSearchParams(location.search);
  const setOrDel = (k, v) => (v ? p.set(k, v) : p.delete(k));
  setOrDel("q", $("search").value.trim());
  const types = typeBoxes().filter((c) => c.checked).map((c) => c.dataset.type);
  const isDefault = types.length === DEFAULT_TYPES.length && DEFAULT_TYPES.every((t) => types.includes(t));
  if (isDefault) p.delete("types");
  else p.set("types", types.join(","));
  setOrDel("falaises", state.onlyFalaises ? "1" : "");
  setOrDel("deleted", state.showDeleted ? "1" : "");
  setOrDel("view", state.view === "carte" ? "carte" : "");
  if (map) {
    const c = map.getCenter();
    p.set("map", `${Math.round(map.getZoom() * 100) / 100}/${c.lat.toFixed(5)}/${c.lng.toFixed(5)}`);
  }
  const qs = p.toString();
  history.replaceState(null, "", location.pathname + (qs ? `?${qs}` : "") + location.hash);
}

// --- Filtres -------------------------------------------------------------
const state = { q: "", types: new Set(), showDeleted: false, onlyFalaises: false, view: "liste" };
const readFilters = () => {
  state.q = norm($("search").value.trim());
  state.types = new Set(
    [...document.querySelectorAll("#type-filters [data-type]")].filter((c) => c.checked).map((c) => c.dataset.type),
  );
  state.showDeleted = $("show-deleted").checked;
  state.onlyFalaises = $("only-falaises").checked;
};

// Un arrêt est visible si l'un de ses types est coché (un arrêt TER + bus reste visible
// avec « Bus » seul coché) ; un arrêt sans aucun type n'est jamais masqué par ce filtre.
const arretVisible = (a) =>
  (state.showDeleted || !a.deleted) &&
  (!state.onlyFalaises || a.falaises.length > 0) &&
  (a.types.length === 0 || a.types.some((t) => state.types.has(t))) &&
  (!state.q || norm(`${a.nom} ${a.commune ?? ""} ${a.id} ${a.falaises.join(" ")}`).includes(state.q));

const ligneLabel = (l) => (l.numero ? `${l.numero} — ` : "") + l.nom;
const ligneVisible = (l) =>
  !state.q || norm(`${l.nom} ${l.numero ?? ""} ${l.operateur ?? ""} ${l.id}`).includes(state.q);

// --- Fusion : 1er clic « Fusionner » sur l'arrêt à absorber, 2e clic « Conserver celui-ci » sur la cible ---
let mergeSource = null;
const mergeButtons = (a) =>
  mergeSource === null
    ? iconBtn("merge", "Fusionner avec un autre arrêt", "btn-outline", `data-merge-from="${a.id}"`)
    : a.id === mergeSource
      ? ""
      : `<button type="button" class="btn btn-xs btn-warning" data-merge-into="${a.id}">Conserver celui-ci</button>`;

function renderMergeBanner() {
  const b = $("merge-banner");
  const src = arrets.find((x) => x.id === mergeSource);
  b.classList.toggle("hidden", !src);
  b.classList.toggle("flex", !!src);
  if (src)
    b.innerHTML = `<span>Fusion : « <b>${esc(src.nom)}</b> » (#${src.id}) sera absorbé. Choisissez l'arrêt à conserver (liste ou carte).</span>
      <button type="button" class="btn btn-xs" data-merge-cancel>Annuler</button>`;
}

async function mergeArrets(sourceId, cibleId) {
  const s = arrets.find((x) => x.id === sourceId);
  const c = arrets.find((x) => x.id === cibleId);
  if (!s || !c) return;
  if (!confirm(`Fusionner « ${s.nom} » (#${s.id}) dans « ${c.nom} » (#${c.id}) ?\nFalaises, lignes, références et trajets sont reportés sur « ${c.nom} » ; « ${s.nom} » est supprimé.`)) return;
  try {
    const r = await fetch("/api/merge_arrets.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
      body: JSON.stringify({ source_id: sourceId, cible_id: cibleId }),
    });
    if (!r.ok) throw new Error((await r.json().catch(() => ({}))).error || r.statusText);
    // Recharge pour récupérer les compteurs, falaises et types à jour.
    location.reload();
  } catch (e) {
    alert(`Fusion impossible : ${e.message}`);
  }
}

// --- Liste ---------------------------------------------------------------
const MAX_ROWS = 500; // évite de rendre des milliers de lignes d'un coup

function renderList() {
  const as = arrets.filter(arretVisible);
  $("count-arrets").textContent = as.length;
  $("tbody-arrets").innerHTML =
    as.slice(0, MAX_ROWS).map((a) => `
      <tr class="${a.deleted ? "opacity-50" : ""}">
        <td><b>${esc(a.nom)}</b>${a.deleted ? ' <span class="badge badge-error badge-xs">supprimé</span>' : ""}
          <div class="text-xs text-base-content/60">${esc(a.commune ?? "")} · #${a.id}</div></td>
        <td>${a.types.map((t) => `<span class="badge badge-ghost badge-xs mr-1">${TYPE_LABELS[t] ?? esc(t)}</span>`).join("")}</td>
        <td class="text-right">${a.nb_lignes}</td>
        <td class="text-xs">${a.falaises.map(esc).join(", ")}</td>
        <td class="text-right whitespace-nowrap">${iconBtn("map-pin", "Voir sur la carte", "btn-outline", `data-show-arret="${a.id}"`)}
          ${iconLink("pencil", "Modifier", "btn-primary text-primary-content", editArretUrl(a.id))}
          ${a.deleted ? "" : mergeButtons(a)}
          ${a.deleted ? "" : iconBtn("trash", "Supprimer", "btn-error btn-outline", `data-delete-arret="${a.id}"`)}</td>
      </tr>`).join("") +
    (as.length > MAX_ROWS
      ? `<tr><td colspan="5" class="text-center text-sm text-base-content/60">${as.length - MAX_ROWS} autres résultats : affinez la recherche.</td></tr>`
      : "") ||
    '<tr><td colspan="5" class="text-center text-base-content/60">Aucun arrêt</td></tr>';

  const ls = lignes.filter(ligneVisible);
  $("count-lignes").textContent = ls.length;
  $("tbody-lignes").innerHTML =
    ls.map((l) => `
      <tr>
        <td><span class="inline-block w-3 h-3 rounded-full align-middle mr-1" style="background:${esc(ligneColor(l))}"></span>
          <b>${esc(ligneLabel(l))}</b> <span class="text-xs text-base-content/60">#${l.id}</span></td>
        <td>${esc(l.operateur ?? "")}</td>
        <td class="text-right">${l.nb_arrets}</td>
        <td class="text-right whitespace-nowrap">${iconBtn("map-pin", "Voir sur la carte", "btn-outline", `data-show-ligne="${l.id}"`)}
          ${iconLink("pencil", "Modifier", "btn-primary text-primary-content", editLigneUrl(l.id))}</td>
      </tr>`).join("") || '<tr><td colspan="4" class="text-center text-base-content/60">Aucune ligne</td></tr>';
}

// Couleur d'un cercle (arrêt sans accès falaise) : celle de sa ligne, noir si l'arrêt n'en a pas
const arretCercleColor = (a) => {
  const l = a.ligne_id ? lignes.find((x) => x.id === a.ligne_id) : null;
  return l ? ligneColor(l) : "#000";
};

// --- Carte ---------------------------------------------------------------
let map = null;
let arretLayer = null;
let ligneLayer = null;
let lignesGeo = null; // lignes avec géométrie, chargées à la demande
let falaiseLayer = null;
const falaiseMarkers = [];

// --- Falaises : point jusqu'au zoom 12, puis icône (comme le formulaire de ligne) ----
const DOT_MAX_ZOOM = 12;
const dotMode = () => map.getZoom() <= DOT_MAX_ZOOM;
const falaiseIcon = () =>
  dotMode()
    ? L.divIcon({
        className: "vg-ligne-falaise-dot",
        html: '<div style="width:10px;height:10px;background:#16a34a;" class="rounded-full border-2 border-white shadow"></div>',
        iconSize: [10, 10],
        iconAnchor: [5, 5],
        tooltipAnchor: [5, 0],
      })
    : L.icon({
        iconUrl: "/images/map/icone_falaise_carte.png",
        iconSize: [22, 22],
        iconAnchor: [11, 11],
        tooltipAnchor: [11, 0],
        className: "vg-ligne-falaise-icon",
      });

// --- Liaison arrêt ↔ falaise (api/link_arret_falaise.php, accès à pied ou à vélo) ----
// 1. clic sur un arrêt / une falaise, 2. bouton « lier », 3. clic sur la falaise / l'arrêt à lier,
// 4. formulaire des informations (accès à pied ou à vélo).
let linkSource = null; // { kind: "arret" | "falaise", item }
const kindOf = new WeakMap(); // marqueur -> { kind, item }
const arretNom = (a) => `« ${a.nom} » (#${a.id})`;

function startLink(kind, id) {
  const item = (kind === "arret" ? arrets : falaises).find((x) => x.id === id);
  if (!item) return;
  map.closePopup();
  linkSource = { kind, item };
  // La couche à désigner doit être visible
  const cible = kind === "arret" ? "layer-falaises" : "layer-arrets";
  if (!$(cible).checked) {
    $(cible).checked = true;
    kind === "arret" ? renderFalaises() : renderMap();
  }
  $("map-status").innerHTML = `Choisir avec quel${kind === "arret" ? "le falaise" : " arrêt"} lier « <b>${esc(item.nom)}</b> » : cliquez dessus sur la carte.
    <button type="button" class="btn btn-xs ml-2" data-link-cancel>Annuler</button>`;
}
function cancelLink() {
  linkSource = null;
  $("map-status").textContent = "";
}

// Un clic sur un marqueur de l'autre type, en mode liaison, ouvre le formulaire au lieu du popup normal
function onPopupOpenWhileLinking(e) {
  if (!linkSource) return;
  const t = kindOf.get(e.popup._source);
  if (!t || t.kind === linkSource.kind) return; // même type : popup normal
  map.closePopup();
  const arret = linkSource.kind === "arret" ? linkSource.item : t.item;
  const falaise = linkSource.kind === "arret" ? t.item : linkSource.item;
  linkSource = null;
  $("map-status").textContent = "";
  L.popup({ minWidth: 260, maxWidth: 280 })
    .setLatLng([t.item.lat, t.item.lng])
    .setContent(linkForm(arret, falaise))
    .openOn(map);
}

/** Formulaire d'un accès entre `arret` et `falaise`. */
function linkForm(arret, falaise) {
  const root = document.createElement("div");
  root.className = "flex flex-col gap-1 text-xs text-base-content";
  root.innerHTML = `
    <div class="text-sm"><b>${esc(arret.nom)}</b> <span class="opacity-60">#${arret.id}</span><br>↔ <b>${esc(falaise.nom)}</b></div>
    <select class="l-type select select-xs w-full" aria-label="Type d'accès">
      <option value="pied">Accès sans vélo (à pied)</option>
      <option value="velo">Itinéraire à vélo</option>
    </select>
    <input class="l-variante input input-xs w-full" type="text" maxlength="100" placeholder="Nom de la variante (optionnel)">
    <textarea class="l-description textarea textarea-xs w-full" rows="2" placeholder="Description"></textarea>
    <div class="flex gap-1">
      <input class="l-km input input-xs w-16" type="number" step="0.1" min="0" placeholder="km" aria-label="Distance (km)">
      <input class="l-dplus input input-xs w-16" type="number" min="0" placeholder="D+" aria-label="D+ (m)">
      <input class="l-dmoins input input-xs w-16" type="number" min="0" placeholder="D-" aria-label="D- (m)">
    </div>
    <label class="flex flex-col gap-1"><span class="l-gpx-label">Trace GPX (optionnel)</span>
      <input class="l-gpx file-input file-input-xs w-full" type="file" accept=".gpx"></label>
    <button type="button" class="l-envoyer btn btn-sm btn-primary w-full">Créer l'accès</button>`;
  const q = (c) => root.querySelector(`.${c}`);
  q("l-type").addEventListener("change", () => {
    q("l-gpx-label").textContent = q("l-type").value === "velo" ? "Trace GPX (obligatoire), km, D+ et D- obligatoires" : "Trace GPX (optionnel)";
  });
  q("l-envoyer").addEventListener("click", async () => {
    const type = q("l-type").value;
    const gpx = q("l-gpx").files[0];
    if (type === "velo" && (!q("l-km").value || !q("l-dplus").value || !q("l-dmoins").value || !gpx)) {
      alert("Accès à vélo : la distance, le D+, le D- et la trace GPX sont obligatoires.");
      return;
    }
    const fields = {
      arret_id: arret.id, falaise_id: falaise.id, action: "link", type, nom_prenom: contrib.nom, email: contrib.email,
      variante: q("l-variante").value.trim(), description: q("l-description").value.trim(),
      km: q("l-km").value, dplus: q("l-dplus").value, dmoins: q("l-dmoins").value,
    };
    let init;
    if (gpx) {
      const fd = new FormData();
      Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
      fd.append("gpx_file", gpx);
      init = { method: "POST", body: fd };
    } else {
      init = { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(fields) };
    }
    const btn = q("l-envoyer");
    btn.disabled = true;
    try {
      const res = await fetch("/api/link_arret_falaise.php", init);
      const result = await res.json().catch(() => ({}));
      if (!res.ok || !result.success) throw new Error(result.error || res.status);
      arret.nb_acces++;
      map.closePopup();
      renderList();
      renderMap();
    } catch (e) {
      alert("Erreur : " + e.message);
    } finally {
      btn.disabled = false;
    }
  });
  return root;
}

function renderFalaises() {
  falaiseLayer.clearLayers();
  falaiseMarkers.length = 0;
  if (!$("layer-falaises").checked) return;
  falaises.forEach((f) => {
    const m = L.marker([f.lat, f.lng], { icon: falaiseIcon(), zIndexOffset: -500 }).addTo(falaiseLayer);
    m.bindTooltip(esc(f.nom), { direction: "right", className: "p-px" });
    m.bindPopup(
      `<b>${esc(f.nom)}</b><br><span class="text-xs opacity-70">Falaise #${f.id}</span>` +
        `<div class="flex gap-2 mt-2">${iconBtn("link", "Lier à un arrêt", "btn-outline", `data-link-start="falaise:${f.id}"`)}</div>`,
    );
    kindOf.set(m, { kind: "falaise", item: f });
    falaiseMarkers.push(m);
  });
}
let lignesPromise = null;
const arretMarkers = new Map(); // arret_id -> marker
const ligneLines = new Map(); // ligne_id -> polyline

function ensureMap() {
  if (map) return;
  ({ map } = createAjoutMap("admin-map"));
  arretLayer = L.layerGroup().addTo(map);
  ligneLayer = L.layerGroup().addTo(map);
  // Les cercles d'arrêts sont des tracés vectoriels : sans pane dédié, les lignes (ajoutées après) passent dessus
  map.createPane("arretsCercles").style.zIndex = 450; // au-dessus des lignes (400), sous les icônes (600)
  falaiseLayer = L.layerGroup().addTo(map);
  map.on("popupopen", onPopupOpenWhileLinking);
  map.on("moveend", updateLabels);
  document.addEventListener("keydown", (e) => e.key === "Escape" && linkSource && cancelLink());
  renderFalaises();
  let wasDot = dotMode();
  map.on("zoomend", () => {
    if (dotMode() === wasDot) return;
    wasDot = dotMode();
    falaiseMarkers.forEach((m) => m.setIcon(falaiseIcon()));
  });
  const pts = arrets.filter((a) => !a.deleted).map((a) => [a.lat, a.lng]);
  if (savedMap) map.setView(savedMap.center, savedMap.zoom);
  else if (pts.length) map.fitBounds(pts, { padding: [20, 20] });
  map.on("moveend", writeUrl);
  lignesPromise = loadLignes();
}

async function loadLignes() {
  $("map-status").textContent = "Chargement des lignes…";
  try {
    const r = await fetch("/api/fetch_lignes.php?geometry=1");
    lignesGeo = (await r.json()).lignes;
    $("map-status").textContent = "";
    renderMap();
  } catch {
    $("map-status").textContent = "Impossible de charger les lignes.";
  }
}

function renderMap() {
  if (!map) return;
  arretLayer.clearLayers();
  ligneLayer.clearLayers();
  arretMarkers.clear();
  ligneLines.clear();
  if ($("layer-arrets").checked) {
    arrets.filter(arretVisible).forEach((a) => {
      // Arrêt sans accès à une falaise : petit cercle noir à bord blanc (comme carte.php) ; sinon icône du type
      const marker = (a.nb_acces > 0
        ? L.marker([a.lat, a.lng], { icon: arretDivIcon(a.types, 20) })
        : L.circleMarker([a.lat, a.lng], { pane: "arretsCercles", radius: 3.5, color: "#fff", weight: 1.5, fillColor: arretCercleColor(a), fillOpacity: 1 }))
        .bindPopup(
          `<b>${esc(a.nom)}</b><br><span class="text-xs">${esc(a.commune ?? "")} · #${a.id} · ${
            a.types.map((t) => TYPE_LABELS[t] ?? t).join(", ") || arretTypeDominant([])
          }</span><br><div class="flex gap-2 mt-2">${iconLink("pencil", "Modifier l'arrêt", "btn-primary", editArretUrl(a.id))}` +
            (a.deleted
              ? ""
              : iconBtn("move", "Déplacer", "btn-outline", `data-move-arret="${a.id}"`) +
                mergeButtons(a) +
                iconBtn("link", "Lier à une falaise", "btn-outline", `data-link-start="arret:${a.id}"`) +
                iconBtn("trash", "Supprimer", "btn-error btn-outline", `data-delete-arret="${a.id}"`)) +
            "</div>",
        )
        .addTo(arretLayer);
      arretMarkers.set(a.id, marker);
      if (!a.deleted) kindOf.set(marker, { kind: "arret", item: a });
    });
  }
  if ($("layer-lignes").checked && lignesGeo) {
    lignesGeo.filter(ligneVisible).forEach((l) => {
      const latlngs = (l.geometry?.coordinates ?? []).map((line) => line.map(([lng, lat]) => [lat, lng]));
      if (!latlngs.length) return;
      const line = L.polyline(latlngs, { color: ligneColor(l), weight: 4, opacity: 0.85 })
        .bindPopup(
          `<b>${esc(ligneLabel(l))}</b><br><span class="text-xs">${esc(l.operateur ?? "")} · #${l.id}</span><br>` +
            `<div class="mt-2">${iconLink("pencil", "Modifier la ligne", "btn-primary", editLigneUrl(l.id))}</div>`,
        )
        .addTo(ligneLayer);
      ligneLines.set(l.id, line);
    });
  }
  updateLabels();
}

// Noms des arrêts, à droite du point (comme carte.php) : gares TER/TGV dès le zoom 13, les autres dès 16 ; seulement
// les arrêts dans la vue, pour ne pas poser des milliers d'infobulles permanentes.
const LABEL_ZOOM = 16;
const LABEL_ZOOM_GARE = 13;
function updateLabels() {
  if (!map) return;
  const z = map.getZoom();
  const bounds = map.getBounds();
  for (const m of arretMarkers.values()) {
    const a = kindOf.get(m)?.item;
    const gare = a?.types.some((t) => t === "ter" || t === "tgv");
    const want = !!a && z >= (gare ? LABEL_ZOOM_GARE : LABEL_ZOOM) && bounds.contains(m.getLatLng());
    const has = !!m.getTooltip();
    if (want && !has) {
      m.bindTooltip(esc(a.nom), {
        permanent: true, direction: "right", offset: [a.nb_acces > 0 ? 2 : 6, 0], className: "vg-arret-label",
      });
    } else if (!want && has) m.unbindTooltip();
  }
}

// --- Repositionnement : « Déplacer » rend le marqueur déplaçable ; le lâcher demande confirmation ---
function startMove(id) {
  const a = arrets.find((x) => x.id === id);
  let marker = arretMarkers.get(id);
  if (!a || !marker) return;
  map.closePopup();
  const from = L.latLng(a.lat, a.lng);
  if (!marker.dragging) {
    // Cercle (arrêt sans accès) : non déplaçable, remplacé le temps du geste par un marqueur ; renderMap() le rétablit
    arretLayer.removeLayer(marker);
    marker = L.marker(from, { icon: arretDivIcon(a.types, 20) }).addTo(arretLayer);
    arretMarkers.set(id, marker);
  }
  marker.dragging.enable();
  $("map-status").textContent = `Déplacez « ${a.nom} » à sa nouvelle position.`;
  marker.once("dragend", async () => {
    marker.dragging.disable();
    $("map-status").textContent = "";
    const to = marker.getLatLng();
    if (!confirm(`Déplacer « ${a.nom} » (#${id}) de ${Math.round(from.distanceTo(to))} m ?\nSeule sa position change.`)) {
      marker.setLatLng(from);
      renderMap();
      return;
    }
    try {
      const r = await fetch("/api/move_arret.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ arret_id: id, lat: to.lat, lng: to.lng }),
      });
      if (!r.ok) throw new Error((await r.json().catch(() => ({}))).error || r.statusText);
      a.lat = to.lat;
      a.lng = to.lng;
    } catch (e) {
      marker.setLatLng(from);
      alert(`Déplacement impossible : ${e.message}`);
    }
    renderMap(); // rétablit popup et rendu (cercle ou icône) du marqueur
  });
}

// --- « Voir sur la carte » depuis la liste ---
async function showOnMap(kind, id) {
  $("layer-arrets").checked = true;
  $("layer-lignes").checked = true;
  setView("carte");
  if (kind === "arret") {
    const a = arrets.find((x) => x.id === id);
    if (!a) return;
    map.setView([a.lat, a.lng], 16);
    arretMarkers.get(id)?.openPopup();
  } else {
    await lignesPromise;
    const line = ligneLines.get(id);
    if (!line) return alert("Cette ligne n'a pas de tracé à afficher.");
    map.fitBounds(line.getBounds(), { padding: [30, 30] });
    line.openPopup(line.getBounds().getCenter());
  }
}

// --- Suppression ---------------------------------------------------------
async function deleteArret(id) {
  const a = arrets.find((x) => x.id === id);
  if (!a || !confirm(`Supprimer l'arrêt « ${a.nom} » (#${id}) ?\nIl sera masqué du site (suppression logique).`)) return;
  try {
    const r = await fetch("/api/delete_arret.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
      body: JSON.stringify({ arret_id: id }),
    });
    if (!r.ok) throw new Error((await r.json().catch(() => ({}))).error || r.statusText);
    a.deleted = true;
    map?.closePopup();
    refresh();
  } catch (e) {
    alert(`Suppression impossible : ${e.message}`);
  }
}
document.addEventListener("click", (e) => {
  const b = e.target.closest("[data-delete-arret]");
  if (b) deleteArret(Number(b.dataset.deleteArret));
  const mv = e.target.closest("[data-move-arret]");
  if (mv) startMove(Number(mv.dataset.moveArret));
  const ls = e.target.closest("[data-link-start]");
  if (ls) startLink(...ls.dataset.linkStart.split(":").map((v, i) => (i ? Number(v) : v)));
  if (e.target.closest("[data-link-cancel]")) cancelLink();
  const sa = e.target.closest("[data-show-arret]");
  if (sa) showOnMap("arret", Number(sa.dataset.showArret));
  const sl = e.target.closest("[data-show-ligne]");
  if (sl) showOnMap("ligne", Number(sl.dataset.showLigne));
  const from = e.target.closest("[data-merge-from]");
  const into = e.target.closest("[data-merge-into]");
  if (from) { mergeSource = Number(from.dataset.mergeFrom); refresh(); }
  else if (into) mergeArrets(mergeSource, Number(into.dataset.mergeInto));
  else if (e.target.closest("[data-merge-cancel]")) { mergeSource = null; refresh(); }
});

// --- Câblage -------------------------------------------------------------
function refresh() {
  readFilters();
  writeUrl();
  renderMergeBanner();
  renderList();
  renderMap();
}

function setView(view) {
  state.view = view;
  writeUrl();
  document.querySelectorAll("[data-view]").forEach((b) => b.classList.toggle("tab-active", b.dataset.view === view));
  $("view-liste").classList.toggle("hidden", view !== "liste");
  $("view-carte").classList.toggle("hidden", view !== "carte");
  $("view-carte").classList.toggle("flex", view === "carte");
  if (view === "carte") {
    ensureMap();
    map.invalidateSize();
    renderMap();
  }
}

let timer;
$("search").addEventListener("input", () => {
  clearTimeout(timer);
  timer = setTimeout(refresh, 150);
});
document.querySelectorAll("#type-filters input, #other-filters input").forEach((c) => c.addEventListener("change", refresh));
$("layer-arrets").addEventListener("change", renderMap);
$("layer-lignes").addEventListener("change", renderMap);
$("layer-falaises").addEventListener("change", () => map && renderFalaises());
document.querySelectorAll("[data-view]").forEach((b) => b.addEventListener("click", () => setView(b.dataset.view)));

const initialView = readUrl();
refresh();
if (initialView === "carte") setView("carte");
