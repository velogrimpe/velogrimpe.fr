/**
 * « Voir la ligne » (Lot 7) : trace une ligne BTT (table `lignes_transport`)
 * dans sa couleur avec un halo blanc, et marque les arrêts qu'elle dessert.
 * Version Leaflet (carte globale, page falaise) ; la carte MapLibre a sa
 * propre implémentation (carte.php).
 *
 * Le panneau d'info de la carte globale (InfoPanel.vue) déclenche
 * `velogrimpe:voir-ligne` (detail = { lignes } ou null) ; les cartes l'écoutent
 * et délèguent ici.
 *
 * @module ligne-view
 */

export const LIGNE_DEFAULT_COLOR = "#2563eb";

/** Couleur CSS valide, sinon couleur par défaut. */
export function ligneColor(ligne) {
  const c = (ligne?.couleur || "").trim();
  return c && CSS.supports("color", c) ? c : LIGNE_DEFAULT_COLOR;
}

/**
 * @param {L.Map} map
 * @param {Object} ligne Ligne telle que renvoyée par /api/fetch_lignes.php?arret_id=
 *   (geometry GeoJSON MultiLineString, couleur, arrets [{id, lat, lng}]).
 * @param {Object} [options]
 * @param {boolean} [options.fit=true] Cadre la carte sur la ligne.
 * @param {Array<{lat:number, lng:number}>} [options.arrets] Arrêts à mettre en évidence
 *   (par défaut `ligne.arrets`).
 * @returns {L.LayerGroup} À retirer avec `group.remove()`.
 */
export function showLigne(map, ligne, options = {}) {
  const color = ligneColor(ligne);
  const group = L.layerGroup();
  const lines = ligne.geometry?.coordinates ?? [];
  // GeoJSON [lng, lat] → Leaflet [lat, lng]
  const latlngs = lines.map((line) => line.map(([lng, lat]) => [lat, lng]));
  if (latlngs.length) {
    L.polyline(latlngs, {
      color: "#fff",
      weight: 10,
      opacity: 0.95,
      lineCap: "round",
      lineJoin: "round",
      interactive: false,
      className: "vg-ligne-halo",
    }).addTo(group);
    L.polyline(latlngs, {
      color,
      weight: 5,
      opacity: 1,
      lineCap: "round",
      lineJoin: "round",
      interactive: false,
      className: "vg-ligne-voir",
    }).addTo(group);
  }
  (options.arrets ?? ligne.arrets ?? []).forEach((a) => {
    const dot = L.circleMarker([a.lat, a.lng], {
      radius: 6,
      color: "#fff",
      weight: 2,
      fillColor: color,
      fillOpacity: 1,
      className: "vg-ligne-arret-ring",
    }).addTo(group);
    if (a.nom) dot.bindTooltip(String(a.nom), { direction: "top" });
  });
  group.addTo(map);
  if (options.fit !== false) {
    const pts = [
      ...latlngs.flat(),
      ...(options.arrets ?? ligne.arrets ?? []).map((a) => [a.lat, a.lng]),
    ];
    if (pts.length) {
      map.flyToBounds(L.latLngBounds(pts), {
        paddingTopLeft: [40, 50],
        paddingBottomRight: [100, 200],
        duration: 0.5,
      });
    }
  }
  return group;
}
