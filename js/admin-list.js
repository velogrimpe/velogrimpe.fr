/**
 * Listes de données de l'admin (lib/admin_list.php) : falaises (avec détails),
 * accès, trajets train, villes. Une config par `kind` : colonnes, texte de recherche,
 * groupes de filtres et boutons d'action par ligne.
 * Un filtre est un groupe de boutons à choix unique : « Tous » ou UNE option (on ne décoche pas le reste).
 * Recherche et filtres sont synchronisés dans l'URL (?q=…&statut=verifier) : « Tous » n'écrit rien.
 */
import { esc, norm, toolbar, iconLink, iconBtn } from "/js/admin-ui.js";

const { kind, rows, token } = JSON.parse(document.getElementById("page-data").textContent);
const $ = (id) => document.getElementById(id);
const adm = encodeURIComponent(token);

// Liens vers les pages publiques et les formulaires (jeton admin quand le formulaire l'exige).
const url = {
  falaise: (id) => `/falaise.php?falaise_id=${id}`,
  falaiseEdit: (id) => `/ajout/ajout_falaise.php?admin=${adm}&falaise_id=${id}`,
  falaiseDetails: (id) => `/ajout/contrib/details_falaise.php?falaise_id=${id}`,
  accesEdit: (a) => `/ajout/edit_velo.php?admin=${adm}&falaise_id=${a.falaise_id}&gare_id=${a.arret_id}&velo_id=${a.id}`,
};

const badge = (cls, label) => `<span class="badge badge-sm whitespace-nowrap ${cls}">${label}</span>`;
const dash = '<span class="opacity-40">—</span>';
const dateFr = (ts) => (ts ? new Date(ts * 1000).toLocaleDateString("fr-FR") : "");
const duree = (min) => (min == null ? dash : `${Math.floor(min / 60)}h${String(min % 60).padStart(2, "0")}`);

const FALAISE_STATUT = {
  1: ["topo", "Topo", "badge-success"],
  2: ["verifier", "À vérifier", "badge-warning"],
  3: ["hors_topo", "Hors topo", "badge-neutral"],
  4: ["verrouillee", "Verrouillée", "badge-ghost"],
};
const statutFalaise = (r) => {
  const [, label, cls] = FALAISE_STATUT[r.public] ?? ["autre", `Statut ${r.public}`, "badge-ghost"];
  return badge(cls, label);
};
const falaiseCell = (r) =>
  `<a class="font-semibold link link-hover" href="${url.falaise(r.id)}">${esc(r.nom)}</a> <span class="opacity-50 text-xs">#${r.id}</span>`;
// Un arrêt peut desservir des dizaines de falaises : les premières en lien, le reste en « +N » (nom complet en infobulle).
const falaiseLinks = (list, max = 3) => {
  if (!list.length) return dash;
  const link = (f) => `<a class="link link-hover" href="${url.falaise(f.id)}">${esc(f.nom)}</a>`;
  const reste = list.slice(max);
  return (
    list.slice(0, max).map(link).join(", ") +
    (reste.length ? ` <span class="opacity-60" title="${esc(reste.map((f) => f.nom).join(", "))}">+${reste.length}</span>` : "")
  );
};

// Groupe de filtres : options = [valeur, libellé] ; la valeur d'une ligne est donnée par `value[key]` du kind.
const group = (key, label, options) => ({ key, label, options });

const KINDS = {
  falaises: {
    cols: ["Falaise", "Statut", "Zone", { t: "Accès", cls: "text-right" }, "Détails", { t: "", w: "8.5rem" }],
    search: (r) => `${r.nom} ${r.zone} ${r.id}`,
    filters: [
      group(
        "statut",
        "Statut :",
        Object.values(FALAISE_STATUT).map(([v, l]) => [v, l]),
      ),
      group("details", "Détails :", [
        ["avec", "Renseignés"],
        ["sans", "Sans détails"],
      ]),
      group("fermee", "Fermeture :", [
        ["oui", "Fermées"],
        ["non", "Ouvertes"],
      ]),
    ],
    value: {
      fermee: (r) => (r.fermee ? "oui" : "non"),
      statut: (r) => (FALAISE_STATUT[r.public] ?? ["autre"])[0],
      details: (r) => (r.mtime ? "avec" : "sans"),
    },
    row: (r) => [
      falaiseCell(r),
      statutFalaise(r) + (r.fermee ? " " + badge("badge-error", "Fermée") : ""),
      esc(r.zone) || dash,
      { v: r.nb_acces, cls: "text-right" },
      r.mtime
        ? `${badge("badge-success", "Renseignés")} <span class="opacity-60 text-xs">${dateFr(r.mtime)}</span>`
        : badge("badge-ghost", "Aucun"),
    ],
    actions: (r) =>
      toolbar(
        iconLink("eye", "Voir la falaise", url.falaise(r.id)) +
          iconLink("pencil", "Modifier la falaise", url.falaiseEdit(r.id)) +
          iconLink("mountain", r.mtime ? "Modifier les détails (secteurs, parkings…)" : "Ajouter les détails (secteurs, parkings…)", url.falaiseDetails(r.id)),
      ),
  },
  acces: {
    cols: ["Arrêt", "Falaise", { t: "Type", cls: "text-center", w: "4rem" }, "Km / D+", { t: "GPX", cls: "text-center", w: "3rem" }, "Statut", { t: "", w: "8.5rem" }],
    search: (r) => `${r.arret} ${r.falaise} ${r.variante} ${r.contrib} ${r.id}`,
    filters: [
      group("statut", "Statut :", [
        ["verifier", "À vérifier"],
        ["valide", "Validés"],
      ]),
      group("type", "Type :", [
        ["velo", "Vélo"],
        ["pied", "À pied"],
      ]),
      group("gpx", "GPX :", [
        ["avec", "Avec GPX"],
        ["sans", "Sans GPX"],
      ]),
    ],
    value: { statut: (r) => (r.public === 2 ? "verifier" : "valide"), type: (r) => r.type, gpx: (r) => (r.gpx ? "avec" : "sans") },
    row: (r) => [
      esc(r.arret) + (r.variante ? ` <span class="opacity-60 text-xs">(${esc(r.variante)})</span>` : ""),
      `<a class="font-semibold link link-hover" href="${url.falaise(r.falaise_id)}">${esc(r.falaise)}</a>`,
      {
        v: `<svg class="w-4 h-5 fill-current inline-block align-middle" role="img" aria-label="${r.type === "pied" ? "À pied" : "À vélo"}"><title>${r.type === "pied" ? "À pied" : "À vélo"}</title><use href="#${r.type === "pied" ? "footprint" : "riding"}"></use></svg>`,
        cls: "text-center",
      },
      r.km == null ? dash : `${r.km} km${r.dplus != null ? ` · ${r.dplus} m D+` : ""}`,
      { v: r.gpx ? '<span class="opacity-60" title="GPX présent">✔︎</span>' : dash, cls: "text-center" },
      r.public === 2 ? badge("badge-warning", "À vérifier") : badge("badge-success", "Validé"),
    ],
    actions: (r) =>
      toolbar(
        iconLink("eye", "Voir la falaise", url.falaise(r.falaise_id)) +
          iconLink("pencil", "Modifier l'accès", url.accesEdit(r)) +
          (r.gpx_url ? iconLink("download", "Télécharger le GPX", r.gpx_url, "btn-outline", "download") : "") +
          iconBtn("file-upload", r.gpx ? "Remplacer le GPX" : "Ajouter un GPX", "btn-outline", `data-upload-gpx="${r.id}"`) +
          iconBtn("trash", "Supprimer l'accès", "btn-error btn-outline", `data-delete-acces="${r.id}"`),
      ),
  },
  trains: {
    cols: ["Ville de départ", "Arrêt (gare)", "Trajet", "Falaises liées", { t: "", w: "4rem" }],
    search: (r) => `${r.ville} ${r.arret} ${r.falaises.map((f) => f.nom).join(" ")} ${r.id}`,
    filters: [
      group("type", "Type :", [
        ["ter", "TER / Intercités"],
        ["tgv", "TGV"],
      ]),
    ],
    value: { type: (r) => (r.tgv ? "tgv" : "ter") },
    row: (r) => [
      esc(r.ville),
      esc(r.arret),
      `${duree(r.temps)}${r.tempsmax ? ` – ${duree(r.tempsmax)}` : ""}${r.tgv ? " " + badge("badge-info", "TGV") : ""}` +
        (r.nbparjour ? ` <span class="opacity-60 text-xs">${r.nbparjour}/jour</span>` : ""),
      falaiseLinks(r.falaises),
    ],
    // Pas de formulaire de modification d'un trajet train : « voir » ouvre le tableau de la ville.
    actions: (r) => toolbar(iconLink("eye", "Voir le tableau de la ville", `/tableau.php?ville_id=${r.ville_id}`)),
  },
  villes: {
    cols: ["Ville de départ", { t: "Dans le tableau", cls: "text-center" }, { t: "Trajets train", cls: "text-right" }, { t: "", w: "6rem" }],
    search: (r) => `${r.nom} ${r.id}`,
    filters: [
      group("tableau", "Tableau :", [
        ["oui", "Affichées"],
        ["non", "Masquées"],
      ]),
    ],
    value: { tableau: (r) => (r.tableau ? "oui" : "non") },
    row: (r) => [
      `<b>${esc(r.nom)}</b> <span class="opacity-50 text-xs">#${r.id}</span>`,
      { v: r.tableau ? "✔" : dash, cls: "text-center" },
      { v: r.nb_trains, cls: "text-right" },
    ],
    actions: (r) =>
      toolbar(
        iconLink("eye", "Voir le tableau de la ville", `/tableau.php?ville_id=${r.id}`) +
          iconLink("plus", "Ajouter un itinéraire train depuis cette ville", `/ajout/ajout_train.php?admin=${adm}&ville_id=${r.id}`),
      ),
  },
};

const cfg = KINDS[kind];
const params = new URLSearchParams(location.search);
const state = { q: params.get("q") ?? "", groups: {} }; // groups[clé] = valeur choisie, "" = Tous
for (const g of cfg.filters) {
  const raw = params.get(g.key) ?? "";
  state.groups[g.key] = g.options.some(([v]) => v === raw) ? raw : "";
}

function writeUrl() {
  const p = new URLSearchParams(location.search);
  state.q ? p.set("q", state.q) : p.delete("q");
  for (const g of cfg.filters) {
    state.groups[g.key] ? p.set(g.key, state.groups[g.key]) : p.delete(g.key);
  }
  const qs = p.toString();
  history.replaceState(null, "", location.pathname + (qs ? `?${qs}` : ""));
}

// En-tête et filtres : construits une fois.
$("thead").innerHTML = `<tr>${cfg.cols
  .map((c) => {
    const o = typeof c === "string" ? { t: c } : c;
    return `<th class="${o.cls ?? ""}"${o.w ? ` style="width:${o.w}"` : ""}>${esc(o.t)}</th>`;
  })
  .join("")}</tr>`;
const renderFilters = () =>
  ($("filters").innerHTML = cfg.filters
    .map((g) => {
      const btn = (v, l) =>
        `<button type="button" class="btn btn-xs join-item ${state.groups[g.key] === v ? "btn-primary text-primary-content" : "btn-outline"}" data-group="${g.key}" data-value="${esc(v)}" aria-pressed="${state.groups[g.key] === v}">${esc(l)}</button>`;
      return `<div class="flex items-center gap-2"><span class="font-semibold">${esc(g.label)}</span><div class="join">${btn("", "Tous")}${g.options.map(([v, l]) => btn(v, l)).join("")}</div></div>`;
    })
    .join(""));
renderFilters();
$("search").value = state.q;

const haystack = rows.map((r) => norm(cfg.search(r)));
const cell = (c) => (typeof c === "object" ? `<td class="${c.cls ?? ""}">${c.v}</td>` : `<td>${c}</td>`);

function render() {
  const terms = norm(state.q).split(/\s+/).filter(Boolean);
  const out = [];
  rows.forEach((r, i) => {
    if (!cfg.filters.every((g) => !state.groups[g.key] || state.groups[g.key] === cfg.value[g.key](r))) return;
    if (!terms.every((t) => haystack[i].includes(t))) return;
    out.push(`<tr class="hover:bg-base-200">${cfg.row(r).map(cell).join("")}<td class="text-right whitespace-nowrap">${cfg.actions(r)}</td></tr>`);
  });
  $("tbody").innerHTML = out.join("");
  $("count").textContent = out.length === rows.length ? rows.length : `${out.length} / ${rows.length}`;
  $("empty").classList.toggle("hidden", out.length > 0);
}

$("search").addEventListener("input", (e) => {
  state.q = e.target.value;
  writeUrl();
  render();
});
$("filters").addEventListener("click", (e) => {
  const b = e.target.closest("button[data-group]");
  if (!b) return;
  state.groups[b.dataset.group] = b.dataset.value;
  renderFilters();
  writeUrl();
  render();
});
render();

// Suppression définitive d'un accès (et de son GPX) après confirmation.
$("tbody").addEventListener("click", async (e) => {
  const b = e.target.closest("[data-delete-acces]");
  if (!b) return;
  const i = rows.findIndex((r) => r.id === Number(b.dataset.deleteAcces));
  if (i < 0) return;
  const r = rows[i];
  const msg =
    `⚠ Supprimer DÉFINITIVEMENT l'accès #${r.id} ?\n\n${r.arret}${r.variante ? ` (${r.variante})` : ""} → ${r.falaise} (${r.type === "pied" ? "à pied" : "vélo"})` +
    (r.gpx ? "\n\n⚠ La trace GPX associée sera ELLE AUSSI supprimée définitivement (vous pouvez la télécharger avant)." : "") +
    "\n\nCette action est irréversible.";
  if (!confirm(msg)) return;
  b.disabled = true;
  try {
    const res = await fetch("/api/delete_acces.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}` },
      body: JSON.stringify({ acces_id: r.id }),
    });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).error || res.statusText);
    rows.splice(i, 1);
    haystack.splice(i, 1);
    render();
  } catch (err) {
    b.disabled = false;
    alert(`Suppression impossible : ${err.message}`);
  }
});

// Remplacement (ou ajout) de la trace GPX : un seul <input type=file> caché, réutilisé pour toutes les lignes.
const gpxInput = Object.assign(document.createElement("input"), { type: "file", accept: ".gpx,application/gpx+xml,text/xml", hidden: true });
document.body.appendChild(gpxInput);
let gpxCible = null;
$("tbody").addEventListener("click", (e) => {
  const b = e.target.closest("[data-upload-gpx]");
  if (!b) return;
  gpxCible = rows.find((r) => r.id === Number(b.dataset.uploadGpx));
  gpxInput.value = "";
  gpxInput.click();
});
gpxInput.addEventListener("change", async () => {
  const f = gpxInput.files[0];
  const r = gpxCible;
  if (!f || !r) return;
  if (r.gpx && !confirm(`Remplacer la trace GPX de l'accès #${r.id} (${r.arret} → ${r.falaise}) par « ${f.name} » ?\n\nL'ancienne version est archivée (gpx-historique/). Distance et dénivelés ne sont pas recalculés.`)) return;
  const body = new FormData();
  body.append("acces_id", r.id);
  body.append("gpx_file", f);
  try {
    const res = await fetch("/api/replace_acces_gpx.php", { method: "POST", headers: { Authorization: `Bearer ${token}` }, body });
    if (!res.ok) throw new Error((await res.json().catch(() => ({}))).error || res.statusText);
    // Recharge pour retrouver l'URL du GPX et son état (filtres et recherche sont conservés dans l'URL).
    location.reload();
  } catch (err) {
    alert(`Envoi impossible : ${err.message}`);
  }
});
