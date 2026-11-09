/**
 * Global utilities for inline scripts (non-module)
 * Attach to window.velogrimpe for use in carte.php inline scripts
 */
(function() {
  function format_time(minutes) {
    if (minutes === null || minutes === undefined) {
      return "";
    }
    var hours = Math.floor(minutes / 60);
    var remaining_minutes = minutes % 60;

    if (hours > 0) {
      return hours + "h" + remaining_minutes.toString().padStart(2, "0");
    } else {
      return remaining_minutes + "'";
    }
  }

  function calculate_time(velo) {
    var velo_km = velo.velo_km;
    var velo_dplus = velo.velo_dplus;
    var km = parseFloat(velo_km);
    var dplus = parseInt(velo_dplus, 10);

    var time_in_hours = km / 20 + dplus / 500;
    return Math.round(time_in_hours * 60);
  }

  // Durée de marche (minutes) ou null si km, D+ ou D- manque — même formule que
  // js/components/utils/times.js (DIN 33466 : 4 km/h, +300 m/h, -500 m/h, max + min/2).
  function calculate_time_pied(p) {
    var vals = [p.km, p.dplus, p.dmoins].map(function (v) {
      return v === null || v === undefined || v === "" ? NaN : parseFloat(v);
    });
    if (vals.some(isNaN)) return null;
    var horizontal = vals[0] / 4;
    var vertical = vals[1] / 300 + vals[2] / 500;
    return Math.round((Math.max(horizontal, vertical) + Math.min(horizontal, vertical) / 2) * 60);
  }

  // Infobulle d'une trace d'accès à pied : durée si tout est renseigné, sinon distance, + icône de pas.
  function pied_tooltip_html(p) {
    var minutes = calculate_time_pied(p);
    var texte = minutes !== null ? format_time(minutes)
      : (p.km !== null && p.km !== undefined && p.km !== "" ? p.km + " km" : null);
    if (texte === null) return null;
    return texte + ' <svg class="inline-block w-4 h-4 fill-current align-text-bottom" aria-label="à pied"><use href="#footprint"></use></svg>';
  }

  // Sanitize a string before injecting into innerHTML (prevents DOM-based XSS)
  function escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  window.velogrimpe = window.velogrimpe || {};
  window.velogrimpe.format_time = format_time;
  window.velogrimpe.calculate_time = calculate_time;
  window.velogrimpe.calculate_time_pied = calculate_time_pied;
  window.velogrimpe.pied_tooltip_html = pied_tooltip_html;
  window.velogrimpe.escapeHtml = escapeHtml;

  // Also expose as globals for backward compatibility
  window.format_time = format_time;
  window.calculate_time = calculate_time;
  window.calculate_time_pied = calculate_time_pied;
  window.pied_tooltip_html = pied_tooltip_html;
  window.escapeHtml = escapeHtml;
})();
