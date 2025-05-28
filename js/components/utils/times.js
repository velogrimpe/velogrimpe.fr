export function format_time(minutes) {
  if (minutes === null) {
    return "";
  }
  const hours = Math.floor(minutes / 60);
  const remaining_minutes = minutes % 60;

  if (hours > 0) {
    return `${hours}h${remaining_minutes.toString().padStart(2, "0")}`;
  } else {
    return `${remaining_minutes}&apos;`;
  }
}
export function calculate_time(velo) {
  const { velo_km, velo_dplus, velo_apieduniquement } = velo;
  let time_in_hours;
  if (velo_apieduniquement == "1") {
    time_in_hours = parseFloat(velo_km) / 4 + parseInt(velo_dplus) / 500;
  } else {
    time_in_hours = parseFloat(velo_km) / 20 + parseInt(velo_dplus) / 500;
  }
  const time_in_minutes = Math.round(time_in_hours * 60);
  return time_in_minutes;
}
