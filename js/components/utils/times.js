/**
 * Format minutes as human-readable time (e.g., "1h30" or "45'")
 */
export function format_time(minutes) {
  if (minutes === null || minutes === undefined) {
    return "";
  }
  const hours = Math.floor(minutes / 60);
  const remaining_minutes = minutes % 60;

  if (hours > 0) {
    return `${hours}h${remaining_minutes.toString().padStart(2, "0")}`;
  } else {
    return `${remaining_minutes}'`;
  }
}

/**
 * Calculate bike travel time in minutes
 * Formula: km/20 + D+/500
 */
export function calculate_time(velo) {
  const { velo_km, velo_dplus } = velo;
  const km = parseFloat(velo_km);
  const dplus = parseInt(velo_dplus, 10);
  const time_in_hours = km / 20 + dplus / 500;
  return Math.round(time_in_hours * 60);
}

/**
 * Durée de marche en minutes, ou null dès qu'un paramètre (km, D+, D-) manque : pas de
 * durée approximative. Norme DIN 33466 : 4 km/h à plat, 300 m/h en montée, 500 m/h en
 * descente ; durée = max(horizontal, vertical) + min(horizontal, vertical) / 2.
 */
export function calculate_time_pied({ km, dplus, dmoins } = {}) {
  const valeurs = [km, dplus, dmoins].map((v) => (v === null || v === undefined || v === "" ? NaN : parseFloat(v)));
  if (valeurs.some(Number.isNaN)) return null;
  const [k, dp, dm] = valeurs;
  const horizontal = k / 4;
  const vertical = dp / 300 + dm / 500;
  return Math.round((Math.max(horizontal, vertical) + Math.min(horizontal, vertical) / 2) * 60);
}

/**
 * Contenu (HTML) de l'infobulle d'une trace d'accès à pied : la durée de marche si km, D+
 * et D- sont tous renseignés, sinon la distance ; dans les deux cas suivie d'une petite
 * icône de pas. null si on n'a rien à afficher.
 */
export function pied_tooltip_html({ km, dplus, dmoins } = {}) {
  const minutes = calculate_time_pied({ km, dplus, dmoins });
  const texte = minutes !== null ? format_time(minutes) : km !== null && km !== undefined && km !== "" ? `${km} km` : null;
  if (texte === null) return null;
  return `${texte} <svg class="inline-block w-4 h-4 fill-current align-text-bottom" aria-label="à pied"><use href="#footprint"></use></svg>`;
}

// Attach to window for use in inline scripts
if (typeof window !== 'undefined') {
  window.velogrimpe = window.velogrimpe || {};
  window.velogrimpe.format_time = format_time;
  window.velogrimpe.calculate_time = calculate_time;
  window.velogrimpe.calculate_time_pied = calculate_time_pied;
  window.velogrimpe.pied_tooltip_html = pied_tooltip_html;
}
