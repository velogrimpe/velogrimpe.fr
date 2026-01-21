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
 * Calculate bike/walk travel time in minutes
 * Formula: km/20 + D+/500 for bike, km/4 + D+/500 for walking
 */
export function calculate_time(velo) {
  const { velo_km, velo_dplus, velo_apieduniquement } = velo;
  const km = parseFloat(velo_km);
  const dplus = parseInt(velo_dplus, 10);
  const isWalking = velo_apieduniquement === "1" || velo_apieduniquement === 1;

  let time_in_hours;
  if (isWalking) {
    time_in_hours = km / 4 + dplus / 500;
  } else {
    time_in_hours = km / 20 + dplus / 500;
  }
  return Math.round(time_in_hours * 60);
}

// Attach to window for use in inline scripts
if (typeof window !== 'undefined') {
  window.velogrimpe = window.velogrimpe || {};
  window.velogrimpe.format_time = format_time;
  window.velogrimpe.calculate_time = calculate_time;
}
