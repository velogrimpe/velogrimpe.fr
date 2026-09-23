import { overpassFetch } from "./overpass.js";

const overpassQuery = (south, west, north, east) => `
[out:json][timeout:25];
(
  node["natural"~"^(cliff|rock|stone)$"]["sport"="climbing"]["name"](${south},${west},${north},${east});
  way["natural"~"^(cliff|rock|stone)$"]["sport"="climbing"]["name"](${south},${west},${north},${east});
);
out body;
out center qt;
`;

function normalizeStr(s) {
  return (s ?? "").trim();
}

function parseCliffs(overpassJson) {
  const elements = overpassJson.elements || [];
  const cliffs = [];

  for (const el of elements) {
    if (el.type !== "node" && el.type !== "way") continue;

    const name = normalizeStr(el.tags?.name);
    if (!name) continue; // déjà filtré côté Overpass, sécurité supplémentaire

    const lat = el.type === "node" ? el.lat : el.center?.lat;
    const lon = el.type === "node" ? el.lon : el.center?.lon;
    if (typeof lat !== "number" || typeof lon !== "number") continue;

    cliffs.push({
      id: el.id,
      osm_type: el.type,
      osm_id: `${el.type}/${el.id}`,
      tags: el.tags || {},
      name,
      lat,
      lon,
    });
  }

  return cliffs;
}

export const fetchCliffs = async (mapOrBounds, options = {}) => {
  const overpassJson = await overpassFetch(mapOrBounds, overpassQuery, options);
  return parseCliffs(overpassJson);
};
