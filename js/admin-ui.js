// Briques d'interface partagées par les pages admin : échappement, recherche sans accents,
// boutons-icônes en barre d'outils (.vg-toolbar, css/admin-ui.css) et tooltips `data-tip`.

export const esc = (s) =>
  String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
export const norm = (s) =>
  String(s ?? "")
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase();

// Bouton-icône (symbole du sprite /symbols/icons.svg) ; le libellé est en tooltip et en aria-label.
export const ico = (name) =>
  `<svg class="w-4 h-4 fill-none stroke-current" stroke-width="1.5" aria-hidden="true"><use href="#${name}"></use></svg>`;
// Barre d'outils : une seule bordure autour de boutons séparés par un filet ; tous en contour, seule la suppression est en rouge.
export const toolbar = (html) => (html ? `<div class="vg-toolbar">${html}</div>` : "");
export const iconBtn = (name, label, cls, attrs) =>
  `<button type="button" class="btn btn-xs btn-square ${cls}" data-tip="${label}" aria-label="${label}" ${attrs}>${ico(name)}</button>`;
// Variante lien (navigation vers un formulaire ou une page) : même rendu que iconBtn.
export const iconLink = (name, label, href, cls = "btn-outline", extra = "") =>
  `<a class="btn btn-xs btn-square ${cls}" data-tip="${label}" aria-label="${label}" href="${esc(href)}" ${extra}>${ico(name)}</a>`;

// --- Tooltips des boutons (`data-tip`) ---------------------------------------------------------
// Un seul élément `position: fixed` au-dessus du bouton, rattaché au body : aucun conteneur à défilement (tableaux,
// popups) ne le rogne. Centré sur le bouton, décalé si besoin pour rester dans la fenêtre (la flèche suit le bouton).
const tipEl = document.createElement("div");
tipEl.className = "vg-tip";
tipEl.hidden = true;
document.body.appendChild(tipEl);
export const hideTip = () => (tipEl.hidden = true);
function showTip(el) {
  tipEl.textContent = el.dataset.tip;
  tipEl.hidden = false;
  const r = el.getBoundingClientRect();
  const w = tipEl.offsetWidth;
  const left = Math.max(4, Math.min(r.left + r.width / 2 - w / 2, window.innerWidth - w - 4));
  tipEl.style.left = `${left}px`;
  tipEl.style.top = `${r.top - tipEl.offsetHeight - 8}px`;
  tipEl.style.setProperty("--vg-tip-arrow", `${r.left + r.width / 2 - left}px`);
}
document.addEventListener("mouseover", (e) => {
  const el = e.target.closest?.("[data-tip]");
  el ? showTip(el) : hideTip();
});
document.addEventListener("focusin", (e) => {
  const el = e.target.closest?.("[data-tip]");
  el ? showTip(el) : hideTip();
});
["focusout", "mousedown", "wheel", "keydown"].forEach((t) => document.addEventListener(t, hideTip, true));
document.addEventListener("scroll", hideTip, true);
