/**
 * Résolution du type dominant et de l'icône d'un arrêt multimodal, factorisée
 * pour être réutilisée partout (formulaire d'ajout, éditeur de falaise, carte
 * globale) — priorité ter > tgv > tram > telepherique > bus, miroir de
 * `arret_type_dominant()` (public_html/lib/arrets_lib.php).
 *
 * Rendu via `<use>` sur le sprite SVG global (public_html/symbols/icons.svg,
 * inclus par components/footer.php sur toutes les pages) plutôt que des PNG
 * dédiés par type.
 *
 * @module arret-icon
 */

const TYPE_PRIORITY = ["ter", "tgv", "tram", "telepherique", "bus"];

// Gares (ter/tgv) : icône ronde. Autres modalités : icône carrée arrondie
// (squircle), plus petite — cf. retour visuel du Lot 4.
const GARE_TYPES = ["ter", "tgv"];

const TYPE_SYMBOLS = {
  ter: "train",
  tgv: "tgv",
  tram: "tram",
  telepherique: "aerial-tram",
  bus: "bus-stop",
};

// Couleur de fond par type (aussi utilisée pour les points « hors topo » des cartes).
const TYPE_COLORS = {
  ter: "#222",
  tgv: "#a00",
  // Bus, tram et téléphérique : fond doré commun (cf. docs/conception-bus.md, étape 5)
  tram: "gold",
  telepherique: "gold",
  bus: "gold",
};

/**
 * @param {string[]} types Sous-ensemble de ter/tgv/bus/tram/telepherique.
 * @returns {string} Type dominant (toujours une valeur, "bus" par défaut).
 */
export function arretTypeDominant(types) {
  const set = new Set(types || []);
  for (const t of TYPE_PRIORITY) {
    if (set.has(t)) return t;
  }
  return "bus";
}

/** @returns {string} id du symbole SVG (public_html/symbols/icons.svg) pour ce type dominant. */
export function arretIconSymbol(typeDominant) {
  return TYPE_SYMBOLS[typeDominant] || TYPE_SYMBOLS.bus;
}

/** @returns {string} couleur de fond de l'icône pour ce type dominant. */
export function arretIconColor(typeDominant) {
  return TYPE_COLORS[typeDominant] || TYPE_COLORS.bus;
}

/**
 * Gabarit HTML de l'icône d'un arrêt, indépendant de la librairie cartographique
 * (Leaflet : contenu d'un `L.divIcon` ; MapLibre : contenu de l'élément d'un Marker).
 * @param {string[]} types
 * @param {number} [size=24] Taille de référence (taille effective des gares ;
 *   les autres modalités sont rendues plus petites, cf. GARE_TYPES ci-dessus).
 * @param {Object} [options={}]
 * @param {string} [options.color] Couleur de fond forcée (sinon déduite du type dominant).
 * @returns {{ html: string, size: number }} `size` = taille effective en px.
 */
export function arretIconHtml(types, size = 24, options = {}) {
  const typeDominant = arretTypeDominant(types);
  const symbol = arretIconSymbol(typeDominant);
  const color = options.color || arretIconColor(typeDominant);
  const isGare = GARE_TYPES.includes(typeDominant);
  const effectiveSize = isGare ? size : Math.round(size * 0.75);
  const borderRadius = isGare ? "50%" : `${Math.round(effectiveSize * 0.3)}px`;
  const inner = Math.round(effectiveSize * 0.6);
  return {
    size: effectiveSize,
    html:
      `<div style="width:${effectiveSize}px;height:${effectiveSize}px;background:${color};border-radius:${borderRadius};" ` +
      `class="border-2 border-white flex items-center justify-center shadow">` +
      `<svg class="fill-current text-white" style="width:${inner}px;height:${inner}px;">` +
      `<use href="#${symbol}"></use>` +
      `</svg></div>`,
  };
}

/**
 * @param {string[]} types
 * @param {number} [size=24]
 * @param {Object} [options={}]
 * @param {string} [options.color] Couleur de fond forcée (sinon déduite du type dominant).
 * @param {string} [options.extraClassName] Classes CSS additionnelles sur le conteneur de l'icône.
 * @returns {L.DivIcon}
 */
export function arretDivIcon(types, size = 24, options = {}) {
  const { html, size: effectiveSize } = arretIconHtml(types, size, options);
  return L.divIcon({
    className: `vg-arret-icon${options.extraClassName ? " " + options.extraClassName : ""}`,
    html,
    iconSize: [effectiveSize, effectiveSize],
    iconAnchor: [effectiveSize / 2, effectiveSize / 2],
    tooltipAnchor: [effectiveSize / 2, 0],
    popupAnchor: [0, -effectiveSize / 2],
  });
}

/** @returns {boolean} true pour une gare (ter/tgv), dont le rendu diffère des autres modalités. */
export function arretEstGare(types) {
  return GARE_TYPES.includes(arretTypeDominant(types));
}

/**
 * Point intermédiaire (zoom moyen) d'un arrêt relié à une falaise : petit rond
 * coloré par type dominant, bord blanc. Cliquable comme l'icône complète.
 * @param {string[]} types
 * @param {number} [size=12]
 * @param {Object} [options={}]
 * @param {string} [options.extraClassName] Classes CSS additionnelles sur le conteneur du point.
 * @returns {L.DivIcon}
 */
export function arretDotIcon(types, size = 12, options = {}) {
  const color = arretIconColor(arretTypeDominant(types));
  return L.divIcon({
    className: `vg-arret-dot${options.extraClassName ? " " + options.extraClassName : ""}`,
    html: `<div style="width:${size}px;height:${size}px;background:${color};" class="rounded-full border-2 border-white shadow"></div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    tooltipAnchor: [size / 2, 0],
    popupAnchor: [0, -size / 2],
  });
}

/**
 * Icône minimaliste (petit rond coloré, bord blanc) pour les propositions
 * Overpass — volontairement distincte de `arretDivIcon` (icône + symbole)
 * pour ne pas laisser croire qu'une suggestion est déjà un arrêt enregistré.
 * @param {string[]} types
 * @param {number} [size=10]
 * @returns {L.DivIcon}
 */
export function arretSuggestionIcon(types, size = 10) {
  const color = arretIconColor(arretTypeDominant(types));
  return L.divIcon({
    className: "vg-arret-suggestion-icon",
    html:
      `<div style="width:${size}px;height:${size}px;background:${color};" ` +
      `class="rounded-full border-2 border-white shadow"></div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    tooltipAnchor: [size / 2, 0],
    popupAnchor: [0, -size / 2],
  });
}

/**
 * Icône générique pour le marqueur temporaire de dessin manuel d'un arrêt
 * (avant ouverture du dialog qui détermine sa/ses modalité(s)) — symbole
 * neutre `#arret` plutôt qu'un symbole de modalité qui n'est pas encore connue.
 * @param {number} [size=24]
 * @returns {L.DivIcon}
 */
export function arretPlaceholderIcon(size = 24) {
  const inner = Math.round(size * 0.6);
  return L.divIcon({
    className: "vg-arret-icon",
    html:
      `<div style="width:${size}px;height:${size}px;background:#6b7280;" ` +
      `class="rounded-full border-2 border-white flex items-center justify-center shadow">` +
      `<svg class="fill-current text-white" style="width:${inner}px;height:${inner}px;">` +
      `<use href="#arret"></use>` +
      `</svg></div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    tooltipAnchor: [size / 2, 0],
    popupAnchor: [0, -size / 2],
  });
}
