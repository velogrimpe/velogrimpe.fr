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
 * Durée de marche en minutes, ou null dès qu'un paramètre (km, D+) manque : pas de durée
 * approximative. Formule historique des itinéraires « à pied uniquement » : 4 km/h + 500 m de
 * D+ par heure (le D- n'entre pas dans le calcul).
 */
export function calculate_time_pied({ km, dplus } = {}) {
  const valeurs = [km, dplus].map((v) => (v === null || v === undefined || v === "" ? NaN : parseFloat(v)));
  if (valeurs.some(Number.isNaN)) return null;
  const [k, dp] = valeurs;
  return Math.round((k / 4 + dp / 500) * 60);
}

/**
 * Contenu (HTML) de l'infobulle d'une trace d'accès à pied : la durée de marche si km et D+
 * sont renseignés, sinon la distance ; dans les deux cas suivie d'une petite
 * icône de pas. null si on n'a rien à afficher.
 */
export function pied_tooltip_html({ km, dplus, dmoins } = {}) {
  const minutes = calculate_time_pied({ km, dplus });
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
