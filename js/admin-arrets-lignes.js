/**
 * Admin « Arrêts et lignes » (admin/arrets_lignes.php) : liste filtrable + carte.
 * Données initiales injectées par PHP dans #page-data ; la géométrie des lignes
 * est chargée à la première ouverture de la carte (/api/fetch_lignes.php?geometry=1).
 */
import {
  arretIconHtml,
  arretTypeDominant,
} from "/js/components/map/arret-icon.js";
import { ligneColor } from "/js/components/map/ligne-view.js";
import { enveloppe, fusionnable } from "/js/components/map/arrets-groupes.js";
import { esc, norm, ico, toolbar, iconBtn, hideTip } from "/js/admin-ui.js";

const { arrets, lignes, falaises, contrib, token } = JSON.parse(
  document.getElementById("page-data").textContent,
);
const $ = (id) => document.getElementById(id);

const TYPE_LABELS = {
  ter: "TER",
  tgv: "TGV",
  bus: "Bus",
  tram: "Tram",
  metro: "Métro",
  telepherique: "Téléph.",
};

// --- État dans l'URL (?q=…&types=bus&statut=lies&view=carte) ---
// Seules les valeurs différentes des défauts sont écrites ; les autres paramètres sont préservés.
// Filtres à choix unique (boutons « Tous / option » de arrets_lignes.php) : clé de filtre → paramètre d'URL.
// « Tous » (valeur "") s'écrit `all` dans l'URL, pour se distinguer d'un paramètre absent (= défaut du filtre).
// Statut : Tous = arrêts non supprimés ; Liés / Non liés = non supprimés, avec / sans falaise reliée ; Supprimés.
const FILTER_PARAM = { type: "types", statut: "statut" };
const filterGroups = () => [...document.querySelectorAll("[data-filter-group]")];
const filterDefault = (g) => g.dataset.default;
const filterValue = (g) => g.querySelector("[data-filter].btn-primary")?.dataset.value ?? filterDefault(g);
function setFilter(g, value) {
  const valid = [...g.querySelectorAll("[data-filter]")].some((b) => b.dataset.value === value);
  const v = valid ? value : filterDefault(g);
  g.querySelectorAll("[data-filter]").forEach((b) => {
    const on = b.dataset.value === v;
    b.classList.toggle("btn-primary", on);
    b.classList.toggle("text-primary-content", on);
    b.classList.toggle("btn-outline", !on);
    b.setAttribute("aria-pressed", String(on));
  });
}

// Position de la carte : ?map=zoom/lat/lng
let savedMap = null;
function parseMapParam(v) {
  const [z, lat, lng] = String(v ?? "")
    .split("/")
    .map(Number);
  return [z, lat, lng].every(Number.isFinite)
    ? { zoom: z, center: [lat, lng] }
    : null;
}

function readUrl() {
  const p = new URLSearchParams(location.search);
  savedMap = parseMapParam(p.get("map"));
  $("search").value = p.get("q") ?? "";
  // Ancien lien : falaises=1 (reliés seulement).
  if (p.get("falaises") === "1" && !p.has("statut")) p.set("statut", "lies");
  for (const g of filterGroups()) {
    const raw = p.get(FILTER_PARAM[g.dataset.filterGroup]);
    setFilter(g, raw === null ? filterDefault(g) : raw === "all" ? "" : raw);
  }
  return p.get("view") === "carte" ? "carte" : "liste";
}

function writeUrl() {
  const p = new URLSearchParams(location.search);
  const setOrDel = (k, v) => (v ? p.set(k, v) : p.delete(k));
  setOrDel("q", $("search").value.trim());
  for (const g of filterGroups()) {
    const v = filterValue(g);
    const param = FILTER_PARAM[g.dataset.filterGroup];
    v === filterDefault(g) ? p.delete(param) : p.set(param, v || "all");
  }
  p.delete("deleted");
  p.delete("falaises");
  setOrDel("view", state.view === "carte" ? "carte" : "");
  if (mapReady) {
    const c = map.getCenter();
    p.set(
      "map",
      `${Math.round(map.getZoom() * 100) / 100}/${c.lat.toFixed(5)}/${c.lng.toFixed(5)}`,
    );
  }
  const qs = p.toString();
  history.replaceState(
    null,
    "",
    location.pathname + (qs ? `?${qs}` : "") + location.hash,
  );
}

// --- Filtres -------------------------------------------------------------
const state = {
  q: "",
  type: "local",
  statut: "",
  view: "liste",
};
const readFilters = () => {
  state.q = norm($("search").value.trim());
  for (const g of filterGroups()) state[g.dataset.filterGroup] = filterValue(g);
};

const LOCAL_TYPES = ["bus", "tram", "metro", "telepherique"];
const typeVisible = (a) =>
  state.type === ""
    ? true
    : state.type === "local"
      ? a.types.length === 0 || a.types.some((t) => LOCAL_TYPES.includes(t))
      : a.types.includes(state.type);

// Un arrêt est visible si l'un de ses types correspond au filtre (un arrêt TER + bus reste visible
// avec « Bus » ou « Hors trains ») ; un arrêt sans aucun type n'est masqué que par un type précis.
const arretVisible = (a) =>
  a.deleted === (state.statut === "supprimes") &&
  (state.statut !== "lies" || a.falaises.length > 0) &&
  (state.statut !== "non_lies" || a.falaises.length === 0) &&
  typeVisible(a) &&
  (!state.q ||
    norm(
      `${a.nom} ${a.commune ?? ""} ${a.id} ${a.falaises.join(" ")}`,
    ).includes(state.q));

const ligneLabel = (l) => (l.numero ? `${l.numero} — ` : "") + l.nom;
// Actions d'une ligne, identiques en liste et sur la carte
const ligneActions = (l, { liste = false } = {}) =>
  toolbar(
  (liste ? iconBtn("map-pin", "Voir sur la carte", "btn-outline", `data-show-ligne="${l.id}"`) : "") +
  iconBtn("pencil", "Modifier", "btn-outline", `data-edit-ligne="${l.id}"`) +
  iconBtn("file-upload", "Import GTFS (tracé et arrêts)", "btn-outline", `data-gtfs-ligne="${l.id}"`) +
  iconBtn("trash", "Supprimer la ligne", "btn-error btn-outline", `data-delete-ligne="${l.id}"`));
const ligneVisible = (l) =>
  !state.q ||
  norm(`${l.nom} ${l.numero ?? ""} ${l.operateur ?? ""} ${l.id}`).includes(
    state.q,
  );

// --- Fusion : 1er clic « Fusionner » sur le 1er arrêt, 2e clic « Regrouper avec celui-ci » sur l'autre ---
// Rien n'est supprimé : les deux arrêts sont regroupés sous un parent (créé, ou déjà existant). Le parent créé
// reprend le nom de l'arrêt choisi en 2e. Règle (cf. fusionnable) : pas d'enfant ; les modes peuvent différer (le parent cumule les types).
let mergeSource = null;
const mergeButtons = (a) => {
  if (a.deleted || a.parent_id != null) return ""; // un enfant se détache, il ne se fusionne pas
  if (mergeSource === null)
    return iconBtn(
      "merge",
      "Fusionner avec un autre arrêt",
      "btn-outline",
      `data-merge-from="${a.id}"`,
    );
  if (a.id === mergeSource) return "";
  const src = arrets.find((x) => x.id === mergeSource);
  const r = fusionnable(src, a);
  return r.ok
    ? iconBtn("merge", "Regrouper avec celui-ci", "btn-warning", `data-merge-into="${a.id}"`)
    : iconBtn("merge", esc(r.raison), "btn-disabled", "disabled");
};

// Détacher un enfant (s'il n'est pas le dernier), défusionner un parent, repasser un parent en position automatique
const groupeButtons = (a) => {
  if (a.deleted) return "";
  const parent =
    a.parent_id != null ? arrets.find((x) => x.id === a.parent_id) : null;
  if (parent)
    return parent.nb_enfants > 1
      ? iconBtn("unlink", "Détacher du parent", "btn-outline", `data-detach-arret="${a.id}"`)
      : "";
  if (a.nb_enfants > 0)
    return (
      iconBtn("unmerge", "Défusionner", "btn-outline", `data-unmerge-arret="${a.id}"`) +
      (a.position_manuelle
        ? `<button type="button" class="btn btn-xs btn-outline" data-auto-position="${a.id}" data-tip="Replacer au centre de ses enfants">Position auto</button>`
        : "")
    );
  return "";
};
// Actions d'un arrêt, identiques en liste et sur la carte (`liste` : ajoute « Voir sur la carte », inutile sur la carte).
// Déplacer et Lier à une falaise sont des gestes de carte : depuis la liste, ils basculent d'abord sur la carte.
// En mode fusion, seul le bouton de regroupement est proposé.
const arretActions = (a, opts) => toolbar(arretButtons(a, opts));
const arretButtons = (a, { liste = false } = {}) => {
  if (mergeSource !== null) return a.deleted ? "" : mergeButtons(a);
  const show = liste
    ? iconBtn("map-pin", "Voir sur la carte", "btn-outline", `data-show-arret="${a.id}"`)
    : "";
  const edit = iconBtn("pencil", "Modifier", "btn-outline", `data-edit-arret="${a.id}"`);
  if (a.deleted) return show + edit;
  return (
    show +
    edit +
    iconBtn("move", "Déplacer", "btn-outline", `data-move-arret="${a.id}"`) +
    iconBtn("link", "Lier à une falaise", "btn-outline", `data-link-start="arret:${a.id}"`) +
    mergeButtons(a) +
    groupeButtons(a) +
    (supprimable(a) ? iconBtn("trash", "Supprimer", "btn-error btn-outline", `data-delete-arret="${a.id}"`) : "")
  );
};
const actionsHtml = (a) =>
  `<div class="flex flex-nowrap justify-end">${arretActions(a, { liste: true })}</div>`;
// Parents dépliés : conservés d'un rendu à l'autre (le mode fusion re-rend la liste)
const openParents = new Set();
// Indicateur sous le parent : la ligne du parent ne change jamais, les enfants sont des lignes en dessous
const enfantsToggle = (a, enfants) =>
  a.nb_enfants > 0 && enfants.length
    ? `<div class="text-xs text-base-content/60 select-none mt-1" data-enfants-toggle="${a.id}">${openParents.has(a.id) ? "▾" : "▸"} Voir les ${enfants.length} arrêts regroupés</div>`
    : "";
// Lignes enfants (compactes), masquées tant que le parent n'est pas déplié
const enfantsRows = (a, enfants) =>
  a.nb_enfants > 0
    ? enfants
        .map(
          (e) => `<tr data-enfant-de="${a.id}" class="bg-base-200/50"${openParents.has(a.id) ? "" : " hidden"}>
        <td colspan="5" class="py-1"><div class="flex items-center gap-2 text-xs" style="margin-left:1.5rem;padding-left:0.75rem;border-left:2px solid rgba(128,128,128,.35)">
          <span class="grow truncate">${esc(e.nom)} <span class="text-base-content/50">#${e.id}</span> ${e.types.map((t) => `<span class="badge badge-ghost badge-xs">${TYPE_LABELS[t] ?? esc(t)}</span>`).join(" ")}</span>
          <span class="flex gap-1 shrink-0">${arretActions(e, { liste: true })}</span>
        </div></td></tr>`,
        )
        .join("")
    : "";
// Suppression : un parent (et ses accès) se supprime en cascade après confirmation ; pas le dernier enfant d'un parent
const supprimable = (a) => {
  if (a.deleted) return false;
  const parent =
    a.parent_id != null ? arrets.find((x) => x.id === a.parent_id) : null;
  return !parent || parent.nb_enfants > 1;
};
const groupeBadges = (a) => {
  const parent =
    a.parent_id != null ? arrets.find((x) => x.id === a.parent_id) : null;
  return (
    (a.nb_enfants > 0
      ? ` <span class="badge badge-info badge-xs">parent · ${a.nb_enfants} arrêts</span>`
      : "") +
    (parent
      ? ` <span class="badge badge-ghost badge-xs">enfant de ${esc(parent.nom)} #${parent.id}</span>`
      : "") +
    (a.position_manuelle
      ? ' <span class="badge badge-ghost badge-xs" title="Position fixée à la main : jamais recalculée ni mise à jour par un import GTFS">position manuelle</span>'
      : "")
  );
};

function renderMergeBanner() {
  const b = $("merge-banner");
  const src = arrets.find((x) => x.id === mergeSource);
  b.classList.toggle("hidden", !src);
  b.classList.toggle("flex", !!src);
  if (src)
    b.innerHTML = `<span>Fusion : « <b>${esc(src.nom)}</b> » (#${src.id}) sera regroupé avec l'arrêt choisi (liste ou carte) sous un arrêt parent ; rien n'est supprimé.</span>
      <button type="button" class="btn btn-xs" data-merge-cancel>Annuler</button>`;
}

/** Appel d'un endpoint admin (Bearer) ; lève une Error portant le message du serveur. */
async function callAdmin(url, body) {
  const r = await fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(body),
  });
  const data = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(data.error || r.statusText);
  return data;
}

/** Appel d'un endpoint admin de regroupement puis rechargement : compteurs, parents et types à jour. */
async function postAdmin(url, body, echec) {
  try {
    await callAdmin(url, body);
    location.reload();
  } catch (e) {
    alert(`${echec} : ${e.message}`);
  }
}

function mergeArrets(sourceId, cibleId) {
  const s = arrets.find((x) => x.id === sourceId);
  const c = arrets.find((x) => x.id === cibleId);
  if (!s || !c) return;
  if (
    !confirm(`Regrouper « ${s.nom} » (#${s.id}) et « ${c.nom} » (#${c.id}) sous un arrêt parent ?
Aucun arrêt n'est supprimé ; accès aux falaises et trajets train passent au parent.`)
  )
    return;
  postAdmin(
    "/api/merge_arrets.php",
    { source_id: sourceId, cible_id: cibleId },
    "Fusion impossible",
  );
}

function detachArret(id) {
  const a = arrets.find((x) => x.id === id);
  if (!a || !confirm(`Détacher « ${a.nom} » (#${id}) de son parent ?`)) return;
  postAdmin(
    "/api/detach_arret.php",
    { arret_id: id },
    "Détachement impossible",
  );
}

function unmergeArret(id) {
  const p = arrets.find((x) => x.id === id);
  if (
    !p ||
    !confirm(
      `Défusionner « ${p.nom} » (#${id}) ? Ses arrêts redeviennent indépendants et le parent est supprimé.`,
    )
  )
    return;
  const body = { parent_id: id };
  const enfants = arrets.filter((x) => x.parent_id === id && !x.deleted);
  if (enfants.length > 1) {
    // Les accès aux falaises et trajets train du parent doivent revenir à l'un des enfants
    const rep = prompt(
      `À quel arrêt reviennent les accès aux falaises et trajets train du parent ?
${enfants.map((e, i) => `${i + 1}) ${e.nom} (#${e.id})`).join("\n")}`,
      "1",
    );
    if (rep === null) return;
    const h = enfants[Number(rep) - 1];
    if (!h) return alert("Choix invalide.");
    body.heritier_id = h.id;
  }
  postAdmin("/api/unmerge_arret.php", body, "Défusion impossible");
}

const autoPosition = (id) =>
  postAdmin(
    "/api/move_arret.php",
    { arret_id: id, auto: true },
    "Repositionnement impossible",
  );

// --- Dialogs de modification : arrêt (nom, modalités) et ligne (infos de base) ---------------------
// Un seul gabarit (formulaire + message d'erreur + validation) pour les deux ; les dialogs sont dans la page PHP.
function setupEditDialog({ dialog, form, error, fill, submit }) {
  const dlg = $(dialog);
  const err = $(error);
  const showError = (m) => {
    err.textContent = m ?? "";
    err.classList.toggle("hidden", !m);
  };
  dlg.querySelector("[data-dialog-close]").addEventListener("click", () => dlg.close());
  $(form).addEventListener("submit", async (e) => {
    e.preventDefault();
    const btn = e.submitter;
    btn.disabled = true;
    try {
      await submit();
      location.reload(); // compteurs, noms, types, lignes des arrêts et carte à jour
    } catch (x) {
      showError(x.message);
      btn.disabled = false;
    }
  });
  return (item) => {
    showError(null);
    $(form).querySelector("[type=submit]").disabled = false;
    fill(item);
    dlg.showModal();
  };
}

let editedArret = null;
const openArretDialog = setupEditDialog({
  dialog: "dlg-arret",
  form: "form-arret",
  error: "arret-error",
  fill(a) {
    editedArret = a;
    $("dlg-arret-id").textContent = `#${a.id}`;
    $("arret-nom").value = a.nom;
    const parent = a.nb_enfants > 0; // modalités d'un parent = celles de ses enfants
    $("arret-types-note").classList.toggle("hidden", !parent);
    document.querySelectorAll("#arret-types input").forEach((c) => {
      c.checked = a.types.includes(c.value);
      c.disabled = parent;
    });
  },
  async submit() {
    const types = [...document.querySelectorAll("#arret-types input:checked")].map((c) => c.value);
    if (!types.length) throw new Error("Cochez au moins une modalité.");
    await callAdmin("/api/update_arret.php", { arret_id: editedArret.id, nom: $("arret-nom").value, types });
  },
});

const LIGNE_FIELDS = ["nom", "numero", "couleur", "ref", "operateur", "operateur_url", "horaire_url", "gtfs_url"];
let editedLigne = null;
const openLigneDialog = setupEditDialog({
  dialog: "dlg-ligne",
  form: "form-ligne",
  error: "ligne-error",
  fill(l) {
    editedLigne = l;
    $("dlg-ligne-id").textContent = `#${l.id}`;
    LIGNE_FIELDS.forEach((f) => ($(`ligne-${f}`).value = l[f] ?? ""));
  },
  async submit() {
    await callAdmin("/api/update_ligne.php", {
      ligne_id: editedLigne.id,
      ...Object.fromEntries(LIGNE_FIELDS.map((f) => [f, $(`ligne-${f}`).value])),
    });
  },
});

// Import GTFS (mise à jour d'une ligne, ou création avec le « + » des lignes) : dialog Vue (AdminLigneGtfsImport.vue), qui prévient de sa fin
document.addEventListener("velogrimpe:gtfs-import-done", () => location.reload());

// --- Liste ---------------------------------------------------------------
const MAX_ROWS = 500; // évite de rendre des milliers de lignes d'un coup

function renderList() {
  hideTip(); // le bouton survolé va être remplacé
  const as = arrets.filter(arretVisible);
  $("count-arrets").textContent = as.length;
  // Un enfant dont le parent est dans la liste n'a pas de ligne propre : il est dans des lignes sous le parent
  const dansListe = new Set(as.map((a) => a.id));
  const enfantsDe = (p) =>
    arrets.filter((x) => x.parent_id === p.id && !x.deleted);
  const racines = as.filter(
    (a) => a.parent_id == null || !dansListe.has(a.parent_id),
  );
  $("tbody-arrets").innerHTML =
    racines
      .slice(0, MAX_ROWS)
      .map(
        (a) => `
      <tr class="${a.deleted ? "opacity-50" : ""}${a.nb_enfants > 0 ? " cursor-pointer" : ""}">
        <td><b>${esc(a.nom)}</b>${a.deleted ? ' <span class="badge badge-error badge-xs">supprimé</span>' : ""}${groupeBadges(a)}
          <div class="text-xs text-base-content/60">${esc(a.commune ?? "")} · #${a.id}</div>${enfantsToggle(a, enfantsDe(a))}</td>
        <td>${a.types.map((t) => `<span class="badge badge-ghost badge-xs mr-1">${TYPE_LABELS[t] ?? esc(t)}</span>`).join("")}</td>
        <td class="text-right">${a.nb_lignes}</td>
        <td class="text-xs">${a.falaises.map(esc).join(", ")}</td>
        <td>${actionsHtml(a)}</td>
      </tr>${enfantsRows(a, enfantsDe(a))}`,
      )
      .join("") +
      (racines.length > MAX_ROWS
        ? `<tr><td colspan="5" class="text-center text-sm text-base-content/60">${racines.length - MAX_ROWS} autres résultats : affinez la recherche.</td></tr>`
        : "") ||
    '<tr><td colspan="5" class="text-center text-base-content/60">Aucun arrêt</td></tr>';

  const ls = lignes.filter(ligneVisible);
  $("count-lignes").textContent = ls.length;
  $("tbody-lignes").innerHTML =
    ls
      .map(
        (l) => `
      <tr>
        <td><span class="inline-block w-3 h-3 rounded-full align-middle mr-1" style="background:${esc(ligneColor(l))}"></span>
          <b>${esc(ligneLabel(l))}</b> <span class="text-xs text-base-content/60">#${l.id}</span></td>
        <td>${esc(l.operateur ?? "")}</td>
        <td class="text-right">${l.nb_arrets}</td>
        <td class="text-right whitespace-nowrap">${ligneActions(l, { liste: true })}</td>
      </tr>`,
      )
      .join("") ||
    '<tr><td colspan="4" class="text-center text-base-content/60">Aucune ligne</td></tr>';
}

// --- Carte (MapLibre GL JS, bundle `map-maplibre` chargé par la page) ---------------------------
let map = null;
let mapReady = false;
let lignesGeo = null; // lignes avec géométrie, chargées à la demande
let lignesPromise = null;
const arretMarkers = new Map(); // arret_id -> marker DOM (arrêts reliés à une falaise)
const labelMarkers = new Map(); // arret_id -> marker DOM du nom
const ligneBounds = new Map(); // ligne_id -> [[w, s], [e, n]]
let popup = null;
let hoverPopup = null;

// MapLibre (tuiles 512 px) est ~1 niveau en dessous de Leaflet à rendu égal : seuils décalés d'autant.
const DOT_MAX_ZOOM = 11; // falaises : point jusqu'ici, puis icône
const GROUPE_ZOOM = 13; // enveloppe des arrêts regroupés (≈ zoom 14 en Leaflet)
const LABEL_ZOOM = 15; // noms des arrêts (hors gares)
const LABEL_ZOOM_GARE = 12; // noms des gares TER/TGV

const BASEMAPS = {
  Landscape: {
    url: "https://a.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    maxzoom: 19,
    attribution:
      '<a href="http://www.thunderforest.com/landscape/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
  },
  OpenCycleMap: {
    url: "https://a.tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    maxzoom: 19,
    attribution:
      '<a href="http://www.thunderforest.com/opencyclemap/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
  },
  IGNv2: {
    url: "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&FORMAT=image/png&TILEMATRIXSET=PM&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}",
    maxzoom: 19,
    attribution: "IGN-F/Geoportail",
  },
  Satellite: {
    url: "https://data.geopf.fr/wmts?&REQUEST=GetTile&SERVICE=WMTS&VERSION=1.0.0&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/jpeg&LAYER=ORTHOIMAGERY.ORTHOPHOTOS&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}",
    maxzoom: 18,
    attribution: "IGN-F/Geoportail",
  },
  Outdoors: {
    url: "https://a.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    maxzoom: 19,
    attribution:
      '<a href="http://www.thunderforest.com/outdoors/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
  },
  Transports: {
    url: "https://a.tile.thunderforest.com/transport/{z}/{x}/{y}.png?apikey=e6b144cfc47a48fd928dad578eb026a6",
    maxzoom: 19,
    attribution:
      '<a href="http://www.thunderforest.com/transport/" target="_blank">Thunderforest</a>, <a href="http://osm.org/copyright" target="_blank">OSM contributors</a>',
  },
};

const emptyFc = () => ({ type: "FeatureCollection", features: [] });
const setData = (id, features) =>
  map.getSource(id)?.setData({ type: "FeatureCollection", features });
const pointFeature = (props, item) => ({
  type: "Feature",
  properties: props,
  geometry: { type: "Point", coordinates: [item.lng, item.lat] },
});

// Couleur d'un cercle (arrêt sans accès falaise) : celle de sa ligne, noir si l'arrêt n'en a pas
const arretCercleColor = (a) => {
  const l = a.ligne_id ? lignes.find((x) => x.id === a.ligne_id) : null;
  return l ? ligneColor(l) : "#000";
};

function openPopup(lngLat, content, opts = {}) {
  popup?.remove();
  hoverPopup?.remove();
  popup = new maplibregl.Popup({
    maxWidth: "300px",
    focusAfterOpen: false,
    ...opts,
  }).setLngLat(lngLat);
  if (typeof content === "string") popup.setHTML(content);
  else popup.setDOMContent(content);
  popup.addTo(map);
  return popup;
}
const closePopup = () => popup?.remove();

// --- Liaison arrêt ↔ falaise (api/link_arret_falaise.php, accès à pied ou à vélo) ----
// 1. clic sur un arrêt / une falaise, 2. bouton « lier », 3. clic sur la falaise / l'arrêt à lier,
// 4. formulaire des informations (accès à pied ou à vélo).
let linkSource = null; // { kind: "arret" | "falaise", item }

async function startLink(kind, id) {
  if (state.view !== "carte") await showOnMap(kind, id); // depuis la liste : on bascule d'abord sur la carte
  const item = (kind === "arret" ? arrets : falaises).find((x) => x.id === id);
  if (!item) return;
  closePopup();
  linkSource = { kind, item };
  // La couche à désigner doit être visible
  const cible = kind === "arret" ? "layer-falaises" : "layer-arrets";
  if (!$(cible).checked) {
    $(cible).checked = true;
    kind === "arret" ? renderFalaises() : renderMap();
  }
  $("map-status").innerHTML =
    `Choisir avec quel${kind === "arret" ? "le falaise" : " arrêt"} lier « <b>${esc(item.nom)}</b> » : cliquez dessus sur la carte.
    <button type="button" class="btn btn-xs ml-2" data-link-cancel>Annuler</button>`;
}
function cancelLink() {
  linkSource = null;
  $("map-status").textContent = "";
}

/** Clic sur un arrêt / une falaise : en mode liaison sur l'autre type, ouvre le formulaire ; sinon le popup normal. */
function onItemClick(kind, item, popupHtml) {
  const lngLat = [item.lng, item.lat];
  if (linkSource && linkSource.kind !== kind) {
    const arret = kind === "arret" ? item : linkSource.item;
    const falaise = kind === "arret" ? linkSource.item : item;
    cancelLink();
    openPopup(lngLat, linkForm(arret, falaise), {
      maxWidth: "280px",
      offset: kind === "arret" ? 12 : 14,
    });
  } else {
    openPopup(lngLat, popupHtml, { offset: kind === "arret" ? 12 : 14 });
  }
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
    q("l-gpx-label").textContent =
      q("l-type").value === "velo"
        ? "Trace GPX (obligatoire), km, D+ et D- obligatoires"
        : "Trace GPX (optionnel)";
  });
  q("l-envoyer").addEventListener("click", async () => {
    const type = q("l-type").value;
    const gpx = q("l-gpx").files[0];
    if (
      type === "velo" &&
      (!q("l-km").value || !q("l-dplus").value || !q("l-dmoins").value || !gpx)
    ) {
      alert(
        "Accès à vélo : la distance, le D+, le D- et la trace GPX sont obligatoires.",
      );
      return;
    }
    const fields = {
      arret_id: arret.id,
      falaise_id: falaise.id,
      action: "link",
      type,
      nom_prenom: contrib.nom,
      email: contrib.email,
      variante: q("l-variante").value.trim(),
      description: q("l-description").value.trim(),
      km: q("l-km").value,
      dplus: q("l-dplus").value,
      dmoins: q("l-dmoins").value,
    };
    let init;
    if (gpx) {
      const fd = new FormData();
      Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
      fd.append("gpx_file", gpx);
      init = { method: "POST", body: fd };
    } else {
      init = {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(fields),
      };
    }
    const btn = q("l-envoyer");
    btn.disabled = true;
    try {
      const res = await fetch("/api/link_arret_falaise.php", init);
      const result = await res.json().catch(() => ({}));
      if (!res.ok || !result.success)
        throw new Error(result.error || res.status);
      arret.nb_acces++;
      closePopup();
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

// --- Contrôles ----------------------------------------------------------
function basemapControl() {
  return {
    onAdd() {
      const div = document.createElement("div");
      div.className = "vg-layer-switcher maplibregl-ctrl maplibregl-ctrl-group";
      const det = document.createElement("details");
      const sum = document.createElement("summary");
      sum.innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg><span class="vg-layer-switcher-label">Fond de carte</span>';
      sum.title = "Fond de carte";
      det.appendChild(sum);
      const body = document.createElement("div");
      Object.keys(BASEMAPS).forEach((name) => {
        const lab = document.createElement("label");
        const r = document.createElement("input");
        r.type = "radio";
        r.name = "vg-basemap";
        r.value = name;
        r.checked = name === "Landscape";
        r.addEventListener("change", () => {
          if (r.checked)
            map.getSource("basemap").setTiles([BASEMAPS[name].url]);
        });
        lab.appendChild(r);
        lab.appendChild(document.createTextNode(" " + name));
        body.appendChild(lab);
      });
      det.appendChild(body);
      div.appendChild(det);
      return div;
    },
    onRemove() {},
  };
}

/** Recherche d'un lieu (Nominatim) : recentre la carte, sans marqueur. */
function searchControl() {
  return {
    onAdd() {
      const div = document.createElement("div");
      div.className = "maplibregl-ctrl vg-search-ctl";
      div.innerHTML = `<input type="search" autocomplete="off" placeholder="Centrer sur…" aria-label="Centrer la carte sur un lieu" />
        <div class="vg-search-results" hidden></div>`;
      const input = div.querySelector("input");
      const results = div.querySelector(".vg-search-results");
      let debounce = null;
      let ctl = null;
      const hide = () => {
        results.hidden = true;
        results.innerHTML = "";
      };
      const go = (item) => {
        const bb = item.boundingbox?.map(parseFloat);
        if (bb?.length === 4)
          map.fitBounds(
            [
              [bb[2], bb[0]],
              [bb[3], bb[1]],
            ],
            { maxZoom: 13 },
          );
        else
          map.flyTo({
            center: [parseFloat(item.lon), parseFloat(item.lat)],
            zoom: 12,
          });
        input.value = "";
        hide();
      };
      async function search(query) {
        ctl?.abort();
        ctl = new AbortController();
        try {
          const r = await fetch(
            `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=6&countrycodes=fr&q=${encodeURIComponent(query)}`,
            { signal: ctl.signal, headers: { "Accept-Language": "fr" } },
          );
          const items = await r.json();
          results.innerHTML = "";
          if (!items.length)
            results.innerHTML =
              '<div class="px-2 py-1 opacity-60">Aucun résultat</div>';
          items.forEach((it) => {
            const b = document.createElement("button");
            b.type = "button";
            b.textContent = it.display_name;
            b.title = it.display_name;
            b.addEventListener("click", () => go(it));
            results.appendChild(b);
          });
          results.hidden = false;
        } catch (e) {
          if (e.name !== "AbortError") hide();
        }
      }
      input.addEventListener("input", () => {
        clearTimeout(debounce);
        const v = input.value.trim();
        if (v.length < 3) return hide();
        debounce = setTimeout(() => search(v), 600);
      });
      input.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          results.querySelector("button")?.click();
        }
        if (e.key === "Escape") {
          input.value = "";
          hide();
        }
      });
      return div;
    },
    onRemove() {},
  };
}

// --- Couches ------------------------------------------------------------
const HIT = (layer) => {
  map.on("mouseenter", layer, () => (map.getCanvas().style.cursor = "pointer"));
  map.on("mouseleave", layer, () => (map.getCanvas().style.cursor = ""));
};

const arretPopupHtml = (a) =>
  `<b>${esc(a.nom)}</b>${groupeBadges(a)}<br><span class="text-xs">${esc(a.commune ?? "")} · #${a.id} · ${
    a.types.map((t) => TYPE_LABELS[t] ?? t).join(", ") || arretTypeDominant([])
  }</span><br><div class="mt-2">${arretActions(a)}</div>`;

const lignePopupHtml = (l) =>
  `<b>${esc(ligneLabel(l))}</b><br><span class="text-xs">${esc(l.operateur ?? "")} · #${l.id}</span><br>` +
  `<div class="mt-2">${ligneActions(l)}</div>`;

const falaisePopupHtml = (f) =>
  `<b>${esc(f.nom)}</b><br><span class="text-xs opacity-70">Falaise #${f.id}</span>` +
  `<div class="mt-2">${toolbar(iconBtn("link", "Lier à un arrêt", "btn-outline", `data-link-start="falaise:${f.id}"`))}</div>`;

async function setupLayers() {
  map.addSource("lignes", { type: "geojson", data: emptyFc() });
  map.addLayer({
    id: "lignes-line",
    type: "line",
    source: "lignes",
    layout: { "line-cap": "round", "line-join": "round" },
    paint: {
      "line-color": ["get", "color"],
      "line-width": 4,
      "line-opacity": 0.85,
    },
  });
  // Zone de clic élargie, invisible
  map.addLayer({
    id: "lignes-hit",
    type: "line",
    source: "lignes",
    paint: { "line-width": 14, "line-opacity": 0 },
  });

  // Enveloppe (enveloppe convexe + buffer) des arrêts regroupés sous un parent, à fort zoom
  map.addSource("arrets-groupes", { type: "geojson", data: emptyFc() });
  map.addLayer({
    id: "arrets-groupes-fill",
    type: "fill",
    source: "arrets-groupes",
    minzoom: GROUPE_ZOOM,
    paint: { "fill-color": "#2563eb", "fill-opacity": 0.12 },
  });
  map.addLayer({
    id: "arrets-groupes-line",
    type: "line",
    source: "arrets-groupes",
    minzoom: GROUPE_ZOOM,
    paint: { "line-color": "#2563eb", "line-width": 1.5, "line-opacity": 0.7 },
  });

  map.addSource("arrets-circles", { type: "geojson", data: emptyFc() });
  map.addLayer({
    id: "arrets-circles",
    type: "circle",
    source: "arrets-circles",
    // Parent : plus gros, liseré blanc épais ; enfant : petit, halo très fin ; arrêt seul : taille d'origine
    paint: {
      "circle-radius": [
        "match",
        ["get", "role"],
        "parent",
        4.5,
        "enfant",
        3.5,
        4.5,
      ],
      "circle-color": ["get", "color"],
      "circle-stroke-color": "#fff",
      "circle-stroke-width": [
        "match",
        ["get", "role"],
        "parent",
        1.5,
        "enfant",
        0.5,
        1.5,
      ],
    },
  });

  map.addSource("falaises", {
    type: "geojson",
    data: {
      type: "FeatureCollection",
      features: falaises.map((f) => pointFeature({ id: f.id }, f)),
    },
  });
  map.addLayer({
    id: "falaises-dot",
    type: "circle",
    source: "falaises",
    maxzoom: DOT_MAX_ZOOM,
    paint: {
      "circle-radius": 5,
      "circle-color": "#16a34a",
      "circle-stroke-color": "#fff",
      "circle-stroke-width": 2,
    },
  });
  try {
    const img = await map.loadImage("/images/map/icone_falaise_carte.png");
    map.addImage("falaise", img.data);
    map.addLayer({
      id: "falaises-icon",
      type: "symbol",
      source: "falaises",
      minzoom: DOT_MAX_ZOOM,
      layout: {
        "icon-image": "falaise",
        "icon-size": 22 / img.data.width,
        "icon-allow-overlap": true,
      },
    });
  } catch {
    // Icône indisponible : on garde les points à tout zoom
    map.setLayerZoomRange("falaises-dot", 0, 24);
  }

  // Interactions
  const first = (e) => e.features?.[0];
  const byId = (list, e) => list.find((x) => x.id === first(e)?.properties.id);
  map.on("click", "arrets-circles", (e) => {
    const a = byId(arrets, e);
    if (!a) return;
    e.originalEvent.__vgHandled = true;
    onItemClick("arret", a, arretPopupHtml(a));
  });
  for (const layer of ["falaises-dot", "falaises-icon"]) {
    if (!map.getLayer(layer)) continue;
    map.on("click", layer, (e) => {
      const f = byId(falaises, e);
      if (!f) return;
      e.originalEvent.__vgHandled = true;
      onItemClick("falaise", f, falaisePopupHtml(f));
    });
    map.on("mouseenter", layer, (e) => {
      const f = byId(falaises, e);
      if (!f || popup?.isOpen()) return;
      hoverPopup?.remove();
      hoverPopup = new maplibregl.Popup({
        closeButton: false,
        closeOnClick: false,
        offset: 12,
        className: "vg-hover-popup",
      })
        .setLngLat([f.lng, f.lat])
        .setText(f.nom)
        .addTo(map);
    });
    map.on("mouseleave", layer, () => hoverPopup?.remove());
    HIT(layer);
  }
  map.on("click", "lignes-hit", (e) => {
    if (e.originalEvent.__vgHandled) return; // un arrêt / une falaise au même endroit prime
    const l = lignes.find((x) => x.id === first(e)?.properties.id);
    if (!l) return;
    openPopup(e.lngLat, lignePopupHtml(l));
  });
  HIT("arrets-circles");
  HIT("lignes-hit");
}

function ensureMap() {
  if (map) return;
  const b = BASEMAPS.Landscape;
  map = new maplibregl.Map({
    container: "admin-map",
    style: {
      version: 8,
      sources: {
        basemap: {
          type: "raster",
          tiles: [b.url],
          tileSize: 256,
          maxzoom: b.maxzoom,
          attribution: b.attribution,
        },
      },
      layers: [{ id: "basemap", type: "raster", source: "basemap" }],
    },
    center: [3.2355, 45.1234],
    zoom: 4,
    dragRotate: false,
    pitchWithRotate: false,
    ...(savedMap
      ? {
          center: [savedMap.center[1], savedMap.center[0]],
          zoom: savedMap.zoom,
        }
      : {}),
  });
  map.addControl(
    new maplibregl.NavigationControl({ showCompass: false }),
    "top-left",
  );
  map.addControl(new maplibregl.FullscreenControl(), "top-left");
  map.addControl(
    new maplibregl.GeolocateControl({
      positionOptions: { enableHighAccuracy: true },
      trackUserLocation: true,
    }),
    "top-left",
  );
  map.addControl(basemapControl(), "top-left");
  map.addControl(searchControl(), "top-right");
  map.addControl(
    new maplibregl.ScaleControl({ maxWidth: 125, unit: "metric" }),
    "bottom-right",
  );
  document.addEventListener(
    "keydown",
    (e) => e.key === "Escape" && linkSource && cancelLink(),
  );
  map.on("moveend", () => {
    updateLabels();
    writeUrl();
  });
  map.on("load", async () => {
    await setupLayers();
    mapReady = true;
    const pts = arrets.filter((a) => !a.deleted);
    if (!savedMap && pts.length) {
      const lngs = pts.map((a) => a.lng),
        lats = pts.map((a) => a.lat);
      map.fitBounds(
        [
          [Math.min(...lngs), Math.min(...lats)],
          [Math.max(...lngs), Math.max(...lats)],
        ],
        { padding: 20, animate: false },
      );
    }
    renderFalaises();
    renderMap();
    lignesPromise = loadLignes();
  });
}

async function loadLignes() {
  $("map-status").textContent = "Chargement des lignes…";
  try {
    const r = await fetch("/api/fetch_lignes.php?geometry=1");
    lignesGeo = (await r.json()).lignes;
    ligneBounds.clear();
    for (const l of lignesGeo) {
      let w = Infinity,
        s = Infinity,
        e = -Infinity,
        n = -Infinity;
      for (const line of l.geometry?.coordinates ?? [])
        for (const [x, y] of line) {
          w = Math.min(w, x);
          e = Math.max(e, x);
          s = Math.min(s, y);
          n = Math.max(n, y);
        }
      if (w <= e)
        ligneBounds.set(l.id, [
          [w, s],
          [e, n],
        ]);
    }
    $("map-status").textContent = "";
    renderMap();
  } catch {
    $("map-status").textContent = "Impossible de charger les lignes.";
  }
}

function renderFalaises() {
  if (!mapReady) return;
  const vis = $("layer-falaises").checked ? "visible" : "none";
  for (const l of ["falaises-dot", "falaises-icon"])
    if (map.getLayer(l)) map.setLayoutProperty(l, "visibility", vis);
}

function domMarker(el, a, opts = {}) {
  return new maplibregl.Marker({
    element: el,
    anchor: "center",
    ...opts,
  }).setLngLat([a.lng, a.lat]);
}

// Rôle dans un regroupement : un parent est représenté plus gros, un enfant plus discret
const arretRole = (a) =>
  a.nb_enfants > 0 ? "parent" : a.parent_id != null ? "enfant" : "seul";

function arretIconEl(a) {
  const el = document.createElement("div");
  const { html, size } = arretIconHtml(a.types, a.nb_enfants > 0 ? 28 : 20);
  el.className = "vg-marker vg-arret-icon";
  el.style.width = el.style.height = size + "px";
  el.innerHTML = html;
  return el;
}

/** Un polygone par parent ayant au moins deux enfants visibles. */
function groupeFeatures() {
  if (!$("layer-arrets").checked) return [];
  const enfants = new Map();
  for (const a of arrets.filter(arretVisible))
    if (a.parent_id != null)
      (
        enfants.get(a.parent_id) ??
        enfants.set(a.parent_id, []).get(a.parent_id)
      ).push(a);
  const features = [];
  for (const [parentId, list] of enfants) {
    const ring = enveloppe(list);
    if (ring)
      features.push({
        type: "Feature",
        properties: { id: parentId },
        geometry: { type: "Polygon", coordinates: [ring] },
      });
  }
  return features;
}

function renderMap() {
  if (!mapReady) return;
  // Arrêts : icônes DOM pour ceux reliés à une falaise, cercles GeoJSON pour les autres
  arretMarkers.forEach((m) => m.remove());
  arretMarkers.clear();
  const circles = [];
  if ($("layer-arrets").checked) {
    arrets.filter(arretVisible).forEach((a) => {
      if (a.nb_acces > 0) {
        const el = arretIconEl(a);
        el.addEventListener("click", (ev) => {
          ev.stopPropagation();
          onItemClick("arret", a, arretPopupHtml(a));
        });
        arretMarkers.set(a.id, domMarker(el, a).addTo(map));
      } else {
        circles.push(
          pointFeature(
            { id: a.id, color: arretCercleColor(a), role: arretRole(a) },
            a,
          ),
        );
      }
    });
  }
  setData("arrets-circles", circles);
  setData("arrets-groupes", groupeFeatures());

  const lineFeatures = [];
  if ($("layer-lignes").checked && lignesGeo) {
    lignesGeo.filter(ligneVisible).forEach((l) => {
      if (!l.geometry?.coordinates?.length) return;
      lineFeatures.push({
        type: "Feature",
        properties: { id: l.id, color: ligneColor(l) },
        geometry: l.geometry,
      });
    });
  }
  setData("lignes", lineFeatures);
  updateLabels();
}

// Noms des arrêts, à droite du point (comme carte.php) : gares TER/TGV dès LABEL_ZOOM_GARE, les autres dès LABEL_ZOOM ;
// seulement les arrêts dans la vue, pour ne pas poser des milliers d'éléments DOM.
const MAX_LABELS = 400;
function updateLabels() {
  if (!mapReady) return;
  const z = map.getZoom();
  const bounds = map.getBounds();
  const want = new Map();
  if ($("layer-arrets").checked && z >= LABEL_ZOOM_GARE) {
    for (const a of arrets) {
      if (want.size >= MAX_LABELS) break;
      // Un arrêt regroupé n'est pas nommé : seul son parent l'est (même nom, au centre du groupe)
      if (
        a.parent_id != null ||
        !arretVisible(a) ||
        !bounds.contains([a.lng, a.lat])
      )
        continue;
      const gare = a.types.some((t) => t === "ter" || t === "tgv");
      if (z >= (gare ? LABEL_ZOOM_GARE : LABEL_ZOOM)) want.set(a.id, a);
    }
  }
  for (const [id, m] of labelMarkers)
    if (!want.has(id)) {
      m.remove();
      labelMarkers.delete(id);
    }
  for (const [id, a] of want) {
    if (labelMarkers.has(id)) continue;
    const el = document.createElement("div");
    el.className = "vg-arret-label";
    el.textContent = a.nom;
    const dx =
      a.nb_enfants > 0 ? (a.nb_acces > 0 ? 18 : 12) : a.nb_acces > 0 ? 14 : 8; // plus loin pour un parent (plus gros)
    labelMarkers.set(
      id,
      new maplibregl.Marker({ element: el, anchor: "left", offset: [dx, 0] })
        .setLngLat([a.lng, a.lat])
        .addTo(map),
    );
  }
}

// --- Repositionnement : « Déplacer » rend le marqueur déplaçable ; le lâcher demande confirmation ---
async function startMove(id) {
  if (state.view !== "carte") await showOnMap("arret", id); // depuis la liste : on bascule d'abord sur la carte
  const a = arrets.find((x) => x.id === id);
  if (!a || !mapReady) return;
  closePopup();
  const from = new maplibregl.LngLat(a.lng, a.lat);
  // Marqueur temporaire déplaçable (le cercle GeoJSON ne l'est pas) ; renderMap() rétablit le rendu normal
  arretMarkers.get(id)?.remove();
  labelMarkers.get(id)?.remove();
  labelMarkers.delete(id);
  const marker = domMarker(arretIconEl(a), a, { draggable: true }).addTo(map);
  arretMarkers.set(id, marker);
  $("map-status").textContent = `Déplacez « ${a.nom} » à sa nouvelle position.`;
  marker.once("dragend", async () => {
    $("map-status").textContent = "";
    const to = marker.getLngLat();
    if (
      !confirm(
        `Déplacer « ${a.nom} » (#${id}) de ${Math.round(from.distanceTo(to))} m ?\nSa position sera fixée à la main (jamais recalculée ni mise à jour par un import GTFS).`,
      )
    ) {
      marker.remove();
      renderMap();
      return;
    }
    try {
      const r = await fetch("/api/move_arret.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ arret_id: id, lat: to.lat, lng: to.lng }),
      });
      if (!r.ok)
        throw new Error(
          (await r.json().catch(() => ({}))).error || r.statusText,
        );
      a.lat = to.lat;
      a.lng = to.lng;
      a.position_manuelle = true;
      // Le parent d'un enfant déplacé est recalculé côté serveur : on recharge pour retrouver sa position
      if (a.parent_id != null) return location.reload();
    } catch (e) {
      alert(`Déplacement impossible : ${e.message}`);
    }
    marker.remove();
    renderMap();
  });
}

// --- « Voir sur la carte » depuis la liste ---
async function showOnMap(kind, id) {
  $("layer-arrets").checked = true;
  $("layer-lignes").checked = true;
  setView("carte");
  if (!mapReady)
    await new Promise((res) => map.once("load", () => setTimeout(res, 0)));
  if (kind === "arret") {
    const a = arrets.find((x) => x.id === id);
    if (!a) return;
    map.jumpTo({ center: [a.lng, a.lat], zoom: 15 });
    renderMap();
    onItemClick("arret", a, arretPopupHtml(a));
  } else {
    await lignesPromise;
    const bb = ligneBounds.get(id);
    if (!bb) return alert("Cette ligne n'a pas de tracé à afficher.");
    const l = lignes.find((x) => x.id === id);
    map.fitBounds(bb, { padding: 30, animate: false });
    openPopup([(bb[0][0] + bb[1][0]) / 2, (bb[0][1] + bb[1][1]) / 2], lignePopupHtml(l));
  }
}

// --- Suppression d'une ligne : dialogue « ligne seule » / « ligne et arrêts sans falaise » ------
function deleteLigne(id) {
  const l = lignes.find((x) => x.id === id);
  if (!l) return;
  const dlg = document.createElement("dialog");
  dlg.className = "modal";
  dlg.innerHTML = `<div class="modal-box flex flex-col gap-3">
      <h3 class="font-bold text-lg">Supprimer la ligne « ${esc(ligneLabel(l))} » (#${l.id}) ?</h3>
      <p>La ligne et son tracé seront supprimés définitivement.</p>
      <p>Faut-il aussi supprimer ses arrêts ? Ils seraient alors supprimés <b>définitivement</b>, avec leurs références.
        Seuls le sont ceux qui ne sont reliés à aucune falaise ni à aucun trajet train (ni eux, ni leur arrêt parent)
        et ne desservent aucune autre ligne ; les autres sont conservés.</p>
      <div class="modal-action flex-wrap">
        <button type="button" class="btn" data-choix="annuler">Annuler</button>
        <button type="button" class="btn btn-error btn-outline" data-choix="ligne">Supprimer la ligne seule</button>
        <button type="button" class="btn btn-error" data-choix="arrets">Supprimer la ligne et ses arrêts sans falaise</button>
      </div></div>`;
  document.body.appendChild(dlg);
  const fermer = () => {
    dlg.close();
    dlg.remove();
  };
  dlg.addEventListener("cancel", fermer);
  dlg.addEventListener("click", async (e) => {
    const choix = e.target.closest("[data-choix]")?.dataset.choix;
    if (!choix) return;
    if (choix === "annuler") return fermer();
    dlg.querySelectorAll("button").forEach((b) => (b.disabled = true));
    try {
      const r = await fetch("/api/delete_ligne.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          ligne_id: id,
          supprimer_arrets: choix === "arrets",
        }),
      });
      const body = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(body.error || r.statusText);
      if (choix === "arrets")
        alert(
          `Ligne supprimée. Arrêts supprimés : ${body.arrets_supprimes}, conservés : ${body.arrets_conserves}.`,
        );
      location.reload(); // compteurs, lignes des arrêts et carte à jour
    } catch (err) {
      alert(`Suppression impossible : ${err.message}`);
      fermer();
    }
  });
  dlg.showModal();
}

// --- Suppression ---------------------------------------------------------
async function deleteArret(id) {
  const a = arrets.find((x) => x.id === id);
  if (!a) return;
  const enfants = arrets.filter((x) => x.parent_id === id && !x.deleted);
  const nbAcces = a.nb_acces + enfants.reduce((n, e) => n + e.nb_acces, 0);
  const cascade = enfants.length > 0 || nbAcces > 0;
  let msg = `Supprimer l'arrêt « ${a.nom} » (#${id}) ?\nIl sera masqué du site (suppression logique).`;
  if (cascade) {
    msg = `⚠ Supprimer l'arrêt « ${a.nom} » (#${id}) ?\n`;
    if (enfants.length) msg += `\n• ${enfants.length} arrêt(s) enfant(s) seront aussi supprimés : ${enfants.map((e) => e.nom).join(", ")}`;
    if (nbAcces) msg += `\n• ${nbAcces} accès aux falaises seront supprimés DÉFINITIVEMENT.`;
    msg += `\n\nLes arrêts sont masqués du site (suppression logique) ; les accès sont effacés.`;
  }
  if (!confirm(msg)) return;
  try {
    const r = await fetch("/api/delete_arret.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ arret_id: id, cascade }),
    });
    if (!r.ok)
      throw new Error((await r.json().catch(() => ({}))).error || r.statusText);
    a.deleted = true;
    if (cascade) {
      enfants.forEach((e) => { e.deleted = true; e.nb_acces = 0; });
      a.nb_acces = 0;
      a.nb_enfants = 0;
    }
    closePopup();
    refresh();
  } catch (e) {
    alert(`Suppression impossible : ${e.message}`);
  }
}
document.addEventListener("click", (e) => {
  const dl = e.target.closest("[data-delete-ligne]");
  if (dl) deleteLigne(Number(dl.dataset.deleteLigne));
  const b = e.target.closest("[data-delete-arret]");
  if (b) deleteArret(Number(b.dataset.deleteArret));
  const mv = e.target.closest("[data-move-arret]");
  if (mv) startMove(Number(mv.dataset.moveArret));
  const ls = e.target.closest("[data-link-start]");
  if (ls)
    startLink(
      ...ls.dataset.linkStart.split(":").map((v, i) => (i ? Number(v) : v)),
    );
  if (e.target.closest("[data-link-cancel]")) cancelLink();
  // Clic sur la ligne d'un parent (hors boutons / liens) : affiche ou masque les lignes de ses enfants
  const tg = e.target.closest("#tbody-arrets tr")?.querySelector("[data-enfants-toggle]");
  if (tg && !e.target.closest("a, button")) {
    const id = Number(tg.dataset.enfantsToggle);
    const ouvert = !openParents.has(id);
    ouvert ? openParents.add(id) : openParents.delete(id);
    document.querySelectorAll(`#tbody-arrets tr[data-enfant-de="${id}"]`).forEach((r) => (r.hidden = !ouvert));
    tg.textContent = tg.textContent.replace(/^[▸▾]/, ouvert ? "▾" : "▸");
  }
  const sa = e.target.closest("[data-show-arret]");
  if (sa) showOnMap("arret", Number(sa.dataset.showArret));
  const sl = e.target.closest("[data-show-ligne]");
  if (sl) showOnMap("ligne", Number(sl.dataset.showLigne));
  const dt = e.target.closest("[data-detach-arret]");
  if (dt) detachArret(Number(dt.dataset.detachArret));
  const ea = e.target.closest("[data-edit-arret]");
  if (ea) openArretDialog(arrets.find((x) => x.id === Number(ea.dataset.editArret)));
  const el = e.target.closest("[data-edit-ligne]");
  if (el) openLigneDialog(lignes.find((x) => x.id === Number(el.dataset.editLigne)));
  const gi = e.target.closest("[data-gtfs-ligne]");
  if (gi)
    document.dispatchEvent(
      new CustomEvent("velogrimpe:gtfs-import-open", { detail: { ligneId: gi.dataset.gtfsLigne === "new" ? null : Number(gi.dataset.gtfsLigne) } }),
    );
  const um = e.target.closest("[data-unmerge-arret]");
  if (um) unmergeArret(Number(um.dataset.unmergeArret));
  const ap = e.target.closest("[data-auto-position]");
  if (ap) autoPosition(Number(ap.dataset.autoPosition));
  const from = e.target.closest("[data-merge-from]");
  const into = e.target.closest("[data-merge-into]");
  if (from) {
    mergeSource = Number(from.dataset.mergeFrom);
    refresh();
  } else if (into) mergeArrets(mergeSource, Number(into.dataset.mergeInto));
  else if (e.target.closest("[data-merge-cancel]")) {
    mergeSource = null;
    refresh();
  }
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
  document
    .querySelectorAll("[data-view]")
    .forEach((b) => b.classList.toggle("tab-active", b.dataset.view === view));
  $("view-liste").classList.toggle("hidden", view !== "liste");
  $("view-carte").classList.toggle("hidden", view !== "carte");
  $("view-carte").classList.toggle("flex", view === "carte");
  if (view === "carte") {
    ensureMap();
    map.resize();
    renderMap();
  }
}

let timer;
$("search").addEventListener("input", () => {
  clearTimeout(timer);
  timer = setTimeout(refresh, 150);
});
document.addEventListener("click", (e) => {
  const b = e.target.closest("button[data-filter]");
  if (!b) return;
  setFilter(b.closest("[data-filter-group]"), b.dataset.value);
  refresh();
});
$("layer-arrets").addEventListener("change", renderMap);
$("layer-lignes").addEventListener("change", renderMap);
$("layer-falaises").addEventListener("change", renderFalaises);
document
  .querySelectorAll("[data-view]")
  .forEach((b) => b.addEventListener("click", () => setView(b.dataset.view)));

const initialView = readUrl();
refresh();
if (initialView === "carte") setView("carte");
