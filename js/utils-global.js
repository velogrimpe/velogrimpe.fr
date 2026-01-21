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
    var velo_apieduniquement = velo.velo_apieduniquement;
    var km = parseFloat(velo_km);
    var dplus = parseInt(velo_dplus, 10);
    var isWalking = velo_apieduniquement === "1" || velo_apieduniquement === 1;

    var time_in_hours;
    if (isWalking) {
      time_in_hours = km / 4 + dplus / 500;
    } else {
      time_in_hours = km / 20 + dplus / 500;
    }
    return Math.round(time_in_hours * 60);
  }

  window.velogrimpe = window.velogrimpe || {};
  window.velogrimpe.format_time = format_time;
  window.velogrimpe.calculate_time = calculate_time;

  // Also expose as globals for backward compatibility
  window.format_time = format_time;
  window.calculate_time = calculate_time;
})();
