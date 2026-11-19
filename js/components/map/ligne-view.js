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

/** Interpolation linéaire par morceaux d'une valeur selon le zoom, bornée aux extrémités. */
function parZoom(zoom, [z0, v0], [z1, v1]) {
  const t = Math.min(1, Math.max(0, (zoom - z0) / (z1 - z0)));
  return v0 + (v1 - v0) * t;
}

/** Libellé « numéro — nom » d'une ligne (survol, infobulles). */
export function ligneTitre(ligne) {
  return [ligne?.numero, ligne?.nom].filter(Boolean).join(" — ");
}

/**
 * Tracé permanent de lignes de transport, mêmes règles de mise en forme que la
 * couche `lignes-all` de carte.php : couleur de la ligne sur halo blanc (90 %),
 * largeurs interpolées de z8 à z14 (trait 1 → 3 px, halo 2 → 6 px). Au survol du
 * tracé, bulle « numéro — nom ». Contrairement à `showLigne`, ni arrêts ni recadrage.
 *
 * @param {L.Map} map
 * @param {Array} lignes Lignes avec `geometry` (MultiLineString), `couleur`, `numero`, `nom`.
 * @returns {L.LayerGroup} À retirer avec `group.remove()` (l'écouteur de zoom suit).
 */
export function showLignesFond(map, lignes) {
  const group = L.layerGroup();
  const paires = [];
  lignes.forEach((ligne) => {
    const coords = ligne.geometry?.coordinates ?? [];
    const latlngs = coords.map((line) => line.map(([lng, lat]) => [lat, lng]));
    if (!latlngs.length) return;
    const halo = L.polyline(latlngs, {
      color: "#fff",
      opacity: 0.9,
      lineCap: "round",
      lineJoin: "round",
      className: "vg-ligne-fond-halo",
    }).addTo(group);
    const titre = ligneTitre(ligne);
    if (titre) halo.bindTooltip(titre, { sticky: true, direction: "top" });
    const trait = L.polyline(latlngs, {
      color: ligneColor(ligne),
      opacity: 1,
      lineCap: "round",
      lineJoin: "round",
      interactive: false,
      className: "vg-ligne-fond",
    }).addTo(group);
    paires.push({ halo, trait });
  });
  const ajuster = () => {
    const z = map.getZoom();
    paires.forEach(({ halo, trait }) => {
      halo.setStyle({ weight: parZoom(z, [8, 2], [14, 6]) });
      trait.setStyle({ weight: parZoom(z, [8, 1], [14, 3]) });
    });
  };
  ajuster();
  map.on("zoomend", ajuster);
  group.on("remove", () => map.off("zoomend", ajuster));
  group.addTo(map);
  return group;
}

/**
 * Lignes de transport en fond de l'éditeur : trait gris très fin, sans halo ni
 * interaction (le tracé de l'éditeur ne doit pas être gêné), pour repérer où
 * passent les lignes.
 *
 * @param {L.Map} map
 * @param {Array} lignes Lignes avec `geometry`.
 * @returns {L.LayerGroup}
 */
export function showLignesGrises(map, lignes) {
  const group = L.layerGroup();
  lignes.forEach((ligne) => {
    const coords = ligne.geometry?.coordinates ?? [];
    if (!coords.length) return;
    L.polyline(
      coords.map((line) => line.map(([lng, lat]) => [lat, lng])),
      { color: "#6b7280", weight: 1, opacity: 0.8, interactive: false, className: "vg-ligne-grise" },
    ).addTo(group);
  });
  return group.addTo(map);
}
