/**
 * Admin « Arrêts et lignes » (admin/arrets_lignes.php) : liste filtrable + carte.
 * Données initiales injectées par PHP dans #page-data ; la géométrie des lignes
 * est chargée à la première ouverture de la carte (/api/fetch_lignes.php?geometry=1).
 */
import { createAjoutMap } from "/js/components/map/ajout-map.js";
import { arretDivIcon, arretTypeDominant } from "/js/components/map/arret-icon.js";
import { ligneColor } from "/js/components/map/ligne-view.js";

const { arrets, lignes, token } = JSON.parse(document.getElementById("page-data").textContent);
const $ = (id) => document.getElementById(id);

const TYPE_LABELS = { ter: "TER", tgv: "TGV", bus: "Bus", tram: "Tram", telepherique: "Téléph." };
const esc = (s) =>
  String(s ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;");
const norm = (s) => String(s ?? "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

// Les liens des popups Leaflet héritent de la couleur bleue de `a` : on force la couleur du bouton.
const BTN_STYLE = "color:var(--color-primary-content,#fff)";
const editArretUrl = (id) => `/ajout/ajout_arret.php?admin=${encodeURIComponent(token)}&arret_id=${id}`;
const editLigneUrl = (id) => `/ajout/ajout_ligne.php?admin=${encodeURIComponent(token)}&ligne_id=${id}`;

// --- Filtres -------------------------------------------------------------
const state = { q: "", types: new Set(), showDeleted: false, view: "liste" };
const readFilters = () => {
  state.q = norm($("search").value.trim());
  state.types = new Set(
    [...document.querySelectorAll("#type-filters [data-type]")].filter((c) => c.checked).map((c) => c.dataset.type),
  );
  state.showDeleted = $("show-deleted").checked;
};

// Un arrêt est visible si l'un de ses types est coché (un arrêt TER + bus reste visible
// avec « Bus » seul coché) ; un arrêt sans aucun type n'est jamais masqué par ce filtre.
const arretVisible = (a) =>
  (state.showDeleted || !a.deleted) &&
  (a.types.length === 0 || a.types.some((t) => state.types.has(t))) &&
  (!state.q || norm(`${a.nom} ${a.commune ?? ""} ${a.id}`).includes(state.q));

const ligneLabel = (l) => (l.numero ? `${l.numero} — ` : "") + l.nom;
const ligneVisible = (l) =>
  !state.q || norm(`${l.nom} ${l.numero ?? ""} ${l.operateur ?? ""} ${l.id}`).includes(state.q);

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
        <td class="text-right">${a.nb_acces}</td>
        <td class="text-right whitespace-nowrap"><a class="btn btn-xs btn-primary text-primary-content" style="${BTN_STYLE}" href="${editArretUrl(a.id)}">Modifier</a>
          ${a.deleted ? "" : `<button type="button" class="btn btn-xs btn-error btn-outline" data-delete-arret="${a.id}">Supprimer</button>`}</td>
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
        <td class="text-right"><a class="btn btn-xs btn-primary text-primary-content" href="${editLigneUrl(l.id)}">Modifier</a></td>
      </tr>`).join("") || '<tr><td colspan="4" class="text-center text-base-content/60">Aucune ligne</td></tr>';
}

// --- Carte ---------------------------------------------------------------
let map = null;
let arretLayer = null;
let ligneLayer = null;
let lignesGeo = null; // lignes avec géométrie, chargées à la demande

function ensureMap() {
  if (map) return;
  ({ map } = createAjoutMap("admin-map"));
  arretLayer = L.layerGroup().addTo(map);
  ligneLayer = L.layerGroup().addTo(map);
  const pts = arrets.filter((a) => !a.deleted).map((a) => [a.lat, a.lng]);
  if (pts.length) map.fitBounds(pts, { padding: [20, 20] });
  loadLignes();
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
  if ($("layer-arrets").checked) {
    arrets.filter(arretVisible).forEach((a) => {
      L.marker([a.lat, a.lng], { icon: arretDivIcon(a.types, 20) })
        .bindPopup(
          `<b>${esc(a.nom)}</b><br><span class="text-xs">${esc(a.commune ?? "")} · #${a.id} · ${
            a.types.map((t) => TYPE_LABELS[t] ?? t).join(", ") || arretTypeDominant([])
          }</span><br><div class="flex gap-2 mt-2"><a class="btn btn-xs btn-primary" style="${BTN_STYLE}" href="${editArretUrl(a.id)}">Modifier l'arrêt</a>` +
            (a.deleted ? "" : `<button type="button" class="btn btn-xs btn-error btn-outline" data-delete-arret="${a.id}">Supprimer</button>`) +
            "</div>",
        )
        .addTo(arretLayer);
    });
  }
  if ($("layer-lignes").checked && lignesGeo) {
    lignesGeo.filter(ligneVisible).forEach((l) => {
      const latlngs = (l.geometry?.coordinates ?? []).map((line) => line.map(([lng, lat]) => [lat, lng]));
      if (!latlngs.length) return;
      L.polyline(latlngs, { color: ligneColor(l), weight: 4, opacity: 0.85 })
        .bindPopup(
          `<b>${esc(ligneLabel(l))}</b><br><span class="text-xs">${esc(l.operateur ?? "")} · #${l.id}</span><br>` +
            `<a class="btn btn-xs btn-primary mt-2" style="${BTN_STYLE}" href="${editLigneUrl(l.id)}">Modifier la ligne</a>`,
        )
        .addTo(ligneLayer);
    });
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
});

// --- Câblage -------------------------------------------------------------
function refresh() {
  readFilters();
  renderList();
  renderMap();
}

function setView(view) {
  state.view = view;
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
document.querySelectorAll("#type-filters input").forEach((c) => c.addEventListener("change", refresh));
$("layer-arrets").addEventListener("change", renderMap);
$("layer-lignes").addEventListener("change", renderMap);
document.querySelectorAll("[data-view]").forEach((b) => b.addEventListener("click", () => setView(b.dataset.view)));

refresh();
